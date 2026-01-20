<?php
// /cron/sms_due_reminder.php
// Purpose: Due থাকা ক্লায়েন্টদের SMS reminder কিউতে তোলা
// Style: Procedural + PDO; safe prepared; Bengali comments

declare(strict_types=1);
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../app/db.php';
@include_once __DIR__ . '/../app/audit.php';

function hcol(PDO $pdo, string $tbl, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
}

// CLI helper
if (!function_exists('cli_get_value')) {
  function cli_get_value(string $key): ?string {
    global $argv;
    if (PHP_SAPI !== 'cli' || empty($argv)) return null;
    foreach ($argv as $arg) {
      if (preg_match('/^--'.preg_quote($key,'/').'=(.*)$/', $arg, $m)) return $m[1];
      if ($arg === '--'.$key) return '1';
    }
    return null;
  }
}

// Audit helper (best-effort; prefers action, entity_id, meta)
if (!function_exists('audit_log_safe_reminder')) {
  function audit_log_safe_reminder(string $action, ?int $entity_id, array $meta = []): void {
    if (!function_exists('audit_log')) return;
    try { @audit_log($action, $entity_id, $meta); return; } catch (Throwable $e) {}
    try { @audit_log('client', $entity_id, $action, null, $meta); return; } catch (Throwable $e) {}
    try { @audit_log($action, $meta); } catch (Throwable $e) {}
  }
}

// ---------- Inputs (GET/CLI) ----------
$ym         = trim($_GET['month'] ?? date('Y-m'));        // YYYY-MM (optional; শুধু মেসেজ টেক্সটে বসাতে)
$router_id  = isset($_GET['router_id']) ? (int)$_GET['router_id'] : 0;
$area       = trim($_GET['area'] ?? '');
$limit      = max(1, (int)($_GET['limit'] ?? 500));       // কিউ সাইজ গার্ড
$dry        = (int)($_GET['dry'] ?? 0);                   // preview only; DB write skip করলে 1
$client_id  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$cliCid     = cli_get_value('client_id');
if ($client_id <= 0 && $cliCid !== null && ctype_digit((string)$cliCid)) {
  $client_id = (int)$cliCid;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------- Build filter ----------
$where = "COALESCE(c.is_left,0)=0 AND COALESCE(c.ledger_balance,0) < 0";
$args  = [];
if ($router_id > 0) { $where .= " AND c.router_id = ?"; $args[] = $router_id; }
if ($area !== '')   { $where .= " AND c.area = ?";      $args[] = $area; }
if ($client_id > 0) { $where .= " AND c.id = ?";        $args[] = $client_id; }

$col_mobile = hcol($pdo,'clients','mobile') ? 'mobile' : null;
if (!$col_mobile) { http_response_code(500); echo "clients.mobile not found\n"; exit; }

$sql = "
SELECT c.id, c.name, c.$col_mobile AS mobile, c.ledger_balance
FROM clients c
WHERE $where
ORDER BY c.ledger_balance ASC
LIMIT $limit
";
$rows = $pdo->prepare($sql);
$rows->execute($args);
$list = $rows->fetchAll(PDO::FETCH_ASSOC);

if (!$list) { echo "No due clients.\n"; exit; }

// ---------- Template তৈরি ----------
// (বাংলা) প্রয়োজনমতো ছোট রাখুন—বাংলা ইউনিকোড ঠিকমতো সাপোর্ট করুন
function render_msg(array $c, string $ym): string {
  // উদাহরণ: Discovery Internet: আপনার বকেয়া 650 TK (2025-08)। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ।
  $due = number_format(abs((float)$c['ledger_balance']), 0, '.', '');
  return "Discovery Internet: আপনার বকেয়া {$due} TK ({$ym})। অনুগ্রহ করে দ্রুত পরিশোধ করুন। ধন্যবাদ।";
}

// ---------- Insert queue (dedupe) ----------
$ins = $pdo->prepare("
  INSERT INTO sms_queue (client_id, mobile, message, status, scheduled_at, dedupe_key, payload)
  VALUES (:client_id, :mobile, :message, 'pending', NOW(), :dedupe, :payload)
  ON DUPLICATE KEY UPDATE updated_at=NOW()
");

$queued = 0; $skipped = 0;
foreach ($list as $c) {
  $mobile = trim((string)$c['mobile']);
  if ($mobile === '') { $skipped++; continue; }

  $msg   = render_msg($c, $ym);
  // (বাংলা) dedupe: ক্লায়েন্ট + মাস + পরিমাণ (ইচ্ছা করলে শুধু ক্লায়েন্ট+মাস রাখুন)
  $dedupe = 'due:' . $c['id'] . ':' . $ym . ':' . (int)abs((float)$c['ledger_balance']);
  $meta = [
    'mobile'=>$mobile,
    'month'=>$ym,
    'due'=>abs((float)$c['ledger_balance']),
    'dedupe'=>$dedupe,
    'dry'=>(bool)$dry,
    'via'=>'sms_due_reminder'
  ];

  if ($dry) {
    $queued++;
    if ($client_id > 0) {
      audit_log_safe_reminder('sms_due_reminder_preview', (int)$c['id'], $meta);
    }
    continue;
  }

  try {
    $ins->execute([
      ':client_id' => (int)$c['id'],
      ':mobile'    => $mobile,
      ':message'   => $msg,
      ':dedupe'    => $dedupe,
      ':payload'   => json_encode(['ym'=>$ym, 'due'=>abs((float)$c['ledger_balance'])], JSON_UNESCAPED_UNICODE),
    ]);
    $queued++;
    if ($client_id > 0) {
      audit_log_safe_reminder('sms_due_reminder_enqueued', (int)$c['id'], $meta);
    }
  } catch (Throwable $e) {
    // duplicate হলে harmless
    $skipped++;
    if ($client_id > 0) {
      audit_log_safe_reminder('sms_due_reminder_skipped', (int)$c['id'], $meta + ['error'=>'duplicate_or_failed']);
    }
  }
}

echo "SMS queued: {$queued}, skipped: {$skipped}, month={$ym}, filter(router_id={$router_id}, area='{$area}')" . ($dry ? " [DRY]\n" : "\n");
