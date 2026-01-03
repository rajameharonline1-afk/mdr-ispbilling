<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

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

// ১ম ধাপ: আজ মেয়াদ শেষ হলে Expired করা
$stmt_expire = $pdo->prepare("UPDATE clients SET status='expired' 
                              WHERE `$expiryCol` = ? AND status != 'expired' AND status != 'inactive'");
$expire_count = $stmt_expire->execute([$today]) ? $stmt_expire->rowCount() : 0;

// ১ম.৫ ধাপ: Expiry বাড়ানো হলে Active করা (inactive/expired → active)
$stmt_reactivate = $pdo->prepare("UPDATE clients SET status='active'
                                  WHERE `$expiryCol` > ? AND status IN ('inactive','expired')");
$reactivate_count = $stmt_reactivate->execute([$today]) ? $stmt_reactivate->rowCount() : 0;

// ২য় ধাপ: Expired হওয়ার পরের দিন Inactive করা
$stmt_inactive = $pdo->prepare("UPDATE clients SET status='inactive' 
                                WHERE `$expiryCol` = ? AND status='expired'");
$inactive_count = $stmt_inactive->execute([$yesterday]) ? $stmt_inactive->rowCount() : 0;

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
            if ($exp === '') continue;

            $shouldDisable = ($exp <= $today);
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
