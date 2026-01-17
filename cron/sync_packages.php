<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

// রাউটার বাছাই (DB থেকে active MikroTik). CLI: --router-id=12 অথবা GET router_id=12
$pdo = db();
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

$where = [];
if ($routerId) $where[] = "id=".(int)$routerId;
$cols = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
if (in_array('type', $cols, true))   $where[] = "type='mikrotik'";
if (in_array('status', $cols, true)) $where[] = "status=1";
$sql = "SELECT id,name,ip,username,password,api_port FROM routers";
if ($where) $sql .= " WHERE ".implode(' AND ', $where);
$sql .= " ORDER BY id ASC LIMIT 1";
$router = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
if (!$router) {
  die("❌ No MikroTik router found (check routers table or router_id filter).\n");
}

$ip   = trim((string)$router['ip']);
$user = (string)$router['username'];
$pass = (string)$router['password'];
$port = (int)($router['api_port'] ?? 8728) ?: 8728;

$API = new RouterosAPI();
$API->debug = false;

if ($API->connect($ip, $user, $pass, $port)) {
    echo "✅ Connected to MikroTik ({$router['name']} @ {$ip}:{$port})\n";

    // প্রোফাইল লিস্ট আনা
    $profiles = $API->comm("/ppp/profile/print");

    foreach ($profiles as $profile) {
        $name = $profile['name'] ?? '';
        $rate = $profile['rate-limit'] ?? '';
        $price = 0.00; // MikroTik প্রোফাইলে দাম থাকে না, ম্যানুয়ালি সেট করতে হবে
        $validity = 30; // ডিফল্ট 30 দিন

        if ($name == '') continue;

        // ডাটাবেজে আগে আছে কিনা চেক
        $stmt = $pdo->prepare("SELECT id FROM packages WHERE name = ?");
        $stmt->execute([$name]);
        $exists = $stmt->fetch();

        if ($exists) {
            // আপডেট
            $update = $pdo->prepare("UPDATE packages SET speed=?, validity=? WHERE id=?");
            $update->execute([$rate, $validity, $exists['id']]);
            echo "🔄 Updated package: $name ($rate)\n";
        } else {
            // নতুন ইনসার্ট
            $insert = $pdo->prepare("INSERT INTO packages (name, speed, price, validity) VALUES (?, ?, ?, ?)");
            $insert->execute([$name, $rate, $price, $validity]);
            echo "➕ Added package: $name ($rate)\n";
        }
    }

    $API->disconnect();
    echo "✅ Sync complete!\n";

} else {
    echo "❌ Failed to connect to MikroTik API ({$ip}:{$port})\n";
}
