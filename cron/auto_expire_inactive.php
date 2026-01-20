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

if (!function_exists('audit_log_safe')) {
    function audit_log_safe(string $action, ?int $entity_id = null, array $meta = []): void {
        if (!function_exists('audit_log')) return;
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        // Preferred: (action, entity_id, meta)
        try { @audit_log($action, $entity_id, $meta); return; } catch (Throwable $e) {}
        // Fallback: (entity, entity_id, action, old, new)
        try { @audit_log('client', $entity_id, $action, null, $meta); return; } catch (Throwable $e) {}
        // Fallback: (action, meta)
        try { @audit_log($action, $meta); return; } catch (Throwable $e) {}
        // Fallback: legacy (pdo, id, action, note)
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

$targetClientId = 0;
// CLI flag helper (e.g., php auto_expire_inactive.php --client_id=123)
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
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Expiry column detection
$expiryCol = col_exists($pdo, 'clients', 'expiry_date') ? 'expiry_date' : (col_exists($pdo, 'clients', 'expire_date') ? 'expire_date' : '');
if ($expiryCol === '') {
    echo "No expiry column found in clients table.\n";
    exit;
}
$expDateExpr   = "DATE(`$expiryCol`)";
$expDateValid  = "`$expiryCol` IS NOT NULL AND `$expiryCol` <> '' AND `$expiryCol` <> '0000-00-00'";

// ১ম ধাপ: আজ মেয়াদ শেষ হলে Expired করা
$expireWhere   = "$expDateValid AND $expDateExpr = ? AND status NOT IN ('expired','inactive')$clientFilterSql";
$expireParams  = array_merge([$today], $clientFilterArgs);
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
            'via'         => 'auto_expire_inactive'
        ]);
    }
}

// ১ম.৫ ধাপ: Expiry বাড়ানো হলে Active করা (inactive/expired → active)
$reactWhere   = "$expDateValid AND $expDateExpr > ? AND status IN ('inactive','expired')$clientFilterSql";
$reactParams  = array_merge([$today], $clientFilterArgs);
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
        ]);
    }
}

// ২য় ধাপ: Expired হওয়ার পরের দিন Inactive করা
$inactive_ids = [];
try {
    $sel_inactive = $pdo->prepare("SELECT id, client_code, name, pppoe_id, router_id, `$expiryCol` AS expiry_date FROM clients WHERE $expDateValid AND $expDateExpr = ? AND status='expired'$clientFilterSql");
    $sel_inactive->execute(array_merge([$yesterday], $clientFilterArgs));
    $inactive_ids = $sel_inactive->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $inactive_ids = []; }
$stmt_inactive = $pdo->prepare("UPDATE clients SET status='inactive' 
                                WHERE $expDateValid AND $expDateExpr = ? AND status='expired'$clientFilterSql");
$inactive_count = $stmt_inactive->execute(array_merge([$yesterday], $clientFilterArgs)) ? $stmt_inactive->rowCount() : 0;
if ($inactive_count > 0 && $inactive_ids) {
    foreach ($inactive_ids as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid <= 0) continue;
        audit_log_safe('client_auto_inactive_expiry', $cid, [
            'expiry_date' => $row['expiry_date'] ?? $yesterday,
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
$hasIsLeft   = col_exists($pdo, 'clients', 'is_left');

if ($hasRouterId && $hasPppoeId) {
    $st = $pdo->prepare("
        SELECT id, client_code, name, router_id, pppoe_id, `$expiryCol` AS expiry_date
        ".($hasIsLeft ? ", is_left" : "")."
        FROM clients
        WHERE router_id IS NOT NULL AND router_id <> 0
          AND pppoe_id IS NOT NULL AND pppoe_id <> ''
          AND `$expiryCol` IS NOT NULL AND `$expiryCol` <> ''
          $clientFilterSql
    ");
    $st->execute($clientFilterArgs);
    $clients = $st->fetchAll(PDO::FETCH_ASSOC);

    $byRouter = [];
    foreach ($clients as $c) {
        if ($hasIsLeft && (int)($c['is_left'] ?? 0) === 1) {
            continue;
        }
        $rid = (int)$c['router_id'];
        if ($rid > 0) $byRouter[$rid][] = $c;
    }

    $enabled = 0;
    $disabled = 0;

    foreach ($byRouter as $router_id => $list) {
        $router = get_router($pdo, (int)$router_id);
        if (!$router) continue;
        if (isset($router['status']) && (int)$router['status'] === 0) continue;
        $API = rt_connect($router);
        if (!$API) continue;

        foreach ($list as $c) {
            $pppoe = trim((string)$c['pppoe_id']);
            if ($pppoe === '') continue;
            $exp = (string)($c['expiry_date'] ?? '');
            $expNorm = normalize_date_str($exp);
            if ($expNorm === null) continue;

            $shouldDisable = ($expNorm <= $today);
            $secret = $API->comm('/ppp/secret/print', ['?name'=>$pppoe, '.proplist'=>'.id,disabled']);
            if (!is_array($secret) || !isset($secret[0]['.id'])) continue;
            $id = $secret[0]['.id'];
            $isDisabled = false;
            if (array_key_exists('disabled', $secret[0])) {
                $val = strtolower(trim((string)$secret[0]['disabled']));
                $isDisabled = in_array($val, ['true','yes','1','on'], true);
            }

            if ($shouldDisable && !$isDisabled) {
                $API->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'yes']);
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
                $API->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'no']);
                $enabled++;
                audit_log_safe('pppoe_enable_expiry', (int)$c['id'], [
                    'pppoe_id'    => $pppoe,
                    'client_code' => $c['client_code'] ?? null,
                    'name'        => $c['name'] ?? null,
                    'router_id'   => $router_id,
                    'expiry_date' => $expNorm,
                    'via'         => 'auto_expire_inactive'
                ]);
            }
        }

        $API->disconnect();
    }

    echo "📌 MikroTik secrets disabled: $disabled\n";
    echo "📌 MikroTik secrets enabled: $enabled\n";
}

// রেজাল্ট দেখানো
echo "✅ Auto Process Completed\n";
echo "📌 Expired updated: $expire_count\n";
echo "📌 Reactivated (expiry extended): $reactivate_count\n";
echo "📌 Inactive updated: $inactive_count\n";
