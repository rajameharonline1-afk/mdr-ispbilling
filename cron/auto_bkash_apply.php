<?php
// /cron/auto_bkash_apply.php
// উদ্দেশ্য: bKash webhook থেকে আসা sms_inbox (gateway=bkash_webhook) অটো-ম্যাচ করে payments-এ অ্যাপ্লাই করা।
// ভাষা: সব আউটপুট/ত্রুটি বাংলা।

declare(strict_types=1);
date_default_timezone_set('Asia/Dhaka');

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/db.php';

/* ---------------- helper functions ---------------- */
function tbl_exists(PDO $pdo, string $t): bool {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $t]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function col_exists(PDO $pdo, string $tbl, string $col): bool {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$db, $tbl, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function table_columns(PDO $pdo, string $tbl): array {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $tbl]);
    return array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) { return []; }
}
function normalize_msisdn($s) {
  $s = preg_replace('/\D+/', '', (string)$s);
  if (strlen($s) === 13 && substr($s, 0, 3) === '880') $s = '0' . substr($s, 3);
  if (strlen($s) === 14 && substr($s, 0, 4) === '0880') $s = '0' . substr($s, 4);
  if (strlen($s) === 11 && substr($s, 0, 2) === '01') return $s;
  return $s;
}
function resolve_client_from_ref(PDO $pdo, ?string $ref): ?int {
  $ref = trim((string)$ref);
  if ($ref === '') return null;

  // Try invoice_number (নতুন স্কিমা)
  if (col_exists($pdo, 'invoices', 'invoice_number')) {
    $st = $pdo->prepare("SELECT client_id FROM invoices WHERE invoice_number=? LIMIT 1");
    $st->execute([$ref]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      if (!empty($row['client_id'])) return (int)$row['client_id'];
    }
  }
  // Legacy invoice_no removed to avoid missing-column errors on current schema

  if (ctype_digit($ref)) {
    try {
      $st = $pdo->prepare("SELECT client_id FROM invoices WHERE id=? LIMIT 1");
      $st->execute([(int)$ref]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['client_id'])) return (int)$row['client_id'];
      }
    } catch (Throwable $e) {}
  }

  foreach (['client_code','pppoe_id','pppoeid','pppoe','code','username','pppoe_username','customer_code','clientid'] as $col) {
    try {
      if (!col_exists($pdo, 'clients', $col)) continue;
      $st = $pdo->prepare("SELECT id FROM clients WHERE `$col`=? LIMIT 1");
      $st->execute([$ref]);
      if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['id'])) return (int)$row['id'];
      }
    } catch (Throwable $e) {}
  }
  return null;
}
// রেফারেন্স/পে-লোড থেকে সম্ভাব্য ক্লায়েন্ট কোড/PPPoE বের করুন
function extract_ref_candidates(array $row): array {
  $cands = [];
  $push = function($v) use (&$cands) {
    $v = trim((string)$v);
    if ($v === '') return;
    $cands[] = $v;
    // regex দিয়ে টোকেন আলাদা করা (R123..., CID123..., INV-..., সাধারণ আলফানিউমেরিক)
    if (preg_match_all('/\b(R[0-9]{5,12}|CID[0-9]{3,10}|INV-[A-Za-z0-9-]{3,30}|[A-Za-z][A-Za-z0-9._-]{3,20})\b/', $v, $m)) {
      foreach ($m[1] as $tok) $cands[] = $tok;
    }
  };

  if (!empty($row['ref_code'])) $push($row['ref_code']);
  if (!empty($row['trx_id']))   $push($row['trx_id']); // কখনও trx-এ রেফারেন্স থাকে

  if (!empty($row['meta_json'])) {
    $meta = json_decode((string)$row['meta_json'], true);
    if (is_array($meta) && isset($meta['payload']) && is_array($meta['payload'])) {
      $p = $meta['payload'];
      foreach (['merchantInvoiceNumber','orderId','reference','payerReference','customerMsisdn','additionalInfo','remarks','note'] as $k) {
        if (isset($p[$k])) $push($p[$k]);
      }
    }
  }

  $uniq = [];
  foreach ($cands as $c) {
    if (!in_array($c, $uniq, true)) $uniq[] = $c;
  }
  return $uniq;
}
function find_client_by_msisdn(PDO $pdo, ?string $msisdn): ?int {
  $msisdn = normalize_msisdn($msisdn ?? '');
  if ($msisdn === '') return null;
  $cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
  $phoneCols = array_values(array_intersect($cols, ['bkash_msisdn','phone','mobile','contact','contact_no','phone1','phone2','owner_phone','owner_mobile']));
  foreach ($phoneCols as $pc) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE `$pc`=? LIMIT 1");
    $st->execute([$msisdn]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }
  return null;
}
function resolve_collector_user_id(PDO $pdo, ?string $msisdn_to): int {
  if (!$msisdn_to) return 0;
  $msisdn_to = normalize_msisdn($msisdn_to);
  if (tbl_exists($pdo, 'wallet_accounts')) {
    $st = $pdo->prepare("SELECT user_id FROM wallet_accounts WHERE msisdn=? LIMIT 1");
    $st->execute([$msisdn_to]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['user_id'])) return (int)$r['user_id'];
  }
  try {
    if (col_exists($pdo, 'users', 'phone')) {
      $st = $pdo->prepare("SELECT id FROM users WHERE phone=? LIMIT 1");
      $st->execute([$msisdn_to]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      if ($r && !empty($r['id'])) return (int)$r['id'];
    }
  } catch (Throwable $e) {}
  return 0;
}
function wallet_credit(PDO $pdo, int $user_id, float $amount, array $meta): void {
  if ($user_id <= 0 || $amount <= 0) return;
  $tbl = 'wallet_transactions';
  if (!tbl_exists($pdo, $tbl)) return;

  $cols = table_columns($pdo, $tbl);
  if (!$cols) return;

  $ownerCol = null;
  foreach (['user_id','employee_id','emp_id','owner_id','collector_id'] as $c) {
    if (in_array($c, $cols, true)) { $ownerCol = $c; break; }
  }
  if (!$ownerCol) return;

  $typeCol = in_array('type', $cols, true) ? 'type' : (in_array('txn_type', $cols, true) ? 'txn_type' : null);
  $amtCol  = in_array('amount', $cols, true) ? 'amount' : null;
  if (!$amtCol) return;

  $srcCol  = in_array('source', $cols, true) ? 'source' : (in_array('channel', $cols, true) ? 'channel' : null);
  $refCol  = in_array('reference', $cols, true) ? 'reference' : (in_array('ref', $cols, true) ? 'ref' : null);
  $metaCol = in_array('meta_json', $cols, true) ? 'meta_json' : (in_array('meta', $cols, true) ? 'meta' : null);
  $created = in_array('created_at', $cols, true) ? 'created_at' : null;

  $fields = [];
  $placeholders = [];
  $values = [];

  $fields[] = "`$ownerCol`"; $placeholders[] = '?'; $values[] = $user_id;
  $fields[] = "`$amtCol`";   $placeholders[] = '?'; $values[] = $amount;
  if ($typeCol) { $fields[] = "`$typeCol`"; $placeholders[] = '?'; $values[] = 'credit'; }
  if ($srcCol)  { $fields[] = "`$srcCol`";  $placeholders[] = '?'; $values[] = 'bkash_webhook'; }
  if ($refCol)  { $fields[] = "`$refCol`";  $placeholders[] = '?'; $values[] = $meta['trx_id'] ?? null; }
  if ($metaCol) { $fields[] = "`$metaCol`"; $placeholders[] = '?'; $values[] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
  if ($created) { $fields[] = "`$created`"; $placeholders[] = 'NOW()'; }

  $sql = "INSERT INTO `$tbl` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
  $st  = $pdo->prepare($sql);
  $st->execute($values);
}
function settle_payment(PDO $pdo, int $client_id, float $amount, string $trx_id, string $method, string $note, ?string $paid_at): array {
  $applied = 0.0; $appliedInvoices = [];

  // invoices টেবিলে year/month কলাম নেই, তাই billing_month/invoice_date দিয়ে sort
  $orderCol = null;
  foreach (['billing_month','invoice_date','due_date'] as $col) {
    try {
      $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME=? LIMIT 1");
      $st->execute([$col]);
      if ($st->fetchColumn()) { $orderCol = $col; break; }
    } catch (Throwable $e) {}
  }
  if (!$orderCol) $orderCol = 'id';

  $where = "i.client_id=?";
  if (col_exists($pdo, 'invoices', 'is_void')) $where .= " AND COALESCE(i.is_void,0)=0";
  if (col_exists($pdo, 'invoices', 'status')) $where .= " AND i.status IN ('unpaid','partial','Unpaid','Partial','Due')";

  $sql = "SELECT i.id, i.client_id, i.payable,
                 COALESCE(i.paid_amount,0) AS paid_amount
          FROM invoices i
          WHERE $where
          ORDER BY i.`$orderCol` ASC, i.id ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$client_id]);
  $invs = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$invs) {
    return ['applied_amount' => 0.0, 'applied_invoices' => [], 'remaining' => $amount, 'error' => 'এই ক্লায়েন্টের কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।'];
  }

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

    // txn_id ইউনিক রাখতে প্রয়োজনে suffix যোগ
    $txnUnique = ($invoiceApplyCount === 1 && $pay >= $amount) ? $trx_id : ($trx_id . '-I' . $invoiceId);

    // Build dynamic INSERT for payments (current schema has many NOT NULL columns)
    $fields = [];
    $placeholders = [];
    $values = [];

    // Required/typical columns
    if (in_array('invoice_id', $payCols, true)) { $fields[]='`invoice_id`'; $placeholders[]='?'; $values[]=$invoiceId; }
    if (in_array('client_id', $payCols, true))  { $fields[]='`client_id`';  $placeholders[]='?'; $values[]=$client_id; }
    if (in_array('bill_id', $payCols, true))    { $fields[]='`bill_id`';    $placeholders[]='?'; $values[]=$invoiceId; }
    $fields[]='`amount`';        $placeholders[]='?'; $values[]=$pay;
    if (in_array('discount', $payCols, true))   { $fields[]='`discount`';   $placeholders[]='?'; $values[]=0; }
    if (in_array('payment_date', $payCols, true)) { $fields[]='`payment_date`'; $placeholders[]='?'; $values[]=$paidAtVal; }
    if (in_array('method', $payCols, true))     { $fields[]='`method`';     $placeholders[]='?'; $values[]=$method; }
    if (in_array('txn_id', $payCols, true))     { $fields[]='`txn_id`';     $placeholders[]='?'; $values[]=$txnUnique; }
    if (in_array('transaction_id', $payCols, true)) { $fields[]='`transaction_id`'; $placeholders[]='?'; $values[]=$trx_id; }
    if (in_array('paid_at', $payCols, true))    { $fields[]='`paid_at`';    $placeholders[]='?'; $values[]=$paidAtVal; }
    if (in_array('note', $payCols, true))       { $fields[]='`note`';       $placeholders[]='?'; $values[]=$note; }
    elseif (in_array('notes', $payCols, true))  { $fields[]='`notes`';      $placeholders[]='?'; $values[]=$note; }
    if (in_array('received_ip', $payCols, true) && !empty($GLOBALS['__BKASH_REMOTE_IP'])) {
      $fields[]='`received_ip`'; $placeholders[]='?'; $values[]=(string)$GLOBALS['__BKASH_REMOTE_IP'];
    }

    $sqlIns = "INSERT INTO payments (".implode(',', $fields).") VALUES (".implode(',', $placeholders).")";
    $pdo->prepare($sqlIns)->execute($values);

    // Update invoice paid_amount + status
    $newPaid = (float)$inv['paid_amount'] + $pay;
    $newStatus = null;
    if (in_array('status', $invCols, true)) {
      $newStatus = ($newPaid >= (float)$inv['payable']) ? 'paid' : 'partial';
    }
    $updSets = [];
    $updVals = [];
    if (in_array('paid_amount', $invCols, true)) { $updSets[]='paid_amount=?'; $updVals[]=$newPaid; }
    if ($newStatus !== null) { $updSets[]='status=?'; $updVals[]=$newStatus; }
    if (in_array('paid_at', $invCols, true)) { $updSets[]='paid_at=?'; $updVals[]=$paidAtVal; }
    if (in_array('method', $invCols, true))  { $updSets[]='method=?';  $updVals[]=$method; }
    if ($updSets) {
      $updVals[]=$invoiceId;
      $pdo->prepare("UPDATE invoices SET ".implode(',', $updSets)." WHERE id=?")->execute($updVals);
    }

    // update local inv paid_amount for possible multiple allocations (not strictly needed but keeps due correct)
    $inv['paid_amount'] = $newPaid;

    $applied += $pay; $amount -= $pay; $appliedInvoices[] = $invoiceId;
  }

  return ['applied_amount' => $applied, 'applied_invoices' => $appliedInvoices, 'remaining' => $amount];
}

