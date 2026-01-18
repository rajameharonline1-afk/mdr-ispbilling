<?php
// /public/cron_dashboard.php
// Cron Dashboard — Run now + Last run history (+ month picker for invoice job)
// UI: English; Comments: বাংলা
declare(strict_types=1);

// config আগে লোড করি যেন CRON_TOKEN পাওয়া যায়
require_once __DIR__ . '/../app/config.php';

// (বাংলা) CLI আর্গুমেন্ট পার্স (key=value) → $_GET/$_POST এ ইনজেক্ট করি
$IS_CLI = PHP_SAPI === 'cli';
if ($IS_CLI && !empty($argv)) {
  foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '=') !== false) {
      [$k, $v] = explode('=', $arg, 2);
      if ($k !== '') {
        $_GET[$k] = $_GET[$k] ?? $v;
        $_POST[$k] = $_POST[$k] ?? $v;
      }
    }
  }
}

// (বাংলা) CLI বা টোকেন এলে require_login ছাড়াই চালানোর গার্ড
$tokenParam = $_GET['token'] ?? $_POST['token'] ?? getenv('CRON_TOKEN') ?? '';
if ($tokenParam && defined('CRON_TOKEN') && $tokenParam === CRON_TOKEN) {
  require_once __DIR__ . '/../app/db.php';
} elseif ($IS_CLI) {
  // CLI মোডে বৈধ টোকেন না থাকলে ব্লক
  fwrite(STDERR, "403: Permission denied (CLI token missing/invalid)\n");
  http_response_code(403);
  exit;
} else {
  require_once __DIR__ . '/../app/require_login.php';
  require_once __DIR__ . '/../app/db.php';
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dbh(){ return db(); }
function close_session_if_open(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close(); // সেশন লক ছেড়ে দেই যাতে অন্য ট্যাব সাড়া দেয়
  }
}
function is_ajax(): bool {
  return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || ($_POST['ajax'] ?? '') === '1'
    || ($_GET['ajax'] ?? '') === '1';
}
$BASE_PREFIX = '';
if (!empty($_SERVER['SCRIPT_NAME'])) {
  $parts = explode('/', trim((string)$_SERVER['SCRIPT_NAME'], '/'));
  if (!empty($parts[0]) && $parts[0] === 'isp_billing') {
    $BASE_PREFIX = '/isp_billing';
  }
}
$CRON_SCHEDULE_FILE = __DIR__ . '/../storage/cron_schedules.json';
$CRON_ENABLE_FILE   = __DIR__ . '/../storage/cron_enabled.json';
$CURRENT_USER_ID = (int)($_SESSION['user']['id'] ?? 0);
$CURRENT_SESSION_ID = session_id();
// দীর্ঘ জবের সময়ও অন্য ট্যাব সাড়া দিতে পারে—তাই সেশন লক আগে ছেড়ে দেই
close_session_if_open();

function load_schedules(string $file, array $jobs): array {
  if (!is_file($file)) {
    $def = [];
    foreach ($jobs as $k=>$j) { $def[$k] = $j['schedule'] ?? '*/5 * * * *'; }
    return $def;
  }
  $txt = @file_get_contents($file);
  $data = $txt ? json_decode($txt, true) : [];
  if (!is_array($data)) $data = [];
  foreach ($jobs as $k=>$j) {
    if (!isset($data[$k])) $data[$k] = $j['schedule'] ?? '*/5 * * * *';
  }
  return $data;
}

function save_schedules(string $file, array $map): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return (bool)@file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}
function load_enabled(string $file, array $jobs): array {
  $def = [];
  foreach ($jobs as $k=>$j) { $def[$k] = true; }
  if (!is_file($file)) return $def;
  $txt = @file_get_contents($file);
  $data = $txt ? json_decode($txt, true) : [];
  if (!is_array($data)) return $def;
  foreach ($def as $k=>$_) {
    if (!isset($data[$k])) $data[$k] = true;
  }
  return $data;
}
function save_enabled(string $file, array $map): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return (bool)@file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

/* ---------- Admin-only গার্ড (UI access) ---------- */
// টোকেন/CLI এলে স্কিপ; শুধু লগইন UI তে অ্যাডমিন বাধ্যতামূলক
if (!$IS_CLI && $tokenParam === '') {
  if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}


