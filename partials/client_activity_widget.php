<?php
// /partials/client_activity_widget.php
// Purpose: Show last 10 audit log entries for this client on client_view.php
// Works even if audit_logs lacks entity_type or JSON functions.

if (!isset($pdo) || !isset($client['id'])) { return; }
$clientId = (int)$client['id'];

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Colored badge picker for action names (basic keyword heuristics)
if (!function_exists('audit_action_badge_class')) {
  function audit_action_badge_class(string $action): string {
    $a = strtolower(trim($action));
    if ($a === '') return 'secondary';
    $normalized = str_replace(['_','-'], ' ', $a);

    // explicit matches first
    $exact = [
      'enable'           => 'success',
      'payment_add'      => 'success',
      'invoice_generate' => 'success',
      'undo_left'        => 'success',
      'package_change'   => 'primary',
      'disable'          => 'danger',
      'left'             => 'danger',
      'invoice_void'     => 'danger',
    ];
    if (isset($exact[$a])) return $exact[$a];

    $groups = [
      'danger'  => ['fail','error','denied','block','void','cancel','delete','remove','drop','reject','expired','timeout'],
      'warning' => ['update','change','edit','renew','retry','pending','suspend','hold'],
      'success' => ['add','create','enable','payment','paid','generate','activate','complete','confirm','approve'],
      'info'    => ['sync','login','logout','fetch','refresh','import','export','email','sms','notify','cron','backup'],
      'primary' => ['set','assign','bind','attach','link','move','connect','upgrade','migrate'],
    ];

    foreach ($groups as $badge => $needles) {
      foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($normalized, $needle)) {
          return $badge;
        }
      }
    }

    return 'secondary';
  }
}

// Normalize JSON/details to array
if (!function_exists('audit_details_to_array')) {
  function audit_details_to_array($details): array {
    if (is_array($details)) return $details;
    if (is_string($details)) {
      $j = json_decode($details, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($j)) return $j;
    }
    return [];
  }
}

