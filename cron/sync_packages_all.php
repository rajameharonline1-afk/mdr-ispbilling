<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$API = new RouterosAPI();
$API->debug = false;

// optional router filter: CLI --router-id=12 or GET router_id=12
$routerId = null;
foreach ($argv ?? [] as $arg) {
  if (preg_match('/^--router-id=(\d+)$/', (string)$arg, $m)) {
    $routerId = (int)$m[1];
    break;
  }
}
if (isset($_GET['router_id'])) {
  $routerId = (int)$_GET['router_id'];
}

// সক্রিয় MikroTik রাউটার আনা
$cols = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$where = [];
if ($routerId) $where[] = "id=".$routerId;
if (in_array('type', $cols, true))   $where[] = "type='mikrotik'";
if (in_array('status', $cols, true)) $where[] = "status=1";
$sql = "SELECT id, name, ip, username, password, api_port FROM routers";
if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY id ASC";
$routers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$routers) {
    die("❌ No MikroTik routers found (check routers table or router_id filter).\n");
}

foreach ($routers as $router) {
    echo "=============================\n";
    echo "📡 Connecting to Router: {$router['name']} ({$router['ip']})\n";

    $port = (int)($router['api_port'] ?? 8728) ?: 8728;
    if ($API->connect($router['ip'], $router['username'], $router['password'], $port)) {

        $profiles = $API->comm("/ppp/profile/print");

        foreach ($profiles as $profile) {
            $name = $profile['name'] ?? '';
            $rate = $profile['rate-limit'] ?? '';
            $price = 0.00; // ম্যানুয়ালি পরে সেট করতে হবে
            $validity = 30;

            if ($name == '') continue;

            // ডাটাবেজে আগে আছে কিনা চেক (note: packages.name unique)
            $stmt = $pdo->prepare("SELECT id FROM packages WHERE name = ?");
            $stmt->execute([$name]);
            $exists = $stmt->fetch();

            if ($exists) {
                $update = $pdo->prepare("UPDATE packages SET speed=?, validity=?, router_id=? WHERE id=?");
                $update->execute([$rate, $validity, $router['id'], $exists['id']]);
                echo "🔄 Updated package: $name ($rate)\n";
            } else {
                $insert = $pdo->prepare("INSERT INTO packages (router_id, name, speed, price, validity) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$router['id'], $name, $rate, $price, $validity]);
                echo "➕ Added package: $name ($rate)\n";
            }
        }

        $API->disconnect();
        echo "✅ Sync complete for {$router['name']}!\n";
    } else {
        echo "❌ Failed to connect to {$router['name']} ({$router['ip']}:{$port})\n";
    }
}
