<?php
// cron/expire_inactive.php
// (বাংলা) এক্সপায়ারি তারিখ অনুযায়ী ক্লায়েন্ট নিষ্ক্রিয় করে এবং MikroTik PPPoE secret কেবল disable (re-enable করা হয় না) নিশ্চিত করে।

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

// (বাংলা) তারিখ নরমালাইজ করা (YYYY-MM-DD), ভুল হলে null
function normalize_date_str(?string $val): ?string {
    $val = trim((string)$val);
    if ($val === '' || $val === '0000-00-00') return null;
    $ts = strtotime($val);
    return $ts === false ? null : date('Y-m-d', $ts);
}

// (বাংলা) অডিট লগের জন্য সেফ র‍্যাপার (যে সিগনেচার চলবে সেটাই ব্যবহার করবে)
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

// (বাংলা) CLI ফ্ল্যাগ রিডার (php expire_inactive.php --client_id=5)
function cli_get_flag(string $key) {
    global $argv;
    if (PHP_SAPI !== 'cli' || empty($argv)) return null;
    foreach ($argv as $arg) {
        if (preg_match('/^--' . preg_quote($key, '/') . '=(.*)$/', $arg, $m)) return $m[1];
        if ($arg === '--' . $key) return '1';
    }
    return null;
}

// (বাংলা) প্রাথমিক ইনপুট/কনফিগ
$targetClientId  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : (int)(cli_get_flag('client_id') ?? 0);
$clientFilterSql = $targetClientId > 0 ? " AND id=?" : "";
$clientFilterArg = $targetClientId > 0 ? [$targetClientId] : [];
$expiryCol       = pick_expiry_col($pdo);

if ($expiryCol === null) {
    echo "[!] clients টেবিলে expiry_date/expire_date কলাম নেই।\n";
    exit(1);
}

$today         = date('Y-m-d');
$expDateExpr   = "DATE(`$expiryCol`)";
$expDateValid  = "`$expiryCol` IS NOT NULL AND `$expiryCol` <> '' AND `$expiryCol` <> '0000-00-00' AND `$expiryCol` <> '0000-00-00 00:00:00'";
$hasIsLeft     = col_exists($pdo, 'clients', 'is_left');
$hasUpdatedAt  = col_exists($pdo, 'clients', 'updated_at');

// (বাংলা) এক্সপায়ারি পার হয়ে গেলে status='inactive' করা
$inactiveSelect = $pdo->prepare("
    SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date, status
    FROM clients
    WHERE $expDateValid AND $expDateExpr <= ? AND status IN ('active','pending','expired')
    " . ($hasIsLeft ? " AND COALESCE(is_left,0)=0" : "") . $clientFilterSql
);
$inactiveSelect->execute(array_merge([$today], $clientFilterArg));
$inactiveRows = $inactiveSelect->fetchAll(PDO::FETCH_ASSOC) ?: [];

$inactiveUpdateSql = "UPDATE clients SET status='inactive'" . ($hasUpdatedAt ? ", updated_at=NOW()" : "") .
                     " WHERE $expDateValid AND $expDateExpr <= ? AND status IN ('active','pending','expired')" .
                     ($hasIsLeft ? " AND COALESCE(is_left,0)=0" : "") . $clientFilterSql;
$inactiveStmt  = $pdo->prepare($inactiveUpdateSql);
$inactiveStmt->execute(array_merge([$today], $clientFilterArg));
$inactiveCount = $inactiveStmt->rowCount();

if ($inactiveRows) {
    foreach ($inactiveRows as $row) {
        audit_log_safe('client_expire_inactive_status', (int)$row['id'], [
            'expiry_date' => $row['expiry_date'] ?? null,
            'client_code' => $row['client_code'] ?? null,
            'name'        => $row['name'] ?? null,
            'pppoe_id'    => $row['pppoe_id'] ?? null,
            'router_id'   => $row['router_id'] ?? null,
            'via'         => 'expire_inactive.php'
        ], [
            'status' => $row['status'] ?? null
        ]);
    }
}

// (বাংলা) রাউটার/PPPoE ভিত্তিক disable-only হ্যান্ডেল করা
$hasRouterId = col_exists($pdo, 'clients', 'router_id');
$hasPppoeId  = col_exists($pdo, 'clients', 'pppoe_id');
$routerDisabled = 0;
$routerErrors   = 0;

if ($hasRouterId && $hasPppoeId) {
    // কুয়েরি স্ট্রিং পরিষ্কারভাবে বানানো (concatenate করে)
    $routerSql  = "SELECT id, client_code, name, router_id, pppoe_id, `$expiryCol` AS expiry_date";
    if ($hasIsLeft) $routerSql .= ", is_left";
    $routerSql .= " FROM clients";
    $routerSql .= " WHERE router_id IS NOT NULL AND router_id <> 0";
    $routerSql .= " AND pppoe_id IS NOT NULL AND pppoe_id <> ''";
    $routerSql .= " AND $expDateValid";
    if ($hasIsLeft) $routerSql .= " AND COALESCE(is_left,0)=0";
    $routerSql .= $clientFilterSql;

    $routerSelect = $pdo->prepare($routerSql);
    $routerSelect->execute($clientFilterArg);
    $clients = $routerSelect->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byRouter = [];
    foreach ($clients as $c) {
        $rid = (int)($c['router_id'] ?? 0);
        $pppoe = trim((string)($c['pppoe_id'] ?? ''));
        $expNorm = normalize_date_str((string)($c['expiry_date'] ?? ''));
        if ($rid <= 0 || $pppoe === '' || $expNorm === null) continue;

        $byRouter[$rid][] = [
            'id'            => (int)($c['id'] ?? 0),
            'client_code'   => $c['client_code'] ?? null,
            'name'          => $c['name'] ?? null,
            'pppoe_id'      => $pppoe,
            'expiry_norm'   => $expNorm,
            'should_disable'=> $expNorm <= $today,
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
            $shouldDisable = (bool)$c['should_disable'];

            try {
                $secret = $API->comm('/ppp/secret/print', ['?name' => $pppoe, '.proplist' => '.id,disabled']);
            } catch (Throwable $e) {
                $routerErrors++;
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
                try { $API->comm('/ppp/secret/set', ['.id' => $id, 'disabled' => 'yes']); }
                catch (Throwable $e) { $routerErrors++; continue; }
                $routerDisabled++;
                audit_log_safe('pppoe_disable_expiry', (int)$c['id'], [
                    'pppoe_id'    => $pppoe,
                    'client_code' => $c['client_code'] ?? null,
                    'name'        => $c['name'] ?? null,
                    'router_id'   => $routerId,
                    'expiry_date' => $expNorm,
                    'via'         => 'expire_inactive.php'
                ]);
            }
        }

        $API->disconnect();
    }
} else {
    echo "Router/PPPoE কলাম পাওয়া যায়নি, রাউটার অপারেশন স্কিপ করা হল।\n";
}

// (বাংলা) সারাংশ আউটপুট
echo "✅ expire_inactive\n";
echo "📌 Status inactive: $inactiveCount\n";
echo "📌 PPPoE disabled (expiry<=today): $routerDisabled\n";
if ($routerErrors > 0) echo "📌 রাউটার অপারেশন ত্রুটি: $routerErrors\n";
