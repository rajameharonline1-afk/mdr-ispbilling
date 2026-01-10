<?php
// /api/bkash_webhook.php
// Purpose: Receive bKash webhook notifications, validate, log, match client by reference,
//          insert payment, activate account, and fall back to a pending table when unmatched.
// Security: shared secret token via header/query + optional HMAC signature + IP allowlist.

declare(strict_types=1);

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/settings_store.php';
@require_once $ROOT . '/app/routeros_api.class.php';

$LOG_FILE = $ROOT . '/storage/logs/bkash_webhook.log';

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
function log_webhook(string $raw, array $context = []): void {
  global $LOG_FILE;
  static $dirReady = null;
  $dir = dirname($LOG_FILE);
  if ($dirReady === null) {
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $dirReady = is_dir($dir) && is_writable($dir);
  }
  if (!$dirReady) return;
  $rawShort = strlen($raw) > 3000 ? (substr($raw, 0, 3000) . '...(trimmed)') : $raw;
  $line = '[' . date('c') . '] ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' raw=' . $rawShort . PHP_EOL;
  file_put_contents($LOG_FILE, $line, FILE_APPEND);
}
function log_router(string $msg, array $ctx = []): void {
  global $ROOT;
  $file = $ROOT . '/storage/logs/router_activation.log';
  $dir  = dirname($file);
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $line = '[' . date('c') . '] ' . $msg;
  if ($ctx) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
}
function normalize_msisdn($s): string {
  $s = preg_replace('/\D+/', '', (string)$s);
  if (strlen($s) === 13 && substr($s, 0, 3) === '880') $s = '0' . substr($s, 3);
  if (strlen($s) === 14 && substr($s, 0, 4) === '0880') $s = '0' . substr($s, 4);
  if (strlen($s) === 11 && substr($s, 0, 2) === '01') return $s;
  return $s;
}
function col_exists(PDO $pdo, string $tbl, string $col): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$tbl, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function table_columns(PDO $pdo, string $tbl): array {
  try {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$tbl]);
    return array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) { return []; }
}
function extract_ref_candidates(string $ref, string $trx, array $payload): array {
  $cands = [];
  $push = function($v) use (&$cands) {
    $v = trim((string)$v);
    if ($v === '') return;
    $cands[] = $v;
    if (preg_match_all('/\b(R[0-9]{5,12}|CID[0-9]{3,10}|INV-[A-Za-z0-9-]{3,30}|[A-Za-z][A-Za-z0-9._-]{3,20})\b/', $v, $m)) {
      foreach ($m[1] as $tok) $cands[] = $tok;
    }
  };
  $push($ref); $push($trx);
  foreach (['reference','merchantInvoiceNumber','orderId','payerReference','customerMsisdn','remarks','additionalInfo','invoiceNumber'] as $k) {
    if (isset($payload[$k])) $push($payload[$k]);
  }
  $uniq = [];
  foreach ($cands as $c) if (!in_array($c, $uniq, true)) $uniq[] = $c;
  return $uniq;
}
function resolve_client_from_ref(PDO $pdo, string $ref): ?int {
  $ref = trim($ref);
  if ($ref === '') return null;
  // numeric → id then client_code
  if (ctype_digit($ref)) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
    if (col_exists($pdo, 'clients', 'client_code')) {
      $st = $pdo->prepare("SELECT id FROM clients WHERE client_code=? LIMIT 1");
      $st->execute([$ref]);
      $cid = (int)($st->fetchColumn() ?: 0);
      if ($cid > 0) return $cid;
    }
  }
  foreach (['client_code','pppoe_id','username','clientid','customer_code'] as $col) {
    if (!col_exists($pdo, 'clients', $col)) continue;
    $st = $pdo->prepare("SELECT id FROM clients WHERE `$col`=? LIMIT 1");
    $st->execute([$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }
  // fallback: strip non-digits (REF1025 → 1025)
  $digits = preg_replace('/\D+/', '', $ref);
  if ($digits !== '' && ctype_digit($digits)) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$digits]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
    if (col_exists($pdo, 'clients', 'client_code')) {
      $st = $pdo->prepare("SELECT id FROM clients WHERE client_code=? LIMIT 1");
      $st->execute([$digits]);
      $cid = (int)($st->fetchColumn() ?: 0);
      if ($cid > 0) return $cid;
    }
  }
  return null;
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
          FROM invoices i WHERE $where ORDER BY i.`$orderCol` ASC, i.id ASC";
  $st = $pdo->prepare($sql);
  $st->execute([$client_id]);
  $invs = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$invs) {
    return ['applied_amount' => 0.0, 'applied_invoices' => [], 'remaining' => $amount, 'error' => 'কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।'];
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
    $txnUnique = ($invoiceApplyCount === 1 && $pay >= $amount) ? $trx_id : ($trx_id . '-I' . $invoiceId);

    $fields = []; $placeholders = []; $values = [];
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

    $sqlIns = "INSERT INTO payments (".implode(',', $fields).") VALUES (".implode(',', $placeholders).")";
    $pdo->prepare($sqlIns)->execute($values);

    $newPaid = (float)$inv['paid_amount'] + $pay;
    $newStatus = null;
    if (in_array('status', $invCols, true)) {
      $newStatus = ($newPaid >= (float)$inv['payable']) ? 'paid' : 'partial';
    }
    $updSets = []; $updVals = [];
    if (in_array('paid_amount', $invCols, true)) { $updSets[]='paid_amount=?'; $updVals[]=$newPaid; }
    if ($newStatus !== null) { $updSets[]='status=?'; $updVals[]=$newStatus; }
    if (in_array('paid_at', $invCols, true)) { $updSets[]='paid_at=?'; $updVals[]=$paidAtVal; }
    if (in_array('method', $invCols, true))  { $updSets[]='method=?';  $updVals[]=$method; }
    if ($updSets) {
      $updVals[]=$invoiceId;
      $pdo->prepare("UPDATE invoices SET ".implode(',', $updSets)." WHERE id=?")->execute($updVals);
    }

    $inv['paid_amount'] = $newPaid;
    $applied += $pay; $amount -= $pay; $appliedInvoices[] = $invoiceId;
  }

  return ['applied_amount' => $applied, 'applied_invoices' => $appliedInvoices, 'remaining' => $amount];
}
function compute_new_expiry(PDO $pdo, int $clientId, ?int $days = null): string {
  $st = $pdo->prepare("SELECT expiry_date, package_id FROM clients WHERE id=? LIMIT 1");
  $st->execute([$clientId]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $current = $row['expiry_date'] ?? null;
  if ($days === null) {
    $pkgDays = null;
    if (!empty($row['package_id'])) {
      $st2 = $pdo->prepare("SELECT duration_days, validity FROM packages WHERE id=? LIMIT 1");
      $st2->execute([(int)$row['package_id']]);
      if ($p = $st2->fetch(PDO::FETCH_ASSOC)) {
        $pkgDays = (int)($p['duration_days'] ?? 0);
        if ($pkgDays <= 0 && isset($p['validity'])) $pkgDays = (int)$p['validity'];
      }
    }
    $days = ($pkgDays && $pkgDays > 0) ? $pkgDays : 30;
  }
  $base = new DateTime();
  if ($current && strtotime((string)$current)) {
    try {
      $cur = new DateTime($current);
      if ($cur > new DateTime()) $base = $cur;
    } catch (Throwable $e) {}
  }
  $base->modify('+' . (int)$days . ' days');
  return $base->format('Y-m-d');
}
function update_client_after_payment(PDO $pdo, int $clientId, string $paidAt, string $expiry): void {
  $fields = ['status=?', 'expiry_date=?'];
  $params = ['active', $expiry];
  if (col_exists($pdo, 'clients', 'last_payment_date')) { $fields[]='last_payment_date=?'; $params[]=$paidAt; }
  if (col_exists($pdo, 'clients', 'payment_status'))    { $fields[]='payment_status=?';    $params[]='paid'; }
  if (col_exists($pdo, 'clients', 'updated_at'))        { $fields[]='updated_at=NOW()'; }
  $params[] = $clientId;
  $sql = "UPDATE clients SET ".implode(',', $fields)." WHERE id=?";
  $pdo->prepare($sql)->execute($params);
}
function activateOnRouter(int $client_id): void {
  global $ROOT;
  if (!class_exists('RouterosAPI')) { log_router('RouterOS class missing'); return; }

  $pdo = db();
  $st = $pdo->prepare("SELECT id, router_id, pppoe_id, connection_type, package_id FROM clients WHERE id=? LIMIT 1");
  $st->execute([$client_id]);
  $client = $st->fetch(PDO::FETCH_ASSOC);
  if (!$client) { log_router('client not found', ['client_id'=>$client_id]); return; }

  $routerId = (int)($client['router_id'] ?? 0);
  $pppoe    = trim((string)($client['pppoe_id'] ?? ''));
  if ($routerId <= 0 || $pppoe === '') { log_router('missing router/pppoe', ['client_id'=>$client_id]); return; }

  $rs = $pdo->prepare("SELECT * FROM routers WHERE id=? LIMIT 1");
  $rs->execute([$routerId]);
  $router = $rs->fetch(PDO::FETCH_ASSOC);
  if (!$router) { log_router('router not found', ['router_id'=>$routerId]); return; }

  $ip   = $router['ip_address'] ?? ($router['ip'] ?? '');
  $user = $router['username'] ?? ($router['user'] ?? '');
  $pass = $router['password'] ?? ($router['pass'] ?? '');
  $port = (int)($router['api_port'] ?? 8728);
  if (!$ip || !$user || !$pass) { log_router('router credentials missing', ['router_id'=>$routerId]); return; }

  $api = new RouterosAPI();
  $api->debug = false;
  $connected = false;
  try {
    if (property_exists($api, 'port')) $api->port = $port;
    $connected = $api->connect($ip, $user, $pass);
  } catch (Throwable $e) {}
  if (!$connected) {
    try { $connected = $api->connect($ip, $user, $pass, $port); } catch (Throwable $e) {}
  }
  if (!$connected) { log_router('router connect failed', ['router_id'=>$routerId]); return; }

  $ctype = strtolower((string)($client['connection_type'] ?? 'pppoe'));
  try {
    if ($ctype === 'hotspot') {
      $r = $api->comm('/ip/hotspot/user/print', ['?name'=>$pppoe]);
      if (isset($r[0]['.id'])) {
        $api->comm('/ip/hotspot/user/set', ['.id'=>$r[0]['.id'], 'disabled'=>'no']);
        log_router('hotspot enabled', ['client_id'=>$client_id,'router_id'=>$routerId,'user'=>$pppoe]);
      } else {
        log_router('hotspot user missing', ['client_id'=>$client_id,'router_id'=>$routerId,'user'=>$pppoe]);
      }
    } else {
      $r = $api->comm('/ppp/secret/print', ['?name'=>$pppoe]);
      if (isset($r[0]['.id'])) {
        $id = $r[0]['.id'];
        $api->comm('/ppp/secret/set', ['.id'=>$id, 'disabled'=>'no']);
        $profile = null;
        if (!empty($client['package_id'])) {
          $p = $pdo->prepare("SELECT profile, profile_name FROM packages WHERE id=? LIMIT 1");
          $p->execute([(int)$client['package_id']]);
          if ($pr = $p->fetch(PDO::FETCH_ASSOC)) {
            $profile = $pr['profile_name'] ?? ($pr['profile'] ?? null);
          }
        }
        if ($profile) { $api->comm('/ppp/secret/set', ['.id'=>$id, 'profile'=>$profile]); }
        $actives = $api->comm('/ppp/active/print', ['?name'=>$pppoe]);
        foreach ($actives as $a) { if (isset($a['.id'])) $api->comm('/ppp/active/remove', ['.id'=>$a['.id']]); }
        log_router('pppoe enabled', ['client_id'=>$client_id,'router_id'=>$routerId,'user'=>$pppoe,'profile'=>$profile]);
      } else {
        log_router('pppoe secret missing', ['client_id'=>$client_id,'router_id'=>$routerId,'user'=>$pppoe]);
      }
    }
  } catch (Throwable $e) {
    log_router('router activation failed', ['client_id'=>$client_id,'router_id'=>$routerId,'error'=>$e->getMessage()]);
  }
  try { $api->disconnect(); } catch (Throwable $e) {}
}
function ensure_pending_table(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS bkash_webhook_pending (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      trx_id VARCHAR(64) DEFAULT NULL,
      msisdn VARCHAR(32) DEFAULT NULL,
      ref_code VARCHAR(64) DEFAULT NULL,
      amount DECIMAL(12,2) DEFAULT NULL,
      raw_body LONGTEXT NOT NULL,
      reason VARCHAR(255) DEFAULT NULL,
      status ENUM('pending','matched','applied','ignored') DEFAULT 'pending',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");
}
function store_pending(PDO $pdo, array $payload): int {
  ensure_pending_table($pdo);
  $st = $pdo->prepare("INSERT INTO bkash_webhook_pending (trx_id, msisdn, ref_code, amount, raw_body, reason) VALUES (?, ?, ?, ?, ?, ?)");
  $st->execute([
    $payload['trx_id'] ?? null,
    $payload['msisdn'] ?? null,
    $payload['ref_code'] ?? null,
    $payload['amount'] ?? null,
    $payload['raw_body'] ?? '',
    $payload['reason'] ?? null,
  ]);
  return (int)$pdo->lastInsertId();
}
function verify_signature(string $raw): void {
  $secret = (string)cfg('BKASH_WEBHOOK_HMAC', '');
  $header = $_SERVER['HTTP_X_BKASH_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';
  if ($secret !== '' && $header !== '') {
    $expected = base64_encode(hash_hmac('sha256', $raw, $secret, true));
    if (!hash_equals($expected, (string)$header)) {
      bn_json(401, ['ok' => false, 'message' => 'Signature mismatch.']);
    }
  }
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
$raw = (string)file_get_contents('php://input');
verify_signature($raw);
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
$refSource = '';
if (!empty($data['payerReference'])) $refSource = 'payerReference';
$receivedAt = $data['completedTime'] ?? $data['paymentExecuteTime'] ?? $data['time'] ?? $data['timestamp'] ?? '';
if ($receivedAt) {
  $ts = strtotime((string)$receivedAt);
  $receivedAt = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
} else {
  $receivedAt = date('Y-m-d H:i:s');
}

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
log_webhook($raw, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'trx' => $trxID, 'ref' => $refCode, 'amount' => $amount, 'ref_source' => $refSource]);

/* ---------- dedupe check (sms_inbox + payments) ---------- */
if ($trxID !== '') {
  $chk = $pdo->prepare("SELECT id FROM sms_inbox WHERE gateway='bkash_webhook' AND trx_id=? LIMIT 1");
  $chk->execute([$trxID]);
  if ($chk->fetchColumn()) {
    bn_json(200, ['ok' => true, 'duplicate' => true, 'message' => 'এই ট্রান্স্যাকশন আগে সেভ করা হয়েছে।']);
  }
  $dupPay = $pdo->prepare("SELECT id FROM payments WHERE txn_id=? AND method IN ('bkash','bkash_webhook') LIMIT 1");
  $dupPay->execute([$trxID]);
  if ($dupPay->fetchColumn()) {
    bn_json(200, ['ok' => true, 'duplicate' => true, 'message' => 'এই ট্রান্স্যাকশন আগেই পেমেন্ট হিসেবে সেভ করা হয়েছে।']);
  }
}

/* ---------- persist to sms_inbox ---------- */
$rawBody = $raw ?: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$meta = [
  'headers' => [
    'content_type' => $ct,
    'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'remote_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'signature'    => $_SERVER['HTTP_X_BKASH_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '',
  ],
  'payload' => $data,
  'ref_source' => $refSource,
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
$inboxId = (int)$pdo->lastInsertId();

/* ---------- realtime apply ---------- */
$apply = [
  'inbox_id'   => $inboxId,
  'trx_id'     => $trxID,
  'amount'     => $amount,
  'msisdn'     => $msisdn,
  'ref_code'   => $refCode,
  'received_at'=> $receivedAt,
  'raw_body'   => $rawBody,
  'payload'    => $data,
];

$resp = [
  'ok'        => true,
  'inbox_id'  => $inboxId,
  'trx_id'    => $trxID,
  'status'    => $status,
];

try {
  $clientId = null;
  foreach (extract_ref_candidates($refCode, $trxID, $data) as $cand) {
    $clientId = resolve_client_from_ref($pdo, $cand);
    if ($clientId) break;
  }
  if (!$clientId) $clientId = find_client_by_msisdn($pdo, $msisdn);

  if (!$clientId) {
    $pendingId = store_pending($pdo, $apply + ['reason' => 'client_not_found']);
    $resp['pending'] = true;
    $resp['pending_id'] = $pendingId;
    $resp['message'] = 'ক্লায়েন্ট মেলেনি; পেন্ডিং টেবিলে রাখা হয়েছে।';
    log_webhook($raw, ['pending_id' => $pendingId, 'reason' => 'client_not_found']);
    bn_json(200, $resp);
  }

  if ($amount <= 0) {
    $resp['ok'] = false;
    $resp['message'] = 'Amount সঠিক নয় (০ এর বেশি হতে হবে)।';
    bn_json(422, $resp);
  }

  $pdo->beginTransaction();
  $note = sprintf('bKash webhook realtime%s; inbox_id=%d; ref=%s', ($refSource==='payerReference'?' [USSD]':''), $inboxId, $refCode);
  $res  = settle_payment($pdo, $clientId, $amount, $trxID, 'bkash_webhook', $note, $receivedAt);
  if (($res['applied_amount'] ?? 0) <= 0) {
    $pdo->rollBack();
    $resp['ok'] = false;
    $resp['message'] = $res['error'] ?? 'কোনো বকেয়া ইনভয়েস পাওয়া যায়নি।';
    bn_json(422, $resp);
  }

  $expiry = compute_new_expiry($pdo, $clientId, null);
  update_client_after_payment($pdo, $clientId, $receivedAt, $expiry);
  $pdo->prepare("UPDATE sms_inbox SET processed=1,error_msg=NULL WHERE id=?")->execute([$inboxId]);
  $pdo->commit();

  try { activateOnRouter($clientId); } catch (Throwable $e) { log_webhook('router-activate-failed', ['client_id'=>$clientId,'error'=>$e->getMessage()]); }

  $resp['message'] = 'পেমেন্ট পাওয়া গেছে, ইনভয়েসে অ্যাপ্লাই করা হয়েছে এবং অ্যাকাউন্ট অ্যাক্টিভ হয়েছে।';
  $resp['client_id'] = $clientId;
  $resp['applied_amount'] = $res['applied_amount'] ?? 0;
  $resp['applied_invoices'] = $res['applied_invoices'] ?? [];
  $resp['expiry_date'] = $expiry;
  log_webhook($raw, ['client_id'=>$clientId,'applied_amount'=>$res['applied_amount'] ?? 0,'invoices'=>$res['applied_invoices'] ?? []]);
  bn_json(200, $resp);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  log_webhook($raw, ['error' => $e->getMessage()]);
  $resp['ok'] = false;
  $resp['message'] = 'ওয়েবহুক প্রসেস করতে ব্যর্থ: ' . $e->getMessage();
  bn_json(500, $resp);
}
