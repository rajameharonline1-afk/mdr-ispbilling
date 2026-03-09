<?php
// /partials/client_activity_widget.php
// Purpose: Show last 10 audit log entries for this client on client_view.php
// Works even if audit_logs lacks entity_type or JSON functions.

if (!isset($pdo) || !isset($client['id'])) { return; }
$clientId = (int)$client['id'];

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// ------ table present? ------
$has_audit = true;
try { $pdo->query("SELECT 1 FROM audit_logs LIMIT 1"); }
catch (Throwable $e) { $has_audit = false; }
if (!$has_audit) { return; }

// ------ schema detect ------
$colExists = function(string $col) use ($pdo): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `audit_logs` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
};

$tsExpr = null;
foreach (['ts','created_at','logged_at','time','timestamp','event_time'] as $c) {
  if ($colExists($c)) { $tsExpr = "al.`$c`"; break; }
}
if ($tsExpr === null) { $tsExpr = 'al.id'; }

$has_user_id  = $colExists('user_id');
$has_ip       = $colExists('ip');
$has_entityid = $colExists('entity_id');
$has_action   = $colExists('action');
$has_entity   = $colExists('entity');
$has_old      = $colExists('old_json');
$has_new      = $colExists('new_json');
$has_details  = $colExists('details');

// users join (for actor name)
$has_users = false;
$user_name_col = null;
try {
  $pdo->query("SELECT 1 FROM users LIMIT 1");
  $has_users = true;
} catch (Throwable $e) { $has_users = false; }
if ($has_users) {
  foreach (['full_name','name','username','email'] as $c) {
    $st = $pdo->prepare("SHOW COLUMNS FROM `users` LIKE ?");
    $st->execute([$c]);
    if ($st->fetchColumn()) { $user_name_col = $c; break; }
  }
}

// SELECT list (safe aliases)
$selects = [
  "al.id",
  "$tsExpr AS ts",
  $has_user_id ? "al.user_id" : "NULL AS user_id",
  $has_action  ? "al.action"  : "''   AS action",
  $has_entity  ? "al.entity"  : "'' AS entity",
  $has_entityid? "al.entity_id"   : "NULL AS entity_id",
  $has_old     ? "al.old_json" : "NULL AS old_json",
  $has_new     ? "al.new_json" : "NULL AS new_json",
  $has_ip      ? "al.ip"      : "NULL AS ip",
];

// ------ WHERE build (no missing columns in predicates) ------
$conds = [];
$params = [];

// client row: if entity_id exists, use it; if entity_type also exists, narrow to 'client'
if ($has_entityid) {
  $conds[]  = $has_entity ? "(al.entity='client' AND al.entity_id=?)" : "(al.entity_id=?)";
  $params[] = $clientId;
}

// invoice row: look for client_id inside JSON-ish blobs (numeric or quoted)
if ($has_old || $has_new || $has_details) {
  $likeNum = '%"client_id":'.(string)$clientId.'%';
  $likeStr = '%"client_id":"'.(string)$clientId.'"%';
  $jsonConds = [];
  if ($has_old)     { $jsonConds[] = "(COALESCE(al.old_json,'') LIKE ? OR COALESCE(al.old_json,'') LIKE ?)";       $params[] = $likeNum; $params[] = $likeStr; }
  if ($has_new)     { $jsonConds[] = "(COALESCE(al.new_json,'') LIKE ? OR COALESCE(al.new_json,'') LIKE ?)";       $params[] = $likeNum; $params[] = $likeStr; }
  if ($has_details) { $jsonConds[] = "(COALESCE(al.details,'')  LIKE ? OR COALESCE(al.details,'')  LIKE ?)";       $params[] = $likeNum; $params[] = $likeStr; }
  if ($jsonConds) $conds[] = '(' . implode(' OR ', $jsonConds) . ')';
}

// nothing detectable? show nothing rather than full table
if (!$conds) { $conds[] = "1=0"; }

