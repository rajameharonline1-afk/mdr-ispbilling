<?php
// /api/bkash_rtn/notify.php
// Purpose: Receive bKash RTN (webhooks) notifications and store into bkash_rtn_events table.
// Response: Bengali JSON.
// Security: shared token + optional IP allowlist (separate keys from old webhook).

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bkash_rtn.php';

function bn_json(int $code, array $body): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// Config keys (separate from legacy sms_inbox webhook)
$SECRET = (string)bkash_rtn_cfg('BKASH_RTN_WEBHOOK_TOKEN', '');
$allow  = trim((string)bkash_rtn_cfg('BKASH_RTN_IP_WHITELIST', ''));

if ($SECRET === '') {
  bn_json(500, ['ok' => false, 'message' => 'সার্ভার কনফিগার করা হয়নি (BKASH_RTN_WEBHOOK_TOKEN লাগবে)।']);
}

if ($allow !== '') {
  $ips = array_filter(array_map('trim', explode(',', $allow)));
  $rip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$rip || !in_array($rip, $ips, true)) {
    bn_json(403, ['ok' => false, 'message' => 'অনুমোদিত আইপি নয়।']);
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  bn_json(405, ['ok' => false, 'message' => 'শুধু POST গ্রহণ করা হয়।']);
}

$token = $_SERVER['HTTP_X_BKASH_WEBHOOK_TOKEN']
  ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN']
  ?? $_SERVER['HTTP_X_BKASH_TOKEN']
  ?? ($_GET['token'] ?? '');

if (!hash_equals($SECRET, (string)$token)) {
  bn_json(401, ['ok' => false, 'message' => 'টোকেন মিলছে না।']);
}

$raw = (string)file_get_contents('php://input');
$ct  = (string)($_SERVER['CONTENT_TYPE'] ?? '');

$data = [];
if (stripos($ct, 'application/json') !== false) {
  $data = json_decode($raw, true) ?: [];
} else {
  $data = $_POST ?: (json_decode($raw, true) ?: []);
}
if (!is_array($data) || $data === []) {
  bn_json(422, ['ok' => false, 'message' => 'বৈধ JSON ডেটা পাওয়া যায়নি।']);
}

$headers = [
  'content_type' => $ct,
  'user_agent'   => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
  'remote_ip'    => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
  'host'         => (string)($_SERVER['HTTP_HOST'] ?? ''),
];

$store = bkash_rtn_store_event($raw !== '' ? $raw : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $headers, $data);
if (!empty($store['error'])) {
  bn_json(500, ['ok' => false, 'message' => 'ইভেন্ট সেভ করা যায়নি।', 'error' => $store['error']]);
}
if (!empty($store['duplicate'])) {
  bn_json(200, ['ok' => true, 'duplicate' => true, 'message' => 'ইভেন্ট আগেই সেভ করা হয়েছে।']);
}

bn_json(200, [
  'ok'      => true,
  'message' => 'ওয়েবহুক গ্রহণ করা হয়েছে (RTN events টেবিলে সেভ হয়েছে)।',
  'id'      => $store['id'] ?? null,
]);

