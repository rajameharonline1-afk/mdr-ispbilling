<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/mikrotik.php';
require_once __DIR__ . '/../app/package_profile.php';
require_once __DIR__ . '/../app/audit.php'; // ✅ Audit helper
require_once __DIR__ . '/../app/location_options.php';
require_once __DIR__ . '/../app/csrf_compat.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --------- helpers --------- */
// (বাংলা) টেবিলের কলাম আছে কিনা — একবার চেক করে cache করি
function db_has_column(string $table, string $column): bool {
    static $cache = [];
    if (!isset($cache[$table])) {
        $rows = db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        $cache[$table] = array_flip($rows ?: []);
    }
    return isset($cache[$table][$column]);
}

function db_enum_values(string $table, string $column): array {
    try {
        $st = db()->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $st->execute([$column]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    if (!$row) return [];
    $type = (string)($row['Type'] ?? '');
    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) return [];
    $vals = str_getcsv($m[1], ',', "'");
    $out = [];
    foreach ($vals as $v) {
        $v = trim($v);
        if ($v !== '') $out[] = $v;
    }
    return $out;
}

function ensure_option_present(array $options, string $value): array {
    $value = trim($value);
    if ($value !== '' && !in_array($value, $options, true)) {
        array_unshift($options, $value);
    }
    return $options;
}

function build_mt_comment(array $data): string {
    $code = trim((string)($data['client_code'] ?? ''));
    if ($code === '' && !empty($data['pppoe_id'])) {
        $digits = preg_replace('/\D+/', '', (string)$data['pppoe_id']);
        if ($digits !== '') $code = substr($digits, -4);
    }
    $map = [
        'client_code' => 'Client Code',
        'name' => 'Client Name',
        'mobile' => 'Contact Number',
        'area' => 'Zone Name',
        'address' => 'Present Address',
        'join_date' => 'Joining Date',
        'package_name' => 'Package Name',
        'monthly_bill' => 'Monthly Bill',
        'expiry_date' => 'Bill Expiry Date',
    ];
    $lines = [];
    foreach ($map as $k => $label) {
        $val = $k === 'client_code' ? $code : trim((string)($data[$k] ?? ''));
        $lines[] = $label.': '.$val;
    }
    return implode(' | ', $lines);
}

function client_code_from_pppoe(string $pppoe_id): string {
    $digits = preg_replace('/\D+/', '', $pppoe_id);
    if ($digits === '') return '';
    return substr($digits, -4);
}

function mikrotik_fetch_pppoe_password(array $client): ?string {
    $pppoe = trim((string)($client['pppoe_id'] ?? ''));
    $ip = trim((string)($client['router_ip'] ?? ''));
    $user = trim((string)($client['r_user'] ?? ''));
    $pass = (string)($client['r_pass'] ?? '');
    $port = isset($client['api_port']) && $client['api_port'] ? (int)$client['api_port'] : 8728;

    if ($pppoe === '' || $ip === '' || $user === '') return null;

    $api = new RouterosAPI();
    $api->debug = false;
    if (!$api->connect($ip, $user, $pass, $port)) return null;

    try {
        $res = $api->comm('/ppp/secret/print', [
            '?name' => $pppoe,
            '.proplist' => 'password',
        ]);
    } catch (Throwable $e) {
        $api->disconnect();
        return null;
    }

    $api->disconnect();
    if (is_array($res) && isset($res[0]['password'])) {
        return (string)$res[0]['password'];
    }
    return null;
}

/**
 * Save uploaded photo for a client using PPPoE ID for the filename.
 * - Filename: <pppoe-id-sanitized>.<ext>  (no random)
 * - Overwrites existing same-name file; removes previous file if path changed.
 *
 * @return array ['ok'=>bool, 'url'=>?string, 'error'=>?string]
 *
 * (বাংলা) pppoe_id থেকে নিরাপদ ফাইলনেম বানিয়ে সেভ করা;
 * আগের ফাইল থাকলে নিরাপদভাবে রিমুভ/ওভাররাইট।
 */
function handle_client_photo_upload(int $client_id, string $pppoe_id, ?string $existing_url = null): array {
    $out = ['ok'=>true, 'url'=>$existing_url, 'error'=>null];

    // Remove request
    if (!empty($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        // delete old file if it lives under uploads/clients
        if ($existing_url && str_starts_with($existing_url, '/uploads/clients/')) {
            $absOld = realpath(__DIR__ . '/..' . $existing_url);
            $baseUploads = realpath(__DIR__ . '/../uploads/clients');
            if ($absOld && $baseUploads && str_starts_with($absOld, $baseUploads)) {
                @unlink($absOld);
            }
        }
        $out['url'] = null;
        return $out;
    }

    // No new file
    if (empty($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $out;
    }

    $f = $_FILES['photo'];
    if ($f['error'] !== UPLOAD_ERR_OK) { $out['ok']=false; $out['error']='Upload failed.'; return $out; }

    // Validate
    $maxBytes = 3 * 1024 * 1024; // 3MB
    if ($f['size'] > $maxBytes) { $out['ok']=false; $out['error']='Max 3MB allowed.'; return $out; }

    $mime = function_exists('finfo_open') ? (function($tmp){
        $fi=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($fi,$tmp); finfo_close($fi); return $m;
    })($f['tmp_name']) : mime_content_type($f['tmp_name']);

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) { $out['ok']=false; $out['error']='Only JPG/PNG/WebP.'; return $out; }

    // Destination dir
    $upDir = __DIR__ . '/../uploads/clients';
    if (!is_dir($upDir)) { @mkdir($upDir, 0775, true); }

    // Sanitize PPPoE ID for filename
    $slug = strtolower($pppoe_id);
    $slug = preg_replace('/[^a-z0-9-_]+/i', '-', $slug);
    $slug = trim($slug, '-_');
    if ($slug === '') $slug = 'client-'.$client_id;

    $ext   = $allowed[$mime];
    $fname = $slug.'.'.$ext;                          // ← no random
    $dest  = $upDir . '/' . $fname;
    $destWeb = '/uploads/clients/'.$fname;

    // If a file with same name exists, overwrite
    if (file_exists($dest)) @unlink($dest);

    // If previous URL is different file, delete that too (keeps storage clean)
    if ($existing_url && $existing_url !== $destWeb && str_starts_with($existing_url, '/uploads/clients/')) {
        $absOld = realpath(__DIR__ . '/..' . $existing_url);
        $baseUploads = realpath($upDir);
        if ($absOld && $baseUploads && str_starts_with($absOld, $baseUploads)) {
            @unlink($absOld);
        }
    }

    if (!move_uploaded_file($f['tmp_name'], $dest)) { $out['ok']=false; $out['error']='Could not save file.'; return $out; }

    $out['url'] = $destWeb;
    return $out;
}

/* ==================== Invoice helpers (expiry-based due) ==================== */
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
        'has_month'          => $has('month'),
        'has_year'           => $has('year'),
        'has_date'           => $has('date'),
        'has_remarks'        => $has('remarks'),
        'has_subtotal'       => $has('subtotal'),
        'has_total_amount'   => $has('total_amount'),
        'has_paid_amount'    => $has('paid_amount'),
        'amount_target'      => $amount_target,
    ];
}

