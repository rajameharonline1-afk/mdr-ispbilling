<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/audit.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function col_exists(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function normalize_date_str(?string $val): ?string {
    $val = trim((string)$val);
    if ($val === '' || $val === '0000-00-00') return null;
    $ts = strtotime($val);
    return $ts === false ? null : date('Y-m-d', $ts);
}

function add_one_month_same_day(string $date): ?string {
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->add(new DateInterval('P1M'))->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

if (!function_exists('audit_log_safe')) {
    function audit_log_safe(string $action, ?int $entity_id = null, array $meta = [], ?array $oldMeta = null): void {
        if (!function_exists('audit_log')) return;
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        // পুরোনো ডাটা দিলে আগে পূর্ণ সিগনেচার (entity, entity_id, action, old, new) চেষ্টা করা হচ্ছে
        if ($oldMeta !== null) {
            try { @audit_log('client', $entity_id, $action, $oldMeta, $meta); return; } catch (Throwable $e) {}
        }
        // প্রাধান্যপ্রাপ্ত: (action, entity_id, meta)
        try { @audit_log($action, $entity_id, $meta); return; } catch (Throwable $e) {}
        // বিকল্প: (entity, entity_id, action, old, new)
        try { @audit_log('client', $entity_id, $action, null, $meta); return; } catch (Throwable $e) {}
        // বিকল্প: (action, meta)
        try { @audit_log($action, $meta); return; } catch (Throwable $e) {}
        // সর্বশেষ বিকল্প: পুরোনো সিগনেচার (pdo, id, action, note)
        try {
            if (isset($GLOBALS['pdo'])) {
                @call_user_func_array('audit_log', [$GLOBALS['pdo'], (int)($entity_id ?? 0), $action, $metaJson]);
            }
        } catch (Throwable $e) {}
    }
}

function get_router(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM routers WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rt_connect(array $router): RouterosAPI|false {
    $ip   = $router['ip_address'] ?? ($router['ip'] ?? ($router['host'] ?? ($router['address'] ?? '')));
    $user = $router['username'] ?? ($router['user'] ?? '');
    $pass = $router['password'] ?? ($router['pass'] ?? '');
    $port = isset($router['api_port']) && $router['api_port'] ? (int)$router['api_port'] : (int)($router['port'] ?? 8728);
    if ($ip === '' || $user === '' || $pass === '') return false;
    $API = new RouterosAPI();
    $API->debug = false;
    if (property_exists($API, 'port')) $API->port = $port;
    return $API->connect($ip, $user, $pass) ? $API : false;
}

function table_exists(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function detect_invoice_schema(PDO $pdo): ?array {
    if (!table_exists($pdo, 'invoices')) return null;
    $has = fn(string $c): bool => col_exists($pdo, 'invoices', $c);
    $amountCol = $has('total') ? 'total'
               : ($has('total_amount') ? 'total_amount'
               : ($has('payable') ? 'payable'
               : ($has('amount') ? 'amount' : null)));
    if (!$amountCol) return null;
    return [
        'amount_col'         => $amountCol,
        'has_invoice_number' => $has('invoice_number'),
        'has_status'         => $has('status'),
        'has_is_void'        => $has('is_void'),
        'has_billing_month'  => $has('billing_month'),
        'has_invoice_date'   => $has('invoice_date'),
        'has_due_date'       => $has('due_date'),
        'has_period_start'   => $has('period_start'),
        'has_period_end'     => $has('period_end'),
        'has_created'        => $has('created_at'),
        'has_updated'        => $has('updated_at'),
        'has_paid_amount'    => $has('paid_amount'),
        'has_total_amount'   => $has('total_amount'),
        'has_total'          => $has('total'),
        'has_payable'        => $has('payable'),
        'has_amount'         => $has('amount'),
        'has_months'         => $has('months'),
        'has_remarks'        => $has('remarks'),
        'has_subtotal'       => $has('subtotal'),
        'has_vat_percent'    => $has('vat_percent'),
        'has_vat_amount'     => $has('vat_amount'),
        'has_is_auto'        => $has('is_auto_generated'),
        'has_package_id'     => $has('package_id'),
    ];
}

function detect_bills_schema(PDO $pdo): ?array {
    if (!table_exists($pdo, 'bills')) return null;
    $has = fn(string $c): bool => col_exists($pdo, 'bills', $c);
    return [
        'has_due_date' => $has('due_date'),
        'has_status'   => $has('status'),
        'has_created'  => $has('created_at'),
    ];
}

function invoice_exists_for_period(PDO $pdo, array $schema, int $clientId, string $monthStart, string $monthEnd): bool {
    $where   = 'client_id=?';
    $params  = [$clientId];

    if (!empty($schema['has_period_start'])) {
        $where   .= ' AND period_start=?';
        $params[] = $monthStart;
        if (!empty($schema['has_period_end'])) {
            $where   .= ' AND period_end=?';
            $params[] = $monthEnd;
        }
    } elseif (!empty($schema['has_billing_month'])) {
        $where   .= ' AND billing_month BETWEEN ? AND ?';
        $params[] = $monthStart;
        $params[] = $monthEnd;
    } elseif (!empty($schema['has_invoice_date'])) {
        $where   .= ' AND DATE(invoice_date) BETWEEN ? AND ?';
        $params[] = $monthStart;
        $params[] = $monthEnd;
    } elseif (!empty($schema['has_created'])) {
        $where   .= ' AND DATE(created_at) BETWEEN ? AND ?';
        $params[] = $monthStart;
        $params[] = $monthEnd;
    }

    if (!empty($schema['has_is_void']))  $where .= " AND COALESCE(is_void,0)=0";
    if (!empty($schema['has_status']))    $where .= " AND COALESCE(status,'') NOT IN ('void','deleted','cancelled','canceled')";

    $st = $pdo->prepare("SELECT id FROM invoices WHERE $where LIMIT 1");
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

function client_has_due(PDO $pdo, int $clientId, ?array $invoiceSchema, ?array $billSchema): bool {
    // ইনভয়েস: unpaid/partial/due এবং void/deleted নয়
    if ($invoiceSchema) {
        $where = "client_id=?"; $params = [$clientId];
        if (!empty($invoiceSchema['has_is_void']))  $where .= " AND COALESCE(is_void,0)=0";
        if (!empty($invoiceSchema['has_status']))   $where .= " AND LOWER(COALESCE(status,'')) IN ('unpaid','partial','due')";
        $st = $pdo->prepare("SELECT id FROM invoices WHERE $where LIMIT 1");
        $st->execute($params);
        if ($st->fetchColumn()) return true;
    }
    // বিল: status = due
    if ($billSchema) {
        $st = $pdo->prepare("SELECT id FROM bills WHERE client_id=? AND status='due' LIMIT 1");
        $st->execute([$clientId]);
        if ($st->fetchColumn()) return true;
    }
    return false;
}

function client_bill_amount(PDO $pdo, int $clientId, array $flags): array {
    $cols = ['id'];
    if (!empty($flags['monthly_bill']))   $cols[] = 'monthly_bill';
    if (!empty($flags['package_price']))  $cols[] = 'package_price';
    if (!empty($flags['package_id']))     $cols[] = 'package_id';

    $st = $pdo->prepare('SELECT '.implode(',', $cols).' FROM clients WHERE id=? LIMIT 1');
    $st->execute([$clientId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['amount'=>0.0,'package_id'=>0];

    $bill = 0.0;
    $pkgId = !empty($flags['package_id']) ? (int)($row['package_id'] ?? 0) : 0;
    if (!empty($flags['package_price'])) {
        $bill = max($bill, (float)($row['package_price'] ?? 0));
    }
    if (!empty($flags['monthly_bill'])) {
        $bill = max($bill, (float)$row['monthly_bill']);
    }

    if ($bill <= 0.0 && !empty($flags['package_id'])) {
        if ($pkgId > 0 && table_exists($pdo, 'packages') && col_exists($pdo, 'packages', 'price')) {
            static $pkgSt = null;
            if ($pkgSt === null) {
                $pkgSt = $pdo->prepare('SELECT price FROM packages WHERE id=? LIMIT 1');
            }
            $pkgSt->execute([$pkgId]);
            $price = (float)$pkgSt->fetchColumn();
            $bill = max($bill, $price);
        }
    }

    return ['amount'=>max(0.0, round($bill, 2)), 'package_id'=>$pkgId];
}

function create_due_invoice(PDO $pdo, int $clientId, string $expiryDate, ?array $invoiceSchema, ?array $billSchema, array $clientFlags): array {
    $expNorm    = normalize_date_str($expiryDate) ?? date('Y-m-d');
    $monthStart = date('Y-m-01', strtotime($expNorm));
    $monthEnd   = date('Y-m-t', strtotime($expNorm));
    $today      = date('Y-m-d');

    $info   = client_bill_amount($pdo, $clientId, $clientFlags);
    $amount = (float)($info['amount'] ?? 0);
    $pkgId  = (int)($info['package_id'] ?? 0);
    if ($amount <= 0.0) return ['status'=>'no_amount'];

    if (client_has_due($pdo, $clientId, $invoiceSchema, $billSchema)) {
        return ['status'=>'has_due'];
    }

    if ($invoiceSchema) {
        if (invoice_exists_for_period($pdo, $invoiceSchema, $clientId, $monthStart, $monthEnd)) {
            return ['status'=>'exists','table'=>'invoices'];
        }

        $cols = ['client_id', $invoiceSchema['amount_col']];
        $vals = [':client_id', ':amount'];

        if (!empty($invoiceSchema['has_invoice_number'])) $cols[] = 'invoice_number';
        if (!empty($invoiceSchema['has_billing_month']))  { $cols[] = 'billing_month';  $vals[]=':billing_month'; }
        if (!empty($invoiceSchema['has_invoice_date']))   { $cols[] = 'invoice_date';   $vals[]=':invoice_date'; }
        if (!empty($invoiceSchema['has_due_date']))       { $cols[] = 'due_date';       $vals[]=':due_date'; }
        if (!empty($invoiceSchema['has_period_start']))   { $cols[] = 'period_start';   $vals[]=':period_start'; }
        if (!empty($invoiceSchema['has_period_end']))     { $cols[] = 'period_end';     $vals[]=':period_end'; }
        if (!empty($invoiceSchema['has_status']))         { $cols[] = 'status';         $vals[]=':status'; }
        if (!empty($invoiceSchema['has_paid_amount']))    { $cols[] = 'paid_amount';    $vals[]=':paid_amount'; }
        if (!empty($invoiceSchema['has_total_amount']) && $invoiceSchema['amount_col'] !== 'total_amount') { $cols[] = 'total_amount'; $vals[]=':total_amount'; }
        if (!empty($invoiceSchema['has_total']) && $invoiceSchema['amount_col'] !== 'total')               { $cols[] = 'total';        $vals[]=':total'; }
        if (!empty($invoiceSchema['has_payable']) && $invoiceSchema['amount_col'] !== 'payable')           { $cols[] = 'payable';      $vals[]=':payable'; }
        if (!empty($invoiceSchema['has_amount']) && $invoiceSchema['amount_col'] !== 'amount')             { $cols[] = 'amount';       $vals[]=':amount_alt'; }
        if (!empty($invoiceSchema['has_months']))         { $cols[] = 'months';         $vals[]=':months'; }
        if (!empty($invoiceSchema['has_remarks']))        { $cols[] = 'remarks';        $vals[]=':remarks'; }
        if (!empty($invoiceSchema['has_subtotal']))       { $cols[] = 'subtotal';       $vals[]=':subtotal'; }
        if (!empty($invoiceSchema['has_vat_percent']))    { $cols[] = 'vat_percent';    $vals[]=':vat_percent'; }
        if (!empty($invoiceSchema['has_vat_amount']))     { $cols[] = 'vat_amount';     $vals[]=':vat_amount'; }
        if (!empty($invoiceSchema['has_is_auto']))        { $cols[] = 'is_auto_generated'; $vals[]=':is_auto_generated'; }
        if (!empty($invoiceSchema['has_package_id']))     { $cols[] = 'package_id';     $vals[]=':package_id'; }
        if (!empty($invoiceSchema['has_created']))        { $cols[] = 'created_at';     $vals[]='NOW()'; }
        if (!empty($invoiceSchema['has_updated']))        { $cols[] = 'updated_at';     $vals[]='NOW()'; }

        $sql = 'INSERT INTO invoices ('.implode(',', $cols).') VALUES ('.implode(',', $vals).')';
        $st  = $pdo->prepare($sql);

        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);

        if (!empty($invoiceSchema['has_invoice_number'])) {
            try {
                $invNo = 'INV-DUE-'.date('Ymd').'-'.$clientId.'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
            } catch (Throwable $e) {
                $invNo = 'INV-DUE-'.date('Ymd').'-'.$clientId.'-'.substr(uniqid(),-6);
            }
            $st->bindValue(':invoice_number', $invNo);
        }
        if (!empty($invoiceSchema['has_billing_month']))  $st->bindValue(':billing_month', $monthStart);
        if (!empty($invoiceSchema['has_invoice_date']))   $st->bindValue(':invoice_date', $today);
        if (!empty($invoiceSchema['has_due_date']))       $st->bindValue(':due_date', $expNorm);
        if (!empty($invoiceSchema['has_period_start']))   $st->bindValue(':period_start', $monthStart);
        if (!empty($invoiceSchema['has_period_end']))     $st->bindValue(':period_end', $monthEnd);
        if (!empty($invoiceSchema['has_status']))         $st->bindValue(':status', 'unpaid');
        if (!empty($invoiceSchema['has_paid_amount']))    $st->bindValue(':paid_amount', 0);
        if (!empty($invoiceSchema['has_total_amount']) && $invoiceSchema['amount_col'] !== 'total_amount') $st->bindValue(':total_amount', $amount);
        if (!empty($invoiceSchema['has_total']) && $invoiceSchema['amount_col'] !== 'total')               $st->bindValue(':total', $amount);
        if (!empty($invoiceSchema['has_payable']) && $invoiceSchema['amount_col'] !== 'payable')           $st->bindValue(':payable', $amount);
        if (!empty($invoiceSchema['has_amount']) && $invoiceSchema['amount_col'] !== 'amount')             $st->bindValue(':amount_alt', $amount);
        if (!empty($invoiceSchema['has_months']))         $st->bindValue(':months', 1, PDO::PARAM_INT);
        if (!empty($invoiceSchema['has_remarks']))        $st->bindValue(':remarks', 'Auto due on inactive (expiry='.$expNorm.')');
        if (!empty($invoiceSchema['has_subtotal']))       $st->bindValue(':subtotal', $amount);
        if (!empty($invoiceSchema['has_vat_percent']))    $st->bindValue(':vat_percent', 0);
        if (!empty($invoiceSchema['has_vat_amount']))     $st->bindValue(':vat_amount', 0);
        if (!empty($invoiceSchema['has_is_auto']))        $st->bindValue(':is_auto_generated', 1, PDO::PARAM_INT);
        if (!empty($invoiceSchema['has_package_id'])) {
            $st->bindValue(':package_id', $pkgId > 0 ? $pkgId : null, $pkgId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        }

        $st->execute();
        return ['status'=>'created','table'=>'invoices','id'=>(int)$pdo->lastInsertId(),'amount'=>$amount];
    }

    if ($billSchema) {
        $billMonth = date('Y-m', strtotime($expNorm));
        $chk = $pdo->prepare('SELECT id FROM bills WHERE client_id=? AND bill_month=? LIMIT 1');
        $chk->execute([$clientId, $billMonth]);
        if ($chk->fetchColumn()) return ['status'=>'exists','table'=>'bills'];

        $cols = ['client_id','amount','bill_month'];
        $vals = [':client_id',':amount',':bill_month'];
        if (!empty($billSchema['has_due_date'])) { $cols[]='due_date'; $vals[]=':due_date'; }
        if (!empty($billSchema['has_status']))   { $cols[]='status';   $vals[]=':status'; }
        if (!empty($billSchema['has_created']))  { $cols[]='created_at'; $vals[]='NOW()'; }

        $sql = 'INSERT INTO bills ('.implode(',', $cols).') VALUES ('.implode(',', $vals).')';
        $st  = $pdo->prepare($sql);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);
        $st->bindValue(':bill_month', $billMonth);
        if (!empty($billSchema['has_due_date'])) $st->bindValue(':due_date', $expNorm);
        if (!empty($billSchema['has_status']))   $st->bindValue(':status', 'due');
        $st->execute();
        return ['status'=>'created','table'=>'bills','id'=>(int)$pdo->lastInsertId(),'amount'=>$amount];
    }

    return ['status'=>'no_table'];
}

$targetClientId = 0;
// CLI ফ্ল্যাগ হেল্পার (যেমন: php auto_expire_inactive.php --client_id=123)
if (!function_exists('cli_get_flag')) {
    function cli_get_flag(string $key) {
        global $argv;
        if (PHP_SAPI !== 'cli' || empty($argv)) return null;
        foreach ($argv as $arg) {
            if (preg_match('/^--'.preg_quote($key,'/').'=(.*)$/', $arg, $m)) return $m[1];
            if ($arg === '--'.$key) return '1';
        }
        return null;
    }
}
$targetClientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (int)(cli_get_flag('client_id') ?? 0);
$clientFilterSql  = $targetClientId > 0 ? " AND id=?" : "";
$clientFilterArgs = $targetClientId > 0 ? [$targetClientId] : [];

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// ক্লায়েন্ট টেবিলে কোন কলামে এক্সপায়ারি ডেট আছে তা নির্ণয়
$expiryCol = col_exists($pdo, 'clients', 'expiry_date') ? 'expiry_date' : (col_exists($pdo, 'clients', 'expire_date') ? 'expire_date' : '');
if ($expiryCol === '') {
    echo "No expiry column found in clients table.\n";
    exit;
}
$expDateExpr   = "DATE(`$expiryCol`)";
$expDateValid  = "`$expiryCol` IS NOT NULL AND `$expiryCol` <> '' AND `$expiryCol` <> '0000-00-00' AND `$expiryCol` <> '0000-00-00 00:00:00'";
$hasIsLeft     = col_exists($pdo, 'clients', 'is_left');
$invoiceSchema = detect_invoice_schema($pdo);
$billSchema    = $invoiceSchema ? null : detect_bills_schema($pdo); // ইনভয়েস থাকলে তা-ই ব্যবহার করা হবে, না থাকলে বিল টেবিলে fallback
$clientBillFlags = [
    'monthly_bill'  => col_exists($pdo, 'clients', 'monthly_bill'),
    'package_price' => col_exists($pdo, 'clients', 'package_price'),
    'package_id'    => col_exists($pdo, 'clients', 'package_id'),
];

// অটোমেটিক মেয়াদ বৃদ্ধি: সর্বশেষ পেমেন্ট ডেটের পরের মাসের একই দিনে expiry_date সেট করা (idempotent)
$expiry_extended = 0;
if (table_exists($pdo, 'payments')) {
    $payParams = [];
    $paySubWhere = "1=1";
    if ($targetClientId > 0) {
        $paySubWhere .= " AND client_id = ?";
        $payParams[] = $targetClientId;
    }
    $paySub = "SELECT client_id, MAX(COALESCE(payment_date, paid_at, created_at)) AS last_pay
               FROM payments
               WHERE $paySubWhere
               GROUP BY client_id";
    $sqlPay = "SELECT c.id,
                      c.status,
                      c.`$expiryCol` AS expiry_date,
                      c.client_code,
                      c.name,
                      c.pppoe_id,
                      c.router_id,
                      p.last_pay".
              ($hasIsLeft ? ", c.is_left" : "").
              " FROM ($paySub) p
                JOIN clients c ON c.id = p.client_id".
              ($hasIsLeft ? " WHERE COALESCE(c.is_left,0)=0" : "");
    $stPay = $pdo->prepare($sqlPay);
    $stPay->execute($payParams);
    $payRows = $stPay->fetchAll(PDO::FETCH_ASSOC);

    if ($payRows) {
        $upd = $pdo->prepare("UPDATE clients SET `$expiryCol` = ?, updated_at = NOW() WHERE id = ?");
        foreach ($payRows as $r) {
            $lastPay = normalize_date_str($r['last_pay'] ?? '');
            if ($lastPay === null) continue;
            $targetExp = add_one_month_same_day($lastPay);
            if ($targetExp === null) continue;
            $currExp = normalize_date_str($r['expiry_date'] ?? '');
            if ($currExp !== null && $currExp >= $targetExp) continue; // আগেই বাড়ানো হয়ে থাকলে সেটি বজায় রাখা
            $upd->execute([$targetExp, (int)$r['id']]);
            $expiry_extended++;
            audit_log_safe('client_auto_extend_expiry', (int)$r['id'], [
                'expiry_date' => $targetExp,
                'client_code' => $r['client_code'] ?? null,
                'name'        => $r['name'] ?? null,
                'pppoe_id'    => $r['pppoe_id'] ?? null,
                'router_id'   => $r['router_id'] ?? null,
                'via'         => 'auto_expire_inactive'
            ], [
                'expiry_date' => $currExp
            ]);
        }
    }
}

// ১ম ধাপ: মেয়াদ শেষ হওয়ার আগের দিন Expired করা
$expireWhere   = "$expDateValid AND $expDateExpr = ? AND status NOT IN ('expired','inactive')$clientFilterSql";
$expireParams  = array_merge([$tomorrow], $clientFilterArgs);
$expiringRows  = $pdo->prepare("SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date FROM clients WHERE $expireWhere");
$expiringRows->execute($expireParams);
$expiringRows = $expiringRows->fetchAll(PDO::FETCH_ASSOC);
$stmt_expire = $pdo->prepare("UPDATE clients SET status='expired' 
                              WHERE $expireWhere");
$expire_count = $stmt_expire->execute($expireParams) ? $stmt_expire->rowCount() : 0;
if ($expire_count > 0 && $expiringRows) {
    foreach ($expiringRows as $row) {
        audit_log_safe('client_auto_expired', (int)$row['id'], [
            'expiry_date' => $row['expiry_date'] ?? $today,
            'client_code' => $row['client_code'] ?? null,
            'name'        => $row['name'] ?? null,
            'pppoe_id'    => $row['pppoe_id'] ?? null,
            'router_id'   => $row['router_id'] ?? null,
            'via'         => 'auto_expire'
        ]);
    }
}

// ১ম.৫ ধাপ: Expiry ভবিষ্যতে সরালে Active করা (inactive/expired → active)
$reactWhere   = "$expDateValid AND $expDateExpr > ? AND status IN ('inactive','expired')$clientFilterSql";
$reactParams  = array_merge([$tomorrow], $clientFilterArgs);
$reactRowsSel = $pdo->prepare("SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date FROM clients WHERE $reactWhere");
$reactRowsSel->execute($reactParams);
$reactRows = $reactRowsSel->fetchAll(PDO::FETCH_ASSOC);
$stmt_reactivate = $pdo->prepare("UPDATE clients SET status='active'
                                  WHERE $reactWhere");
$reactivate_count = $stmt_reactivate->execute($reactParams) ? $stmt_reactivate->rowCount() : 0;
if ($reactivate_count > 0 && $reactRows) {
    foreach ($reactRows as $row) {
        audit_log_safe('client_auto_reactivated_expiry', (int)$row['id'], [
            'expiry_date' => $row['expiry_date'] ?? null,
            'client_code' => $row['client_code'] ?? null,
            'name'        => $row['name'] ?? null,
            'pppoe_id'    => $row['pppoe_id'] ?? null,
            'router_id'   => $row['router_id'] ?? null,
            'via'         => 'auto_expire_inactive'
        ], [
            // আপডেটের আগে এক্সপায়ারি কার্যত আজকেই ধরে নেওয়া হয়েছিল
            'expiry_date' => $today
        ]);
    }
}

// ২য় ধাপ: মেয়াদ শেষের দিনে Inactive করা + ডিউ বিল জেনারেট
$inactiveRows = [];
$isLeftClause = $hasIsLeft ? " AND COALESCE(is_left,0)=0" : "";
try {
    $sel_inactive = $pdo->prepare("SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date FROM clients WHERE $expDateValid AND $expDateExpr = ? AND status IN ('expired','active','pending')$isLeftClause$clientFilterSql");
    $sel_inactive->execute(array_merge([$today], $clientFilterArgs));
    $inactiveRows = $sel_inactive->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $inactiveRows = []; }
$stmt_inactive = $pdo->prepare("UPDATE clients SET status='inactive' 
                                WHERE $expDateValid AND $expDateExpr = ? AND status IN ('expired','active','pending')$isLeftClause$clientFilterSql");
$inactive_count = $stmt_inactive->execute(array_merge([$today], $clientFilterArgs)) ? $stmt_inactive->rowCount() : 0;

// ইনভয়েস/বিল জেনারেশন ও অডিট
$inv_created = 0; $inv_exists = 0; $inv_skipped = 0; $inv_missing_table = 0; $inv_no_amount = 0; $inv_errors = 0; $inv_has_due = 0;
if ($inactive_count > 0 && $inactiveRows) {
    foreach ($inactiveRows as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid <= 0) continue;

        try {
            $res = create_due_invoice($pdo, $cid, (string)($row['expiry_date'] ?? $today), $invoiceSchema, $billSchema, $clientBillFlags);
            if (($res['status'] ?? '') === 'created') $inv_created++;
            elseif (($res['status'] ?? '') === 'exists') $inv_exists++;
            elseif (($res['status'] ?? '') === 'no_table') $inv_missing_table++;
            elseif (($res['status'] ?? '') === 'no_amount') $inv_no_amount++;
            elseif (($res['status'] ?? '') === 'has_due') $inv_has_due++;
            else $inv_skipped++;
        } catch (Throwable $e) {
            $inv_errors++;
        }

        audit_log_safe('client_auto_inactive_expiry', $cid, [
            'expiry_date' => $row['expiry_date'] ?? $today,
            'client_code' => $row['client_code'] ?? null,
            'name'        => $row['name'] ?? null,
            'pppoe_id'    => $row['pppoe_id'] ?? null,
            'router_id'   => $row['router_id'] ?? null,
            'via'         => 'auto_expire_inactive'
        ]);
    }
}

// ৩য় ধাপ: MikroTik secret enable/disable (Expiry অনুযায়ী)
$hasRouterId = col_exists($pdo, 'clients', 'router_id');
$hasPppoeId  = col_exists($pdo, 'clients', 'pppoe_id');

if ($hasRouterId && $hasPppoeId) {
    $st = $pdo->prepare("
        SELECT id, client_code, name, router_id, pppoe_id, `$expiryCol` AS expiry_date
        ".($hasIsLeft ? ", is_left" : "")."
        FROM clients
        WHERE router_id IS NOT NULL AND router_id <> 0
          AND pppoe_id IS NOT NULL AND pppoe_id <> ''
          AND $expDateValid
          $clientFilterSql
    ");
    $st->execute($clientFilterArgs);
    $clients = $st->fetchAll(PDO::FETCH_ASSOC);

    // রাউটার অনুযায়ী ক্লায়েন্ট লিস্ট ভাগ করা এবং ভ্যালিড ডাটা আগেই প্রস্তুত করা
    $byRouter = [];
    foreach ($clients as $c) {
        if ($hasIsLeft && (int)($c['is_left'] ?? 0) === 1) continue;
        $rid    = (int)($c['router_id'] ?? 0);
        $pppoe  = trim((string)($c['pppoe_id'] ?? ''));
        $expNorm = normalize_date_str((string)($c['expiry_date'] ?? ''));
        if ($rid <= 0 || $pppoe === '' || $expNorm === null) continue;

        $byRouter[$rid][] = [
            'id'            => (int)($c['id'] ?? 0),
            'client_code'   => $c['client_code'] ?? null,
            'name'          => $c['name'] ?? null,
            'router_id'     => $rid,
            'pppoe_id'      => $pppoe,
            'expiry_norm'   => $expNorm,
            'should_disable'=> $expNorm <= $today,
        ];
    }

    $enabled = 0;
    $disabled = 0;
    $router_errors = 0;

    foreach ($byRouter as $router_id => $list) {
        $router = get_router($pdo, (int)$router_id);
        if (!$router) continue;
        if (isset($router['status']) && (int)$router['status'] === 0) continue;
        try {
            $API = rt_connect($router);
        } catch (Throwable $e) {
            $API = false;
        }
        if (!$API) { $router_errors++; continue; }
        if (property_exists($API, 'timeout')) {
            // কানেক্ট/কমান্ড যেন ঝুলে না থাকে তার জন্য টাইমআউট সেট
            $API->timeout = 10;
        }

        foreach ($list as $c) {
            $pppoe = $c['pppoe_id'];
            $expNorm = $c['expiry_norm'];
            $shouldDisable = (bool)$c['should_disable']; // আজ বা আগের তারিখ হলে ডিজেবল, ভবিষ্যৎ তারিখ হলে এনেবল
            try {
                $secret = $API->comm('/ppp/secret/print', ['?name'=>$pppoe, '.proplist'=>'.id,disabled']);
            } catch (Throwable $e) {
                $router_errors++;
                continue;
            }
            if (!is_array($secret) || !isset($secret[0]['.id'])) continue;
            $id = $secret[0]['.id'];
            $isDisabled = false;
            if (array_key_exists('disabled', $secret[0])) {
                $val = strtolower(trim((string)$secret[0]['disabled']));
                $isDisabled = in_array($val, ['true','yes','1','on'], true);
            }

            if ($shouldDisable && !$isDisabled) {
                try { $API->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'yes']); }
                catch (Throwable $e) { $router_errors++; continue; }
                $disabled++;
                audit_log_safe('pppoe_disable_expiry', (int)$c['id'], [
                    'pppoe_id'    => $pppoe,
                    'client_code' => $c['client_code'] ?? null,
                    'name'        => $c['name'] ?? null,
                    'router_id'   => $router_id,
                    'expiry_date' => $expNorm,
                    'via'         => 'auto_expire_inactive'
                ]);
            } elseif (!$shouldDisable && $isDisabled) {
                try { $API->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'no']); }
                catch (Throwable $e) { $router_errors++; continue; }
                $enabled++;
                audit_log_safe('pppoe_enable_expiry', (int)$c['id'], [
                    'pppoe_id'    => $pppoe,
                    'client_code' => $c['client_code'] ?? null,
                    'name'        => $c['name'] ?? null,
                    'router_id'   => $router_id,
                    'expiry_date' => $expNorm,
                    'via'         => 'auto_expire_inactive'
                ], [
                    'expiry_date' => $today
                ]);
            }
        }

        $API->disconnect();
    }

    echo "📌 MikroTik secrets disabled: $disabled\n";
    echo "📌 MikroTik secrets enabled: $enabled\n";
    if ($router_errors > 0) echo "📌 MikroTik errors (connect/command): $router_errors\n";
}

// রেজাল্ট দেখানো
echo "✅ Auto Process Completed\n";
echo "📌 Expired updated: $expire_count\n";
echo "📌 Reactivated (expiry extended): $reactivate_count\n";
echo "📌 Inactive updated: $inactive_count\n";
echo "📌 Expiry auto-extended by payment: $expiry_extended\n";
echo "📌 Due invoices created: $inv_created\n";
echo "📌 Due invoices skipped (exists): $inv_exists\n";
echo "📌 Due invoices skipped (no amount): $inv_no_amount\n";
echo "📌 Due invoices skipped (has previous due): $inv_has_due\n";
echo "📌 Due invoices skipped (other): $inv_skipped\n";
echo "📌 Due invoice missing table: $inv_missing_table\n";
echo "📌 Due invoice errors: $inv_errors\n";
