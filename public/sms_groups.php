<?php
// /public/sms_groups.php
// SMS groups manager.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';

if (function_exists('require_perm')) {
  require_perm('send.sms');
}

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$csrf = csrf_ensure_token();
$_active = 'sms_groups';
$page_title = 'SMS Groups';

function col_exists(PDO $pdo, string $table, string $col): bool {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$db, $table, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

$setup_error = '';
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS sms_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(120) NOT NULL,
    member_type VARCHAR(30) NOT NULL DEFAULT 'all',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
  $setup_error = 'SMS groups table setup failed: ' . $e->getMessage();
}

$member_types = [
  'all' => 'All Clients',
  'active' => 'Active Clients',
  'inactive' => 'Inactive Clients',
  'expired' => 'Expired Clients',
  'pending' => 'Pending Clients',
  'disabled' => 'Disabled Clients',
  'left' => 'Left Clients',
  'online' => 'Online Clients',
  'offline' => 'Offline Clients',
];

$alert = '';
$alert_kind = 'success';
$errors = [];
$open_modal = false;
$form_data = [
  'id' => 0,
  'group_name' => '',
  'member_type' => 'all',
  'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setup_error === '') {
  $open_modal = true;
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $alert = 'Invalid CSRF token.';
    $alert_kind = 'danger';
  } else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim((string)($_POST['group_name'] ?? ''));
      $member_type = (string)($_POST['member_type'] ?? 'all');
      $status = (string)($_POST['status'] ?? 'active');
      if (!isset($member_types[$member_type])) $member_type = 'all';
      if (!in_array($status, ['active','inactive'], true)) $status = 'active';

      $form_data = [
        'id' => $id,
        'group_name' => $name,
        'member_type' => $member_type,
        'status' => $status,
      ];

      if ($name === '') $errors[] = 'Group name is required.';

      if (!$errors) {
        if ($id > 0) {
          $st = $pdo->prepare("UPDATE sms_groups SET group_name=?, member_type=?, status=? WHERE id=?");
          $st->execute([$name, $member_type, $status, $id]);
          $alert = 'Group updated successfully.';
          $alert_kind = 'success';
          $open_modal = false;
        } else {
          $st = $pdo->prepare("INSERT INTO sms_groups (group_name, member_type, status) VALUES (?,?,?)");
          $st->execute([$name, $member_type, $status]);
          $alert = 'Group created successfully.';
          $alert_kind = 'success';
          $open_modal = false;
          $form_data = [
            'id' => 0,
            'group_name' => '',
            'member_type' => 'all',
            'status' => 'active',
          ];
        }
      }
    }
  }
}

$filter_member = (string)($_GET['member_status'] ?? '');
if (!isset($member_types[$filter_member])) $filter_member = '';
$q = trim((string)($_GET['q'] ?? ''));
$per = (int)($_GET['per'] ?? 100);
$per = in_array($per, [10, 25, 50, 100], true) ? $per : 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$off = ($page - 1) * $per;

