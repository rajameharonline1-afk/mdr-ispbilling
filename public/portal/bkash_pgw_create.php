<?php
// /public/portal/bkash_pgw_create.php
// Purpose: Portal AJAX endpoint to create a bKash Tokenized Checkout payment and return bkashURL.
// Response: Bengali JSON.

declare(strict_types=1);

require_once __DIR__ . '/../../app/portal_require_login.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/bkash_tokenized.php';

function bn_json(int $code, array $body): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  bn_json(405, ['ok' => false, 'message' => 'শুধু POST গ্রহণ করা হয়।']);
}

$raw = file_get_contents('php://input');
$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];
if (stripos($ct, 'application/json') !== false) {
  $data = json_decode($raw, true) ?: [];
} else {
  $data = $_POST ?: (json_decode($raw, true) ?: []);
}
if (!is_array($data)) $data = [];

$amount = (float)($data['amount'] ?? 0);
$ref    = trim((string)($data['ref'] ?? ''));
$payer  = trim((string)($data['payer'] ?? ''));

function normalize_bd_msisdn(string $v): string {
  $digits = preg_replace('/\D+/', '', $v);
  if (!$digits) return '';
  // +8801XXXXXXXXX / 8801XXXXXXXXX → 01XXXXXXXXX
  if (str_starts_with($digits, '8801') && strlen($digits) === 13) $digits = '0' . substr($digits, 3);
  if (str_starts_with($digits, '1') && strlen($digits) === 10) $digits = '0' . $digits;
  return $digits;
}

if ($amount <= 0) {
  bn_json(422, ['ok' => false, 'message' => 'অ্যামাউন্ট সঠিক নয়।']);
}
if ($amount > 500000) {
  bn_json(422, ['ok' => false, 'message' => 'অ্যামাউন্ট সীমা অতিক্রম করেছে।']);
}
if ($ref === '') {
  bn_json(422, ['ok' => false, 'message' => 'রেফারেন্স (Invoice/Client Code) লাগবে।']);
}

// Try auto payerReference from logged-in portal client profile (clients.bkash_msisdn/mobile)
if ($payer === '') {
  $cid = 0;
  if (function_exists('portal_client_id')) $cid = (int)portal_client_id();
  if ($cid <= 0 && session_status() !== PHP_SESSION_NONE) {
    $cid = (int)($_SESSION['portal_client_id'] ?? 0);
  }
  if ($cid > 0) {
    try {
      $pdo = db();
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $st = $pdo->prepare("SELECT bkash_msisdn, mobile FROM clients WHERE id=? LIMIT 1");
      $st->execute([$cid]);
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
      $payer = (string)($row['bkash_msisdn'] ?? '') ?: (string)($row['mobile'] ?? '');
    } catch (Throwable $e) {
      // ignore
    }
  }
}

$payer = normalize_bd_msisdn($payer);
if (!preg_match('/^01\\d{9}$/', $payer)) {
  bn_json(422, ['ok' => false, 'message' => 'আপনার bKash নম্বর/মোবাইল নম্বর সঠিক নয়। প্রোফাইলে 01XXXXXXXXX ফরম্যাটে সেট করুন।']);
}

// Build callback URL
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($host === '') {
  bn_json(500, ['ok' => false, 'message' => 'সার্ভার হোস্ট পাওয়া যায়নি।']);
}
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme  = $isHttps ? 'https' : 'http';
$callbackUrl = $scheme . '://' . $host . '/api/bkash_pgw_callback.php';

try {
  $createReq = [
    'mode'                  => '0011',
    'payerReference'        => $payer,
    'callbackURL'           => $callbackUrl,
    'amount'                => number_format($amount, 2, '.', ''),
    'currency'              => 'BDT',
    'intent'                => 'sale',
    'merchantInvoiceNumber' => $ref,
  ];

  $res = bkash_tz_create_payment($createReq);
  bn_json(200, [
    'ok'        => true,
    'message'   => 'bKash পেমেন্ট লিংক তৈরি হয়েছে।',
    'paymentID' => $res['paymentID'] ?? null,
    'bkashURL'  => $res['bkashURL'] ?? null,
  ]);
} catch (Throwable $e) {
  $err = (string)$e->getMessage();
  $detail = '';
  // Try to extract a concise vendor message from "... Response: {...json...}"
  $pos = strpos($err, 'Response: ');
  if ($pos !== false) {
    $json = trim(substr($err, $pos + 10));
    $j = json_decode($json, true);
    if (is_array($j)) {
      $detail = (string)($j['statusMessage'] ?? $j['message'] ?? $j['consideration'] ?? $j['errorMessage'] ?? '');
      if ($detail === '' && isset($j['errorCode'])) {
        $detail = (string)$j['errorCode'];
      }
    }
  }
  $msg = 'bKash পেমেন্ট তৈরি করা যায়নি।';
  if ($detail !== '') $msg .= ' কারণ: ' . $detail;
  bn_json(500, ['ok' => false, 'message' => $msg, 'error' => $detail !== '' ? $detail : $err]);
}
