<?php
// cron/expire_active.php
// (বাংলা) এক্সপায়ারি ডেট ভবিষ্যতে চলে গেলে নিষ্ক্রিয় ক্লায়েন্টকে পুনরায় সক্রিয় করা ও MikroTik PPPoE secret enable করা।

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/audit.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// (বাংলা) নির্দিষ্ট টেবিল/কলাম আছে কিনা নিরাপদভাবে চেক করা
function col_exists(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([$col]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// (বাংলা) এক্সপায়ারি কলাম বাছাই করা (priority: expiry_date -> expire_date)
function pick_expiry_col(PDO $pdo): ?string {
    if (col_exists($pdo, 'clients', 'expiry_date')) return 'expiry_date';
    if (col_exists($pdo, 'clients', 'expire_date')) return 'expire_date';
    return null;
}

// (বাংলা) নির্দিষ্ট টেবিল আছে কিনা দেখা
function table_exists(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// (বাংলা) তারিখ নরমালাইজ করা (YYYY-MM-DD), ভুল হলে null
function normalize_date_str(?string $val): ?string {
    $val = trim((string)$val);
    if ($val === '' || $val === '0000-00-00') return null;
    $ts = strtotime($val);
    return $ts === false ? null : date('Y-m-d', $ts);
}

// (বাংলা) অডিট লগের জন্য সেফ র‍্যাপার (বিভিন্ন সিগনেচার সাপোর্টেড)
function audit_log_safe(string $action, ?int $entity_id = null, array $meta = [], ?array $oldMeta = null): void {
    if (!function_exists('audit_log')) return;
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($oldMeta !== null) {
        try { @audit_log('client', $entity_id, $action, $oldMeta, $meta); return; } catch (Throwable $e) {}
    }
    try { @audit_log($action, $entity_id, $meta); return; } catch (Throwable $e) {}
    try { @audit_log('client', $entity_id, $action, null, $meta); return; } catch (Throwable $e) {}
    try { @audit_log($action, $meta); return; } catch (Throwable $e) {}
    try {
        if (isset($GLOBALS['pdo'])) {
            @call_user_func_array('audit_log', [$GLOBALS['pdo'], (int)($entity_id ?? 0), $action, $metaJson]);
        }
    } catch (Throwable $e) {}
}

// (বাংলা) রাউটার তথ্য টানা
function get_router(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM routers WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// (বাংলা) RouterOS API কানেকশন তৈরি
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

// (বাংলা) CLI ফ্ল্যাগ রিডার (php expire_active.php --client_id=5)
function cli_get_flag(string $key) {
    global $argv;
    if (PHP_SAPI !== 'cli' || empty($argv)) return null;
    foreach ($argv as $arg) {
        if (preg_match('/^--' . preg_quote($key, '/') . '=(.*)$/', $arg, $m)) return $m[1];
        if ($arg === '--' . $key) return '1';
    }
    return null;
}

// (বাংলা) TRUE/FALSE স্ট্রিং থেকে boolean বের করা
function str_bool(mixed $val): bool {
    $val = strtolower(trim((string)$val));
    return in_array($val, ['true', 'yes', '1', 'on'], true);
}

// (বাংলা) audit_logs টেবিলের মেটাডাটা ক্যাশ করা (auto/ম্যানুয়াল আলাদা করতে)
function audit_meta(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [
        'ok'            => false,
        'table'         => null,
        'entity_col'    => null,
        'entity_id_col' => null,
        'has_created'   => false,
    ];

    foreach (['audit_logs', 'audit'] as $tbl) {
        if (!table_exists($pdo, $tbl)) continue;
        $hasAction   = col_exists($pdo, $tbl, 'action');
        $entityIdCol = col_exists($pdo, $tbl, 'entity_id') ? 'entity_id' : (col_exists($pdo, $tbl, 'row_id') ? 'row_id' : null);
        if (!$hasAction || !$entityIdCol) continue;

        $cache = [
            'ok'            => true,
            'table'         => $tbl,
            'entity_col'    => col_exists($pdo, $tbl, 'entity') ? 'entity' : (col_exists($pdo, $tbl, 'table') ? 'table' : null),
            'entity_id_col' => $entityIdCol,
            'has_created'   => col_exists($pdo, $tbl, 'created_at'),
        ];
        break;
    }

    return $cache;
}

// (বাংলা) সর্বশেষ স্ট্যাটাস-সম্পর্কিত অডিট অ্যাকশন টেনে আনা
function last_status_audit_action(PDO $pdo, int $clientId, array $auditMeta): ?string {
    if (!$auditMeta['ok'] || $clientId <= 0) return null;

    $table   = $auditMeta['table'];
    $eidCol  = $auditMeta['entity_id_col'];
    $entCol  = $auditMeta['entity_col'];
    $orderBy = $auditMeta['has_created'] ? "`created_at` DESC, id DESC" : "id DESC";

    $where   = ["`$eidCol`= ?"];
    $params  = [$clientId];
    if ($entCol) {
        $where[] = "`$entCol` IN ('client','system')";
    }
    $where[] = "(LOWER(action) LIKE '%inactive%' OR LOWER(action) LIKE '%disable%' OR LOWER(action) LIKE '%enable%' OR LOWER(action) LIKE '%expire%' OR LOWER(action) LIKE '%suspend%' OR LOWER(action) LIKE '%active%')";

    $sql = "SELECT action FROM `$table` WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy LIMIT 1";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $action = $st->fetchColumn();
        return $action ? strtolower((string)$action) : null;
    } catch (Throwable $e) {
        return null;
    }
}

// (বাংলা) চেক করা হবে ক্লায়েন্টকে expire_inactive দিয়ে ইনঅ্যাক্টিভ করা হয়েছিল কিনা
function was_inactivated_by_expiry(PDO $pdo, int $clientId, array $auditMeta): bool {
    static $memo = [];
    if ($clientId <= 0) return false;
    if (array_key_exists($clientId, $memo)) return $memo[$clientId];
    if (!$auditMeta['ok']) return $memo[$clientId] = true; // (guard unavailable হলে পুরোনো আচরণ বজায়)

    $table   = $auditMeta['table'];
    $eidCol  = $auditMeta['entity_id_col'];
    $entCol  = $auditMeta['entity_col'];
    $orderBy = $auditMeta['has_created'] ? "`created_at` DESC, id DESC" : "id DESC";

    $where = ["`$eidCol` = ?"];
    $params = [$clientId];
    if ($entCol) $where[] = "`$entCol` IN ('client','system')";
    $where[] = "action IN ('client_expire_inactive_status','client_auto_inactive_expiry','pppoe_disable_expiry')";

    $sql = "SELECT action FROM `$table` WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy LIMIT 1";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $action = $st->fetchColumn();
    } catch (Throwable $e) {
        $action = null;
    }

    $lastStatus = last_status_audit_action($pdo, $clientId, $auditMeta);
    $autoActions = ['client_expire_inactive_status', 'client_auto_inactive_expiry', 'pppoe_disable_expiry'];

    $memo[$clientId] = $action !== false && $action !== null && in_array(strtolower((string)$action), $autoActions, true)
                       && $lastStatus !== null && in_array($lastStatus, $autoActions, true);
    return $memo[$clientId];
}

// (বাংলা) প্রাথমিক ইনপুট/কনফিগ
$targetClientId  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (int)(cli_get_flag('client_id') ?? 0);
$clientFilterSql = $targetClientId > 0 ? " AND id=?" : "";
$clientFilterArg = $targetClientId > 0 ? [$targetClientId] : [];
$expiryCol       = pick_expiry_col($pdo);

if ($expiryCol === null) {
    echo "[!] clients table is missing expiry_date/expire_date column.\n";
    exit(1);
}

$today        = date('Y-m-d');
$expDateExpr  = "DATE(`$expiryCol`)";
$expDateValid = "`$expiryCol` IS NOT NULL AND `$expiryCol` <> '' AND `$expiryCol` <> '0000-00-00' AND `$expiryCol` <> '0000-00-00 00:00:00'";
$hasIsLeft    = col_exists($pdo, 'clients', 'is_left');
$hasUpdatedAt = col_exists($pdo, 'clients', 'updated_at');
$hasStatus    = col_exists($pdo, 'clients', 'status');
$auditMeta    = audit_meta($pdo);

// (বাংলা) সক্রিয় করার জন্য ক্লায়েন্ট বাছাই করা (expiry ভবিষ্যৎ এবং status inactive/expired/pending)
$statusWhere = $hasStatus ? " AND status IN ('inactive','expired','pending')" : "";
$activateSelect = $pdo->prepare("
    SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date, " . ($hasStatus ? "status" : "NULL AS status") . "
    FROM clients
    WHERE $expDateValid AND $expDateExpr > ?
    " . ($hasIsLeft ? " AND COALESCE(is_left,0)=0" : "") . $statusWhere . $clientFilterSql
);
$activateSelect->execute(array_merge([$today], $clientFilterArg));
$activatableRowsRaw = $activateSelect->fetchAll(PDO::FETCH_ASSOC) ?: [];

// (বাংলা) শুধু expire_inactive দ্বারা ইনঅ্যাক্টিভ হওয়া ক্লায়েন্টগুলো নেব
$guardSkipped = 0;
$activatableRows = [];
foreach ($activatableRowsRaw as $row) {
    $cid = (int)($row['id'] ?? 0);
    if ($cid <= 0) continue;
    if (!was_inactivated_by_expiry($pdo, $cid, $auditMeta)) {
        $guardSkipped++;
        continue;
    }
    $activatableRows[] = $row;
}
$activatableIds = array_map('intval', array_column($activatableRows, 'id'));
$activatableIdSet = array_fill_keys($activatableIds, true);

$activatedCount = 0;
if ($hasStatus && $activatableIds) {
    $idPlaceholders = implode(',', array_fill(0, count($activatableIds), '?'));
    $activateSql = "UPDATE clients SET status='active'" . ($hasUpdatedAt ? ", updated_at=NOW()" : "") .
                   " WHERE id IN ($idPlaceholders) AND $expDateValid AND $expDateExpr > ?" .
                   ($hasIsLeft ? " AND COALESCE(is_left,0)=0" : "") .
                   $statusWhere;
    $activateStmt = $pdo->prepare($activateSql);
    $activateStmt->execute(array_merge($activatableIds, [$today]));
    $activatedCount = $activateStmt->rowCount();

    foreach ($activatableRows as $row) {
        audit_log_safe('client_expiry_activate', (int)$row['id'], [
            'expiry_date' => $row['expiry_date'] ?? null,
            'client_code' => $row['client_code'] ?? null,
            'name'        => $row['name'] ?? null,
            'pppoe_id'    => $row['pppoe_id'] ?? null,
            'router_id'   => $row['router_id'] ?? null,
            'via'         => 'expire_active.php'
        ], [
            'status' => $row['status'] ?? null
        ]);
    }
} elseif (!$hasStatus) {
    echo "[!] clients table is missing status column, status update skipped.\n";
}

// (বাংলা) রাউটার/PPPoE ভিত্তিক enable-only হ্যান্ডেল করা (expiry>today)
$hasRouterId = col_exists($pdo, 'clients', 'router_id');
$hasPppoeId  = col_exists($pdo, 'clients', 'pppoe_id');
$routerEnabled = 0;
$routerErrors  = 0;

if ($hasRouterId && $hasPppoeId) {
    $routerSql  = "SELECT id, client_code, name, router_id, pppoe_id, `$expiryCol` AS expiry_date";
    if ($hasStatus) $routerSql .= ", status";
    if ($hasIsLeft) $routerSql .= ", is_left";
    $routerSql .= " FROM clients";
    $routerSql .= " WHERE router_id IS NOT NULL AND router_id <> 0";
    $routerSql .= " AND pppoe_id IS NOT NULL AND pppoe_id <> ''";
    $routerSql .= " AND $expDateValid AND $expDateExpr > ?";
    if ($hasStatus) $routerSql .= " AND status IN ('inactive','expired','pending')";
    if ($hasIsLeft) $routerSql .= " AND COALESCE(is_left,0)=0";
    $routerSql .= $clientFilterSql;

    $routerSelect = $pdo->prepare($routerSql);
    $routerSelect->execute(array_merge([$today], $clientFilterArg));
    $clients = $routerSelect->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byRouter = [];
    foreach ($clients as $c) {
        $cid = (int)($c['id'] ?? 0);
        if ($cid <= 0) continue;
        if (!isset($activatableIdSet[$cid])) continue; // (বাংলা) expire_inactive দ্বারা নিষ্ক্রিয় না হলে স্কিপ
        $rid = (int)($c['router_id'] ?? 0);
        $pppoe = trim((string)($c['pppoe_id'] ?? ''));
        $expNorm = normalize_date_str((string)($c['expiry_date'] ?? ''));
        if ($rid <= 0 || $pppoe === '' || $expNorm === null) continue;

        $byRouter[$rid][] = [
            'id'          => $cid,
            'client_code' => $c['client_code'] ?? null,
            'name'        => $c['name'] ?? null,
            'pppoe_id'    => $pppoe,
            'expiry_norm' => $expNorm,
        ];
    }

    foreach ($byRouter as $routerId => $items) {
        $router = get_router($pdo, (int)$routerId);
        if (!$router) continue;
        if (isset($router['status']) && (int)$router['status'] === 0) continue;

        try {
            $API = rt_connect($router);
        } catch (Throwable $e) {
            $API = false;
        }
        if (!$API) { $routerErrors++; continue; }
        if (property_exists($API, 'timeout')) $API->timeout = 10;

        foreach ($items as $c) {
            $pppoe = $c['pppoe_id'];
            $expNorm = $c['expiry_norm'];

            try {
                $secret = $API->comm('/ppp/secret/print', ['?name' => $pppoe, '.proplist' => '.id,disabled']);
            } catch (Throwable $e) {
                $routerErrors++;
                continue;
            }

            if (!is_array($secret) || !isset($secret[0]['.id'])) continue;
            $id = $secret[0]['.id'];
            $isDisabled = array_key_exists('disabled', $secret[0]) ? str_bool($secret[0]['disabled']) : false;

            if ($isDisabled) {
                try { $API->comm('/ppp/secret/set', ['.id' => $id, 'disabled' => 'no']); }
                catch (Throwable $e) { $routerErrors++; continue; }
                $routerEnabled++;
                audit_log_safe('pppoe_enable_expiry', (int)$c['id'], [
                    'pppoe_id'    => $pppoe,
                    'client_code' => $c['client_code'] ?? null,
                    'name'        => $c['name'] ?? null,
                    'router_id'   => $routerId,
                    'expiry_date' => $expNorm,
                    'via'         => 'expire_active.php'
                ]);
            }
        }

        $API->disconnect();
    }
} else {
    echo "Router/PPPoE columns not found, router operations skipped.\n";
}

// (বাংলা) সারাংশ আউটপুট
echo "✅ expire_active\n";
echo "📌 Status activated: $activatedCount\n";
echo "📌 PPPoE enabled (expiry>today): $routerEnabled\n";
if ($routerErrors > 0) echo "📌 Router operation error: $routerErrors\n";
if ($auditMeta['ok'] && $guardSkipped > 0) echo "📌 Skipped (manual/guarded inactive): $guardSkipped\n";
if (!$auditMeta['ok']) echo "📌 Audit log guard unavailable; auto/manual distinction not enforced.\n";