/* ---------------- core logic ---------------- */
$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $pdo->query("SELECT * FROM sms_inbox WHERE gateway='bkash_webhook' AND processed=0 AND (error_msg IS NULL OR error_msg='') ORDER BY id ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
  echo "কোনো পেন্ডিং ওয়েবহুক নেই।\n";
  exit;
}

$ok = 0; $fail = 0;
foreach ($rows as $r) {
  $id    = (int)$r['id'];
  $trx   = trim((string)($r['trx_id'] ?? ''));
  $amt   = (float)($r['amount'] ?? 0);
  $ref   = trim((string)($r['ref_code'] ?? ''));
  $msisdn = $r['msisdn_from'] ?? $r['sender_number'] ?? '';
  $status = '';
  if (!empty($r['meta_json'])) {
    $meta = json_decode((string)$r['meta_json'], true);
    if (is_array($meta)) {
      if (!empty($meta['payload']['transactionStatus'])) {
        $status = strtoupper((string)$meta['payload']['transactionStatus']);
      }
    }
  }
  if ($status === '') $status = strtoupper((string)($r['gateway'] ?? ''));

  $paid_at = $r['received_at'] ?? date('Y-m-d H:i:s');
  // Remote IP from webhook meta (if available)
  $GLOBALS['__BKASH_REMOTE_IP'] = null;
  if (!empty($r['meta_json'])) {
    $meta = json_decode((string)$r['meta_json'], true);
    if (is_array($meta) && !empty($meta['headers']['remote_ip'])) {
      $GLOBALS['__BKASH_REMOTE_IP'] = (string)$meta['headers']['remote_ip'];
    }
  }

  // Basic validations
  if ($trx === '') {
    $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute(['TrxID পাওয়া যায়নি (অটো প্রোসেস বন্ধ)।', $id]);
    $fail++; continue;
  }
  if ($amt <= 0) {
    $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute(['Amount সঠিক নয় (০ এর বেশি হতে হবে)।', $id]);
    $fail++; continue;
  }
  $okStatuses = ['COMPLETED','SUCCESS','CAPTURED','CAPTURED_COMPLETED'];
  if ($status && !in_array($status, $okStatuses, true)) {
    $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute(["স্ট্যাটাস '$status' হওয়ায় পেমেন্ট অ্যাপ্লাই হয়নি।", $id]);
    $fail++; continue;
  }

  // Duplicate guard (payments)
  $dup = $pdo->prepare("SELECT id FROM payments WHERE method='bkash' AND txn_id=? LIMIT 1");
  $dup->execute([$trx]);
  if ($dup->fetchColumn()) {
    $pdo->prepare("UPDATE sms_inbox SET processed=1,error_msg='ডুপ্লিকেট ট্রানজেকশন; আগেই প্রোসেস হয়েছে।' WHERE id=?")->execute([$id]);
    $ok++; continue;
  }

  // Resolve client: রেফারেন্স/পে-লোড প্যাটার্ন + msisdn
  $clientId = null;
  $candidates = extract_ref_candidates($r);
  foreach ($candidates as $cand) {
    $clientId = resolve_client_from_ref($pdo, $cand);
    if ($clientId) break;
  }
  if (!$clientId) $clientId = find_client_by_msisdn($pdo, $msisdn);
  if (!$clientId) {
    $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute(['ক্লায়েন্ট শনাক্ত করা যায়নি।', $id]);
    $fail++; continue;
  }

  try {
    $pdo->beginTransaction();

    $note = sprintf('bKash webhook auto; inbox_id=%d; ref=%s', $id, $ref);
    $res  = settle_payment($pdo, $clientId, $amt, $trx, 'bkash', $note, $paid_at);
    if (($res['applied_amount'] ?? 0) <= 0) {
      $pdo->rollBack();
      $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute([$res['error'] ?? 'কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।', $id]);
      $fail++;
      echo "❌ inbox_id={$id} ব্যর্থ: কোনো বকেয়া ইনভয়েস পাওয়া যায়নি\n";
      continue;
    }

    $collectorId = resolve_collector_user_id($pdo, $r['msisdn_to'] ?? null);
    wallet_credit($pdo, $collectorId, (float)$res['applied_amount'], [
      'trx_id'    => $trx,
      'client_id' => $clientId,
      'invoices'  => $res['applied_invoices'],
      'msisdn_to' => $r['msisdn_to'] ?? null,
      'sender'    => $msisdn,
      'ref'       => $ref,
      'src'       => 'bkash_webhook_auto',
    ]);

    $pdo->prepare("UPDATE sms_inbox SET processed=1,error_msg=NULL WHERE id=?")->execute([$id]);
    $pdo->commit();
    $ok++;
    echo "✅ inbox_id={$id} → client={$clientId} amount={$amt} trx={$trx}\n";
  } catch (Throwable $e) {
    $pdo->rollBack();
    $pdo->prepare("UPDATE sms_inbox SET error_msg=? WHERE id=?")->execute(['অটো অ্যাপ্লাই ব্যর্থ: '.$e->getMessage(), $id]);
    $fail++;
    echo "❌ inbox_id={$id} ব্যর্থ: ".$e->getMessage()."\n";
  }
}

echo "সম্পন্ন। সফল: {$ok}, ব্যর্থ/স্কিপ: {$fail}\n";
