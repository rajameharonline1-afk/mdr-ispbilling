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
$LOCK_WAIT_SECONDS   = 5;      // GET_LOCK wait window
$STALE_LOCK_SECONDS  = 1800;   // idle MySQL session holding lock beyond this is treated as stale
$STALE_RUN_FAIL_SEC  = 900;    // stale running rows will be auto-failed after 15 minutes
$JOB_STALE_LIMITS    = [
    'save_client_traffic' => 1800, // heavy fetch; allow up to 30 minutes before force-fail
];

// CLI আর্গ পার্স
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--base-url=') === 0) $BASE_URL = substr($arg, 11);
    if (strpos($arg, '--token=') === 0)    $TOKEN    = substr($arg, 8);
}
$BASE_URL = rtrim($BASE_URL, '/');
$baseCandidates = build_base_candidates($BASE_URL);
if (empty($baseCandidates)) {
    $baseCandidates = [$BASE_URL ?: 'http://127.0.0.1'];
}
$preferredBaseIndex = 0;

function build_url_from_parts(array $parts): string
{
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'http://';
    $host   = $parts['host'] ?? '127.0.0.1';
    $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user   = $parts['user'] ?? '';
    $pass   = isset($parts['pass']) && $parts['pass'] !== '' ? ':' . $parts['pass'] : '';
    $auth   = $user !== '' ? $user . $pass . '@' : '';
    $path   = $parts['path'] ?? '';
    $path   = rtrim($path ?: '', '/');
    return $scheme . $auth . $host . $port . ($path !== '' ? $path : '');
}

function build_base_candidates(string $base): array
{
    if ($base === '') return [];
    $parts = parse_url($base);
    if ($parts === false) {
        return [$base];
    }
    $path = $parts['path'] ?? '';
    $path = rtrim($path ?: '', '/');
    $candidates = [];
    while (true) {
        $parts['path'] = $path;
        $candidate = rtrim(build_url_from_parts($parts), '/');
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
        if ($path === '') break;
        $pos = strrpos($path, '/');
        if ($pos === false) {
            $path = '';
        } else {
            $path = substr($path, 0, $pos);
        }
    }
    if (empty($candidates)) {
        $candidates[] = $base;
    }
    return array_values(array_unique($candidates));
}

function build_job_full_url(string $base, string $path): string
{
    $base = rtrim($base, '/');
    if ($path === '') {
        return $base;
    }
    if (str_starts_with($path, '/')) {
        return $base . $path;
    }
    return $base . '/' . ltrim($path, '/');
}

