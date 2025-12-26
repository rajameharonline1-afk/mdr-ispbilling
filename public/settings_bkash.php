<?php
// /public/settings_bkash.php
// Purpose: Save bKash PGW + Webhook settings into settings table and use across project.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/settings_store.php';

if (function_exists('require_perm')) {
  require_perm('settings.manage');
}

$csrf = csrf_ensure_token();
$_active = 'settings_bkash';
$page_title = 'bKash PGW & Webhook Settings';

$keys = [
  'BKASH_MERCHANT_NUMBER',
  'BKASH_BASE_URL',
  'BKASH_APP_KEY',
  'BKASH_APP_SECRET',
  'BKASH_USERNAME',
  'BKASH_PASSWORD',
  'BKASH_WEBHOOK_TOKEN',
  'BKASH_WEBHOOK_IP_WHITELIST',
];

$msg = '';
$kind = 'success';

// Load current
$cur = settings_get_many($keys);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $msg = 'CSRF টোকেন সঠিক নয়।';
    $kind = 'danger';
  } else {
    // Save: secrets keep old if blank
    $pairs = [];
    foreach ($keys as $k) {
      $v = trim((string)($_POST[$k] ?? ''));
      $isSecret = in_array($k, ['BKASH_APP_SECRET','BKASH_PASSWORD','BKASH_WEBHOOK_TOKEN'], true);
      if ($isSecret && $v === '') {
        continue; // don't overwrite secrets with empty
      }
      $pairs[$k] = $v;
    }
    if (settings_set_many($pairs)) {
      $msg = 'bKash সেটিংস সেভ হয়েছে।';
      $kind = 'success';
      // reload
      $cur = settings_get_many($keys);
    } else {
      $msg = 'সেভ ব্যর্থ হয়েছে।';
      $kind = 'danger';
    }
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

include __DIR__ . '/../partials/partials_header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-credit-card-2-front"></i> bKash PGW & Webhook Settings</h4>
      <div class="text-muted">এই সেটিংসগুলো `settings` টেবিলে সেভ হবে এবং পুরো প্রজেক্টে ব্যবহার হবে।</div>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= h($kind) ?> shadow-sm"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <strong>Gateway (Live/Sandbox) Credentials</strong>
        </div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <div class="col-12">
              <label class="form-label">Merchant Number</label>
              <input class="form-control" name="BKASH_MERCHANT_NUMBER" value="<?= h($cur['BKASH_MERCHANT_NUMBER'] ?? '') ?>" placeholder="01303326003">
              <div class="form-text">পেমেন্ট পেজে এই নম্বর দেখাবে।</div>
            </div>

            <div class="col-12">
              <label class="form-label">Base URL</label>
              <input class="form-control" name="BKASH_BASE_URL" value="<?= h($cur['BKASH_BASE_URL'] ?? '') ?>" placeholder="https://tokenized.pay.bka.sh/v1.2.0-beta">
              <div class="form-text">Live/Sandbox URL bKash থেকে যেটা দিয়েছেন।</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">App Key (X-APP-Key)</label>
              <input class="form-control" name="BKASH_APP_KEY" value="<?= h($cur['BKASH_APP_KEY'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label">App Secret</label>
              <input class="form-control" type="password" name="BKASH_APP_SECRET" value="" placeholder="<?= ($cur['BKASH_APP_SECRET'] ?? '') !== '' ? '******** (saved)' : '' ?>" autocomplete="new-password">
              <div class="form-text">খালি রাখলে আগেরটা অপরিবর্তিত থাকবে।</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Username</label>
              <input class="form-control" name="BKASH_USERNAME" value="<?= h($cur['BKASH_USERNAME'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password</label>
              <input class="form-control" type="password" name="BKASH_PASSWORD" value="" placeholder="<?= ($cur['BKASH_PASSWORD'] ?? '') !== '' ? '******** (saved)' : '' ?>" autocomplete="new-password">
              <div class="form-text">খালি রাখলে আগেরটা অপরিবর্তিত থাকবে।</div>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><i class="bi bi-save2"></i> Save</button>
              <a class="btn btn-outline-secondary" href="/public/settings_bkash.php"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <strong>Webhook</strong>
        </div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <div class="col-12">
              <label class="form-label">Webhook Token</label>
              <input class="form-control" type="password" name="BKASH_WEBHOOK_TOKEN" value="" placeholder="<?= ($cur['BKASH_WEBHOOK_TOKEN'] ?? '') !== '' ? '******** (saved)' : '' ?>" autocomplete="new-password">
              <div class="form-text">খালি রাখলে আগেরটা অপরিবর্তিত থাকবে।</div>
            </div>

            <div class="col-12">
              <label class="form-label">IP Whitelist (optional)</label>
              <input class="form-control" name="BKASH_WEBHOOK_IP_WHITELIST" value="<?= h($cur['BKASH_WEBHOOK_IP_WHITELIST'] ?? '') ?>" placeholder="1.2.3.4, 5.6.7.8">
            </div>

            <div class="col-12">
              <div class="alert alert-info mb-0">
                Endpoint: <code>/api/bkash_webhook.php?token=YOUR_TOKEN</code><br>
                Method: <code>POST</code>, Content-Type: <code>application/json</code>
              </div>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><i class="bi bi-save2"></i> Save</button>
              <a class="btn btn-outline-secondary" href="/api/bkash_webhook.php?token=TEST" target="_blank" rel="noopener">Test (GET)</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <div class="fw-semibold mb-1">নোট</div>
          <ul class="mb-0 small text-muted">
            <li>লাইভ পেমেন্ট অটো অ্যাপ্লাই হতে হলে webhook-এ <code>trxID</code> ইউনিক এবং <code>transactionStatus=Completed</code> থাকতে হবে।</li>
            <li>অটো-ম্যাপিংয়ের জন্য webhook payload-এর <code>merchantInvoiceNumber</code> এ <code>pppoe_id</code>/<code>client_code</code>/<code>invoice_number</code দিন।</li>
            <li>ক্রন: <code>cron/auto_bkash_apply.php</code> প্রতি ৫ মিনিটে চলবে (আগেই সেট করা)।</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>

