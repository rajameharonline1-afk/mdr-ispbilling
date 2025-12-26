<?php
// /app/bkash_rtn.php
// Purpose: bKash RTN (Real-time Payment Notifications) storage helpers.
// Note: Uses separate DB table `bkash_rtn_events` (does not use sms_inbox).

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings_store.php';

function bkash_rtn_cfg(string $k, $def = null) {
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

function bkash_rtn_table_exists(PDO $pdo): bool {
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE 'bkash_rtn_events'");
    $st->execute();
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function bkash_rtn_extract_fields(array $payload): array {
  $eventType = (string)($payload['eventType'] ?? $payload['event_type'] ?? $payload['type'] ?? $payload['event'] ?? '');
  $eventId   = (string)($payload['eventId'] ?? $payload['event_id'] ?? $payload['id'] ?? '');

  $paymentId = (string)($payload['paymentID'] ?? $payload['paymentId'] ?? $payload['payment_id'] ?? '');
  $trxId     = (string)($payload['trxID'] ?? $payload['trxId'] ?? $payload['trx_id'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? '');

  $status    = (string)($payload['transactionStatus'] ?? $payload['status'] ?? $payload['paymentStatus'] ?? '');
  $amount    = isset($payload['amount']) ? (float)$payload['amount'] : null;

  $payer     = (string)($payload['customerMsisdn'] ?? $payload['payerReference'] ?? $payload['payer'] ?? $payload['msisdn'] ?? '');
  $ref       = (string)($payload['merchantInvoiceNumber'] ?? $payload['merchant_invoice_number'] ?? $payload['invoiceNumber'] ?? $payload['orderId'] ?? $payload['reference'] ?? '');

  return [
    'event_type' => $eventType !== '' ? $eventType : null,
    'event_id'   => $eventId !== '' ? $eventId : null,
    'payment_id' => $paymentId !== '' ? $paymentId : null,
    'trx_id'     => $trxId !== '' ? $trxId : null,
    'status'     => $status !== '' ? $status : null,
    'amount'     => $amount !== null && $amount > 0 ? $amount : null,
    'payer'      => $payer !== '' ? $payer : null,
    'ref'        => $ref !== '' ? $ref : null,
  ];
}

/**
 * Store an RTN event in bkash_rtn_events.
 * Returns: ['inserted'=>bool,'duplicate'=>bool,'id'=>int|null,'error'=>string|null]
 */
function bkash_rtn_store_event(string $rawBody, array $headers, array $payload): array {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if (!bkash_rtn_table_exists($pdo)) {
    return ['inserted' => false, 'duplicate' => false, 'id' => null, 'error' => 'bkash_rtn_events টেবিল নেই। tools/bkash_rtn_events.sql রান করুন।'];
  }

  $hash = hash('sha256', $rawBody);
  $f = bkash_rtn_extract_fields($payload);
  $remoteIp = $headers['remote_ip'] ?? null;

  try {
    $st = $pdo->prepare(
      "INSERT INTO bkash_rtn_events
        (event_hash, event_type, event_id, payment_id, trx_id, status, amount, payer_msisdn, merchant_invoice_number, raw_body, headers_json, remote_ip)
       VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $st->execute([
      $hash,
      $f['event_type'],
      $f['event_id'],
      $f['payment_id'],
      $f['trx_id'],
      $f['status'],
      $f['amount'],
      $f['payer'],
      $f['ref'],
      $rawBody,
      json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      $remoteIp,
    ]);
    return ['inserted' => true, 'duplicate' => false, 'id' => (int)$pdo->lastInsertId(), 'error' => null];
  } catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
      return ['inserted' => false, 'duplicate' => true, 'id' => null, 'error' => null];
    }
    return ['inserted' => false, 'duplicate' => false, 'id' => null, 'error' => $e->getMessage()];
  }
}