function create_due_invoice_for_expiry(int $client_id, float $amount, string $expiry_date, ?int $package_id = null): array {
    $pdo = db();
    $sch = invoice_schema();
    if (!$sch['amount_target']) {
        return ['ok'=>false, 'message'=>'No suitable amount column (total/payable/amount) in invoices table.'];
    }
    if ($amount <= 0) {
        return ['ok'=>false, 'message'=>'Amount is zero.'];
    }

    $ym = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date) ? substr($expiry_date, 0, 7) : date('Y-m');
    $ym_start = $ym.'-01';
    $ym_end   = date('Y-m-t', strtotime($ym_start));

    // check existing invoice for that month
    $rangeExpr = $sch['has_billing_month'] ? "billing_month BETWEEN ? AND ?"
                : ($sch['has_invoice_date'] ? "DATE(invoice_date) BETWEEN ? AND ?"
                : ($sch['has_month'] && $sch['has_year'] ? "month = ? AND year = ?"
                : ($sch['has_created'] ? "DATE(created_at) BETWEEN ? AND ?" : null)));
    if ($rangeExpr) {
        $sqlOld = "SELECT id FROM invoices WHERE client_id=? AND $rangeExpr".
                  ($sch['has_is_void'] ? " AND COALESCE(is_void,0)=0" : "").
                  ($sch['has_status']  ? " AND status <> 'void' " : "").
                  " LIMIT 1";
        $stOld = $pdo->prepare($sqlOld);
        $params = [$client_id];
        if ($sch['has_month'] && $sch['has_year'] && !$sch['has_billing_month'] && !$sch['has_invoice_date']) {
            $params[] = (int)substr($ym,5,2);
            $params[] = (int)substr($ym,0,4);
        } else {
            $params[] = $ym_start;
            $params[] = $ym_end;
        }
        $stOld->execute($params);
        if ($stOld->fetchColumn()) {
            return ['ok'=>true, 'skipped'=>true];
        }
    }

    $pdo->beginTransaction();
    try {
        $cols = ['client_id', $sch['amount_target']];
        $vals = [':client_id', ':amount'];
        if ($sch['has_invoice_number']) { $cols[]='invoice_number'; $vals[]=':invoice_number'; }
        if ($sch['has_billing_month'])  { $cols[]='billing_month';  $vals[]=':billing_month'; }
        if ($sch['has_month'])          { $cols[]='month';          $vals[]=':month'; }
        if ($sch['has_year'])           { $cols[]='year';           $vals[]=':year'; }
        if ($sch['has_invoice_date'])   { $cols[]='invoice_date';   $vals[]=':invoice_date'; }
        if ($sch['has_due_date'])       { $cols[]='due_date';       $vals[]=':due_date'; }
        if ($sch['has_period_start'])   { $cols[]='period_start';   $vals[]=':period_start'; }
        if ($sch['has_period_end'])     { $cols[]='period_end';     $vals[]=':period_end'; }
        if ($sch['has_date'] && !$sch['has_invoice_date']) { $cols[]='date'; $vals[]=':date'; }
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
            $invNo = 'INV-'.date('Ym', strtotime($ym_start)).'-'.$client_id.'-'.$rand;
        }
        $remarks = $sch['has_remarks'] ? ('Auto created on client edit (pkg='.$package_id.')') : null;

        $ins->bindValue(':client_id', $client_id, PDO::PARAM_INT);
        $ins->bindValue(':amount', $amount);
        if ($sch['has_invoice_number']) $ins->bindValue(':invoice_number', $invNo);
        if ($sch['has_billing_month'])  $ins->bindValue(':billing_month', $ym_start);
        if ($sch['has_month'])          $ins->bindValue(':month', (int)substr($ym,5,2));
        if ($sch['has_year'])           $ins->bindValue(':year', (int)substr($ym,0,4));
        if ($sch['has_invoice_date'])   $ins->bindValue(':invoice_date', $ym_start);
        if ($sch['has_due_date'])       $ins->bindValue(':due_date', date('Y-m-d', strtotime('+7 days', strtotime($ym_start))));
        if ($sch['has_period_start'])   $ins->bindValue(':period_start', $ym_start);
        if ($sch['has_period_end'])     $ins->bindValue(':period_end', $ym_end);
        if ($sch['has_date'] && !$sch['has_invoice_date']) $ins->bindValue(':date', $ym_start);
        if ($sch['has_remarks'])        $ins->bindValue(':remarks', $remarks);
        if ($sch['has_subtotal'])       $ins->bindValue(':subtotal', $amount);
        if ($sch['has_total_amount'])   $ins->bindValue(':total_amount', $amount);
        if ($sch['has_paid_amount'])    $ins->bindValue(':paid_amount', 0);
        $ins->execute();

        if (db_has_column('clients','ledger_balance')) {
            $pdo->prepare("UPDATE clients SET ledger_balance = ledger_balance - :d WHERE id=:cid")
               ->execute([':d'=>$amount, ':cid'=>$client_id]);
        }
        if (db_has_column('clients','billing_status')) {
            $pdo->prepare("UPDATE clients SET billing_status='unpaid' WHERE id=?")->execute([$client_id]);
        }

        $pdo->commit();
        return ['ok'=>true, 'amount'=>$amount];
    } catch(Throwable $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'message'=>$e->getMessage()];
    }
}

