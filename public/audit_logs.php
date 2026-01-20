<?php
// /public/audit_logs.php


// বাংলা: Audit Logs — dynamic schema aware (cached), old/new → details merge, filters + sorting + export

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
include __DIR__ . '/../partials/partials_header.php';

// (অপশনাল) ACL — থাকলে ব্যবহার; না থাকলে নীরবভাবে এগোবে
$acl_file = __DIR__ . '/../acl.php';
if (is_file($acl_file)) require_once $acl_file;

// বাংলা: হেল্পার — HTML-safe
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// বাংলা: PDO
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ========== Small helpers ========== */
// বাংলা: দ্রুত টেবিল এক্সিস্ট চেক (INFORMATION_SCHEMA)
function tbl_exists(PDO $pdo, string $t): bool {
  try{
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $t]);
    return (bool)$st->fetchColumn();
  }catch(Throwable $e){ return false; }
}

//বাংলা: SHOW COLUMNS cache (বারবার কল কমাতে)
function table_columns_cached(PDO $pdo, string $table){
  static $CACHE = [];
  if(isset($CACHE[$table])) return $CACHE[$table];
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table`");
  $st->execute();
  $cols = array_map(fn($r)=>$r['Field'], $st->fetchAll(PDO::FETCH_ASSOC));
  return $CACHE[$table] = array_flip($cols); // flip → isset দ্রুত
}
function col_exists(PDO $pdo, string $table, string $col){
  $cols = table_columns_cached($pdo, $table);
  return isset($cols[$col]);
}
function pick_col(PDO $pdo, string $table, array $cands){
  foreach ($cands as $c) if (col_exists($pdo, $table, $c)) return $c;
  return null;
}
function clip_details_for_export($s){
  // বাংলা: CSV/XLS এ বড় JSON সীমিত রাখি (64KB)
  if (!is_string($s)) return $s;
  $limit = 65536; // 64KB
  return (strlen($s) > $limit) ? (substr($s,0,$limit) . " /* truncated */") : $s;
}
function normalize_audit_details($raw){
  if (!is_string($raw) || $raw === '') return $raw;
  $data = json_decode($raw, true);
  if (json_last_error() !== JSON_ERROR_NONE) return $raw;
  foreach (['new','old','meta','details'] as $k) {
    if (isset($data[$k]) && is_string($data[$k])) {
      $inner = json_decode($data[$k], true);
      if (json_last_error() === JSON_ERROR_NONE) $data[$k] = $inner;
    }
  }
  return $data;
}
// Decode JSON to array (safe)
function audit_details_to_array($details): array {
  if (is_array($details)) return $details;
  if (is_string($details)) {
    $j = json_decode($details, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($j)) return $j;
  }
  return [];
}
// Expand nested JSON strings inside known keys
function audit_expand_nested(array $data): array {
  foreach (['old','new','meta','details'] as $k) {
    if (isset($data[$k]) && is_string($data[$k])) {
      $inner = json_decode($data[$k], true);
      if (json_last_error() === JSON_ERROR_NONE) $data[$k] = $inner;
    }
  }
  return $data;
}
// Extract old/new arrays (fallback to treating payload as new)
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
// Badge color picker (keyword based)
function audit_action_badge_class(string $action): string {
  $a = strtolower(trim($action));
  if ($a === '') return 'secondary';
  $normalized = str_replace(['_','-'], ' ', $a);
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
// Icon picker for timeline/table
function audit_action_icon(string $action): string {
  $a = strtolower($action);
  if (str_contains($a,'sync')) return '🔄';
  if (str_contains($a,'create') || str_contains($a,'add') || str_contains($a,'new')) return '🆕';
  if (str_contains($a,'update') || str_contains($a,'edit') || str_contains($a,'change')) return '📝';
  if (str_contains($a,'delete') || str_contains($a,'remove')) return '🗑️';
  if (str_contains($a,'login') || str_contains($a,'auth')) return '🔑';
  return '📌';
}
// Bengali-friendly summary generator (no raw JSON)
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

/* ========== Inputs ========== */
$action  = trim($_GET['action'] ?? '');
$q       = trim($_GET['q'] ?? '');
$router  = $_GET['router']  ?? '';
$package = $_GET['package'] ?? '';
$area    = trim($_GET['area'] ?? '');
$df      = trim($_GET['df'] ?? '');   // YYYY-MM-DD
$dt      = trim($_GET['dt'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = max(10, min(100, (int)($_GET['limit'] ?? 25)));
$offset  = ($page - 1) * $limit;

$export  = strtolower(trim($_GET['export'] ?? '')); // ''|'csv'|'xls'

/* ========== ACL guard (optional) ========== */
// বাংলা: পারমিশন সিস্টেম থাকলে audit.view গার্ড করো
if (function_exists('require_perm')) {
  require_perm('audit.view');
}

/* ========== Column discovery (cached) ========== */
$AUDIT_TBL = 'audit_logs';
if (!tbl_exists($pdo, $AUDIT_TBL)) {
  http_response_code(500);
  echo "<div style='padding:16px;font-family:sans-serif;color:#b00020;'>Audit table <code>{$AUDIT_TBL}</code> not found.</div>";
  exit;
}

// action/event
$colAction   = pick_col($pdo, $AUDIT_TBL, ['action','event','activity']);
// created timestamp
$colCreated  = pick_col($pdo, $AUDIT_TBL, ['created_at','created','timestamp','logged_at','time','at']);
// entity type/name (আপনার audit.php তে 'entity')
$colEntType  = pick_col($pdo, $AUDIT_TBL, ['entity_type','entity','type','target_type']);
// entity/client id
$colEntityId = pick_col($pdo, $AUDIT_TBL, ['entity_id','client_id','row_id']);
// misc
$colUserId   = pick_col($pdo, $AUDIT_TBL, ['user_id','performed_by','actor_id','uid']);
$colIP       = pick_col($pdo, $AUDIT_TBL, ['ip','ip_address','remote_ip']);
$colUA       = pick_col($pdo, $AUDIT_TBL, ['ua','user_agent']);
$colDetails  = pick_col($pdo, $AUDIT_TBL, ['details','meta','data','payload']); // may not exist
// old/new JSON (আপনার schema)
$colOldJson  = pick_col($pdo, $AUDIT_TBL, ['old_json','old']);
$colNewJson  = pick_col($pdo, $AUDIT_TBL, ['new_json','new']);

/* ---- Expressions (alias-safe) ---- */
$exprAction   = $colAction  ? "a.`$colAction`"  : "NULL";
$exprCreated  = $colCreated ? "a.`$colCreated`" : "NULL";
$exprEntType  = $colEntType ? "a.`$colEntType`" : "NULL";
$exprUserId   = $colUserId  ? "a.`$colUserId`"  : "NULL";
$exprIP       = $colIP      ? "a.`$colIP`"      : "NULL";
$exprUA       = $colUA      ? "a.`$colUA`"      : "NULL";

/* entity id expression (column → JSON → 0) */
$jsonClientFromDetails = $colDetails
  ? "CASE WHEN JSON_VALID(a.`$colDetails`) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(a.`$colDetails`, '$.client_id')) AS UNSIGNED) ELSE NULL END"
  : "NULL";
$jsonClientFromNew = $colNewJson
  ? "CASE WHEN JSON_VALID(a.`$colNewJson`) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(a.`$colNewJson`, '$.client_id')) AS UNSIGNED) ELSE NULL END"
  : "NULL";
if ($colEntityId) {
  $exprEntityId = "COALESCE(NULLIF(a.`$colEntityId`,0), $jsonClientFromDetails, $jsonClientFromNew, 0)";
} else {
  $exprEntityId = "COALESCE($jsonClientFromDetails, $jsonClientFromNew, 0)";
}

/* details expression — portability:
   - যদি details কলাম থাকে → সেটাই
   - নইলে old_json/new_json merge: JSON_MERGE_PRESERVE (MySQL 5.7.22+/8); প্রয়োজনে fallback */
$exprOld = $colOldJson ? "CASE WHEN JSON_VALID(a.`$colOldJson`) THEN a.`$colOldJson` ELSE NULL END" : "NULL";
$exprNew = $colNewJson ? "CASE WHEN JSON_VALID(a.`$colNewJson`) THEN a.`$colNewJson` ELSE NULL END" : "NULL";
if ($colDetails) {
  $exprDetails = "COALESCE(NULLIF(a.`$colDetails`,''), JSON_MERGE_PRESERVE(JSON_OBJECT('old', $exprOld), JSON_OBJECT('new', $exprNew)))";
} else {
  // নোট: MariaDB-তে JSON_MERGE_PRESERVE নেই; দরকার হলে নিচের লাইনটি JSON_MERGE_PATCH বা COALESCE এ নামিয়ে নিন
  $exprDetails = "JSON_MERGE_PRESERVE(JSON_OBJECT('old', $exprOld), JSON_OBJECT('new', $exprNew))";
  // Fallback উদাহরণ (DB পুরোনো হলে): $exprDetails = "COALESCE($exprNew, $exprOld)";
}

/* ======= Optional: users টেবিল থেকে actor নাম জয়েন (schema-aware) ======= */
$USER_TBL_EXISTS = tbl_exists($pdo, 'users');
$colUserName = null;
$exprUserName = "NULL";
if ($USER_TBL_EXISTS && $colUserId) {
  $colUserName = pick_col($pdo, 'users', ['full_name','name','username','email']);
  if ($colUserName) {
    $exprUserName = "u.`$colUserName`";
  }
}

/* ======= Which supporting tables exist? ======= */
$HAS_CLIENTS  = tbl_exists($pdo, 'clients');
$HAS_ROUTERS  = tbl_exists($pdo, 'routers');
$HAS_PACKAGES = tbl_exists($pdo, 'packages');
$colClientCode = $HAS_CLIENTS ? pick_col($pdo, 'clients', ['client_code','code','customer_code','clientid']) : null;

/* ========== SELECT + JOIN (schema-aware) ========== */
$joins = "FROM {$AUDIT_TBL} a ";
$selectPieces = [
  "a.id",
  "$exprCreated  AS created_at",
  "$exprAction   AS action",
  "$exprEntType  AS entity_type",
  "$exprEntityId AS entity_id",
  "$exprUserId   AS user_id",
  "$exprIP       AS ip",
  "$exprUA       AS ua",
  "$exprDetails  AS details"
];

if ($HAS_CLIENTS) {
  $joins .= "LEFT JOIN clients c ON (c.id = $exprEntityId) ";
  $selectPieces[] = "c.name AS client_name";
  $selectPieces[] = "c.pppoe_id";
  $selectPieces[] = "c.area";
  if ($colClientCode) {
    $selectPieces[] = "c.`$colClientCode` AS client_code";
  } else {
    $selectPieces[] = "NULL AS client_code";
  }
  if ($HAS_ROUTERS) {
    $joins .= "LEFT JOIN routers r ON c.router_id = r.id ";
    $selectPieces[] = "r.name AS router_name";
  } else {
    $selectPieces[] = "NULL AS router_name";
  }
  if ($HAS_PACKAGES) {
    $joins .= "LEFT JOIN packages p ON c.package_id = p.id ";
    $selectPieces[] = "p.name AS package_name";
  } else {
    $selectPieces[] = "NULL AS package_name";
  }
} else {
  // বাংলা: clients নাই — সেফ NULL কলাম
  $selectPieces[] = "NULL AS client_name";
  $selectPieces[] = "NULL AS pppoe_id";
  $selectPieces[] = "NULL AS client_code";
  $selectPieces[] = "NULL AS router_name";
  $selectPieces[] = "NULL AS package_name";
  $selectPieces[] = "NULL AS area";
}

// users join
$join_users = ($USER_TBL_EXISTS && $colUserId && $colUserName);
if ($join_users) {
  $joins .= "LEFT JOIN users u ON u.id = a.`$colUserId` ";
  $selectPieces[] = "$exprUserName AS user_name";
}

$sql_base = $joins . " WHERE 1 ";
$selectFields = implode(",\n  ", $selectPieces);

/* ======= JSON search paths (wider coverage) ======= */
// বাংলা: details থাকলে $.pppoe_id এবং $.new.pppoe_id — উভয় পাথ চেষ্টা
$pathsPpp = [];
$pathsNam = [];
if ($colDetails) {
  $pathsPpp[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.pppoe_id'))";
  $pathsPpp[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.new.pppoe_id'))";
  $pathsNam[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.name'))";
  $pathsNam[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.new.name'))";
} else {
  $pathsPpp[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.new.pppoe_id'))";
  $pathsNam[] = "JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.new.name'))";
}

/* ========== Sorting (whitelist) ========== */
$allowed_sort = [
  'id'      => 'a.id',
  'created' => ($colCreated ? "a.`$colCreated`" : 'a.id'),
  'action'  => ($colAction  ? "a.`$colAction`"  : 'a.id'),
  'entity'  => ($colEntType ? "a.`$colEntType`" : 'a.id'),
];
if ($HAS_CLIENTS) $allowed_sort['client'] = 'c.name';
if ($HAS_ROUTERS) $allowed_sort['router'] = 'r.name';

$sort_key = $_GET['sort'] ?? 'created';
$sort_col = $allowed_sort[$sort_key] ?? $allowed_sort['created'];

// বাংলা: ডিফল্ট dir — created/id DESC, টেক্সট ASC
$default_dir = ['created'=>'desc','id'=>'desc','client'=>'asc','router'=>'asc','action'=>'asc','entity'=>'asc'];
$dir_raw = strtolower($_GET['dir'] ?? ($default_dir[$sort_key] ?? 'desc'));
$dir     = ($dir_raw === 'asc') ? 'ASC' : 'DESC';

/* ========== Filters ========== */
$params = [];

// action filter only if action column exists
if ($colAction && $action !== '') { $sql_base .= " AND a.`$colAction` = ? "; $params[] = $action; }

/* search */
if ($q !== '') {
  $like = "%$q%";
  $w = [];
  if ($colEntType) $w[] = "a.`$colEntType` LIKE ?";
  foreach ($pathsPpp as $pp) $w[] = "$pp LIKE ?";
  foreach ($pathsNam as $nm) $w[] = "$nm LIKE ?";
  if ($HAS_CLIENTS) {
    $w[] = "c.name LIKE ?";
    $w[] = "c.pppoe_id LIKE ?";
  }
  if ($join_users) $w[] = "$exprUserName LIKE ?"; // বাংলা: actor name দিয়েও সার্চ

  $sql_base .= " AND (".implode(' OR ', $w).") ";
  if ($colEntType) $params[] = $like;
  for ($i=0, $n=count($pathsPpp)+count($pathsNam); $i<$n; $i++) $params[] = $like;
  if ($HAS_CLIENTS) { $params[] = $like; $params[] = $like; }
  if ($join_users)  { $params[] = $like; }
}

/* router/package/area (client joined হলে কাজ করবে) */
if ($HAS_CLIENTS && $router !== '' && ctype_digit((string)$router)) {
  $sql_base .= " AND c.router_id = ? ";  $params[] = (int)$router;
}
if ($HAS_CLIENTS && $package !== '' && ctype_digit((string)$package) && $HAS_PACKAGES) {
  $sql_base .= " AND c.package_id = ? "; $params[] = (int)$package;
}
if ($HAS_CLIENTS && $area !== '') {
  $sql_base .= " AND c.area LIKE ? ";    $params[] = "%$area%";
}

/* date range (created column থাকলেই) — index-friendly */
$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if ($colCreated && $df && preg_match($re_date, $df)) { $sql_base .= " AND a.`$colCreated` >= ? "; $params[] = $df.' 00:00:00'; }
if ($colCreated && $dt && preg_match($re_date, $dt)) { $sql_base .= " AND a.`$colCreated` <= ? "; $params[] = $dt.' 23:59:59'; }

/* ========== Export (streamed, no LIMIT) ========== */
if ($export === 'csv' || $export === 'xls') {
  $fname = 'audit_logs_'.date('Ymd_His');
  $sqlx = "SELECT $selectFields $sql_base ORDER BY $sort_col $dir, a.id DESC";
  $stx = $pdo->prepare($sqlx);
  $stx->execute($params);

  // বাংলা: স্ট্রিমিংয়ের আগে বাফার/কমপ্রেশন বন্ধ করার চেষ্টা
  if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
  @ini_set('output_buffering','0'); @ini_set('zlib.output_compression','0'); while (ob_get_level()) { @ob_end_flush(); }
  @ob_implicit_flush(1);

  $headers = ['ID','Created','Action','Entity','Entity ID','Client','PPPoE','Router','Package','Area','User ID','User Name','IP','UA','Details'];

  if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    while ($r = $stx->fetch(PDO::FETCH_ASSOC)) {
      fputcsv($out, [
        $r['id'],$r['created_at'],$r['action'],$r['entity_type'],$r['entity_id'],
        $r['client_name'],$r['pppoe_id'],$r['router_name'],$r['package_name'],$r['area'],
        $r['user_id'], ($r['user_name'] ?? ''), $r['ip'],$r['ua'], clip_details_for_export($r['details'])
      ]);
    }
    fclose($out);
    exit;
  } else {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fname.'.xls"');
    echo '<meta charset="utf-8"><table border="1" cellspacing="0" cellpadding="4"><tr>';
    foreach ($headers as $h) echo '<th>'.h($h).'</th>';
    echo '</tr>';
    while ($r = $stx->fetch(PDO::FETCH_ASSOC)) {
      echo '<tr>';
      echo '<td>'.h($r['id']).'</td>';
      echo '<td>'.h($r['created_at']).'</td>';
      echo '<td>'.h($r['action']).'</td>';
      echo '<td>'.h($r['entity_type']).'</td>';
      echo '<td>'.h($r['entity_id']).'</td>';
      echo '<td>'.h($r['client_name']).'</td>';
      echo '<td>'.h($r['pppoe_id']).'</td>';
      echo '<td>'.h($r['router_name']).'</td>';
      echo '<td>'.h($r['package_name']).'</td>';
      echo '<td>'.h($r['area']).'</td>';
      echo '<td>'.h($r['user_id']).'</td>';
      echo '<td>'.h($r['user_name'] ?? '').'</td>';
      echo '<td>'.h($r['ip']).'</td>';
      echo '<td>'.h($r['ua']).'</td>';
      echo '<td>'.h(clip_details_for_export($r['details'])).'</td>';
      echo '</tr>';
    }
    echo '</table>';
    exit;
  }
}

/* ========== Count + Fetch (paged) ========== */
// বাংলা: COUNT(*) — ভারি হলে future-এ অপ্টিমাইজ করা যাবে
$stc = $pdo->prepare("SELECT COUNT(*) $sql_base");
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

$sql = "SELECT $selectFields $sql_base ORDER BY $sort_col $dir, a.id DESC LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ========== Dropdown data (safe if tables exist) ========== */
$actions = ($colAction
  ? $pdo->query("SELECT DISTINCT `$colAction` AS a FROM {$AUDIT_TBL} ORDER BY a ASC")->fetchAll(PDO::FETCH_COLUMN)
  : []);
$rtrs    = $HAS_ROUTERS  ? $pdo->query("SELECT id,name FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$pkgs    = $HAS_PACKAGES ? $pdo->query("SELECT id,name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$areas   = $HAS_CLIENTS  ? $pdo->query("SELECT DISTINCT area FROM clients WHERE area IS NOT NULL AND area<>'' ORDER BY area ASC")->fetchAll(PDO::FETCH_COLUMN) : [];

/* ========== Helper: sort link ========== */
function sort_link($key,$label,$cur,$dir_raw){
  $qs = $_GET;
  $qs['sort'] = $key;
  $qs['dir']  = ($cur===$key && strtolower($dir_raw)==='asc') ? 'desc' : 'asc';
  $qs['page'] = 1;
  $icon = ' <i class="bi bi-arrow-down-up"></i>';
  if ($cur === $key) $icon = (strtolower($dir_raw)==='asc') ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
  return '<a class="text-decoration-none" href="?'.http_build_query($qs).'">'.$label.$icon.'</a>';
}

// Helper: split summary into lead/rest for toggle
function split_summary_text(string $text): array {
  $t = trim($text);
  if ($t === '') return ['', ''];
  $len = mb_strlen($t);
  if ($len <= 80) return [$t, ''];

  $seps = ['। ', '।', '.', '!', '?'];
  $pos = null;
  foreach ($seps as $s) {
    $p = mb_strpos($t, $s);
    if ($p !== false && $p > 10) { $pos = $p + mb_strlen($s); break; }
  }
  if ($pos === null || $pos >= $len - 8) {
    $pos = 90;
  }
  $lead = trim(mb_substr($t, 0, $pos));
  $rest = trim(mb_substr($t, $pos));
  return [$lead, $rest];
}

/* ========== Page title ========== */
$page_title = 'Audit Logs';
$customCssVer = @filemtime(__DIR__ . '/../assets/css/custom_modern.css') ?: time();


?>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/assets/css/custom_modern.css?v=<?= $customCssVer ?>">
<style>
  .audit-table-card { border: none; border-radius: 14px; }
  .audit-logs-table {
    font-size: 0.84rem;
    margin-bottom: 0;
    table-layout: fixed;
  }
  .audit-logs-table thead { position: sticky; top: 0; z-index: 5; }
  .audit-logs-table thead th {
    background: linear-gradient(90deg, #1f2a44 0%, #243b55 100%);
    color: #e2e8f0;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 8px 10px;
  }
  .audit-logs-table th:nth-child(1) { width: 190px; }
  .audit-logs-table th:nth-child(2) { width: 170px; }
  .audit-logs-table th:nth-child(3) { width: 420px; }
  .audit-logs-table th:nth-child(4) { width: 170px; }
  .audit-logs-table th:nth-child(5) { width: 170px; }
  .audit-logs-table th:nth-child(6) { width: 140px; }
  .audit-logs-table td { padding: 7px 10px; vertical-align: top; }
  .audit-logs-table td:not([data-label="Summary"]) { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .audit-row { transition: background-color .18s ease, box-shadow .18s ease, transform .12s ease; }
  .audit-row:hover { background: #f7faff; box-shadow: inset 0 1px 0 rgba(0,0,0,0.02); transform: translateY(-1px); }
  .log-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
  }
  .log-text { line-height: 1.26; font-size: 0.82rem; }
  .log-text b { color: #0f172a; }
  .log-chips .badge { font-weight: 600; letter-spacing: 0.1px; font-size: 10px; }
  .audit-logs-table .badge { padding: 0.28rem 0.55rem; }
  .summary-wrap { word-break: break-word; }
  .summary-lead { display: inline; }
  .summary-rest { display: none; }
  .summary-ellipsis { display: inline; color: #94a3b8; }
  .summary-wrap.expanded .summary-rest { display: inline; }
  .summary-wrap.expanded .summary-ellipsis { display: none; }
  .summary-toggle {
    background: transparent;
    border: none;
    color: #1d4ed8;
    font-size: 10px;
    font-weight: 700;
    padding: 0;
    margin-left: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
  }
  .ip-chip {
    background: #0f172a;
    color: #e2e8f0;
    padding: 4px 8px;
    border-radius: 8px;
    font-family: "SFMono-Regular", Menlo, monospace;
    font-size: 12px;
    letter-spacing: 0.2px;
    display: inline-block;
  }
  .text-trunc-ua { max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  @media (max-width: 992px) {
    .audit-logs-table thead th { padding: 8px 9px; font-size: 11px; }
    .audit-logs-table td { padding: 8px 9px; }
  }
  @media (max-width: 768px) {
    .audit-logs-table thead { display: none; }
    .audit-logs-table,
    .audit-logs-table tbody,
    .audit-logs-table tr,
    .audit-logs-table td { display: block; width: 100%; }
    .audit-logs-table tbody tr {
      margin-bottom: 14px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 10px 12px;
      box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    }
    .audit-logs-table td {
      border: none !important;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px dashed #e2e8f0 !important;
    }
    .audit-logs-table td:last-child { border-bottom: none !important; }
    .audit-logs-table td::before {
      content: attr(data-label);
      font-weight: 700;
      text-transform: uppercase;
      color: #475569;
      letter-spacing: 0.4px;
      font-size: 12px;
      flex: 1 1 45%;
    }
    .audit-logs-table .cell-content { flex: 1 1 55%; text-align: right; }
    .log-icon { width: 36px; height: 36px; font-size: 1rem; }
    .text-trunc-ua { max-width: none; white-space: normal; text-align: right; }
  }
</style>

<div class="container-fluid py-3">
  <?php
    // (বাংলা) ক্লায়েন্ট ভিউতে ফেরার জন্য ব্যাক বাটন; client_id পেলে কুয়েরিতে যোগ করি
    $backUrl = '/public/client_view.php';
    if (!empty($_GET['client_id']) && ctype_digit((string)$_GET['client_id'])) {
      $backUrl .= '?id='.(int)$_GET['client_id'];
    }
  ?>
  <div class="d-flex justify-content-end mb-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($backUrl) ?>">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <!-- Filters -->
  <form class="card shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2 align-items-end">

        <!-- Keep sort/dir -->
        <input type="hidden" name="sort" value="<?= h($sort_key) ?>">
        <input type="hidden" name="dir"  value="<?= h($dir_raw) ?>">

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Search</label>
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Client / PPPoE / Action / Actor / Area">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Action</label>
          <select name="action" class="form-select form-select-sm" <?= $colAction ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($actions as $a): ?>
              <option value="<?= h($a) ?>" <?= $action===$a?'selected':'' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!$colAction): ?><div class="form-text small text-danger">Action column not found</div><?php endif; ?>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Router</label>
          <select name="router" class="form-select form-select-sm" <?= $HAS_CLIENTS && $HAS_ROUTERS ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($rtrs as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($router!=='' && (int)$router===(int)$r['id'])?'selected':'' ?>>
                <?= h($r['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Package</label>
          <select name="package" class="form-select form-select-sm" <?= $HAS_CLIENTS && $HAS_PACKAGES ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($pkgs as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ($package!=='' && (int)$package===(int)$p['id'])?'selected':'' ?>>
                <?= h($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Area</label>
          <input name="area" value="<?= h($area) ?>" list="areas" class="form-control form-control-sm" placeholder="Area" <?= $HAS_CLIENTS ? '' : 'disabled' ?>>
          <datalist id="areas">
            <?php foreach($areas as $ar): ?><option value="<?= h($ar) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Date From</label>
          <input type="date" name="df" value="<?= h($df) ?>" class="form-control form-control-sm" <?= $colCreated ? '' : 'disabled' ?>>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Date To</label>
          <input type="date" name="dt" value="<?= h($dt) ?>" class="form-control form-control-sm" <?= $colCreated ? '' : 'disabled' ?>>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Per Page</label>
          <select name="limit" class="form-select form-select-sm">
            <?php foreach([10,25,50,100] as $L): ?>
              <option value="<?= $L ?>" <?= $limit==$L?'selected':'' ?>><?= $L ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        </div>

        <!-- Export -->
        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-success btn-sm" name="export" value="csv" formtarget="_blank">
            <i class="bi bi-filetype-csv"></i> Export CSV
          </button>
        </div>
        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-primary btn-sm" name="export" value="xls" formtarget="_blank">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
          </button>
        </div>

      </div>
    </div>
  </form>

  <!-- Table -->
  <div class="card shadow-sm audit-table-card">
    <div class="table-responsive">
      <table class="table table-sm align-middle audit-logs-table">
        <thead>
          <tr>
            <th><?= sort_link('created','Time', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('action','Action', $sort_key, $dir_raw) ?></th>
            <th>Summary</th>
            <th>Entity</th>
            <th>Actor</th>
            <th>IP</th>
          </tr>
        </thead>
    <tbody>
    <?php if($rows): foreach($rows as $r):
      $message = render_log_message($r['action'] ?? '', $r['details'] ?? '');
      $entityIdForText = isset($r['entity_id']) && $r['entity_id'] !== null ? (int)$r['entity_id'] : null;
      $clientCodeForText = isset($r['client_code']) ? trim((string)$r['client_code']) : '';
      if ($clientCodeForText !== '' && $entityIdForText) {
        $message = str_replace('#'.$entityIdForText, '#'.$clientCodeForText, $message);
      }
      $badge   = audit_action_badge_class($r['action'] ?? '');
      $icon    = audit_action_icon($r['action'] ?? '');
      $messagePlain = trim(strip_tags($message));
      [$summaryLead, $summaryRest] = split_summary_text($messagePlain);
    ?>
      <tr class="audit-row">
        <td class="mono fw-semibold" data-label="Time">
          <div class="cell-content">
            <div class="fw-semibold"><?= h($r['created_at'] ?: '-') ?></div>
            <div class="text-muted small">#<?= (int)$r['id'] ?></div>
          </div>
        </td>
        <td data-label="Action">
          <div class="cell-content">
            <span class="badge bg-<?= h($badge) ?> text-uppercase shadow-sm px-3">
              <?= h($r['action'] ?: '-') ?>
            </span>
            <div class="text-muted small mt-1">
              📌 activity
            </div>
          </div>
        </td>
        <td data-label="Summary" style="min-width:260px;">
          <div class="cell-content">
            <div class="d-flex align-items-start gap-3">
              <div class="log-icon bg-<?= h($badge) ?> bg-opacity-10 text-<?= h($badge) ?> border border-<?= h($badge) ?>">
                <?= h($icon) ?>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex align-items-start">
                  <div class="fw-semibold log-text mb-1 summary-wrap <?= $summaryRest ? 'summary-collapsed' : '' ?>" id="summary-<?= (int)$r['id'] ?>" title="<?= h($messagePlain) ?>" aria-label="<?= h($messagePlain) ?>">
                    <span class="summary-lead"><?= h($summaryLead) ?></span>
                    <?php if ($summaryRest): ?>
                      <span class="summary-ellipsis">…</span>
                      <span class="summary-rest"> <?= h($summaryRest) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($summaryRest): ?>
                    <button type="button" class="summary-toggle" data-target="summary-<?= (int)$r['id'] ?>">More</button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </td>
        <td data-label="Entity">
          <div class="cell-content">
            <?php
              $entityLabel = '—';
              if (!empty($r['entity_type']) || !empty($r['entity_id'])) {
                $code = trim((string)($r['client_code'] ?? ''));
                if ($code !== '') {
                  $entityLabel = 'client#'.h($code);
                } else {
                  $entityLabel = h(($r['entity_type'] ?? 'entity')) . (isset($r['entity_id']) && $r['entity_id'] !== null && $r['entity_id'] !== '' ? '#'.(int)$r['entity_id'] : '');
                }
              }
            ?>
            <span class="badge rounded-pill bg-light text-dark border"><?= $entityLabel ?></span>
          </div>
        </td>
        <td class="small" data-label="Actor">
          <div class="cell-content">
            <?php
              $actor = trim(($r['user_name'] ?? ''));
              $uidVal = $r['user_id'] ?? null;
              $uid = is_numeric($uidVal) ? (int)$uidVal : null;
              if ($uid === 0) {
                echo '<span class="badge rounded-pill bg-light text-dark">System automatic</span>';
              } elseif ($actor !== '' && $uid) {
                echo '<span class="badge rounded-pill bg-light text-dark">'.h($actor).' (#'.h((string)$uid).')</span>';
              } elseif ($uid) {
                echo '<span class="badge rounded-pill bg-light text-dark">#'.h((string)$uid).'</span>';
              } else {
                echo '<span class="text-muted">-</span>';
              }
            ?>
          </div>
        </td>
        <td data-label="IP">
          <div class="cell-content">
            <?php if (!empty($r['ip'])): ?>
              <div class="ip-chip mb-1"><?= h($r['ip'] ?? '') ?></div>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No logs found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
<?php if($total_pages>1):
  $qsPrev = $_GET; $qsPrev['page'] = max(1,$page-1);
  $qsNext = $_GET; $qsNext['page'] = min($total_pages,$page+1);
  $start = max(1,$page-2); $end = min($total_pages,$page+2);
  if(($end-$start)<4){ $end=min($total_pages,$start+4); $start=max(1,$end-4); }
?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query($qsPrev) ?>">Previous</a>
        </li>
        <?php for($i=$start;$i<=$end;$i++): $qsi=$_GET; $qsi['page']=$i; ?>
          <li class="page-item <?= $i==$page?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query($qsi) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query($qsNext) ?>">Next</a>
        </li>
      </ul>
    </nav>
<?php endif; ?>

</div>

<script>
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.summary-toggle');
    if(!btn) return;
    const id = btn.getAttribute('data-target');
    if(!id) return;
    const el = document.getElementById(id);
    if(!el) return;
    const isOpen = el.classList.toggle('expanded');
    btn.textContent = isOpen ? 'Less' : 'More';
    if (isOpen) { el.classList.remove('summary-collapsed'); } else { el.classList.add('summary-collapsed'); }
  });
</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
