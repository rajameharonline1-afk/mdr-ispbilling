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

/* ---------- (ঐচ্ছিক) Admin-only গার্ড ----------
   যদি আপনার সিস্টেমে $_SESSION['user']['role']=='admin' থাকে, আনকমেন্ট করুন
// if (($_SESSION['user']['role'] ?? '') !== 'admin') {
//   http_response_code(403);
//   echo 'Forbidden';
//   exit;
// }
*/

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
    'url'   => '/public/invoice_generate.php?month={month}&commit=1',
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
    'schedule' => '*/5 * * * *',
    'supports' => [],
  ],
  'auto_billing' => [
    'title' => 'Auto Billing',
    'url'   => '/cron/auto_billing.php',
    'method'=> 'GET',
    'desc'  => 'Generate periodic billing actions.',
    'timeout' => 180,
    'schedule' => '0 2 * * *',
    'supports' => [],
  ],
  'auto_suspend' => [
    'title' => 'Auto Suspend (due clients)',
    'url'   => '/cron/auto_suspend.php',
    'method'=> 'GET',
    'desc'  => 'Suspend clients based on due rules.',
    'timeout' => 180,
    'schedule' => '*/15 * * * *',
    'supports' => [],
  ],
  'auto_suspend_enable' => [
    'title' => 'Auto Resume (enable)',
    'url'   => '/cron/auto_suspend_enable.php',
    'method'=> 'GET',
    'desc'  => 'Re-enable clients when dues cleared.',
    'timeout' => 180,
    'schedule' => '*/15 * * * *',
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
    'schedule' => '0 */2 * * *',
    'supports' => [],
  ],
  'notify_runner' => [
    'title' => 'Notification Runner',
    'url'   => '/cron/notify_runner.php',
    'method'=> 'GET',
    'desc'  => 'Process notification queue.',
    'timeout' => 120,
    'schedule' => '*/10 * * * *',
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
    'supports' => [],
  ],
  'sync_packages_all' => [
    'title' => 'Sync Packages (all)',
    'url'   => '/cron/sync_packages_all.php',
    'method'=> 'GET',
    'desc'  => 'Sync all package profiles.',
    'timeout' => 240,
    'supports' => [],
  ],
  'auto_bkash_apply' => [
    'title' => 'Auto bKash Apply',
    'url'   => '/cron/auto_bkash_apply.php',
    'method'=> 'GET',
    'desc'  => 'Apply bKash payments automatically.',
    'timeout' => 180,
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
    'url'   => '/api/olt_mac_refresh_telnet.php?mode=fast',
    'method'=> 'GET',
    'desc'  => 'Refresh OLT MAC cache via telnet (onu_inventory/onu_mac_map টেবিল প্রয়োজন)।',
    'timeout' => 300,
    'supports' => [],
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
    echo '<tr><td colspan="9" class="text-center text-muted">No runs recorded.</td></tr>';
  } else {
    foreach($rows as $r){ ?>
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
        <td><?=render_log_cell($r['output'] ?? '', 140)?></td>
        <td><?=render_log_cell($r['error'] ?? '', 140)?></td>
      </tr>
    <?php }
  }
  return ob_get_clean();
}