/* --------- load client & lists --------- */
$client_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$client_id) { header("Location: clients.php"); exit; }

$sqlClient = "SELECT c.*,
                     p.name AS package_name,
                     r.name AS router_name, r.ip AS router_ip, r.username AS r_user, r.password AS r_pass, r.api_port
              FROM clients c
              LEFT JOIN packages p ON c.package_id = p.id
              LEFT JOIN routers  r ON c.router_id  = r.id
              WHERE c.id = ?";
$st = db()->prepare($sqlClient);
$st->execute([$client_id]);
$client = $st->fetch(PDO::FETCH_ASSOC);
if (!$client) { header("Location: clients.php"); exit; }

$packages = db()->query("SELECT id, name, price, profile, profile_name, router_id FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$routers  = db()->query("SELECT id, name, ip, username, password, api_port FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ensure legacy unique index on mobile is removed to allow duplicates
try {
    db()->exec("ALTER TABLE clients DROP INDEX uq_mobile");
} catch (Throwable $e) {
    // ignore
}

/* --------- optional columns present? --------- */
$SHOW_CLIENT_CODE = false; // client_code deprecated
$HAS_CLIENT_CODE = db_has_column('clients','client_code');
$HAS_SUB_ZONE    = db_has_column('clients','sub_zone');
$HAS_BOX         = db_has_column('clients','box');
$HAS_NID         = db_has_column('clients','nid');
$HAS_DOB         = db_has_column('clients','dob');
$HAS_PHOTO_URL   = db_has_column('clients','photo_url');
$HAS_PPPOE_PASS  = db_has_column('clients','pppoe_pass');
$HAS_PPPOE_PASSWORD = db_has_column('clients','pppoe_password');
$HAS_JOIN_DATE   = db_has_column('clients','join_date');
$HAS_UPDATED_AT  = db_has_column('clients','updated_at');
$HAS_EXPIRY_DATE = db_has_column('clients','expiry_date');
$HAS_EXPIRE_DATE = db_has_column('clients','expire_date');

$client_expiry = '';
if ($HAS_EXPIRY_DATE) {
    $client_expiry = trim((string)($client['expiry_date'] ?? ''));
}
if ($client_expiry === '' && $HAS_EXPIRE_DATE) {
    $client_expiry = trim((string)($client['expire_date'] ?? ''));
}
if ($client_expiry !== '' || $HAS_EXPIRE_DATE) {
    $client['expiry_date'] = $client_expiry;
}
$EXPIRY_COLS = [];
if ($HAS_EXPIRY_DATE) $EXPIRY_COLS[] = 'expiry_date';
if ($HAS_EXPIRE_DATE) $EXPIRY_COLS[] = 'expire_date';

$pdoOptions      = db();
$area_options    = location_option_list($pdoOptions, 'area');
$subzone_options = $HAS_SUB_ZONE ? location_option_list($pdoOptions, 'sub_zone') : [];
$box_options     = $HAS_BOX ? location_option_list($pdoOptions, 'box') : [];
$LOC_CSRF        = csrf_ensure_token();

$status_values = db_enum_values('clients', 'status');
if (!$status_values) {
    $status_values = ['active','inactive','pending','hold','disabled','blocked','expired'];
}
$status_values = array_values(array_unique(array_map('strtolower', $status_values)));
$status_labels = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'pending' => 'Pending',
    'expired' => 'Expired',
    'hold' => 'Hold',
    'disabled' => 'Disabled',
    'blocked' => 'Blocked',
    'left' => 'Left',
];

$pppoe_pass_display = '';
if ($HAS_PPPOE_PASS) {
    $pppoe_pass_display = (string)($client['pppoe_pass'] ?? '');
} elseif ($HAS_PPPOE_PASSWORD) {
    $pppoe_pass_display = (string)($client['pppoe_password'] ?? '');
}
if (($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD) && $pppoe_pass_display === '') {
    $mkPass = mikrotik_fetch_pppoe_password($client);
    if ($mkPass !== null && $mkPass !== '') {
        $pppoe_pass_display = $mkPass;
    }
}

/* --------- date helpers --------- */
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
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : '';
}

