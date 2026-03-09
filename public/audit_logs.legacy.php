<?php
// cSpell:disable
// বাংলা: /public/audit_logs.php — অডিট লগ লিস্ট; ডায়নামিক স্কিমা-সচেতন, পুরোনো/নতুন JSON মার্জ,
// ফিল্টার + সর্ট + স্ট্রিমিং CSV/XLS এক্সপোর্ট + সুন্দর JSON (ট্রাঙ্কেট) + ACL গার্ড + actor নাম জয়েন

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

// বাংলা: (ঐচ্ছিক) ACL — ফাইল থাকলে লোড; নাহলে নীরবভাবে এগিয়ে যাবে
$acl_file = __DIR__ . '/../app/acl.php';
if (is_file($acl_file)) require_once $acl_file;

// বাংলা: হেল্পার — HTML-safe আউটপুট
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// বাংলা: PDO কানেকশন + এরর মোড
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ========== ছোট হেল্পার ========== */
// বাংলা: দ্রুত টেবিল এক্সিস্ট চেক (INFORMATION_SCHEMA)
function tbl_exists(PDO $pdo, string $t): bool {
  try{
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $st->execute([$db, $t]);
    return (bool)$st->fetchColumn();
  }catch(Throwable $e){ return false; }
}

// বাংলা: SHOW COLUMNS ক্যাশ (বারবার কল কমাতে)
function table_columns_cached(PDO $pdo, string $table){
  static $CACHE = [];
  if(isset($CACHE[$table])) return $CACHE[$table];
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table`");
  $st->execute();
  $cols = array_map(fn($r)=>$r['Field'], $st->fetchAll(PDO::FETCH_ASSOC));
  return $CACHE[$table] = array_flip($cols); // বাংলা: array_flip করে isset দ্রুত
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

// বাংলা: UI-তে দেখানোর জন্য JSON সুন্দর/ট্রাঙ্কেটেড টেক্সটে রূপান্তর
function pretty_details($val): string{
  if ($val === null || $val === '') return '';
  $raw = (string)$val;
  if (strlen($raw) > 65536) {
    $raw = substr($raw, 0, 65536) . "\n/* truncated */";
  }
  $decoded = json_decode($val, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }
  return $raw;
}

// বাংলা: ছোট সারাংশ বানাতে সাধারণ টেক্সট ক্লিপ
function clip_line(string $txt, int $limit = 220): string{
  $txt = trim(preg_replace('/\s+/', ' ', $txt));
  if ($txt === '') return '';
  if (function_exists('mb_strimwidth')) {
    return mb_strimwidth($txt, 0, $limit, '…', 'UTF-8');
  }
  return (strlen($txt) > $limit) ? (substr($txt, 0, $limit-1) . '…') : $txt;
}

// বাংলা: মানকে ছোট আকারে দেখাও (bool/null/json হ্যান্ডেল)
function tiny_val($v, int $limit = 120): string{
  if ($v === null) return 'null';
  if (is_bool($v)) return $v ? 'true' : 'false';
  if (is_numeric($v)) return (string)$v;
  $txt = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
  $txt = trim(preg_replace('/\s+/', ' ', (string)$txt));
  return (strlen($txt) > $limit) ? (substr($txt, 0, $limit-1) . '…') : $txt;
}

// বাংলা: details JSON এ যদি old/new থাকে তাহলে সারাংশ তৈরি
function summarize_old_new($val, int $limit = 220): array{
  $decoded = json_decode((string)$val, true);
  if (!is_array($decoded)) return ['', false];
  $old = is_array($decoded['old'] ?? null) ? $decoded['old'] : [];
  $new = is_array($decoded['new'] ?? null) ? $decoded['new'] : [];
  if (!$old && !$new) return ['', false];

  $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
  $parts = [];
  foreach ($keys as $k) {
    $ov = array_key_exists($k, $old) ? $old[$k] : null;
    $nv = array_key_exists($k, $new) ? $new[$k] : null;
    $parts[] = "{$k}: ".tiny_val($ov, 70)." → ".tiny_val($nv, 70);
  }
  $line = clip_line(implode(' | ', $parts), $limit);
  return [$line, true];
}

// বাংলা: বড় PRE ব্লকের প্রিভিউ (সম্পূর্ণ ডাটা মডালে দেখানো হবে)
function preview_block(string $txt, int $limit = 1400): string{
  if (strlen($txt) <= $limit) return $txt;
  return substr($txt, 0, $limit) . "\n/* trimmed: নিচে পূর্ণ ডিটেইলস দেখতে আরও চাপুন */";
}

// বাংলা: মানব-পঠিত মান (ছোট)
function kv_text($v, int $limit = 160): string{
  if ($v === null) return '—';
  if (is_bool($v)) return $v ? 'true' : 'false';
  if (is_numeric($v)) return (string)$v;
  if (is_string($v)) {
    $v = trim($v);
    return strlen($v) > $limit ? substr($v, 0, $limit-1).'…' : $v;
  }
  $enc = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  return strlen($enc) > $limit ? substr($enc,0,$limit-1).'…' : $enc;
}

// বাংলা: ডিটেইলসকে old/new টেবিল বানানোর জন্য প্রস্তুত করা
function prepare_detail_rows($raw): array{
  $decoded = json_decode((string)$raw, true);
  if (!is_array($decoded)) return [[], false, false];

  $hasOldNew = isset($decoded['old']) || isset($decoded['new']);
  $old = (is_array($decoded['old'] ?? null)) ? $decoded['old'] : [];
  $new = (is_array($decoded['new'] ?? null)) ? $decoded['new'] : [];
  // বাংলা: যদি old/new না থাকে, পুরো অবজেক্টকে নতুন হিসেবে দেখাই
  if (!$hasOldNew) {
    $new = $decoded;
  }

  $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
  sort($keys);
  $rows = [];
  foreach ($keys as $k) {
    $ov = array_key_exists($k,$old) ? $old[$k] : null;
    $nv = array_key_exists($k,$new) ? $new[$k] : null;
    $rows[] = [
      'key'     => $k,
      'old_txt' => kv_text($ov),
      'new_txt' => kv_text($nv),
      'changed' => $ov !== $nv,
    ];
  }
  return [$rows, $hasOldNew, true];
}

/* ========== ইনপুট সংগ্রহ ========== */
$action  = trim($_GET['action'] ?? '');
$q       = trim($_GET['q'] ?? '');
$client_id = max(0, (int)($_GET['client_id'] ?? 0));
$client_code = trim((string)($_GET['client_code'] ?? ''));
$router  = $_GET['router']  ?? '';
$package = $_GET['package'] ?? '';
$area    = trim($_GET['area'] ?? '');
$df      = trim($_GET['df'] ?? '');   // বাংলা: YYYY-MM-DD
$dt      = trim($_GET['dt'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = max(10, min(100, (int)($_GET['limit'] ?? 25)));
$offset  = ($page - 1) * $limit;

$export  = strtolower(trim($_GET['export'] ?? '')); // বাংলা: '' / 'csv' / 'xls'

/* ========== ACL গার্ড (ঐচ্ছিক) ========== */
// বাংলা: পারমিশন সিস্টেম থাকলে audit.view চেক করো
if (function_exists('require_perm')) {
  require_perm('view.audit.log');
}

/* ========== কলাম ডিটেকশন (ক্যাশড) ========== */
$AUDIT_TBL = 'audit_logs';
if (!tbl_exists($pdo, $AUDIT_TBL)) {
  http_response_code(500);
  echo "<div style='padding:16px;font-family:sans-serif;color:#b00020;'>Audit table <code>{$AUDIT_TBL}</code> not found.</div>";
  exit;
}

// বাংলা: action/event সম্ভাব্য কলাম
$colAction   = pick_col($pdo, $AUDIT_TBL, ['action','event','activity','activity_type','type']);
// বাংলা: তৈরি তারিখ/টাইমস্ট্যাম্প
$colCreated  = pick_col($pdo, $AUDIT_TBL, ['created_at','created','timestamp','logged_at','time','at','created_on','date','datetime']);
// বাংলা: entity টাইপ/নাম (audit.php এ 'entity')
$colEntType  = pick_col($pdo, $AUDIT_TBL, ['entity_type','entity','type','target_type','module','scope']);
// বাংলা: entity/client আইডি
$colEntityId = pick_col($pdo, $AUDIT_TBL, ['entity_id','client_id','row_id','ref_id','target_id','subject_id']);
// বাংলা: actor/user/আইডি/আইপি/UA সম্পর্কিত
$colUserId   = pick_col($pdo, $AUDIT_TBL, ['user_id','performed_by','actor_id','uid']);
$colIP       = pick_col($pdo, $AUDIT_TBL, ['ip','ip_address','remote_ip','client_ip','ipv4','ipv6']);
$colUA       = pick_col($pdo, $AUDIT_TBL, ['ua','user_agent','agent','ua_string']);
$colDetails  = pick_col($pdo, $AUDIT_TBL, [
  'details','meta','data','payload','note','notes','description','remark','remarks','message'
]); // বাংলা: নাও থাকতে পারে
// বাংলা: পুরোনো/নতুন JSON (স্কিমা ভেদে)
$colOldJson  = pick_col($pdo, $AUDIT_TBL, ['old_json','old']);
$colNewJson  = pick_col($pdo, $AUDIT_TBL, ['new_json','new']);

/* ---- বাংলা: এক্সপ্রেশন (অ্যালিয়াস-সেফ) ---- */
$exprAction   = $colAction  ? "a.`$colAction`"  : "NULL";
$exprCreated  = $colCreated ? "a.`$colCreated`" : "NULL";
$exprEntType  = $colEntType ? "a.`$colEntType`" : "NULL";
$exprUserId   = $colUserId  ? "a.`$colUserId`"  : "NULL";
$exprIP       = $colIP      ? "a.`$colIP`"      : "NULL";
$exprUA       = $colUA      ? "a.`$colUA`"      : "NULL";

/* বাংলা: entity id এক্সপ্রেশন (কলাম → JSON → না থাকলে 0) */
if ($colEntityId) {
  $exprEntityId = "a.`$colEntityId`";
} elseif ($colDetails) {
  $exprEntityId = "CAST(JSON_UNQUOTE(JSON_EXTRACT(a.`$colDetails`, '$.client_id')) AS UNSIGNED)";
} elseif ($colNewJson) {
  $exprEntityId = "CAST(JSON_UNQUOTE(JSON_EXTRACT(a.`$colNewJson`, '$.client_id')) AS UNSIGNED)";
} else {
  $exprEntityId = "0";
}

/* বাংলা: details এক্সপ্রেশন — সামঞ্জস্যপূর্ণ রাখতে:
   - যদি details কলাম থাকে → সেটাই
   - নইলে old_json/new_json মার্জ: JSON_MERGE_PRESERVE (MySQL 5.7.22+/8); প্রয়োজনে বিকল্প ব্যবহার */
$exprOld = $colOldJson ? "CASE WHEN JSON_VALID(a.`$colOldJson`) THEN a.`$colOldJson` ELSE NULL END" : "NULL";
$exprNew = $colNewJson ? "CASE WHEN JSON_VALID(a.`$colNewJson`) THEN a.`$colNewJson` ELSE NULL END" : "NULL";
// বাংলা: details/summary/meta → যেটা আছে + ফাঁকা না, নইলে old/new মার্জ
$colMeta    = ($colDetails !== 'meta'    && col_exists($pdo, $AUDIT_TBL, 'meta'))    ? 'meta'    : null;
$colSummary = ($colDetails !== 'summary' && col_exists($pdo, $AUDIT_TBL, 'summary')) ? 'summary' : null;
$exprOldNewMerge = "JSON_MERGE_PRESERVE(JSON_OBJECT('old', $exprOld), JSON_OBJECT('new', $exprNew))";
$exprOldNewSafe  = "IFNULL($exprOldNewMerge, COALESCE($exprNew, $exprOld))"; // বাংলা: MariaDB পুরোনো হলে null হলে fallback
$detailPieces = [];
if ($colDetails)  $detailPieces[] = "NULLIF(TRIM(a.`$colDetails`),'')";
if ($colSummary)  $detailPieces[] = "NULLIF(TRIM(a.`$colSummary`),'')";
if ($colMeta)     $detailPieces[] = "NULLIF(TRIM(a.`$colMeta`),'')";
$exprDetails = $detailPieces ? ("COALESCE(" . implode(", ", $detailPieces) . ", $exprOldNewSafe)") : $exprOldNewSafe;

/* বাংলা: client_id এক্সপ্রেশন (join/filter এর জন্য — entity_id টিকেট হলে JSON থেকে client_id নেয়) */
$clientIdExprs = [];
if ($colEntType) {
  $clientIdExprs[] = "CASE WHEN a.`$colEntType` IN ('client','clients','customer','customers') THEN $exprEntityId END";
}
$clientIdExprs[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.client_id')) AS UNSIGNED)";
$clientIdExprs[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.new.client_id')) AS UNSIGNED)";
$clientIdExprs[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT($exprDetails,'$.old.client_id')) AS UNSIGNED)";
$clientIdExprs = array_filter($clientIdExprs);
$exprClientId = $clientIdExprs ? ("COALESCE(" . implode(", ", $clientIdExprs) . ", $exprEntityId)") : $exprEntityId;

/* ======= users টেবিল থেকে actor নাম জয়েন (স্কিমা-সচেতন) ======= */
$USER_TBL_EXISTS = tbl_exists($pdo, 'users');
$colUserName = null;
$exprUserName = "NULL";
if ($USER_TBL_EXISTS && $colUserId) {
  $colUserName = pick_col($pdo, 'users', ['full_name','name','username','email']);
  if ($colUserName) {
    $exprUserName = "u.`$colUserName`";
  }
}

/* ======= কোন সাপোর্টিং টেবিল আছে? ======= */
$HAS_CLIENTS    = tbl_exists($pdo, 'clients');
$HAS_ROUTERS    = tbl_exists($pdo, 'routers');
$HAS_PACKAGES   = tbl_exists($pdo, 'packages');

/* ========== SELECT + JOIN (স্কিমা-সচেতন) ========== */
$joins = "FROM {$AUDIT_TBL} a ";
$selectPieces = [
  "a.id",
  "$exprCreated  AS created_at",
  "$exprAction   AS action",
  "$exprEntType  AS entity_type",
  "$exprEntityId AS entity_id",
  "$exprClientId AS client_id_resolved",
  "$exprUserId   AS user_id",
  "$exprIP       AS ip",
  "$exprUA       AS ua",
  "$exprDetails  AS details"
];

if ($HAS_CLIENTS) {
  $joins .= "LEFT JOIN clients c ON (c.id = $exprClientId) ";
  $selectPieces[] = "c.name AS client_name";
  $selectPieces[] = "c.pppoe_id";
  $selectPieces[] = "c.area";
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
  $selectPieces[] = "NULL AS package_name";
  $selectPieces[] = "NULL AS area";
}

// বাংলা: router নাম দুইভাবে ধরতে চেষ্টা (client → router_id বা entity_id সরাসরি)
if ($HAS_ROUTERS) {
  if ($HAS_CLIENTS) {
    $joins .= "LEFT JOIN routers r ON c.router_id = r.id ";
  }
  $joins .= "LEFT JOIN routers r_ent ON r_ent.id = $exprEntityId ";
  $selectPieces[] = $HAS_CLIENTS ? "COALESCE(r.name, r_ent.name) AS router_name" : "r_ent.name AS router_name";
} else {
  $selectPieces[] = "NULL AS router_name";
}

// বাংলা: users জয়েন
$join_users = ($USER_TBL_EXISTS && $colUserId && $colUserName);
if ($join_users) {
  $joins .= "LEFT JOIN users u ON u.id = a.`$colUserId` ";
  $selectPieces[] = "$exprUserName AS user_name";
}

$sql_base = $joins . " WHERE 1 ";
$selectFields = implode(",\n  ", $selectPieces);

/* ======= JSON সার্চ পাথ (বিস্তৃত কাভারেজ) ======= */
// বাংলা: details থাকলে $.pppoe_id এবং $.new.pppoe_id — দুটোই
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

/* ========== সর্টিং (হোয়াইটলিস্ট) ========== */
$allowed_sort = [
  'id'      => 'a.id',
  'created' => ($colCreated ? "a.`$colCreated`" : 'a.id'),
  'action'  => ($colAction  ? "a.`$colAction`"  : 'a.id'),
  'entity'  => ($colEntType ? "a.`$colEntType`" : 'a.id'),
];
if ($HAS_CLIENTS) $allowed_sort['client'] = 'c.name';
if ($HAS_ROUTERS) $allowed_sort['router'] = ($HAS_CLIENTS ? 'COALESCE(r.name, r_ent.name)' : 'r_ent.name');

$sort_key = $_GET['sort'] ?? 'created';
$sort_col = $allowed_sort[$sort_key] ?? $allowed_sort['created'];

// বাংলা: ডিফল্ট dir — created/id DESC, টেক্সট ASC
$default_dir = ['created'=>'desc','id'=>'desc','client'=>'asc','router'=>'asc','action'=>'asc','entity'=>'asc'];
$dir_raw = strtolower($_GET['dir'] ?? ($default_dir[$sort_key] ?? 'desc'));
$dir     = ($dir_raw === 'asc') ? 'ASC' : 'DESC';

/* ========== ফিল্টার ========== */
$params = [];

// বাংলা: নির্দিষ্ট ক্লায়েন্ট (আইডি/কোড) ফিল্টার
if ($client_code !== '' && $client_id === 0 && $HAS_CLIENTS) {
  try {
    $st_c = $pdo->prepare("SELECT id FROM clients WHERE client_code=? OR pppoe_id=? LIMIT 1");
    $st_c->execute([$client_code, $client_code]);
    $client_id = (int)$st_c->fetchColumn();
  } catch (Throwable $e) { $client_id = 0; }
}

// বাংলা: action কলাম থাকলেই ফিল্টার করো
if ($colAction && $action !== '') { $sql_base .= " AND a.`$colAction` = ? "; $params[] = $action; }

if ($client_id > 0) {
  $sql_base .= " AND ( $exprClientId = ? OR $exprEntityId = ? OR JSON_EXTRACT($exprDetails,'$.client_id') = ? OR JSON_EXTRACT($exprDetails,'$.new.client_id') = ? OR JSON_EXTRACT($exprDetails,'$.old.client_id') = ? ) ";
  $params[] = $client_id; // resolved client_id
  $params[] = $client_id; // raw entity_id (when entity=client)
  $params[] = $client_id;
  $params[] = $client_id;
  $params[] = $client_id;
}

/* বাংলা: সার্চ (q) */
if ($q !== '') {
  $like = "%$q%";
  $w = [];
  if ($colEntType) $w[] = "a.`$colEntType` LIKE ?";
  if ($colDetails)  $w[] = "a.`$colDetails` LIKE ?";
  foreach ($pathsPpp as $pp) $w[] = "$pp LIKE ?";
  foreach ($pathsNam as $nm) $w[] = "$nm LIKE ?";
  if ($HAS_CLIENTS) {
    $w[] = "c.name LIKE ?";
    $w[] = "c.pppoe_id LIKE ?";
  }
  if ($join_users) $w[] = "$exprUserName LIKE ?"; // বাংলা: actor name দিয়েও সার্চ

  $sql_base .= " AND (".implode(' OR ', $w).") ";
  if ($colEntType) $params[] = $like;
  if ($colDetails)  $params[] = $like;
  for ($i=0, $n=count($pathsPpp)+count($pathsNam); $i<$n; $i++) $params[] = $like;
  if ($HAS_CLIENTS) { $params[] = $like; $params[] = $like; }
  if ($join_users)  { $params[] = $like; }
}

/* বাংলা: router/package/area — ক্লায়েন্ট জয়েন থাকলেই */
if ($HAS_CLIENTS && $router !== '' && ctype_digit((string)$router)) {
  $sql_base .= " AND c.router_id = ? ";  $params[] = (int)$router;
}
if ($HAS_CLIENTS && $package !== '' && ctype_digit((string)$package) && $HAS_PACKAGES) {
  $sql_base .= " AND c.package_id = ? "; $params[] = (int)$package;
}
if ($HAS_CLIENTS && $area !== '') {
  $sql_base .= " AND c.area LIKE ? ";    $params[] = "%$area%";
}

/* বাংলা: date range (created কলাম থাকলে), ইনডেক্স-বন্ধুত্বপূর্ণ */
$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if ($colCreated && $df && preg_match($re_date, $df)) { $sql_base .= " AND a.`$colCreated` >= ? "; $params[] = $df.' 00:00:00'; }
if ($colCreated && $dt && preg_match($re_date, $dt)) { $sql_base .= " AND a.`$colCreated` <= ? "; $params[] = $dt.' 23:59:59'; }

/* ========== এক্সপোর্ট (স্ট্রিম, LIMIT নেই) ========== */
if ($export === 'csv' || $export === 'xls') {
  $fname = 'audit_logs_'.date('Ymd_His');
  $sqlx = "SELECT $selectFields $sql_base ORDER BY $sort_col $dir, a.id DESC";
  $stx = $pdo->prepare($sqlx);
  $stx->execute($params);

  // বাংলা: স্ট্রিমিংয়ের আগে বাফার/কমপ্রেশন বন্ধ করার চেষ্টা
  if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
  @ini_set('output_buffering','0'); @ini_set('zlib.output_compression','0'); while (ob_get_level()) { @ob_end_flush(); }
  @ob_implicit_flush(true);

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

/* ========== কাউন্ট + ফেচ (পৃষ্ঠা ভিত্তিক) ========== */
// বাংলা: COUNT(*) — ভারি হলে ভবিষ্যতে অপ্টিমাইজ করা যাবে
$stc = $pdo->prepare("SELECT COUNT(*) $sql_base");
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

$sql = "SELECT $selectFields $sql_base ORDER BY $sort_col $dir, a.id DESC LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ========== ড্রপডাউন ডাটা (টেবিল থাকলে সেফ) ========== */
$actions = ($colAction
  ? $pdo->query("SELECT DISTINCT `$colAction` AS a FROM {$AUDIT_TBL} ORDER BY a ASC")->fetchAll(PDO::FETCH_COLUMN)
  : []);
$rtrs    = $HAS_ROUTERS  ? $pdo->query("SELECT id,name FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$pkgs    = $HAS_PACKAGES ? $pdo->query("SELECT id,name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) : [];
$areas   = $HAS_CLIENTS  ? $pdo->query("SELECT DISTINCT area FROM clients WHERE area IS NOT NULL AND area<>'' ORDER BY area ASC")->fetchAll(PDO::FETCH_COLUMN) : [];

/* ========== হেল্পার: sort লিংক ========== */
function sort_link($key,$label,$cur,$dir_raw){
  $qs = $_GET;
  $qs['sort'] = $key;
  $qs['dir']  = ($cur===$key && strtolower($dir_raw)==='asc') ? 'desc' : 'asc';
  $qs['page'] = 1;
  $icon = ' <i class="bi bi-arrow-down-up"></i>';
  if ($cur === $key) $icon = (strtolower($dir_raw)==='asc') ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
  return '<a class="text-decoration-none" href="?'.http_build_query($qs).'">'.$label.$icon.'</a>';
}

/* ========== পেজ রেন্ডার ========== */
$page_title = 'Audit Logs';
include __DIR__ . '/../partials/partials_header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
.table-sm td,.table-sm th{ padding:.5rem .55rem; vertical-align:middle; }
thead.table-dark th a{ color:#fff; text-decoration:none; }
thead.table-dark th a:hover{ text-decoration:underline; }
.badge-act{ background:#343a40; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace; }
.text-trunc-ua { max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.audit-table{ table-layout:fixed; width:100%; min-width:1250px; }
.audit-table col.col-id{ width:68px; }
.audit-table col.col-when{ width:190px; }
.audit-table col.col-action{ width:150px; }
.audit-table col.col-entity{ width:190px; }
.audit-table col.col-client{ width:220px; }
.audit-table col.col-router{ width:170px; }
.audit-table col.col-details{ width:42%; }
.audit-table col.col-actor{ width:170px; }
.audit-table col.col-ip{ width:240px; }
.audit-table td:not(.details-col), .audit-table th:not(.details-col){ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.details-inline{ display:flex; flex-direction:column; gap:6px; }
.details-inline .summary-pills{ display:flex; flex-wrap:wrap; gap:6px; }
.details-inline .summary-pill{ background:#eef2ff; color:#4338ca; border:1px solid #e0e7ff; border-radius:999px; padding:3px 9px; font-size:12px; }
.details-inline .details-snippet{ max-height:44px; overflow:hidden; white-space:normal; line-height:1.35; color:#1f2937; }
.details-inline .details-snippet.mono{ white-space:pre-wrap; word-break:break-word; }
.details-inline .details-links{ display:flex; gap:10px; align-items:center; font-size:12px; color:#0b5ed7; }
.details-inline .details-links a{ text-decoration:none; }
.details-inline .details-links a:hover{ text-decoration:underline; }
.details-inline .meta-note{ font-size:12px; color:#6b7280; }
.details-modal{ position:fixed; inset:0; background:rgba(0,0,0,0.35); display:flex; align-items:center; justify-content:center; padding:12px; z-index:1055; }
.details-modal[hidden]{ display:none; }
.details-modal-box{ background:#fff; border:1px solid #dee2e6; box-shadow:0 10px 40px rgba(0,0,0,0.18); border-radius:12px; width:min(760px, 96vw); max-height:82vh; padding:16px; overflow:hidden; }
.details-modal-body{ background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; padding:12px; max-height:65vh; overflow:auto; }
body.details-lock{ overflow:hidden; }
</style>

<div class="container-fluid py-3">
  <?php if ($client_id > 0): ?>
    <div class="alert alert-info py-2 d-flex align-items-center gap-2">
      <i class="bi bi-funnel"></i>
      <div>
        Showing logs for client ID <strong><?= (int)$client_id ?></strong><?= $client_code !== '' ? ' (filter: '.h($client_code).')' : '' ?>.
        <a href="/public/client_view.php?id=<?= (int)$client_id ?>" class="ms-2">Back to client</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- বাংলা: ফিল্টার ফর্ম -->
  <form class="card shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2 align-items-end">

        <!-- বাংলা: sort/dir স্টেট ধরে রাখো -->
        <input type="hidden" name="sort" value="<?= h($sort_key) ?>">
        <input type="hidden" name="dir"  value="<?= h($dir_raw) ?>">

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Search</label>
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Client / PPPoE / Event / JSON / Actor">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Client Code/ID</label>
          <input type="text" name="client_code" value="<?= h($client_code) ?>" class="form-control form-control-sm" placeholder="e.g., C123 / PPPoE">
          <input type="hidden" name="client_id" value="<?= $client_id > 0 ? (int)$client_id : '' ?>">
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

        <!-- বাংলা: এক্সপোর্ট অপশন -->
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

  <!-- বাংলা: ডাটা টেবিল -->
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle audit-table">
        <colgroup>
          <col class="col-id">
          <col class="col-when">
          <col class="col-action">
          <col class="col-entity">
          <col class="col-client">
          <col class="col-router">
          <col class="col-details">
          <col class="col-actor">
          <col class="col-ip">
        </colgroup>
        <thead class="table-dark">
          <tr>
            <th><?= sort_link('id','#', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('created','When', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('action','Action', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('entity','Entity', $sort_key, $dir_raw) ?></th>
            <?php if($HAS_CLIENTS): ?>
              <th><?= sort_link('client','Client', $sort_key, $dir_raw) ?></th>
            <?php else: ?>
              <th>Client</th>
            <?php endif; ?>
            <?php if($HAS_ROUTERS): ?>
              <th><?= sort_link('router','Router', $sort_key, $dir_raw) ?></th>
            <?php else: ?>
              <th>Router</th>
            <?php endif; ?>
            <th>Details</th>
            <th>Actor</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
        <?php if($rows): foreach($rows as $r):
          // বাংলা: ডিটেইলস প্রি-প্রসেস — টেবিলে সঠিক ডাটা নিশ্চিত
          $detailsRaw = (string)($r['details'] ?? '');
          $hasDetails = $detailsRaw !== '';
          $pretty = $hasDetails ? pretty_details($detailsRaw) : '';
          [$summaryOldNew, $hasDiff] = $hasDetails ? summarize_old_new($detailsRaw) : ['', false]; // বাংলা: পুরোনো→নতুন দ্রুত সারাংশ
          [$detailRows, $hasStructuredDiff, $isObj] = $hasDetails ? prepare_detail_rows($detailsRaw) : [[], false, false]; // বাংলা: ট্যাবুলার ভিউ তৈরির জন্য
          $fallbackSummary = $hasDetails ? ($summaryOldNew ?: clip_line($pretty, 200)) : '';
          $needsModal = $hasDetails && (strlen($pretty) > 260 || strlen($fallbackSummary) > 160); // বাংলা: লম্বা হলে পপ-আপ দরকার
          $summaryParts = $hasDiff && $summaryOldNew ? array_values(array_filter(array_map('trim', explode('|', $summaryOldNew)))) : [];
          $previewPretty = $hasDetails ? preview_block($pretty, 1400) : '';
          $badge = 'secondary';
          if (($r['action'] ?? '') !== '') {
            $act = strtolower((string)$r['action']);
            if (str_contains($act,'add') || str_contains($act,'create')) $badge='success';
            elseif (str_contains($act,'update') || str_contains($act,'edit')) $badge='primary';
            elseif (str_contains($act,'delete') || str_contains($act,'remove')) $badge='danger';
            elseif (str_contains($act,'toggle') || str_contains($act,'status')) $badge='warning';
          }
          $clientIdResolved = (int)($r['client_id_resolved'] ?? 0);
        ?>
          <tr>
            <td class="text-muted mono"><?= (int)$r['id'] ?></td>
            <td class="mono"><?= h($r['created_at'] ?: '-') ?></td>
            <td><span class="badge bg-<?= h($badge) ?>"><?= h($r['action'] ?: '-') ?></span></td>
            <td><?= ($r['entity_type']!==null ? h($r['entity_type']) : '—') ?> <?= $r['entity_id']?('#'.(int)$r['entity_id']):'' ?></td>
            <td>
              <?php if ($HAS_CLIENTS && !empty($r['client_name'])): ?>
                <a class="text-decoration-none" href="client_view.php?id=<?= $clientIdResolved ?: (int)$r['entity_id'] ?>">
                  <?= h($r['client_name']) ?>
                </a>
                <div class="text-muted small"><?= h($r['pppoe_id'] ?: '') ?></div>
                <?php if (($r['package_name'] ?? null) || ($r['area'] ?? null)): ?>
                  <div class="text-muted small">
                    <?= h($r['package_name'] ?: '') ?><?= (($r['package_name'] ?? '') && ($r['area'] ?? ''))?' · ':'' ?><?= h($r['area'] ?: '') ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= h($r['router_name'] ?: '—') ?></td>
            <td class="details-col" style="min-width:260px;">
              <?php if($hasDetails): ?>
                <?php $fieldCount = count($detailRows); // বাংলা: ডিফ টেবিলের মোট ফিল্ড সংখ্যা দেখাতে রাখলাম ?>
                <div class="details-inline">
                  <?php if($summaryParts): ?>
                    <div class="summary-pills">
                      <?php foreach(array_slice($summaryParts,0,4) as $chip): ?>
                        <span class="summary-pill"><?= h($chip) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="details-links">
                    <a href="javascript:void(0)" class="details-more" data-full="<?= h($pretty) ?>" data-logid="<?= (int)$r['id'] ?>">▶ Full Details</a>
                  </div>
                </div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php
                $actor = trim(($r['user_name'] ?? ''));
                $uid   = (string)($r['user_id'] ?? '');
                if ($actor !== '' && $uid !== '') echo h($actor) . " (#" . h($uid) . ")";
                elseif ($uid !== '') echo "#" . h($uid);
                else echo '-';
              ?>
            </td>
            <td>
              <code><?= h($r['ip'] ?? '') ?></code>
              <div class="text-muted text-trunc-ua" title="<?= h($r['ua'] ?? '') ?>"><?= h($r['ua'] ?? '') ?></div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No logs found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- বাংলা: ডিটেইলস পূর্ণ ভিউ মডাল -->
  <div id="details-modal" class="details-modal" hidden>
    <div class="details-modal-box">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Details</div>
        <button type="button" class="btn-close" aria-label="Close" data-dismiss="details-modal"></button>
      </div>
      <pre class="details-modal-body mono mb-0"></pre>
    </div>
  </div>

  <!-- বাংলা: প্যাজিনেশন -->
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

<!-- বাংলা: ডিটেইলস পপ-আপ কন্ট্রোল -->
<script>
document.addEventListener('DOMContentLoaded', function(){
  const modal = document.getElementById('details-modal');
  if(!modal) return;
  const body = modal.querySelector('.details-modal-body');
  const closeBtns = modal.querySelectorAll('[data-dismiss="details-modal"]');

  const closeModal = () => {
    modal.classList.remove('show');
    modal.hidden = true;
    document.body.classList.remove('details-lock');
  };

  const openModal = (txt) => {
    body.textContent = txt || '';
    modal.hidden = false;
    modal.classList.add('show');
    document.body.classList.add('details-lock');
  };

  closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  document.querySelectorAll('.details-more').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.full || ''));
  });
});
</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