/* ---------- Ensure log table ---------- */
// (বাংলা) ক্রন রান লগ টেবিল — না থাকলে বানাই
dbh()->exec("
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

/* ---------- Declare cron jobs ---------- */
/* (বাংলা) নতুন জব যোগ করতে $jobs অ্যারে-তে আরেকটা কী যোগ করুন */
$current_month = date('Y-m');
$jobs = [
  'mt_sync' => [
    'title' => 'MikroTik Sync Clients',
    'url'   => '/api/mt_sync_clients.php',
    'method'=> 'GET',
    'desc'  => 'Upsert clients from RouterOS (show-sensitive aware).',
    'timeout' => 180,
    'schedule' => '*/5 * * * *',
    'supports' => [], // extra inputs নেই
  ],
  'invoice_month' => [
    'title' => 'Generate Monthly Invoices (commit)',
    // (বাংলা) {month} প্লেসহোল্ডার রিপ্লেস হবে; নিচে ফর্মে month নেওয়া হচ্ছে
    // JSON API পথ ব্যবহার করছি যাতে HTML আউটপুট না আসে
    'url'   => '/cron/generate_invoices.php?month={month}&mode=replace',
    'method'=> 'GET',
    'desc'  => 'Create/replace invoices for a given month and update ledgers.',
    'timeout' => 240,
    'schedule' => '0 6 1 * *',
    'supports' => ['month'], // extra input: month (YYYY-MM)
  ],
  'pppoe_olt_link' => [
    'title' => 'PPPoE → OLT Auto Link',
    'url'   => '/cron/auto_link_pppoe_olt.php',
    'method'=> 'GET',
    'desc'  => 'Sync PPPoE active MACs, update clients.router_mac, link to OLT cache, fill OLT fields.',
    'timeout' => 180,
    'schedule' => '2-59/5 * * * *', // offset to run at 02,07,12... to avoid locking with OLT job
    'supports' => [],
  ],
  'auto_billing' => [
    'title' => 'Auto Billing',
    'url'   => '/cron/auto_billing.php',
    'method'=> 'GET',
    'desc'  => 'Generate periodic billing actions.',
    'timeout' => 180,
    'schedule' => '15 2 1 * *', // 1st of month, after OLT window
    'supports' => [],
  ],
  'auto_suspend' => [
    'title' => 'Auto Suspend (due clients)',
    'url'   => '/cron/auto_suspend.php',
    'method'=> 'GET',
    'desc'  => 'Suspend clients based on due rules.',
    'timeout' => 180,
    'schedule' => '7-59/15 * * * *', // offset away from OLT job start
    'supports' => [],
  ],
  'auto_suspend_enable' => [
    'title' => 'Auto Resume (enable)',
    'url'   => '/cron/auto_suspend_enable.php',
    'method'=> 'GET',
    'desc'  => 'Re-enable clients when dues cleared.',
    'timeout' => 180,
    'schedule' => '12-59/15 * * * *', // staggered away from suspend and OLT
    'supports' => [],
  ],
  'auto_expire_inactive' => [
    'title' => 'Expire Inactive Accounts',
    'url'   => '/cron/auto_expire_inactive.php',
    'method'=> 'GET',
    'desc'  => 'Mark inactive/expired accounts.',
    'timeout' => 180,
    'schedule' => '30 3 * * *',
    'supports' => [],
  ],
  'save_client_traffic' => [
    'title' => 'Save Client Traffic',
    'url'   => '/cron/save_client_traffic.php',
    'method'=> 'GET',
    'desc'  => 'Poll live status and log to client_traffic_log.',
    'timeout' => 180,
    'schedule' => '*/10 * * * *',
    'supports' => [],
  ],
  'sms_sender' => [
    'title' => 'SMS Sender',
    'url'   => '/cron/sms_sender.php',
    'method'=> 'GET',
    'desc'  => 'Send queued SMS messages.',
    'timeout' => 120,
    'schedule' => '*/5 * * * *',
    'supports' => [],
  ],
  'sms_due_reminder' => [
    'title' => 'SMS Due Reminder',
    'url'   => '/cron/sms_due_reminder.php',
    'method'=> 'GET',
    'desc'  => 'Send due reminder SMS.',
    'timeout' => 120,
    'schedule' => '0 9 * * *',
    'supports' => [],
  ],
  'enqueue_due_notifications' => [
    'title' => 'Enqueue Due Notifications',
    'url'   => '/cron/enqueue_due_notifications.php',
    'method'=> 'GET',
    'desc'  => 'Queue due notifications for later sending.',
    'timeout' => 120,
    'schedule' => '10 */2 * * *', // OLT overlap এড়াতে ঘণ্টা +10 মিনিটে চালাও
    'supports' => [],
  ],
  'notify_runner' => [
    'title' => 'Notification Runner',
    'url'   => '/cron/notify_runner.php',
    'method'=> 'GET',
    'desc'  => 'Process notification queue.',
    'timeout' => 120,
    'schedule' => '20,50 * * * *', // OLT :00/:30 থেকে সরে 20/50 মিনিটে
    'supports' => [],
  ],
  'sync_clients' => [
    'title' => 'Sync Clients (general)',
    'url'   => '/cron/sync_clients.php',
    'method'=> 'GET',
    'desc'  => 'Sync clients from external source.',
    'timeout' => 240,
    'supports' => [],
  ],
  'sync_packages' => [
    'title' => 'Sync Packages (default)',
    'url'   => '/cron/sync_packages.php',
    'method'=> 'GET',
    'desc'  => 'Sync package list.',
    'timeout' => 180,
    'schedule' => '3-59/10 * * * *', // OLT :00/:30 এড়াতে ৩ মিনিট অফসেট
    'supports' => [],
  ],
  'sync_packages_all' => [
    'title' => 'Sync Packages (all)',
    'url'   => '/cron/sync_packages_all.php',
    'method'=> 'GET',
    'desc'  => 'Sync all package profiles.',
    'timeout' => 240,
    'schedule' => '3-59/10 * * * *', // same offset slot
    'supports' => [],
  ],
  'auto_bkash_apply' => [
    'title' => 'Auto bKash Apply',
    'url'   => '/cron/auto_bkash_apply.php',
    'method'=> 'GET',
    'desc'  => 'Apply bKash payments automatically.',
    'timeout' => 180,
    'schedule' => '5,35 * * * *', // OLT :00/:30 এড়াতে ৫/৩৫ মিনিটে
    'supports' => [],
  ],
  'bkash_rtn_process' => [
    'title' => 'bKash Return Process',
    'url'   => '/cron/bkash_rtn_process.php',
    'method'=> 'GET',
    'desc'  => 'Process bKash return callbacks.',
    'timeout' => 120,
    'supports' => [],
  ],
  'db_backup' => [
    'title' => 'DB Backup',
    'url'   => '/cron/db_backup.php',
    'method'=> 'GET',
    'desc'  => 'Create database backup.',
    'timeout' => 300,
    'schedule' => '15 3 * * *', // রাত ৩:১৫ এ (বড় জব থেকে সরে)
    'supports' => [],
  ],
  'generate_invoices' => [
    'title' => 'Generate Invoices (legacy)',
    'url'   => '/cron/generate_invoices.php',
    'method'=> 'GET',
    'desc'  => 'Legacy invoice generation script.',
    'timeout' => 240,
    'supports' => [],
  ],
  'auto_link_pppoe_olt_legacy' => [
    'title' => 'Auto Link PPPoE to OLT (legacy)',
    'url'   => '/cron/auto_link_pppoe_olt.php',
    'method'=> 'GET',
    'desc'  => 'Backfill PPPoE to OLT linkage.',
    'timeout' => 180,
    'supports' => [],
  ],
  'olt_mac_refresh_telnet' => [
    'title' => 'OLT MAC Refresh (telnet)',
    'url'   => '/api/olt_mac_refresh_telnet.php?mode=full',
    'method'=> 'GET',
    'desc'  => 'Refresh OLT MAC cache via telnet (onu_inventory/onu_mac_map টেবিল প্রয়োজন)।',
    'timeout' => 600, // heavy job; cap at 10m to avoid UI hang
    'async' => true, // detach response early so browser doesn't hang
    'schedule' => '*/30 * * * *', // default every 30 minutes
    'supports' => ['olt_id','skip_pppoe'], // allow narrowing to a specific OLT / skipping PPPoE link phase
  ],
];

/* ---------- Helpers ---------- */
function local_url(string $path): string {
  if ($path === '' || $path[0] !== '/') $path = '/'.$path;
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  global $BASE_PREFIX;
  return $scheme.'://'.$host.$BASE_PREFIX.$path;
}

// (বাংলা) লগ আউটপুট ছোট করে দেখানোর হেলপার
function render_log_cell($text, int $limit = 160): string {
  $txt = trim((string)$text);
  if ($txt === '') return '<span class="text-muted">—</span>';
  $full = mb_substr($txt, 0, 2000);
  $short = mb_substr($txt, 0, $limit);
  $more = mb_strlen($txt) > $limit;
  $summary = h($short . ($more ? '…' : ''));
  if (!$more) {
    return '<pre class="small mb-0 log-clip">'.$summary.'</pre>';
  }
  return '<div class="text-muted small">'.$summary.'</div>'
    .'<details class="log-details mt-1"><summary class="text-primary small">পুরোটা দেখুন</summary>'
    .'<pre class="small mb-0 log-clip">'.h($full).'</pre>'
    .'</details>';
}

function render_history_rows(array $rows): string {
  ob_start();
  if (!$rows) {
    echo '<tr><td colspan="10" class="text-center text-muted">No runs recorded.</td></tr>';
  } else {
    foreach($rows as $r){ ?>
      <tr>
        <td>#<?=h((string)$r['id'])?></td>
        <td><code><?=h($r['job_key'])?></code></td>
        <td><?=h($r['title'])?></td>
        <td>
          <?php if ((int)($r['triggered_by'] ?? 0) === 0): ?>
            <span class="badge bg-info text-dark">Auto</span>
          <?php else: ?>
            <span class="badge bg-secondary">Manual</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['status']): ?>
            <span class="badge bg-<?= $r['status']==='success'?'success':'danger' ?>"><?=h($r['status'])?></span>
          <?php else: ?>
            <span class="badge bg-secondary">running</span>
          <?php endif; ?>
        </td>
        <td><?=h((string)$r['started_at'])?></td>
        <td><?=h((string)($r['finished_at'] ?? ''))?></td>
        <td class="text-end"><?=h($r['duration_ms'] !== null ? (string)$r['duration_ms'].' ms' : '')?></td>
        <td><?=render_log_cell($r['output'] ?? '', 140)?></td>
        <td><?=render_log_cell($r['error'] ?? '', 140)?></td>
      </tr>
    <?php }
  }
  return ob_get_clean();
}