// Expand nested JSON strings inside known keys
if (!function_exists('audit_expand_nested')) {
  function audit_expand_nested(array $data): array {
    foreach (['old','new','meta','details'] as $k) {
      if (isset($data[$k]) && is_string($data[$k])) {
        $inner = json_decode($data[$k], true);
        if (json_last_error() === JSON_ERROR_NONE) $data[$k] = $inner;
      }
    }
    return $data;
  }
}
// Extract old/new arrays (fallback to treating payload as new)
if (!function_exists('audit_old_new_from_payload')) {
  function audit_old_new_from_payload($payload): array {
    $details = audit_expand_nested(audit_details_to_array($payload));
    $old = $details['old'] ?? null;
    $new = $details['new'] ?? null;
    if (is_string($old)) { $decoded = json_decode($old, true); if (json_last_error() === JSON_ERROR_NONE) $old = $decoded; }
    if (is_string($new)) { $decoded = json_decode($new, true); if (json_last_error() === JSON_ERROR_NONE) $new = $decoded; }
    if (($old === null || $old === []) && ($new === null || $new === []) && $details) {
      $new = $details;
    }
    return [$old, $new, $details];
  }
}
if (!function_exists('audit_action_icon')) {
  function audit_action_icon(string $action): string {
    $a = strtolower($action);
    if (str_contains($a,'sync')) return '🔄';
    if (str_contains($a,'create') || str_contains($a,'add') || str_contains($a,'new')) return '🆕';
    if (str_contains($a,'update') || str_contains($a,'edit') || str_contains($a,'change')) return '📝';
    if (str_contains($a,'delete') || str_contains($a,'remove')) return '🗑️';
    if (str_contains($a,'login') || str_contains($a,'auth')) return '🔑';
    return '📌';
  }
}
// Bengali-friendly summary (no raw JSON)
if (!function_exists('render_log_message')) {
  function render_log_message(string $action, $json): string {
    [$old, $new, $details] = audit_old_new_from_payload($json);
    $act = trim($action);
    $baseIcon = audit_action_icon($act);
    $safeAction = $act !== '' ? $act : 'লগ';

    $isCreation = ($old === null || $old === []);

    // Creation: numeric id only
    if ($isCreation && is_numeric($new)) {
      return "🆕 নতুন গ্রাহক <b>#".h((string)$new)."</b> সিস্টেমে অন্তর্ভুক্ত করা হয়েছে।";
    }

    // Creation: MikroTik sync (source secret)
    if ($isCreation && is_array($new) && strtolower((string)($new['source'] ?? '')) === 'secret') {
      $pppoe = $new['pppoe_id'] ?? ($new['username'] ?? ($details['pppoe_id'] ?? 'অজানা'));
      $comment = is_array($new['comment_data'] ?? null) ? $new['comment_data'] : [];
      $area = $new['area'] ?? ($comment['area'] ?? ($comment['zone'] ?? ($comment['sub_zone'] ?? '')));
      $bill = $comment['monthly_bill'] ?? ($comment['bill'] ?? ($comment['bill_amount'] ?? ($new['monthly_bill'] ?? null)));
      $bits = [];
      if ($area !== '' && $area !== null) $bits[] = "এলাকা: <b>".h((string)$area)."</b>";
      if ($bill !== null && $bill !== '') $bits[] = "বিল: <b>".h((string)$bill)."</b> টাকা";
      $suffix = $bits ? ' ' . implode(', ', $bits) . '।' : '।';
      return "🔄 মাইক্রোটিক থেকে গ্রাহক (PPPoE: <b>".h((string)$pppoe)."</b>) এর প্রোফাইল সিঙ্ক করা হয়েছে{$suffix}";
    }

    // Creation: generic payload
    if ($isCreation && is_array($new)) {
      $cid = $new['id'] ?? ($new['client_id'] ?? ($details['client_id'] ?? null));
      $name = $new['name'] ?? ($new['client_name'] ?? null);
      $label = $name ? "<b>".h((string)$name)."</b>" : 'নতুন গ্রাহক';
      if ($cid) $label .= " (#".h((string)$cid).")";
      return "🆕 {$label} সিস্টেমে অন্তর্ভুক্ত করা হয়েছে।";
    }

    // Updates: show only changes
    $changes = [];
    $pkgOld = is_array($old) ? ($old['package_name'] ?? ($old['package'] ?? ($old['from_name'] ?? ($old['package_id'] ?? null)))) : null;
    $pkgNew = is_array($new) ? ($new['package_name'] ?? ($new['package'] ?? ($new['to_name'] ?? ($new['package_id'] ?? null)))) : null;
    if ($pkgNew !== null && $pkgOld !== $pkgNew) {
      $from = $pkgOld !== null ? '<b>'.h((string)$pkgOld).'</b>' : 'পূর্বে নির্ধারিত ছিল না';
      $changes[] = "গ্রাহকের প্যাকেজ {$from} থেকে পরিবর্তন করে <b>".h((string)$pkgNew)."</b> করা হয়েছে";
    }

    $subOld = is_array($old) ? ($old['sub_zone'] ?? null) : null;
    $subNew = is_array($new) ? ($new['sub_zone'] ?? ($new['zone'] ?? null)) : null;
    if ($subNew !== null && $subOld !== $subNew) {
      $changes[] = "সাব-জোন <b>".h((string)$subNew)."</b> সেট করা হয়েছে";
    }

    $areaOld = is_array($old) ? ($old['area'] ?? null) : null;
    $areaNew = is_array($new) ? ($new['area'] ?? null) : null;
    if ($areaNew !== null && $areaOld !== $areaNew) {
      $changes[] = "এলাকা <b>".h((string)$areaNew)."</b> এ আপডেট করা হয়েছে";
    }

    $boxOld = is_array($old) ? ($old['box'] ?? null) : null;
    $boxNew = is_array($new) ? ($new['box'] ?? null) : null;
    if ($boxNew !== null && $boxOld !== $boxNew) {
      $changes[] = "বক্স/ডিস্ট্রিবিউশন পয়েন্ট <b>".h((string)$boxNew)."</b> নির্ধারণ করা হয়েছে";
    }

    $fieldMap = [
      'pppoe_id'    => 'PPPoE',
      'client_code' => 'ক্লায়েন্ট কোড',
      'status'      => 'স্ট্যাটাস',
      'mobile'      => 'মোবাইল',
      'phone'       => 'মোবাইল',
      'email'       => 'ইমেইল',
    ];
    foreach ($fieldMap as $key => $label) {
      $o = is_array($old) ? ($old[$key] ?? null) : null;
      $n = is_array($new) ? ($new[$key] ?? null) : null;
      if ($n !== null && $n !== '' && $o !== $n) {
        $from = ($o !== null && $o !== '') ? " (পূর্বে <b>".h((string)$o)."</b>)" : '';
        $changes[] = "{$label} <b>".h((string)$n)."</b>{$from}";
      }
    }

    $passUpdated = is_array($new) && !empty($new['pppoe_pass_set']);
    if ($passUpdated) $changes[] = "পাসওয়ার্ড আপডেট করা হয়েছে";

    if ($changes) {
      $last = array_pop($changes);
      $sentence = $changes ? implode(', ', $changes) . ' এবং ' . $last : $last;
      return "📝 {$sentence}।";
    }

    return "{$baseIcon} ".h($safeAction);
  }
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

// invoice row: only use details if details column exists;
// if entity_type exists, add 'invoice' guard; otherwise just details LIKE
if ($has_old || $has_new) {
  $like1 = '%"client_id":'.(string)$clientId.'%';
  $like2 = '%"client_id":"'.(string)$clientId.'"%';
  $pattern = "(COALESCE(al.old_json,'') LIKE ? OR COALESCE(al.new_json,'') LIKE ?)";
  $conds[] = "($pattern)";
  $params[] = $like1;
  $params[] = $like2;
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
      <div>
        <p class="text-uppercase text-muted small mb-1" style="letter-spacing: 1px;">Overview</p>
        <h5 class="mb-0">Recent Activity</h5>
      </div>
      <a class="btn btn-sm btn-outline-primary"
         href="/public/audit_logs.php?q=<?php echo urlencode((string)$clientId); ?>"
         target="_blank" rel="noopener">Open Logs</a>
    </div>

    <?php if (!$rows): ?>
      <div class="activity-empty small">No recent activity found.</div>
    <?php else: ?>
      <div class="activity-table-shell">
        <div class="table-responsive">
          <table class="table align-middle activity-table">
            <thead>
              <tr>
                <th scope="col">Time</th>
                <th scope="col">Action</th>
                <th scope="col">Summary</th>
                <th scope="col">Entity</th>
                <th scope="col">Actor</th>
                <th scope="col">IP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r):
          $actionRaw = (string)($r['action'] ?? '');
          $badge = audit_action_badge_class($actionRaw);
          $icon  = audit_action_icon($actionRaw);
          $actionLabel = trim(str_replace(['_','-'], ' ', $actionRaw)) ?: $actionRaw;
          $payload = ['old' => $r['old_json'] ?? null, 'new' => $r['new_json'] ?? null];
          $summary = render_log_message($actionRaw, $payload);
          $clientCode = trim((string)($client['client_code'] ?? ''));
          if ($clientCode !== '') {
            $summary = str_replace('#'.$clientId, '#'.$clientCode, $summary);
          }
          $userName = (string)($r['user_name'] ?? '');
          $uid = isset($r['user_id']) && is_numeric($r['user_id']) ? (int)$r['user_id'] : null;
          $actorText = '-';
          if ($uid === 0) $actorText = 'System automatic';
          elseif ($userName !== '' && $uid) $actorText = h($userName) . " (#{$uid})";
                elseif ($uid) $actorText = '#'.$uid;

          $entityLabel = '';
          if (!empty($r['entity'])) $entityLabel = (string)$r['entity'];
          if ($entityLabel !== '' && isset($r['entity_id']) && $r['entity_id']!=='') {
            $entityLabel .= '#'.(string)$r['entity_id'];
          }
          if ($clientCode !== '' && $entityLabel !== '') {
            $entityLabel = 'client#'.$clientCode;
          }
        ?>
                <tr>
                  <td data-label="Time">
                    <div class="cell-content">
                      <div class="fw-semibold"><?php echo h($r['ts']); ?></div>
                      <div class="cell-subtext">#<?php echo h((string)($r['id'] ?? '')); ?></div>
                    </div>
                  </td>
                  <td data-label="Action">
                    <div class="cell-content">
                      <span class="badge rounded-pill bg-<?php echo $badge; ?> text-white px-3 py-2 text-uppercase"><?php echo h($actionLabel); ?></span>
                      <div class="cell-subtext mt-1"><?php echo h($icon); ?> activity</div>
                    </div>
                  </td>
                  <td class="summary-col" data-label="Summary">
                    <div class="cell-content"><?php echo $summary; ?></div>
                  </td>
                  <td data-label="Entity">
                    <div class="cell-content">
                      <?php if ($entityLabel !== ''): ?>
                        <span class="tag-soft"><?php echo h($entityLabel); ?></span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td data-label="Actor">
                    <div class="cell-content">
                      <?php if ($actorText !== '-'): ?>
                        <span class="actor-chip"><?php echo $actorText; ?></span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td data-label="IP">
                    <div class="cell-content">
                      <?php if (!empty($r['ip'])): ?>
                        <span class="ip-chip"><?php echo h($r['ip']); ?></span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
