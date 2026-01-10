<?php
// /public/sms_templates.php
// SMS template manager.

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
$_active = 'sms_templates';
$page_title = 'SMS Template';

$table_ready = true;
$setup_error = '';
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(80) NOT NULL,
    channel ENUM('sms','email') NOT NULL,
    subject VARCHAR(200) NULL,
    body TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tpl (template_key, channel)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
  $table_ready = false;
  $setup_error = 'Template table setup failed: ' . $e->getMessage();
}

function make_template_key(PDO $pdo, string $name): string {
  $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
  if ($key === '') $key = 'sms_template';
  $base = $key;
  $i = 1;
  while (true) {
    $st = $pdo->prepare("SELECT 1 FROM notification_templates WHERE template_key=? AND channel='sms' LIMIT 1");
    $st->execute([$key]);
    if (!$st->fetchColumn()) break;
    $key = $base . '_' . $i;
    $i++;
  }
  return $key;
}

$alert = '';
$alert_kind = 'success';
$errors = [];
$open_modal = false;
$form_data = [
  'id' => 0,
  'template_name' => '',
  'template_key' => '',
  'template_body' => '',
  'active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ready) {
  $open_modal = true;
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $alert = 'Invalid CSRF token.';
    $alert_kind = 'danger';
  } else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim((string)($_POST['template_name'] ?? ''));
      $key_in = trim((string)($_POST['template_key'] ?? ''));
      $body = trim((string)($_POST['template_body'] ?? ''));
      $active = isset($_POST['active']) ? 1 : 0;

      $form_data = [
        'id' => $id,
        'template_name' => $name,
        'template_key' => $key_in,
        'template_body' => $body,
        'active' => $active,
      ];

      if ($name === '') $errors[] = 'Template name is required.';
      if ($body === '') $errors[] = 'Template body is required.';

      if (!$errors) {
        if ($id > 0) {
          $st = $pdo->prepare("UPDATE notification_templates SET subject=?, body=?, active=? WHERE id=? AND channel='sms'");
          $st->execute([$name, $body, $active, $id]);
          $alert = 'Template updated successfully.';
          $alert_kind = 'success';
          $open_modal = false;
        } else {
          $key = $key_in !== '' ? strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '_', $key_in), '_')) : '';
          if ($key === '') {
            $key = make_template_key($pdo, $name);
          } else {
            $st = $pdo->prepare("SELECT 1 FROM notification_templates WHERE template_key=? AND channel='sms' LIMIT 1");
            $st->execute([$key]);
            if ($st->fetchColumn()) {
              $errors[] = 'Template key already exists.';
            }
          }
          if (!$errors) {
            $st = $pdo->prepare("INSERT INTO notification_templates (template_key, channel, subject, body, active) VALUES (?,?,?,?,?)");
            $st->execute([$key, 'sms', $name, $body, $active]);
            $alert = 'Template added successfully.';
            $alert_kind = 'success';
            $open_modal = false;
            $form_data = [
              'id' => 0,
              'template_name' => '',
              'template_key' => '',
              'template_body' => '',
              'active' => 1,
            ];
          }
        }
      }
    }
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$per = (int)($_GET['per'] ?? 10);
$per = in_array($per, [10, 25, 50, 100], true) ? $per : 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$off = ($page - 1) * $per;

