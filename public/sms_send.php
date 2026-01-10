<?php
// /public/sms_send.php
// Bulk/group SMS sending page.

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
$_active = 'sms_send';
$page_title = 'Send SMS';

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

function client_filter_sql(PDO $pdo, string $type): array {
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
      return ['1=0', []];
    }
  } elseif ($type === 'offline') {
    if (col_exists($pdo, 'clients', 'online')) {
      $where[] = "(online IS NULL OR online IN (0,'0','no','offline','down','inactive'))";
    } else {
      return ['1=0', []];
    }
  }
  return [$where ? implode(' AND ', $where) : '1=1', []];
}

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

$groups = [];
try {
  $st = $pdo->query("SELECT id, group_name, member_type, status FROM sms_groups ORDER BY id ASC");
  $groups = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $groups = [];
}

$alert = '';
$alert_kind = 'success';
$errors = [];
$message = '';
$template_key = '';
$selected_groups = [];
$selected_users = [];
$users = [];
$sent = 0;
$failed = 0;

function load_users_by_groups(PDO $pdo, array $group_types): array {
  if (!$group_types) return [];
  $clauses = [];
  $args = [];
  foreach ($group_types as $type) {
    [$where, $params] = client_filter_sql($pdo, $type);
    $clauses[] = '(' . $where . ')';
    $args = array_merge($args, $params);
  }
  $where_sql = $clauses ? implode(' OR ', $clauses) : '1=1';
  $sql = "SELECT id, name, mobile FROM clients WHERE $where_sql ORDER BY id ASC";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    $alert = 'Invalid CSRF token.';
    $alert_kind = 'danger';
  } else {
    $action = (string)($_POST['action'] ?? '');
    $selected_groups = array_map('intval', $_POST['groups'] ?? []);
    $selected_users = array_map('intval', $_POST['users'] ?? []);
    $template_key = trim((string)($_POST['template_key'] ?? ''));
    $message = trim((string)($_POST['sms_message'] ?? ''));

    $group_types = [];
    if ($selected_groups && $groups) {
      $by_id = [];
      foreach ($groups as $g) $by_id[(int)$g['id']] = $g;
      foreach ($selected_groups as $gid) {
        if (!isset($by_id[$gid])) continue;
        $group_types[] = (string)($by_id[$gid]['member_type'] ?? 'all');
      }
    }

    if ($action === 'transfer') {
      $users = load_users_by_groups($pdo, array_values(array_unique($group_types)));
    }

    if ($action === 'send') {
      if ($message === '' && $template_key !== '' && isset($template_map[$template_key])) {
        $message = trim($template_map[$template_key]);
      }

      if ($message === '') $errors[] = 'Please enter a message.';

      $users = load_users_by_groups($pdo, array_values(array_unique($group_types)));
      $user_map = [];
      foreach ($users as $u) $user_map[(int)$u['id']] = $u;

      $recipients = [];
      if ($selected_users) {
        foreach ($selected_users as $uid) {
          if (!isset($user_map[$uid])) continue;
          $mob = trim((string)$user_map[$uid]['mobile']);
          if ($mob !== '') $recipients[] = $mob;
        }
      } else {
        foreach ($users as $u) {
          $mob = trim((string)$u['mobile']);
          if ($mob !== '') $recipients[] = $mob;
        }
      }

      $recipients = array_values(array_unique($recipients));
      if (!$recipients) $errors[] = 'No recipients selected.';

      if (!$errors) {
        $cfg = notify_cfg();
        foreach ($recipients as $num) {
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
          $selected_users = [];
        } else {
          $alert = "SMS sent: {$sent}. Failed: {$failed}.";
          $alert_kind = 'warning';
        }
      } else {
        $alert = 'Please fix the errors and try again.';
        $alert_kind = 'danger';
      }
    }
  }
}

require_once __DIR__ . '/../partials/partials_header.php';
?>

