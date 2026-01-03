<?php
// /public/webhook_payments.php
// Daily webhook payment view with filters + detail modal.
// --- Retry Handler ---
if (isset($_GET['retry_id'])) {
    $retryId = (int)$_GET['retry_id'];
    $row = $pdo->prepare("SELECT * FROM sms_inbox WHERE id=? AND processed=0 LIMIT 1");
    $row->execute([$retryId]);
    $payment = $row->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        $clientId = resolve_client_from_ref($pdo, (string)$payment['ref_code']);
        
        // যদি রেফ দিয়ে না পাওয়া যায়, তবে মোবাইল নম্বর দিয়ে ট্রাই করবে
        if (!$clientId && !empty($payment['sender_number'])) {
            $clientId = find_client_by_msisdn($pdo, $payment['sender_number']);
        }

        if ($clientId) {
            // পেমেন্ট সেটল এবং রাউটার একটিভ করার ট্রাই
            try {
                $res = settle_payment($pdo, $clientId, (float)$payment['amount'], $payment['trx_id'], 'bkash', 'Manual Retry Map', $payment['received_at'] ?? date('Y-m-d H:i:s'));
                if (($res['applied_amount'] ?? 0) > 0) {
                    activateOnRouter($clientId);
                    $pdo->prepare("UPDATE sms_inbox SET processed=1, error_msg=NULL WHERE id=?")->execute([$retryId]);
                    $_SESSION['msg'] = "✅ সফলভাবে পেমেন্ট ম্যাচ হয়েছে এবং ক্লায়েন্ট একটিভ হয়েছে!";
                }
            } catch (Exception $e) {
                $_SESSION['err'] = "❌ ত্রুটি: " . $e->getMessage();
            }
        } else {
            $_SESSION['err'] = "⚠️ দুঃখিত, এই রেফারেন্স বা নম্বর দিয়ে কোনো ক্লায়েন্ট খুঁজে পাওয়া যায়নি।";
        }
    }
    header("Location: webhook_payments.php");
    exit;
}

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$page_title = 'Webhook Payments';
$_active = 'bkash_webhook';
$toast_msg = '';
$toast_type = 'success';

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Helpers pulled from webhook processor (local copy to avoid executing API endpoint) */
function col_exists_local(PDO $pdo, string $tbl, string $col): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$tbl, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function table_columns(PDO $pdo, string $tbl): array {
  try { $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"); $st->execute([$tbl]); return $st->fetchAll(PDO::FETCH_COLUMN); }
  catch (Throwable $e) { return []; }
}
function normalize_msisdn_local($s): string {
  $s = preg_replace('/\D+/', '', (string)$s);
  if (strlen($s) === 13 && substr($s, 0, 3) === '880') $s = '0' . substr($s, 3);
  if (strlen($s) === 14 && substr($s, 0, 4) === '0880') $s = '0' . substr($s, 4);
  if (strlen($s) === 11 && substr($s, 0, 2) === '01') return $s;
  return $s;
}
function resolve_client_from_ref(PDO $pdo, string $ref): ?int {
  $ref = trim($ref);
  if ($ref === '') return null;
  if (ctype_digit($ref)) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
    if (col_exists_local($pdo, 'clients', 'client_code')) {
      $st = $pdo->prepare("SELECT id FROM clients WHERE client_code=? LIMIT 1");
      $st->execute([$ref]);
      $cid = (int)($st->fetchColumn() ?: 0);
      if ($cid > 0) return $cid;
    }
  }
  foreach (['client_code','pppoe_id','username','clientid','customer_code'] as $col) {
    if (!col_exists_local($pdo, 'clients', $col)) continue;
    $st = $pdo->prepare("SELECT id FROM clients WHERE `$col`=? LIMIT 1");
    $st->execute([$ref]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
  }
  $digits = preg_replace('/\D+/', '', $ref);
  if ($digits !== '' && ctype_digit($digits)) {
    $st = $pdo->prepare("SELECT id FROM clients WHERE id=? LIMIT 1");
    $st->execute([(int)$digits]);
    $cid = (int)($st->fetchColumn() ?: 0);
    if ($cid > 0) return $cid;
    if (col_exists_local($pdo, 'clients', 'client_code')) {
      $st = $pdo->prepare("SELECT id FROM clients WHERE client_code=? LIMIT 1");
      $st->execute([$digits]);
      $cid = (int)($st->fetchColumn() ?: 0);
      if ($cid > 0) return $cid;
    }
  }
  return null;
}
function find_client_by_msisdn(PDO $pdo, ?string $msisdn): ?int {
  $msisdn = normalize_msisdn_local($msisdn ?? '');
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
  if (col_exists_local($pdo,'clients','last_payment_date')) { $fields[]='last_payment_date=?'; $params[]=$paidAt; }
  if (col_exists_local($pdo,'clients','payment_status')) { $fields[]='payment_status=?'; $params[]='paid'; }
  if (col_exists_local($pdo,'clients','updated_at')) { $fields[]='updated_at=NOW()'; }
  $params[]=$clientId;
  $pdo->prepare("UPDATE clients SET ".implode(',', $fields)." WHERE id=?")->execute($params);
}
function settle_payment(PDO $pdo, int $client_id, float $amount, string $trx_id, string $method, string $note, ?string $paid_at): array {
  $applied=0.0; $appliedInvoices=[];
  $orderCol=null;
  foreach (['billing_month','invoice_date','due_date'] as $c) { if (col_exists_local($pdo,'invoices',$c)) { $orderCol=$c; break; } }
  if (!$orderCol) $orderCol='id';
  $where="i.client_id=?"; if (col_exists_local($pdo,'invoices','is_void')) $where.=" AND COALESCE(i.is_void,0)=0";
  if (col_exists_local($pdo,'invoices','status')) $where.=" AND i.status IN ('unpaid','partial','Unpaid','Partial','Due')";
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
  if (!class_exists('RouterosAPI')) {
    @require_once __DIR__ . '/../app/routeros_api.class.php';
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

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$status = trim($_GET['status'] ?? 'all'); // all|processed|pending|error
$search = trim($_GET['q'] ?? '');
$limit  = max(10, min(200, (int)($_GET['limit'] ?? 25)));

$where = ["gateway='bkash_webhook'"];
$params = [];
if ($from !== '') { $where[] = 'DATE(received_at) >= ?'; $params[] = $from; }
if ($to   !== '') { $where[] = 'DATE(received_at) <= ?'; $params[] = $to; }
if ($status === 'processed') { $where[] = 'processed=1'; }
if ($status === 'pending')   { $where[] = "processed=0 AND (error_msg IS NULL OR error_msg='')"; }
if ($status === 'error')     { $where[] = "processed=0 AND (error_msg IS NOT NULL AND error_msg<>'')"; }
if ($search !== '') {
  $where[] = '(trx_id LIKE ? OR ref_code LIKE ? OR msisdn_from LIKE ?)';
  $params[] = '%' . $search . '%';
  $params[] = '%' . $search . '%';
  $params[] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, received_at, trx_id, amount, ref_code, msisdn_from, msisdn_to, processed, error_msg, created_at, raw_body, meta_json
        FROM sms_inbox
        $whereSql
        ORDER BY received_at DESC
        LIMIT $limit";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$statSql = "SELECT 
    SUM(CASE WHEN processed=1 THEN amount ELSE 0 END) AS settled_amt,
    SUM(CASE WHEN processed=0 THEN amount ELSE 0 END) AS pending_amt,
    SUM(amount) AS total_amt,
    SUM(CASE WHEN processed=0 THEN 1 ELSE 0 END) AS pending_cnt,
    SUM(CASE WHEN processed=1 THEN 1 ELSE 0 END) AS settled_cnt,
    SUM(CASE WHEN processed=1 AND DATE(received_at)=CURDATE() THEN amount ELSE 0 END) AS today_amt,
    SUM(CASE WHEN processed=0 AND (error_msg IS NULL OR error_msg='') THEN 1 ELSE 0 END) AS pending_unmatched,
    SUM(CASE WHEN processed=0 AND (error_msg IS NOT NULL AND error_msg<>'') THEN 1 ELSE 0 END) AS error_cnt
  FROM sms_inbox WHERE gateway='bkash_webhook'";
$stats = $pdo->query($statSql)->fetch(PDO::FETCH_ASSOC) ?: [];
$pendingCnt = (int)($stats['pending_cnt'] ?? 0);
$errorCnt   = (int)($stats['error_cnt'] ?? 0);
$settledCnt = (int)($stats['settled_cnt'] ?? 0);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/../partials/partials_header.php';
?>
<?php if ($toast_msg): ?>
  <div class="alert alert-<?= h($toast_type) ?> alert-dismissible fade show mx-3" role="alert">
    <?= h($toast_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>
<style>
  .stat-card { min-height: 120px; color:#fff; border:0; box-shadow: 0 6px 24px rgba(0,0,0,0.08); }
  .stat-card h5 { font-size: 1rem; margin-bottom: .25rem; letter-spacing:.2px; }
  .stat-card .value { font-size: 1.8rem; font-weight: 800; }
  .hero-card { background: linear-gradient(135deg, #0d6efd, #0b5ed7); color:#fff; border:0; }
  .table thead th { text-transform: uppercase; font-size: 12px; letter-spacing: .3px; }
  .badge-pill { border-radius: 50rem; }
  .stat-card{ border:1px solid #e5e7eb; border-radius:.75rem; background:#fff; }
  .stat-card .hdr{ padding:.65rem .9rem; border-bottom:1px solid #eef1f4; background:#f8f9fa; font-weight:600; }
  .stat-card .bd{ padding:.9rem; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
</style>

<div class="container-fluid py-3 text-start">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="m-0"><img src="/assets/images/BKash-Icon-Logo.wine.png" alt="BKash Logo" width="50" height="40"> Webhook Payments</h5>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/public/webhook_payments.php"><i class="bi bi-arrow-repeat me-1"></i>Refresh</a>
        <a class="btn btn-secondary btn-sm" href="/public/bkash_rtn_dashboard.php"><i class="bi bi-graph-up-arrow me-1"></i>RTN Dashboard</a>
        <a class="btn btn-outline-secondary btn-sm" href="/public/process_manual.php"><i class="bi bi-wrench me-1"></i>Manual Pending</a>
        <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="stat-card rounded px-3 py-3" style="background:#0d6efd;">
        <h5>Today's Collection</h5>
        <div class="value"><?= number_format((float)($stats['today_amt'] ?? 0),2) ?></div>
        <small><?= date('d-M-Y') ?> • <?= (int)($stats['settled_cnt'] ?? 0) ?> settled overall</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card rounded px-3 py-3" style="background:#20c997;">
        <h5>Pending Issues</h5>
        <div class="value"><?= (int)($stats['pending_unmatched'] ?? 0) ?></div>
        <small>Unmatched reference (need manual map)</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card rounded px-3 py-3" style="background:#6f42c1;">
        <h5>Error Rate</h5>
      <div class="value"><?= (int)($stats['error_cnt'] ?? 0) ?></div>
      <small>Failed settle attempts</small>
    </div>
  </div>
</div>

  <?php
    $baseUrl = '/public/webhook_payments.php';
    $buildQs = function($status) use($from,$to,$search,$limit){ return http_build_query(['status'=>$status,'from'=>$from,'to'=>$to,'q'=>$search,'limit'=>$limit]); };
  ?>
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pill-filter">
        <div class="btn-group">
          <a class="btn btn-sm <?= $status==='all'?'btn-primary':'btn-outline-secondary' ?>" href="<?= $baseUrl ?>?<?= $buildQs('all') ?>">All</a>
          <a class="btn btn-sm <?= $status==='processed'?'btn-primary':'btn-outline-secondary' ?>" href="<?= $baseUrl ?>?<?= $buildQs('processed') ?>">Processed <span class="badge bg-light text-dark ms-1"><?= $settledCnt ?></span></a>
          <a class="btn btn-sm <?= $status==='pending'?'btn-primary':'btn-outline-secondary' ?>" href="<?= $baseUrl ?>?<?= $buildQs('pending') ?>">Pending <span class="badge bg-light text-dark ms-1"><?= $pendingCnt ?></span></a>
          <a class="btn btn-sm <?= $status==='error'?'btn-primary':'btn-outline-secondary' ?>" href="<?= $baseUrl ?>?<?= $buildQs('error') ?>">Error <span class="badge bg-light text-dark ms-1"><?= $errorCnt ?></span></a>
        </div>
        <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="true">
          <i class="bi bi-sliders me-1"></i> Advanced Filters
        </button>
      </div>
      <div class="collapse show" id="advancedFilters">
        <form class="row g-3 align-items-end">
          <div class="col-md-2">
            <label class="form-label small mb-1">From Date</label>
            <input type="date" name="from" value="<?= h($from) ?>" class="form-control form-control-sm">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">To Date</label>
            <input type="date" name="to" value="<?= h($to) ?>" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Search (TrxID/Ref/MSISDN)</label>
            <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="TrxID or Ref or MSISDN">
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-1">Show</label>
            <select name="limit" class="form-select form-select-sm">
              <?php foreach ([25,50,100,150,200] as $l): ?>
                <option value="<?= $l ?>" <?= ($limit===$l?'selected':'') ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 text-end ms-auto">
            <button class="btn btn-primary btn-sm"><i class="bi bi-filter me-1"></i>Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="webhook_payments.php">Clear</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>Trxn.Date</th>
            <th>TrxID</th>
            <th>FromWallet</th>
            <th>Amount</th>
            <th>Trxn.Ref</th>
            <th>TimeStamp</th>
            <th>TypeFor</th>
            <th>Status</th>
            <th>Client</th>
            <th>UpdatedOn</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="11" class="text-center text-muted py-3">No data found</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r):
              $statusLabel = 'Pending';
              $badge = 'warning';
              if ((int)$r['processed'] === 1) { $statusLabel = 'Automatically Settled'; $badge = 'success'; }
              elseif (!empty($r['error_msg'])) { $statusLabel = 'Error'; $badge = 'danger'; }
              $ts = $r['received_at'] ?: $r['created_at'];
              $clientInfo = '';
              if (!empty($r['trx_id'])) {
                $p = $pdo->prepare("SELECT p.client_id, c.name FROM payments p JOIN clients c ON c.id=p.client_id WHERE p.txn_id=? OR p.transaction_id=? LIMIT 1");
                $p->execute([$r['trx_id'], $r['trx_id']]);
                if ($cp = $p->fetch(PDO::FETCH_ASSOC)) {
                  $clientInfo = '#'.$cp['client_id'].' • '.$cp['name'];
                }
              }
              ?>
              <tr data-row='<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>
                <td><?= h($ts) ?></td>
                <td><code><?= h($r['trx_id']) ?></code></td>
                <td><?= h($r['msisdn_from']) ?></td>
                <td><?= number_format((float)($r['amount'] ?? 0), 2) ?></td>
                <td><?= h($r['ref_code']) ?></td>
                <td><?= h($ts) ?></td>
                <td>BKASH_WEBHOOK</td>
                <td><span class="badge bg-<?= $badge ?>"><?= h($statusLabel) ?></span></td>
                <td><?= h($clientInfo) ?></td>
                <td><?= h($r['created_at']) ?></td>
                <td class="text-nowrap">
                  <button class="btn btn-sm btn-outline-primary btn-view" type="button">View</button>
                  <?php $mapClass = ((int)$r['processed']===1) ? 'btn-outline-success' : 'btn-warning'; ?>
                  <a class="btn btn-sm <?= $mapClass ?>" href="/public/process_manual.php?inbox_id=<?= (int)$r['id'] ?>">Map Payment</a>
                  <?php if ((int)$r['processed']===0): ?>
                    <a class="btn btn-sm btn-outline-warning" href="/public/webhook_payments.php?retry_id=<?= (int)$r['id'] ?>">Re-try Auto Map</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between small">
      <span>Showing <?= count($rows) ?> entries (limit <?= $limit ?>)</span>
      <span>Total amount in view: <?= number_format(array_sum(array_map(fn($x)=> (float)($x['amount'] ?? 0), $rows)),2) ?></span>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Webhook Payment Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small mb-1">Transaction ID</label>
            <input class="form-control form-control-sm" id="d_trx" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Amount</label>
            <input class="form-control form-control-sm" id="d_amt" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">From Wallet</label>
            <input class="form-control form-control-sm" id="d_from" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Reference</label>
            <input class="form-control form-control-sm" id="d_ref" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Timestamp</label>
            <input class="form-control form-control-sm" id="d_time" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1">Status</label>
            <input class="form-control form-control-sm" id="d_status" readonly>
          </div>
          <div class="col-12">
            <label class="form-label small mb-1">Raw Payload</label>
            <pre class="bg-light border rounded small p-2" id="d_raw" style="max-height:260px; overflow:auto;"></pre>
          </div>
          <div class="col-12">
            <label class="form-label small mb-1">Error / Note</label>
            <textarea class="form-control form-control-sm" id="d_err" rows="2" readonly></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="#" id="d_map" class="btn btn-success">Payment Transfer</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  const modalEl = document.getElementById('detailModal');
  const modal = new bootstrap.Modal(modalEl);
  document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      const data = JSON.parse(tr.dataset.row || '{}');
      document.getElementById('d_trx').value = data.trx_id || '';
      document.getElementById('d_amt').value = data.amount || '';
      document.getElementById('d_from').value = data.msisdn_from || '';
      document.getElementById('d_ref').value = data.ref_code || '';
      document.getElementById('d_time').value = data.received_at || data.created_at || '';
      let status = 'Pending';
      if (Number(data.processed) === 1) status = 'Automatically Settled';
      else if (data.error_msg) status = 'Error: ' + data.error_msg;
      document.getElementById('d_status').value = status;
      let raw = data.raw_body || '';
      try { raw = JSON.stringify(JSON.parse(raw), null, 2); } catch(e) {}
      document.getElementById('d_raw').textContent = raw;
      document.getElementById('d_err').value = data.error_msg || '';
      document.getElementById('d_map').href = '/public/process_manual.php?inbox_id=' + (data.id || 0);
      modal.show();
    });
  });
</script>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
$toast_msg = '';
$toast_type = 'success';

// Retry auto map handler
if (isset($_GET['retry_id'])) {
  $retryId = (int)$_GET['retry_id'];
  try {
    $st = $pdo->prepare("SELECT * FROM sms_inbox WHERE id=? AND gateway='bkash_webhook' LIMIT 1");
    $st->execute([$retryId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      throw new Exception('ওয়েবহুক ডাটা পাওয়া যায়নি।');
    }
    $ref = trim((string)($row['ref_code'] ?? ''));
    $clientId = resolve_client_from_ref($pdo, $ref);
    if (!$clientId) $clientId = find_client_by_msisdn($pdo, $row['msisdn_from'] ?? ($row['sender_number'] ?? ''));
    if (!$clientId) {
      throw new Exception('ক্লায়েন্ট মেলেনি; ম্যাপ হয়নি।');
    }
    $amt = (float)($row['amount'] ?? 0);
    $trx = trim((string)($row['trx_id'] ?? ''));
    if ($amt <= 0 || $trx === '') throw new Exception('Amount/TrxID সঠিক নয়।');
    $paid_at = $row['received_at'] ?? date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    $note = sprintf('Retry auto-map; inbox_id=%d; ref=%s', $retryId, $ref);
    $res = settle_payment($pdo, $clientId, $amt, $trx, 'bkash_webhook_retry', $note, $paid_at);
    if (($res['applied_amount'] ?? 0) <= 0) {
      throw new Exception($res['error'] ?? 'কোনো ইনভয়েসে অ্যাপ্লাই হয়নি।');
    }
    $expiry = compute_new_expiry($pdo, $clientId);
    update_client_after_payment($pdo, $clientId, $paid_at, $expiry);
    $pdo->prepare("UPDATE sms_inbox SET processed=1,error_msg=NULL WHERE id=?")->execute([$retryId]);
    $pdo->commit();
    activateOnRouter($clientId);
    $toast_msg = 'রিট্রাই সফল: ক্লায়েন্ট #'.$clientId.' এ পেমেন্ট অ্যাপ্লাই হয়েছে।';
    $toast_type = 'success';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $toast_msg = 'রিট্রাই ব্যর্থ: '.$e->getMessage();
    $toast_type = 'danger';
  }
}
