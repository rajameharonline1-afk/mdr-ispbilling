<?php
// (বাংলা) পেজের PHP লজিক app/PHP/client_view_logic.php এ সরানো হয়েছে
require_once __DIR__ . '/../app/PHP/client_view_logic.php';
include __DIR__ . '/../partials/partials_header.php';
$clientViewCssVer = @filemtime(__DIR__ . '/css/client_view.css') ?: time();
?>
<!-- CSRF for JS -->
<meta name="csrf-token" content="<?= h($csrf) ?>">
<!-- (বাংলা) Client view স্টাইল assets/css/custom_modern.css এ রাখা হয়েছে -->
<link rel="stylesheet" href="/public/css/client_view.css?v=<?= $clientViewCssVer ?>">

<div class="container py-3 text-start">

  <!-- Header -->
  <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="header-avatar">
        <?php if ($photo_url): ?>
          <img src="<?= h($photo_url) ?>" referrerpolicy="no-referrer" alt="<?= h($client['name'] ?? 'Photo') ?>">
        <?php else: ?>
          <div class="avatar-fallback"><?= h($client_initial) ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-column">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person-vcard"></i>
          <!-- <span class="fw-bold">Details:<?= h($client['name']) ?></span> -->
          <span class="fw-bold">Customer Information</span>
          
        </div>
      </div>
    </div>

    <div class="ms-auto d-flex flex-wrap gap-2">
      <a href="/public/clients.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
      <a href="/public/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Edit Info</a>
      <a href="/public/audit_logs.php?client_id=<?= (int)$client['id'] ?>" class="btn btn-outline-dark btn-sm" target="_blank" rel="noopener"><i class="bi bi-clock-history"></i> Logs</a>
      <?php if (!$isLeft): ?>
        <?php if ($stVal === 'active'): ?>
          <button class="btn btn-danger btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>, 'disable')"><i class="bi bi-x-square"></i> Disable</button>
        <?php else: ?>
          <button class="btn btn-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>, 'enable')"><i class="bi bi-file-check"></i> Enable</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isLeft): ?>
    <div class="alert alert-dark d-flex align-items-center" role="alert">
      <i class="bi bi-person-dash me-2"></i>
      This client is marked as <strong class="ms-1">Left</strong>. Router actions are disabled.
    </div>
  <?php endif; ?>

  <div class="row g-3">

    <!-- Account Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title">Account Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr><td class="k"><i class="bi bi-upc-scan"></i> Client ID</td><td class="v mono"><?= h($client['id'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-person"></i> Name</td><td class="v"><?= h($client['name'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-geo-alt"></i> Address</td><td class="v"><?= nl2br(h($client['address'] ?: '-')) ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-telephone"></i> Mobile No.</td>
                <td class="v">
                  <?php if ($m): ?>
                    <span class="me-1"><?= h($m) ?></span><br>
                    <a class="btn btn-outline-secondary btn-sm me-1" href="tel:+88<?= h($m) ?>" title="Call"><i class="bi bi-telephone"></i></a>
                    <a class="btn btn-outline-secondary btn-sm me-1" href="sms:+88<?= h($m) ?>" title="SMS"><i class="bi bi-chat-dots"></i></a>
                    <a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/+88<?= preg_replace('/\D/','',$m) ?>" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                  <?php else: ?>-<?php endif; ?>
                </td>
              </tr>
              <tr><td class="k"><i class="bi bi-envelope"></i> Email</td><td class="v"><?= h($client['email'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-card-list"></i> NID No.</td><td class="v"><?= h($client['nid'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-card-list"></i> DOB</td><td class="v"><?= h($client['dob'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Area</td><td class="v"><?= h($client['area'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Sub Zone</td><td class="v"><?= h($client['sub_zone'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-box2"></i> Box</td><td class="v"><?= h($client['box'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Join Date</td><td class="v"><?= h($client['join_date'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-calendar2-week"></i> Update</td><td class="v"><?= h($client['updated_at'] ?? '-') ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <a href="/public/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Edit Info</a>
          <?php if ($m): ?><a href="sms:<?= h($m) ?>" class="btn btn-success btn-sm"><i class="bi bi-envelope"></i> Send SMS</a><?php endif; ?>
          <a href="/public/audit_logs.php?client_id=<?= (int)$client['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm"><i class="bi bi-clock-history"></i> Logs</a>
        </div>
      </div>
    </div>

    <!-- Billing Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title d-flex justify-content-between align-items-center">Billing Information<span class="badge <?= $badge ?>"><?= $stLabel ?></span></div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr><td class="k"><i class="bi bi-diagram-3"></i>Conn. Type</td><td class="v mono"><?= strtoupper($client['connection_type'] ?? 'PPPOE') ?></td></tr>
              <tr><td class="k"><i class="bi bi-box2-fill"></i>Package</td><td class="v fw-bold"><?= h($client['package_name'] ?: 'N/A') ?></td></tr>
              <tr><td class="k"><i class="bi bi-cash-coin"></i>Packg Price</td><td class="v"><?= h($client['monthly_bill'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-arrow-repeat"></i>Bill Cycle</td><td class="v">Monthly</td></tr>
              <tr><td class="k"><i class="bi bi-ui-checks"></i>Bill Type</td><td class="v">Prepaid</td></tr>
              <tr>
                <td class="k"><i class="bi bi-flag"></i> Bill Status</td>
                <td class="v">
                  <?php $expired = !empty($client['expiry_date']) && (strtotime($client['expiry_date']) < time()); ?>
                  <?php if ($expired): ?><span class="text-danger fw-bold">Expired</span>
                  <?php else: ?><span class="text-success">Running</span><?php endif; ?>
                  <span class="ms-3"><i class="bi bi-circle-fill <?= ($stVal==='active')?'text-success':'text-danger' ?>"></i> <span class="ms-1"><?= ($stVal==='active')? 'Enabled':'Disabled' ?></span></span>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-calendar-date"></i> Expiry Date</td>
                <td class="v">
                  <span><?= h($client['expiry_date'] ?? '-') ?></span>
                  <button class="btn btn-outline-secondary btn-sm ms-1" title="Calendar"><i class="bi bi-calendar3"></i></button>
                </td>
              </tr>
              <tr><td class="k"><i class="bi bi-wallet2"></i> Balance</td><td class="v"><span class="badge <?= $displayClass ?>"><?= number_format($display_balance,2) ?> (<?= $displayText ?>)</span></td></tr>
              <tr><td class="k"><i class="bi bi-person-check"></i>Connect By</td><td class="v"><?= h($client['created_by'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-geo"></i> Location</td><td class="v"><?= h($client['area'] ?: '-') ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <!-- Pay Bill: always enabled -->
          <a href="<?= h($pay_url) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-cash-coin"></i> Pay Bill
          </a>

          <!-- Renew (invoice create) -->
          <button id="btnRenew" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#renewModal" title="Create Invoice">
            <i class="bi bi-receipt"></i> Create Invoice
          </button>

          <a href="/public/client_invoices.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-receipt"></i> Invoices</a>
          <a href="/public/client_payments.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-cash-coin"></i> Payments</a>

          <?php if (!$isLeft): ?>
            <?php if ($stVal==='active'): ?>
              <button class="btn btn-outline-danger btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'disable')"><i class="bi bi-x-octagon"></i> Disable</button>
              <button class="btn btn-outline-warning btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'kick')"><i class="bi bi-plug"></i> Disconnect</button>
            <?php else: ?>
              <button class="btn btn-outline-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'enable')"><i class="bi bi-check2-circle"></i> Enable</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Server Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title">Server Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr>
                <td class="k"><i class="bi bi-hdd-network"></i> Server</td>
                <td class="v"><?= h($client['router_name'] ?: '-') ?></td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-person-badge"></i> Username</td>
                <td class="v mono">
                  <span id="pppoe-username"><?= h($client['pppoe_id'] ?: '-') ?></span>
                  <button type="button"
                          class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#pppoe-username"
                          title="Copy Username">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-key"></i> Password</td>
                <td class="v mono">
                  <?php $pp = $mk_secret_pass ?: ($client['pppoe_pass'] ?? ($client['pppoe_password'] ?? ($client['ppp_pass'] ?? ''))); // (বাংলা) স্কিমা ভিন্নতা গার্ড ?>
                  <span id="ppp-mask" data-revealed="0"><?= $pp ? str_repeat('•', max(6, strlen($pp))) : '-' ?></span>
                  <?php if ($pp): ?>
                    <button class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                            data-copy="<?= h($pp) ?>" title="Copy">
                      <i class="bi bi-clipboard"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ms-1" id="ppp-eye" title="Show/Hide">
                      <i class="bi bi-eye"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-ethernet"></i>Router Mac</td>
                <td class="v mono">
                  <span id="router-mac"><?= $router_mac_display ? h($router_mac_display) : '—' ?></span>
                  <button type="button" id="btn-copy-router" class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#router-mac" title="Copy Router Mac">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr>
              <!-- <tr>
                <td class="k"><i class="bi bi-ethernet"></i> Active Mac</td>
                <td class="v mono">
                  <span id="active-mac">—</span>
                  <button id="btn-copy-active" class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#active-mac" title="Copy" style="display:none;">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr> -->
              <tr><td class="k"><i class="bi bi-cpu"></i> Vendor</td><td class="v" id="device-vendor"><?= $device_vendor ? h($device_vendor) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-pc-display"></i> IP Address</td><td class="v mono" id="live-ip"><?= h($live_ip) ?></td></tr>
              <tr><td class="k"><i class="bi bi-stopwatch"></i> Uptime</td><td class="v" id="uptime">—</td></tr>
              <tr>
                <td class="k"><i class="bi bi-wifi"></i> Status</td>
                <td class="v"><span id="live-status" class="badge <?= $is_online?'bg-success':'bg-danger' ?>"><?= $is_online?'Online':'Offline' ?></span></td>
              </tr>
              <tr><td class="k"><i class="bi bi-alarm"></i>Last Logout</td><td class="v" id="last-seen"><?= h($last_seen) ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-bar-chart-line"></i> Data Used</td>
                <td class="v"><span id="total-dl">—</span> Download <br> <span id="total-ul">—</span> Upload</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <a href="/public/client_live_graph.php?id=<?= (int)$client['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up"></i> Live Graph</a>
          <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-link-45deg"></i> Bind Mac</a>
          <?php if (!$isLeft): ?>
            <?php if ($stVal==='active'): ?>
              <button class="btn btn-outline-warning btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'kick')"><i class="bi bi-plug"></i> Disconnect</button>
            <?php else: ?>
              <button class="btn btn-outline-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'enable')"><i class="bi bi-plug"></i> Connect</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- OLT Information -->
    <div class="col-12 col-lg-4 mt-3 mt-lg-0">
      <div class="card-block h-100">
        <div class="card-title">OLT Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm table-borderless table-kv mb-0">
            <tbody>
              <tr><td class="k"><i class="bi bi-lightning-charge"></i> OLT</td><td class="v" id="olt-name"><?= $olt_linked && $olt_name ? h($olt_name) : ($olt_linked ? ('OLT #'.(int)$client['olt_id']) : '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-cpu"></i> Vendor</td><td class="v" id="olt-vendor"><?= $olt_linked && $olt_vendor ? h($olt_vendor) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-hdd-network"></i> Host/IP</td><td class="v" id="olt-host"><?= $olt_linked && $olt_host ? h($olt_host) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-diagram-2"></i> PON Port</td><td class="v" id="olt-port"><?= $pon_port_display ? h($pon_port_display) : ($pon_display ? h($pon_display) : ($pon_iface ? h($pon_iface) : '—')) ?></td></tr>
              <tr><td class="k"><i class="bi bi-disc"></i> ONU ID</td><td class="v" id="olt-onu"><?= ($onu_id_display !== null && $onu_id_display !== '' ? h((string)$onu_id_display) : '—') ?></td></tr>
              <tr><td class="k"><i class="bi bi-upc-scan"></i> ONU MAC</td><td class="v mono" id="olt-mac"><?= $onu_mac ? h(strtoupper($onu_mac)) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-calendar2-week"></i> Last Linked</td><td class="v" id="olt-linked-at"><?= $last_linked_display ? h($last_linked_display) : '—' ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-broadcast-pin"></i> Last Rx (dBm)</td>
                <td class="v">
                  <span id="olt-last-rx-value"><?= $rx_prefill !== null ? h($rx_prefill).' dBm' : '—' ?></span>
                  <?php if($rx_prefill_meta[0]): ?>
                    <span id="olt-last-rx-badge" class="badge <?= $rx_prefill_meta[1]; ?> ms-2"><?= $rx_prefill_meta[0]; ?></span>
                  <?php else: ?>
                    <span id="olt-last-rx-badge"></span>
                  <?php endif; ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <span id="olt-unlinked-hint" class="text-muted small" <?=$olt_linked?'style="display:none"':'';?>>No OLT linked</span>
          <a id="olt-view-link" href="/olt/index.php" class="btn btn-outline-primary btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-diagram-3"></i> View OLT</a>
          <a id="olt-onu-monitor-link" href="/public/onu_monitor.php<?= $olt_linked ? ('?olt_id='.(int)$client['olt_id']) : ''; ?>" class="btn btn-outline-secondary btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-broadcast-pin"></i> ONU Monitor</a>
          <a id="olt-mac-cache-link" href="/public/olt_mac_table.php<?= $olt_linked ? ('?olt_id='.(int)$client['olt_id']) : ''; ?>" class="btn btn-outline-info btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-table"></i> MAC Cache</a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===================== RENEW MODAL ===================== -->
<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="renewForm">
      <div class="modal-header">
        <h6 class="modal-title" id="renewModalLabel"><i class="bi bi-arrow-repeat"></i> Renew — <?= h($client['name']) ?> (<?= h($client['client_code']) ?>)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Months</label>
            <select name="months" id="rn_months" class="form-select form-select-sm">
              <?php for($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $i===1?'selected':'' ?>><?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="rn_amount" class="form-control form-control-sm"
                   value="<?= is_numeric($client['monthly_bill']??null)? (0+$client['monthly_bill']) : 0 ?>">
          </div>

          <div class="col-6">
            <label class="form-label">Method</label>
            <select name="method" id="rn_method" class="form-select form-select-sm">
              <option value="Cash">Cash</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
              <option value="Bank">Bank</option>
              <option value="Online">Online</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Invoice Date</label>
            <input type="date" name="invoice_date" id="rn_invoice_date" class="form-control form-control-sm"
                   value="<?= date('Y-m-d') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Note (optional)</label>
            <input type="text" name="note" id="rn_note" class="form-control form-control-sm" placeholder="e.g. Monthly renewal">
          </div>

          <div class="col-12 mt-2">
            <div class="alert alert-light border d-flex align-items-center gap-2 py-2 mb-0">
              <i class="bi bi-calendar-check text-primary"></i>
              <div>
                <div class="small text-muted">Current Expiry:</div>
                <div class="fw-semibold" id="rn_exp_current"><?= h($client['expiry_date'] ?: '—') ?></div>
              </div>
              <div class="ms-3">
                <div class="small text-muted">New Expiry (est.):</div>
                <div class="fw-semibold text-success" id="rn_exp_new">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="small text-muted">Package: <?= h($client['package_name'] ?: 'N/A') ?> • Bill: <?= h($client['monthly_bill'] ?? '0') ?></span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary btn-sm">Create Invoice & Renew</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- =================== /RENEW MODAL =================== -->

<?php
$client_view_boot = [
  'initialOltBinding' => $initialOltBinding,
  'clientId' => (int)($client['id'] ?? 0),
  'monthlyBill' => (float)($client['monthly_bill'] ?? 0),
  'expiryDate' => $client['expiry_date'] ?? '',
  'pp' => $pp ?? '',
];
$jsVer = @filemtime(__DIR__ . '/js/client_view.js') ?: time();
?>
<script>
window.CLIENT_VIEW_BOOT = <?= json_encode($client_view_boot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/public/js/client_view.js?v=<?= $jsVer ?>"></script>
<?php include __DIR__ . '/../partials/client_recent.php'; ?>
<?php include __DIR__ . '/../partials/client_ledger_widget.php'; ?>
<?php include __DIR__ . '/../partials/client_activity_widget.php'; ?>
<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
