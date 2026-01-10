<?php
// /public/sms_individual.php
// Individual SMS sending page.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/notify.php';

if (function_exists('require_perm')) {
  require_perm('send.sms');
}

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csrf = csrf_ensure_token();
$_active = 'sms_individual';
$page_title = 'Individual SMS';

$templates = [];
$template_map = [];
if (function_exists('tbl_exists') && tbl_exists($pdo, 'notification_templates')) {
  $st = $pdo->prepare("SELECT template_key, subject, body FROM notification_templates WHERE channel='sms' AND active=1 ORDER BY template_key ASC");
  $st->execute();
  $templates = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($templates as $t) {
    $template_map[(string)$t['template_key']] = (string)$t['body'];
  }
}

$alert = '';
$alert_kind = 'success';
$send_to_raw = '';
$template_key = '';
$message = '';
$sent = 0;
$failed = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $alert = 'Invalid CSRF token.';
    $alert_kind = 'danger';
  } else {
    $send_to_raw = trim((string)($_POST['send_to'] ?? ''));
    $template_key = trim((string)($_POST['template_key'] ?? ''));
    $message = trim((string)($_POST['sms_message'] ?? ''));

    if ($message === '' && $template_key !== '' && isset($template_map[$template_key])) {
      $message = trim($template_map[$template_key]);
    }

    $parts = preg_split('/[,\s]+/', $send_to_raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $numbers = [];
    foreach ($parts as $p) {
      $num = preg_replace('/[^0-9+]/', '', (string)$p);
      if ($num === '') continue;
      if (str_starts_with($num, '00')) $num = '+' . substr($num, 2);
      $numbers[] = $num;
    }
    $numbers = array_values(array_unique($numbers));

    if (!$numbers) {
      $alert = 'Please enter at least one mobile number.';
      $alert_kind = 'danger';
    } elseif ($message === '') {
      $alert = 'Please enter a message.';
      $alert_kind = 'danger';
    } else {
      $cfg = notify_cfg();
      foreach ($numbers as $num) {
        [$ok, $err] = notify_send_sms($num, $message, $cfg);
        if ($ok) {
          $sent++;
        } else {
          $failed++;
          $errors[] = $num . ': ' . $err;
        }
      }
      if ($failed === 0) {
        $alert = "SMS sent successfully. Sent: {$sent}.";
        $alert_kind = 'success';
        $send_to_raw = '';
      } else {
        $alert = "SMS sent: {$sent}. Failed: {$failed}.";
        $alert_kind = 'warning';
      }
    }
  }
}

require_once __DIR__ . '/../partials/partials_header.php';
?>
<div class="container-fluid py-3 sms-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="sms-page-icon"><i class="bi bi-chat-dots"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">Individual SMS</h4>
          <span class="text-muted small">SMS Sending</span>
        </div>
        <div class="text-muted small">Send single or multiple numbers from one place.</div>
      </div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-chat-left-dots me-1"></i> SMS Service
      <span class="mx-1">&rsaquo;</span> Individual SMS
    </div>
  </div>

  <?php if ($alert): ?>
    <div class="alert alert-<?php echo h($alert_kind); ?> py-2">
      <?php echo h($alert); ?>
      <?php if ($errors): ?>
        <div class="small mt-1"><?php echo h(implode(' | ', $errors)); ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card sms-card">
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">Send To</label>
              <input type="text" name="send_to" class="form-control" value="<?php echo h($send_to_raw); ?>" placeholder="ex: 8801670000900, 8801670000000">
              <div class="form-text">Separate multiple numbers with comma.</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">Select Template</label>
              <select name="template_key" class="form-select" id="smsTemplate">
                <option value="">Select</option>
                <?php foreach ($templates as $t): ?>
                  <?php
                    $key = (string)$t['template_key'];
                    $label = trim((string)($t['subject'] ?? '')) ?: $key;
                  ?>
                  <option value="<?php echo h($key); ?>" data-body="<?php echo h((string)$t['body']); ?>" <?php echo $template_key === $key ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">Length</label>
              <input type="text" id="smsLength" class="form-control" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">Cost</label>
              <input type="text" id="smsCost" class="form-control" readonly>
            </div>
          </div>
          <div class="col-lg-6">
            <label class="form-label text-uppercase small fw-semibold">SMS Description</label>
            <textarea name="sms_message" id="smsMessage" class="form-control sms-textarea" rows="12" placeholder="Write your message here..."><?php echo h($message); ?></textarea>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-4">
          <button type="submit" class="btn btn-primary sms-send-btn">
            <i class="bi bi-send me-1"></i> Send
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const msg = document.getElementById('smsMessage');
  const len = document.getElementById('smsLength');
  const cost = document.getElementById('smsCost');
  const tpl = document.getElementById('smsTemplate');

  function calc(){
    if (!msg || !len || !cost) return;
    const text = msg.value || '';
    const unicode = /[^\x00-\x7F]/.test(text);
    const perSeg = unicode ? 70 : 160;
    const segments = text.length ? Math.ceil(text.length / perSeg) : 0;
    len.value = text.length ? (text.length + ' chars (' + segments + ' SMS)') : '';
    cost.value = segments ? String(segments) : '';
  }

  if (tpl) {
    tpl.addEventListener('change', () => {
      const opt = tpl.options[tpl.selectedIndex];
      const body = opt ? opt.getAttribute('data-body') : '';
      if (body) {
        msg.value = body;
      }
      calc();
    });
  }

  if (msg) {
    msg.addEventListener('input', calc);
  }

  calc();
})();
</script>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
