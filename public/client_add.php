<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/location_options.php';
require_once __DIR__ . '/../app/csrf_compat.php';


require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/mikrotik.php';
require_once __DIR__ . '/../app/package_profile.php';
@include_once __DIR__ . '/../app/audit.php'; // (বাংলা) থাকলে অডিট লগ করবো

if (function_exists('require_perm')) {
    require_perm('add.client');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ==================== Audit wrapper (client context) ==================== */
// বাংলা: বিভিন্ন প্রোজেক্টে audit_log() সিগনেচার আলাদা হতে পারে।
// এখানে সেফ-কলার রাখলাম: আগে (action,'client',id,details) ট্রাই করবে,
// টাইপ এরর হলে (action,id,details) ট্রাই করবে, না পারলে চুপ করে ফেল করবে।
function audit_client(string $action, int $client_id, array $details = []): void {
    if (!function_exists('audit_log')) return;
    try {
        audit_log('client', $client_id, $action, null, $details);
    } catch (Throwable $e) { /* ignore */ }
}

/* ==================== Helpers ==================== */
// (বাংলা) টেবিলের কলাম আছে কিনা — একবার চেক করে cache করি
function db_has_column(string $table, string $column): bool {
    static $cache = [];
    if (!isset($cache[$table])) {
        $rows = db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        $cache[$table] = array_flip($rows ?: []);
    }
    return isset($cache[$table][$column]);
}

function ensure_option_present(array $options, string $value): array {
    $value = trim($value);
    if ($value !== '' && !in_array($value, $options, true)) {
        array_unshift($options, $value);
    }
    return $options;
}

/**
 * Save uploaded photo using PPPoE ID as filename: <pppoe-id>.<ext>
 * Overwrites any existing same-name file.
 * @return array ['ok'=>bool, 'url'=>?string, 'error'=>?string]
 * (বাংলা) নতুন ইউজারের ছবির জন্য pppoe_id দিয়ে ফাইলনেম বানিয়ে সেভ করি।
 */
function save_photo_with_pppoe_filename(string $pppoe_id): array {
    $out = ['ok'=>true, 'url'=>null, 'error'=>null];

    if (empty($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $out; // no file selected
    }

    $f = $_FILES['photo'];
    if ($f['error'] !== UPLOAD_ERR_OK) { $out['ok']=false; $out['error']='Upload failed.'; return $out; }

    $maxBytes = 3 * 1024 * 1024; // 3MB
    if ((int)$f['size'] > $maxBytes) { $out['ok']=false; $out['error']='Max 3MB allowed.'; return $out; }

    $mime = function_exists('finfo_open') ? (function($tmp){
        $fi=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($fi,$tmp); finfo_close($fi); return $m;
    })($f['tmp_name']) : mime_content_type($f['tmp_name']);

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) { $out['ok']=false; $out['error']='Only JPG/PNG/WebP.'; return $out; }

    $upDir = __DIR__ . '/../uploads/clients';
    if (!is_dir($upDir)) { @mkdir($upDir, 0775, true); }

    // (বাংলা) PPPoE আইডি স্যানিটাইজ করে ফাইলনেম বানাই
    $slug = strtolower($pppoe_id);
    $slug = preg_replace('/[^a-z0-9-_]+/i', '-', $slug);
    $slug = trim($slug, '-_');
    if ($slug === '') $slug = 'client';

    $ext   = $allowed[$mime];
    $fname = $slug.'.'.$ext;
    $dest  = $upDir . '/' . $fname;
    $destWeb = '/uploads/clients/'.$fname;

    if (file_exists($dest)) @unlink($dest);
    if (!move_uploaded_file($f['tmp_name'], $dest)) { $out['ok']=false; $out['error']='Could not save file.'; return $out; }

    $out['url'] = $destWeb;
    return $out;
}

/* ==================== Invoice helpers ==================== */
// (বাংলা) ইনভয়েস স্কিমা-ফ্ল্যাগস
function invoice_schema(): array {
    $has = fn($c)=> db_has_column('invoices', $c);
    $amount_target = $has('total') ? 'total' : ($has('payable') ? 'payable' : ($has('amount') ? 'amount' : null));
    return [
        'has_invoice_number' => $has('invoice_number'),
        'has_status'         => $has('status'),
        'has_is_void'        => $has('is_void'),
        'has_created'        => $has('created_at'),
        'has_updated'        => $has('updated_at'),
        'has_billing_month'  => $has('billing_month'),
        'has_invoice_date'   => $has('invoice_date'),
        'has_due_date'       => $has('due_date'),
        'has_period_start'   => $has('period_start'),
        'has_period_end'     => $has('period_end'),
        'has_remarks'        => $has('remarks'),
        'has_subtotal'       => $has('subtotal'),
        'has_total_amount'   => $has('total_amount'),
        'has_paid_amount'    => $has('paid_amount'),
        'amount_target'      => $amount_target,
    ];
}

// (বাংলা) নতুন ক্লায়েন্ট হলে বর্তমান মাসের বিল অটো-জেনারেট করি
function create_first_invoice(int $client_id, float $amount, ?int $package_id = null): array {
    $pdo = db();
    $sch = invoice_schema();
    if (!$sch['amount_target']) {
        return ['ok'=>false, 'message'=>'No suitable amount column (total/payable/amount) in invoices table.'];
    }
    $has_ledger = db_has_column('clients','ledger_balance');

    $ym_start = date('Y-m-01');
    $ym_end   = date('Y-m-t', strtotime($ym_start));

    $pdo->beginTransaction();
    try {
        // (বাংলা) একই মাসে পুরনো ইনভয়েস থাকলে void + লেজার থেকে minus
        $rangeExpr = $sch['has_billing_month'] ? "billing_month BETWEEN ? AND ?" :
                    ($sch['has_invoice_date'] ? "DATE(invoice_date) BETWEEN ? AND ?" :
                    ($sch['has_created'] ? "DATE(created_at) BETWEEN ? AND ?" : null));

        if ($rangeExpr) {
            $sqlOld = "SELECT id, ".$sch['amount_target']." AS amt FROM invoices
                       WHERE client_id=? AND $rangeExpr " .
                       ($sch['has_is_void'] ? " AND COALESCE(is_void,0)=0" : "") .
                       ($sch['has_status']  ? " AND status <> 'void' " : "");
            $stOld = $pdo->prepare($sqlOld);
            $stOld->execute([$client_id, $ym_start, $ym_end]);
            $olds = $stOld->fetchAll(PDO::FETCH_ASSOC);
            if ($olds) {
                if ($sch['has_is_void'])      $void = $pdo->prepare("UPDATE invoices SET is_void=1 ".($sch['has_updated']?", updated_at=NOW()":"")." WHERE id=?");
                elseif ($sch['has_status'])   $void = $pdo->prepare("UPDATE invoices SET status='void' ".($sch['has_updated']?", updated_at=NOW()":"")." WHERE id=?");
                else                          $void = $pdo->prepare("DELETE FROM invoices WHERE id=?");

                foreach($olds as $o){
                    if ($has_ledger) {
                        $pdo->prepare("UPDATE clients SET ledger_balance = ledger_balance + :d WHERE id=:cid")
                           ->execute([':d'=> (float)$o['amt'], ':cid'=>$client_id]);
                    }
                    $void->execute([(int)$o['id']]);
                    audit_client('invoice_void', $client_id, ['invoice_id'=>(int)$o['id']]);
                }
            }
        }

        // (বাংলা) নতুন ইনভয়েস insert
        $cols = ['client_id', $sch['amount_target']];
        $vals = [':client_id', ':amount'];
        if ($sch['has_invoice_number']) { $cols[]='invoice_number'; $vals[]=':invoice_number'; }
        if ($sch['has_billing_month'])  { $cols[]='billing_month';  $vals[]=':billing_month'; }
        if ($sch['has_invoice_date'])   { $cols[]='invoice_date';   $vals[]=':invoice_date'; }
        if ($sch['has_due_date'])       { $cols[]='due_date';       $vals[]=':due_date'; }
        if ($sch['has_period_start'])   { $cols[]='period_start';   $vals[]=':period_start'; }
        if ($sch['has_period_end'])     { $cols[]='period_end';     $vals[]=':period_end'; }
        if ($sch['has_status'])         { $cols[]='status';         $vals[]="'unpaid'"; }
        if ($sch['has_remarks'])        { $cols[]='remarks';        $vals[]=':remarks'; }
        if ($sch['has_subtotal'])       { $cols[]='subtotal';       $vals[]=':subtotal'; }
        if ($sch['has_total_amount'])   { $cols[]='total_amount';   $vals[]=':total_amount'; }
        if ($sch['has_paid_amount'])    { $cols[]='paid_amount';    $vals[]=':paid_amount'; }
        if ($sch['has_created'])        { $cols[]='created_at';     $vals[]='NOW()'; }
        if ($sch['has_updated'])        { $cols[]='updated_at';     $vals[]='NOW()'; }

        $sqlIns = "INSERT INTO invoices (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
        $ins = $pdo->prepare($sqlIns);

        $invNo = null;
        if ($sch['has_invoice_number']) {
            $rand = strtoupper(substr(bin2hex(random_bytes(2)),0,4));
            $invNo = 'INV-'.date('Ym').'-'.$client_id.'-'.$rand;
        }
        $remarks = $sch['has_remarks'] ? ('Auto created on client add (pkg='.$package_id.')') : null;

        $ins->bindValue(':client_id', $client_id, PDO::PARAM_INT);
        $ins->bindValue(':amount', $amount);
        if ($sch['has_invoice_number']) $ins->bindValue(':invoice_number', $invNo);
        if ($sch['has_billing_month'])  $ins->bindValue(':billing_month', $ym_start);
        if ($sch['has_invoice_date'])   $ins->bindValue(':invoice_date', date('Y-m-d'));
        if ($sch['has_due_date'])       $ins->bindValue(':due_date', date('Y-m-d', strtotime('+7 days')));
        if ($sch['has_period_start'])   $ins->bindValue(':period_start', $ym_start);
        if ($sch['has_period_end'])     $ins->bindValue(':period_end', $ym_end);
        if ($sch['has_remarks'])        $ins->bindValue(':remarks', $remarks);
        if ($sch['has_subtotal'])       $ins->bindValue(':subtotal', $amount);
        if ($sch['has_total_amount'])   $ins->bindValue(':total_amount', $amount);
        if ($sch['has_paid_amount'])    $ins->bindValue(':paid_amount', 0);
        $ins->execute();
        $inv_id = (int)$pdo->lastInsertId();

        // (বাংলা) লেজার += amount
        if ($has_ledger) {
            $pdo->prepare("UPDATE clients SET ledger_balance = ledger_balance - :d WHERE id=:cid")
               ->execute([':d'=>$amount, ':cid'=>$client_id]);
        }

        audit_client('invoice_create', $client_id, ['invoice_id'=>$inv_id,'total'=>$amount]);

        $pdo->commit();
        return ['ok'=>true, 'invoice_id'=>$inv_id, 'amount'=>$amount];
    } catch(Throwable $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'message'=>$e->getMessage()];
    }
}