function render_running_rows(array $rows, array $jobs): string {
  if (!$rows) {
    return '<tr><td colspan="6" class="text-muted text-center">No jobs are running right now.</td></tr>';
  }
  ob_start();
  $nowTs = time();
  foreach ($rows as $r) {
    $jobKey = $r['job_key'];
    $timeout = (int)($jobs[$jobKey]['timeout'] ?? 120);
    $started = $r['started_at'] ?? '';
    $elapsed = '';
    $remaining = '';
    if ($started) {
      $elapsedMs = (int)round(($nowTs - strtotime($started)) * 1000);
      $elapsed = $elapsedMs . ' ms';
      $remainingMs = max(0, ($timeout * 1000) - $elapsedMs);
      $remaining = $remainingMs . ' ms';
    }
    ?>
    <tr data-job="<?=h($jobKey)?>">
      <td>#<?=h((string)$r['id'])?></td>
      <td><code><?=h($jobKey)?></code></td>
      <td><?=h($r['title'])?></td>
      <td><?=h((string)$r['started_at'])?></td>
      <td class="text-end"><?=h((string)$timeout)?> s</td>
      <td class="text-end">
        <div class="small">Elapsed: <?=h($elapsed ?: '—')?></div>
        <div class="small text-muted">Remaining: <?=h($remaining ?: '—')?></div>
      </td>
    </tr>
    <?php
  }
  return ob_get_clean();
}

function render_recent_rows(array $rows): string {
  if (!$rows) {
    return '<tr><td colspan="7" class="text-muted text-center">No recent cron_runner jobs.</td></tr>';
  }
  ob_start();
  foreach ($rows as $r) { ?>
    <tr>
      <td>#<?=h((string)$r['id'])?></td>
      <td><code><?=h($r['job_key'])?></code></td>
      <td><?=h($r['title'])?></td>
      <td>
        <?php if ($r['status']): ?>
          <span class="badge bg-<?= $r['status']==='success'?'success':'danger' ?>"><?=h($r['status'])?></span>
        <?php else: ?>
          <span class="badge bg-secondary">running</span>
        <?php endif; ?>
      </td>
      <td><?=h((string)$r['started_at'])?></td>
      <td><?=h((string)($r['finished_at'] ?? ''))?></td>
      <td class="text-end"><?=h($r['duration_ms'] !== null ? (string)$r['duration_ms'].' ms' : '')?></td>
    </tr>
  <?php }
  return ob_get_clean();
}

/* (বাংলা) লোকাল HTTP কল — cURL প্রেফার্ড; নইলে file_get_contents */
function http_call(string $url, string $method = 'GET', array $post = [], int $timeout = 120): array {
  $full = local_url($url);
  $method = strtoupper($method);
  $cookie = '';
  global $CURRENT_SESSION_ID;
  if ($CURRENT_SESSION_ID !== '') {
    $cookie = 'PHPSESSID=' . $CURRENT_SESSION_ID;
  } elseif (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
    $cookie = 'PHPSESSID=' . session_id();
  }
  // If token is present, prefer token-only to avoid session lock coupling
  if ($cookie && (($_GET['token'] ?? '') || ($_POST['token'] ?? '') || getenv('CRON_TOKEN'))) {
    // drop session cookie when token available
    $cookie = '';
  }
  // টোকেন ফওয়ার্ড (CLI token → downstream)
  global $tokenForward;
  if ($tokenForward && !isset($post['token']) && $method === 'POST') {
    $post['token'] = $tokenForward;
  }
  if ($tokenForward && $method === 'GET') {
    $full .= (str_contains($full, '?') ? '&' : '?') . 'token=' . urlencode($tokenForward);
  }

  if (function_exists('curl_init')) {
    $ch = curl_init();
    if ($method === 'GET' && $post) {
      $full .= (str_contains($full, '?') ? '&' : '?').http_build_query($post);
    }
    curl_setopt_array($ch, [
      CURLOPT_URL => $full,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if ($cookie !== '') {
      curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  }
  $out = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => ($err==='' && $status>=200 && $status<300), 'status'=>$status, 'output'=>$out?:'', 'error'=>$err];
  }

  // fallback
  $opts = ['http' => ['method'=>$method, 'timeout'=>$timeout, 'ignore_errors'=>true]];
  $headers = [];
  if ($method === 'POST') {
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    $opts['http']['content'] = http_build_query($post);
  }
  if ($cookie !== '') {
    $headers[] = "Cookie: $cookie";
  }
  if ($headers) {
    $opts['http']['header'] = implode("\r\n", $headers)."\r\n";
  }
  $ctx = stream_context_create($opts);
  $out = @file_get_contents($full, false, $ctx);
  $status = 0;
  if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
    $status = (int)$m[1];
  }
  $ok = ($status>=200 && $status<300 && $out!==false);
  return ['ok'=>$ok, 'status'=>$status, 'output'=>$out!==false?$out:'', 'error'=>$ok?'':'HTTP error or timeout'];
}

/* ---------- Run handler ---------- */
$flash_success = '';
$flash_error   = '';
$isAjaxReq     = is_ajax();
$tokenForward  = $tokenParam ?: '';
if ($tokenForward === '' && defined('CRON_TOKEN')) {
  $tokenForward = CRON_TOKEN; // fallback to global cron token for authenticated UI runs
}
$schedules     = []; // পরে লোড হবে
$enabledMap    = []; // পরে লোড হবে
$ajaxRunResponded = false; // async run response already sent?

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_job'])) {
  $job_key = trim($_POST['run_job']);
  if (!isset($jobs[$job_key])) {
    $flash_error = 'Unknown job.';
  } else {
    $job = $jobs[$job_key];
    $title = $job['title'];
    $asyncRun = !empty($job['async']);
    $user_id = $CURRENT_USER_ID;

    // (বাংলা) ইনপুট: month সাপোর্ট করলে নিন
    $url = $job['url'];
    if (!empty($job['supports']) && in_array('month', $job['supports'], true)) {
      $month = trim($_POST['month'] ?? date('Y-m'));
      // validate YYYY-MM
      if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
        $flash_error = 'Invalid month format. Use YYYY-MM.';
        goto after_run; // graceful
      }
      $url = str_replace('{month}', $month, $url);
      $title .= " [$month]";
    } else {
      $url = str_replace('{month}', $current_month, $url); // যদি ভুলে থেকেও placeholder থাকে
    }
    // Optional: OLT filter
    if (!empty($job['supports']) && in_array('olt_id', $job['supports'], true)) {
      $oltId = trim($_POST['olt_id'] ?? '');
      if ($oltId !== '' && !preg_match('/^\d+$/', $oltId)) {
        $flash_error = 'Invalid OLT ID.';
        goto after_run;
      }
      if ($oltId !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'olt_id=' . urlencode($oltId);
        $title .= " [OLT {$oltId}]";
      }
    }
    if (!empty($job['supports']) && in_array('skip_pppoe', $job['supports'], true)) {
      $skipPppoe = isset($_POST['skip_pppoe']) && $_POST['skip_pppoe'] === '1';
      if ($skipPppoe) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'skip_pppoe=1';
        $title .= ' [skip_pppoe]';
      }
    }
    // টোকেন থাকলে URL এ যুক্ত করি (GET কলের জন্য)
    if ($tokenForward) {
      $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . urlencode($tokenForward);
    }

    // স্টার্ট লগ
    $st = dbh()->prepare("INSERT INTO cron_runs (job_key, title, started_at, triggered_by) VALUES (?, ?, NOW(), ?)");
    $st->execute([$job_key, $title, $user_id]);
    $run_id = (int)dbh()->lastInsertId();

    // Execute
    if ($asyncRun) {
      ignore_user_abort(true); // keep running even if client disconnects
      if ($isAjaxReq && !$ajaxRunResponded) {
        $ajaxRunResponded = true;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'ok' => true,
          'message' => "Job '{$title}' started (will update when done).",
        ], JSON_UNESCAPED_UNICODE);
        if (function_exists('fastcgi_finish_request')) {
          fastcgi_finish_request();
        } else {
          @ob_flush(); @flush();
        }
      }
    }
    close_session_if_open();
    $started = microtime(true);
    @set_time_limit(max(60, (int)($job['timeout'] ?? 120)));
    try {
      $res = http_call($url, $job['method'] ?? 'GET', [], (int)($job['timeout'] ?? 120));
      $duration = (int)round((microtime(true)-$started)*1000);
      $status = $res['ok'] ? 'success' : 'failed';

      // Output trim (1MB)
      $output = (string)($res['output'] ?? '');
      if (strlen($output) > 1024*1024) {
        $output = substr($output, 0, 1024*1024)."\n...[truncated]...";
      }

      $upd = dbh()->prepare("
        UPDATE cron_runs 
        SET status=?, finished_at=NOW(), duration_ms=?, output=?, error=?
        WHERE id=?");
      $upd->execute([$status, $duration, $output, (string)($res['error'] ?? ''), $run_id]);

      $flash_success = $status==='success'
        ? "Job '{$title}' finished successfully."
        : "Job '{$title}' failed (HTTP ".$res['status'].").";
    } catch (Throwable $e) {
      $duration = (int)round((microtime(true)-$started)*1000);
      $upd = dbh()->prepare("
        UPDATE cron_runs 
        SET status='failed', finished_at=NOW(), duration_ms=?, error=?
        WHERE id=?");
      $upd->execute([$duration, $e->getMessage(), $run_id]);
      $flash_error = "Job '{$title}' failed: ".$e->getMessage();
    }
  }
}
after_run:

