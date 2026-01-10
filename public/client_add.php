<?php
// (বাংলা) লজিক app/PHP/client_add_logic.php এ রাখা হয়েছে
require_once __DIR__ . '/../app/PHP/client_add_logic.php';
include __DIR__ . '/../partials/partials_header.php';
$clientAddCssVer = @filemtime(__DIR__ . '/css/client_add.css') ?: time();
$clientAddJsVer  = @filemtime(__DIR__ . '/js/client_add.js') ?: time();
?>
<link rel="stylesheet" href="/public/css/client_add.css?v=<?= $clientAddCssVer ?>">

<script>
// (বাংলা) JS এর জন্য প্রয়োজনীয় ডাটা
window.CLIENT_ADD_BOOT = {
  locCsrf: <?= json_encode($LOC_CSRF ?? '', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="/public/js/client_add.js?v=<?= $clientAddJsVer ?>" defer></script>



<?php if (hasPermission('add.client')){ ?>



<div class="container-fluid py-3 text-start">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="bi bi-person-plus"></i> Add Client</h6>
    <div class="d-flex gap-2">
      <!-- <button type="submit" form="client-add-form" class="btn btn-primary btn-sm">
        <i class="bi bi-save2"></i> Create Client
      </button> -->
      <button type="submit" form="client-add-form" class="btn btn-outline-secondary btn-sm" >Save</button>
      <?php if ($new_id): ?>
        <a class="btn btn-light btn-sm" href="/public/client_view.php?id=<?= (int)$new_id ?>"><i class="bi bi-eye"></i> View</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php"><i class="bi bi-arrow-left"></i> Back</a>
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

  <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="client-add-form">
    <input type="hidden" name="csrf" value="<?= h($csrf_form) ?>">
    <div class="row g-3">
      <!-- Account -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">
            <span style="font-weight: 700;font-size: 14px;font-weight: 700;"><i class="far fa-user icon-gap"></i> Personal Information</span>
            <br>
            <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 300;">*</span>) Field Data</span>
          </div>
          <div class="p-3">
		  
            <div class="mb-2">
              <label class="form-label req">Customer Name</label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="" value="<?= h($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="mb-2">
              <label class="form-label req">Mobile Number</label>
              <input type="text" name="mobile" pattern="\d{11}" maxlength="11" inputmode="numeric" class="form-control form-control-sm" value="<?= h($_POST['mobile'] ?? '') ?>" required>
              <!-- <div class="form-text small">Enter 11-digit mobile number (digits only).</div> -->
            </div>

            
            <div class="mb-2">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control form-control-sm" value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <?php if ($HAS_NID): ?>
            <div class="mb-2">
              <label class="form-label">NID No.</label>
              <input type="text" name="nid" class="form-control form-control-sm" value="<?= h($_POST['nid'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <?php if ($HAS_DOB): ?>
            <div class="mb-2">
              <label class="form-label">DOB</label>
              <input type="date" name="dob" class="form-control form-control-sm" value="<?= h($_POST['dob'] ?? '') ?>">
            </div>
            <?php endif; ?>


            <?php if ($HAS_CLIENT_CODE): ?>
            <div class="mb-2">
              <label class="form-label req">Client Code</label>
              <input type="text" name="client_code" class="form-control form-control-sm" value="<?= h($_POST['client_code'] ?? '') ?>" required>
            </div>
            <?php endif; ?>

            <?php $form_area = (string)($_POST['area'] ?? ''); $area_opts = ensure_option_present($area_options, $form_area); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Area</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="area"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="area" class="form-select form-select-sm" data-loc-type="area" required>
                <option value="">Select</option>
                <?php foreach ($area_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_area===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($HAS_SUB_ZONE): ?>
            <?php $form_sub = (string)($_POST['sub_zone'] ?? ''); $sub_opts = ensure_option_present($subzone_options, $form_sub); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Sub Zone</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="sub_zone"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="sub_zone" class="form-select form-select-sm" data-loc-type="sub_zone" required>
                <option value="">Select</option>
                <?php foreach ($sub_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_sub===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($HAS_BOX): ?>
            <?php $form_box = (string)($_POST['box'] ?? ''); $box_opts = ensure_option_present($box_options, $form_box); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0 req">Box</label>
                <button type="button" class="btn btn-outline-primary btn-sm py-0" data-loc-add="box"><i class="fa-sharp-duotone fa-light fa-plus"></i></button>
              </div>
              <select name="box" class="form-select form-select-sm" data-loc-type="box" required>
                <option value="">Select</option>
                <?php foreach ($box_opts as $opt): $opt=(string)$opt; ?>
                  <option value="<?= h($opt) ?>" <?= $form_box===$opt?'selected':'' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($HAS_JOIN_DATE): ?>
            <div class="mb-2">
              <label class="form-label req">Join Date</label>
              <input type="date" name="join_date" class="form-control form-control-sm" value="<?= h($_POST['join_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control form-control-sm" rows="2"><?= h($_POST['address'] ?? '') ?></textarea>
            </div>

          </div>
        </div>
      </div>

      <!-- Billing -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title">
            <span style="font-weight: 700;font-size: 14px;font-weight: 700;"><i class="far fa-list-alt"></i> Billing Information</span>
            <br>
            <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 300;">*</span>) Field Data</span>
          </div>

          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">Package</label>
              <select name="package_id" id="package_id" class="form-select form-select-sm" required>
                <option value="">-- Select --</option>
                <?php foreach ($packages as $p): ?>
                  <option
                    value="<?= (int)$p['id'] ?>"
                    data-price="<?= is_numeric($p['price']) ? (0+$p['price']) : 0 ?>"
                    data-router="<?= isset($p['router_id']) ? (int)$p['router_id'] : 0 ?>"
                    <?= (isset($_POST['package_id']) && (int)$_POST['package_id']===(int)$p['id'])?'selected':'' ?>
                  >
                    <?= h($p['name']) ?> <?= is_numeric($p['price'])? '— '.(0+$p['price']):'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <!-- <div class="form-text small">(Package name = MikroTik PPP profile name 1:1)</div> -->
            </div>

            <div class="mb-2">
              <label class="form-label req">Monthly Bill</label>
              <input type="number" step="0.01" name="monthly_bill" id="monthly_bill" class="form-control form-control-sm" value="<?= h($_POST['monthly_bill'] ?? '0') ?>" required>
              <!-- <div class="form-text small" id="autoHint">Auto from package price when selection changes.</div> -->
            </div>

            <div class="mb-2">
              <label class="form-label">Expiry Date</label>
              <?php
                $exp_raw = (string)($_POST['expiry_date'] ?? '');
                $exp_day = '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp_raw)) {
                  $exp_day = (string)(int)substr($exp_raw, 8, 2);
                } elseif (preg_match('/^\d{1,2}$/', $exp_raw)) {
                  $exp_day = (string)(int)$exp_raw;
                }
              ?>
              <select name="expiry_date" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php for ($d=1; $d<=31; $d++): ?>
                  <option value="<?= $d ?>" <?= $exp_day===(string)$d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Status</label>
              <select name="status" class="form-select form-select-sm">
                <?php
                  $opts = ['active'=>'Active','inactive'=>'Inactive','pending'=>'Pending','hold'=>'Hold','disabled'=>'Disabled','blocked'=>'Blocked','expired'=>'Expired'];
                  $cur  = strtolower(trim($_POST['status'] ?? 'active'));
                  foreach($opts as $k=>$v){
                    echo '<option value="'.h($k).'"'.($cur===$k?' selected':'').'>'.h($v).'</option>';
                  }
                ?>
              </select>
            </div>

            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="auto_invoice" name="auto_invoice" value="1" <?= isset($_POST['auto_invoice']) ? ( ($_POST['auto_invoice']?'checked':'') ) : 'checked' ?>>
              <!-- <label class="form-check-label" for="auto_invoice">Generate first invoice now</label> -->
            </div>
          </div>
        </div>
      </div>

      <!-- Server / PPP + Photo -->
      <div class="col-12 col-lg-4">
        <div class="card-block h-100">
          <div class="card-title"><span style="font-weight: 700;font-weight: 700;"><i class="fas fa-wifi icon-gap"></i> Service Information</span>
          <br>
          <span style="font-size: 14px;">Fill Up All Required(<span style="color: red;font-weight: 700;">*</span>) Field Data</span>
        </div>
          <div class="p-3">
            <div class="mb-2">
              <label class="form-label req">PPPoE Server</label>
              <select name="router_id" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php foreach ($routers as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (isset($_POST['router_id']) && (int)$_POST['router_id']===(int)$r['id'])?'selected':'' ?>required>
                    <?= h($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div id="router_id" class="form-text small"></div>
            </div>

            <div class="mb-2">
              <label class="form-label req">Username</label>
              <input type="text" name="pppoe_id" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_id'] ?? '') ?>" required>
              <div id="pppoe_status" class="form-text small"></div>
            </div>

            <?php if ($HAS_PPPOE_PASS || $HAS_PPPOE_PASSWORD): ?>
            <div class="mb-2">
              <label class="form-label req">Password</label>
              <input type="text" name="pppoe_pass" class="form-control form-control-sm mono" value="<?= h($_POST['pppoe_pass'] ?? '') ?>"required>
            <div id="pppoe_pass" class="form-text small"></div>
            </div>
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label req">Profile</label>
              <select name="ppp_profile" id="ppp_profile" class="form-select form-select-sm">
                <option value="">Select</option>
                <?php if (!empty($_POST['ppp_profile'])): ?>
                  <option value="<?= h($_POST['ppp_profile']) ?>" selected><?= h($_POST['ppp_profile']) ?></option>
                <?php endif; ?>
              </select>
              <div id="ppp_profile" class="form-text small"></div>
            </div>

            <div class="card mt-3">
              <div class="card-header fw-bold">Photo (optional)</div>
              <div class="card-body">
                <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm" <?= $HAS_PHOTO_URL?'':'disabled' ?>required>
                <div class="form-text small">Supported: JPG, PNG • Max 3MB • Filename will be PPPoE-ID</div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3"></div>
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
          <div id="locSaveNotice" class="form-text small d-none"></div>
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




<?php } ?>
<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