function normalize_mac_for_lookup(?string $mac): ?string {
    if(!$mac) return null;
    $hex = strtolower(preg_replace('/[^0-9a-f]/', '', (string)$mac));
    if(strlen($hex) !== 12) return null;
    return implode(':', str_split($hex, 2));
}

/* ==================== Date helpers ==================== */
function normalize_day_only_date(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
    if (preg_match('/^\d{1,2}$/', $raw)) {
        $day = (int)$raw;
        if ($day <= 0) return '';
        $nextMonth = strtotime('first day of next month');
        $year = (int)date('Y', $nextMonth);
        $month = (int)date('m', $nextMonth);
        $daysInMonth = (int)date('t', $nextMonth);
        if ($day > $daysInMonth) $day = $daysInMonth;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    return $raw;
}

function auto_link_client_olt_from_cache(PDO $pdo, int $client_id, array $fields): array {
    $tokens = [];
    $addToken = static function($val) use (&$tokens) {
        $val = trim((string)$val);
        if($val !== '') $tokens[] = $val;
    };
    $addToken($fields['pppoe_id'] ?? '');
    $addToken($fields['client_code'] ?? '');
    $addToken($fields['name'] ?? '');
    $addToken($fields['mobile'] ?? '');

    if(!$tokens) return ['ok'=>false, 'reason'=>'no tokens'];

    $fetchRow = static function(string $sql, array $params) use ($pdo): ?array {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    $match = null;
    try {
        foreach($tokens as $token){
            $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE LOWER(description)=? ORDER BY learned_at DESC LIMIT 1", [strtolower($token)]);
            if($match) break;
        }
        if(!$match){
            foreach($tokens as $token){
                $mac = normalize_mac_for_lookup($token);
                if(!$mac) continue;
                $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE REPLACE(LOWER(mac),':','') = ? ORDER BY learned_at DESC LIMIT 1", [strtolower(str_replace(':','',$mac))]);
                if($match) break;
            }
        }
    } catch(Throwable $e){
        return ['ok'=>false, 'reason'=>'lookup_failed'];
    }

    if(!$match) return ['ok'=>false, 'reason'=>'not_found'];

    $onuText = (string)($match['onu'] ?? '');
    $onuId = null;
    if(preg_match('/(\d+)/', $onuText, $m)){
        $onuId = (string)(int)$m[1];
    }
    $macAddr = normalize_mac_for_lookup($match['mac'] ?? '') ?? ($match['mac'] ?? null);
    $vendor = null;
    try{
        $st = $pdo->prepare("SELECT vendor FROM olts WHERE id=? LIMIT 1");
        $st->execute([(int)$match['olt_id']]);
        $vendor = $st->fetchColumn() ?: null;
    }catch(Throwable $e){}

    try{
        $upd = $pdo->prepare("UPDATE clients
                              SET olt_id=:oid,
                                  olt_vendor=:vendor,
                                  olt_port=:port,
                                  olt_onu=:onu,
                                  caller_mac=:mac,
                                  last_linked_at=:linked
                              WHERE id=:id");
        $upd->execute([
            ':oid' => $match['olt_id'] ?? null,
            ':vendor' => $vendor,
            ':port' => $match['port'] ?? null,
            ':onu' => $onuId,
            ':mac' => $macAddr,
            ':linked' => $match['learned_at'] ?? null,
            ':id' => $client_id,
        ]);
    }catch(Throwable $e){
        return ['ok'=>false, 'reason'=>'update_failed'];
    }

    $portLabel = (string)($match['port'] ?? '');
    if($portLabel !== '' && $onuId){
        try{
            if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $portLabel, $pm)){
                $family = strtolower($pm[1]);
                $slot = (int)$pm[2];
                $iface = '0/'.$slot;
                $rxVal = null;
                if(isset($match['rx_power_dbm']) && $match['rx_power_dbm'] !== '' && is_numeric($match['rx_power_dbm'])){
                    $rxVal = (float)$match['rx_power_dbm'];
                }
                $status = strtolower((string)($match['status'] ?? 'online'));
                $invSql = "INSERT INTO onu_inventory (olt_id,family,iface,onu_id,client_id,is_active,last_status,last_rx_dbm,last_updated)
                           VALUES (?,?,?,?,?,1,?,?,?)
                           ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), last_status=VALUES(last_status), last_rx_dbm=VALUES(last_rx_dbm), last_updated=VALUES(last_updated)";
                $pdo->prepare($invSql)->execute([
                    (int)$match['olt_id'],
                    $family,
                    $iface,
                    (int)$onuId,
                    $client_id,
                    $status !== '' ? $status : 'online',
                    $rxVal,
                    $match['learned_at'] ?? null,
                ]);
                if($macAddr){
                    $invIdStmt = $pdo->prepare("SELECT id FROM onu_inventory WHERE olt_id=? AND family=? AND iface=? AND onu_id=? LIMIT 1");
                    $invIdStmt->execute([(int)$match['olt_id'], $family, $iface, (int)$onuId]);
                    $targetId = (int)($invIdStmt->fetchColumn() ?: 0);
                    if($targetId > 0){
                        $mapSql = "INSERT INTO onu_mac_map (target_id, mac, last_seen)
                                   VALUES (?, ?, ?)
                                   ON DUPLICATE KEY UPDATE mac=VALUES(mac), last_seen=VALUES(last_seen)";
                        $pdo->prepare($mapSql)->execute([$targetId, $macAddr, $match['learned_at'] ?? date('Y-m-d H:i:s')]);
                    }
                }
            }
        }catch(Throwable $e){
            // ignore inventory sync failures
        }
    }

    return [
        'ok'=>true,
        'port'=>$portLabel,
        'onu'=>$onuId,
        'olt'=>$match['olt_id'] ?? null,
    ];
}

