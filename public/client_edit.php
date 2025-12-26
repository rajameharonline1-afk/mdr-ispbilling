<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/mikrotik.php';
require_once __DIR__ . '/../app/package_profile.php';
require_once __DIR__ . '/../app/audit.php'; // ✅ Audit helper
require_once __DIR__ . '/../app/location_options.php';
require_once __DIR__ . '/../app/csrf_compat.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --------- helpers --------- */
// (বাংলা) টেবিলের কলাম আছে কিনা — একবার চেক করে cache করি
function db_has_column(string $table, string $column): bool {
    static $cache = [];
    if (!isset($cache[$table])) {
        $rows = db()->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        $cache[$table] = array_flip($rows ?: []);
    }
    return isset($cache[$table][$column]);
}

function ensure_option_present(array $options, string $value): array {
    $value = trim($value);
    if ($value !== '' && !in_array($value, $options, true)) {
        array_unshift($options, $value);
    }
    return $options;
}

/**
 * Save uploaded photo for a client using PPPoE ID for the filename.
 * - Filename: <pppoe-id-sanitized>.<ext>  (no random)
 * - Overwrites existing same-name file; removes previous file if path changed.
 *
 * @return array ['ok'=>bool, 'url'=>?string, 'error'=>?string]
 *
 * (বাংলা) pppoe_id থেকে নিরাপদ ফাইলনেম বানিয়ে সেভ করা;
 * আগের ফাইল থাকলে নিরাপদভাবে রিমুভ/ওভাররাইট।
 */
function handle_client_photo_upload(int $client_id, string $pppoe_id, ?string $existing_url = null): array {
    $out = ['ok'=>true, 'url'=>$existing_url, 'error'=>null];

    // Remove request
    if (!empty($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        // delete old file if it lives under uploads/clients
        if ($existing_url && str_starts_with($existing_url, '/uploads/clients/')) {
            $absOld = realpath(__DIR__ . '/..' . $existing_url);
            $baseUploads = realpath(__DIR__ . '/../uploads/clients');
            if ($absOld && $baseUploads && str_starts_with($absOld, $baseUploads)) {
                @unlink($absOld);
            }
        }
        $out['url'] = null;
        return $out;
    }

    // No new file
    if (empty($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $out;
    }

    $f = $_FILES['photo'];
    if ($f['error'] !== UPLOAD_ERR_OK) { $out['ok']=false; $out['error']='Upload failed.'; return $out; }

    // Validate
    $maxBytes = 3 * 1024 * 1024; // 3MB
    if ($f['size'] > $maxBytes) { $out['ok']=false; $out['error']='Max 3MB allowed.'; return $out; }

    $mime = function_exists('finfo_open') ? (function($tmp){
        $fi=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($fi,$tmp); finfo_close($fi); return $m;
    })($f['tmp_name']) : mime_content_type($f['tmp_name']);

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) { $out['ok']=false; $out['error']='Only JPG/PNG/WebP.'; return $out; }

    // Destination dir
    $upDir = __DIR__ . '/../uploads/clients';
    if (!is_dir($upDir)) { @mkdir($upDir, 0775, true); }

    // Sanitize PPPoE ID for filename
    $slug = strtolower($pppoe_id);
    $slug = preg_replace('/[^a-z0-9-_]+/i', '-', $slug);
    $slug = trim($slug, '-_');
    if ($slug === '') $slug = 'client-'.$client_id;

    $ext   = $allowed[$mime];
    $fname = $slug.'.'.$ext;                          // ← no random
    $dest  = $upDir . '/' . $fname;
    $destWeb = '/uploads/clients/'.$fname;

    // If a file with same name exists, overwrite
    if (file_exists($dest)) @unlink($dest);

    // If previous URL is different file, delete that too (keeps storage clean)
    if ($existing_url && $existing_url !== $destWeb && str_starts_with($existing_url, '/uploads/clients/')) {
        $absOld = realpath(__DIR__ . '/..' . $existing_url);
        $baseUploads = realpath($upDir);
        if ($absOld && $baseUploads && str_starts_with($absOld, $baseUploads)) {
            @unlink($absOld);
        }
    }

    if (!move_uploaded_file($f['tmp_name'], $dest)) { $out['ok']=false; $out['error']='Could not save file.'; return $out; }

    $out['url'] = $destWeb;
    return $out;
}

/* --------- load client & lists --------- */
$client_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$client_id) { header("Location: clients.php"); exit; }

