<?php
// /public/process_manual.php
// Manually map a webhook inbox (sms_inbox.gateway=bkash_webhook) to a client and apply payment.

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$page_title = 'Manual Payment Mapping';
$_active = 'bkash_manual';

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function col_exists(PDO $pdo, string $tbl, string $col): bool {
  try { $st=$pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?"); $st->execute([$col]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}
function table_columns(PDO $pdo, string $tbl): array {
  try { $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"); $st->execute([$tbl]); return $st->fetchAll(PDO::FETCH_COLUMN); }
  catch(Throwable $e){ return []; }
}
function compute_new_expiry(PDO $pdo, int $clientId): string {
  $st = $pdo->prepare("SELECT expiry_date, package_id FROM clients WHERE id=? LIMIT 1");
  $st->execute([$clientId]);
  $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $days = 30;
  if (!empty($row['package_id'])) {
    $p = $pdo->prepare("SELECT duration_days, validity FROM packages WHERE id=? LIMIT 1");
    $p->execute([(int)$row['package_id']]);
    if ($pkg = $p->fetch(PDO::FETCH_ASSOC)) {
      $days = (int)($pkg['duration_days'] ?? 0);
      if ($days <= 0 && isset($pkg['validity'])) $days = (int)$pkg['validity'];
      if ($days <= 0) $days = 30;
    }
  }
  $base = new DateTime();
  if (!empty($row['expiry_date']) && strtotime((string)$row['expiry_date'])) {
    $cur = new DateTime($row['expiry_date']);
    if ($cur > new DateTime()) $base = $cur;
  }
  $base->modify('+' . $days . ' days');
  return $base->format('Y-m-d');
}
function update_client_after_payment(PDO $pdo, int $clientId, string $paidAt, string $expiry): void {
  $fields=['status=?','expiry_date=?']; $params=['active',$expiry];
  if (col_exists($pdo,'clients','last_payment_date')) { $fields[]='last_payment_date=?'; $params[]=$paidAt; }
  if (col_exists($pdo,'clients','payment_status')) { $fields[]='payment_status=?'; $params[]='paid'; }
  if (col_exists($pdo,'clients','updated_at')) { $fields[]='updated_at=NOW()'; }
  $params[]=$clientId;
  $pdo->prepare("UPDATE clients SET ".implode(',', $fields)." WHERE id=?")->execute($params);
}
function table_col_exists(PDO $pdo,string $tbl,string $col):bool{
  try{ $st=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1"); $st->execute([$tbl,$col]); return (bool)$st->fetchColumn(); }
  catch(Throwable $e){ return false; }
}
function settle_payment(PDO $pdo, int $client_id, float $amount, string $trx_id, string $method, string $note, ?string $paid_at): array {
  $applied=0.0; $appliedInvoices=[];
  $orderCol=null;
  foreach (['billing_month','invoice_date','due_date'] as $c) { if (table_col_exists($pdo,'invoices',$c)) { $orderCol=$c; break; } }
  if (!$orderCol) $orderCol='id';
  $where="i.client_id=?"; if (table_col_exists($pdo,'invoices','is_void')) $where.=" AND COALESCE(i.is_void,0)=0";
  if (table_col_exists($pdo,'invoices','status')) $where.=" AND i.status IN ('unpaid','partial','Unpaid','Partial','Due')";
  $st=$pdo->prepare("SELECT id,payable,COALESCE(paid_amount,0) paid_amount FROM invoices i WHERE $where ORDER BY i.`$orderCol` ASC, i.id ASC");
  $st->execute([$client_id]); $invs=$st->fetchAll(PDO::FETCH_ASSOC);
  if (!$invs) return ['error'=>'কোনো বকেয়া ইনভয়েস নেই।'];
  $payCols=table_columns($pdo,'payments'); $invCols=table_columns($pdo,'invoices'); $paidAtVal=$paid_at?:date('Y-m-d H:i:s'); $iCount=0;
  foreach ($invs as $inv) {
    if ($amount<=0) break;
    $due=(float)$inv['payable']-(float)$inv['paid_amount']; if ($due<=0) continue;
    $pay=min($due,$amount); $iCount++; $invoiceId=(int)$inv['id'];
    $txnUnique=($iCount===1 && $pay>=$amount)?$trx_id:($trx_id.'-I'.$invoiceId);
    $fields=[];$ph=[];$vals=[];
    if (in_array('invoice_id',$payCols,true)) { $fields[]='`invoice_id`'; $ph[]='?'; $vals[]=$invoiceId; }
    if (in_array('client_id',$payCols,true))  { $fields[]='`client_id`';  $ph[]='?'; $vals[]=$client_id; }
    if (in_array('bill_id',$payCols,true))    { $fields[]='`bill_id`';    $ph[]='?'; $vals[]=$invoiceId; }
    $fields[]='`amount`'; $ph[]='?'; $vals[]=$pay;
    if (in_array('discount',$payCols,true))   { $fields[]='`discount`';   $ph[]='?'; $vals[]=0; }
    if (in_array('payment_date',$payCols,true)) { $fields[]='`payment_date`'; $ph[]='?'; $vals[]=$paidAtVal; }
    if (in_array('method',$payCols,true))     { $fields[]='`method`';     $ph[]='?'; $vals[]=$method; }
    if (in_array('txn_id',$payCols,true))     { $fields[]='`txn_id`';     $ph[]='?'; $vals[]=$txnUnique; }
    if (in_array('transaction_id',$payCols,true)) { $fields[]='`transaction_id`'; $ph[]='?'; $vals[]=$trx_id; }
    if (in_array('paid_at',$payCols,true))    { $fields[]='`paid_at`';    $ph[]='?'; $vals[]=$paidAtVal; }
    if (in_array('note',$payCols,true))       { $fields[]='`note`';       $ph[]='?'; $vals[]=$note; }
    elseif (in_array('notes',$payCols,true))  { $fields[]='`notes`';      $ph[]='?'; $vals[]=$note; }
    $pdo->prepare("INSERT INTO payments (".implode(',',$fields).") VALUES (".implode(',',$ph).")")->execute($vals);

    $newPaid=(float)$inv['paid_amount']+$pay; $updSets=[];$updVals=[];
    if (in_array('paid_amount',$invCols,true)) { $updSets[]='paid_amount=?'; $updVals[]=$newPaid; }
    if (in_array('status',$invCols,true)) { $updSets[]='status=?'; $updVals[]=($newPaid>=(float)$inv['payable'])?'paid':'partial'; }
    if (in_array('paid_at',$invCols,true)) { $updSets[]='paid_at=?'; $updVals[]=$paidAtVal; }
    if (in_array('method',$invCols,true)) { $updSets[]='method=?'; $updVals[]=$method; }
    if ($updSets) { $updVals[]=$invoiceId; $pdo->prepare("UPDATE invoices SET ".implode(',',$updSets)." WHERE id=?")->execute($updVals); }
    $amount-=$pay; $applied+=$pay; $appliedInvoices[]=$invoiceId;
  }
  return ['applied_amount'=>$applied,'applied_invoices'=>$appliedInvoices,'remaining'=>$amount];
}
function activateOnRouter(int $client_id): void {
  $file = dirname(__DIR__) . '/api/bkash_webhook.php';
  if (!class_exists('RouterosAPI') && file_exists($file)) {
    @require_once dirname(__DIR__) . '/app/routeros_api.class.php';
  }
  if (!class_exists('RouterosAPI')) return;
  $pdo = db();
  $st = $pdo->prepare("SELECT id, router_id, pppoe_id, connection_type, package_id FROM clients WHERE id=? LIMIT 1");
  $st->execute([$client_id]);
  $c = $st->fetch(PDO::FETCH_ASSOC);
  if (!$c) return;
  $routerId = (int)($c['router_id'] ?? 0);
  $pppoe = trim((string)($c['pppoe_id'] ?? ''));
  if ($routerId<=0 || $pppoe==='') return;
  $rs=$pdo->prepare("SELECT * FROM routers WHERE id=? LIMIT 1"); $rs->execute([$routerId]); $r=$rs->fetch(PDO::FETCH_ASSOC);
  if (!$r) return;
  $api = new RouterosAPI(); $api->debug=false;
  $ip=$r['ip_address'] ?? ($r['ip'] ?? ''); $user=$r['username'] ?? ($r['user'] ?? ''); $pass=$r['password'] ?? ($r['pass'] ?? ''); $port=(int)($r['api_port'] ?? 8728);
  $ok=false; try { if (property_exists($api,'port')) $api->port=$port; $ok=$api->connect($ip,$user,$pass); } catch(Throwable $e){}
  if (!$ok) { try{$ok=$api->connect($ip,$user,$pass,$port);}catch(Throwable $e){} }
  if (!$ok) return;
  $ctype=strtolower((string)($c['connection_type'] ?? 'pppoe'));
  if ($ctype==='hotspot') {
    $u=$api->comm('/ip/hotspot/user/print',['?name'=>$pppoe]); if (isset($u[0]['.id'])) $api->comm('/ip/hotspot/user/set',['.id'=>$u[0]['.id'],'disabled'=>'no']);
  } else {
    $u=$api->comm('/ppp/secret/print',['?name'=>$pppoe]); if (isset($u[0]['.id'])) {
      $id=$u[0]['.id']; $api->comm('/ppp/secret/set',['.id'=>$id,'disabled'=>'no']);
      $profile=null;
      if (!empty($c['package_id'])) {
        $p=$pdo->prepare("SELECT profile, profile_name FROM packages WHERE id=? LIMIT 1"); $p->execute([(int)$c['package_id']]);
        if ($pr=$p->fetch(PDO::FETCH_ASSOC)) $profile=$pr['profile_name'] ?? ($pr['profile'] ?? null);
        if ($profile) $api->comm('/ppp/secret/set',['.id'=>$id,'profile'=>$profile]);
      }
      $act=$api->comm('/ppp/active/print',['?name'=>$pppoe]); foreach ($act as $a) if (isset($a['.id'])) $api->comm('/ppp/active/remove',['.id'=>$a['.id']]);
    }
  }
  try { $api->disconnect(); } catch(Throwable $e){}
}

$inboxId = (int)($_GET['inbox_id'] ?? $_POST['inbox_id'] ?? 0);
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && $inboxId>0) {
  $clientId = (int)($_POST['client_id'] ?? 0);
  if ($clientId<=0) { $err='ক্লায়েন্ট নির্বাচন করুন।'; }
  else {
    $st=$pdo->prepare("SELECT * FROM sms_inbox WHERE id=? AND gateway='bkash_webhook' LIMIT 1");
    $st->execute([$inboxId]);
    $row=$st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $err='Webhook inbox পাওয়া যায়নি।'; }
    else {
      $amt=(float)($row['amount'] ?? 0); $trx=trim((string)($row['trx_id'] ?? '')); $ref=trim((string)($row['ref_code'] ?? ''));
      $paid_at=$row['received_at'] ?? date('Y-m-d H:i:s');
      if ($amt<=0 || $trx==='') { $err='Amount/TrxID সঠিক নয়।'; }
      else {
        try {
          $pdo->beginTransaction();
          $note = sprintf('Manual map from inbox %d; ref=%s', $inboxId, $ref);
          $res = settle_payment($pdo, $clientId, $amt, $trx, 'bkash_webhook_manual', $note, $paid_at);
          if (($res['applied_amount'] ?? 0) <= 0) throw new Exception($res['error'] ?? 'কোনো ইনভয়েসে অ্যাপ্লাই হয়নি।');
          $expiry = compute_new_expiry($pdo, $clientId);
          update_client_after_payment($pdo, $clientId, $paid_at, $expiry);
          $pdo->prepare("UPDATE sms_inbox SET processed=1, error_msg=NULL WHERE id=?")->execute([$inboxId]);
          $pdo->commit();
          activateOnRouter($clientId);
          header("Location: /public/webhook_payments.php?msg=" . urlencode('ম্যানুয়াল ট্রান্সফার সম্পন্ন।'));
          exit;
        } catch(Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $err = 'ট্রান্সফার ব্যর্থ: '.$e->getMessage();
        }
      }
    }
  }
}

$row = null;
if ($inboxId>0) {
  $st=$pdo->prepare("SELECT * FROM sms_inbox WHERE id=? AND gateway='bkash_webhook' LIMIT 1");
  $st->execute([$inboxId]);
  $row=$st->fetch(PDO::FETCH_ASSOC);
}
$search = trim($_GET['search'] ?? '');
$clients = [];
if ($search !== '') {
  $like = '%'.$search.'%';
  $st = $pdo->prepare("SELECT id, name, pppoe_id, client_code FROM clients WHERE name LIKE ? OR pppoe_id LIKE ? OR client_code LIKE ? ORDER BY id DESC LIMIT 30");
  $st->execute([$like,$like,$like]);
  $clients = $st->fetchAll(PDO::FETCH_ASSOC);
}
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : $msg;

require_once __DIR__ . '/../partials/partials_header.php';
?>
<style>
  .hero-card { background: linear-gradient(135deg, #0d6efd, #0a58ca); color:#fff; border:0; box-shadow: 0 6px 24px rgba(0,0,0,0.08);}
  .label-muted { color: #6c757d; font-size: 12px; letter-spacing: .3px; text-transform: uppercase; }
  .card-section { box-shadow: 0 6px 24px rgba(0,0,0,0.08); }
</style>

<div class="container py-4">
  <div class="hero-card card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-wrench-adjustable-circle fs-4"></i>
          <h4 class="mb-0">Manual Payment Mapping</h4>
        </div>
        <small class="opacity-75">Map unmatched webhook payments to clients and settle immediately.</small>
      </div>
      <div class="btn-group">
        <a class="btn btn-light btn-sm" href="/public/webhook_payments.php"><i class="bi bi-arrow-left-circle me-1"></i>Webhook Dashboard</a>
      </div>
    </div>
  </div>
  <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if (!$row): ?>
    <div class="alert alert-warning">Webhook inbox পাওয়া যায়নি।</div>
  <?php else: ?>
    <div class="card mb-3 card-section">
      <div class="card-header bg-dark text-white">Webhook Transaction</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small mb-1">TrxID</label><input class="form-control form-control-sm" value="<?= h($row['trx_id']) ?>" readonly></div>
          <div class="col-md-2"><label class="form-label small mb-1">Amount</label><input class="form-control form-control-sm" value="<?= h($row['amount']) ?>" readonly></div>
          <div class="col-md-3"><label class="form-label small mb-1">MSISDN</label><input class="form-control form-control-sm" value="<?= h($row['msisdn_from']) ?>" readonly></div>
          <div class="col-md-3"><label class="form-label small mb-1">Ref</label><input class="form-control form-control-sm" value="<?= h($row['ref_code']) ?>" readonly></div>
          <div class="col-md-6"><label class="form-label small mb-1">Received At</label><input class="form-control form-control-sm" value="<?= h($row['received_at']) ?>" readonly></div>
          <div class="col-12">
            <label class="form-label small mb-1">Raw Payload</label>
            <pre class="bg-light border rounded p-2 small" style="max-height:260px; overflow:auto;"><?php
              $raw = $row['raw_body'] ?? '';
              $pretty = $raw;
              $j = json_decode($raw, true);
              if (is_array($j)) $pretty = json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
              echo h($pretty);
            ?></pre>
          </div>
        </div>
      </div>
    </div>

    <div class="card card-section">
      <div class="card-header bg-dark text-white">Map to Client</div>
      <div class="card-body">
        <form class="row g-3 mb-3" method="get">
          <input type="hidden" name="inbox_id" value="<?= (int)$inboxId ?>">
          <div class="col-md-6">
            <label class="form-label small mb-1">Search Client (name / PPPoE / code)</label>
            <input type="text" name="search" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="e.g., John or PPP123 or C1001">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <button class="btn btn-primary btn-sm" type="submit">Search</button>
          </div>
        </form>

        <form method="post" class="mt-2">
          <input type="hidden" name="inbox_id" value="<?= (int)$inboxId ?>">
          <div class="mb-3">
            <label class="form-label small mb-1">Select Client</label>
            <select name="client_id" class="form-select">
              <option value="">-- Choose --</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>">#<?= (int)$c['id'] ?> • <?= h($c['name']) ?> (<?= h($c['pppoe_id']) ?> | <?= h($c['client_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">নিচে সার্চ করে ক্লায়েন্ট নির্বাচন করুন, তারপর ট্রান্সফার করুন।</div>
          </div>
          <button class="btn btn-success">Payment Transfer</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