/* --------- process save --------- */
$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $successNotes = [];
    $new_client_id = $client_id;
    $prevRouterId   = (int)($client['router_id'] ?? 0);
    $prevPppoeId    = (string)($client['pppoe_id'] ?? '');
    if ($HAS_PPPOE_PASS) {
        $prevPppoePass = (string)($client['pppoe_pass'] ?? '');
    } elseif ($HAS_PPPOE_PASSWORD) {
        $prevPppoePass = (string)($client['pppoe_password'] ?? '');
    } else {
        $prevPppoePass = null;
    }
    $name         = trim($_POST['name'] ?? $client['name']);
    $mobile       = preg_replace('/\D+/', '', trim($_POST['mobile'] ?? $client['mobile']));
    $email        = trim($_POST['email'] ?? ($client['email'] ?? ''));
    $address      = trim($_POST['address'] ?? ($client['address'] ?? ''));
    $area         = trim($_POST['area'] ?? ($client['area'] ?? ''));
    $sub_zone     = trim($_POST['sub_zone'] ?? ($client['sub_zone'] ?? ''));
    $box          = trim($_POST['box'] ?? ($client['box'] ?? ''));
    $nid          = trim($_POST['nid'] ?? ($client['nid'] ?? ''));
    $dob          = trim($_POST['dob'] ?? ($client['dob'] ?? ''));
    $pppoe_id     = trim($_POST['pppoe_id'] ?? $client['pppoe_id']);
    $pppoe_pass   = trim($_POST['pppoe_pass'] ?? ($client['pppoe_pass'] ?? ($client['pppoe_password'] ?? '')));
    $package_id   = intval($_POST['package_id'] ?? $client['package_id']);
    $router_id    = intval($_POST['router_id']  ?? $client['router_id']);
    $monthly_bill_input = $_POST['monthly_bill'] ?? null;
    $userProvidedBill = is_numeric($monthly_bill_input);
    $monthly_bill = $userProvidedBill ? (float)$monthly_bill_input : (0+$client['monthly_bill']);
    $expiry_input = trim((string)($_POST['expiry_date'] ?? ''));
    $expiry_date  = $expiry_input !== '' ? normalize_day_only_date($expiry_input) : '';
    $status       = strtolower(trim($_POST['status'] ?? ($client['status'] ?? '')));
    $prev_expiry  = (string)($client['expiry_date'] ?? '');
    $join_date    = trim($_POST['join_date'] ?? ($client['join_date'] ?? ''));
    $client_code  = $HAS_CLIENT_CODE ? trim((string)($_POST['client_code'] ?? ($client['client_code'] ?? ''))) : '';
    if ($HAS_CLIENT_CODE && $client_code === '') {
        $client_code = client_code_from_pppoe($pppoe_id);
    }

    // Package lookup (align with add flow)
    $selectedPackage = null;
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
        if ($package_price > 0 && !$userProvidedBill) {
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

    if ($name === '')        $errors[] = 'Name is required.';
    if ($area === '')        $errors[] = 'Area is required.';
    if ($HAS_SUB_ZONE && $sub_zone === '') $errors[] = 'Sub Zone is required.';
    if ($HAS_BOX && $box === '') $errors[] = 'Box is required.';
    if ($mobile === '')      $errors[] = 'Mobile is required.';
    if ($mobile !== '' && !preg_match('/^\d{11}$/', $mobile)) $errors[] = 'Mobile must be exactly 11 digits.';
    if ($pppoe_id === '')    $errors[] = 'PPPoE username is required.';
    if ($package_id <= 0)    $errors[] = 'Please select a package.';
    if ($monthly_bill < 0)   $errors[] = 'Monthly bill is invalid.';
    if ($expiry_input !== '' && $expiry_date === '') $errors[] = 'Expiry date is invalid.';
    if ($expiry_input !== '' && !$EXPIRY_COLS) $errors[] = 'Expiry date column is missing in database.';
    if ($status !== '' && !in_array($status, $status_values, true)) $errors[] = 'Invalid status.';
    if ($status === '') $status = strtolower(trim((string)($client['status'] ?? '')));
    $router_id_db = $router_id > 0 ? $router_id : null;

    // duplicate mobile / PPPoE checks skipped per requirement

    // Handle photo (pppoe-based filename)
    $new_photo_url = $client['photo_url'] ?? null;
    if ($HAS_PHOTO_URL) {
        $photoResult = handle_client_photo_upload($client_id, $pppoe_id, $new_photo_url);
        if (!$photoResult['ok']) $errors[] = $photoResult['error'] ?? 'Photo upload failed.';
        $new_photo_url = $photoResult['url'];
    }

    if (!$errors) {
        // Build dynamic UPDATE
        $sets = [
            'name = :name',
            'mobile = :mobile',
            'email = :email',
            'address = :address',
            'area = :area',
            'pppoe_id = :pppoe_id',
            'package_id = :package_id',
            'router_id = :router_id',
            'monthly_bill = :monthly_bill',
            'status = :status'
        ];
        $params = [
            ':name'=>$name,
            ':mobile'=>$mobile,
            ':email'=>($email==='') ? null : $email,
            ':address'=>($address==='') ? null : $address,
            ':area'=>$area,
            ':pppoe_id'=>$pppoe_id, ':package_id'=>$package_id, ':router_id'=>$router_id_db,
            ':monthly_bill'=>$monthly_bill, ':status'=>$status,
            ':id'=>$client_id
        ];
        if ($HAS_CLIENT_CODE && $client_code !== '') { $sets[] = 'client_code = :client_code'; $params[':client_code'] = $client_code; }
        if ($HAS_SUB_ZONE)    { $sets[] = 'sub_zone = :sub_zone';      $params[':sub_zone'] = $sub_zone; }
        if ($HAS_BOX)         { $sets[] = 'box = :box';                $params[':box'] = $box; }
        if ($HAS_NID)         { $sets[] = 'nid = :nid';                $params[':nid'] = $nid === '' ? null : $nid; }
        if ($HAS_DOB)         { $sets[] = 'dob = :dob';                $params[':dob'] = $dob === '' ? null : $dob; }
        if ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD)  {
            $pppoe_store = ($pppoe_pass === '') ? $pppoe_id : $pppoe_pass;
            if ($HAS_PPPOE_PASS) {
                $sets[] = 'pppoe_pass = :pppoe_pass';
                $params[':pppoe_pass'] = $pppoe_store;
            }
            if ($HAS_PPPOE_PASSWORD) {
                $sets[] = 'pppoe_password = :pppoe_password';
                $params[':pppoe_password'] = $pppoe_store;
            }
        }
        if ($HAS_PHOTO_URL)   { $sets[] = 'photo_url = :photo_url';    $params[':photo_url']  = $new_photo_url; }
        if ($HAS_UPDATED_AT)  { $sets[] = 'updated_at = NOW()'; }
        if ($expiry_date !== '') {
            if ($HAS_EXPIRY_DATE) { $sets[] = 'expiry_date = :expiry_date'; $params[':expiry_date'] = $expiry_date; }
            if ($HAS_EXPIRE_DATE) { $sets[] = 'expire_date = :expire_date'; $params[':expire_date'] = $expiry_date; }
        }

        $pdoSave = db();
        $pdoSave->beginTransaction();
        $pdoSave->exec("SET FOREIGN_KEY_CHECKS=0");

        $sqlUp = "UPDATE clients SET ".implode(',', $sets)." WHERE id = :id";
        $u = $pdoSave->prepare($sqlUp);
        $u->execute($params);

        $pdoSave->exec("SET FOREIGN_KEY_CHECKS=1");
        $pdoSave->commit();
        $client_id = $new_client_id;

        // ✅ Audit log: track all changed fields (excluding raw passwords)
        $oldData = [
            'id' => $client['id'] ?? null,
            'client_code' => $client['client_code'] ?? null,
            'name' => $client['name'] ?? null,
            'mobile' => $client['mobile'] ?? null,
            'email' => $client['email'] ?? null,
            'address' => $client['address'] ?? null,
            'area' => $client['area'] ?? null,
            'sub_zone' => $client['sub_zone'] ?? null,
            'box' => $client['box'] ?? null,
            'pppoe_id' => $client['pppoe_id'] ?? null,
            'package_id' => $client['package_id'] ?? null,
            'router_id' => $client['router_id'] ?? null,
            'monthly_bill' => $client['monthly_bill'] ?? null,
            'expiry_date' => $client['expiry_date'] ?? null,
            'status' => $client['status'] ?? null,
        ];
        $effective_expiry = $expiry_date !== '' ? $expiry_date : ($prev_expiry !== '' ? $prev_expiry : null);
        $newData = [
            'id' => $new_client_id,
            'client_code' => $client_code !== '' ? $client_code : null,
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email === '' ? null : $email,
            'address' => $address === '' ? null : $address,
            'area' => $area,
            'sub_zone' => $HAS_SUB_ZONE ? $sub_zone : ($client['sub_zone'] ?? null),
            'box' => $HAS_BOX ? $box : ($client['box'] ?? null),
            'pppoe_id' => $pppoe_id,
            'package_id' => $package_id,
            'router_id' => $router_id_db,
            'monthly_bill' => $monthly_bill,
            'expiry_date' => $effective_expiry,
            'status' => $status,
        ];
        $pppoe_store_compare = ($pppoe_pass === '') ? $pppoe_id : $pppoe_pass;
        if (($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD) && $pppoe_store_compare !== $prevPppoePass) {
            $newData['pppoe_pass_set'] = true;
        }
        $chgOld = [];
        $chgNew = [];
        foreach ($newData as $k => $v) {
            $ov = $oldData[$k] ?? null;
            if ((string)$ov !== (string)$v) {
                $chgOld[$k] = $ov;
                $chgNew[$k] = $v;
            }
        }
        if ($chgOld || $chgNew) {
            audit_log('client', (int)$client_id, 'update', $chgOld, $chgNew);
        }

        $old_pkg_id = intval($client['package_id']);           // পুরনো প্যাকেজ আইডি (সেভের আগের)
        $routerChanged = $router_id && $router_id !== $prevRouterId;
        $pppoeChanged  = $pppoe_id !== $prevPppoeId;
        $passChanged   = ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD) && $pppoe_store_compare !== $prevPppoePass;
        $packageChanged = $package_id && $package_id !== $old_pkg_id;
        // (বাংলা) MikroTik কমেন্টের জন্য যেসব ফিল্ড বদলালে সিক্রেট রিফ্রেশ করতে হবে
        $commentFields = ['name','mobile','area','address','package_id','monthly_bill','expiry_date','client_code','join_date'];
        $commentChanged = false;
        foreach ($commentFields as $field) {
            if (array_key_exists($field, $chgNew)) { $commentChanged = true; break; }
        }

        $expiry_for_comment = $expiry_date !== '' ? $expiry_date : $prev_expiry;
        $needsSecretSync = $router_id && $pppoe_id !== '' && ($routerChanged || $pppoeChanged || $passChanged || $packageChanged || $commentChanged || !$prevRouterId);
        $pkg = null;
        if (($needsSecretSync || $packageChanged) && $package_id) {
            $stp = db()->prepare("SELECT id, name, profile, profile_name FROM packages WHERE id=?");
            $stp->execute([$package_id]);
            $pkg = $stp->fetch(PDO::FETCH_ASSOC);
        }

        if ($needsSecretSync) {
            $profileName = $pkg ? package_ppp_profile_name($pkg) : null;
            $pkgName = $pkg['name'] ?? ($client['package_name'] ?? '');
            if ($pkgName === '' && $package_id) {
                try {
                    $stmtPkgName = db()->prepare("SELECT name FROM packages WHERE id=?");
                    $stmtPkgName->execute([$package_id]);
                    $pkgName = (string)($stmtPkgName->fetchColumn() ?: '');
                } catch (Throwable $e) {
                    $pkgName = $pkgName;
                }
            }
            $comment = build_mt_comment([
                'client_code' => $client_code !== '' ? $client_code : ($client['client_code'] ?? ''),
                'pppoe_id' => $pppoe_id,
                'name' => $name,
                'mobile' => $mobile,
                'area' => $area,
                'address' => $address,
                'join_date' => $HAS_JOIN_DATE ? $join_date : '',
                'package_name' => $pkgName,
                'monthly_bill' => (string)$monthly_bill,
                'expiry_date' => (string)$expiry_for_comment,
            ]);
            $syncPass = ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD) ? (($pppoe_pass === '') ? $pppoe_id : $pppoe_pass) : null;
            $secret = mikrotik_ensure_pppoe_secret((int)$router_id, $pppoe_id, $syncPass, $profileName, ['comment'=>$comment]);
            if (!$secret['ok']) {
                $notice = 'Saved, but MikroTik sync failed: '.$secret['error'];
            } else {
                $successNotes[] = 'PPP secret '.($secret['action'] ?? 'synced').'.';
            }
        }

        if ($packageChanged) {
            // ✅ Audit log: package change
            audit('package_change', 'client', (int)$client_id, [
                'pppoe_id'    => $client['pppoe_id'] ?? '',
                'name'        => $client['name'] ?? '',
                'from_id'     => $old_pkg_id,
                'from_name'   => $client['package_name'] ?? '',
                'to_id'       => (int)$package_id,
                'to_name'     => $pkg['name'] ?? '',
                'router_id'   => (int)($client['router_id'] ?? 0),
                'router_name' => $client['router_name'] ?? '',
            ]);
        }

        // (বাংলা) expiry_date অনুযায়ী due invoice create (যদি আগে না থাকে)
        if ($expiry_date !== '' && $monthly_bill >= 0) {
            // Prefer package price for auto invoice; fallback to monthly_bill
            $inv_amount = $package_price > 0 ? (float)$package_price : (float)$monthly_bill;
            if ($inv_amount <= 0 && $package_id) {
                try{
                    $stpAmt = db()->prepare("SELECT price FROM packages WHERE id=?");
                    $stpAmt->execute([$package_id]);
                    $inv_amount = (float)($stpAmt->fetchColumn() ?: 0);
                }catch(Throwable $e){ $inv_amount = 0; }
            }
            if ($inv_amount > 0 && $expiry_date !== $prev_expiry) {
                $invRes = create_due_invoice_for_expiry((int)$client_id, $inv_amount, $expiry_date, $package_id);
                if (!empty($invRes['ok']) && empty($invRes['skipped'])) {
                    $successNotes[] = 'Due invoice created.';
                }
            }
        }

        // Reload fresh client
        $st = db()->prepare($sqlClient);
        $st->execute([$client_id]);
        $client = $st->fetch(PDO::FETCH_ASSOC);

        $notice = $notice ? ('Saved successfully. '.$notice) : 'Saved successfully.';
        if (!empty($successNotes)) {
            $notice .= ' '.implode(' ', $successNotes);
        }

        // PRG: redirect after POST to avoid resubmission prompt
        $_SESSION['flash_notice'] = $notice;
        header('Location: /public/client_edit.php?id='.(int)$client_id);
        exit;
    }
}

