<?php
// /api/bkash_pgw_callback.php
// Purpose: bKash tokenized checkout callback endpoint.
// Notes: Executes payment and stores result into sms_inbox for later auto-apply.
// UI: Bengali.

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/db.php';
require_once dirname(__DIR__) . '/app/bkash_tokenized.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$status   = strtolower(trim((string)($_GET['status'] ?? '')));
$paymentID = trim((string)($_GET['paymentID'] ?? $_GET['paymentId'] ?? ''));

// Simple result page (portal will redirect back)
$redirectTo = '/public/portal/invoices.php';

if ($paymentID === '') {
  http_response_code(400);
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">';
  echo '<h3 style="margin:0 0 8px;">পেমেন্ট সম্পন্ন হয়নি</h3>';
  echo '<p>paymentID পাওয়া যায়নি।</p>';
  echo '<a href="'.h($redirectTo).'">ইনভয়েসে ফিরে যান</a>';
  echo '</div>';
  exit;
}

if ($status !== '' && $status !== 'success') {
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">';
  echo '<h3 style="margin:0 0 8px;">পেমেন্ট বাতিল/ব্যর্থ</h3>';
  echo '<p>স্ট্যাটাস: <strong>'.h($status).'</strong></p>';
  echo '<a href="'.h($redirectTo).'">ইনভয়েসে ফিরে যান</a>';
  echo '</div>';
  exit;
}

// Try execute and store into sms_inbox
try {
  $exec = bkash_tz_execute_payment($paymentID);

  $trxID  = trim((string)($exec['trxID'] ?? $exec['trxId'] ?? ''));
  $amount = (float)($exec['amount'] ?? 0);
  $payer  = trim((string)($exec['customerMsisdn'] ?? $exec['payerReference'] ?? ''));
  $ref    = trim((string)($exec['merchantInvoiceNumber'] ?? $exec['invoiceNumber'] ?? ''));
  $tst    = trim((string)($exec['transactionStatus'] ?? $exec['status'] ?? 'Completed'));

  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Deduplicate if trxID exists
  if ($trxID !== '') {
    $chk = $pdo->prepare("SELECT id FROM sms_inbox WHERE gateway='bkash_webhook' AND trx_id=? LIMIT 1");
    $chk->execute([$trxID]);
    if (!$chk->fetchColumn()) {
      $meta = [
        'source'  => 'bkash_pgw_callback',
        'payload' => $exec,
        'paymentID' => $paymentID,
      ];
      $rawBody = json_encode($exec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $st = $pdo->prepare("INSERT INTO sms_inbox (gateway, msisdn_from, msisdn_to, raw_body, trx_id, amount, sender_number, ref_code, received_at, meta_json)
                           VALUES ('bkash_webhook', ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)");
      $st->execute([
        $payer ?: null,
        $rawBody,
        $trxID ?: null,
        $amount > 0 ? $amount : null,
        $payer ?: null,
        $ref ?: null,
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ]);
    }
  }

  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">';
  echo '<h3 style="margin:0 0 8px;">পেমেন্ট সম্পন্ন হয়েছে</h3>';
  echo '<p>ট্রান্স্যাকশন আইডি: <strong>'.h($trxID ?: 'N/A').'</strong></p>';
  echo '<p>অ্যামাউন্ট: <strong>'.h(number_format($amount, 2)).'</strong></p>';
  echo '<p>রেফারেন্স: <strong>'.h($ref ?: 'N/A').'</strong></p>';
  echo '<p style="color:#555">পেমেন্টটি যাচাই/অটো-এপ্লাই হতে কয়েক মুহূর্ত সময় লাগতে পারে।</p>';
  echo '<a href="'.h($redirectTo).'" style="display:inline-block;padding:8px 12px;border:1px solid #ccc;border-radius:8px;text-decoration:none">ইনভয়েসে ফিরে যান</a>';
  echo '</div>';
} catch (Throwable $e) {
  http_response_code(500);
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">';
  echo '<h3 style="margin:0 0 8px;">পেমেন্ট প্রসেস করা যায়নি</h3>';
  echo '<p style="color:#b02a37">'.h($e->getMessage()).'</p>';
  echo '<a href="'.h($redirectTo).'">ইনভয়েসে ফিরে যান</a>';
  echo '</div>';
}