<div class="container-fluid py-3 sms-send-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="sms-page-icon"><i class="bi bi-chat-square-dots"></i></div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="mb-0">Send SMS</h4>
          <span class="text-muted small">SMS Sending</span>
        </div>
      </div>
    </div>
    <div class="text-muted small">
      <i class="bi bi-chat-left-dots me-1"></i> SMS Service
      <span class="mx-1">&rsaquo;</span> Send SMS
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

  <div class="card sms-send-card">
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <div class="row g-4">
          <div class="col-xl-6">
            <div class="mb-3">
              <label class="form-label text-uppercase small fw-semibold">Template</label>
              <select name="template_key" class="form-select" id="sendTemplate">
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
              <label class="form-label text-uppercase small fw-semibold">Message (<span id="msgCount">0</span>)</label>
              <textarea name="sms_message" id="sendMessage" class="form-control sms-textarea" rows="5"><?php echo h($message); ?></textarea>
            </div>
            <div class="groups-panel">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0 text-muted">Groups</h6>
              </div>
              <div class="table-responsive groups-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:80px;"><input type="checkbox" id="groupAll"></th>
                      <th>Name</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$groups): ?>
                      <tr><td colspan="2" class="text-center text-muted py-3">No groups found.</td></tr>
                    <?php else: foreach ($groups as $g): ?>
                      <tr>
                        <td>
                          <input type="checkbox" class="group-check" name="groups[]" value="<?php echo (int)$g['id']; ?>" <?php echo in_array((int)$g['id'], $selected_groups, true) ? 'checked' : ''; ?>>
                        </td>
                        <td><?php echo h((string)$g['group_name']); ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="action" value="transfer" class="btn btn-primary btn-sm">
                  <i class="bi bi-arrow-right-circle me-1"></i> Transfer
                </button>
              </div>
            </div>
          </div>

          <div class="col-xl-6">
            <div class="users-panel">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0 text-muted">Users</h6>
              </div>
              <div class="table-responsive users-table">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th style="width:80px;"><input type="checkbox" id="userAll"></th>
                      <th>Name</th>
                      <th style="width:160px;">Mobile</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$users): ?>
                      <tr><td colspan="3" class="text-center text-muted py-4">No users loaded.</td></tr>
                    <?php else: foreach ($users as $u): ?>
                      <tr>
                        <td>
                          <input type="checkbox" class="user-check" name="users[]" value="<?php echo (int)$u['id']; ?>" <?php echo in_array((int)$u['id'], $selected_users, true) ? 'checked' : ''; ?>>
                        </td>
                        <td><?php echo h((string)$u['name']); ?></td>
                        <td><?php echo h((string)$u['mobile']); ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="d-flex align-items-center justify-content-between mt-3 gap-3 flex-wrap">
                <div class="count-box">
                  <span>Total Client</span>
                  <input type="text" id="totalClient" class="form-control form-control-sm" readonly>
                </div>
                <div class="count-box">
                  <span>Selected Client</span>
                  <input type="text" id="selectedClient" class="form-control form-control-sm" readonly>
                </div>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="action" value="send" class="btn btn-primary btn-sm">
                  <i class="bi bi-send me-1"></i> Send Message
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const template = document.getElementById('sendTemplate');
  const message = document.getElementById('sendMessage');
  const msgCount = document.getElementById('msgCount');
  const userAll = document.getElementById('userAll');
  const groupAll = document.getElementById('groupAll');

  function updateCount(){
    if (!message || !msgCount) return;
    msgCount.textContent = String(message.value.length);
  }

  function updateSelected(){
    const users = document.querySelectorAll('.user-check');
    const total = users.length;
    const selected = Array.from(users).filter((u) => u.checked).length;
    const totalEl = document.getElementById('totalClient');
    const selectedEl = document.getElementById('selectedClient');
    if (totalEl) totalEl.value = total ? total : 0;
    if (selectedEl) selectedEl.value = selected ? selected : 0;
  }

  if (template && message) {
    template.addEventListener('change', () => {
      const opt = template.options[template.selectedIndex];
      const body = opt ? opt.getAttribute('data-body') : '';
      if (body) message.value = body;
      updateCount();
    });
  }
  if (message) {
    message.addEventListener('input', updateCount);
  }
  updateCount();

  if (userAll) {
    userAll.addEventListener('change', () => {
      document.querySelectorAll('.user-check').forEach((cb) => { cb.checked = userAll.checked; });
      updateSelected();
    });
  }
  if (groupAll) {
    groupAll.addEventListener('change', () => {
      document.querySelectorAll('.group-check').forEach((cb) => { cb.checked = groupAll.checked; });
    });
  }
  document.querySelectorAll('.user-check').forEach((cb) => {
    cb.addEventListener('change', () => {
      updateSelected();
    });
  });
  updateSelected();
})();
</script>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