$sqlClient = "SELECT c.*,
                     p.name AS package_name,
                     r.name AS router_name, r.ip AS router_ip, r.username AS r_user, r.password AS r_pass, r.api_port
              FROM clients c
              LEFT JOIN packages p ON c.package_id = p.id
              LEFT JOIN routers  r ON c.router_id  = r.id
              WHERE c.id = ?";
$st = db()->prepare($sqlClient);
$st->execute([$client_id]);
$client = $st->fetch(PDO::FETCH_ASSOC);
if (!$client) { header("Location: clients.php"); exit; }

$packages = db()->query("SELECT id, name, price, profile, profile_name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$routers  = db()->query("SELECT id, name FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ensure legacy unique index on mobile is removed to allow duplicates
try {
    db()->exec("ALTER TABLE clients DROP INDEX uq_mobile");
} catch (Throwable $e) {
    // ignore
}

/* --------- optional columns present? --------- */
$SHOW_CLIENT_CODE = false; // client_code deprecated
$HAS_SUB_ZONE    = db_has_column('clients','sub_zone');
$HAS_BOX         = db_has_column('clients','box');
$HAS_NID         = db_has_column('clients','nid');
$HAS_DOB         = db_has_column('clients','dob');
$HAS_PHOTO_URL   = db_has_column('clients','photo_url');
$HAS_PPPOE_PASS  = db_has_column('clients','pppoe_pass');
$HAS_UPDATED_AT  = db_has_column('clients','updated_at');

$pdoOptions      = db();
$area_options    = location_option_list($pdoOptions, 'area');
$subzone_options = $HAS_SUB_ZONE ? location_option_list($pdoOptions, 'sub_zone') : [];
$box_options     = $HAS_BOX ? location_option_list($pdoOptions, 'box') : [];
$LOC_CSRF        = csrf_ensure_token();

/* --------- process save --------- */
$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $successNotes = [];
    $new_client_id = intval($_POST['client_id_new'] ?? $client_id);
    $prevRouterId   = (int)($client['router_id'] ?? 0);
    $prevPppoeId    = (string)($client['pppoe_id'] ?? '');
    $prevPppoePass  = $HAS_PPPOE_PASS ? (string)($client['pppoe_pass'] ?? '') : null;
    $name         = trim($_POST['name'] ?? $client['name']);
    $mobile       = preg_replace('/\D+/', '', trim($_POST['mobile'] ?? $client['mobile']));
    $email        = trim($_POST['email'] ?? $client['email']);
    $address      = trim($_POST['address'] ?? $client['address']);
    $area         = trim($_POST['area'] ?? $client['area']);
    $sub_zone     = trim($_POST['sub_zone'] ?? ($client['sub_zone'] ?? ''));
    $box          = trim($_POST['box'] ?? ($client['box'] ?? ''));
    $nid          = trim($_POST['nid'] ?? ($client['nid'] ?? ''));
    $dob          = trim($_POST['dob'] ?? ($client['dob'] ?? ''));
    $pppoe_id     = trim($_POST['pppoe_id'] ?? $client['pppoe_id']);
    $pppoe_pass   = trim($_POST['pppoe_pass'] ?? ($client['pppoe_pass'] ?? ''));
    $package_id   = intval($_POST['package_id'] ?? $client['package_id']);
    $router_id    = intval($_POST['router_id']  ?? $client['router_id']);
    $monthly_bill = is_numeric($_POST['monthly_bill'] ?? null) ? (0+$_POST['monthly_bill']) : (0+$client['monthly_bill']);
    $expiry_date  = trim($_POST['expiry_date'] ?? ($client['expiry_date'] ?? ''));
    $status       = trim($_POST['status'] ?? $client['status']);

    if ($name === '')        $errors[] = 'Name is required.';
    if ($area === '')        $errors[] = 'Area is required.';
    if ($HAS_SUB_ZONE && $sub_zone === '') $errors[] = 'Sub Zone is required.';
    if ($HAS_BOX && $box === '') $errors[] = 'Box is required.';
    if ($mobile === '')      $errors[] = 'Mobile is required.';
    if ($mobile !== '' && !preg_match('/^\d{11}$/', $mobile)) $errors[] = 'Mobile must be exactly 11 digits.';
    if ($pppoe_id === '')    $errors[] = 'PPPoE username is required.';
    if ($package_id <= 0)    $errors[] = 'Please select a package.';
    if ($monthly_bill < 0)   $errors[] = 'Monthly bill is invalid.';
    if ($new_client_id <= 0) $errors[] = 'Client ID must be a positive number.';

    if (!$errors && $new_client_id !== $client_id) {
        $stmtChk = db()->prepare("SELECT COUNT(*) FROM clients WHERE id = ?");
        $stmtChk->execute([$new_client_id]);
        if ($stmtChk->fetchColumn() > 0) {
            $errors[] = 'Client ID already exists.';
        }
    }

    // duplicate mobile / PPPoE checks skipped per requirement

    // Handle photo (pppoe-based filename)
    $new_photo_url = $client['photo_url'] ?? null;
    if ($HAS_PHOTO_URL) {
        $photoResult = handle_client_photo_upload($client_id, $pppoe_id, $new_photo_url);
        if (!$photoResult['ok']) $errors[] = $photoResult['error'] ?? 'Photo upload failed.';
        $new_photo_url = $photoResult['url'];
    }

    if (!$errors) {
        // Build dynamic UPDATE
        $sets = [
            'name = :name',
            'mobile = :mobile',
            'email = :email',
            'address = :address',
            'area = :area',
            'pppoe_id = :pppoe_id',
            'package_id = :package_id',
            'router_id = :router_id',
            'monthly_bill = :monthly_bill',
            'expiry_date = :expiry_date',
            'status = :status'
        ];
        $params = [
            ':name'=>$name,
            ':mobile'=>$mobile,
            ':email'=>($email==='') ? null : $email,
            ':address'=>($address==='') ? null : $address,
            ':area'=>$area,
            ':pppoe_id'=>$pppoe_id, ':package_id'=>$package_id, ':router_id'=>$router_id,
            ':monthly_bill'=>$monthly_bill, ':expiry_date'=>$expiry_date ?: null, ':status'=>$status,
            ':id'=>$client_id
        ];
        if ($HAS_SUB_ZONE)    { $sets[] = 'sub_zone = :sub_zone';      $params[':sub_zone'] = $sub_zone; }
        if ($HAS_BOX)         { $sets[] = 'box = :box';                $params[':box'] = $box; }
        if ($HAS_NID)         { $sets[] = 'nid = :nid';                $params[':nid'] = $nid === '' ? null : $nid; }
        if ($HAS_DOB)         { $sets[] = 'dob = :dob';                $params[':dob'] = $dob === '' ? null : $dob; }
        if ($HAS_PPPOE_PASS)  { $sets[] = 'pppoe_pass = :pppoe_pass';  $params[':pppoe_pass'] = $pppoe_pass; }
        if ($HAS_PHOTO_URL)   { $sets[] = 'photo_url = :photo_url';    $params[':photo_url']  = $new_photo_url; }
        if ($HAS_UPDATED_AT)  { $sets[] = 'updated_at = NOW()'; }

        $pdoSave = db();
        $pdoSave->beginTransaction();
        $pdoSave->exec("SET FOREIGN_KEY_CHECKS=0");

        if ($new_client_id !== $client_id) {
            // Update all tables that have client_id column
            $tblStmt = $pdoSave->prepare("SELECT TABLE_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'client_id'");
            $tblStmt->execute();
            $tables = $tblStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($tables as $tbl) {
                if ($tbl === 'clients') continue;
                $pdoSave->prepare("UPDATE `$tbl` SET client_id = :new WHERE client_id = :old")->execute([
                    ':new' => $new_client_id,
                    ':old' => $client_id,
                ]);
            }
            $sets[] = 'id = :new_id';
            $params[':new_id'] = $new_client_id;
        }

        $sqlUp = "UPDATE clients SET ".implode(',', $sets)." WHERE id = :id";
        $u = $pdoSave->prepare($sqlUp);
        $u->execute($params);

        $pdoSave->exec("SET FOREIGN_KEY_CHECKS=1");
        $pdoSave->commit();
        $client_id = $new_client_id;

        if ($area !== '') {
            location_option_store($pdoSave, 'area', $area);
        }
        if ($HAS_SUB_ZONE && $sub_zone !== '') {
            location_option_store($pdoSave, 'sub_zone', $sub_zone, '', $area ?: null);
        }
        if ($HAS_BOX && $box !== '') {
            location_option_store($pdoSave, 'box', $box, '', $area ?: null, $sub_zone ?: null);
        }

        $old_pkg_id = intval($client['package_id']);           // পুরনো প্যাকেজ আইডি (সেভের আগের)
        $routerChanged = $router_id && $router_id !== $prevRouterId;
        $pppoeChanged  = $pppoe_id !== $prevPppoeId;
        $passChanged   = $HAS_PPPOE_PASS && $pppoe_pass !== $prevPppoePass;
        $packageChanged = $package_id && $package_id !== $old_pkg_id;

        $needsSecretSync = $router_id && $pppoe_id !== '' && ($routerChanged || $pppoeChanged || $passChanged || $packageChanged || !$prevRouterId);
        $pkg = null;
        if (($needsSecretSync || $packageChanged) && $package_id) {
            $stp = db()->prepare("SELECT id, name, profile, profile_name FROM packages WHERE id=?");
            $stp->execute([$package_id]);
            $pkg = $stp->fetch(PDO::FETCH_ASSOC);
        }

        if ($needsSecretSync) {
            $profileName = $pkg ? package_ppp_profile_name($pkg) : null;
            $commentParts = array_filter([$name, $mobile], function($v){ return trim((string)$v) !== ''; });
            $comment = $commentParts ? implode(' | ', $commentParts) : '';
            $syncPass = $HAS_PPPOE_PASS ? ($pppoe_pass === '' ? null : $pppoe_pass) : null;
            $secret = mikrotik_ensure_pppoe_secret((int)$router_id, $pppoe_id, $syncPass, $profileName, ['comment'=>$comment]);
            if (!$secret['ok']) {
                $notice = 'Saved, but MikroTik sync failed: '.$secret['error'];
            } else {
                $successNotes[] = 'PPP secret '.($secret['action'] ?? 'synced').'.';
            }
        }

        if ($packageChanged) {
            // ✅ Audit log: package change
            audit('package_change', 'client', (int)$client_id, [
                'pppoe_id'    => $client['pppoe_id'] ?? '',
                'name'        => $client['name'] ?? '',
                'from_id'     => $old_pkg_id,
                'from_name'   => $client['package_name'] ?? '',
                'to_id'       => (int)$package_id,
                'to_name'     => $pkg['name'] ?? '',
                'router_id'   => (int)($client['router_id'] ?? 0),
                'router_name' => $client['router_name'] ?? '',
            ]);
        }

        // Reload fresh client
        $st = db()->prepare($sqlClient);
        $st->execute([$client_id]);
        $client = $st->fetch(PDO::FETCH_ASSOC);

        $notice = $notice ? ('Saved successfully. '.$notice) : 'Saved successfully.';
        if (!empty($successNotes)) {
            $notice .= ' '.implode(' ', $successNotes);
        }
    }
}