$joins = "";
if ($has_users && $user_name_col) {
  $selects[] = "u.`$user_name_col` AS user_name";
  $joins = " LEFT JOIN users u ON u.id = al.user_id ";
}
$sql = "SELECT ".implode(", ", $selects)."
        FROM audit_logs al
        $joins
        WHERE ".implode(" OR ", $conds)."
        ORDER BY ts DESC, al.id DESC
        LIMIT 10";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
  .activity-widget-card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
  }
  .activity-widget-card .card-body { padding: 1.15rem 1.2rem 1.05rem; }
  .activity-table-shell {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    overflow: hidden;
  }
  .activity-table {
    margin-bottom: 0;
    min-width: 100%;
  }
  .activity-table thead th {
    background: linear-gradient(90deg, #1f2a44 0%, #243b55 100%);
    color: #e2e8f0;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.6px;
    border: none;
    padding: 12px 14px;
    white-space: nowrap;
  }
  .activity-table tbody tr {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.12s ease, transform 0.12s ease, box-shadow 0.12s ease;
  }
  .activity-table tbody tr:hover {
    background: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
  }
  .activity-table td {
    vertical-align: middle;
    padding: 12px 14px;
    font-size: 14px;
    color: #0f172a;
  }
  .activity-table .summary-col { line-height: 1.32; font-size: 12px; }
  .activity-table .badge {
    font-weight: 700;
    letter-spacing: 0.3px;
  }
  .cell-subtext { color: #475569; font-size: 12px; }
  .actor-chip {
    background: #eef2ff;
    color: #312e81;
    border-radius: 10px;
    padding: 6px 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .tag-soft {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 10px;
    font-weight: 600;
    color: #0f172a;
  }
  .ip-chip {
    background: #0f172a;
    color: #e2e8f0;
    padding: 6px 10px;
    border-radius: 10px;
    font-family: "SFMono-Regular", Menlo, monospace;
    font-size: 12px;
    letter-spacing: 0.2px;
  }
  .activity-empty { color: #94a3b8; padding: 6px 0; }
  @media (max-width: 992px) {
    .activity-table thead th,
    .activity-table td { padding: 10px 12px; }
    .activity-table thead th { font-size: 11px; }
  }
  @media (max-width: 768px) {
    .activity-table thead { display: none; }
    .activity-table,
    .activity-table tbody,
    .activity-table tr,
    .activity-table td { display: block; width: 100%; }
    .activity-table tbody tr {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 10px 12px;
      margin-bottom: 12px;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }
    .activity-table td {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      border-bottom: 1px dashed #e2e8f0;
      padding: 9px 0;
    }
    .activity-table td:last-child { border-bottom: none; }
    .activity-table td::before {
      content: attr(data-label);
      font-weight: 700;
      text-transform: uppercase;
      color: #475569;
      letter-spacing: 0.4px;
      font-size: 12px;
      flex: 1 1 40%;
    }
    .activity-table .cell-content { flex: 1 1 60%; text-align: right; }
    .activity-table-shell { border-radius: 14px; }
  }
</style>
<div class="card mb-3 activity-widget-card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0">Recent Activity</h5>
      <a class="btn btn-sm btn-outline-secondary"
         href="/public/audit_logs.php?search=<?php echo urlencode((string)$clientId); ?>"
         target="_blank" rel="noopener">Open Logs</a>
    </div>

    <?php if (!$rows): ?>
      <div class="text-muted small">No recent activity found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Time</th>
              <th>Action</th>
              <th>User</th>
              <th>IP</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
            $a = strtolower((string)($r['action'] ?? ''));
            $badge = in_array($a, ['enable','payment_add','invoice_generate','undo_left','package_change'], true) ? 'success'
                   : (in_array($a, ['disable','left','invoice_void'], true) ? 'danger' : 'secondary');

            $short = '';
            $details = ['old' => $r['old_json'] ?? null, 'new' => $r['new_json'] ?? null];
            foreach (['old','new'] as $k) {
              if (is_string($details[$k])) {
                $inner = json_decode($details[$k], true);
                if (json_last_error() === JSON_ERROR_NONE) $details[$k] = $inner;
              }
            }
            $json = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $short = mb_substr($json, 0, 120) . (mb_strlen($json) > 120 ? '…' : '');

            $userName = (string)($r['user_name'] ?? '');
            $uid = isset($r['user_id']) && is_numeric($r['user_id']) ? (int)$r['user_id'] : null;
          ?>
            <tr>
              <td><small class="text-muted"><?php echo h($r['ts']); ?></small></td>
              <td><span class="badge bg-<?php echo $badge; ?>"><?php echo h($r['action'] ?? ''); ?></span></td>
              <td><small>
                <?php
                  if ($uid === 0) echo 'System automatic';
                  elseif ($userName !== '' && $uid) echo h($userName).' (#'.$uid.')';
                  elseif ($uid) echo '#'.$uid;
                  else echo '-';
                ?>
              </small></td>
              <td><small><?php echo h($r['ip'] ?? ''); ?></small></td>
              <td><small class="text-muted"><?php echo h($short); ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
    </div>
