<?php
// cron/save_client_traffic.php
// Purpose: client_traffic_log টেবিল নিয়মিত ভরতে লাইভ স্ট্যাটাস ফেচ করে সেভ করা
// Usage: php cron/save_client_traffic.php [--base-url=http://localhost] [--token=XYZ]

require_once __DIR__ . '/../app/db.php';

$BASE_URL = 'https://127.0.0.1';
$TOKEN    = null;

// CLI args (lightweight parser)
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--base-url=')) $BASE_URL = substr($arg, 11);
    if (str_starts_with($arg, '--token='))    $TOKEN    = substr($arg, 8);
}

// Load token if not passed: env > config CRON_TOKEN > storage/cron_token.txt (fallback only)
if (!$TOKEN && getenv('CRON_TOKEN')) $TOKEN = getenv('CRON_TOKEN');
// (বাংলা) কনস্ট্যান্ট থাকলে constant() দিয়ে নিরাপদে পড়ি—না থাকলে undefined constant এরর এড়ায়
if (!$TOKEN && defined('CRON_TOKEN')) {
    $TOKEN = (string)constant('CRON_TOKEN');
}
if (!$TOKEN && is_readable(__DIR__.'/../storage/cron_token.txt')) {
    $TOKEN = trim((string)@file_get_contents(__DIR__.'/../storage/cron_token.txt'));
}

$clientsPerRun = 0;
$BATCH_SIZE = 100; // process 100 clients per run (avoids long single-run timeouts)
$OFFSET_FILE = __DIR__ . '/../storage/save_client_traffic_offset.json';

// Allow full run (no batching) via flag
$fullRun = false;
foreach ($argv ?? [] as $arg) {
    if ($arg === '--full') $fullRun = true;
}
if (isset($_GET['full']) && $_GET['full'] === '1') $fullRun = true;

$softLimit = 1200; // seconds; generous for large fleets
@set_time_limit($softLimit);

$BASE_URL = rtrim($BASE_URL, '/');

$pdo = db();
$clients = [];
if ($fullRun) {
    $stmt = $pdo->query("SELECT id FROM clients ORDER BY id");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Batch mode: resume from last id, wrap to beginning when needed
    $lastId = 0;
    if (is_readable($OFFSET_FILE)) {
        $decoded = json_decode((string)file_get_contents($OFFSET_FILE), true);
        if (is_array($decoded) && isset($decoded['last_id'])) {
            $lastId = (int)$decoded['last_id'];
        }
    }

    $limit = max(1, (int)$BATCH_SIZE);
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id > ? ORDER BY id LIMIT {$limit}");
    $stmt->execute([$lastId]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$clients) {
        $stmt = $pdo->query("SELECT id FROM clients ORDER BY id LIMIT {$limit}");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$useCurl = function(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'cron/save_client_traffic.php',
    ]);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$out, $err, $code];
};

$okCnt = 0; $failCnt = 0;
foreach ($clients as $client) {
    $clientsPerRun++;
    $client_id = (int)$client['id'];
    $url = $BASE_URL . "/api/client_live_status.php?id={$client_id}";
    if ($TOKEN) $url .= "&cron_token=" . urlencode($TOKEN);

    $json = null;
    $httpErr = '';
    // প্রথমে curl চেষ্টা করি, fallback file_get_contents
    [$out, $err, $code] = $useCurl($url);
    if ($out !== false && $out !== null && $out !== '') {
        $json = $out;
    } else {
        $httpErr = $err ?: "HTTP {$code}";
        $json = @file_get_contents($url);
    }
    if ($json === false || $json === null || $json === '') {
        echo "Failed HTTP for client {$client_id}" . ($httpErr ? " ({$httpErr})" : "") . "\n";
        $failCnt++; continue;
    }

    $data = json_decode($json, true);
    if (empty($data) || ($data['status'] ?? '') !== 'ok') {
        $snippet = substr(trim((string)$json), 0, 160);
        echo "Bad payload for client {$client_id}" . ($snippet ? " (body: {$snippet})" : "") . "\n";
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

// Persist last processed id for batch mode
if (!$fullRun) {
    $lastProcessed = end($clients);
    $lid = $lastProcessed ? (int)$lastProcessed['id'] : 0;
    @file_put_contents($OFFSET_FILE, json_encode(['last_id' => $lid], JSON_PRETTY_PRINT));
}

echo "Done. clients=" . count($clients) . ", success={$okCnt}, failed={$failCnt}" . ($fullRun ? " (full run)" : " (batched)") . "\n";
