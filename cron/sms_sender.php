<?php
// /cron/sms_sender.php
// Purpose: sms_queue থেকে pending মেসেজ সেন্ড করা + status আপডেট
// Notes: rate-limit / batch-size কনফিগ রাখুন; রিট্রাই লিমিট সহ

declare(strict_types=1);
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/sms.php';   // send_sms()
@include_once __DIR__ . '/../app/audit.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------- Settings ----------
$batch      = max(1, (int)($_GET['batch'] ?? 100));   // প্রতি রান কতগুলো পাঠাবেন
$max_retry  = 3;                                      // ব্যর্থ হলে কতবার পর্যন্ত রিট্রাই
$sleep_ms   = 200;                                    // প্রতিটি সেন্ডের মাঝে ডিলে (ms)
$client_id  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// CLI helpers
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
$cliCid = cli_get_value('client_id');
if ($client_id <= 0 && $cliCid !== null && ctype_digit((string)$cliCid)) {
  $client_id = (int)$cliCid;
}

// Audit helper (best-effort; action, entity_id, meta)
if (!function_exists('audit_log_safe_sms')) {
  function audit_log_safe_sms(string $action, ?int $entity_id, array $meta = []): void {
    if (!function_exists('audit_log')) return;
    // prefer modern signature (action, entity_id, meta)
    try { @audit_log($action, $entity_id, $meta); return; } catch (Throwable $e) {}
    // fallback legacy (entity, entity_id, action, old, new)
    try { @audit_log('client', $entity_id, $action, null, $meta); return; } catch (Throwable $e) {}
    // last resort (action, meta)
    try { @audit_log($action, $meta); } catch (Throwable $e) {}
  }
}

// ---------- Fetch pending ----------
$sql = "
  SELECT id, client_id, mobile, message, attempts
  FROM sms_queue
  WHERE status='pending' AND scheduled_at <= NOW()".
  ($client_id > 0 ? " AND client_id=:cid" : "") ."
  ORDER BY id ASC
  LIMIT :lim
";
$q = $pdo->prepare($sql);
$q->bindValue(':lim', $batch, PDO::PARAM_INT);
if ($client_id > 0) $q->bindValue(':cid', $client_id, PDO::PARAM_INT);
$q->execute();
$items = $q->fetchAll(PDO::FETCH_ASSOC);

if (!$items) { echo "No pending SMS.\n"; exit; }

// ---------- Prepare updates ----------
$u_sent = $pdo->prepare("
  UPDATE sms_queue
     SET status='sent', sent_at=NOW(), attempts=attempts+1, last_error=NULL, updated_at=NOW()
   WHERE id=?
");
$u_fail = $pdo->prepare("
  UPDATE sms_queue
     SET status=CASE WHEN attempts+1 >= :maxr THEN 'failed' ELSE 'pending' END,
         attempts=attempts+1,
         last_error=:err,
         updated_at=NOW()
   WHERE id=:id
");

$sent = 0; $failed = 0;
foreach ($items as $it) {
  $id      = (int)$it['id'];
  $mobile  = trim($it['mobile']);
  $message = (string)$it['message'];
  $cid     = isset($it['client_id']) ? (int)$it['client_id'] : null;
  $msgPreview = mb_substr($message, 0, 160);

  if ($mobile === '' || $message === '') {
    $u_fail->execute([':maxr'=>$max_retry, ':err'=>'missing mobile/message', ':id'=>$id]);
    audit_log_safe_sms('sms_send_failed', $cid, [
      'queue_id'=>$id,
      'mobile'=>$mobile,
      'message'=>$msgPreview,
      'attempts'=>($it['attempts'] ?? 0)+1,
      'error'=>'missing mobile/message',
      'via'=>'sms_sender'
    ]);
    $failed++;
    continue;
  }

  $res = send_sms($mobile, $message);

  if ($res['ok']) {
    $u_sent->execute([$id]);
    audit_log_safe_sms('sms_sent', $cid, [
      'queue_id'=>$id,
      'mobile'=>$mobile,
      'message'=>$msgPreview,
      'attempts'=>($it['attempts'] ?? 0)+1,
      'provider_response'=>$res['raw'] ?? null,
      'http_code'=>$res['http_code'] ?? null,
      'via'=>'sms_sender'
    ]);
    $sent++;
  } else {
    $err = 'HTTP='.$res['http_code'].' ERR='.$res['error'].' RAW='.substr((string)$res['raw'],0,250);
    $u_fail->execute([':maxr'=>$max_retry, ':err'=>$err, ':id'=>$id]);
    audit_log_safe_sms('sms_send_failed', $cid, [
      'queue_id'=>$id,
      'mobile'=>$mobile,
      'message'=>$msgPreview,
      'attempts'=>($it['attempts'] ?? 0)+1,
      'error'=>$err,
      'http_code'=>$res['http_code'] ?? null,
      'via'=>'sms_sender'
    ]);
    $failed++;
  }

  // rate-limit
  if ($sleep_ms > 0) usleep($sleep_ms * 1000);
}

echo "SMS sent={$sent}, failed={$failed}, batch={$batch}\n";
