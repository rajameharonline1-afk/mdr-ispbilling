<?php
// /public/sms_gateway.php
// SMS gateway setup.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/settings_store.php';

if (function_exists('require_perm')) {
  require_perm('send.sms');
}

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csrf = csrf_ensure_token();
$_active = 'sms_gateway';
$page_title = 'SMS Gateway Setup';

function table_exists(PDO $pdo, string $table): bool {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

$stat_balance = (float)settings_get('sms_balance', '0');
$stat_today = 0;
$stat_month = 0;
$stat_failed = 0;

if (table_exists($pdo, 'notifications')) {
  try {
    $stat_today = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE channel='sms' AND status='sent' AND DATE(sent_at)=CURDATE()")->fetchColumn();
    $stat_month = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE channel='sms' AND status='sent' AND YEAR(sent_at)=YEAR(CURDATE()) AND MONTH(sent_at)=MONTH(CURDATE())")->fetchColumn();
    $stat_failed = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE channel='sms' AND status='failed' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();
  } catch (Throwable $e) {
    $stat_today = 0;
    $stat_month = 0;
    $stat_failed = 0;
  }
}

$keys = [
  'sms_provider',
  'sms_user',
  'sms_sender',
  'sms_password',
  'sms_api_url',
  'sms_api_key',
];
$cur = settings_get_many($keys);

$alert = '';
$alert_kind = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $alert = 'Invalid CSRF token.';
    $alert_kind = 'danger';
  } else {
    $provider = trim((string)($_POST['sms_provider'] ?? ''));
    $user = trim((string)($_POST['sms_user'] ?? ''));
    $sender = trim((string)($_POST['sms_sender'] ?? ''));
    $pass = trim((string)($_POST['sms_password'] ?? ''));
    $api_url = trim((string)($_POST['sms_api_url'] ?? ''));
    $api_key = trim((string)($_POST['sms_api_key'] ?? ''));

    settings_set_many([
      'sms_provider' => $provider,
      'sms_user' => $user,
      'sms_sender' => $sender,
      'sms_password' => $pass,
      'sms_api_url' => $api_url,
      'sms_api_key' => $api_key,
    ]);

    $cur = settings_get_many($keys);
    $alert = 'SMS settings updated successfully.';
    $alert_kind = 'success';
  }
}

require_once __DIR__ . '/../partials/partials_header.php';
?>

<div class="container-fluid py-3 sms-gateway-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="sms-page-icon"><i class="fas fa-headset"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">SMS Service</h4>
          <span class="text-muted small">SMS Gateway Setup</span>
        </div>
      </div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-chat-left-dots me-1"></i> SMS Service
      <span class="mx-1">&rsaquo;</span> SMS Gateway Setup
    </div>
  </div>

  <?php if ($alert): ?>
    <div class="alert alert-<?php echo h($alert_kind); ?> py-2"><?php echo h($alert); ?></div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
      <div class="sms-stat-card stat-green">
        <div class="stat-icon"><i class="bi bi-envelope-fill"></i></div>
        <div>
          <div class="stat-label">SMS Balance</div>
          <div class="stat-value"><?php echo h(number_format($stat_balance, 2)); ?></div>
        </div>
        <div class="stat-footer">Total SMS Remaining Balance</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="sms-stat-card stat-cyan">
        <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
        <div>
          <div class="stat-label">Today&#39;s Send</div>
          <div class="stat-value"><?php echo (int)$stat_today; ?></div>
        </div>
        <div class="stat-footer">Total SMS Send Today</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="sms-stat-card stat-orange">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="stat-label">This Month Send</div>
          <div class="stat-value"><?php echo (int)$stat_month; ?></div>
        </div>
        <div class="stat-footer">Total SMS Send in This Month</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="sms-stat-card stat-red">
        <div class="stat-icon"><i class="bi bi-x-lg"></i></div>
        <div>
          <div class="stat-label">This Month Failed</div>
          <div class="stat-value"><?php echo (int)$stat_failed; ?></div>
        </div>
        <div class="stat-footer">Total SMS Sending Failed in This Month</div>
      </div>
    </div>
  </div>

  <div class="card sms-gateway-card">
    <div class="card-header">
      <i class="bi bi-chat-left-dots"></i> SMS Settings
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label">SMS Provider</label>
            <select name="sms_provider" class="form-select">
              <option value="">Select</option>
              <?php
                $providers = [
                  'generic' => 'Generic HTTP',
                  'masking' => 'Masking Provider',
                  'nonmasking' => 'Non-Masking Provider',
                  'other' => 'Other',
                ];
              ?>
              <?php foreach ($providers as $key => $label): ?>
                <option value="<?php echo h($key); ?>" <?php echo ($cur['sms_provider'] ?? '') === $key ? 'selected' : ''; ?>>
                  <?php echo h($label); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-6">
            <label class="form-label">SMS User Name</label>
            <input type="text" name="sms_user" class="form-control" value="<?php echo h((string)($cur['sms_user'] ?? '')); ?>" placeholder="Ex: Username">
          </div>
          <div class="col-lg-6">
            <label class="form-label">SMS Sender</label>
            <input type="text" name="sms_sender" class="form-control" value="<?php echo h((string)($cur['sms_sender'] ?? '')); ?>" placeholder="Ex: Name">
          </div>
          <div class="col-lg-6">
            <label class="form-label">SMS Password</label>
            <input type="password" name="sms_password" class="form-control" value="<?php echo h((string)($cur['sms_password'] ?? '')); ?>" placeholder="Ex: ********">
          </div>
        </div>

        <details class="mt-3">
          <summary class="text-muted small">Advanced settings</summary>
          <div class="row g-3 mt-2">
            <div class="col-lg-6">
              <label class="form-label">SMS API URL</label>
              <input type="text" name="sms_api_url" class="form-control" value="<?php echo h((string)($cur['sms_api_url'] ?? '')); ?>" placeholder="https://api.example.com/sms/send">
            </div>
            <div class="col-lg-6">
              <label class="form-label">SMS API Key</label>
              <input type="text" name="sms_api_key" class="form-control" value="<?php echo h((string)($cur['sms_api_key'] ?? '')); ?>" placeholder="API key">
            </div>
          </div>
        </details>

        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-primary btn-sm">
            <i class="bi bi-check-circle me-1"></i> Update SMS Settings
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