// Save schedule (AJAX-friendly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
  $job_key = trim($_POST['save_schedule']);
  $sched = trim($_POST['schedule'] ?? '');
  if (!isset($jobs[$job_key])) {
    $flash_error = 'Unknown job for schedule.';
  } elseif ($sched === '') {
    $flash_error = 'Schedule cannot be empty.';
  } else {
    $schedules = load_schedules($CRON_SCHEDULE_FILE, $jobs);
    $schedules[$job_key] = $sched;
    if (save_schedules($CRON_SCHEDULE_FILE, $schedules)) {
      $flash_success = "Schedule saved for '{$jobs[$job_key]['title']}'.";
    } else {
      $flash_error = 'Failed to save schedule file.';
    }
  }
  if ($isAjaxReq) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => $flash_error === '',
      'message' => $flash_success ?: ($flash_error ?: 'Failed'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// Save enable/disable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_enable'])) {
  $job_key = trim($_POST['save_enable']);
  $enable  = ($_POST['enabled'] ?? '1') === '1';
  if (!isset($jobs[$job_key])) {
    $flash_error = 'Unknown job for enable toggle.';
  } else {
    $enabledMap = load_enabled($CRON_ENABLE_FILE, $jobs);
    $enabledMap[$job_key] = $enable;
    if (save_enabled($CRON_ENABLE_FILE, $enabledMap)) {
      $flash_success = "Job '{$jobs[$job_key]['title']}' ".($enable?'enabled':'disabled').'.';
    } else {
      $flash_error = 'Failed to save enable file.';
    }
  }
  if ($isAjaxReq) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => $flash_error === '',
      'message' => $flash_success ?: ($flash_error ?: 'Failed'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// Ajax POST response (job run)
if ($isAjaxReq && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_job']) && !$ajaxRunResponded) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => $flash_error === '',
    'message' => $flash_success ?: ($flash_error ?: 'Failed'),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---------- Filters & pagination for history ---------- */
$f_job   = trim($_GET['job'] ?? '');
$f_state = trim($_GET['state'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page-1)*$limit;

$where = [];
$params = [];
if ($f_job !== '' && isset($jobs[$f_job])) { $where[]="job_key=?"; $params[]=$f_job; }
if (in_array($f_state, ['success','failed'], true)) { $where[]="status=?"; $params[]=$f_state; }
$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Count & fetch
$stc = dbh()->prepare("SELECT COUNT(*) FROM cron_runs $where_sql");
$stc->execute($params);
$total_rows = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows/$limit));

$st = dbh()->prepare("SELECT * FROM cron_runs $where_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Currently running (unfinished) — only cron_runner triggered (triggered_by=0)
$running = dbh()->query("SELECT * FROM cron_runs WHERE finished_at IS NULL AND IFNULL(triggered_by,0)=0 ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
// Recent runs by cron_runner
$recentCron = dbh()->query("SELECT * FROM cron_runs WHERE IFNULL(triggered_by,0)=0 ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Last run per job
$last = [];
$stl = dbh()->query("
  SELECT r.*
  FROM (SELECT job_key, MAX(id) mx FROM cron_runs GROUP BY job_key) x
  JOIN cron_runs r ON r.id = x.mx
  ORDER BY r.id DESC
");
foreach ($stl->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $last[$r['job_key']] = $r;
}
$schedules = $schedules ?: load_schedules($CRON_SCHEDULE_FILE, $jobs);
$enabledMap = $enabledMap ?: load_enabled($CRON_ENABLE_FILE, $jobs);

// Ajax history partial
if ($isAjaxReq && ($_GET['ajax'] ?? '') === 'history') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'history_body' => render_history_rows($rows),
    'total_rows' => $total_rows,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// Ajax: last run for a single job
if ($isAjaxReq && ($_GET['ajax'] ?? '') === 'last' && !empty($_GET['job'])) {
  $jobKey = trim($_GET['job']);
  $st = dbh()->prepare("SELECT * FROM cron_runs WHERE job_key=? ORDER BY id DESC LIMIT 1");
  $st->execute([$jobKey]);
  $lr = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true,'last'=>$lr], JSON_UNESCAPED_UNICODE);
  exit;
}

// Ajax: running list (unfinished rows)
if ($isAjaxReq && ($_GET['ajax'] ?? '') === 'running') {
  $running = dbh()->query("SELECT * FROM cron_runs WHERE finished_at IS NULL AND IFNULL(triggered_by,0)=0 ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
  $recentCron = dbh()->query("SELECT * FROM cron_runs WHERE IFNULL(triggered_by,0)=0 ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'running_body' => render_running_rows($running, $jobs),
    'recent_body' => render_recent_rows($recentCron),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
?>
<?php require_once __DIR__ . '/../partials/partials_header.php'; ?>
<style>
  .log-clip {max-height:140px; overflow:auto; background:#f8f9fa; border:1px solid #e9ecef; padding:8px;}
  .table-condensed td, .table-condensed th {padding: .5rem .6rem;}
  .table-code code {white-space: nowrap;}
  .jobs-table { min-width: 1300px; table-layout: auto; }
  .jobs-table th.schedule-col, .jobs-table td.schedule-col {min-width: 190px;}
  .jobs-table th.last-col, .jobs-table td.last-col {min-width: 220px;}
  .jobs-table th.action-col, .jobs-table td.action-col {min-width: 240px; white-space: nowrap; }
  .jobs-table thead th{
    background:#1e3f5e;
    color:#fff;
    border-color:#1a344f;
    text-transform:uppercase;
    letter-spacing:.02em;
    font-size:.82rem;
  }
  .jobs-table tbody tr:nth-of-type(even){background:#f8fbff;}
  .jobs-table tbody tr:hover{background:#eef7ff;}
  .jobs-table tbody td{vertical-align:middle; padding:14px 12px; white-space: normal; word-break: break-word;}
  .jobs-table .job-title{font-weight:700;color:#1f2b3a;}
  .jobs-table .job-key{font-size:.8rem;}
  .jobs-table .endpoint-col code{white-space:normal; word-break:break-word; display:block;}
  .jobs-table .endpoint-col .text-muted{display:block; margin-top:2px;}
  .jobs-table .action-col{position:sticky; right:0; background:#fff;}
  .jobs-table .action-col .btn{box-shadow:none;}
  .jobs-table .schedule-val code{font-size:.92rem;}
  .jobs-table .last-meta{display:flex; flex-direction:column; gap:2px; align-items:flex-start;}
  .jobs-table .last-meta .badge{font-size:.75rem; text-transform:uppercase;}
  .jobs-table .last-meta .small{line-height:1.25; word-break:break-word;}
  .schedule-form input.form-control{min-width:110px;}
  /* Toast-style flash: top-right, never pushing page header */
  #flash-dock{
    position:fixed;
    right:16px;
    top:16px;
    width:min(380px, calc(100% - 32px));
    z-index:1080;
    pointer-events:none;
  }
  #flash-dock .alert{
    pointer-events:auto;
    box-shadow:0 0.5rem 1.5rem rgba(0,0,0,0.25);
    margin-top:8px;
  }
  /* Responsive cards for jobs table on small screens */
  @media (max-width: 991px){
    .jobs-table{min-width:100%; width:100%;}
    .jobs-table thead{display:none;}
    .jobs-table tbody tr{
      display:block;
      margin-bottom:12px;
      border:1px solid #e9ecef;
      border-radius:10px;
      box-shadow:0 6px 20px rgba(0,0,0,0.04);
      overflow:hidden;
    }
    .jobs-table tbody td{
      display:flex;
      gap:10px;
      align-items:flex-start;
      justify-content:space-between;
      padding:10px 12px;
      border-bottom:1px solid #f0f2f5;
    }
    .jobs-table tbody td:last-child{border-bottom:none;}
    .jobs-table tbody td::before{
      content:attr(data-label);
      font-weight:600;
      color:#4b5563;
      flex:0 0 110px;
      text-transform:uppercase;
      font-size:.78rem;
      letter-spacing:.01em;
    }
    .jobs-table .action-col{position:static; text-align:left;}
    .jobs-table .action-col .d-flex{width:100%; flex-direction:column; align-items:flex-start;}
  }
</style>
<div class="container-fluid my-4">
  <div class="d-flex align-items-center justify-content-start">
    <h3 class="mb-0">Cron Dashboard</h3>
  </div>

  <div id="flash-dock"></div>

  <!-- Jobs -->
  <div class="card shadow-sm mt-3">
    <div class="card-header">Available Jobs</div>
    <div class="card-body">
      <div class="table-responsive" style="overflow-x:auto;">
        <table class="table table-sm align-middle table-condensed table-code jobs-table">
          <thead class="table-light">
            <tr>
              <th>Job</th>
              <th>Description</th>
              <th>Endpoint</th>
              <th class="schedule-col">Schedule</th>
              <th class="last-col">Last Run</th>
              <th class="text-end action-col">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($jobs as $key => $j): 
            $lr = $last[$key] ?? null;
            $url_preview = $j['url'];
            if (!empty($j['supports']) && in_array('month', $j['supports'], true)) {
              $url_preview = str_replace('{month}', $current_month, $url_preview);
            }
            $scheduleStr = $schedules[$key] ?? ($j['schedule'] ?? '*/5 * * * *');
            $defaultSchedule = $j['schedule'] ?? '*/5 * * * *';
            $lrStatus = strtolower((string)($lr['status'] ?? ''));
            $lrBadge = $lrStatus==='success' ? 'bg-success' : ($lrStatus==='failed' ? 'bg-danger' : 'bg-secondary');
            $lrLabel = $lrStatus !== '' ? $lrStatus : ($lr ? 'running' : '');
            $isEnabled = $enabledMap[$key] ?? true;
          ?>
            <tr>
              <td class="job-col" data-label="Job">
                <div class="job-title"><?=h($j['title'])?></div>
                <div class="job-key text-muted"><?=h($key)?></div>
              </td>
              <td data-label="Description"><?=h($j['desc'])?></td>
              <td data-label="Endpoint">
                <code><?=h($j['method'] ?? 'GET')?> <?=h($url_preview)?></code>
                <div class="text-muted small">Timeout: <?=h((string)($j['timeout'] ?? 120))?>s</div>
              </td>
              <td class="schedule-col" data-label="Schedule">
                <div class="schedule-val" data-schedule-display="<?=h($key)?>">
                  <code><?=h($scheduleStr)?></code>
                </div>
                <div class="text-muted small">Default: <?=h($defaultSchedule)?></div>
              </td>
              <td class="last-col" data-label="Last Run">
                <div class="last-meta" data-last-display="<?=h($key)?>">
                  <?php if ($lr): ?>
                    <div><span class="badge <?=$lrBadge?>"><?=h($lrLabel ?: 'running')?></span></div>
                    <div class="small text-muted">Start: <?=h($lr['started_at'] ?? '—')?></div>
                    <div class="small text-muted">End: <?=h($lr['finished_at'] ?? '—')?></div>
                    <div class="small text-muted">Duration: <?=h($lr['duration_ms'] !== null ? (string)$lr['duration_ms'].' ms' : '—')?></div>
                  <?php else: ?>
                    <span class="text-muted small">No runs yet</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-end action-col" data-label="Action">
                <div class="d-flex flex-column align-items-end gap-2">
                  <form class="enable-form d-flex align-items-center gap-2" method="post">
                    <input type="hidden" name="save_enable" value="<?=h($key)?>">
                    <div class="form-check form-switch m-0">
                      <input class="form-check-input enable-toggle" type="checkbox" role="switch" name="enabled" value="1" <?= $isEnabled ? 'checked' : '' ?> data-job="<?=h($key)?>">
                      <label class="form-check-label small">Auto</label>
                    </div>
                  </form>
                  

                  <button type="button"
                          class="btn btn-outline-secondary btn-sm view-last-btn"
                          data-bs-toggle="modal"
                          data-bs-target="#lastRunModal"
                          data-job="<?=h($key)?>"
                          data-title="<?=h($j['title'])?>"
                          data-status="<?=h($lr['status'] ?? '')?>"
                          data-start="<?=h($lr['started_at'] ?? '')?>"
                          data-end="<?=h($lr['finished_at'] ?? '')?>"
                          data-duration="<?=h($lr['duration_ms'] ?? '')?>"
                          data-output="<?=h(mb_substr((string)($lr['output'] ?? ''),0,1000))?>"
                          data-error="<?=h(mb_substr((string)($lr['error'] ?? ''),0,1000))?>"
                          data-supports-month="<?=!empty($j['supports']) && in_array('month',$j['supports'],true) ? '1' : '0'?>"
                          data-supports-olt="<?=!empty($j['supports']) && in_array('olt_id',$j['supports'],true) ? '1' : '0'?>"
                          data-supports-skip_pppoe="<?=!empty($j['supports']) && in_array('skip_pppoe',$j['supports'],true) ? '1' : '0'?>"
                          data-schedule="<?=h($scheduleStr)?>">
                    <i class="bi bi-clock-history"></i> Schedule
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- History filters -->
  <form class="row g-2 mt-4 mb-2">
    <div class="col-md-3">
      <label class="form-label">Job</label>
      <select name="job" class="form-select">
        <option value="">All</option>
        <?php foreach($jobs as $k=>$j): ?>
          <option value="<?=h($k)?>" <?= $f_job===$k?'selected':'' ?>><?=h($j['title'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="state" class="form-select">
        <option value="">All</option>
        <option value="success" <?= $f_state==='success'?'selected':'' ?>>Success</option>
        <option value="failed"  <?= $f_state==='failed'?'selected':'' ?>>Failed</option>
      </select>
    </div>
    <div class="col-md-6 d-flex align-items-end gap-2">
      <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
      <a class="btn btn-outline-dark" href="/public/cron_dashboard.php"><i class="bi bi-x-circle"></i> Reset</a>
    </div>
  </form>

  <!-- History table -->
  <div class="card shadow-sm">
    <div class="card-header">Last Runs</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle table-condensed">
          <thead class="table-light">
            <tr>
              <th style="width:90px;">ID</th>
              <th>Job</th>
              <th>Title</th>
              <th>Source</th>
              <th>Status</th>
              <th>Started</th>
              <th>Finished</th>
              <th class="text-end">Duration</th>
              <th>Output (first lines)</th>
              <th>Error</th>
            </tr>
          </thead>
          <tbody id="history-body">
            <?=render_history_rows($rows)?>
          </tbody>
        </table>
      </div>

      <!-- Pagination (max 5 pages) -->
      <?php
        $win=5; $start=max(1, $page-intdiv($win-1,2)); $end=min($total_pages, $start+$win-1);
        if ($end-$start+1<$win) $start=max(1, $end-$win+1);
      ?>
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
          <?php
            $q=$_GET; $q['page']=1; $first='?'.h(http_build_query($q));
            $q=$_GET; $q['page']=max(1,$page-1); $prev='?'.h(http_build_query($q));
          ?>
          <li class="page-item <?=($page<=1?'disabled':'')?>"><a class="page-link" href="<?=$first?>">&laquo;</a></li>
          <li class="page-item <?=($page<=1?'disabled':'')?>"><a class="page-link" href="<?=$prev?>">Prev</a></li>
          <?php for($i=$start;$i<=$end;$i++): $q=$_GET; $q['page']=$i; $u='?'.h(http_build_query($q)); ?>
            <li class="page-item <?=($i==$page?'active':'')?>"><a class="page-link" href="<?=$u?>"><?=$i?></a></li>
          <?php endfor;
            $q=$_GET; $q['page']=min($total_pages,$page+1); $next='?'.h(http_build_query($q));
            $q=$_GET; $q['page']=$total_pages; $last='?'.h(http_build_query($q));
          ?>
          <li class="page-item <?=($page>=$total_pages?'disabled':'')?>"><a class="page-link" href="<?=$next?>">Next</a></li>
          <li class="page-item <?=($page>=$total_pages?'disabled':'')?>"><a class="page-link" href="<?=$last?>">&raquo;</a></li>
        </ul>
      </nav>
    </div>
  </div>
</div>
<!-- Last Run Modal -->
<div class="modal fade" id="lastRunModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-clock-history"></i> Job-Schedule</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong id="lr-title">—</strong> <span class="text-muted small" id="lr-job"></span></div>
        <div class="mb-2">Status: <span id="lr-status" class="badge bg-secondary">—</span></div>
        <div class="mb-2">Start: <span id="lr-start">—</span></div>
        <div class="mb-2">End: <span id="lr-end">—</span></div>
        <div class="mb-2">Duration: <span id="lr-duration">—</span></div>
        <div class="mb-3">
          <label class="form-label mb-1">Job Schedule (cron expression)</label>
          <form id="lr-schedule-form" class="d-flex gap-2">
            <input type="hidden" name="save_schedule" id="lr-save-key" value="">
            <input type="text" name="schedule" id="lr-schedule-input" class="form-control form-control-sm" style="min-width:160px">
            <button class="btn btn-outline-secondary btn-sm" type="submit">
              <span class="spinner-border spinner-border-sm d-none btn-spinner" role="status" aria-hidden="true"></span>
              <i class="bi bi-save"></i> Save
            </button>
          </form>
          <div class="small text-muted mt-1">উদাহরণ: */5 * * * * (প্রতি ৫ মিনিট)</div>
          <div class="bg-light border rounded p-2 mt-2">
            <div class="small fw-semibold mb-1">Quick builder (মিনিট-লেভেল)</div>
            <div class="row g-2">
              <div class="col-6 col-sm-4">
                <label class="form-label form-label-sm mb-0 small">Minute</label>
                <select class="form-select form-select-sm" id="sb-minute">
                  <option value="*">Every minute</option>
                  <option value="*/5">Every 5 min</option>
                  <option value="*/10">Every 10 min</option>
                  <option value="0">0</option>
                  <option value="15">15</option>
                  <option value="30">30</option>
                  <option value="45">45</option>
                </select>
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label form-label-sm mb-0 small">Hour</label>
                <select class="form-select form-select-sm" id="sb-hour">
                  <option value="*">Every hour</option>
                  <option value="*/2">Every 2 hours</option>
                  <option value="*/4">Every 4 hours</option>
                  <option value="*/6">Every 6 hours</option>
                  <option value="*/12">Every 12 hours</option>
                  <option value="0">0</option>
                  <option value="6">6</option>
                  <option value="12">12</option>
                  <option value="18">18</option>
                </select>
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label form-label-sm mb-0 small">Day of month</label>
                <select class="form-select form-select-sm" id="sb-dom">
                  <option value="*">Every day</option>
                  <option value="1">1</option>
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="15">15</option>
                  <option value="20">20</option>
                  <option value="25">25</option>
                  <option value="28">28</option>
                </select>
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label form-label-sm mb-0 small">Month</label>
                <select class="form-select form-select-sm" id="sb-month">
                  <option value="*">Every month</option>
                  <option value="1">Jan</option>
                  <option value="3">Mar</option>
                  <option value="6">Jun</option>
                  <option value="9">Sep</option>
                  <option value="12">Dec</option>
                </select>
              </div>
              <div class="col-6 col-sm-4">
                <label class="form-label form-label-sm mb-0 small">Day of week</label>
                <select class="form-select form-select-sm" id="sb-dow">
                  <option value="*">Every day</option>
                  <option value="1">Mon</option>
                  <option value="2">Tue</option>
                  <option value="3">Wed</option>
                  <option value="4">Thu</option>
                  <option value="5">Fri</option>
                  <option value="6">Sat</option>
                  <option value="0">Sun</option>
                </select>
              </div>
            </div>
            <div class="small text-muted mt-1">নোট: ক্রন সেকেন্ড সাপোর্ট করে না; প্রতি মিনিটে রান হয়।</div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label mb-1">Run this job</label>
          <form id="lr-run-form" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="run_job" id="lr-run-job" value="">
            <div id="lr-month-wrap" class="d-flex align-items-center gap-1 d-none">
              <span class="small">Month</span>
              <input type="month" name="month" id="lr-month-input" class="form-control form-control-sm">
            </div>
            <div id="lr-olt-wrap" class="d-flex align-items-center gap-1 d-none">
              <span class="small">OLT ID</span>
              <input type="number" min="1" step="1" name="olt_id" id="lr-olt-input" class="form-control form-control-sm" style="width:100px;">
            </div>
            <div id="lr-skip-pppoe-wrap" class="form-check d-none">
              <input class="form-check-input" type="checkbox" value="1" id="lr-skip-pppoe" name="skip_pppoe">
              <label class="form-check-label small" for="lr-skip-pppoe">Skip PPPoE link</label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit">
              <span class="spinner-border spinner-border-sm d-none btn-spinner" role="status" aria-hidden="true"></span>
              <i class="bi bi-play-fill"></i> Run now
            </button>
          </form>
        </div>
        <div class="mb-2">Output:</div>
        <pre class="small border rounded p-2" id="lr-output" style="max-height:180px;overflow:auto;">—</pre>
        <div class="mb-2">Error:</div>
        <pre class="small border rounded p-2 text-danger" id="lr-error" style="max-height:120px;overflow:auto;">—</pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const flashDock = document.getElementById('flash-dock');
  const lrModal = document.getElementById('lastRunModal');
  const lrSchedForm = document.getElementById('lr-schedule-form');
  const lrRunForm = document.getElementById('lr-run-form');
  const lrSchedInput = document.getElementById('lr-schedule-input');
  const lrRunJobInput = document.getElementById('lr-run-job');
  const lrSaveKeyInput = document.getElementById('lr-save-key');
  const lrMonthWrap = document.getElementById('lr-month-wrap');
  const lrMonthInput = document.getElementById('lr-month-input');
  const lrOltWrap = document.getElementById('lr-olt-wrap');
  const lrOltInput = document.getElementById('lr-olt-input');
  const lrSkipPppoeWrap = document.getElementById('lr-skip-pppoe-wrap');
  const lrSkipPppoeInput = document.getElementById('lr-skip-pppoe');
  const sbMinute = document.getElementById('sb-minute');
  const sbHour = document.getElementById('sb-hour');
  const sbDom = document.getElementById('sb-dom');
  const sbMonth = document.getElementById('sb-month');
  const sbDow = document.getElementById('sb-dow');
  let activeBtn = null;

  function showFlash(type, text){
    const dock = flashDock || document.createElement('div');
    if (!dock.id) dock.id = 'flash-dock';
    if (!dock.parentElement) document.body.appendChild(dock);
    const div = document.createElement('div');
    div.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' mb-0';
    div.textContent = text || (type === 'success' ? 'Done' : 'Failed');
    dock.appendChild(div);
    setTimeout(() => { div.remove(); }, 4000);
  }

  async function refreshHistory(){
    const params = new URLSearchParams(window.location.search);
    params.set('ajax','history');
    try{
      const res = await fetch(window.location.pathname + '?' + params.toString(), {
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      const j = await res.json();
      if (j && j.ok && j.history_body){
        const body = document.getElementById('history-body');
        if (body) body.innerHTML = j.history_body;
      }
    }catch(e){}
  }

  function updateScheduleDisplay(jobKey, schedule){
    const cell = document.querySelector(`[data-schedule-display="${jobKey}"] code`);
    if (cell) cell.textContent = schedule;
    const btn = activeBtn || document.querySelector(`.view-last-btn[data-job="${jobKey}"]`);
    btn?.setAttribute('data-schedule', schedule);
  }

  function updateLastSummary(jobKey, lastRun){
    const holder = document.querySelector(`[data-last-display="${jobKey}"]`);
    if (!holder) return;
    holder.innerHTML = '';
    if (!lastRun){
      const span = document.createElement('span');
      span.className = 'text-muted small';
      span.textContent = 'No runs yet';
      holder.appendChild(span);
      return;
    }
    const status = (lastRun.status || '').toLowerCase();
    const badge = document.createElement('span');
    badge.className = 'badge ' + (status==='success'?'bg-success':(status==='failed'?'bg-danger':'bg-secondary'));
    badge.textContent = status || 'running';
    const badgeWrap = document.createElement('div');
    badgeWrap.appendChild(badge);
    holder.appendChild(badgeWrap);
    const meta = [
      ['Start', lastRun.started_at || '—'],
      ['End', lastRun.finished_at || '—'],
      ['Duration', lastRun.duration_ms ? (lastRun.duration_ms + ' ms') : '—'],
    ];
    meta.forEach(([label,val])=>{
      const div=document.createElement('div');
      div.className='small text-muted';
      div.textContent = `${label}: ${val}`;
      holder.appendChild(div);
    });
  }

  function fillModalFrom(btn){
    if (!btn || !lrModal) return;
    const get = (k) => btn.getAttribute('data-' + k) || '';
    const job = get('job');
    activeBtn = btn;

    const titleEl = lrModal.querySelector('#lr-title');
    const jobEl = lrModal.querySelector('#lr-job');
    if (titleEl) titleEl.textContent = get('title') || '—';
    if (jobEl) jobEl.textContent = job ? '(' + job + ')' : '';

    if (lrRunJobInput) lrRunJobInput.value = job;
    if (lrSaveKeyInput) lrSaveKeyInput.value = job;
    if (lrSchedInput) lrSchedInput.value = get('schedule') || '*/5 * * * *';

    if (lrMonthWrap) {
      const supportsMonth = get('supports-month') === '1';
      lrMonthWrap.classList.toggle('d-none', !supportsMonth);
      if (supportsMonth && lrMonthInput && !lrMonthInput.value) {
        lrMonthInput.value = new Date().toISOString().slice(0,7);
      }
    }
    if (lrOltWrap) {
      const supportsOlt = get('supports-olt') === '1';
      lrOltWrap.classList.toggle('d-none', !supportsOlt);
      if (!supportsOlt && lrOltInput) lrOltInput.value = '';
    }
    if (lrSkipPppoeWrap) {
      const supportsSkip = get('supports-skip_pppoe') === '1';
      lrSkipPppoeWrap.classList.toggle('d-none', !supportsSkip);
      if (!supportsSkip && lrSkipPppoeInput) lrSkipPppoeInput.checked = false;
    }

    updateModalFromLast({
      status: get('status') || '',
      started_at: get('start') || '—',
      finished_at: get('end') || '—',
      duration_ms: get('duration') || '',
      output: get('output') || '—',
      error: get('error') || '—',
    });
  }

  function updateModalFromLast(lastRun){
    if (!lrModal) return;
    const badge = lrModal.querySelector('#lr-status');
    const startEl = lrModal.querySelector('#lr-start');
    const endEl = lrModal.querySelector('#lr-end');
    const durationEl = lrModal.querySelector('#lr-duration');
    const outputEl = lrModal.querySelector('#lr-output');
    const errorEl = lrModal.querySelector('#lr-error');
    if (!lastRun){
      badge && (badge.textContent = '—', badge.className='badge bg-secondary');
      startEl && (startEl.textContent = '—');
      endEl && (endEl.textContent = '—');
      durationEl && (durationEl.textContent = '—');
      outputEl && (outputEl.textContent = 'No runs yet');
      errorEl && (errorEl.textContent = '—');
      return;
    }
    const status = (lastRun.status || '').toLowerCase();
    if (badge){
      badge.textContent = status || '—';
      badge.className = 'badge ' + (status==='success'?'bg-success':(status==='failed'?'bg-danger':'bg-secondary'));
    }
    startEl && (startEl.textContent = lastRun.started_at || '—');
    endEl && (endEl.textContent = lastRun.finished_at || '—');
    durationEl && (durationEl.textContent = lastRun.duration_ms ? (lastRun.duration_ms + ' ms') : '—');
    outputEl && (outputEl.textContent = (lastRun.output || '').substring(0,1000) || '—');
    errorEl && (errorEl.textContent = (lastRun.error || '').substring(0,1000) || '—');
  }

  async function fetchLastRun(jobKey){
    if (!jobKey) return;
    try{
      const res = await fetch(window.location.pathname + '?ajax=last&job=' + encodeURIComponent(jobKey), {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      });
      const j = await res.json();
      if (j && j.ok){
        const lr = j.last || null;
        updateModalFromLast(lr);
        updateLastSummary(jobKey, lr);
        const btn = activeBtn && activeBtn.getAttribute('data-job') === jobKey ? activeBtn : document.querySelector(`.view-last-btn[data-job="${jobKey}"]`);
        if (btn && lr){
          btn.setAttribute('data-status', lr.status || '');
          btn.setAttribute('data-start', lr.started_at || '');
          btn.setAttribute('data-end', lr.finished_at || '');
          btn.setAttribute('data-duration', lr.duration_ms || '');
          btn.setAttribute('data-output', (lr.output || '').substring(0,1000));
          btn.setAttribute('data-error', (lr.error || '').substring(0,1000));
        }
      }
    }catch(e){}
  }

  // Live running list
  document.querySelectorAll('.job-run-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
      const spin = btn?.querySelector('.btn-spinner');
      if (btn) btn.disabled = true;
      if (spin) spin.classList.remove('d-none');
      const fd = new FormData(form);
      fd.append('ajax','1');
      try{
        const res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        const text = await res.text();
        let j = null;
        try { j = JSON.parse(text); } catch(_) {}
        if (j && j.ok){
          showFlash('success', j.message || 'Job finished');
          refreshHistory();
        } else {
          showFlash('danger', (j && j.message) ? j.message : 'Job failed');
        }
      }catch(err){
        showFlash('danger', 'Request failed');
      }finally{
        if (btn) btn.disabled = false;
        if (spin) spin.classList.add('d-none');
      }
    });
  });

  document.querySelectorAll('.schedule-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
      const spin = btn?.querySelector('.btn-spinner');
      if (btn) btn.disabled = true;
      if (spin) spin.classList.remove('d-none');
      const fd = new FormData(form);
      fd.append('ajax','1');
      try{
        const res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        const text = await res.text();
        let j = null;
        try { j = JSON.parse(text); } catch(_) {}
        if (j && j.ok){
          showFlash('success', j.message || 'Schedule saved');
        } else {
          showFlash('danger', (j && j.message) ? j.message : 'Schedule save failed');
        }
      }catch(err){
        showFlash('danger', 'Request failed');
      }finally{
        if (btn) btn.disabled = false;
        if (spin) spin.classList.add('d-none');
      }
    });
  });

  // Enable toggle forms
  document.querySelectorAll('.enable-form').forEach(form => {
    const toggle = form.querySelector('.enable-toggle');
    if (!toggle) return;
    toggle.addEventListener('change', async () => {
      const fd = new FormData(form);
      if (toggle.checked) fd.set('enabled','1'); else fd.set('enabled','0');
      fd.append('ajax','1');
      try{
        const res = await fetch(window.location.href, {
          method: 'POST',
          body: fd,
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        const text = await res.text();
        let j = null;
        try { j = JSON.parse(text); } catch(_) {}
        if (j && j.ok){
          showFlash('success', j.message || 'Saved');
        } else {
          showFlash('danger', (j && j.message) ? j.message : 'Save failed');
          toggle.checked = !toggle.checked;
        }
      }catch(err){
        showFlash('danger', 'Request failed');
        toggle.checked = !toggle.checked;
      }
    });
  });

  if (lrModal && lrModal.parentElement !== document.body) {
    document.body.appendChild(lrModal);
  }
  lrModal?.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget;
    fillModalFrom(btn);
    const job = btn?.getAttribute('data-job') || '';
    if (job) fetchLastRun(job);
  });

  lrSchedForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = lrSchedForm.querySelector('button[type="submit"]') || lrSchedForm.querySelector('button');
    const spin = btn?.querySelector('.btn-spinner');
    if (btn) btn.disabled = true;
    if (spin) spin.classList.remove('d-none');
    const fd = new FormData(lrSchedForm);
    fd.append('ajax','1');
    const jobKey = (lrSaveKeyInput?.value || '').trim();
    try{
      const res = await fetch(window.location.href, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      const text = await res.text();
      let j = null;
      try { j = JSON.parse(text); } catch(_) {}
      if (j && j.ok){
        showFlash('success', j.message || 'Schedule saved');
        if (jobKey && lrSchedInput) updateScheduleDisplay(jobKey, lrSchedInput.value || '*/5 * * * *');
      } else {
        showFlash('danger', (j && j.message) ? j.message : 'Schedule save failed');
      }
    }catch(err){
      showFlash('danger', 'Request failed');
    }finally{
      if (btn) btn.disabled = false;
      if (spin) spin.classList.add('d-none');
    }
  });

  lrRunForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = lrRunForm.querySelector('button[type="submit"]') || lrRunForm.querySelector('button');
    const spin = btn?.querySelector('.btn-spinner');
    if (btn) btn.disabled = true;
    if (spin) spin.classList.remove('d-none');
    const fd = new FormData(lrRunForm);
    fd.append('ajax','1');
    const jobKey = (lrRunJobInput?.value || '').trim();
    try{
      const res = await fetch(window.location.href, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });
      const text = await res.text();
      let j = null;
      try { j = JSON.parse(text); } catch(_) {}
      if (j && j.ok){
        showFlash('success', j.message || 'Job finished');
        refreshHistory();
        if (jobKey) fetchLastRun(jobKey);
      } else {
        showFlash('danger', (j && j.message) ? j.message : 'Job failed');
      }
    }catch(err){
      showFlash('danger', 'Request failed');
    }finally{
      if (btn) btn.disabled = false;
      if (spin) spin.classList.add('d-none');
    }
  });

  // Set default month for month-supported jobs on first open
  if (lrMonthInput && !lrMonthInput.value) {
    lrMonthInput.value = new Date().toISOString().slice(0,7);
  }
  if (lrOltInput && !lrOltInput.value) {
    lrOltInput.value = '';
  }
  if (lrSkipPppoeInput) {
    lrSkipPppoeInput.checked = false;
  }

  function applyBuilderToSchedule(){
    if (!lrSchedInput) return;
    const expr = [
      (sbMinute && sbMinute.value) ? sbMinute.value : '*',
      (sbHour && sbHour.value) ? sbHour.value : '*',
      (sbDom && sbDom.value) ? sbDom.value : '*',
      (sbMonth && sbMonth.value) ? sbMonth.value : '*',
      (sbDow && sbDow.value) ? sbDow.value : '*',
    ].join(' ');
    lrSchedInput.value = expr;
  }
  [sbMinute, sbHour, sbDom, sbMonth, sbDow].forEach(sel => {
    if (!sel) return;
    sel.addEventListener('change', applyBuilderToSchedule);
  });
});
</script>
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
