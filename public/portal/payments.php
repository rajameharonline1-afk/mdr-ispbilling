<?php
// /public/portal/bkash.php
// UI English; বাংলা কমেন্ট
// বাংলা: কাস্টমার পোর্টালের bKash Send Money নির্দেশনা পেজ (স্ট্যাটিক)

declare(strict_types=1);

require_once __DIR__ . '/../../app/portal_require_login.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/settings_store.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// (বাংলা) bKash নম্বর — কনফিগ/ENV থেকে নেবে, না পেলে fallback
$BKASH_NUMBER = '';
if (function_exists('settings_get')) {
  $BKASH_NUMBER = (string)settings_get('BKASH_MERCHANT_NUMBER', '');
}
if ($BKASH_NUMBER !== '') {
  // ok
} elseif (defined('BKASH_MERCHANT_NUMBER') && BKASH_MERCHANT_NUMBER !== '') {
  $BKASH_NUMBER = BKASH_MERCHANT_NUMBER;
} elseif (($env = getenv('BKASH_MERCHANT_NUMBER')) !== false && $env !== '') {
  $BKASH_NUMBER = $env;
} else {
  $BKASH_NUMBER = '01732197767'; // fallback ডিফল্ট
}

// (বাংলা) Query params → prefills (?ref=...&amount=...)
$pref_ref    = trim((string)($_GET['ref'] ?? ''));
$pref_amount = (float)($_GET['amount'] ?? 0);
$mode        = strtolower(trim((string)($_GET['mode'] ?? ''))); // 'pgw' to highlight direct payment