/* --------- UI helpers --------- */
if ($notice === null && isset($_SESSION['flash_notice'])) {
    $notice = $_SESSION['flash_notice'];
    unset($_SESSION['flash_notice']);
}
$photo_url = $HAS_PHOTO_URL ? trim($client['photo_url'] ?? '') : '';
if ($photo_url === '/assets/img/avatar_placeholder.png') {
    $photo_url = '';
}
if ($photo_url !== '' && str_starts_with($photo_url, '/uploads/clients/')) {
    $abs = realpath(__DIR__ . '/..' . $photo_url);
    if (!$abs || !is_file($abs)) {
        $photo_url = '';
    }
}
$client_initial = mb_strtoupper(mb_substr($client['name'] ?? '?', 0, 1, 'UTF-8'));

include __DIR__ . '/../partials/partials_header.php';
// (বাংলা) স্টাইল একীভূত ফাইল থেকে লোড করি
$customCssVer = @filemtime(__DIR__ . '/../assets/css/custom_modern.css') ?: time();
?>
<link rel="stylesheet" href="/assets/css/custom_modern.css?v=<?= $customCssVer ?>">

<div class="container-fluid py-3 text-start client-edit-shell">
  <!-- (বাংলা) হেডার: ক্লায়েন্ট সারাংশ + কুইক মেটা + অ্যাকশন -->
  <div class="mb-3 d-flex flex-wrap align-items-center gap-2 ce-header">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <div class="header-avatar">
        <?php if ($photo_url): ?>
          <img id="topPreview" src="<?= h($photo_url) ?>" alt="<?= h($client['name'] ?? 'Photo') ?>">
        <?php else: ?>
          <img id="photoPreview" src="/assets/images/default-avatar.png" alt="Photo" style="width:100%;height:100%;object-fit:cover">
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <i class="bi bi-person-lines-fill"></i>
        <span class="fw-bold">Edit Client — <?= h($client['name']) ?></span>
        <?php if ($SHOW_CLIENT_CODE && !empty($client['client_code'])): ?>
          <span class="badge bg-secondary mono"><?= h($client['client_code']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex flex-column flex-lg-row gap-2 ms-lg-auto w-100 w-lg-auto">
      <div class="d-flex flex-wrap gap-2 ce-meta-line">
        <span class="ce-chip"><i class="bi bi-person-badge"></i> PPPoE: <?= h($client['pppoe_id'] ?? '-') ?></span>
        <span class="ce-chip"><i class="bi bi-diagram-3"></i> Router: <?= h($client['router_name'] ?? 'N/A') ?></span>
        <span class="ce-chip"><i class="bi bi-geo-alt"></i> Area: <?= h($client['area'] ?? '-') ?></span>
        <?php if (!empty($client['status'])): ?>
          <span class="ce-chip <?= strtolower((string)$client['status'])==='active'?'bg-success text-white':'bg-secondary' ?>">
            <i class="bi bi-activity"></i> <?= h(ucfirst((string)$client['status'])) ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="ms-auto d-flex flex-wrap gap-2 ce-actions">
        <a href="/public/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> View</a>
        <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php"><i class="bi bi-arrow-left"></i> Back</a>
      </div>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= h(implode(' | ', $errors)) ?></div>
  <?php elseif ($notice): ?>
    <div class="alert alert-success" id="pageNotice"><i class="bi bi-check2-circle"></i> <?= h($notice) ?></div>
  <?php endif; ?>

<?php if (!$HAS_PHOTO_URL): ?>
    <div class="alert alert-warning py-2">
      <strong>Heads up:</strong> photo cannot be saved because <code>clients.photo_url</code> column is missing.
      Run once: <code>ALTER TABLE clients ADD COLUMN photo_url VARCHAR(255) NULL;</code>
    </div>
  <?php endif; ?>

  <!-- (বাংলা) মূল ফর্ম: তিনটি কার্ডে ভাগ -->
  <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
    <input type="hidden" name="csrf" value="<?= h($LOC_CSRF) ?>">

    <div class="row g-3">
      <!-- Account + Photo -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title d-flex justify-content-between align-items-center">
            <span>Account</span>
          </div>
          <div class="p-3">
            <?php if ($HAS_CLIENT_CODE): ?>
            <div class="mb-2">
              <label class="form-label req">Client Code</label>
              <input type="text"
                     name="client_code"
                     class="form-control form-control-sm"
                     value="<?= h($_POST['client_code'] ?? ($client['client_code'] ?? '')) ?>"
                     required>
              <!-- <div class="form-text small">Blank হলে PPPoE username-এর শেষ ৪ ডিজিট ব্যবহার হবে।</div> -->
            </div>
            <?php endif; ?>
            <div class="mb-2">
              <label class="form-label req">Name</label>
              <input type="text" name="name" class="form-control form-control-sm" value="<?= h($_POST['name'] ?? $client['name']) ?>" required>
            </div>
            <?php $form_area = isset($_POST['area']) ? (string)$_POST['area'] : (string)($client['area'] ?? ''); $area_opts_form = ensure_option_present($area_options, $form_area); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Area</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="area" title="Add Area" style="text-decoration:none;">
                  <i class="fa-sharp-duotone fa-light fa-plus"></i>
                </button>
              </div>
              <select name="area" class="form-select form-select-sm" data-loc-type="area" required>
                <option value="">Select</option>
                <?php foreach ($area_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_area===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($HAS_SUB_ZONE): ?>
            <?php $form_sub = isset($_POST['sub_zone']) ? (string)$_POST['sub_zone'] : (string)($client['sub_zone'] ?? ''); $sub_opts_form = ensure_option_present($subzone_options, $form_sub); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Sub Zone</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="sub_zone" title="Add Sub Zone" style="text-decoration:none;">
                  <i class="fa-sharp-duotone fa-light fa-plus"></i>
                </button>
              </div>
              <select name="sub_zone" class="form-select form-select-sm" data-loc-type="sub_zone" required>
                <option value="">Select</option>
                <?php foreach ($sub_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_sub===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($HAS_BOX): ?>
            <?php $form_box = isset($_POST['box']) ? (string)$_POST['box'] : (string)($client['box'] ?? ''); $box_opts_form = ensure_option_present($box_options, $form_box); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Box</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="box" title="Add Box" style="text-decoration:none;">
                  <i class="fa-sharp-duotone fa-light fa-plus"></i>
                </button>
              </div>
              <select name="box" class="form-select form-select-sm" data-loc-type="box" required>
                <option value="">Select</option>
                <?php foreach ($box_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_box===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="mb-2">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($_POST['address'] ?? ($client['address'] ?? '')) ?></textarea>
            </div>

            <hr>

            <div class="mb-2">
              <label class="form-label req">Mobile</label>
              <input type="text" name="mobile" pattern="\d{11}" maxlength="11" inputmode="numeric" class="form-control form-control-sm" value="<?= h($_POST['mobile'] ?? $client['mobile']) ?>" required>
              <!-- <div class="form-text small">Enter 11-digit mobile number (digits only).</div> -->
            </div>
            <div class="mb-2">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control form-control-sm" value="<?= h($_POST['email'] ?? ($client['email'] ?? '')) ?>">
            </div>
            <?php if ($HAS_NID): ?>
            <div class="mb-2">
              <label class="form-label">NID No.</label>
              <input type="text" name="nid" class="form-control form-control-sm" value="<?= h($_POST['nid'] ?? ($client['nid'] ?? '')) ?>">
            </div>
            <?php endif; ?>
            <?php if ($HAS_DOB): ?>
            <div class="mb-2">
              <label class="form-label">DOB</label>
              <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($_POST['dob'] ?? ($client['dob'] ?? '')) ?>">
            </div>
            <?php endif; ?>

            <hr>

            <!-- Photo -->
            <div class="card">
              <div class="card-header fw-bold">Profile Photo</div>
              <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle overflow-hidden border" style="width:80px;height:80px;background:#f2f4f7">
                    <?php if ($photo_url): ?>
                      <img id="photoPreview" src="<?= h($photo_url) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                      <img id="photoPreview" src="/assets/images/default-avatar.png" alt="Photo" style="width:100%;height:100%;object-fit:cover">
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow-1">
                    <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm mb-1" <?= $HAS_PHOTO_URL?'':'disabled' ?>>
                    <div class="form-text small">Supported: JPG, PNG • Max 3MB</div>
                    <?php if ($HAS_PHOTO_URL && $photo_url): ?>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" id="remove_photo" name="remove_photo">
                        <label class="form-check-label" for="remove_photo">Remove current photo</label>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Billing -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">Billing</div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">Package</label>
              <?php $form_pkg_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : (int)$client['package_id']; ?>
              <select name="package_id" class="form-select form-select-sm" required>
                <option value="">-- Select --</option>
                <?php foreach ($packages as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= ($form_pkg_id===(int)$p['id'])?'selected':'' ?>>
                    <?= h($p['name']) ?> <?= is_numeric($p['price'])? '— '.(0+$p['price']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <!-- <div class="form-text small">(Package name = MikroTik PPP profile name 1:1)</div> -->
            </div>
            <div class="mb-2">
              <label class="form-label req">Monthly Bill</label>
              <input type="number" step="0.01" name="monthly_bill" class="form-control form-control-sm" value="<?= h($_POST['monthly_bill'] ?? $client['monthly_bill']) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Expiry Date</label>
              <?php
                $exp_raw = trim((string)($_POST['expiry_date'] ?? ''));
                if ($exp_raw === '') $exp_raw = (string)($client['expiry_date'] ?? '');
                $exp_display = '';
                if ($exp_raw !== '') {
                  $ts = strtotime($exp_raw);
                  if ($ts !== false) $exp_display = date('Y-m-d', $ts);
                }
              ?>
              <input type="date"
                     name="expiry_date"
                     class="form-control form-control-sm"
                     value="<?= h($exp_display) ?>"
                     placeholder="YYYY-MM-DD">
              <!-- <div class="form-text small">Calendar থেকে পূর্ণ তারিখ সিলেক্ট করুন (ফাঁকা রাখলে আপডেট হবে না)।</div> -->
            </div>
            <div class="mb-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php
                  $cur  = strtolower(trim($_POST['status'] ?? ($client['status'] ?? 'active')));
                  if (!in_array($cur, $status_values, true)) {
                    $cur = strtolower(trim($client['status'] ?? 'active'));
                  }
                  foreach($status_values as $k){
                    $label = $status_labels[$k] ?? ucwords(str_replace('_',' ', $k));
                    echo '<option value="'.h($k).'"'.($cur===$k?' selected':'').'>'.h($label).'</option>';
                  }
                ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Server / PPP -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">Server / PPP</div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label">Router</label>
              <?php $form_router_id = isset($_POST['router_id']) ? (int)$_POST['router_id'] : (int)$client['router_id']; ?>
              <select name="router_id" class="form-select form-select-sm">
                <option value="">-- Select --</option>
                <?php foreach ($routers as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= ($form_router_id===(int)$r['id'])?'selected':'' ?>>
                    <?= h($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label req">PPPoE Username</label>
              <input type="text" name="pppoe_id" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_id'] ?? $client['pppoe_id']) ?>" required>
            </div>

            <?php if ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD): ?>
            <div class="mb-2">
              <label class="form-label">PPPoE Password</label>
              <input type="text" name="pppoe_pass" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_pass'] ?? $pppoe_pass_display) ?>">
            </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

    <!-- (বাংলা) ফর্ম অ্যাকশন বাটন -->
    <div class="d-flex justify-content-between align-items-center mt-3">
      <span class="text-muted small">
        <?php if ($SHOW_CLIENT_CODE && !empty($client['client_code'])): ?>
          Client Code: <span class="mono"><?= h($client['client_code']) ?></span>
        <?php endif; ?>
      </span>
      <div class="d-flex gap-2">
        <a href="/public/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save2"></i> Save Changes</button>
      </div>
    </div>
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
// (বাংলা) photo preview
document.getElementById('photo')?.addEventListener('change', function(){
  const [file] = this.files || [];
  if(!file) return;
  const obj = URL.createObjectURL(file);
  const img1 = document.getElementById('photoPreview');
  const img2 = document.getElementById('topPreview');
  if(img1) img1.src = obj;
  if(img2) img2.src = obj;
});
</script>

<?php if ($notice): ?>
<script>
(function(){
  const msg = <?= json_encode($notice, JSON_UNESCAPED_UNICODE) ?>;
  window.addEventListener('load', () => {
    if (window.globalToast) {
      window.globalToast(msg, 'success', 3500, 'Success');
      const alertEl = document.getElementById('pageNotice');
      if (alertEl) alertEl.style.display = 'none';
    }
  });
})();
</script>
<?php endif; ?>

<script>
(function(){
  const HAS_SUB_ZONE = <?= json_encode((bool)$HAS_SUB_ZONE) ?>;
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
    if (currentType === 'box' && HAS_SUB_ZONE) {
      populateParentSub(parentAreaSelect.value || '', '');
    }
  });

  function ensureModal(){
    if (modalInstance) return modalInstance;
    const bs = window.bootstrap || null;
    if (!modalEl || !bs || !bs.Modal) return null;
    if (modalEl.parentElement !== document.body) {
      document.body.appendChild(modalEl);
    }
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
    const showSub = (type === 'box' && HAS_SUB_ZONE);
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

  async function submitValue(type, label, details, btn){
    try {
      if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
      const payload = {type, label, details, csrf_token: csrf};
      if (type !== 'area' && parentAreaSelect && !parentAreaWrap?.classList.contains('d-none')) {
        payload.parent_area = parentAreaSelect.value || '';
      }
      if (type === 'box' && parentSubSelect && !parentSubWrap?.classList.contains('d-none')) {
        payload.parent_sub_zone = parentSubSelect.value || '';
      }
      const res = await fetch('/ajax/location_options.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json','X-CSRF-Token': csrf},
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to save');
      areaCache = null;
      subZoneCache = null;
      const savedVal = data.value || data?.data?.value || label;
      await refreshSelect(type, savedVal);
      setNotice(`Saved: ${(savedVal || '').trim()}.`, 'success');
      notify('Updated successfully.', 'success', 'Success');
      const modal = ensureModal();
      modal?.hide();
      form?.reset();
      if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    } catch (err) {
      const msg = err?.message || 'Could not save option.';
      setNotice(msg, 'error');
      notify(msg, 'danger', 'Error');
      if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    }
  }

  function handleSave(evt){
    evt?.preventDefault();
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
      if (HAS_SUB_ZONE && parentSubSelect && !parentSubSelect.value) {
        setNotice('Please select a sub zone.', 'error');
        notify('Please select a sub zone.', 'warning', 'Notice');
        parentSubSelect.focus();
        return;
      }
    }
    submitValue(type, label, details, saveBtn);
  }

  form?.addEventListener('submit', handleSave);
  saveBtn?.addEventListener('click', handleSave);

  document.querySelectorAll('[data-loc-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const t = btn.dataset.locAdd;
      if (!t) return;
      openModal(t).catch(err => alert(err.message || 'Failed to open form.'));
    });
  });
})();

</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