function ensure_cron_runs_table(PDO $pdo): void
{
    // (বাংলা) ক্রন লগ টেবিল না থাকলে তৈরি করি, যাতে ওভারল্যাপ লজিক ডাটাবেজ ছাড়া না পড়ে
    $pdo->exec("
CREATE TABLE IF NOT EXISTS cron_runs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_key VARCHAR(64) NOT NULL,
  title   VARCHAR(255) NOT NULL,
  status  ENUM('success','failed') DEFAULT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  duration_ms INT UNSIGNED DEFAULT NULL,
  output MEDIUMTEXT NULL,
  error  MEDIUMTEXT NULL,
  triggered_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(job_key),
  INDEX(status),
  INDEX(started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
}

// জব ডেফিনিশন (URL + ক্রন এক্সপ্রেশন)
$jobs = [
  'mt_sync' => [
    'title' => 'MikroTik Sync Clients',
    'url'   => '/api/mt_sync_clients.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '* * * * *', // প্রতি মিনিটে দ্রুত সিঙ্ক
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
    'timeout' => 420,
    'schedule' => '7-59/15 * * * *', // OLT শুরুর টাইম থেকে সরে
  ],
  'auto_suspend_enable' => [
    'title' => 'Auto Resume (enable)',
    'url'   => '/cron/auto_suspend_enable.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '* * * * *', // প্রতি মিনিটে ফাস্ট রেজিউম
  ],
  'auto_expire_inactive' => [
    'title' => 'Expire Inactive Accounts',
    'url'   => '/cron/auto_expire_inactive.php',
    'method'=> 'GET',
    'timeout' => 180,
    'schedule' => '30 3 * * *',
  ],
  'expire_inactive' => [
    'title' => 'Expire Inactive (disable PPPoE)',
    'url'   => '/cron/expire_inactive.php',
    'method'=> 'GET',
    'timeout' => 300,
    'schedule' => '0 7 * * *',
  ],
  'expire_active' => [
    'title' => 'Expire Active (enable PPPoE)',
    'url'   => '/cron/expire_active.php',
    'method'=> 'GET',
    'timeout' => 300,
    'schedule' => '* * * * *',
  ],
  'save_client_traffic' => [
    'title' => 'Save Client Traffic',
    'url'   => '/cron/save_client_traffic.php',
    'method'=> 'GET',
    'timeout' => 1200,
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
    'schedule' => '0 */3 * * *', // প্রতি ৩ ঘন্টায়
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

function is_mysql_gone(Throwable $e): bool {
    $code = (string)($e->getCode() ?? '');
    $msg  = strtolower($e->getMessage());
    return str_contains($msg, 'server has gone away') || str_contains($msg, 'lost connection') || in_array($code, ['2006','2013'], true);
}

function running_job_hint(PDO $pdo): string {
    try {
        $st = $pdo->query("SELECT job_key, TIMESTAMPDIFF(SECOND, started_at, NOW()) AS elapsed FROM cron_runs WHERE finished_at IS NULL ORDER BY started_at DESC LIMIT 3");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return '';
        $parts = [];
        foreach ($rows as $row) {
            $elapsed = isset($row['elapsed']) ? (int)$row['elapsed'] : 0;
            $parts[] = ($row['job_key'] ?? '?') . ' (+' . $elapsed . 's)';
        }
        return implode(', ', $parts);
    } catch (Throwable $e) {
        return '';
    }
}

function acquire_runner_lock(PDO &$pdo, int $waitSeconds, int $staleSeconds, ?string &$note=null, bool $reconnected=false): bool {
    try {
        $lock = $pdo->query("SELECT GET_LOCK('cron_runner_lock', {$waitSeconds})")->fetchColumn();
        if ((int)$lock === 1) {
            return true;
        }
    } catch (Throwable $e) {
        if (!$reconnected && is_mysql_gone($e)) {
            $pdo = db(true);
            return acquire_runner_lock($pdo, $waitSeconds, $staleSeconds, $note, true);
        }
        $note = 'lock query failed: ' . $e->getMessage();
        return false;
    }

    $usedId = null;
    try {
        $usedId = $pdo->query("SELECT IS_USED_LOCK('cron_runner_lock')")->fetchColumn();
    } catch (Throwable $e) {
        if (!$reconnected && is_mysql_gone($e)) {
            $pdo = db(true);
            return acquire_runner_lock($pdo, $waitSeconds, $staleSeconds, $note, true);
        }
        $note = 'lock status unavailable';
        return false;
    }
    if (!$usedId) {
        return false;
    }

    try {
        $st = $pdo->prepare("SELECT ID, TIME, COMMAND FROM information_schema.processlist WHERE ID=?");
        $st->execute([(int)$usedId]);
        $row = $st->fetch();
        if (!$row) return false;
        $idle = (int)($row['TIME'] ?? 0);
        $command = strtolower((string)($row['COMMAND'] ?? ''));
        if ($command === 'sleep' && $idle >= $staleSeconds) {
            // stale lock holder—terminate the connection so runner can continue
            $pdo->exec("KILL " . (int)$usedId);
            $note = "Recovered stale lock from connection {$usedId} (idle {$idle}s)";
            $lock = $pdo->query("SELECT GET_LOCK('cron_runner_lock', {$waitSeconds})")->fetchColumn();
            return (int)$lock === 1;
        }
        $note = "Lock held by connection {$usedId} ({$command}, {$idle}s)";
    } catch (Throwable $e) {
        if (!$reconnected && is_mysql_gone($e)) {
            $pdo = db(true);
            return acquire_runner_lock($pdo, $waitSeconds, $staleSeconds, $note, true);
        }
        $note = 'lock check failed';
    }
    return false;
}

function acquire_job_lock(PDO $pdo, string $jobKey, int $waitSeconds = 2): bool {
    $name = 'cron_job_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $jobKey);
    $st = $pdo->prepare("SELECT GET_LOCK(?, ?)");
    $st->execute([$name, $waitSeconds]);
    return (int)$st->fetchColumn() === 1;
}

function release_job_lock(PDO $pdo, string $jobKey): void {
    try {
        $name = 'cron_job_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $jobKey);
        $st = $pdo->prepare("SELECT RELEASE_LOCK(?)");
        $st->execute([$name]);
    } catch (Throwable $_) {}
}

// গ্লোবাল ওভারল্যাপ আটকাতে লক
$pdo = db();
$lockNote = null;
if (!acquire_runner_lock($pdo, $LOCK_WAIT_SECONDS, $STALE_LOCK_SECONDS, $lockNote)) {
    $pieces = [];
    if ($lockNote) {
        $pieces[] = $lockNote;
    }
    $runningHint = running_job_hint($pdo);
    if ($runningHint !== '') {
        $pieces[] = "running: {$runningHint}";
    }
    $suffix = $pieces ? ' (' . implode('; ', $pieces) . ')' : '';
    echo "Lock busy{$suffix}\n";
    return;
}
$runningCheck = null;
$runningCheckNote = null;

try {
    ensure_cron_runs_table($pdo);
    try {
        $runningCheck = $pdo->prepare("SELECT COUNT(*) FROM cron_runs WHERE job_key=? AND finished_at IS NULL");
    } catch (Throwable $e) {
        // (বাংলা) রানিং চেক না পারলেও জব চালু রাখা হবে যাতে ক্রন থেমে না যায়
        $runningCheckNote = 'concurrency check soft-disabled: ' . $e->getMessage();
        echo "WARN: {$runningCheckNote}\n";
    }

    // stale run auto-close যাতে নতুন জব ব্লক না হয়
    try {
        $customKeys = array_keys($JOB_STALE_LIMITS);
        // default stale closer (jobs without custom limit)
        $placeholders = $customKeys ? implode(',', array_fill(0, count($customKeys), '?')) : '';
        $sql = "
            UPDATE cron_runs
            SET status='failed',
                finished_at=IFNULL(finished_at, NOW()),
                duration_ms=IFNULL(duration_ms, TIMESTAMPDIFF(SECOND, started_at, NOW())*1000),
                error=CONCAT(
                    'Auto-closed stale run (', TIMESTAMPDIFF(SECOND, started_at, NOW()), 's)',
                    CASE WHEN error IS NULL OR error='' THEN '' ELSE CONCAT('; prev: ', error) END
                )
            WHERE finished_at IS NULL
              AND TIMESTAMPDIFF(SECOND, started_at, NOW()) > ?
        ";
        if ($placeholders !== '') {
            $sql .= " AND job_key NOT IN ($placeholders)";
        }
        $stale = $pdo->prepare($sql);
        $params = [$STALE_RUN_FAIL_SEC];
        if ($customKeys) {
            $params = array_merge($params, $customKeys);
        }
        $stale->execute($params);

        // per-job stale limits
        foreach ($JOB_STALE_LIMITS as $jKey => $limitSec) {
            $staleJob = $pdo->prepare("
                UPDATE cron_runs
                SET status='failed',
                    finished_at=IFNULL(finished_at, NOW()),
                    duration_ms=IFNULL(duration_ms, TIMESTAMPDIFF(SECOND, started_at, NOW())*1000),
                    error=CONCAT(
                        'Auto-closed stale run (', TIMESTAMPDIFF(SECOND, started_at, NOW()), 's)',
                        CASE WHEN error IS NULL OR error='' THEN '' ELSE CONCAT('; prev: ', error) END
                    )
                WHERE finished_at IS NULL
                  AND job_key=?
                  AND TIMESTAMPDIFF(SECOND, started_at, NOW()) > ?
            ");
            $staleJob->execute([$jKey, (int)$limitSec]);
        }
    } catch (Throwable $e) {
        // ignore stale cleanup errors
    }

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
    if ($lockNote) {
        echo $lockNote . "\n";
    }
    if ($runningCheckNote) {
        echo "NOTE: {$runningCheckNote}\n";
    }
    foreach ($dueJobs as $key=>$job) {
        $jobHasLock = acquire_job_lock($pdo, $key, 2);
        if (!$jobHasLock) {
            echo "SKIP: $key (job lock busy)\n";
            continue;
        }
        // একই জব চলছে কিনা—চললে স্কিপ (API lock_busy এড়াতে)
        if ($runningCheck) {
            try {
                $runningCheck->execute([$key]);
                if ((int)$runningCheck->fetchColumn() > 0) {
                    echo "SKIP: $key (previous run still in progress)\n";
                    release_job_lock($pdo, $key);
                    continue;
                }
            } catch (Throwable $e) {
                echo "WARN: $key concurrency check failed, continuing: {$e->getMessage()}\n";
            }
        }
        $url = $job['url'];
        if (strpos($url, '{month}') !== false) {
            $url = str_replace('{month}', $now->format('Y-m'), $url);
        }
        // টোকেন কুয়েরি প্যারামে যোগ
        if ($TOKEN) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . urlencode($TOKEN);
        }
        $startedAt = date('Y-m-d H:i:s');
        $started = microtime(true);
        // UI “Currently running” দেখাতে আগে লগ ইন্সার্ট
        $runId = null;
        try {
            $stStart = $pdo->prepare("INSERT INTO cron_runs (job_key,title,status,started_at,triggered_by) VALUES (?,?,?,?,0)");
            $stStart->execute([$key, $job['title'] ?? $key, null, $startedAt]);
            $runId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            echo "WARN: $key log start failed (will still run): {$e->getMessage()}\n";
        }

        try {
            $orderCount = count($baseCandidates);
            $orderIndices = [];
            for ($i = 0; $i < $orderCount; $i++) {
                $orderIndices[] = ($preferredBaseIndex + $i) % $orderCount;
            }
            $usedBaseIndex = $preferredBaseIndex;
            $res = null;
            foreach ($orderIndices as $baseIdx) {
                $candidateBase = $baseCandidates[$baseIdx];
                $full = build_job_full_url($candidateBase, $url);
                $res = http_call($full, $job['method'] ?? 'GET', [], (int)($job['timeout'] ?? 120));
                if ($res['ok'] || $res['status'] !== 404) {
                    $usedBaseIndex = $baseIdx;
                    break;
                }
            }
            if ($res === null) {
                $res = ['ok'=>false,'status'=>0,'output'=>'','error'=>'failed to build request'];
            }
            $preferredBaseIndex = $usedBaseIndex;
            $dur = (int)round((microtime(true)-$started)*1000);

            // রান শেষে cron_runs আপডেট
            $status = $res['ok'] ? 'success' : 'failed';
            $out = (string)($res['output'] ?? '');
            if (strlen($out) > 1024*512) $out = substr($out,0,1024*512).'...[truncated]';
            $err = (string)($res['error'] ?? '');
            if ($runId) {
                try {
                    $st = $pdo->prepare("UPDATE cron_runs SET status=?, finished_at=NOW(), duration_ms=?, output=?, error=? WHERE id=?");
                    $st->execute([$status, $dur, $out, $err, $runId]);
                } catch (Throwable $e) {
                    echo "WARN: $key result logging failed: {$e->getMessage()}\n";
                }
            }

            if ($res['ok']) {
                $ok++; echo "OK: $key ($dur ms)\n";
            } else {
                $fail++; echo "FAIL: $key (HTTP {$res['status']} / {$err})\n";
            }
        } catch (Throwable $e) {
            $fail++;
            if ($runId) {
                try {
                    $pdo->prepare("
                        UPDATE cron_runs
                        SET status='failed',
                            finished_at=IFNULL(finished_at, NOW()),
                            duration_ms=IFNULL(duration_ms, TIMESTAMPDIFF(SECOND, started_at, NOW())*1000),
                            error=?
                        WHERE id=?")->execute(['runner exception: ' . $e->getMessage(), $runId]);
                } catch (Throwable $_) {
                    // ignore secondary logging errors
                }
            }
            echo "FAIL: $key (runner error: {$e->getMessage()})\n";
            continue;
        }
        finally {
            release_job_lock($pdo, $key);
        }
    }

    echo "Done. ok=$ok fail=$fail\n";
} catch (Throwable $e) {
    echo "Cron runner aborted: " . $e->getMessage() . "\n";
} finally {
    try {
        $pdo->query("SELECT RELEASE_LOCK('cron_runner_lock')");
    } catch (Throwable $e) {
        // ignore release errors
    }
}
