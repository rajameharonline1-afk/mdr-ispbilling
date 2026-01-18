<?php
// cron/cron_runner.php
// cron_schedules.json পড়ে নির্দিষ্ট ক্রন এক্সপ্রেশন অনুযায়ী জব HTTP দিয়ে ট্রিগার করে
// উদাহরণ crontab: * * * * * php /var/www/isp_billing/cron/cron_runner.php --base-url="http://127.0.0.1/isp_billing" --token="YOUR_TOKEN"

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

$BASE_URL = 'http://127.0.0.1/isp_billing';
$TOKEN    = defined('CRON_TOKEN') ? CRON_TOKEN : null;
$SCHEDULE_FILE = __DIR__ . '/../storage/cron_schedules.json';
$ENABLE_FILE   = __DIR__ . '/../storage/cron_enabled.json';

// CLI আর্গ পার্স
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--base-url=') === 0) $BASE_URL = substr($arg, 11);
    if (strpos($arg, '--token=') === 0)    $TOKEN    = substr($arg, 8);
}
$BASE_URL = rtrim($BASE_URL, '/');

// জব ডেফিনিশন (URL + ক্রন এক্সপ্রেশন)
$jobs = [
  'mt_sync' => [
    'title' => 'MikroTik Sync Clients',
    'url'   => '/api/mt_sync_clients.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '*/5 * * * *',
  ],
  'invoice_month' => [
    'title' => 'Generate Monthly Invoices (commit)',
    'url'   => '/cron/generate_invoices.php?month={month}&mode=replace',
    'method'=> 'GET',
    'timeout' => 240,
    'schedule' => '0 6 1 * *',
  ],
  'pppoe_olt_link' => [
    'title' => 'PPPoE → OLT Auto Link',
    'url'   => '/cron/auto_link_pppoe_olt.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '2-59/5 * * * *', // ভারী OLT জব থেকে টাইম সরে রাখা
  ],
  'auto_billing' => [
    'title' => 'Auto Billing',
    'url'   => '/cron/auto_billing.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '15 2 1 * *', // মাসের ১ তারিখ ভোরে, OLT উইন্ডোর পরে
  ],
  'auto_suspend' => [
    'title' => 'Auto Suspend (due clients)',
    'url'   => '/cron/auto_suspend.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '7-59/15 * * * *', // OLT শুরুর টাইম থেকে সরে
  ],
  'auto_suspend_enable' => [
    'title' => 'Auto Resume (enable)',
    'url'   => '/cron/auto_suspend_enable.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '12-59/15 * * * *', // suspend/OLT থেকে আলাদা স্লট
  ],
  'auto_expire_inactive' => [
    'title' => 'Expire Inactive Accounts',
    'url'   => '/cron/auto_expire_inactive.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '30 3 * * *',
  ],
  'save_client_traffic' => [
    'title' => 'Save Client Traffic',
    'url'   => '/cron/save_client_traffic.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '*/10 * * * *',
  ],
  'sms_sender' => [
    'title' => 'SMS Sender',
    'url'   => '/cron/sms_sender.php',
    'method'=> 'GET',
    'timeout' => 120,
    'schedule' => '*/5 * * * *',
  ],
  'sms_due_reminder' => [
    'title' => 'SMS Due Reminder',
    'url'   => '/cron/sms_due_reminder.php',
    'method'=> 'GET',
    'timeout' => 120,
    'schedule' => '0 9 * * *',
  ],
  'enqueue_due_notifications' => [
    'title' => 'Enqueue Due Notifications',
    'url'   => '/cron/enqueue_due_notifications.php',
    'method'=> 'GET',
    'timeout' => 120,
    'schedule' => '10 */2 * * *', // OLT overlap এড়াতে ঘন্টা+10 এ চালাও
  ],
  'notify_runner' => [
    'title' => 'Notification Runner',
    'url'   => '/cron/notify_runner.php',
    'method'=> 'GET',
    'timeout' => 120,
    'schedule' => '20,50 * * * *', // OLT start (:00/:30) এড়িয়ে ২০/৫০ মিনিটে
  ],
  'sync_clients' => [
    'title' => 'Sync Clients (general)',
    'url'   => '/cron/sync_clients.php',
    'method'=> 'GET',
    'timeout' => 240,
    'schedule' => '*/5 * * * *',
  ],
  'sync_packages' => [
    'title' => 'Sync Packages (default)',
    'url'   => '/cron/sync_packages.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '3-59/10 * * * *', // OLT :00/:30 এড়াতে ৩ মিনিট অফসেট, প্রতি ১০ মিনিট
  ],
  'sync_packages_all' => [
    'title' => 'Sync Packages (all)',
    'url'   => '/cron/sync_packages_all.php',
    'method'=> 'GET',
    'timeout' => 240,
    'schedule' => '3-59/10 * * * *', // same offset as default
  ],
  'auto_bkash_apply' => [
    'title' => 'Auto bKash Apply',
    'url'   => '/cron/auto_bkash_apply.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '5,35 * * * *', // OLT :00/:30 এড়াতে ৫/৩৫ মিনিটে
  ],
  'bkash_rtn_process' => [
    'title' => 'bKash Return Process',
    'url'   => '/cron/bkash_rtn_process.php',
    'method'=> 'GET',
    'timeout' => 120,
    'schedule' => '*/10 * * * *',
  ],
  'db_backup' => [
    'title' => 'DB Backup',
    'url'   => '/cron/db_backup.php',
    'method'=> 'GET',
    'timeout' => 300,
    'schedule' => '15 3 * * *', // রাত ৩:১৫ এ (OLT+billing থেকে সরে)
  ],
  'generate_invoices' => [
    'title' => 'Generate Invoices (legacy)',
    'url'   => '/cron/generate_invoices.php',
    'method'=> 'GET',
    'timeout' => 240,
    'schedule' => '0 1 * * *',
  ],
  'auto_link_pppoe_olt_legacy' => [
    'title' => 'Auto Link PPPoE to OLT (legacy)',
    'url'   => '/cron/auto_link_pppoe_olt.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '*/5 * * * *',
  ],
  'olt_mac_refresh_telnet' => [
    'title' => 'OLT MAC Refresh (telnet)',
    'url'   => '/api/olt_mac_refresh_telnet.php?mode=full',
    'method'=> 'GET',
    'timeout' => 900, // পূর্ণ রিফ্রেশ শেষ করতে বেশি টাইমআউট
    'schedule' => '*/30 * * * *',
  ],
];

