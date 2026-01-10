<?php
// (বাংলা) পেজের PHP লজিক app/PHP/billing_logic.php এ সরানো হয়েছে
require_once __DIR__ . '/../app/PHP/billing_logic.php';
include __DIR__ . '/../partials/partials_header.php';
?>


<div class="main-content p-3 p-md-4">
  <div class="container-fluid">

    <h3 class="mb-3">Billing</h3>

    <?php
      $qs=$_GET; $qs['page']=1;
      $qs_sum=$qs;  $qs_sum['view']='summary'; unset($qs_sum['tab']); unset($qs_sum['search']);
      $qs_all=$qs;  $qs_all['view']='list'; $qs_all['tab']='all';
      $qs_paid=$qs; $qs_paid['view']='list'; $qs_paid['tab']='paid';
      $qs_due=$qs;  $qs_due['view']='list'; $qs_due['tab']='due';
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div class="seg btn-group" role="group">
        <a class="btn btn-outline-primary <?= $view==='summary'?'active':'' ?>" href="?<?= h(http_build_query($qs_sum)) ?>"><i class="bi bi-graph-up"></i> Summary</a>
        <a class="btn btn-outline-secondary <?= ($view==='list' && $tab==='all')?'active':'' ?>" href="?<?= h(http_build_query($qs_all)) ?>"><i class="bi bi-list-ul"></i> All</a>
        <a href="/public/collections.php?when=today" class="btn btn-outline-success"><i class="bi bi-calendar-day"></i> Today's Collection</a>
        <!-- <a class="btn btn-outline-success <?= ($view==='list' && $tab==='paid')?'active':'' ?>" href="?<?= h(http_build_query($qs_paid)) ?>"><i class="bi bi-check2-circle"></i> Paid</a>
        <a class="btn btn-outline-danger  <?= ($view==='list' && $tab==='due')?'active':'' ?>" href="?<?= h(http_build_query($qs_due )) ?>"><i class="bi bi-exclamation-octagon"></i> Due</a> -->
        <a href="/public/webhook_payments.php" class="btn btn-outline-danger btn-sm"> 📴 Webhook Payments </a>
        <?php if($view==='list'){ $qs_csv=$qs; $qs_csv['export']='csv'; ?>
          <a class="btn btn-outline-primary btn-sm" href="?<?= h(http_build_query($qs_csv)) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
        <?php } ?>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <!-- Filters -->
    <form class="filter-card card border-0 shadow-sm mb-3" method="GET">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-3">
            <label class="form-label mb-1">Month</label>
            <input type="month" class="form-control form-control-sm" name="month" value="<?= h($monthParam) ?>">
          </div>
          <div class="col-12 col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" class="form-control form-control-sm" name="search" placeholder="Client Code / Name / PPPoE / Mobile" value="<?= h($search) ?>">
          </div>
          <div class="col-6 col-sm-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Apply</button>
          </div>
          <div class="col-6 col-sm-2 d-grid">
            <a class="btn btn-outline-secondary btn-sm" href="?month=<?= h(date('Y-m')) ?>&view=<?= h($view) ?>&tab=<?= h($tab) ?>"><i class="bi bi-x-circle"></i> Reset</a>
          </div>
        </div>

        <?php if($view==='list'): ?>
        <div class="filter-grid mt-3">
          <div class="row g-2 g-md-3">
            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Server</label>
              <select name="router_id" class="form-select form-select-sm">
                <option value="0">Select</option>
                <?php foreach($routers as $rt): ?>
                  <option value="<?= (int)$rt['id'] ?>" <?= $router_id==(int)$rt['id']?'selected':'' ?>>
                    <?= h($rt['name'] ?? 'Router #'.(int)$rt['id']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Zone</label>
              <select name="zone" class="form-select form-select-sm" <?= !$hasArea ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($zones as $z): ?>
                  <option value="<?= h($z) ?>" <?= $zone===$z?'selected':'' ?>><?= h($z) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Sub Zone</label>
              <select name="sub_zone" class="form-select form-select-sm" <?= !$hasSubZone ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($sub_zones_list as $sz): ?>
                  <option value="<?= h($sz) ?>" <?= $sub_zone===$sz?'selected':'' ?>><?= h($sz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Box</label>
              <select name="box" class="form-select form-select-sm" <?= !$hasBox ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($boxes as $b): ?>
                  <option value="<?= h($b) ?>" <?= $box===$b?'selected':'' ?>><?= h($b) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Package</label>
              <select name="package_id" class="form-select form-select-sm">
                <option value="0">Select</option>
                <?php foreach($packages as $pkg): ?>
                  <option value="<?= (int)$pkg['id'] ?>" <?= $package_id==(int)$pkg['id']?'selected':'' ?>>
                    <?= h($pkg['name'] ?? 'N/A') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Billing Status</label>
              <select name="b_status" class="form-select form-select-sm" <?= ($B_STATUS_COL==='' && !$invStatusCol) ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($b_statuses as $bs): ?>
                  <option value="<?= h($bs) ?>" <?= $b_status===$bs?'selected':'' ?>><?= h(ucfirst($bs)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Custom Status</label>
              <select name="custom_status" class="form-select form-select-sm">
                <option value="">Select</option>
                <option value="active" <?= $custom_status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $custom_status==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>

          </div>
        </div>
        <?php endif; ?>
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <?php if($view==='list'): ?><input type="hidden" name="tab" value="<?= h($tab) ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="limit" value="<?= (int)$limit ?>">
      </div>
    </form>

    <?php if($view==='summary'): ?>

      <div class="hero p-3 p-md-4 mb-3">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
          <div>
            <h4 class="mb-1">Bills vs Collections</h4>
            <div class="text-muted">Month: <?= h(date('F Y', strtotime($monthParam.'-01'))) ?></div>
          </div>
          <div class="summary d-inline-flex flex-wrap gap-4">
            <div><span class="text-muted">Invoices:</span> <span class="num total"><?= number_format($count_total) ?></span></div>
            <div><span class="text-muted">Paid:</span> <span class="num paid"><?= number_format($count_paid) ?></span></div>
            <div><span class="text-muted">Due:</span> <span class="num due"><?= number_format($count_due) ?></span></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-primary"><i class="bi bi-receipt"></i></div><div class="hint">Generated</div></div><div class="val text-primary"> <?= number_format($z_tot_gen,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-success"><i class="bi bi-coin"></i></div><div class="hint">Collection</div></div><div class="val text-success"> <?= number_format($z_tot_col,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-info"><i class="bi bi-percent"></i></div><div class="hint">Discount</div></div><div class="val text-info"> <?= number_format($z_tot_dis,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-danger"><i class="bi bi-exclamation-octagon"></i></div><div class="hint">Total Due</div></div><div class="val text-danger"> <?= number_format($z_tot_due,2) ?></div></div></div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">Zones / Areas</div>
        <div class="card-body p-0">
          <?php if($summaryError): ?>
            <div class="alert alert-danger m-3">Failed to load summary: <?= h($summaryError) ?></div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:56px">SL</th>
                  <th>Area</th>
                  <th>Status</th>
                  <th class="text-end">Generated</th>
                  <th class="text-end">Collection</th>
                  <th class="text-end">Discount</th>
                  <th class="text-end">Due</th>
                  <th class="text-end">Ratio</th>
                </tr>
              </thead>
              <tbody>
                <?php if($zonesData): $sl=1; foreach($zonesData as $z): ?>
                <tr>
                  <td><?= $sl++ ?></td>
                  <td>
                    <div class="fw-semibold"><?= h($z['zone_name']) ?></div>

                  </td>
                  <td>
                    <div class="small">
                      <span class="badge bg-success-subtle text-success border badge-pill">Active: <?= (int)$z['active_clients'] ?></span>
                      <span class="badge bg-danger-subtle text-danger border badge-pill ms-1">Inactive: <?= (int)$z['inactive_clients'] ?></span>
                      <span class="text-muted ms-1 small">Total: <?= (int)$z['total_clients'] ?></span>
                    </div>
                  </td>
                  <td class="text-end fw-semibold"> <?= number_format($z['generated'],2) ?></td>
                  <td class="text-end text-success fw-semibold"> <?= number_format($z['collection'],2) ?></td>
                  <td class="text-end text-primary"> <?= number_format($z['discount'],2) ?></td>
                  <td class="text-end text-danger fw-semibold"> <?= number_format($z['due'],2) ?></td>
                  <td class="text-end">
                    <div class="ratio-wrap">
                      <div class="d-flex justify-content-between small text-muted mb-1">
                        <span><?= number_format($z['ratio'],2) ?>%</span>
                        <span><?= $z['generated']>0? number_format(($z['collection']/$z['generated'])*100,0).'%' : '0%' ?></span>
                      </div>
                      <div class="progress"><div class="progress-bar" style="width: <?= max(0,min(100,$z['ratio'])) ?>%"></div></div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="8" class="text-center text-muted">No data.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?><!-- LIST VIEW -->

      <div class="text-left mb-2">
        <div class="summary d-inline-flex flex-wrap gap-4 fs-5 fs-md-4">
          <div><span><i class="fas fa-hand-holding-usd text-success"></i> Paid : <span class="fw-semibold text-success"> <?= number_format($month_paid_total,2) ?></span></span></div>
          <div>          <span><i class="fas fa-hand-holding-usd text-danger"></i> Due : <span class="fw-semibold text-danger"> <?= number_format($month_due_total,2) ?></span></span></div>
          
        </div>
      </div>

      <!-- Totals row -->


      <form class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2" method="GET">
        <div class="d-flex align-items-center gap-2">
          <label class="small text-muted mb-0" for="bill-limit">Show</label>
          <select id="bill-limit" name="limit" class="form-select form-select-sm" style="width: 90px;" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $opt): ?>
              <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <span class="small text-muted">entries</span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <label class="small text-muted mb-0" for="bill-search">Search:</label>
          <input id="bill-search" type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Client Code / Name / PPPoE / Mobile">
          <button class="btn btn-primary btn-sm" type="submit">Go</button>
        </div>
        <input type="hidden" name="month" value="<?= h($monthParam) ?>">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <input type="hidden" name="page" value="1">
      </form>

      <div class="table-responsive overflow-x billing-table-wrap">
        <table class="table table-striped table-hover table-sm align-middle billing-table">
          <thead>
            <tr>
              <th style="width:28px;"><input type="checkbox" aria-label="Select all"></th>
              <th>C.Code</th>
              <th><PPP class="ID">ID</PPP></th>
              <th>Cus. Name</th>
              <th>MobileNumber</th>
              <th>Zone</th>
              <th>Package</th>
              <th>Ex.Date</th>
              <th class="text-end">M.Bill</th>
              <th class="text-end">Received</th>
              <th class="text-end">BalanceDue</th>
              <th class="text-end">Advance</th>
              <th>PaymentDate</th>
              <th>Billing Status</th>
              <th>Custom Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if($rows): foreach($rows as $r):
              $invAmount = (float)($r['inv_amount']??0);
              $paid      = (float)($r['paid_amount']??0);
              $disc      = (float)($r['discount']??0);
              $remain    = max(0.0, $invAmount - ($isNetInvAmount?0:$disc) - $paid);
              $ledger    = (float)($r['ledger_balance']??0);
              $inv_balance = (float)($r['inv_balance']??0);
              $effective_due = isset($r['effective_due']) ? (float)$r['effective_due'] : $inv_balance;
              $due_balance = $effective_due > 0 ? $effective_due : 0.0;
              $pay_class = $due_balance>0.0001?'due':'zero';
              $advance   = (float)($r['advance_amount'] ?? 0);
              $vat       = (float)($r['vat_amount'] ?? 0);
              $m_bill    = (float)($r['monthly_bill'] ?? 0);
              if ($m_bill <= 0) { $m_bill = $invAmount; }
              $billing_status = strtolower(trim((string)($r['billing_status'] ?? '')));
              if ($billing_status === '') { $billing_status = strtolower(trim((string)($r['inv_status'] ?? ''))); }
              $client_status = strtolower(trim((string)($r['client_status'] ?? '')));
              $is_left = ((int)($r['is_left'] ?? 0) === 1) || in_array($client_status, ['left','terminated'], true);
              if ($is_left) {
                $bill_badge = 'dark';
                $bill_label = 'Left';
              } else {
                if ($billing_status === '') {
                  if ($remain <= 0.0001) {
                    $billing_status = 'paid';
                  } elseif ($paid > 0.0001) {
                    $billing_status = 'partial';
                  } else {
                    $billing_status = 'due';
                  }
                } elseif (in_array($billing_status, ['unpaid','clear','cleared'], true)) {
                  $billing_status = ($billing_status === 'unpaid') ? 'due' : 'paid';
                } elseif ($billing_status === 'unpaid') {
                  $billing_status = 'due';
                }
                $bill_badge = $billing_status==='paid'?'success':($billing_status==='partial'?'warning text-dark':($billing_status==='due'?'danger':'secondary'));
                $bill_label = ucfirst($billing_status);
              }

              $exp_date = trim((string)($r['expiry_date'] ?? ''));
              $pay_date = trim((string)($r['last_payment_date'] ?? ''));
              $pay_ts = $pay_date !== '' ? strtotime($pay_date) : false;
              $pay_date_fmt = $pay_ts ? date('M-d-Y', $pay_ts) : '-';
              // (বাংলা) Custom Status = মাইক্রোটিক সিক্রেট স্ট্যাটাস (clients.status)
              $secret_status = $client_status !== '' ? $client_status : (($r['period_active'] ?? 0) ? 'active' : '');
              $status_label = $secret_status !== '' ? ucfirst($secret_status) : 'Unknown';
              $status_badge = 'secondary';
              if (in_array($secret_status, ['active','online'], true))  $status_badge = 'success';
              if (in_array($secret_status, ['inactive','disabled','offline'], true)) $status_badge = 'secondary';
              if ($secret_status === 'expired') $status_badge = 'warning text-dark';
              if (in_array($secret_status, ['due','suspended','blocked'], true)) $status_badge = 'danger';
              if (in_array($secret_status, ['left','terminated'], true)) $status_badge = 'dark';

              $return = $cur_url;
              $invoice_id = (int)($r['invoice_id'] ?? 0);
              if ($invoice_id > 0) {
                $pay_url = 'payment_add.php?invoice_id='.$invoice_id.'&return='.rawurlencode($return);
                $pay_title = 'Pay (Full/Partial)';
              } else {
                $pay_url = 'invoice_new.php?client_id='.(int)$r['client_id'];
                $pay_title = 'Create Invoice';
              }
              $ledger_url = 'client_ledger.php?client_id='.(int)$r['client_id'].'&return='.rawurlencode($cur_url);
            ?>
            <?php $client_code = trim((string)($r['client_code'] ?? '')); ?>
            <tr class="<?= $inv_balance<=0.0001 ? 'table-success' : '' ?>">
              <td><input type="checkbox" aria-label="Select"></td>
              <td><?= $client_code !== '' ? h($client_code) : (int)$r['client_id'] ?></td>
              <td>
                <div class="fw-semibold"><?= h($r['pppoe_id'] ?? '') ?></div>
                <div class="text-muted small"><?= h($r['ip_address'] ?? '') ?></div>
              </td>
              <td>
                <div class="fw-semibold"><a class="text-decoration-none" href="client_view.php?id=<?= (int)$r['client_id'] ?>"><?= h($r['client_name']) ?></a></div>
                <div class="text-muted small"><?= h($r['sub_zone'] ?? '') ?><?= ($r['box_name'] ?? '') ? ' • '.h($r['box_name']) : '' ?></div>
              </td>
              <td><?= h($r['mobile'] ?? '-') ?></td>
              <td><?= h($r['zone_name'] ?? '-') ?></td>
              <td><?= h($r['package_name'] ?? '-') ?></td>
              <td><?= h($exp_date) ?></td>
              <td class="text-end"> <?= number_format($m_bill, 2) ?></td>
              <td class="text-end"> <?= number_format($paid, 2) ?></td>
              <td class="text-end payable <?= $pay_class ?>"> <?= number_format($due_balance, 2) ?></td>
              <td class="text-end"> <?= number_format($advance, 2) ?></td>
              <td><?= h($pay_date_fmt) ?></td>
              <td><span class="badge bg-<?= $bill_badge ?>"><?= h($bill_label) ?></span></td>
              <td><span class="badge bg-<?= $status_badge ?>"><?= h($status_label) ?></span></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <?php if ($pay_url !== ''): ?>
                    <a class="btn btn-outline-success" title="<?= h($pay_title) ?>" href="<?= h($pay_url) ?>"><i class="bi bi-cash-coin"></i> Pay</a>
                  <?php else: ?>
                    <button class="btn btn-outline-success" title="<?= h($pay_title) ?>" type="button" disabled><i class="bi bi-cash-coin"></i> Pay</button>
                  <?php endif; ?>
                  <?php if ($invoice_id > 0): ?>
                    <a class="btn btn-outline-secondary" title="Invoice" href="invoice_view.php?id=<?= $invoice_id ?>"><i class="bi bi-receipt"></i></a>
                  <?php else: ?>
                    <button class="btn btn-outline-secondary" title="Invoice" type="button" disabled><i class="bi bi-receipt"></i></button>
                  <?php endif; ?>
                  <!-- <a class="btn btn-outline-info" title="View Ledger" href="<?= h($ledger_url) ?>"><i class="bi bi-eye"></i></a> -->
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="17" class="text-center text-muted">No data found for this month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if($pages>1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
          <?php $qs=$_GET; $prev=max(1,$page-1); $next=min($pages,$page+1); ?>
          <li class="page-item <?= $page<=1?'disabled':'' ?>"><?php $qs['page']=$prev; ?><a class="page-link" href="?<?= h(http_build_query($qs)) ?>">Previous</a></li>
          <?php $start=max(1,$page-2); $end=min($pages,$start+4); $start=max(1,min($start,$end-4));
          for($p=$start;$p<=$end;$p++): $qs['page']=$p; ?>
            <li class="page-item <?= $p==$page?'active':'' ?>"><a class="page-link" href="?<?= h(http_build_query($qs)) ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><?php $qs['page']=$next; ?><a class="page-link" href="?<?= h(http_build_query($qs)) ?>">Next</a></li>
        </ul>
      </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<!-- Discount Manager Modal -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Manage Discounts</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="disc-info" class="mb-3 small text-muted">Loading…</div>
        <div class="mb-2">
          <div class="fw-semibold">Invoice-level Discount</div>
          <div id="inv-disc-row" class="d-flex align-items-center justify-content-between border rounded p-2">
            <span id="inv-disc-amt"> 0.00</span>
            <button id="btn-clear-inv" class="btn btn-outline-danger btn-sm" disabled>
              <i class="bi bi-x-circle"></i> Clear
            </button>
          </div>
        </div>
        <div class="mt-3">
          <div class="fw-semibold mb-1">Payment Discounts</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>#ID</th><th>Date</th><th>Method</th><th class="text-end">Discount</th><th class="text-end">Action</th></tr></thead>
              <tbody id="pay-disc-tbody">
                <tr><td colspan="5" class="text-center text-muted">No rows.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">Tip: Clearing will just set discount = 0 (soft delete).</small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$billing_boot = [
  'csrf' => $__csrf ?? '',
];
$jsVer = @filemtime(__DIR__ . '/js/billing.js') ?: time();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
window.BILLING_BOOT = <?= json_encode($billing_boot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/public/js/billing.js?v=<?= $jsVer ?>"></script>

<?php if (isset($_GET['debug'])): ?>
<pre style="background:#111;color:#9f9;padding:12px;border-radius:8px;margin:12px">
invAmountCol    = <?= h($invAmountCol) . ($isNetInvAmount?' (NET)':' (GROSS)') . "\n" ?>
invoiceDiscCol  = <?= h($invoiceDiscCol ?? 'NULL') . "\n" ?>
hasPayDiscount  = <?= h($hasPayDiscount ? '1':'0') . "\n" ?>
payFk           = <?= h($payFk ?? 'NULL') . "\n" ?>
month_due_total = <?= number_format($month_due_total,2) . "\n" ?>
filtered_due_total = <?= number_format($filtered_due_total,2) . "\n" ?>
pages = <?= (int)$pages ?>, page = <?= (int)$page ?>, limit = <?= (int)$limit ?>, tab = <?= h($tab) . "\n" ?>
</pre>
<?php endif; ?>

</body>
</html>