/* ==================== Load dropdowns ==================== */
$packages = db()->query("SELECT id, name, price, router_id, profile, profile_name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$routers  = db()->query("SELECT id, name, ip, username, password, api_port FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ensure mobile column is not forced unique (drop legacy index if present)
try {
    db()->exec("ALTER TABLE clients DROP INDEX uq_mobile");
} catch (Throwable $e) {
    // ignore if index missing
}

/* ==================== Optional columns present? ==================== */
$HAS_CLIENT_CODE = db_has_column('clients','client_code');
// Client code field deprecated; hide from UI and skip persistence
$HAS_CLIENT_CODE = false;
$HAS_SUB_ZONE    = db_has_column('clients','sub_zone');
$HAS_BOX         = db_has_column('clients','box');
$HAS_JOIN_DATE   = db_has_column('clients','join_date');
$HAS_NID         = db_has_column('clients','nid');
$HAS_DOB         = db_has_column('clients','dob');
$HAS_PHOTO_URL   = db_has_column('clients','photo_url');
$HAS_PPPOE_PASS  = db_has_column('clients','pppoe_pass');
$HAS_PPPOE_PASSWORD = db_has_column('clients','pppoe_password');
$HAS_UPDATED_AT  = db_has_column('clients','updated_at');
$HAS_CREATED_AT  = db_has_column('clients','created_at');
$BILLING_STATUS_COL = db_has_column('clients','billing_status')
  ? 'billing_status'
  : (db_has_column('clients','payment_status') ? 'payment_status' : '');
$ADVANCE_COLS = array_values(array_filter(
  ['advance','advance_balance','advance_amount','prepaid','wallet_advance'],
  fn($c) => db_has_column('clients', $c)
));

// Preload dropdown data (ম্যাপড তালিকা)
$pdoOptions      = db();
$area_options    = location_option_list($pdoOptions, 'area');
$subzone_options = $HAS_SUB_ZONE ? location_option_list($pdoOptions, 'sub_zone') : [];
$box_options     = $HAS_BOX ? location_option_list($pdoOptions, 'box') : [];
$LOC_CSRF        = csrf_ensure_token();
$csrf_form       = csrf_ensure_token();

/* ==================== Handle POST ==================== */
$errors = [];
$notice = null;
$new_id = null;
$new_invoice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }
    // (বাংলা) ইনপুট নিন
    $client_code  = trim($_POST['client_code'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $mobile       = preg_replace('/\D+/', '', trim($_POST['mobile'] ?? ''));
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $join_date    = trim($_POST['join_date'] ?? date('Y-m-d'));
    $area         = trim($_POST['area'] ?? '');
    $sub_zone     = trim($_POST['sub_zone'] ?? '');
    $box          = trim($_POST['box'] ?? '');
    $nid          = trim($_POST['nid'] ?? '');
    $dob          = trim($_POST['dob'] ?? '');
    $pppoe_id     = trim($_POST['pppoe_id'] ?? '');
    $pppoe_pass   = trim($_POST['pppoe_pass'] ?? '');
    $ppp_profile  = trim($_POST['ppp_profile'] ?? '');
    $package_id   = (int)($_POST['package_id'] ?? 0);
    $router_id    = (int)($_POST['router_id']  ?? 0);
    $selectedPackage = null;
    $monthly_bill = isset($_POST['monthly_bill']) && is_numeric($_POST['monthly_bill']) ? (float)$_POST['monthly_bill'] : 0.0;
    $expiry_date  = normalize_day_only_date((string)($_POST['expiry_date'] ?? ''));
    $status       = trim($_POST['status'] ?? 'active');
    $auto_invoice = 1; // always generate first invoice on client add

    // (বাংলা) ভ্যালিডেশন
    if ($name === '')        $errors[] = 'Name is required.';
    if ($HAS_CLIENT_CODE && $client_code === '') $errors[] = 'Client Code is required.';
    if ($HAS_JOIN_DATE && $join_date === '') $errors[] = 'Join date is required.';
    if ($area === '')        $errors[] = 'Area is required.';
    if ($HAS_SUB_ZONE && $sub_zone === '') $errors[] = 'Sub Zone is required.';
    if ($HAS_BOX && $box === '') $errors[] = 'Box is required.';
    if ($mobile === '')      $errors[] = 'Mobile is required.';
    if ($mobile !== '' && !preg_match('/^\d{11}$/', $mobile)) $errors[] = 'Mobile must be exactly 11 digits.';
    if ($pppoe_id === '')    $errors[] = 'PPPoE username is required.';
    if ($package_id <= 0)    $errors[] = 'Please select a package.';
    if ($monthly_bill < 0)   $errors[] = 'Monthly bill is invalid.';

    // (বাংলা) PPPoE uniqueness
    $chk = db()->prepare("SELECT id FROM clients WHERE pppoe_id = ? LIMIT 1");
    $chk->execute([$pppoe_id]);
    if ($chk->fetch()) $errors[] = 'PPPoE username already exists.';

    // (বাংলা) Mobile দেওয়া থাকলে তবেই ডুপ্লিকেট চেক
    // duplicate mobile check skipped per requirement

    // (বাংলা) Photo upload (filename = ppppoe_id)
    $photo_url = null;
    if ($HAS_PHOTO_URL) {
        $ph = save_photo_with_pppoe_filename($pppoe_id);
        if (!$ph['ok']) $errors[] = $ph['error'] ?? 'Photo upload failed.';
        $photo_url = $ph['url'];
    }

    if ($package_id > 0) {
        foreach ($packages as $pkgRow) {
            if ((int)$pkgRow['id'] === $package_id) {
                $selectedPackage = $pkgRow;
                break;
            }
        }
        if (!$selectedPackage) {
            $errors[] = 'Selected package is invalid.';
        }
    }
    $package_price = 0.0;
    if ($selectedPackage && is_numeric($selectedPackage['price'] ?? null)) {
        $package_price = (float)$selectedPackage['price'];
        if ($package_price > 0) {
            $monthly_bill = $package_price;
            $_POST['monthly_bill'] = (string)$monthly_bill;
        }
    }
    if (!$router_id && $selectedPackage && !empty($selectedPackage['router_id'])) {
        $router_id = (int)$selectedPackage['router_id'];
        $_POST['router_id'] = (string)$router_id;
    }
    if (!$router_id && count($routers) === 1) {
        $router_id = (int)$routers[0]['id'];
        $_POST['router_id'] = (string)$router_id;
    }

if (!$errors) {
    // (বাংলা) ডায়নামিক INSERT তৈরি + ট্রানজেকশন
    $pdo = db(); $pdo->beginTransaction();
    $mtSyncNote = null;
    $autoOltResult = null;
    try {
            $cols = ['name','mobile','email','address','area','pppoe_id','package_id','router_id','monthly_bill','status'];
            $vals = [':name',':mobile',':email',':address',':area',':pppoe_id',':package_id',':router_id',':monthly_bill',':status'];
            $params = [
                ':name'=>$name,
                ':mobile'=> $mobile,
                ':email'=> ($email === '') ? null : $email,
                ':address'=> ($address === '') ? null : $address,
                ':area'=> $area,
                ':pppoe_id'=>$pppoe_id,
                ':package_id'=>$package_id,
                ':router_id'=>$router_id ?: null,
                ':monthly_bill'=>$monthly_bill,
                ':status'=>$status
            ];

            if ($HAS_CLIENT_CODE) { $cols[]='client_code'; $vals[]=':client_code'; $params[':client_code']= $client_code; }
            if ($HAS_SUB_ZONE)    { $cols[]='sub_zone';     $vals[]=':sub_zone';     $params[':sub_zone']= $sub_zone; }
            if ($HAS_BOX)         { $cols[]='box';          $vals[]=':box';          $params[':box']= $box; }
            if ($HAS_JOIN_DATE)   { $cols[]='join_date';    $vals[]=':join_date';    $params[':join_date']= $join_date ?: date('Y-m-d'); }
            if ($HAS_NID)         { $cols[]='nid';          $vals[]=':nid';          $params[':nid']= ($nid==='')? null : $nid; }
            if ($HAS_DOB)         { $cols[]='dob';          $vals[]=':dob';          $params[':dob']= ($dob==='')? null : $dob; }
            if ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD)  {
                $pppoe_store = ($pppoe_pass === '') ? $pppoe_id : $pppoe_pass;
                if ($HAS_PPPOE_PASS) {
                    $cols[]='pppoe_pass';   $vals[]=':pppoe_pass';   $params[':pppoe_pass']= $pppoe_store;
                } else {
                    $cols[]='pppoe_password';   $vals[]=':pppoe_pass';   $params[':pppoe_pass']= $pppoe_store;
                }
            }
            if ($HAS_PHOTO_URL)   { $cols[]='photo_url';    $vals[]=':photo_url';    $params[':photo_url']= $photo_url; }
            if ($expiry_date!==''){ $cols[]='expiry_date';  $vals[]=':expiry_date';  $params[':expiry_date']= $expiry_date; }
            if ($HAS_CREATED_AT)  { $cols[]='created_at';   $vals[]='NOW()'; }
            if ($HAS_UPDATED_AT)  { $cols[]='updated_at';   $vals[]='NOW()'; }
            if ($BILLING_STATUS_COL !== '') { $cols[]=$BILLING_STATUS_COL; $vals[]=':billing_status'; $params[':billing_status']='unpaid'; }
            foreach ($ADVANCE_COLS as $c) {
                $cols[] = $c;
                $vals[] = ':adv_'.$c;
                $params[':adv_'.$c] = 0;
            }

            $sql = "INSERT INTO clients (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
            $ins = $pdo->prepare($sql);
            $ins->execute($params);
            $new_id = (int)$pdo->lastInsertId();

            if ($router_id && $pppoe_id) {
                $profileName = ($ppp_profile !== '') ? $ppp_profile : ($selectedPackage ? package_ppp_profile_name($selectedPackage) : null);
                $commentParts = array_filter([$name, $mobile], function($v){ return !empty($v); });
                $comment = $commentParts ? implode(' | ', $commentParts) : '';
                $pppPass = ($pppoe_pass === '') ? $pppoe_id : $pppoe_pass;
                $secret = mikrotik_ensure_pppoe_secret((int)$router_id, $pppoe_id, $pppPass, $profileName, ['comment'=>$comment]);
                if (!$secret['ok']) {
                    throw new RuntimeException('MikroTik sync failed: '.$secret['error']);
                }
                $mtSyncNote = $secret['action'] ?? 'created';
            }

            if ($new_id) {
                $autoOltResult = auto_link_client_olt_from_cache($pdo, $new_id, [
                    'pppoe_id' => $pppoe_id,
                    'client_code' => $HAS_CLIENT_CODE ? $client_code : '',
                    'name' => $name,
                    'mobile' => $mobile,
                ]);
            }

            $pdo->commit();
            $notice = 'Client created successfully.';
            if ($mtSyncNote === 'created') {
                $notice .= ' (PPP secret added)';
            } elseif ($mtSyncNote === 'updated') {
                $notice .= ' (PPP secret updated)';
            }
            if ($autoOltResult && !empty($autoOltResult['ok'])) {
                $notice .= ' (OLT auto-linked)';
            }
            audit_client('client_create', $new_id, [
                'pppoe_id' => $pppoe_id,
                'name'     => $name,
                'mobile'   => $mobile,
                'router_id'=> $router_id,
                'package_id'=>$package_id,
            ]);
        } catch(Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Save failed: '.$e->getMessage();
        }

        // (বাংলা) সেভ সফল হলে এবং অটো-ইনভয়েস চাইলে — বর্তমান মাসের বিল বানাও
        $invoice_amount = ($package_price > 0) ? $package_price : $monthly_bill;
        if ($new_id && !$errors && $auto_invoice && $invoice_amount > 0) {
            $new_invoice = create_first_invoice($new_id, (float)$invoice_amount, $package_id);
            if (!$new_invoice['ok']) {
                $notice .= ' (Invoice skipped: '.$new_invoice['message'].')';
            } else {
                $notice .= ' (Invoice #'.$new_invoice['invoice_id'].' created)';
            }
        }
    }
}