$groups = [];
$total = 0;
if ($setup_error === '') {
  $where = [];
  $args = [];
  if ($filter_member !== '') {
    $where[] = 'member_type = ?';
    $args[] = $filter_member;
  }
  if ($q !== '') {
    $where[] = 'group_name LIKE ?';
    $args[] = '%' . $q . '%';
  }
  $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

  $tc = $pdo->prepare("SELECT COUNT(1) FROM sms_groups $where_sql");
  $tc->execute($args);
  $total = (int)$tc->fetchColumn();

  $st = $pdo->prepare("SELECT * FROM sms_groups $where_sql ORDER BY id ASC LIMIT $per OFFSET $off");
  $st->execute($args);
  $groups = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function client_count(PDO $pdo, string $type): int {
  static $cache = [];
  if (isset($cache[$type])) return $cache[$type];

  $where = [];
  if (col_exists($pdo, 'clients', 'is_deleted')) $where[] = "COALESCE(is_deleted,0)=0";

  if ($type === 'active' || $type === 'inactive' || $type === 'expired' || $type === 'pending' || $type === 'disabled') {
    if (col_exists($pdo, 'clients', 'status')) $where[] = "status='" . $type . "'";
  } elseif ($type === 'left') {
    if (col_exists($pdo, 'clients', 'is_left')) $where[] = "COALESCE(is_left,0)=1";
    elseif (col_exists($pdo, 'clients', 'status')) $where[] = "status='left'";
  } elseif ($type === 'online') {
    if (col_exists($pdo, 'clients', 'online')) {
      $where[] = "online IN (1,'1','yes','online','up')";
    } else {
      $cache[$type] = 0;
      return 0;
    }
  } elseif ($type === 'offline') {
    if (col_exists($pdo, 'clients', 'online')) {
      $where[] = "(online IS NULL OR online IN (0,'0','no','offline','down','inactive'))";
    } else {
      $cache[$type] = 0;
      return 0;
    }
  }

  $sql = "SELECT COUNT(*) FROM clients";
  if ($where) $sql .= " WHERE " . implode(' AND ', $where);
  try {
    $cache[$type] = (int)$pdo->query($sql)->fetchColumn();
  } catch (Throwable $e) {
    $cache[$type] = 0;
  }
  return $cache[$type];
}

$from = $total ? $off + 1 : 0;
$to = $total ? min($off + $per, $total) : 0;
$pages = max(1, (int)ceil($total / $per));

function qs(array $overrides = []): string {
  $params = array_merge($_GET, $overrides);
  return '?' . http_build_query($params);
}

require_once __DIR__ . '/../partials/partials_header.php';
?>

<div class="container-fluid py-3 sms-groups-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="sms-page-icon"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">SMS Groups</h4>
          <span class="text-muted small">All SMS Groups</span>
        </div>
      </div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-chat-left-dots me-1"></i> SMS Service
      <span class="mx-1">&rsaquo;</span> SMS Group
    </div>
  </div>

  <?php if ($setup_error): ?>
    <div class="alert alert-danger py-2"><?php echo h($setup_error); ?></div>
  <?php endif; ?>
  <?php if ($alert): ?>
    <div class="alert alert-<?php echo h($alert_kind); ?> py-2"><?php echo h($alert); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger py-2"><?php echo h(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <div class="card sms-groups-card">
    <div class="card-header bg-white d-flex justify-content-end">
      <button type="button" class="btn btn-primary btn-sm btn-add-group" data-bs-toggle="modal" data-bs-target="#smsGroupModal">
        <i class="bi bi-plus-lg me-1"></i> New Group
      </button>
    </div>
    <div class="card-body">
      <form class="sms-groups-filter mb-3" method="get">
        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label text-uppercase small fw-semibold">Member Status</label>
            <select name="member_status" class="form-select">
              <option value="">Select</option>
              <?php foreach ($member_types as $key => $label): ?>
                <option value="<?php echo h($key); ?>" <?php echo $filter_member === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
          <div class="d-flex align-items-center gap-2">
            <label class="text-muted small">Show</label>
            <select name="per" class="form-select form-select-sm" style="width: 90px" onchange="this.form.submit()">
              <?php foreach ([10,25,50,100] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php echo $per === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-muted small">Entries</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <label class="text-muted small mb-0">Search:</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?php echo h($q); ?>" placeholder="Search group">
          </div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle sms-groups-table">
          <thead>
            <tr>
              <th style="width:80px;">Sr. No.</th>
              <th style="width:240px;">Group Name</th>
              <th style="width:120px;">Status</th>
              <th style="width:220px;">Member Types</th>
              <th style="width:140px;">Member Count</th>
              <th style="width:80px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$groups): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No data available in table</td></tr>
            <?php else: ?>
              <?php foreach ($groups as $idx => $g): ?>
                <?php
                  $member_type = (string)($g['member_type'] ?? 'all');
                  $member_label = $member_types[$member_type] ?? 'All Clients';
                  $count = client_count($pdo, $member_type);
                  $status = (string)($g['status'] ?? 'active');
                  $badge = $status === 'active' ? 'success' : 'secondary';
                ?>
                <tr>
                  <td><?php echo (int)($off + $idx + 1); ?></td>
                  <td><?php echo h((string)$g['group_name']); ?></td>
                  <td><span class="badge bg-<?php echo $badge; ?>"><?php echo h(ucfirst($status)); ?></span></td>
                  <td><?php echo h($member_label); ?></td>
                  <td><?php echo (int)$count; ?></td>
                  <td>
                    <button type="button"
                            class="btn btn-sm btn-outline-success btn-edit-group"
                            data-id="<?php echo (int)$g['id']; ?>"
                            data-name="<?php echo h((string)$g['group_name']); ?>"
                            data-type="<?php echo h($member_type); ?>"
                            data-status="<?php echo h($status); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#smsGroupModal">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex align-items-center justify-content-between mt-3">
        <div class="text-muted small">Showing <?php echo $from; ?> to <?php echo $to; ?> of <?php echo (int)$total; ?> entries</div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $page <= 1 ? '#' : h(qs(['page' => $page - 1])); ?>">Previous</a>
            </li>
            <li class="page-item disabled"><span class="page-link"><?php echo $page; ?></span></li>
            <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo $page >= $pages ? '#' : h(qs(['page' => $page + 1])); ?>">Next</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="smsGroupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content sms-group-modal">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="groupId" value="<?php echo (int)$form_data['id']; ?>">
      <div class="modal-header">
        <h5 class="modal-title">New SMS Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="sms-stepper">
          <div class="step active" data-step="1">
            <span class="dot"><i class="bi bi-person"></i></span>
            <span class="label">Group Info</span>
          </div>
          <div class="step" data-step="2">
            <span class="dot"><i class="bi bi-person-check"></i></span>
            <span class="label">Assigned Member</span>
          </div>
          <div class="step" data-step="3">
            <span class="dot"><i class="bi bi-people"></i></span>
            <span class="label">Group Members</span>
          </div>
        </div>

        <div class="sms-step-content" data-step="1">
          <h6 class="mb-3">Group Information <span class="text-muted small">Step 1 - 3</span></h6>
          <div class="mb-3">
            <label class="form-label">Group Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="group_name" id="groupName" value="<?php echo h($form_data['group_name']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Activity Status</label>
            <select name="status" class="form-select" id="groupStatus">
              <option value="active" <?php echo $form_data['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="inactive" <?php echo $form_data['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
          </div>
        </div>

        <div class="sms-step-content d-none" data-step="2">
          <h6 class="mb-3">Assigned Member <span class="text-muted small">Step 2 - 3</span></h6>
          <div class="mb-3">
            <label class="form-label">Member Type</label>
            <select name="member_type" class="form-select" id="memberType">
              <?php foreach ($member_types as $key => $label): ?>
                <option value="<?php echo h($key); ?>" <?php echo $form_data['member_type'] === $key ? 'selected' : ''; ?>><?php echo h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="sms-step-content d-none" data-step="3">
          <h6 class="mb-3">Group Members <span class="text-muted small">Step 3 - 3</span></h6>
          <div class="alert alert-light border">
            This group will include members based on the selected member type.
          </div>
          <div class="small text-muted">Member count is calculated automatically after saving.</div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary btn-prev" disabled>Back</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-primary btn-next">Next</button>
          <button class="btn btn-primary btn-save d-none">Save Group</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const addBtn = document.querySelector('.btn-add-group');
  const editBtns = document.querySelectorAll('.btn-edit-group');
  const steps = document.querySelectorAll('.sms-stepper .step');
  const contents = document.querySelectorAll('.sms-step-content');
  const prevBtn = document.querySelector('.btn-prev');
  const nextBtn = document.querySelector('.btn-next');
  const saveBtn = document.querySelector('.btn-save');
  const shouldOpen = <?php echo $open_modal ? 'true' : 'false'; ?>;

  const idEl = document.getElementById('groupId');
  const nameEl = document.getElementById('groupName');
  const typeEl = document.getElementById('memberType');
  const statusEl = document.getElementById('groupStatus');

  let currentStep = 1;

  function setStep(step){
    currentStep = step;
    steps.forEach((el) => el.classList.toggle('active', Number(el.dataset.step) === step));
    contents.forEach((el) => el.classList.toggle('d-none', Number(el.dataset.step) !== step));
    if (prevBtn) prevBtn.disabled = step === 1;
    if (nextBtn) nextBtn.classList.toggle('d-none', step === 3);
    if (saveBtn) saveBtn.classList.toggle('d-none', step !== 3);
  }

  function fillForm(data){
    if (idEl) idEl.value = data.id || 0;
    if (nameEl) nameEl.value = data.name || '';
    if (typeEl) typeEl.value = data.type || 'all';
    if (statusEl) statusEl.value = data.status || 'active';
    setStep(1);
  }

  if (addBtn) addBtn.addEventListener('click', () => fillForm({id: 0, name: '', type: 'all', status: 'active'}));
  editBtns.forEach((btn) => {
    btn.addEventListener('click', () => fillForm({
      id: btn.dataset.id || 0,
      name: btn.dataset.name || '',
      type: btn.dataset.type || 'all',
      status: btn.dataset.status || 'active'
    }));
  });

  if (prevBtn) prevBtn.addEventListener('click', () => setStep(Math.max(1, currentStep - 1)));
  if (nextBtn) nextBtn.addEventListener('click', () => setStep(Math.min(3, currentStep + 1)));

  if (shouldOpen) {
    const modalEl = document.getElementById('smsGroupModal');
    if (modalEl && window.bootstrap) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  const memberSelect = document.querySelector('select[name="member_status"]');
  if (memberSelect && memberSelect.form) {
    memberSelect.addEventListener('change', () => memberSelect.form.submit());
  }
})();
</script>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
