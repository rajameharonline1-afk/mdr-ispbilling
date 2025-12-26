<?php
// /public/ticket_add.php
declare(strict_types=1);
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Base table
$pdo->exec("
CREATE TABLE IF NOT EXISTS tickets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('open','in_progress','closed') DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY client_id (client_id),
  KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ensure extended columns
$extraCols = [
  'customer_name VARCHAR(255) NULL',
  'mobile_existing VARCHAR(50) NULL',
  'client_address VARCHAR(255) NULL',
  'zone VARCHAR(120) NULL',
  'billing_status VARCHAR(50) NULL',
  'monthly_bill DECIMAL(10,2) NULL',
  'last_paid_amount DECIMAL(10,2) NULL',
  'payment_status VARCHAR(50) NULL',
  'mikrotik_status VARCHAR(50) NULL',
  'uptime VARCHAR(100) NULL',
  'last_logout_time VARCHAR(100) NULL',
  'mac_caller_id VARCHAR(120) NULL',
  'ip_address VARCHAR(64) NULL',
  'device_vendor_name VARCHAR(120) NULL',
  'connectivity_status VARCHAR(50) NULL',
  'downloaded_data VARCHAR(50) NULL',
  'uploaded_data VARCHAR(50) NULL',
  'client_mac_address VARCHAR(120) NULL',
  'olt_port VARCHAR(120) NULL',
  'distance VARCHAR(50) NULL',
  'problem_category VARCHAR(120) NULL',
  'problem_priority VARCHAR(120) NULL',
  'complained_number VARCHAR(120) NULL',
  'olt_name VARCHAR(120) NULL',
  'optical_power VARCHAR(50) NULL',
  'onu_mac_serial VARCHAR(120) NULL',
  'onu_status VARCHAR(50) NULL',
  'last_deregister_time VARCHAR(120) NULL',
  'last_deregister_reasons VARCHAR(255) NULL',
  'description TEXT NULL',
  'send_sms TINYINT(1) DEFAULT 0'
];
foreach ($extraCols as $def) {
    [$col] = explode(' ', $def, 2);
    $chk = $pdo->prepare("SHOW COLUMNS FROM tickets LIKE ?");
    $chk->execute([$col]);
    if (!$chk->fetch()) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN $def");
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$ticket = null;
if ($isEdit) {
    $st = $pdo->prepare("SELECT * FROM tickets WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $ticket = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        $_SESSION['flash_error'] = 'Ticket not found.';
        header('Location: /public/tickets.php');
        exit;
    }
}

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)($_POST['client_id'] ?? 0);
    $subject   = trim($_POST['subject'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    $status    = $_POST['status'] ?? 'open';

    $customer_name    = trim($_POST['customer_name'] ?? '');
    $mobile_existing  = trim($_POST['mobile_existing'] ?? '');
    $client_address   = trim($_POST['client_address'] ?? '');
    $zone             = trim($_POST['zone'] ?? '');
    $billing_status   = trim($_POST['billing_status'] ?? '');
    $monthly_bill     = (float)($_POST['monthly_bill'] ?? 0);
    $last_paid_amount = (float)($_POST['last_paid_amount'] ?? 0);
    $payment_status   = trim($_POST['payment_status'] ?? '');
    $mikrotik_status  = trim($_POST['mikrotik_status'] ?? '');
    $uptime           = trim($_POST['uptime'] ?? '');
    $last_logout_time = trim($_POST['last_logout_time'] ?? '');
    $mac_caller_id    = trim($_POST['mac_caller_id'] ?? '');
    $ip_address       = trim($_POST['ip_address'] ?? '');
    $device_vendor_name = trim($_POST['device_vendor_name'] ?? '');
    $connectivity_status = trim($_POST['connectivity_status'] ?? '');
    $downloaded_data  = trim($_POST['downloaded_data'] ?? '');
    $uploaded_data    = trim($_POST['uploaded_data'] ?? '');
    $client_mac_address = trim($_POST['client_mac_address'] ?? '');
    $olt_port         = trim($_POST['olt_port'] ?? '');
    $distance         = trim($_POST['distance'] ?? '');
    $problem_category = trim($_POST['problem_category'] ?? '');
    $problem_priority = trim($_POST['problem_priority'] ?? '');
    $complained_number= trim($_POST['complained_number'] ?? '');
    $olt_name         = trim($_POST['olt_name'] ?? '');
    $optical_power    = trim($_POST['optical_power'] ?? '');
    $onu_mac_serial   = trim($_POST['onu_mac_serial'] ?? '');
    $onu_status       = trim($_POST['onu_status'] ?? '');
    $last_deregister_time   = trim($_POST['last_deregister_time'] ?? '');
    $last_deregister_reasons= trim($_POST['last_deregister_reasons'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $send_sms         = isset($_POST['send_sms']) ? 1 : 0;

    if ($client_id <= 0) $errors[] = 'Client is required.';
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '') $errors[] = 'Message is required.';
    if (!in_array($status, ['open','in_progress','closed'], true)) $status = 'open';

    if (!$errors) {
        if ($isEdit) {
            $st = $pdo->prepare("UPDATE tickets SET client_id=?, subject=?, message=?, status=?, customer_name=?, mobile_existing=?, client_address=?, zone=?, billing_status=?, monthly_bill=?, last_paid_amount=?, payment_status=?, mikrotik_status=?, uptime=?, last_logout_time=?, mac_caller_id=?, ip_address=?, device_vendor_name=?, connectivity_status=?, downloaded_data=?, uploaded_data=?, client_mac_address=?, olt_port=?, distance=?, problem_category=?, problem_priority=?, complained_number=?, olt_name=?, optical_power=?, onu_mac_serial=?, onu_status=?, last_deregister_time=?, last_deregister_reasons=?, description=?, send_sms=? WHERE id=?");
            $st->execute([$client_id, $subject, $message, $status, $customer_name, $mobile_existing, $client_address, $zone, $billing_status, $monthly_bill, $last_paid_amount, $payment_status, $mikrotik_status, $uptime, $last_logout_time, $mac_caller_id, $ip_address, $device_vendor_name, $connectivity_status, $downloaded_data, $uploaded_data, $client_mac_address, $olt_port, $distance, $problem_category, $problem_priority, $complained_number, $olt_name, $optical_power, $onu_mac_serial, $onu_status, $last_deregister_time, $last_deregister_reasons, $description, $send_sms, $id]);
            $_SESSION['flash_success'] = 'Ticket updated successfully.';
        } else {
            $st = $pdo->prepare("INSERT INTO tickets (client_id, subject, message, status, customer_name, mobile_existing, client_address, zone, billing_status, monthly_bill, last_paid_amount, payment_status, mikrotik_status, uptime, last_logout_time, mac_caller_id, ip_address, device_vendor_name, connectivity_status, downloaded_data, uploaded_data, client_mac_address, olt_port, distance, problem_category, problem_priority, complained_number, olt_name, optical_power, onu_mac_serial, onu_status, last_deregister_time, last_deregister_reasons, description, send_sms) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$client_id, $subject, $message, $status, $customer_name, $mobile_existing, $client_address, $zone, $billing_status, $monthly_bill, $last_paid_amount, $payment_status, $mikrotik_status, $uptime, $last_logout_time, $mac_caller_id, $ip_address, $device_vendor_name, $connectivity_status, $downloaded_data, $uploaded_data, $client_mac_address, $olt_port, $distance, $problem_category, $problem_priority, $complained_number, $olt_name, $optical_power, $onu_mac_serial, $onu_status, $last_deregister_time, $last_deregister_reasons, $description, $send_sms]);
            $_SESSION['flash_success'] = 'Ticket created successfully.';
        }
        header('Location: /public/tickets.php');
        exit;
    }
}

$page_title = $isEdit ? 'Edit Ticket' : 'Add Ticket';
$totalAll = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$totalOpen = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
$totalIP   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in_progress'")->fetchColumn();
$totalClosed = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='closed'")->fetchColumn();
require __DIR__ . '/../partials/partials_header.php';
?>
<style>
.ticket-hero{
  background: linear-gradient(120deg, #0ea5e9, #6366f1);
  color:#fff;
  border-radius: 14px;
  padding:18px 20px;
  box-shadow:0 8px 18px rgba(0,0,0,.15);
}
.ticket-badges{ display:flex; gap:10px; flex-wrap:wrap; }
.ticket-badge{
  background: rgba(255,255,255,.1);
  border:1px solid rgba(255,255,255,.25);
  border-radius:12px;
  padding:10px 14px;
  min-width:140px;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
}
.ticket-badge .num{ font-size:22px; font-weight:800; line-height:1; }
.ticket-badge .lbl{ font-size:12px; opacity:.9; margin:0; }
.ticket-card{
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  box-shadow:0 10px 24px rgba(16,24,40,.08);
}
.ticket-form-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit,minmax(240px,1fr));
  gap:14px;
}
.pill-label{ font-weight:700; color:#0f172a; font-size:13px; }
.pill-control{
  border-radius:10px;
  border:1px solid #cbd5e1;
  padding:10px 12px;
  height:44px;
}
.btn-soft{
  border-radius: 10px;
  padding: 10px 16px;
  box-shadow: 0 8px 16px rgba(0,0,0,.12);
}
.btn-soft-danger{ background:#f87171; color:#fff; border:0; }
.btn-soft-secondary{ background:#e5e7eb; color:#0f172a; border:0; }
.btn-soft-primary{ background:#2563eb; color:#fff; border:0; }
.pill-chip-group{ display:flex; gap:8px; flex-wrap:wrap; }
.pill-chip{
  padding:10px 14px;
  border-radius:10px;
  border:1px solid #cbd5e1;
  cursor:pointer;
  font-weight:600;
  user-select:none;
}
.pill-chip.active-green{ background:#16a34a; color:#fff; border-color:#16a34a; }
.pill-chip.active-blue{ background:#2563eb; color:#fff; border-color:#2563eb; }
.pill-chip.active-orange{ background:#f59e0b; color:#fff; border-color:#f59e0b; }
.pill-chip.active-red{ background:#ef4444; color:#fff; border-color:#ef4444; }
.group-block{
  border:1px solid #e5e7eb;
  border-radius:12px;
  padding:12px;
  margin-bottom:12px;
  box-shadow: inset 0 0 0 1px rgba(241,245,249,.7);
}
.section-title{
  font-weight:700;
  color:#0f172a;
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:8px;
}
</style>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="m-0"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h4>
    <a class="btn btn-outline-secondary btn-sm" href="/public/tickets.php"><i class="bi bi-arrow-left"></i> Back to Tickets</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="ticket-hero mb-3">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-2">
      <div class="fw-semibold text-white">Support & Ticketing</div>
      <div class="badge bg-light text-dark">New Ticket</div>
    </div>
    <div class="ticket-badges">
      <div class="ticket-badge">
        <div class="num"><?= $totalAll ?></div>
        <p class="lbl mb-0">Total Tickets</p>
      </div>
      <div class="ticket-badge">
        <div class="num"><?= $totalOpen ?></div>
        <p class="lbl mb-0">Open</p>
      </div>
      <div class="ticket-badge">
        <div class="num"><?= $totalIP ?></div>
        <p class="lbl mb-0">In Progress</p>
      </div>
      <div class="ticket-badge">
        <div class="num"><?= $totalClosed ?></div>
        <p class="lbl mb-0">Closed</p>
      </div>
    </div>
  </div>

  <div class="ticket-card p-3">
    <form method="post" class="ticket-form-grid">
      <div class="group-block" style="grid-column:1 / -1;">
        <div class="section-title">Primary Info</div>
        <div class="ticket-form-grid">
          <div><label class="pill-label">User Name (ID)</label><input type="text" name="customer_name" class="form-control pill-control" value="<?= htmlspecialchars($ticket['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Mobile Number (Existing)</label><input type="text" name="mobile_existing" class="form-control pill-control" value="<?= htmlspecialchars($ticket['mobile_existing'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Client Address</label><input type="text" name="client_address" class="form-control pill-control" value="<?= htmlspecialchars($ticket['client_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Zone</label><input type="text" name="zone" class="form-control pill-control" value="<?= htmlspecialchars($ticket['zone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Billing Status</label><input type="text" name="billing_status" class="form-control pill-control" value="<?= htmlspecialchars($ticket['billing_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Monthly Bill</label><input type="number" step="0.01" name="monthly_bill" class="form-control pill-control" value="<?= htmlspecialchars($ticket['monthly_bill'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Last Paid Amount</label><input type="number" step="0.01" name="last_paid_amount" class="form-control pill-control" value="<?= htmlspecialchars($ticket['last_paid_amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div>
            <label class="pill-label">Payment Status</label>
            <div class="pill-chip-group">
              <?php $pay=strtolower((string)($ticket['payment_status'] ?? '')); $payOpts=['Paid'=>'active-green','Advanced'=>'active-blue','Due'=>'active-orange']; foreach($payOpts as $lbl=>$cls){$active=strcasecmp($pay,$lbl)===0?$cls.' active':''; echo "<span class=\"pill-chip {$active}\" data-target=\"payment_status\" data-value=\"{$lbl}\">{$lbl}</span>";} ?>
              <input type="hidden" name="payment_status" value="<?= htmlspecialchars($ticket['payment_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div>
            <label class="pill-label">Mikrotik Status</label>
            <div class="pill-chip-group">
              <?php $mk=strtolower((string)($ticket['mikrotik_status'] ?? '')); $mkOpts=['Enabled'=>'active-green','Disabled'=>'active-red']; foreach($mkOpts as $lbl=>$cls){$active=strcasecmp($mk,$lbl)===0?$cls.' active':''; echo "<span class=\"pill-chip {$active}\" data-target=\"mikrotik_status\" data-value=\"{$lbl}\">{$lbl}</span>";} ?>
              <input type="hidden" name="mikrotik_status" value="<?= htmlspecialchars($ticket['mikrotik_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div>
            <label class="pill-label">Status</label>
            <select name="status" class="form-select pill-control">
              <?php $statusVal=$ticket['status'] ?? 'open'; foreach(['open'=>'Open','in_progress'=>'In Progress','closed'=>'Closed'] as $k=>$lbl){$sel=$statusVal===$k?'selected':''; echo "<option value=\"{$k}\" {$sel}>{$lbl}</option>";} ?>
            </select>
          </div>
          <div>
            <label class="pill-label">Client</label>
            <select name="client_id" class="form-select pill-control" required>
              <option value="">Select client</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id']; ?>" <?= ($ticket['client_id'] ?? null)==$c['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($c['name'] ?? ('ID '.$c['id']), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="group-block" style="grid-column:1 / -1;">
        <div class="section-title">Session / Usage</div>
        <div class="ticket-form-grid">
          <div><label class="pill-label">Uptime</label><input type="text" name="uptime" class="form-control pill-control" value="<?= htmlspecialchars($ticket['uptime'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Last Logout Time</label><input type="text" name="last_logout_time" class="form-control pill-control" value="<?= htmlspecialchars($ticket['last_logout_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">MAC Address / Caller ID</label><input type="text" name="mac_caller_id" class="form-control pill-control" value="<?= htmlspecialchars($ticket['mac_caller_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">IP Address</label><input type="text" name="ip_address" class="form-control pill-control" value="<?= htmlspecialchars($ticket['ip_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Device Vendor Name</label><input type="text" name="device_vendor_name" class="form-control pill-control" value="<?= htmlspecialchars($ticket['device_vendor_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div>
            <label class="pill-label">Connectivity Status</label>
            <div class="pill-chip-group">
              <?php $conn=strtolower((string)($ticket['connectivity_status'] ?? '')); $connOpts=['Online'=>'active-green','Connected'=>'active-green','Offline'=>'active-red']; foreach($connOpts as $lbl=>$cls){$active=strcasecmp($conn,$lbl)===0?$cls.' active':''; echo "<span class=\"pill-chip {$active}\" data-target=\"connectivity_status\" data-value=\"{$lbl}\">{$lbl}</span>";} ?>
              <input type="hidden" name="connectivity_status" value="<?= htmlspecialchars($ticket['connectivity_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div><label class="pill-label">Downloaded Data</label><input type="text" name="downloaded_data" class="form-control pill-control" value="<?= htmlspecialchars($ticket['downloaded_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Uploaded Data</label><input type="text" name="uploaded_data" class="form-control pill-control" value="<?= htmlspecialchars($ticket['uploaded_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
      </div>

      <div class="group-block" style="grid-column:1 / -1;">
        <div class="section-title">ONU Informations</div>
        <div class="ticket-form-grid">
          <div><label class="pill-label">Client MAC Address</label><input type="text" name="client_mac_address" class="form-control pill-control" value="<?= htmlspecialchars($ticket['client_mac_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">IP Address</label><input type="text" name="ip_address" class="form-control pill-control" value="<?= htmlspecialchars($ticket['ip_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">OLT Name</label><input type="text" name="olt_name" class="form-control pill-control" value="<?= htmlspecialchars($ticket['olt_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Optical Power</label><input type="text" name="optical_power" class="form-control pill-control" value="<?= htmlspecialchars($ticket['optical_power'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">OLT Port</label><input type="text" name="olt_port" class="form-control pill-control" value="<?= htmlspecialchars($ticket['olt_port'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">ONU MAC Address/Serial</label><input type="text" name="onu_mac_serial" class="form-control pill-control" value="<?= htmlspecialchars($ticket['onu_mac_serial'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div>
            <label class="pill-label">Status</label>
            <div class="pill-chip-group">
              <?php $onu=strtolower((string)($ticket['onu_status'] ?? '')); $onuOpts=['online'=>'active-green','offline'=>'active-red']; foreach($onuOpts as $lbl=>$cls){$active=strcasecmp($onu,$lbl)===0?$cls.' active':''; echo "<span class=\"pill-chip {$active}\" data-target=\"onu_status\" data-value=\"{$lbl}\">{$lbl}</span>";} ?>
              <input type="hidden" name="onu_status" value="<?= htmlspecialchars($ticket['onu_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
          <div><label class="pill-label">Last Deregister Time</label><input type="text" name="last_deregister_time" class="form-control pill-control" value="<?= htmlspecialchars($ticket['last_deregister_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Distance</label><input type="text" name="distance" class="form-control pill-control" value="<?= htmlspecialchars($ticket['distance'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Last Deregister Reasons</label><input type="text" name="last_deregister_reasons" class="form-control pill-control" value="<?= htmlspecialchars($ticket['last_deregister_reasons'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Description</label><input type="text" name="description" class="form-control pill-control" value="<?= htmlspecialchars($ticket['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
        </div>
      </div>

      <div class="group-block" style="grid-column:1 / -1;">
        <div class="section-title">Problem / Notes</div>
        <div class="ticket-form-grid">
          <div><label class="pill-label">Problem Category</label><input type="text" name="problem_category" class="form-control pill-control" value="<?= htmlspecialchars($ticket['problem_category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Problem Priority</label><input type="text" name="problem_priority" class="form-control pill-control" value="<?= htmlspecialchars($ticket['problem_priority'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div><label class="pill-label">Complained Number</label><input type="text" name="complained_number" class="form-control pill-control" value="<?= htmlspecialchars($ticket['complained_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div style="grid-column:1 / -1;"><label class="pill-label">Subject</label><input type="text" name="subject" class="form-control pill-control" required value="<?= htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
          <div style="grid-column:1 / -1;"><label class="pill-label">Message</label><textarea name="message" rows="6" class="form-control pill-control" required><?= htmlspecialchars($ticket['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
          <div class="d-flex align-items-center gap-2" style="grid-column:1 / -1;">
            <label class="pill-label m-0">Send SMS to Client?</label>
            <input type="checkbox" name="send_sms" value="1" <?= !empty($ticket['send_sms']) ? 'checked' : ''; ?>>
          </div>
          <div class="d-flex gap-2 mt-2" style="grid-column: 1 / -1;">
            <button type="button" class="btn btn-soft btn-soft-danger" onclick="document.forms[0].reset()">Cancel</button>
            <button type="reset" class="btn btn-soft btn-soft-secondary">Clear</button>
            <button type="submit" class="btn btn-soft btn-soft-primary ms-auto"><?= $isEdit ? 'Update Ticket' : 'Submit'; ?></button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  const chips = document.querySelectorAll('.pill-chip');
  chips.forEach(ch=>{
    ch.addEventListener('click', ()=>{
      const target = ch.getAttribute('data-target');
      const val = ch.getAttribute('data-value');
      const hidden = document.querySelector(`input[name="${target}"]`);
      if(!hidden) return;
      chips.forEach(c=>{
        if(c.getAttribute('data-target')===target){
          c.classList.remove('active-green','active-blue','active-orange','active-red','active');
        }
      });
      const cls = (ch.className.match(/active-\w+/) || [])[0] || '';
      ch.classList.add(cls || 'active-green','active');
      hidden.value = val;
    });
  });
})();
</script>
<?php require __DIR__ . '/../partials/partials_footer.php'; ?>
