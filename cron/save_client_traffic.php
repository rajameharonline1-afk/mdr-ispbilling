<?php
// cron/save_client_traffic.php
// Purpose: client_traffic_log টেবিল নিয়মিত ভরতে লাইভ স্ট্যাটাস ফেচ করে সেভ করা
// Usage: php cron/save_client_traffic.php [--base-url=http://localhost] [--token=XYZ]

require_once __DIR__ . '/../app/db.php';

$BASE_URL = 'http://localhost';
$TOKEN    = null;

// CLI args (lightweight parser)
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--base-url=')) $BASE_URL = substr($arg, 11);
    if (str_starts_with($arg, '--token='))    $TOKEN    = substr($arg, 8);
}

// Load token if not passed: env > storage/cron_token.txt > config CRON_TOKEN (if defined)
if (!$TOKEN && getenv('CRON_TOKEN')) $TOKEN = getenv('CRON_TOKEN');
if (!$TOKEN && is_readable(__DIR__.'/../storage/cron_token.txt')) {
    $TOKEN = trim((string)@file_get_contents(__DIR__.'/../storage/cron_token.txt'));
}
if (!$TOKEN && defined('CRON_TOKEN')) $TOKEN = (string)CRON_TOKEN;

$BASE_URL = rtrim($BASE_URL, '/');

$pdo = db();
$stmt = $pdo->query("SELECT id FROM clients");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$okCnt = 0; $failCnt = 0;
foreach ($clients as $client) {
    $client_id = (int)$client['id'];
    $url = $BASE_URL . "/api/client_live_status.php?id={$client_id}";
    if ($TOKEN) $url .= "&cron_token=" . urlencode($TOKEN);

    $json = @file_get_contents($url);
    if ($json === false) { echo "Failed HTTP for client {$client_id}\n"; $failCnt++; continue; }

    $data = json_decode($json, true);
    if (empty($data) || ($data['status'] ?? '') !== 'ok') {
        echo "Bad payload for client {$client_id}\n";
        $failCnt++; continue;
    }

    $rx = isset($data['rx_kbps']) ? (int)$data['rx_kbps'] : 0;
    $tx = isset($data['tx_kbps']) ? (int)$data['tx_kbps'] : 0;
    $dl = isset($data['total_download_gb']) ? (float)$data['total_download_gb'] : 0.0;
    $ul = isset($data['total_upload_gb'])   ? (float)$data['total_upload_gb']   : 0.0;

    $st = $pdo->prepare("INSERT INTO client_traffic_log 
        (client_id, log_time, rx_speed, tx_speed, total_download_gb, total_upload_gb)
        VALUES (?, NOW(), ?, ?, ?, ?)");
    $st->execute([$client_id, $rx, $tx, $dl, $ul]);

    $okCnt++;
    echo "Saved log for client {$client_id} (rx={$rx} kbps, tx={$tx} kbps)\n";
}

echo "Done. success={$okCnt}, failed={$failCnt}\n";
