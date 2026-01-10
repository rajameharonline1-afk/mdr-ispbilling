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
        try { @call_user_func_array('audit_log', [$action, 'client', $entity_id, $meta]); return; } catch (Throwable $e) {}
        try { @call_user_func_array('audit_log', [$action, $entity_id, $meta]); return; } catch (Throwable $e) {}
        try { @call_user_func_array('audit_log', [$action, $entity_id]); return; } catch (Throwable $e) {}
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
$stmt_expire = $pdo->prepare("UPDATE clients SET status='expired' 
                              WHERE $expDateValid AND $expDateExpr = ? AND status NOT IN ('expired','inactive')");
$expire_count = $stmt_expire->execute([$today]) ? $stmt_expire->rowCount() : 0;

// ১ম.৫ ধাপ: Expiry বাড়ানো হলে Active করা (inactive/expired → active)
$stmt_reactivate = $pdo->prepare("UPDATE clients SET status='active'
                                  WHERE $expDateValid AND $expDateExpr > ? AND status IN ('inactive','expired')");
$reactivate_count = $stmt_reactivate->execute([$today]) ? $stmt_reactivate->rowCount() : 0;

// ২য় ধাপ: Expired হওয়ার পরের দিন Inactive করা
$inactive_ids = [];
try {
    $sel_inactive = $pdo->prepare("SELECT id FROM clients WHERE $expDateValid AND $expDateExpr = ? AND status='expired'");
    $sel_inactive->execute([$yesterday]);
    $inactive_ids = $sel_inactive->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) { $inactive_ids = []; }
$stmt_inactive = $pdo->prepare("UPDATE clients SET status='inactive' 
                                WHERE $expDateValid AND $expDateExpr = ? AND status='expired'");
$inactive_count = $stmt_inactive->execute([$yesterday]) ? $stmt_inactive->rowCount() : 0;
if ($inactive_count > 0 && $inactive_ids) {
    foreach ($inactive_ids as $cid) {
        audit_log_safe('client_auto_inactive_expiry', (int)$cid, ['expiry_date'=>$yesterday]);
    }
}

// ৩য় ধাপ: MikroTik secret enable/disable (Expiry অনুযায়ী)
$hasRouterId = col_exists($pdo, 'clients', 'router_id');
$hasPppoeId  = col_exists($pdo, 'clients', 'pppoe_id');
$hasIsLeft   = col_exists($pdo, 'clients', 'is_left');

if ($hasRouterId && $hasPppoeId) {
    $st = $pdo->prepare("
        SELECT id, router_id, pppoe_id, `$expiryCol` AS expiry_date
        ".($hasIsLeft ? ", is_left" : "")."
        FROM clients
        WHERE router_id IS NOT NULL AND router_id <> 0
          AND pppoe_id IS NOT NULL AND pppoe_id <> ''
          AND `$expiryCol` IS NOT NULL AND `$expiryCol` <> ''
    ");
    $st->execute();
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
            } elseif (!$shouldDisable && $isDisabled) {
                $API->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'no']);
                $enabled++;
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
