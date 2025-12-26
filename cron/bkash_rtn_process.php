<?php
// /cron/bkash_rtn_process.php
// Purpose: Process bKash RTN events from bkash_rtn_events (separate from PGW/sms_inbox),
//          and apply payments to invoices in real time/near real time.
// Output: Bengali.

declare(strict_types=1);
date_default_timezone_set('Asia/Dhaka');

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/bkash_tokenized.php'; // used only if event has payment_id but missing trx_id

function col_exists(PDO $pdo, string $tbl, string $col): bool {
  try {
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$db, $tbl, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function table_columns(PDO $pdo, string $tbl): array {
  try {
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $tbl]);
    return array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) { return []; }
}
function normalize_msisdn($s): string {
  $s = preg_replace('/\D+/', '', (string)$s);
  if (strlen($s) === 13 && str_starts_with($s, '880')) $s = '0' . substr($s, 3);
  if (strlen($s) === 14 && str_starts_with($s, '0880')) $s = '0' . substr($s, 4);
  return $s;
}
function resolve_client_from_ref(PDO $pdo, ?string $ref): ?int {
  $ref = trim((string)$ref);
  if ($ref === '') return null;

  // Prefer invoice_number if present
  if (col_exists($pdo, 'invoices', 'invoice_number') && col_exists($pdo, 'invoices', 'client_id')) {
    $st = $pdo->prepare("SELECT client_id FROM invoices WHERE invoice_number=? LIMIT 1");
    $st->execute([$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }

  // If numeric, try invoice id
  if (ctype_digit($ref) && col_exists($pdo, 'invoices', 'client_id')) {
    $st = $pdo->prepare("SELECT client_id FROM invoices WHERE id=? LIMIT 1");
    $st->execute([(int)$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }

  // Match common client identifiers
  foreach (['client_code','pppoe_id','pppoeid','pppoe','username','customer_code','clientid'] as $col) {
    if (!col_exists($pdo, 'clients', $col)) continue;
    $st = $pdo->prepare("SELECT id FROM clients WHERE `$col`=? LIMIT 1");
    $st->execute([$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }

  return null;
}
function find_client_by_msisdn(PDO $pdo, ?string $msisdn): ?int {
  $msisdn = normalize_msisdn($msisdn ?? '');
  if ($msisdn === '') return null;
  $cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
  $phoneCols = array_values(array_intersect($cols, ['bkash_msisdn','mobile','phone','contact','contact_no','phone1','phone2','owner_phone']));
  foreach ($phoneCols as $col) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE `$col`=? LIMIT 1");
    $st->execute([$msisdn]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }
  return null;
}

function settle_payment(PDO $pdo, int $client_id, float $amount, string $trx_id, string $method, string $note, ?string $paid_at): array {
  $applied = 0.0; $appliedInvoices = [];

  $orderCol = null;
  foreach (['billing_month','invoice_date','due_date'] as $col) {
    if (col_exists($pdo, 'invoices', $col)) { $orderCol = $col; break; }
  }
  if (!$orderCol) $orderCol = 'id';

  $where = "i.client_id=?";
  if (col_exists($pdo, 'invoices', 'is_void')) $where .= " AND COALESCE(i.is_void,0)=0";
  if (col_exists($pdo, 'invoices', 'status')) $where .= " AND i.status IN ('unpaid','partial','Unpaid','Partial','Due')";

  $sql = "SELECT i.id, i.client_id, i.payable, COALESCE(i.paid_amount,0) AS paid_amount
          FROM invoices i
          WHERE $where
          ORDER BY i.`$orderCol` ASC, i.id ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$client_id]);
  $invs = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$invs) return ['applied_amount'=>0.0,'applied_invoices'=>[],'remaining'=>$amount,'error'=>'এই ক্লায়েন্টের কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।'];

  $payCols = table_columns($pdo, 'payments');
  $invCols = table_columns($pdo, 'invoices');
  $paidAtVal = $paid_at ?: date('Y-m-d H:i:s');
  $invoiceApplyCount = 0;

  foreach ($invs as $inv) {
    if ($amount <= 0) break;
    $invoiceId = (int)$inv['id'];
    $due = (float)$inv['payable'] - (float)$inv['paid_amount'];
    if ($due <= 0) continue;
    $pay = min($due, $amount);
    $invoiceApplyCount++;

    $txnUnique = ($invoiceApplyCount === 1 && $pay >= $amount) ? $trx_id : ($trx_id . '-I' . $invoiceId);

    $fields = [];
    $placeholders = [];
    $values = [];

    if (in_array('invoice_id', $payCols, true)) { $fields[]='`invoice_id`'; $placeholders[]='?'; $values[]=$invoiceId; }
    if (in_array('client_id', $payCols, true))  { $fields[]='`client_id`';  $placeholders[]='?'; $values[]=$client_id; }
    if (in_array('bill_id', $payCols, true))    { $fields[]='`bill_id`';    $placeholders[]='?'; $values[]=$invoiceId; }
    $fields[]='`amount`'; $placeholders[]='?'; $values[]=$pay;
    if (in_array('discount', $payCols, true))   { $fields[]='`discount`'; $placeholders[]='?'; $values[]=0; }
    if (in_array('payment_date', $payCols, true)) { $fields[]='`payment_date`'; $placeholders[]='?'; $values[]=$paidAtVal; }
    if (in_array('method', $payCols, true))     { $fields[]='`method`'; $placeholders[]='?'; $values[]=$method; }
    if (in_array('txn_id', $payCols, true))     { $fields[]='`txn_id`'; $placeholders[]='?'; $values[]=$txnUnique; }
    if (in_array('transaction_id', $payCols, true)) { $fields[]='`transaction_id`'; $placeholders[]='?'; $values[]=$trx_id; }
    if (in_array('paid_at', $payCols, true))    { $fields[]='`paid_at`'; $placeholders[]='?'; $values[]=$paidAtVal; }
    if (in_array('note', $payCols, true))       { $fields[]='`note`'; $placeholders[]='?'; $values[]=$note; }
    elseif (in_array('notes', $payCols, true))  { $fields[]='`notes`'; $placeholders[]='?'; $values[]=$note; }

    $sqlIns = "INSERT INTO payments (".implode(',', $fields).") VALUES (".implode(',', $placeholders).")";
    $pdo->prepare($sqlIns)->execute($values);

    $newPaid = (float)$inv['paid_amount'] + $pay;
    $updSets = [];
    $updVals = [];
    if (in_array('paid_amount', $invCols, true)) { $updSets[]='paid_amount=?'; $updVals[]=$newPaid; }
    if (in_array('status', $invCols, true)) { $updSets[]='status=?'; $updVals[]=(($newPaid >= (float)$inv['payable']) ? 'paid' : 'partial'); }
    if (in_array('paid_at', $invCols, true)) { $updSets[]='paid_at=?'; $updVals[]=$paidAtVal; }
    if (in_array('method', $invCols, true))  { $updSets[]='method=?';  $updVals[]=$method; }
    if ($updSets) {
      $updVals[] = $invoiceId;
      $pdo->prepare("UPDATE invoices SET ".implode(',', $updSets)." WHERE id=?")->execute($updVals);
    }

    $applied += $pay;
    $amount  -= $pay;
    $appliedInvoices[] = $invoiceId;
  }

  return ['applied_amount'=>$applied,'applied_invoices'=>$appliedInvoices,'remaining'=>$amount];
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure table exists
$stt = $pdo->prepare("SHOW TABLES LIKE 'bkash_rtn_events'");
$stt->execute();
if (!$stt->fetchColumn()) {
  echo "bkash_rtn_events টেবিল নেই। tools/bkash_rtn_events.sql রান করুন।\n";
  exit(1);
}

$rows = $pdo->query("SELECT * FROM bkash_rtn_events WHERE processed=0 ORDER BY id ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
  echo "কোনো পেন্ডিং RTN ইভেন্ট নেই।\n";
  exit;
}

$ok = 0; $skip = 0; $fail = 0;

foreach ($rows as $r) {
  $id = (int)$r['id'];
  $pdo->prepare("UPDATE bkash_rtn_events SET process_attempts=process_attempts+1 WHERE id=?")->execute([$id]);

  $trx = trim((string)($r['trx_id'] ?? ''));
  $payId = trim((string)($r['payment_id'] ?? ''));
  $status = strtoupper(trim((string)($r['status'] ?? '')));
  $amt = (float)($r['amount'] ?? 0);
  $ref = trim((string)($r['merchant_invoice_number'] ?? ''));
  $payer = trim((string)($r['payer_msisdn'] ?? ''));

  // If success but missing trx/amount, try execute by paymentID
  $okStatuses = ['COMPLETED','SUCCESS','CAPTURED','CAPTURED_COMPLETED','INITIATED']; // INITIATED will be ignored for apply
  if ($status === '' && $r['raw_body']) {
    $payload = json_decode((string)$r['raw_body'], true);
    if (is_array($payload)) $status = strtoupper((string)($payload['transactionStatus'] ?? $payload['status'] ?? ''));
  }

  if ($status === 'INITIATED') {
    $pdo->prepare("UPDATE bkash_rtn_events SET last_error=?, processed=0 WHERE id=?")->execute(['স্ট্যাটাস Initiated; পরে আবার চেষ্টা হবে।', $id]);
    $skip++;
    continue;
  }

  if (!in_array($status, ['COMPLETED','SUCCESS','CAPTURED','CAPTURED_COMPLETED'], true)) {
    $pdo->prepare("UPDATE bkash_rtn_events SET processed=1, processed_at=NOW(), last_error=? WHERE id=?")->execute(["স্ট্যাটাস '$status' হওয়ায় স্কিপ।", $id]);
    $skip++;
    continue;
  }

  if (($trx === '' || $amt <= 0) && $payId !== '') {
    try {
      $exec = bkash_tz_execute_payment($payId);
      $trx = trim((string)($exec['trxID'] ?? $exec['trxId'] ?? $trx));
      $amt = (float)($exec['amount'] ?? $amt);
      $ref = trim((string)($exec['merchantInvoiceNumber'] ?? $ref));
      $payer = trim((string)($exec['customerMsisdn'] ?? $exec['payerReference'] ?? $payer));
      $pdo->prepare("UPDATE bkash_rtn_events SET trx_id=?, amount=?, merchant_invoice_number=?, payer_msisdn=? WHERE id=?")
          ->execute([$trx ?: null, ($amt>0?$amt:null), $ref ?: null, $payer ?: null, $id]);
    } catch (Throwable $e) {
      $pdo->prepare("UPDATE bkash_rtn_events SET last_error=? WHERE id=?")->execute(['Execute ব্যর্থ: '.$e->getMessage(), $id]);
      $fail++;
      continue;
    }
  }

  if ($trx === '' || $amt <= 0) {
    $pdo->prepare("UPDATE bkash_rtn_events SET last_error=? WHERE id=?")->execute(['trx_id/amount পাওয়া যায়নি।', $id]);
    $fail++;
    continue;
  }

  // Dedupe: if already applied in payments (from PGW callback or older flow)
  $payCols = table_columns($pdo, 'payments');
  $dupSql = null;
  $dupArgs = [];
  if (in_array('transaction_id', $payCols, true)) {
    $dupSql = "SELECT id FROM payments WHERE transaction_id=? LIMIT 1";
    $dupArgs = [$trx];
  } elseif (in_array('txn_id', $payCols, true)) {
    $dupSql = "SELECT id FROM payments WHERE txn_id=? LIMIT 1";
    $dupArgs = [$trx];
  }
  if ($dupSql) {
    $stDup = $pdo->prepare($dupSql);
    $stDup->execute($dupArgs);
    if ($stDup->fetchColumn()) {
      $pdo->prepare("UPDATE bkash_rtn_events SET processed=1, processed_at=NOW(), last_error=? WHERE id=?")
          ->execute(['আগেই পেমেন্ট অ্যাপ্লাই হয়েছে (ডুপ্লিকেট)।', $id]);
      $skip++;
      continue;
    }
  }

  $clientId = resolve_client_from_ref($pdo, $ref);
  if (!$clientId) $clientId = find_client_by_msisdn($pdo, $payer);
  if (!$clientId) {
    $pdo->prepare("UPDATE bkash_rtn_events SET last_error=? WHERE id=?")->execute(['ক্লায়েন্ট শনাক্ত করা যায়নি।', $id]);
    $fail++;
    continue;
  }

  try {
    $pdo->beginTransaction();
    $note = sprintf('bKash RTN webhook; event_id=%d; ref=%s; payId=%s', $id, $ref, $payId);
    $res = settle_payment($pdo, (int)$clientId, (float)$amt, $trx, 'bkash', $note, date('Y-m-d H:i:s'));
    if (($res['applied_amount'] ?? 0) <= 0) {
      $pdo->rollBack();
      $pdo->prepare("UPDATE bkash_rtn_events SET last_error=? WHERE id=?")->execute([$res['error'] ?? 'কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।', $id]);
      $fail++;
      echo "❌ event_id={$id} ব্যর্থ: কোনো বকেয়া ইনভয়েস নেই\n";
      continue;
    }
    $pdo->prepare("UPDATE bkash_rtn_events SET processed=1, processed_at=NOW(), applied_client_id=?, applied_amount=?, last_error=NULL WHERE id=?")
        ->execute([(int)$clientId, (float)$res['applied_amount'], $id]);
    $pdo->commit();
    $ok++;
    echo "✅ event_id={$id} → client={$clientId} amount={$amt} trx={$trx}\n";
  } catch (Throwable $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    $pdo->prepare("UPDATE bkash_rtn_events SET last_error=? WHERE id=?")->execute(['অটো অ্যাপ্লাই ব্যর্থ: '.$e->getMessage(), $id]);
    $fail++;
    echo "❌ event_id={$id} ব্যর্থ: ".$e->getMessage()."\n";
  }
}

echo "সম্পন্ন। সফল: {$ok}, স্কিপ: {$skip}, ব্যর্থ: {$fail}\n";