// JSON থেকে শিডিউল ওভাররাইড লোড
if (is_file($SCHEDULE_FILE)) {
    $data = json_decode((string)file_get_contents($SCHEDULE_FILE), true);
    if (is_array($data)) {
        foreach ($data as $k=>$v) {
            if (isset($jobs[$k]) && is_string($v) && trim($v) !== '') {
                $jobs[$k]['schedule'] = trim($v);
            }
        }
    }
}
$enabled = [];
if (is_file($ENABLE_FILE)) {
    $edata = json_decode((string)file_get_contents($ENABLE_FILE), true);
    if (is_array($edata)) $enabled = $edata;
}

// ক্রন ম্যাচার (মিনিট লেভেল; অতিরিক্ত ফিল্ড সহনশীল)
function cron_match(string $expr, DateTime $dt): bool {
  $parts = preg_split('/\s+/', trim($expr));
  if (count($parts) < 5) return false;
  if (count($parts) > 5) $parts = array_slice($parts, 0, 5); // যদি ৬ ফিল্ড থাকে, বাড়তি বাদ
  [$m,$h,$d,$mo,$w] = $parts;
    $checks = [
        [$m,  (int)$dt->format('i'), 0, 59],
        [$h,  (int)$dt->format('G'), 0, 23],
        [$d,  (int)$dt->format('j'), 1, 31],
        [$mo, (int)$dt->format('n'), 1, 12],
        [$w,  (int)$dt->format('w'), 0, 6],
    ];
    foreach ($checks as [$seg,$val,$min,$max]) {
        if (!cron_match_segment($seg, $val, $min, $max)) return false;
    }
    return true;
}
function cron_match_segment(string $seg, int $val, int $min, int $max): bool {
    $seg = trim($seg);
    if ($seg === '*') return true;
    $options = explode(',', $seg);
    foreach ($options as $opt) {
        $opt = trim($opt);
        if ($opt === '') continue;
        $step = 1;
        if (strpos($opt, '/') !== false) {
            [$opt, $stepStr] = explode('/', $opt, 2);
            $step = max(1, (int)$stepStr);
        }
        if ($opt === '*') {
            if (($val - $min) % $step === 0) return true;
            continue;
        }
        if (strpos($opt, '-') !== false) {
            [$a,$b] = array_map('intval', explode('-', $opt, 2));
            if ($a > $b) [$a,$b] = [$b,$a];
            if ($val >= $a && $val <= $b && (($val - $a) % $step === 0)) return true;
            continue;
        }
        if (is_numeric($opt)) {
            $v = (int)$opt;
            if ($v === $val) return true;
        }
    }
    return false;
}

