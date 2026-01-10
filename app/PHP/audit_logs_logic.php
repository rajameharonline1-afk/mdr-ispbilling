<?php
// /public/audit_logs.php লজিক (schema-aware audit log list/export)
// UI: ইংরেজি; মন্তব্য বাংলায়

declare(strict_types=1);

require_once __DIR__ . '/../require_login.php';
require_once __DIR__ . '/../db.php';

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

/* ========== Page title ========== */
$page_title = 'Audit Logs';
