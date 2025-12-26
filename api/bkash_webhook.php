<?php
// /api/bkash_webhook.php
// Purpose: Receive bKash PGW/PSP webhook notifications and log them into sms_inbox.
// Response language: Bengali (বাংলা)
// Security: shared secret token via header/query; optional IP allowlist.

declare(strict_types=1);

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/settings_store.php';

/* ---------- helpers ---------- */
function cfg(string $k, $def = null) {
  if (function_exists('settings_get')) {
    $sv = settings_get($k, null);
    if ($sv !== null && $sv !== '') return $sv;
  }
  if (defined($k)) return constant($k);
  $env = getenv($k);
  if ($env !== false && $env !== '') return $env;
  if (isset($GLOBALS['CONFIG'][$k])) return $GLOBALS['CONFIG'][$k];
  return $def;
}
function bn_json(int $code, array $body): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ---------- security ---------- */
$SECRET = (string)cfg('BKASH_WEBHOOK_TOKEN', '');
if ($SECRET === '') {
  bn_json(500, ['ok' => false, 'message' => 'সার্ভার কনফিগার করা হয়নি (BKASH_WEBHOOK_TOKEN লাগবে)।']);
}
$allow = trim((string)cfg('BKASH_WEBHOOK_IP_WHITELIST', ''));
if ($allow !== '') {
  $ips = array_filter(array_map('trim', explode(',', $allow)));
  $rip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$rip || !in_array($rip, $ips, true)) {
    bn_json(403, ['ok' => false, 'message' => 'অনুমোদিত আইপি নয়।']);
  }
}
$token = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? $_SERVER['HTTP_X_BKASH_TOKEN'] ?? ($_GET['token'] ?? '');
if (!hash_equals($SECRET, (string)$token)) {
  bn_json(401, ['ok' => false, 'message' => 'টোকেন মিলছে না।']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  bn_json(405, ['ok' => false, 'message' => 'শুধু POST গ্রহণ করা হয়।']);
}

/* ---------- payload ---------- */
$raw = file_get_contents('php://input');
$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];
if (stripos($ct, 'application/json') !== false) {
  $data = json_decode($raw, true) ?: [];
} else {
  $data = $_POST ?: (json_decode($raw, true) ?: []);
}
if (!is_array($data) || $data === []) {
  bn_json(422, ['ok' => false, 'message' => 'বৈধ JSON ডেটা পাওয়া যায়নি।']);
}

/* ---------- normalize fields ---------- */
$trxID   = trim((string)($data['trxID'] ?? $data['transactionId'] ?? $data['trx_id'] ?? ''));
$amount  = (float)($data['amount'] ?? 0);
$msisdn  = trim((string)($data['customerMsisdn'] ?? $data['payerReference'] ?? $data['sender'] ?? ''));
$refCode = trim((string)($data['merchantInvoiceNumber'] ?? $data['orderId'] ?? $data['reference'] ?? ''));
$status  = trim((string)($data['transactionStatus'] ?? $data['status'] ?? ''));
$receivedAt = $data['completedTime'] ?? $data['paymentExecuteTime'] ?? $data['time'] ?? $data['timestamp'] ?? '';
if ($receivedAt) {
  $ts = strtotime((string)$receivedAt);
  $receivedAt = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
} else {
  $receivedAt = date('Y-m-d H:i:s');
}

/* ---------- dedupe check ---------- */
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($trxID !== '') {
  $chk = $pdo->prepare("SELECT id FROM sms_inbox WHERE gateway='bkash_webhook' AND trx_id=? LIMIT 1");
  $chk->execute([$trxID]);
  if ($chk->fetchColumn()) {
    bn_json(200, ['ok' => true, 'duplicate' => true, 'message' => 'এই ট্রান্স্যাকশন আগে সেভ করা হয়েছে।']);
  }
}

/* ---------- persist to sms_inbox ---------- */
$rawBody = $raw ?: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$meta = [
  'headers' => [
    'content_type' => $ct,
    'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'remote_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
  ],
  'payload' => $data,
];

$st = $pdo->prepare("INSERT INTO sms_inbox (gateway, msisdn_from, msisdn_to, raw_body, trx_id, amount, sender_number, ref_code, received_at, meta_json)
                     VALUES ('bkash_webhook', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$st->execute([
  $msisdn ?: null,
  $data['merchantMsisdn'] ?? null,
  $rawBody,
  $trxID ?: null,
  $amount > 0 ? $amount : null,
  $msisdn ?: null,
  $refCode ?: null,
  $receivedAt,
  json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$id = (int)$pdo->lastInsertId();

/* ---------- response ---------- */
bn_json(200, [
  'ok'        => true,
  'message'   => 'ওয়েবহুক গ্রহণ করা হয়েছে এবং ইনবক্সে রাখা হয়েছে।',
  'inbox_id'  => $id,
  'trx_id'    => $trxID,
  'status'    => $status,
]);