// গ্লোবাল ওভারল্যাপ আটকাতে লক
$pdo = db();
$lock = $pdo->query("SELECT GET_LOCK('cron_runner_lock', 1)")->fetchColumn();
if ((int)$lock !== 1) {
    echo "Lock busy\n";
    exit;
}
$runningCheck = $pdo->prepare("SELECT COUNT(*) FROM cron_runs WHERE job_key=? AND finished_at IS NULL");

$now = new DateTime('now');
$dueJobs = [];
foreach ($jobs as $key=>$job) {
    if (isset($enabled[$key]) && !$enabled[$key]) continue; // UI থেকে নিষ্ক্রিয়
    $expr = trim((string)($job['schedule'] ?? '* * * * *'));
    if (cron_match($expr, $now)) {
        $dueJobs[$key] = $job;
    }
}

function http_call(string $url, string $method='GET', array $post=[], int $timeout=120): array {
    $method = strtoupper($method);
    $ch = curl_init();
    if ($method === 'GET' && $post) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($post);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'cron_runner',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $out = curl_exec($ch);
    $err = curl_error($ch);
    $code= (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok'=>($err==='' && $code>=200 && $code<300), 'status'=>$code, 'output'=>$out?:'', 'error'=>$err];
}

$ok = 0; $fail = 0;
foreach ($dueJobs as $key=>$job) {
    // একই জব চলছে কিনা—চললে স্কিপ (API lock_busy এড়াতে)
    try {
        $runningCheck->execute([$key]);
        if ((int)$runningCheck->fetchColumn() > 0) {
            echo "SKIP: $key (previous run still in progress)\n";
            continue;
        }
    } catch (Throwable $e) {
        // ত্রুটি হলেও চালিয়ে যান
    }
    $url = $job['url'];
    if (strpos($url, '{month}') !== false) {
        $url = str_replace('{month}', $now->format('Y-m'), $url);
    }
    // টোকেন কুয়েরি প্যারামে যোগ
    if ($TOKEN) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . urlencode($TOKEN);
    }
    $full = $BASE_URL . (str_starts_with($url, '/') ? $url : ('/'.$url));
    $startedAt = date('Y-m-d H:i:s');
    $started = microtime(true);
    // UI “Currently running” দেখাতে আগে লগ ইন্সার্ট
    $runId = null;
    $stStart = $pdo->prepare("INSERT INTO cron_runs (job_key,title,status,started_at,triggered_by) VALUES (?,?,?,?,0)");
    $stStart->execute([$key, $job['title'] ?? $key, null, $startedAt]);
    $runId = (int)$pdo->lastInsertId();

    $res = http_call($full, $job['method'] ?? 'GET', [], (int)($job['timeout'] ?? 120));
    $dur = (int)round((microtime(true)-$started)*1000);

    // রান শেষে cron_runs আপডেট
    $status = $res['ok'] ? 'success' : 'failed';
    $out = (string)($res['output'] ?? '');
    if (strlen($out) > 1024*512) $out = substr($out,0,1024*512).'...[truncated]';
    $err = (string)($res['error'] ?? '');
    $st = $pdo->prepare("UPDATE cron_runs SET status=?, finished_at=NOW(), duration_ms=?, output=?, error=? WHERE id=?");
    $st->execute([$status, $dur, $out, $err, $runId]);

    if ($res['ok']) {
        $ok++; echo "OK: $key ($dur ms)\n";
    } else {
        $fail++; echo "FAIL: $key (HTTP {$res['status']} / {$err})\n";
    }
}

$pdo->query("SELECT RELEASE_LOCK('cron_runner_lock')");
echo "Done. ok=$ok fail=$fail\n";