/* (বাংলা) লোকাল HTTP কল — cURL প্রেফার্ড; নইলে file_get_contents */
function http_call(string $url, string $method = 'GET', array $post = [], int $timeout = 120): array {
  $full = local_url($url);
  $method = strtoupper($method);
  $cookie = '';
  if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
    $cookie = 'PHPSESSID=' . session_id();
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
$schedules     = []; // পরে লোড হবে

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_job'])) {
  $job_key = trim($_POST['run_job']);
  if (!isset($jobs[$job_key])) {
    $flash_error = 'Unknown job.';
  } else {
    $job = $jobs[$job_key];
    $title = $job['title'];
    $user_id = (int)($_SESSION['user']['id'] ?? 0);

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
    // টোকেন থাকলে URL এ যুক্ত করি (GET কলের জন্য)
    if ($tokenForward) {
      $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . urlencode($tokenForward);
    }

    // স্টার্ট লগ
    $st = dbh()->prepare("INSERT INTO cron_runs (job_key, title, started_at, triggered_by) VALUES (?, ?, NOW(), ?)");
    $st->execute([$job_key, $title, $user_id]);
    $run_id = (int)dbh()->lastInsertId();

    // Execute
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

// Ajax POST response (job run)
if ($isAjaxReq && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_job'])) {
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
?>
<?php require_once __DIR__ . '/../partials/partials_header.php'; ?>
<style>
  .log-clip {max-height:140px; overflow:auto; background:#f8f9fa; border:1px solid #e9ecef; padding:8px;}
  .table-condensed td, .table-condensed th {padding: .5rem .6rem;}
  .table-code code {white-space: nowrap;}
  .jobs-table { min-width: 1300px; table-layout: auto; }
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
  .schedule-form input.form-control{min-width:110px;}
  .jobs-table .last-meta{display:flex; flex-direction:column; align-items:flex-start; gap:2px;}
  .jobs-table .last-meta .small{line-height:1.2; word-break:break-word;}
</style>
<div class="container-fluid my-4">
  <div class="d-flex align-items-center justify-content-between">
    <h3 class="mb-0">Cron Dashboard</h3>
    <div class="d-flex gap-2">
      <a href="?<?=h(http_build_query($_GET))?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </a>
      <button type="button" class="btn btn-outline-dark" id="btn-hard-refresh">
        <i class="bi bi-arrow-repeat"></i> Hard Refresh
      </button>
    </div>
  </div>

  <?php if ($flash_success): ?>
    <div class="alert alert-success mt-3"><?=h($flash_success)?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger mt-3"><?=h($flash_error)?></div>
  <?php endif; ?>
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
          ?>
            <tr>
              <td class="job-col">
                <div class="job-title"><?=h($j['title'])?></div>
                <div class="job-key text-muted"><?=h($key)?></div>
              </td>
              <td><?=h($j['desc'])?></td>
              <td>
                <code><?=h($j['method'] ?? 'GET')?> <?=h($url_preview)?></code>
                <div class="text-muted small">Timeout: <?=h((string)($j['timeout'] ?? 120))?>s</div>
              </td>
              <td class="text-end action-col">
                <div class="d-flex flex-column align-items-end gap-2">
                  

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
                          data-schedule="<?=h($schedules[$key] ?? ($j['schedule'] ?? '*/5 * * * *'))?>">
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
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
<script>
(function(){
  const flashDock = document.getElementById('flash-dock');
  function showFlash(type, text){
    if (!flashDock) return;
    const div = document.createElement('div');
    div.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' mt-3';
    div.textContent = text || (type === 'success' ? 'Done' : 'Failed');
    flashDock.innerHTML = '';
    flashDock.appendChild(div);
    setTimeout(() => { if (flashDock.contains(div)) div.remove(); }, 4000);
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

  // Schedule save forms
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

  // View last run modal
  const lrModal = document.getElementById('lastRunModal');
  lrModal?.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget;
    if (!btn) return;
    const get = (k) => btn.getAttribute('data-' + k) || '';
    const title = get('title');
    const job = get('job');
    const status = (get('status') || '').toLowerCase();
    const start = get('start') || '—';
    const end = get('end') || '—';
    const dur = get('duration') ? get('duration') + ' ms' : '—';
    const output = get('output') || '—';
    const error = get('error') || '—';

    lrModal.querySelector('#lr-title').textContent = title || '—';
    lrModal.querySelector('#lr-job').textContent = job ? '(' + job + ')' : '';
    const badge = lrModal.querySelector('#lr-status');
    badge.textContent = status || '—';
    badge.className = 'badge ' + (status === 'success' ? 'bg-success' : (status === 'failed' ? 'bg-danger' : 'bg-secondary'));
    lrModal.querySelector('#lr-start').textContent = start;
    lrModal.querySelector('#lr-end').textContent = end;
    lrModal.querySelector('#lr-duration').textContent = dur;
    lrModal.querySelector('#lr-output').textContent = output;
    lrModal.querySelector('#lr-error').textContent = error;
    const schedInput = lrModal.querySelector('#lr-schedule-input');
    const saveKey = lrModal.querySelector('#lr-save-key');
    if (saveKey) saveKey.value = job;
    if (schedInput) schedInput.value = get('schedule') || '*/5 * * * *';
    const monthWrap = lrModal.querySelector('#lr-month-wrap');
    if (monthWrap) monthWrap.classList.toggle('d-none', get('supports-month') !== '1');
    const runJob = lrModal.querySelector('#lr-run-job');
    if (runJob) runJob.value = job;

    // AJAX দিয়ে সর্বশেষ রান নিয়ে আসি (always refresh)
    if (job) {
      fetch(window.location.pathname + '?ajax=last&job=' + encodeURIComponent(job), {
        headers:{'X-Requested-With':'XMLHttpRequest'}
      }).then(r=>r.json()).then(j=>{
        if (j && j.ok && j.last) {
          const lr = j.last;
          const st = (lr.status || '').toLowerCase();
          badge.textContent = st || '—';
          badge.className = 'badge ' + (st==='success'?'bg-success':(st==='failed'?'bg-danger':'bg-secondary'));
          lrModal.querySelector('#lr-start').textContent = lr.started_at || '—';
          lrModal.querySelector('#lr-end').textContent = lr.finished_at || '—';
          lrModal.querySelector('#lr-duration').textContent = lr.duration_ms ? (lr.duration_ms + ' ms') : '—';
          lrModal.querySelector('#lr-output').textContent = (lr.output || '').substring(0,1000) || '—';
          lrModal.querySelector('#lr-error').textContent = (lr.error || '').substring(0,1000) || '—';
        } else {
          badge.textContent = '—';
          badge.className = 'badge bg-secondary';
          lrModal.querySelector('#lr-start').textContent = '—';
          lrModal.querySelector('#lr-end').textContent = '—';
          lrModal.querySelector('#lr-duration').textContent = '—';
          lrModal.querySelector('#lr-output').textContent = 'No runs yet';
          lrModal.querySelector('#lr-error').textContent = '—';
        }
      }).catch(()=>{});
    }
  });

  // Hard refresh button: force page reload (cache bypass)
  document.getElementById('btn-hard-refresh')?.addEventListener('click', () => {
    window.location.reload(true);
  });

  // Modal schedule save
  const lrSchedForm = document.getElementById('lr-schedule-form');
  lrSchedForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = lrSchedForm.querySelector('button[type="submit"]') || lrSchedForm.querySelector('button');
    const spin = btn?.querySelector('.btn-spinner');
    if (btn) btn.disabled = true;
    if (spin) spin.classList.remove('d-none');
    const fd = new FormData(lrSchedForm);
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

  // Modal run form
  const lrRunForm = document.getElementById('lr-run-form');
  lrRunForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = lrRunForm.querySelector('button[type="submit"]') || lrRunForm.querySelector('button');
    const spin = btn?.querySelector('.btn-spinner');
    if (btn) btn.disabled = true;
    if (spin) spin.classList.remove('d-none');
    const fd = new FormData(lrRunForm);
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
})();
</script>

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
        </div>
        <div class="mb-3">
          <label class="form-label mb-1">Run this job</label>
          <form id="lr-run-form" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="run_job" id="lr-run-job" value="">
            <div id="lr-month-wrap" class="d-flex align-items-center gap-1 d-none">
              <span class="small">Month</span>
              <input type="month" name="month" id="lr-month-input" class="form-control form-control-sm">
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