include __DIR__ . '/../partials/partials_header.php';
?>
<style>
.card-block{ border:6px solid #dfe3e8; border-radius:.75rem; background:#f5f6f8; }
.card-block .card-title{ font-weight:700; padding:.65rem .9rem; border-bottom:2px solid #dfe3e8; background:#e9ecef; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.form-text.small{ font-size:.8rem; }
.req::after{ content:" *"; color:#dc3545; font-weight:700; }
</style>




<?php if (hasPermission('add.client')){ ?>



<div class="container-fluid py-3 text-start">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-person-plus"></i> Add Client</h6>
    <div class="d-flex gap-2">
      <!-- <button type="submit" form="client-add-form" class="btn btn-primary btn-sm">
        <i class="bi bi-save2"></i> Create Client
      </button> -->
      <button type="submit" form="client-add-form" class="btn btn-outline-secondary btn-sm" >Save</button>
      <?php if ($new_id): ?>
        <a class="btn btn-light btn-sm" href="/public/client_view.php?id=<?= (int)$new_id ?>"><i class="bi bi-eye"></i> View</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= h(implode(' | ', $errors)) ?></div>
  <?php elseif ($notice): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle"></i> <?= h($notice) ?></div>
  <?php endif; ?>

  <?php if (!$HAS_PHOTO_URL): ?>
    <div class="alert alert-warning py-2">
      <strong>Heads up:</strong> photo cannot be saved because <code>clients.photo_url</code> column is missing.
      Run once: <code>ALTER TABLE clients ADD COLUMN photo_url VARCHAR(255) NULL;</code>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="client-add-form">
    <input type="hidden" name="csrf" value="<?= h($csrf_form) ?>">
    <div class="row g-3">
      <!-- Account -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">
            <span style="font-weight: 700;font-size: 14px;font-weight: 700;"><i class="far fa-user icon-gap"></i> Personal Information</span>
            <br>
            <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 300;">*</span>) Field Data</span>
          </div>
          <div class="p-3">
		  
            <div class="mb-2">
              <label class="form-label req">Customer Name</label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="" value="<?= h($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="mb-2">
              <label class="form-label req">Mobile Number</label>
              <input type="text" name="mobile" pattern="\d{11}" maxlength="11" inputmode="numeric" class="form-control form-control-sm" value="<?= h($_POST['mobile'] ?? '') ?>" required>
              <!-- <div class="form-text small">Enter 11-digit mobile number (digits only).</div> -->
            </div>

            
            <div class="mb-2">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control form-control-sm" value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <?php if ($HAS_NID): ?>
            <div class="mb-2">
              <label class="form-label">NID No.</label>
              <input type="text" name="nid" class="form-control form-control-sm" value="<?= h($_POST['nid'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <?php if ($HAS_DOB): ?>
            <div class="mb-2">
              <label class="form-label">DOB</label>
              <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($_POST['dob'] ?? '') ?>">
            </div>
            <?php endif; ?>


            <?php if ($HAS_CLIENT_CODE): ?>
            <div class="mb-2">
              <label class="form-label req">Client Code</label>
              <input type="text" name="client_code" class="form-control form-control-sm" value="<?= h($_POST['client_code'] ?? '') ?>" required>
            </div>
            <?php endif; ?>

            <?php $form_area = (string)($_POST['area'] ?? ''); $area_opts = ensure_option_present($area_options, $form_area); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Area</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="area"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="area" class="form-select form-select-sm" data-loc-type="area" required>
                <option value="">Select</option>
                <?php foreach ($area_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_area===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($HAS_SUB_ZONE): ?>
            <?php $form_sub = (string)($_POST['sub_zone'] ?? ''); $sub_opts = ensure_option_present($subzone_options, $form_sub); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Sub Zone</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="sub_zone"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="sub_zone" class="form-select form-select-sm" data-loc-type="sub_zone" required>
                <option value="">Select</option>
                <?php foreach ($sub_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_sub===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($HAS_BOX): ?>
            <?php $form_box = (string)($_POST['box'] ?? ''); $box_opts = ensure_option_present($box_options, $form_box); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Box</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="box"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="box" class="form-select form-select-sm" data-loc-type="box" required>
                <option value="">Select</option>
                <?php foreach ($box_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_box===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($HAS_JOIN_DATE): ?>
            <div class="mb-2">
              <label class="form-label req">Join Date</label>
              <input type="date" name="join_date" class="form-control form-control-sm" value="<?= h($_POST['join_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($_POST['address'] ?? '') ?></textarea>
            </div>

          </div>
        </div>
      </div>

      <!-- Billing -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">
            <span style="font-weight: 700;font-size: 14px;font-weight: 700;"><i class="far fa-list-alt"></i> Billing Information</span>
            <br>
            <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 300;">*</span>) Field Data</span>
          </div>

          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">Package</label>
              <select name="package_id" id="package_id" class="form-select form-select-sm" required>
                <option value="">-- Select --</option>
                <?php foreach ($packages as $p): ?>
                  <option
                    value="<?= (int)$p['id'] ?>"
                    data-price="<?= is_numeric($p['price']) ? (0+$p['price']) : 0 ?>"
                    data-router="<?= isset($p['router_id']) ? (int)$p['router_id'] : 0 ?>"
                    <?= (isset($_POST['package_id']) && (int)$_POST['package_id']===(int)$p['id'])?'selected':'' ?>
                  >
                    <?= h($p['name']) ?> <?= is_numeric($p['price'])? '— '.(0+$p['price']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <!-- <div class="form-text small">(Package name = MikroTik PPP profile name 1:1)</div> -->
            </div>

            <div class="mb-2">
              <label class="form-label req">Monthly Bill</label>
              <input type="number" step="0.01" name="monthly_bill" id="monthly_bill" class="form-control form-control-sm" value="<?= h($_POST['monthly_bill'] ?? '0') ?>" required>
              <!-- <div class="form-text small" id="autoHint">Auto from package price when selection changes.</div> -->
            </div>

            <div class="mb-2">
              <label class="form-label">Expiry Date</label>
              <?php
                $exp_raw = (string)($_POST['expiry_date'] ?? '');
                $exp_day = '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp_raw)) {
                  $exp_day = (string)(int)substr($exp_raw, 8, 2);
                } elseif (preg_match('/^\d{1,2}$/', $exp_raw)) {
                  $exp_day = (string)(int)$exp_raw;
                }
              ?>
              <select name="expiry_date" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php for ($d=1; $d<=31; $d++): ?>
                  <option value="<?= $d ?>" <?= $exp_day===(string)$d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php
                  $opts = ['active'=>'Active','inactive'=>'Inactive','pending'=>'Pending','hold'=>'Hold','disabled'=>'Disabled','blocked'=>'Blocked','expired'=>'Expired'];
                  $cur  = strtolower(trim($_POST['status'] ?? 'active'));
                  foreach($opts as $k=>$v){
                    echo '<option value="'.h($k).'"'.($cur===$k?' selected':'').'>'.h($v).'</option>';
                  }
                ?>
              </select>
            </div>

            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="auto_invoice" name="auto_invoice" value="1" <?= isset($_POST['auto_invoice']) ? ( ($_POST['auto_invoice']?'checked':'') ) : 'checked' ?>>
              <!-- <label class="form-check-label" for="auto_invoice">Generate first invoice now</label> -->
            </div>
          </div>
        </div>
      </div>

      <!-- Server / PPP + Photo -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title"><span style="font-weight: 700;font-weight: 700;"><i class="fas fa-wifi icon-gap"></i> Service Information</span>
          <br>
          <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 700;">*</span>) Field Data</span>
        </div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">PPPoE Server</label>
              <select name="router_id" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php foreach ($routers as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (isset($_POST['router_id']) && (int)$_POST['router_id']===(int)$r['id'])?'selected':'' ?>required>
                    <?= h($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div id="router_id" class="form-text small"></div>
            </div>

            <div class="mb-2">
              <label class="form-label req">Username</label>
              <input type="text" name="pppoe_id" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_id'] ?? '') ?>" required>
              <div id="pppoe_status" class="form-text small"></div>
            </div>

            <?php if ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD): ?>
            <div class="mb-2">
              <label class="form-label req">Password</label>
              <input type="text" name="pppoe_pass" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_pass'] ?? '') ?>"required>
            <div id="pppoe_pass" class="form-text small"></div>
            </div>
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label req">Profile</label>
              <select name="ppp_profile" id="ppp_profile" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php if (!empty($_POST['ppp_profile'])): ?>
                  <option value="<?= h($_POST['ppp_profile']) ?>" selected><?= h($_POST['ppp_profile']) ?></option>
                <?php endif; ?>
              </select>
              <div id="ppp_profile" class="form-text small"></div>
            </div>

            <div class="card mt-3">
              <div class="card-header fw-bold">Photo (optional)</div>
              <div class="card-body">
                <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm" <?= $HAS_PHOTO_URL?'':'disabled' ?>required>
                <div class="form-text small">Supported: JPG, PNG • Max 3MB • Filename will be PPPoE-ID</div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3"></div>
  </form>
</div>

<!-- Location Option Modal -->
<div class="modal fade" id="locOptionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="locModalTitle">Add Option</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="locOptionForm">
          <input type="hidden" id="locTypeField" value="">
          <div class="mb-3 d-none" data-field="parent_area">
            <label class="form-label">Zone</label>
            <select class="form-select" id="locParentArea">
              <option value="">Select Zone</option>
            </select>
          </div>
          <div class="mb-3 d-none" data-field="parent_sub_zone">
            <label class="form-label">Sub Zone</label>
            <select class="form-select" id="locParentSubZone">
              <option value="">Select Sub Zone</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="locLabelInput" placeholder="Enter name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Details (optional)</label>
            <textarea class="form-control" id="locDetailInput" rows="3" placeholder="Notes"></textarea>
          </div>
          <div id="locSaveNotice" class="form-text small d-none"></div>
        </form>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger" id="locClearBtn">Clear</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-primary" id="locSaveBtn">Save</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// (বাংলা) প্যাকেজ সিলেক্ট করলে price + default router অটো-সেট
(function(){
  const pkgSel = document.getElementById('package_id');
  const bill   = document.getElementById('monthly_bill');
  const routerSel = document.querySelector('select[name="router_id"]');
  const pppoeInput = document.querySelector('input[name="pppoe_id"]');
  const pppoeStatus = document.getElementById('pppoe_status');
  let routerTouched = false;

  routerSel?.addEventListener('change', () => { routerTouched = true; });

  function updateFromPackage(opts = {}){
    const {forceBill = false, forceRouter = false} = opts;
    if (!pkgSel) return;
    const opt = pkgSel.options?.[pkgSel.selectedIndex];
    if (!opt) return;

    const price = parseFloat(opt.dataset?.price || '0');
    if (!isNaN(price) && bill) {
      if (forceBill || !bill.value || parseFloat(bill.value) === 0) {
        bill.value = price.toFixed(2);
      }
    }

    if (routerSel) {
      const rid = parseInt(opt.dataset?.router || '0', 10);
      if (rid && (forceRouter ? !routerSel.value : (!routerTouched || !routerSel.value))) {
        routerSel.value = String(rid);
      }
    }
  }

  pkgSel?.addEventListener('change', () => updateFromPackage({forceBill:true}));
  if (pkgSel) {
    updateFromPackage({forceRouter:true});
  }

  const pppSelect = document.getElementById('ppp_profile');
  async function loadProfiles(routerId) {
    if (!pppSelect) return;
    const curVal = pppSelect.value || '';
    pppSelect.innerHTML = '';
    const baseOpt = document.createElement('option');
    baseOpt.value = '';
    baseOpt.textContent = 'Use package profile';
    pppSelect.appendChild(baseOpt);
    if (!routerId) return;
    try {
      const res = await fetch('../app/ppp_profiles.php?router_id=' + encodeURIComponent(routerId));
      const data = await res.json();
      if (data && data.status === 'success' && Array.isArray(data.profiles)) {
        data.profiles.forEach((name) => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          pppSelect.appendChild(opt);
        });
        if (curVal) {
          pppSelect.value = curVal;
        }
      }
    } catch (e) {
      // ignore profile fetch errors
    }
  }
  if (routerSel) {
    routerSel.addEventListener('change', () => loadProfiles(routerSel.value));
    if (routerSel.value) loadProfiles(routerSel.value);
  }

  let checkTimer = null;
  function setPppoeStatus(msg, cls) {
    if (!pppoeStatus) return;
    pppoeStatus.className = 'form-text small ' + (cls || '');
    pppoeStatus.textContent = msg || '';
  }
  async function checkPppoeSecret() {
    if (!pppoeInput || !routerSel) return;
    const name = (pppoeInput.value || '').trim();
    const rid = routerSel.value;
    if (!rid || !name) {
      setPppoeStatus('', '');
      return;
    }
    setPppoeStatus('Checking...', 'text-muted');
    try {
      const res = await fetch('../api/pppoe_secret_check.php?router_id=' + encodeURIComponent(rid) + '&pppoe_id=' + encodeURIComponent(name));
      const data = await res.json();
      if (data && data.status === 'success') {
        if (data.exists) {
          setPppoeStatus('Already Exists...❌', 'text-danger');
        } else {
          setPppoeStatus('Available ✔', 'text-success');
        }
      } else {
        setPppoeStatus('Check failed', 'text-danger');
      }
    } catch (e) {
      setPppoeStatus('Check failed', 'text-danger');
    }
  }
  function debounceCheck() {
    if (checkTimer) clearTimeout(checkTimer);
    checkTimer = setTimeout(checkPppoeSecret, 400);
  }
  pppoeInput?.addEventListener('input', debounceCheck);
  pppoeInput?.addEventListener('blur', checkPppoeSecret);
  routerSel?.addEventListener('change', debounceCheck);
})();
</script>

<script>
(function(){
  const typeLabels = {area:'Area', sub_zone:'Sub Zone', box:'Box'};
  const csrf = <?= json_encode($LOC_CSRF, JSON_UNESCAPED_UNICODE) ?>;
  let currentType = null;
  const modalEl = document.getElementById('locOptionModal');
  let modalInstance = null;
  const titleEl = document.getElementById('locModalTitle');
  const form = document.getElementById('locOptionForm');
  const typeField = document.getElementById('locTypeField');
  const labelInput = document.getElementById('locLabelInput');
  const detailInput = document.getElementById('locDetailInput');
  const noticeEl = document.getElementById('locSaveNotice');
  const clearBtn = document.getElementById('locClearBtn');
  const saveBtn = document.getElementById('locSaveBtn');
  const parentAreaWrap = document.querySelector('[data-field="parent_area"]');
  const parentSubWrap = document.querySelector('[data-field="parent_sub_zone"]');
  const parentAreaSelect = document.getElementById('locParentArea');
  const parentSubSelect = document.getElementById('locParentSubZone');
  const areaSelect = document.querySelector('select[data-loc-type="area"]');
  const subZoneSelect = document.querySelector('select[data-loc-type="sub_zone"]');
  let areaCache = null;
  let subZoneCache = null;

  async function refreshSelect(type, selectedValue){
    const sel = document.querySelector(`select[data-loc-type="${type}"]`);
    if (!sel) return;
    try {
      const res = await fetch(`/ajax/location_options.php?type=${encodeURIComponent(type)}`, {cache:'no-store'});
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to load list');
      const keep = selectedValue ?? sel.value;
      const opts = Array.isArray(data.items) ? data.items : (Array.isArray(data.options) ? data.options : []);
      sel.innerHTML = '<option value="">Select</option>';
      opts.forEach(val => {
        const opt = document.createElement('option');
        const label = typeof val === 'string' ? val : (val.label || '');
        opt.value = label;
        opt.textContent = label;
        sel.appendChild(opt);
      });
      if (keep) {
        if (!opts.includes(keep)) {
          const extra = document.createElement('option');
          extra.value = keep;
          extra.textContent = keep;
          sel.appendChild(extra);
        }
        sel.value = keep;
      }
    } catch (err) {
      console.error(err);
      alert(err.message || 'Could not refresh options.');
    }
  }

  async function fetchFull(type){
    const url = `/ajax/location_options.php?type=${encodeURIComponent(type)}&full=1`;
    const res = await fetch(url, {cache:'no-store'});
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'Failed to load list');
    return Array.isArray(json.items) ? json.items : [];
  }

  async function ensureAreaCache(){
    if (!areaCache) {
      areaCache = await fetchFull('area');
    }
    return areaCache;
  }
  async function ensureSubZoneCache(){
    if (!subZoneCache) {
      subZoneCache = await fetchFull('sub_zone');
    }
    return subZoneCache;
  }

  async function populateParentArea(selectedValue){
    if (!parentAreaSelect) return;
    const data = await ensureAreaCache();
    parentAreaSelect.innerHTML = '<option value="">Select Zone</option>';
    data.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentAreaSelect.appendChild(opt);
    });
  }

  async function populateParentSub(areaValue, selectedValue){
    if (!parentSubSelect) return;
    const data = await ensureSubZoneCache();
    parentSubSelect.innerHTML = '<option value="">Select Sub Zone</option>';
    const filtered = areaValue ? data.filter(row => row.parent_area === areaValue) : data;
    filtered.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentSubSelect.appendChild(opt);
    });
    parentSubSelect.disabled = filtered.length === 0;
  }

  parentAreaSelect?.addEventListener('change', () => {
    if (currentType === 'box') {
      populateParentSub(parentAreaSelect.value || '', '');
    }
  });

  function ensureModal(){
    if (modalInstance) return modalInstance;
    const bs = window.bootstrap || null;
    if (!modalEl || !bs || !bs.Modal) return null;
    modalInstance = new bs.Modal(modalEl);
    return modalInstance;
  }

  async function openModal(type){
    currentType = type;
    const modal = ensureModal();
    if (!modal) {
      notify('Cannot open form because Bootstrap modal is unavailable.', 'danger', 'Error');
      return;
    }
    typeField.value = type;
    if (titleEl) titleEl.textContent = 'Add ' + (typeLabels[type] || 'Option');
    form?.reset();
    clearNotice();
    const showArea = (type === 'sub_zone' || type === 'box');
    const showSub = (type === 'box');
    parentAreaWrap?.classList.toggle('d-none', !showArea);
    parentSubWrap?.classList.toggle('d-none', !showSub);
    if (showArea) {
      const defaultArea = areaSelect?.value || '';
      await populateParentArea(defaultArea);
      if (showSub) {
        const defaultSub = subZoneSelect?.value || '';
        await populateParentSub(parentAreaSelect.value || defaultArea, defaultSub);
      }
    }
    modal.show();
  }

  clearBtn?.addEventListener('click', () => {
    form?.reset();
    clearNotice();
    parentAreaSelect && (parentAreaSelect.value = '');
    parentSubSelect && (parentSubSelect.value = '');
    parentSubSelect && (parentSubSelect.disabled = false);
    labelInput?.focus();
  });

  function notify(message, type = 'info', title = null){
    if (window.globalToast) {
      window.globalToast(message, type, 3200, title);
      return;
    }
    alert(message);
  }

  function setNotice(message, type = 'info'){
    if (!noticeEl) return;
    noticeEl.textContent = message || '';
    noticeEl.classList.remove('d-none', 'text-success', 'text-danger', 'text-muted');
    if (type === 'success') {
      noticeEl.classList.add('text-success');
    } else if (type === 'error') {
      noticeEl.classList.add('text-danger');
    } else {
      noticeEl.classList.add('text-muted');
    }
    if (!message) noticeEl.classList.add('d-none');
  }

  function clearNotice(){
    if (!noticeEl) return;
    noticeEl.textContent = '';
    noticeEl.classList.add('d-none');
    noticeEl.classList.remove('text-success', 'text-danger', 'text-muted');
  }

  async function submitValue(type, label, details){
    try {
      const payload = {type, label, details, csrf_token: csrf};
      if (type !== 'area' && parentAreaSelect && !parentAreaWrap?.classList.contains('d-none')) {
        payload.parent_area = parentAreaSelect.value || '';
      }
      if (type === 'box' && parentSubSelect && !parentSubWrap?.classList.contains('d-none')) {
        payload.parent_sub_zone = parentSubSelect.value || '';
      }
      const res = await fetch('/ajax/location_options.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to save');
      areaCache = null;
      subZoneCache = null;
      await refreshSelect(type, data.value || label);
      if (type === 'area') {
        await populateParentArea(parentAreaSelect?.value || '');
      } else if (type === 'sub_zone' || type === 'box') {
        await populateParentSub(parentAreaSelect?.value || '', parentSubSelect?.value || '');
      }
      setNotice(`Saved: ${(data.value || label).trim()}.`, 'success');
      notify('Saved successfully.', 'success', 'Success');
      const modal = ensureModal();
      modal?.hide();
      form?.reset();
    } catch (err) {
      const msg = err?.message || 'Could not save option.';
      setNotice(msg, 'error');
      notify(msg, 'danger', 'Error');
    }
  }

  saveBtn?.addEventListener('click', () => {
    const type = currentType;
    const label = (labelInput?.value ?? '').trim();
    const details = (detailInput?.value ?? '').trim();
    if (!type || !label) {
      setNotice('Please enter a value.', 'error');
      notify('Please enter a value.', 'warning', 'Notice');
      return;
    }
    if (type === 'sub_zone' && parentAreaSelect && !parentAreaSelect.value) {
      setNotice('Please select a zone first.', 'error');
      notify('Please select a zone first.', 'warning', 'Notice');
      parentAreaSelect.focus();
      return;
    }
    if (type === 'box') {
      if (parentAreaSelect && !parentAreaSelect.value) {
        setNotice('Please select a zone first.', 'error');
        notify('Please select a zone first.', 'warning', 'Notice');
        parentAreaSelect.focus();
        return;
      }
      if (parentSubSelect && !parentSubSelect.value) {
        setNotice('Please select a sub zone.', 'error');
        notify('Please select a sub zone.', 'warning', 'Notice');
        parentSubSelect.focus();
        return;
      }
    }
    submitValue(type, label, details);
  });

  document.querySelectorAll('[data-loc-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const t = btn.dataset.locAdd;
      if (!t) return;
      openModal(t).catch(err => alert(err.message || 'Failed to open form.'));
    });
  });
})();
</script>


<?php } ?> 





<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