/* --------- UI helpers --------- */
$photo_url = $HAS_PHOTO_URL ? trim($client['photo_url'] ?? '') : '';
$client_initial = mb_strtoupper(mb_substr($client['name'] ?? '?', 0, 1, 'UTF-8'));

include __DIR__ . '/../partials/partials_header.php';
?>
<style>
.card-block{ border:1px solid #dfe3e8; border-radius:.75rem; background:#f5f6f8; }
.card-block .card-title{ font-weight:700; padding:.65rem .9rem; border-bottom:1px solid #dfe3e8; background:#e9ecef; }
.header-avatar{ width:56px; height:56px; border-radius:50%; overflow:hidden; border:1px solid #e5e7eb; background:#f2f4f7; }
.header-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
.header-avatar .avatar-fallback{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#5c6b7a; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.form-text.small{ font-size:.8rem; }
.req::after{ content:" *"; color:#dc3545; font-weight:700; }
</style>

<div class="container-fluid py-3 text-start">
  <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="header-avatar">
        <?php if ($photo_url): ?>
          <img id="topPreview" src="<?= h($photo_url) ?>" alt="<?= h($client['name'] ?? 'Photo') ?>">
        <?php else: ?>
          <div class="avatar-fallback"><?= h($client_initial) ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-person-lines-fill"></i>
        <span class="fw-bold">Edit Client — <?= h($client['name']) ?></span>
        <?php if ($SHOW_CLIENT_CODE && !empty($client['client_code'])): ?>
          <span class="badge bg-secondary mono"><?= h($client['client_code']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <a href="/public/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-light btn-sm"><i class="bi bi-eye"></i> View</a>
      <a href="/public/clients.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= h(implode(' | ', $errors)) ?></div>
  <?php elseif ($notice): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle"></i> <?= h($notice) ?></div>
  <?php endif; ?>

  <?php if (!$HAS_PHOTO_URL): ?>
    <div class="alert alert-warning py-2">
      <strong>Heads up:</strong> photo cannot be saved because <code>clients.photo_url</code> column is missing.
      Run once: <code>ALTER TABLE clients ADD COLUMN photo_url VARCHAR(255) NULL;</code>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">

    <div class="row g-3">
      <!-- Account + Photo -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title d-flex justify-content-between align-items-center">
            <span>Account</span>
            <span class="text-muted small">Client ID: <span class="mono">#<?= (int)$client_id; ?></span></span>
          </div>
          <div class="p-3">
            <div class="mb-2">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleClientId">
                <label class="form-check-label" for="toggleClientId">Change Client ID</label>
              </div>
              <div id="clientIdWrap" class="d-none mt-2">
                <input type="number"
                       name="client_id_new"
                       id="client_id_new"
                       class="form-control form-control-sm"
                       value="<?= (int)($client_id ?? $client['id']) ?>"
                       min="1"
                       readonly>
                <div class="form-text small">Changing this updates all linked records.</div>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label req">Name</label>
              <input type="text" name="name" class="form-control form-control-sm" value="<?= h($client['name']) ?>" required>
            </div>
            <?php $form_area = isset($_POST['area']) ? (string)$_POST['area'] : (string)($client['area'] ?? ''); $area_opts_form = ensure_option_present($area_options, $form_area); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Area</label>
                <button type="button" class="btn btn-link btn-sm px-1 py-0 text-primary" data-loc-add="area" title="Add Area" style="text-decoration:none;">
                  <i class="bi bi-plus-lg"></i>
                </button>
              </div>
              <select name="area" class="form-select form-select-sm" data-loc-type="area" required>
                <option value="">Select</option>
                <?php foreach ($area_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_area===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($HAS_SUB_ZONE): ?>
            <?php $form_sub = isset($_POST['sub_zone']) ? (string)$_POST['sub_zone'] : (string)($client['sub_zone'] ?? ''); $sub_opts_form = ensure_option_present($subzone_options, $form_sub); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Sub Zone</label>
                <button type="button" class="btn btn-link btn-sm px-1 py-0 text-primary" data-loc-add="sub_zone" title="Add Sub Zone" style="text-decoration:none;">
                  <i class="bi bi-plus-lg"></i>
                </button>
              </div>
              <select name="sub_zone" class="form-select form-select-sm" data-loc-type="sub_zone" required>
                <option value="">Select</option>
                <?php foreach ($sub_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_sub===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($HAS_BOX): ?>
            <?php $form_box = isset($_POST['box']) ? (string)$_POST['box'] : (string)($client['box'] ?? ''); $box_opts_form = ensure_option_present($box_options, $form_box); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Box</label>
                <button type="button" class="btn btn-link btn-sm px-1 py-0 text-primary" data-loc-add="box" title="Add Box" style="text-decoration:none;">
                  <i class="bi bi-plus-lg"></i>
                </button>
              </div>
              <select name="box" class="form-select form-select-sm" data-loc-type="box" required>
                <option value="">Select</option>
                <?php foreach ($box_opts_form as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_box===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="mb-2">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($client['address']) ?></textarea>
            </div>

            <hr>

            <div class="mb-2">
              <label class="form-label req">Mobile</label>
              <input type="text" name="mobile" pattern="\d{11}" maxlength="11" inputmode="numeric" class="form-control form-control-sm" value="<?= h($_POST['mobile'] ?? $client['mobile']) ?>" required>
              <div class="form-text small">Enter 11-digit mobile number (digits only).</div>
            </div>
            <div class="mb-2">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control form-control-sm" value="<?= h($client['email']) ?>">
            </div>
            <?php if ($HAS_NID): ?>
            <div class="mb-2">
              <label class="form-label">NID No.</label>
              <input type="text" name="nid" class="form-control form-control-sm" value="<?= h($client['nid'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <?php if ($HAS_DOB): ?>
            <div class="mb-2">
              <label class="form-label">DOB</label>
              <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($client['dob'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <hr>

            <!-- Photo -->
            <div class="card">
              <div class="card-header fw-bold">Profile Photo</div>
              <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle overflow-hidden border" style="width:80px;height:80px;background:#f2f4f7">
                    <?php if ($photo_url): ?>
                      <img id="photoPreview" src="<?= h($photo_url) ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                      <img id="photoPreview" src="/assets/img/avatar_placeholder.png" alt="Photo" style="width:100%;height:100%;object-fit:cover">
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow-1">
                    <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm mb-1" <?= $HAS_PHOTO_URL?'':'disabled' ?>>
                    <div class="form-text small">Supported: JPG, PNG, WebP • Max 3MB</div>
                    <?php if ($HAS_PHOTO_URL && $photo_url): ?>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" id="remove_photo" name="remove_photo">
                        <label class="form-check-label" for="remove_photo">Remove current photo</label>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Billing -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">Billing</div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">Package</label>
              <select name="package_id" class="form-select form-select-sm" required>
                <option value="">-- Select --</option>
                <?php foreach ($packages as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= ((int)$client['package_id']===(int)$p['id'])?'selected':'' ?>>
                    <?= h($p['name']) ?> <?= is_numeric($p['price'])? '— '.(0+$p['price']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small">(Package name = MikroTik PPP profile name 1:1)</div>
            </div>
            <div class="mb-2">
              <label class="form-label req">Monthly Bill</label>
              <input type="number" step="0.01" name="monthly_bill" class="form-control form-control-sm" value="<?= h($client['monthly_bill']) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Expiry Date</label>
              <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= h($client['expiry_date'] ?? '') ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php
                  $opts = ['active'=>'Active','inactive'=>'Inactive','pending'=>'Pending','hold'=>'Hold','disabled'=>'Disabled','blocked'=>'Blocked','expired'=>'Expired'];
                  $cur  = strtolower(trim($client['status'] ?? 'active'));
                  foreach($opts as $k=>$v){
                    echo '<option value="'.h($k).'"'.($cur===$k?' selected':'').'>'.h($v).'</option>';
                  }
                ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Server / PPP -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">Server / PPP</div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label">Router</label>
              <select name="router_id" class="form-select form-select-sm">
                <option value="">-- Select --</option>
                <?php foreach ($routers as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= ((int)$client['router_id']===(int)$r['id'])?'selected':'' ?>>
                    <?= h($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label req">PPPoE Username</label>
              <input type="text" name="pppoe_id" class="form-control form-control-sm mono" value="<?= h($client['pppoe_id']) ?>" required>
            </div>

            <?php if ($HAS_PPPOE_PASS): ?>
            <div class="mb-2">
              <label class="form-label">PPPoE Password</label>
              <input type="text" name="pppoe_pass" class="form-control form-control-sm mono" value="<?= h($client['pppoe_pass'] ?? '') ?>">
            </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <span class="text-muted small">
        <?php if ($SHOW_CLIENT_CODE && !empty($client['client_code'])): ?>
          Client Code: <span class="mono"><?= h($client['client_code']) ?></span>
        <?php endif; ?>
      </span>
      <div class="d-flex gap-2">
        <a href="/public/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save2"></i> Save Changes</button>
      </div>
    </div>
  </form>
</div>

<!-- Location Option Modal -->
<div class="modal fade" id="locOptionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="locModalTitle">Add Option</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="locOptionForm">
          <input type="hidden" id="locTypeField" value="">
          <div class="mb-3 d-none" data-field="parent_area">
            <label class="form-label">Zone</label>
            <select class="form-select" id="locParentArea">
              <option value="">Select Zone</option>
            </select>
          </div>
          <div class="mb-3 d-none" data-field="parent_sub_zone">
            <label class="form-label">Sub Zone</label>
            <select class="form-select" id="locParentSubZone">
              <option value="">Select Sub Zone</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" id="locLabelInput" placeholder="Enter name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Details (optional)</label>
            <textarea class="form-control" id="locDetailInput" rows="3" placeholder="Notes"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger" id="locClearBtn">Clear</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-primary" id="locSaveBtn">Save</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// (বাংলা) photo preview
document.getElementById('photo')?.addEventListener('change', function(){
  const [file] = this.files || [];
  if(!file) return;
  const obj = URL.createObjectURL(file);
  const img1 = document.getElementById('photoPreview');
  const img2 = document.getElementById('topPreview');
  if(img1) img1.src = obj;
  if(img2) img2.src = obj;
});
</script>

<script>
(function(){
  const typeLabels = {area:'Area', sub_zone:'Sub Zone', box:'Box'};
  const csrf = <?= json_encode($LOC_CSRF, JSON_UNESCAPED_UNICODE) ?>;
  let currentType = null;
  const modalEl = document.getElementById('locOptionModal');
  let modalInstance = null;
  const titleEl = document.getElementById('locModalTitle');
  const form = document.getElementById('locOptionForm');
  const typeField = document.getElementById('locTypeField');
  const labelInput = document.getElementById('locLabelInput');
  const detailInput = document.getElementById('locDetailInput');
  const clearBtn = document.getElementById('locClearBtn');
  const saveBtn = document.getElementById('locSaveBtn');
  const parentAreaWrap = document.querySelector('[data-field="parent_area"]');
  const parentSubWrap = document.querySelector('[data-field="parent_sub_zone"]');
  const parentAreaSelect = document.getElementById('locParentArea');
  const parentSubSelect = document.getElementById('locParentSubZone');
  const areaSelect = document.querySelector('select[data-loc-type="area"]');
  const subZoneSelect = document.querySelector('select[data-loc-type="sub_zone"]');
  let areaCache = null;
  let subZoneCache = null;

  async function refreshSelect(type, selectedValue){
    const sel = document.querySelector(`select[data-loc-type="${type}"]`);
    if (!sel) return;
    try {
      const res = await fetch(`/public/ajax/location_options.php?type=${encodeURIComponent(type)}`, {cache:'no-store'});
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to load list');
      const keep = selectedValue ?? sel.value;
      const opts = Array.isArray(data.options) ? data.options : [];
      sel.innerHTML = '<option value="">Select</option>';
      opts.forEach(val => {
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = val;
        sel.appendChild(opt);
      });
      if (keep) {
        if (!opts.includes(keep)) {
          const extra = document.createElement('option');
          extra.value = keep;
          extra.textContent = keep;
          sel.appendChild(extra);
        }
        sel.value = keep;
      }
    } catch (err) {
      console.error(err);
      alert(err.message || 'Could not refresh options.');
    }
  }

  async function fetchFull(type){
    const res = await fetch(`/public/ajax/location_options.php?type=${encodeURIComponent(type)}&full=1`, {cache:'no-store'});
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'Failed to load list.');
    return Array.isArray(json.items) ? json.items : [];
  }

  async function ensureAreaCache(){ if (!areaCache) areaCache = await fetchFull('area'); return areaCache; }
  async function ensureSubZoneCache(){ if (!subZoneCache) subZoneCache = await fetchFull('sub_zone'); return subZoneCache; }

  async function populateParentArea(selectedValue){
    if (!parentAreaSelect) return;
    const list = await ensureAreaCache();
    parentAreaSelect.innerHTML = '<option value="">Select Zone</option>';
    list.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentAreaSelect.appendChild(opt);
    });
  }

  async function populateParentSub(areaValue, selectedValue){
    if (!parentSubSelect) return;
    const list = await ensureSubZoneCache();
    parentSubSelect.innerHTML = '<option value="">Select Sub Zone</option>';
    const filtered = areaValue ? list.filter(row => row.parent_area === areaValue) : list;
    filtered.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentSubSelect.appendChild(opt);
    });
    parentSubSelect.disabled = filtered.length === 0;
  }

  parentAreaSelect?.addEventListener('change', () => {
    if (currentType === 'box') {
      populateParentSub(parentAreaSelect.value || '', '');
    }
  });

  function ensureModal(){
    if (modalInstance) return modalInstance;
    const bs = window.bootstrap || null;
    if (!modalEl || !bs || !bs.Modal) return null;
    modalInstance = new bs.Modal(modalEl);
    return modalInstance;
  }

  async function openModal(type){
    currentType = type;
    const modal = ensureModal();
    if (!modal) {
      alert('Cannot open form because Bootstrap modal is unavailable.');
      return;
    }
    typeField.value = type;
    if (titleEl) titleEl.textContent = 'Add ' + (typeLabels[type] || 'Option');
    form?.reset();
    const showArea = (type === 'sub_zone' || type === 'box');
    const showSub = (type === 'box');
    parentAreaWrap?.classList.toggle('d-none', !showArea);
    parentSubWrap?.classList.toggle('d-none', !showSub);
    if (showArea) {
      const defaultArea = areaSelect?.value || '';
      await populateParentArea(defaultArea);
      if (showSub) {
        const defaultSub = subZoneSelect?.value || '';
        await populateParentSub(parentAreaSelect.value || defaultArea, defaultSub);
      }
    }
    modal.show();
  }

  clearBtn?.addEventListener('click', () => {
    form?.reset();
    parentAreaSelect && (parentAreaSelect.value = '');
    parentSubSelect && (parentSubSelect.value = '');
    parentSubSelect && (parentSubSelect.disabled = false);
    labelInput?.focus();
  });

  async function submitValue(type, label, details){
    try {
      const payload = {type, label, details, csrf_token: csrf};
      if (type !== 'area' && parentAreaSelect && !parentAreaWrap?.classList.contains('d-none')) {
        payload.parent_area = parentAreaSelect.value || '';
      }
      if (type === 'box' && parentSubSelect && !parentSubWrap?.classList.contains('d-none')) {
        payload.parent_sub_zone = parentSubSelect.value || '';
      }
      const res = await fetch('/public/ajax/location_options.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to save');
      areaCache = null;
      subZoneCache = null;
      await refreshSelect(type, data.value || label);
      ensureModal()?.hide();
    } catch (err) {
      alert(err.message || 'Could not save option.');
    }
  }

  saveBtn?.addEventListener('click', () => {
    const type = currentType;
    const label = (labelInput?.value ?? '').trim();
    const details = (detailInput?.value ?? '').trim();
    if (!type || !label) {
      alert('Please enter a value.');
      return;
    }
    if (type === 'sub_zone' && parentAreaSelect && !parentAreaSelect.value) {
      alert('Please select a zone first.');
      parentAreaSelect.focus();
      return;
    }
    if (type === 'box') {
      if (parentAreaSelect && !parentAreaSelect.value) {
        alert('Please select a zone first.');
        parentAreaSelect.focus();
        return;
      }
      if (parentSubSelect && !parentSubSelect.value) {
        alert('Please select a sub zone.');
        parentSubSelect.focus();
        return;
      }
    }
    submitValue(type, label, details);
  });

  document.querySelectorAll('[data-loc-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const t = btn.dataset.locAdd;
      if (!t) return;
      openModal(t).catch(err => alert(err.message || 'Failed to open form.'));
    });
  });
})();

// Enable Client ID edit toggle
document.getElementById('toggleClientId')?.addEventListener('change', (e)=>{
  const wrap = document.getElementById('clientIdWrap');
  const inp = document.getElementById('client_id_new');
  if(!wrap || !inp) return;
  if(e.target.checked){
    wrap.classList.remove('d-none');
    inp.readOnly = false;
    inp.classList.add('border-warning');
    inp.focus();
  } else {
    wrap.classList.add('d-none');
    inp.readOnly = true;
    inp.classList.remove('border-warning');
  }
});
</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