$templates = [];
$total = 0;
if ($table_ready) {
  $where = "WHERE channel='sms'";
  $args = [];
  if ($q !== '') {
    $where .= " AND (template_key LIKE ? OR subject LIKE ? OR body LIKE ?)";
    $args = array_fill(0, 3, '%' . $q . '%');
  }
  $tc = $pdo->prepare("SELECT COUNT(1) FROM notification_templates $where");
  $tc->execute($args);
  $total = (int)$tc->fetchColumn();

  $sql = "SELECT * FROM notification_templates $where ORDER BY id ASC LIMIT $per OFFSET $off";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $templates = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

<div class="container-fluid py-3 sms-template-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="sms-page-icon"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">SMS Template</h4>
          <span class="text-muted small">Add SMS Template</span>
        </div>
      </div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-chat-left-dots me-1"></i> SMS Service
      <span class="mx-1">&rsaquo;</span> SMS Template
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

  <div class="card sms-template-card">
    <div class="card-header bg-white d-flex justify-content-end">
      <button type="button" class="btn btn-primary btn-sm btn-add-template" data-bs-toggle="modal" data-bs-target="#smsTemplateModal">
        <i class="bi bi-plus-lg me-1"></i> Add Template
      </button>
    </div>
    <div class="card-body">
      <form class="sms-template-toolbar" method="get">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
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
            <input type="text" name="q" class="form-control form-control-sm" value="<?php echo h($q); ?>" placeholder="Search">
          </div>
        </div>
      </form>

      <div class="table-responsive mt-3">
        <table class="table table-sm table-hover align-middle sms-template-table">
          <thead>
            <tr>
              <th style="width:70px;">Serial</th>
              <th style="width:220px;">Template Name</th>
              <th style="width:120px;">Template Type</th>
              <th>Template</th>
              <th style="width:80px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$templates): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">No templates found.</td></tr>
            <?php else: ?>
              <?php foreach ($templates as $idx => $t): ?>
                <?php
                  $name = (string)($t['subject'] ?? '');
                  if ($name === '') $name = (string)$t['template_key'];
                  $badge = ((int)$t['active'] === 1) ? 'primary' : 'secondary';
                  $badge_label = ((int)$t['active'] === 1) ? 'Default' : 'Inactive';
                ?>
                <tr>
                  <td><?php echo (int)($off + $idx + 1); ?></td>
                  <td><?php echo h($name); ?></td>
                  <td><span class="badge bg-<?php echo $badge; ?>"><?php echo h($badge_label); ?></span></td>
                  <td class="sms-template-body"><?php echo h((string)$t['body']); ?></td>
                  <td>
                    <button type="button"
                            class="btn btn-sm btn-outline-success btn-edit-template"
                            data-id="<?php echo (int)$t['id']; ?>"
                            data-name="<?php echo h($name); ?>"
                            data-key="<?php echo h((string)$t['template_key']); ?>"
                            data-body="<?php echo h((string)$t['body']); ?>"
                            data-active="<?php echo (int)$t['active']; ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#smsTemplateModal">
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

<div class="modal fade" id="smsTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="tplId" value="<?php echo (int)$form_data['id']; ?>">
      <div class="modal-header">
        <h5 class="modal-title">SMS Template</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Template Name</label>
            <input type="text" class="form-control" name="template_name" id="tplName" value="<?php echo h($form_data['template_name']); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Template Key</label>
            <input type="text" class="form-control" name="template_key" id="tplKey" value="<?php echo h($form_data['template_key']); ?>" placeholder="Auto-generated" <?php echo (int)$form_data['id'] > 0 ? 'readonly' : ''; ?>>
            <div class="form-text">Used for integration.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Template</label>
            <textarea class="form-control" name="template_body" id="tplBody" rows="6" required><?php echo h($form_data['template_body']); ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="tplActive" name="active" value="1" <?php echo (int)$form_data['active'] === 1 ? 'checked' : ''; ?>>
              <label class="form-check-label" for="tplActive">Default (active)</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Template</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const addBtn = document.querySelector('.btn-add-template');
  const editBtns = document.querySelectorAll('.btn-edit-template');
  const idEl = document.getElementById('tplId');
  const nameEl = document.getElementById('tplName');
  const keyEl = document.getElementById('tplKey');
  const bodyEl = document.getElementById('tplBody');
  const activeEl = document.getElementById('tplActive');
  const shouldOpen = <?php echo $open_modal ? 'true' : 'false'; ?>;

  function fillForm(data){
    if (idEl) idEl.value = data.id || 0;
    if (nameEl) nameEl.value = data.name || '';
    if (keyEl) {
      keyEl.value = data.key || '';
      keyEl.readOnly = Number(data.id || 0) > 0;
    }
    if (bodyEl) bodyEl.value = data.body || '';
    if (activeEl) activeEl.checked = data.active !== 0;
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      fillForm({id: 0, name: '', key: '', body: '', active: 1});
    });
  }

  editBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      fillForm({
        id: btn.dataset.id || 0,
        name: btn.dataset.name || '',
        key: btn.dataset.key || '',
        body: btn.dataset.body || '',
        active: parseInt(btn.dataset.active || '1', 10)
      });
    });
  });

  if (shouldOpen) {
    const modalEl = document.getElementById('smsTemplateModal');
    if (modalEl && window.bootstrap) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }
})();
</script>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
