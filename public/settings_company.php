<?php
// /public/settings_company.php
// Company setup: logo + business profile settings.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/settings_store.php';

if (function_exists('require_perm')) {
  require_perm('settings.manage');
}

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$csrf = csrf_ensure_token();
$_active = 'settings_company';
$page_title = 'Company Setup';

$keys = [
  'company_name',
  'company_email',
  'company_address1',
  'company_address2',
  'company_address',
  'company_mobile1',
  'company_mobile2',
  'company_phone1',
  'company_phone2',
  'company_phone',
  'company_logo',
  'client_code_mode',
  'show_login_brand',
];

$msg = '';
$kind = 'success';
$cur = settings_get_many($keys);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $msg = 'Invalid CSRF token.';
    $kind = 'danger';
  } else {
    $errors = [];

    $company_name   = trim((string)($_POST['company_name'] ?? ''));
    $company_email  = trim((string)($_POST['company_email'] ?? ''));
    $address1       = trim((string)($_POST['company_address1'] ?? ''));
    $address2       = trim((string)($_POST['company_address2'] ?? ''));
    $mobile1        = trim((string)($_POST['company_mobile1'] ?? ''));
    $mobile2        = trim((string)($_POST['company_mobile2'] ?? ''));
    $phone1         = trim((string)($_POST['company_phone1'] ?? ''));
    $phone2         = trim((string)($_POST['company_phone2'] ?? ''));
    $client_mode_in = strtolower(trim((string)($_POST['client_code_mode'] ?? 'auto')));
    $client_mode    = in_array($client_mode_in, ['auto','custom'], true) ? $client_mode_in : 'auto';
    $show_login     = isset($_POST['show_login_brand']) ? '1' : '0';

    $logo_path = (string)($cur['company_logo'] ?? '');
    if (!empty($_FILES['company_logo']) && (int)($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      $f = $_FILES['company_logo'];
      if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'Logo upload failed.';
      } else {
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        $allow = ['png','jpg','jpeg','webp'];
        if (!in_array($ext, $allow, true)) {
          $errors[] = 'Logo must be png, jpg, jpeg, or webp.';
        } elseif (!is_uploaded_file((string)$f['tmp_name'])) {
          $errors[] = 'Invalid upload attempt.';
        } elseif (@getimagesize((string)$f['tmp_name']) === false) {
          $errors[] = 'Logo file is not a valid image.';
        } else {
          $dir = __DIR__ . '/../uploads/company';
          if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
          $filename = 'company_logo.' . $ext;
          $dest = $dir . '/' . $filename;

          // Remove previous logo variants
          foreach (['png','jpg','jpeg','webp'] as $oldExt) {
            $old = $dir . '/company_logo.' . $oldExt;
            if ($old !== $dest && is_file($old)) { @unlink($old); }
          }

          if (!@move_uploaded_file((string)$f['tmp_name'], $dest)) {
            $errors[] = 'Could not save logo file.';
          } else {
            $logo_path = '/uploads/company/' . $filename;
          }
        }
      }
    }

    if (!$errors) {
      $addr_combo = $address1;
      if ($address2 !== '') {
        $addr_combo = $addr_combo !== '' ? ($addr_combo . ', ' . $address2) : $address2;
      }
      $phone_combo = $phone1 !== '' ? $phone1 : ($mobile1 !== '' ? $mobile1 : '');

      $pairs = [
        'company_name'     => $company_name,
        'company_email'    => $company_email,
        'company_address1' => $address1,
        'company_address2' => $address2,
        'company_address'  => $addr_combo,
        'company_mobile1'  => $mobile1,
        'company_mobile2'  => $mobile2,
        'company_phone1'   => $phone1,
        'company_phone2'   => $phone2,
        'company_phone'    => $phone_combo,
        'company_logo'     => $logo_path,
        'client_code_mode' => $client_mode,
        'show_login_brand' => $show_login,
      ];

      if (settings_set_many($pairs)) {
        $msg = 'Company settings saved.';
        $kind = 'success';
        $cur = settings_get_many($keys);
      } else {
        $msg = 'Save failed. Please try again.';
        $kind = 'danger';
      }
    } else {
      $msg = implode(' ', $errors);
      $kind = 'danger';
    }
  }
}

$logo_web = (string)($cur['company_logo'] ?? '');
$logo_abs = $logo_web !== '' ? ($_SERVER['DOCUMENT_ROOT'] . $logo_web) : '';
$logo_ok  = $logo_web !== '' && is_file($logo_abs);

include __DIR__ . '/../partials/partials_header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-buildings"></i> Company Setup</h4>
      <div class="text-muted">Update brand logo and company profile details from one place.</div>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= h($kind) ?> shadow-sm"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="card settings-card">
    <div class="card-header">
      <strong><i class="bi bi-gear-wide-connected me-1"></i> Basic Company Settings</strong>
    </div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div class="col-md-4">
          <label class="form-label">Company Name *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-building"></i></span>
            <input class="form-control" name="company_name" value="<?= h($cur['company_name'] ?? '') ?>" placeholder="Ex: Green Net" required>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" name="company_email" value="<?= h($cur['company_email'] ?? '') ?>" placeholder="example@example.com">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Address 1</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
            <input class="form-control" name="company_address1" value="<?= h($cur['company_address1'] ?? '') ?>" placeholder="Ex: Dhaka, Bangladesh">
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Address 2</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-geo"></i></span>
            <input class="form-control" name="company_address2" value="<?= h($cur['company_address2'] ?? '') ?>" placeholder="Ex: Gulshan, Dhaka">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Mobile 1</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input class="form-control" name="company_mobile1" value="<?= h($cur['company_mobile1'] ?? '') ?>" placeholder="+8801XXXXXXXXX">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Mobile 2</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input class="form-control" name="company_mobile2" value="<?= h($cur['company_mobile2'] ?? '') ?>" placeholder="+8801XXXXXXXXX">
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Phone 1</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input class="form-control" name="company_phone1" value="<?= h($cur['company_phone1'] ?? '') ?>" placeholder="+8802XXXXXXX">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Phone 2</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input class="form-control" name="company_phone2" value="<?= h($cur['company_phone2'] ?? '') ?>" placeholder="+8802XXXXXXX">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Logo</label>
          <div class="d-flex align-items-center gap-3">
            <div class="logo-preview">
              <?php if ($logo_ok): ?>
                <img src="<?= h($logo_web) ?>" alt="Logo">
              <?php else: ?>
                <i class="bi bi-image"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
              <input class="form-control" type="file" name="company_logo" accept=".png,.jpg,.jpeg,.webp">
              <div class="form-text">Recommended: 300x300px, PNG/WebP.</div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Client Code Automatic or Customizable?</label>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="client_code_mode" id="codeCustom" value="custom" <?= ($cur['client_code_mode'] ?? '') === 'custom' ? 'checked' : '' ?>>
              <label class="form-check-label" for="codeCustom">Customizable</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="client_code_mode" id="codeAuto" value="auto" <?= ($cur['client_code_mode'] ?? 'auto') !== 'custom' ? 'checked' : '' ?>>
              <label class="form-check-label" for="codeAuto">Automatic</label>
            </div>
          </div>
          <div class="form-text">This preference is stored for future workflow rules.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Show brand on Login page?</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="show_login_brand" id="showLoginBrand" value="1" <?= ($cur['show_login_brand'] ?? '') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="showLoginBrand">Yes, display company logo and name on login</label>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button class="btn btn-primary"><i class="bi bi-save2"></i> Update Company Information</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