// (বাংলা) সেশন/DB fallback
if ($pref_ref === '') {
  $pref_ref = $_SESSION['pppoe_id'] ?? $_SESSION['client_code'] ?? 'CLIENT-XXXX';
}
if ($pref_amount <= 0 && isset($_SESSION['due_amount'])) {
  $pref_amount = (float)$_SESSION['due_amount'];
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$page_title = "bKash Payment — Send Money";

/* ====== JPG guide image config ======
 * Web URL: ব্রাউজার যে পাথ থেকে ইমেজ লোড করবে
 * FS path: সার্ভারে আসল পাথ (exists/mtime cache-bust)
 * প্রজেক্ট কাঠামো: project/public/assets/images/bkash_send_money.jpg
 */
$GUIDE_IMAGE_URL = '/public/assets/images/bkash_send_money.jpg';
$PUBLIC_FS_ROOT  = realpath(__DIR__ . '/../..'); // => project/public
$GUIDE_IMAGE_FS  = $PUBLIC_FS_ROOT . '/assets/images/bkash_send_money.jpg';
$guide_exists    = is_file($GUIDE_IMAGE_FS);
$guide_url_final = $GUIDE_IMAGE_URL . ($guide_exists ? ('?v=' . filemtime($GUIDE_IMAGE_FS)) : '');

// (বাংলা) PGW কনফিগ আছে কিনা
$has_pgw = false;
if (function_exists('settings_get')) {
  $has_pgw =
    (string)settings_get('BKASH_BASE_URL', '') !== '' &&
    (string)settings_get('BKASH_APP_KEY', '') !== '' &&
    (string)settings_get('BKASH_APP_SECRET', '') !== '' &&
    (string)settings_get('BKASH_USERNAME', '') !== '' &&
    (string)settings_get('BKASH_PASSWORD', '') !== '';
} else {
  $has_pgw =
    (defined('BKASH_BASE_URL') && BKASH_BASE_URL !== '') &&
    (defined('BKASH_APP_KEY') && BKASH_APP_KEY !== '') &&
    (defined('BKASH_APP_SECRET') && BKASH_APP_SECRET !== '') &&
    (defined('BKASH_USERNAME') && BKASH_USERNAME !== '') &&
    (defined('BKASH_PASSWORD') && BKASH_PASSWORD !== '');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Light UI */
    body{ background: linear-gradient(180deg,#f7f9fc 0%, #eef3ff 100%); }
    .topbar { background:#ffffff; border-bottom:1px solid #e9edf3; }
    .pay-card { border-radius: 14px; border:1px solid #e9edf3; }
    .number-badge{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; }
    .soft { background: #f8f9fb; }
    .img-frame { border-radius: 12px; overflow: hidden; border:1px solid #e5e7eb; background:#fff; }
    .step-list li::marker{ color:#dc3545; font-weight:700; }
    .note-field { font-family: ui-monospace, monospace; letter-spacing:.2px; }
  </style>
</head>
<body>

<!-- Topbar (light) -->
<nav class="navbar topbar">
  <div class="container">
    <a class="navbar-brand fw-semibold text-body" href="/public/portal/index.php">
      <i class="bi bi-wallet2 me-1"></i> Payments
    </a>
    <div class="d-flex">
      <a class="btn btn-sm btn-outline-secondary" href="/public/portal/index.php">
        <i class="bi bi-arrow-left"></i> Back to Portal
      </a>
    </div>
  </div>
</nav>

<div class="d-flex">
  <?php
    // (বাংলা) Sidebar include (portal_sidebar.php → sidebar.php)
    $sb1 = __DIR__ . '/portal_sidebar.php';
    $sb2 = __DIR__ . '/sidebar.php';
    if (is_file($sb1))      { include $sb1; }
    elseif (is_file($sb2))  { include $sb2; }
    else { echo '<!-- Sidebar file not found -->'; }
  ?>

  <div class="container my-4">
    <div class="row justify-content-center">
      <div class="col-lg-9">

        <!-- PGW Direct Payment (Tokenized Checkout) -->
        <div class="card shadow-sm pay-card mb-3 <?php echo ($mode==='pgw'?'border border-danger':''); ?>">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">bKash Payment Gateway (Direct)</h5>
              <small class="text-muted">Tokenized Checkout — Pay now from browser</small>
            </div>
            <span class="badge rounded-pill text-bg-danger">PGW</span>
          </div>
          <div class="card-body">
            <?php if (!$has_pgw): ?>
              <div class="alert alert-warning mb-0">
                <div class="fw-semibold">PGW সেটিংস কনফিগার করা নেই</div>
                <div class="small">Admin থেকে <code>Settings → bKash PGW</code> এ গিয়ে App Key/Secret/Username/Password/Base URL সেট করুন।</div>
              </div>
            <?php else: ?>
              <div class="row g-3 align-items-end">
                <div class="col-md-5">
                  <label class="form-label form-label-sm mb-1">Reference (Invoice/Client Code)</label>
                  <input id="pgwRef" type="text" class="form-control note-field" value="<?= h($pref_ref) ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label form-label-sm mb-1">Amount (BDT)</label>
                  <input id="pgwAmount" type="number" step="0.01" min="1" class="form-control note-field" value="<?= h(number_format(max(0,$pref_amount), 2, '.', '')) ?>">
                </div>
                <div class="col-md-4 d-grid">
                  <button id="pgwPayBtn" class="btn btn-danger">
                    <i class="bi bi-wallet2"></i> Pay with bKash
                  </button>
                  <div class="form-text">ক্লিক করলে bKash পেমেন্ট পেইজ একই ট্যাবে ওপেন হবে।</div>
                </div>
              </div>
              <div id="pgwMsg" class="mt-3"></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card shadow-sm pay-card">
          <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <div>
              <h5 class="mb-0">bKash — Send Money</h5>
              <small class="text-muted">Send Money (Personal) only — Not Cash Out / Payment Gateway</small>
            </div>
            <div class="d-flex gap-2">
              <a href="/public/portal/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Portal
              </a>
            </div>
          </div>

          <div class="card-body">
            <!-- নম্বর ব্যাজ + কপি বোতাম -->
            <div class="alert alert-light border d-flex align-items-center justify-content-between">
              <div>
                <div class="small text-muted mb-1">Send Money to</div>
                <div class="h4 mb-0 number-badge">
                  <?= h($BKASH_NUMBER) ?>
                  <span class="badge rounded-pill text-bg-danger align-middle ms-2">bKash</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button id="btnCopy" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-clipboard"></i> Copy
                </button>
                <a class="btn btn-primary btn-sm" href="tel:*247%23">
                  <i class="bi bi-telephone"></i> Dial *247#
                </a>
              </div>
            </div>

            <!-- উল্টানো লেআউট: ইমেজ বামে, টেক্সট ডানে -->
            <div class="row g-3">
              <!-- ইমেজ (বামে) -->
              <div class="col-lg-6 order-lg-1">
                <div class="img-frame h-100">
                  <?php if ($guide_exists): ?>
                    <img
                      src="/assets/images/bkash_send_money.jpg"
                      alt="bKash Send Money visual guide"
                      style="width:100%;height:auto;display:block"
                      loading="lazy"
                    >
                  <?php else: ?>
                    <div class="p-4 text-center text-muted">
                      <div class="mb-2"><i class="bi bi-image" style="font-size:2rem;"></i></div>
                      <div>Guide image not found at <code><?= h($GUIDE_IMAGE_URL) ?></code>.</div>
                      <div class="small">Place your JPG there (e.g., a screenshot of the steps) and refresh.</div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- টেক্সট/স্টেপ (ডানে) -->
              <div class="col-lg-6 order-lg-2">
                <div class="p-3 soft rounded-3 h-100">
                  <h6 class="mb-3">Steps (USSD / App)</h6>
                  <ol class="step-list ps-3">
                    <li class="mb-2">Open the bKash App <em>or</em> dial <strong>*247#</strong></li>
                    <li class="mb-2">Choose <strong>Send Money</strong></li>
                    <li class="mb-2">Enter number: <span class="number-badge"><?= h($BKASH_NUMBER) ?></span></li>
                    <li class="mb-2">Type the <strong>Amount</strong> (as per your invoice)</li>
                    <li class="mb-2">Write <strong>Reference/Note</strong> as your PPPoE/Client Code</li>
                    <li class="mb-2">Confirm with your PIN</li>
                  </ol>

                  <div class="alert alert-warning small mt-3 mb-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    Please add a proper <strong>Reference/Note</strong> (e.g., PPPoE ID). Otherwise matching can be delayed.
                  </div>

                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label form-label-sm mb-1">Suggested Reference</label>
                      <input type="text" class="form-control form-control-sm note-field" value="<?= h($pref_ref) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label form-label-sm mb-1">Amount (Example)</label>
                      <input type="text" class="form-control form-control-sm note-field" value="<?= number_format($pref_amount, 2) ?>" readonly>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- সাবমিশন নোট -->
            <div class="alert alert-info mt-4 mb-0">
              <div class="d-flex align-items-start gap-2">
                <i class="bi bi-info-circle mt-1"></i>
                <div>
                  <div class="fw-semibold">Payment Verification</div>
                  After Send Money, please share the <strong>Transaction ID</strong> and the <strong>Reference</strong> you used.
                  You may also open a <a class="link-dark" href="/public/portal/ticket_new.php">Support Ticket</a> for faster processing.
                </div>
              </div>
            </div>

          </div><!--/card-body-->
        </div><!--/card-->

      </div>
    </div>
  </div>
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// বাংলা: নম্বর কপি বোতাম
document.getElementById('btnCopy')?.addEventListener('click', async () => {
  try{
    await navigator.clipboard.writeText('<?= $BKASH_NUMBER ?>');
    const btn = document.getElementById('btnCopy');
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
    btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-success');
    setTimeout(()=>{ btn.innerHTML = old; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-primary'); }, 1200);
  }catch(e){
    alert('Copy failed. Number: <?= $BKASH_NUMBER ?>');
  }
});
</script>

<script>
  (function(){
    const btn = document.getElementById('pgwPayBtn');
    if (!btn) return;
    const msg = document.getElementById('pgwMsg');
    const refEl = document.getElementById('pgwRef');
    const amtEl = document.getElementById('pgwAmount');

    const setMsg = (html) => { if (msg) msg.innerHTML = html; };

    btn.addEventListener('click', async () => {
      const ref = (refEl?.value || '').trim();
      const amount = parseFloat(amtEl?.value || '0');
      if (!ref) { setMsg('<div class="alert alert-warning">রেফারেন্স দিন (Invoice/Client Code)।</div>'); return; }
      if (!amount || amount <= 0) { setMsg('<div class="alert alert-warning">সঠিক অ্যামাউন্ট দিন।</div>'); return; }

      btn.disabled = true;
      const old = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> লিংক তৈরি হচ্ছে...';
      setMsg('');

      try {
        const res = await fetch('/public/portal/bkash_pgw_create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ ref, amount })
        });
        const data = await res.json().catch(() => ({}));
        if (!data || !data.ok || !data.bkashURL) {
          const m = (data && (data.message || data.error)) ? (data.message || data.error) : 'রিকোয়েস্ট ব্যর্থ হয়েছে।';
          const d = (data && data.error && data.message && data.error !== data.message) ? String(data.error) : '';
          setMsg(
            '<div class="alert alert-danger mb-2">'+ String(m) +'</div>' +
            (d ? '<div class="small text-muted">Details: <code>'+ d.replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s])) +'</code></div>' : '')
          );
          return;
        }
        setMsg('<div class="alert alert-success">bKash পেমেন্ট পেইজ ওপেন হচ্ছে...</div>');
        window.location.href = data.bkashURL; // same tab
      } catch (e) {
        setMsg('<div class="alert alert-danger">নেটওয়ার্ক/সার্ভার সমস্যা। পরে আবার চেষ্টা করুন।</div>');
      } finally {
        btn.disabled = false;
        btn.innerHTML = old;
      }
    });
  })();
</script>
</body>
</html>
