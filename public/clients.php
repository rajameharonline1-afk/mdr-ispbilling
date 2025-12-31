<?php
// /public/clients.php (schema-aware list + filters + live online overlay)
// UI: English; Comments: বাংলা

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

/* ================== Bootstrap ================== */
$pdo = db(); // বাংলা নোট: একবারই PDO নিন
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- Runtime column detection ---------- */
$clientCols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
if (!function_exists('pick_col')) {
  function pick_col(array $cols, array $cands): string {
    foreach ($cands as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return '';
  }
}

$hasOnline  = in_array('is_online',   $clientCols, true);
$hasLeft    = in_array('is_left',     $clientCols, true);
$hasJoin    = in_array('join_date',   $clientCols, true);
$hasExpire  = in_array('expiry_date', $clientCols, true);

$AREA_COL       = pick_col($clientCols, ['area','zone','location']);
$SUB_ZONE_COL   = pick_col($clientCols, ['sub_zone','subzone','sub_area']);
$BOX_COL        = pick_col($clientCols, ['box','distribution_box','box_name']);
$PROTOCOL_COL   = pick_col($clientCols, ['protocol_type','protocol']);
$PROFILE_COL    = pick_col($clientCols, ['profile','pppoe_profile','profile_name','mt_profile']);
$CLIENT_TYPE_COL= pick_col($clientCols, ['client_type','customer_type']);
$CONN_TYPE_COL  = pick_col($clientCols, ['connection_type','conn_type']);
$B_STATUS_COL   = pick_col($clientCols, ['billing_status','payment_status']);
$M_STATUS_COL   = pick_col($clientCols, ['mikrotik_status','m_status','mt_status']);
$CUSTOM_STATUS_COL = pick_col($clientCols, ['custom_status','status_custom']);

$hasArea    = ($AREA_COL !== '');
$hasSubZone = ($SUB_ZONE_COL !== '');
$hasBox     = ($BOX_COL !== '');

/* (বাংলা) প্যাকেজ/রাউটার টেবিলের নাম কলাম ডাইনামিকলি ঠিক করা */
$pkgCols = [];
try { $pkgCols = $pdo->query("SHOW COLUMNS FROM packages")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
$pkgNameParts = [];
foreach (['name','title','package_name'] as $c) { if (in_array($c,$pkgCols,true)) $pkgNameParts[] = "p.`$c`"; }
$PKG_NAME_EXPR = $pkgNameParts ? ('COALESCE('.implode(',', $pkgNameParts).')') : 'NULL';

$pkgProfileCols = array_values(array_filter(
  ['profile','profile_name','pppoe_profile','mt_profile','router_profile'],
  fn($c) => in_array($c, $pkgCols, true)
));

$rtCols = [];
try { $rtCols = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {}
$rtNameParts = [];
foreach (['name','identity','ip','host'] as $c) { if (in_array($c,$rtCols,true)) $rtNameParts[] = "`$c`"; }
$ROUTER_NAME_EXPR = $rtNameParts ? ('COALESCE('.implode(',', $rtNameParts).')') : 'id';

/* ================== Inputs ================== */
$status = $_GET['status'] ?? '';
$allowedStatus = ['','active','inactive','online','offline']; // বাংলা: স্ট্যাটাস স্যানিটাইজ
if (!in_array($status, $allowedStatus, true)) { $status = ''; }

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

/* Live flag: live=1 => fetch from MikroTik PPP Active; live=0 => use DB only */
$live = (int)($_GET['live'] ?? 1); // বাংলা নোট: ডিফল্ট লাইভ অন

/* ---- Advanced filters ---- */
$package_id = (int)($_GET['package_id'] ?? 0);
$router_id  = (int)($_GET['router_id']  ?? 0);
$zone       = trim($_GET['zone'] ?? '');
$area       = trim($_GET['area'] ?? '');
if ($zone === '' && $area !== '') $zone = $area;

$sub_zone   = trim($_GET['sub_zone'] ?? '');
$box        = trim($_GET['box'] ?? '');
$protocol   = trim($_GET['protocol'] ?? '');
$profile    = trim($_GET['profile'] ?? '');
$client_type = trim($_GET['client_type'] ?? '');
$connection_type = trim($_GET['connection_type'] ?? '');
$b_status   = trim($_GET['b_status'] ?? '');
$m_status   = trim($_GET['m_status'] ?? '');
$custom_status = trim($_GET['custom_status'] ?? '');

$join_from  = trim($_GET['join_from'] ?? '');
$join_to    = trim($_GET['join_to']   ?? '');
$from_date  = trim($_GET['from_date'] ?? '');
$to_date    = trim($_GET['to_date']   ?? '');
if ($join_from === '' && $from_date !== '') $join_from = $from_date;
if ($join_to === '' && $to_date !== '')     $join_to   = $to_date;

$exp_from   = trim($_GET['exp_from']  ?? '');
$exp_to     = trim($_GET['exp_to']    ?? '');

$re_date = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re_date, $join_from)) $join_from = '';
if (!preg_match($re_date, $join_to))   $join_to   = '';
if (!preg_match($re_date, $from_date)) $from_date = '';
if (!preg_match($re_date, $to_date))   $to_date   = '';
if (!preg_match($re_date, $exp_from))  $exp_from  = '';
if (!preg_match($re_date, $exp_to))    $exp_to    = '';

if ($CUSTOM_STATUS_COL === '') {
  if ($custom_status !== '' && in_array($custom_status, $allowedStatus, true)) {
    $status = $custom_status;
  } elseif ($custom_status !== '') {
    $custom_status = '';
  }
}
if ($custom_status === '' && $status !== '') {
  $custom_status = $status;
}

/* ---- Sorting (?sort=name&dir=asc) ---- */
$sort   = strtolower($_GET['sort'] ?? 'id');
$dirRaw = strtolower($_GET['dir']  ?? 'desc');
$dirRaw = in_array($dirRaw, ['asc','desc'], true) ? $dirRaw : 'desc';

$map = [
  'id'      => 'c.id',
  'code'    => 'c.client_code',
  'name'    => 'c.name',
  'pppoe'   => 'c.pppoe_id',
  'phone'   => 'c.mobile',
  'package' => $PKG_NAME_EXPR,   // (ডাইনামিক এক্সপ্রেশন)
  'status'  => 'c.status',
  'area'    => $hasArea ? ('c.' . $AREA_COL) : 'c.id',
  'balance' => 'c.ledger_balance',
];
if ($hasJoin)   { $map['join']   = 'c.join_date'; }
if ($hasOnline) { $map['online'] = 'c.is_online'; }
if ($hasExpire) { $map['expiry'] = 'c.expiry_date'; }
if (!isset($map[$sort])) $sort = 'id';

$dirSql = ($dirRaw === 'asc') ? 'ASC' : 'DESC';
$order  = $map[$sort] . ' ' . $dirSql;

/* ---------- Sortable header link helper ---------- */
// বাংলা নোট: সব GET প্যারাম রেখে নির্দিষ্ট sort/dir/page আপডেট করি
function sort_link(string $key, string $label): string {
    $qs = $_GET;
    $currentSort = strtolower($qs['sort'] ?? 'id');
    $currentDir  = strtolower($qs['dir'] ?? 'desc');

    $qs['sort'] = $key;
    $qs['dir']  = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $qs['page'] = 1;

    $href = '?' . http_build_query($qs);

    if ($currentSort === $key) {
        $arrow = ($currentDir === 'asc')
               ? ' <i class="bi bi-caret-up-fill"></i>'
               : ' <i class="bi bi-caret-down-fill"></i>';
    } else {
        $arrow = ' <i class="bi bi-arrow-down-up"></i>';
    }
    return '<a class="text-decoration-none" href="'.$href.'">'.$label.$arrow.'</a>';
}

/* ================== Query Build ================== */
$sql_base = "FROM clients c
             LEFT JOIN packages p ON c.package_id = p.id
             LEFT JOIN routers r ON c.router_id = r.id
             WHERE 1";
$params = [];

if ($hasLeft) {
    $sql_base .= " AND c.is_left = 0"; // বাংলা নোট: delete-এর বদলে left ফ্লো
}

/* Status quick filters (DB-level only) */
if ($status === 'active') {
    $sql_base .= " AND c.status = 'active'";
} elseif ($status === 'inactive') {
    $sql_base .= " AND c.status = 'inactive'";
} elseif ($status === 'online' && $hasOnline) {
    $sql_base .= " AND c.is_online = 1";
} elseif ($status === 'offline' && $hasOnline) {
    $sql_base .= " AND c.is_online = 0";
}

/* Basic search */
if ($search !== '') {
    $sql_base .= " AND (c.name LIKE ? OR c.pppoe_id LIKE ? OR c.mobile LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

/* Advanced filters */
if ($package_id > 0) { $sql_base .= " AND c.package_id = ?";  $params[] = $package_id; }
if ($router_id  > 0) { $sql_base .= " AND c.router_id  = ?";  $params[] = $router_id; }
if ($hasArea && $zone !== '') { $sql_base .= " AND c.`{$AREA_COL}` = ?"; $params[] = $zone; }
if ($hasSubZone && $sub_zone !== '') { $sql_base .= " AND c.`{$SUB_ZONE_COL}` = ?"; $params[] = $sub_zone; }
if ($hasBox && $box !== '') { $sql_base .= " AND c.`{$BOX_COL}` = ?"; $params[] = $box; }
if ($PROTOCOL_COL !== '' && $protocol !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$PROTOCOL_COL}`)) = TRIM(LOWER(?))"; $params[] = $protocol; }
if ($CLIENT_TYPE_COL !== '' && $client_type !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$CLIENT_TYPE_COL}`)) = TRIM(LOWER(?))"; $params[] = $client_type; }
if ($CONN_TYPE_COL !== '' && $connection_type !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$CONN_TYPE_COL}`)) = TRIM(LOWER(?))"; $params[] = $connection_type; }
if ($B_STATUS_COL !== '' && $b_status !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$B_STATUS_COL}`)) = TRIM(LOWER(?))"; $params[] = $b_status; }
if ($M_STATUS_COL !== '' && $m_status !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$M_STATUS_COL}`)) = TRIM(LOWER(?))"; $params[] = $m_status; }
if ($CUSTOM_STATUS_COL !== '' && $custom_status !== '') { $sql_base .= " AND TRIM(LOWER(c.`{$CUSTOM_STATUS_COL}`)) = TRIM(LOWER(?))"; $params[] = $custom_status; }

if ($profile !== '') {
  if ($PROFILE_COL !== '') {
    $sql_base .= " AND TRIM(LOWER(c.`{$PROFILE_COL}`)) = TRIM(LOWER(?))";
    $params[] = $profile;
  } elseif (!empty($pkgProfileCols)) {
    $or = [];
    foreach ($pkgProfileCols as $col) {
      $or[] = "TRIM(LOWER(p.`{$col}`)) = TRIM(LOWER(?))";
      $params[] = $profile;
    }
    $sql_base .= " AND (" . implode(' OR ', $or) . ")";
  }
}

/* বাংলা নোট: ইনডেক্স বাঁচাতে DATE() এড়াই; DATETIME ধরে বাউন্ড সেট */
if ($hasJoin) {
  if ($join_from !== '') { $sql_base .= " AND c.join_date >= ?";   $params[] = $join_from . ' 00:00:00'; }
  if ($join_to   !== '') { $sql_base .= " AND c.join_date <= ?";   $params[] = $join_to   . ' 23:59:59'; }
}
if ($hasExpire) {
  if ($exp_from  !== '') { $sql_base .= " AND c.expiry_date >= ?"; $params[] = $exp_from  . ' 00:00:00'; }
  if ($exp_to    !== '') { $sql_base .= " AND c.expiry_date <= ?"; $params[] = $exp_to    . ' 23:59:59'; }
}

/* Count */
$stmt_count = $pdo->prepare("SELECT COUNT(*) ".$sql_base);
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();
$total_pages   = $limit > 0 ? (int)ceil($total_records / $limit) : 1;

/* Data: (বাংলা) প্যাকেজ নামকে ডাইনামিক এক্সপ্রেশনে সিলেক্ট করি */
$sql = "SELECT c.*,
               {$PKG_NAME_EXPR} AS package_name,
               r.name AS router_name,
               r.ip AS router_ip,
               r.username AS router_user,
               r.password AS router_pass
        ".$sql_base."
        ORDER BY $order, c.id DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================== LIVE online overlay (MikroTik PPP Active) ================== */
$liveOnlineMap = []; // [pppoe_id] => 1
$liveRouters = [];

if ($live && count($clients) > 0) {
    $routerIds = array_unique(array_values(array_filter(array_map(
        fn($c) => isset($c['router_id']) ? (int)$c['router_id'] : 0, $clients
    ))));
    if ($routerIds) {
        $in = implode(',', array_fill(0, count($routerIds), '?'));
        $rst = $pdo->prepare("SELECT id, ip, username, password, api_port FROM routers WHERE id IN ($in)");
        $rst->execute($routerIds);
        $liveRouters = $rst->fetchAll(PDO::FETCH_ASSOC);

        @require_once __DIR__ . '/../app/routeros_api.class.php';
        if (class_exists('RouterosAPI')) {
            foreach ($liveRouters as $rt) {
                $ip   = $rt['ip'] ?? '';
                $user = $rt['username'] ?? '';
                $pass = $rt['password'] ?? '';
                $port = (int)($rt['api_port'] ?? 8728) ?: 8728;
                if (!$ip || !$user) continue;

                try {
                    $API = new RouterosAPI();
                    $API->debug = false;
                    if (property_exists($API, 'timeout'))  $API->timeout  = 3;
                    if (property_exists($API, 'attempts')) $API->attempts = 1;

                    if (method_exists($API, 'connect') && $API->connect($ip, $user, $pass, $port)) {
                        if (method_exists($API, 'comm')) {
                            $res = $API->comm('/ppp/active/print', ['.proplist' => 'name']);
                        } else {
                            $API->write('/ppp/active/print');
                            $res = $API->read();
                        }
                        if (is_array($res)) {
                            foreach ($res as $row) {
                                if (!empty($row['name'])) $liveOnlineMap[(string)$row['name']] = 1;
                            }
                        }
                        $API->disconnect();
                    }
                } catch (Throwable $e) {
                    // বাংলা নোট: কোনো রাউটার না ধরলে চুপচাপ স্কিপ
                }
            }
        }
    }

    foreach ($clients as &$c) {
        $pppoe = (string)($c['pppoe_id'] ?? '');
        $c['_live_online'] = ($pppoe !== '' && isset($liveOnlineMap[$pppoe])) ? 1 : 0;
    }
    unset($c);

    if (!$hasOnline && ($status === 'online' || $status === 'offline')) {
        $want = ($status === 'online') ? 1 : 0;
        $clients = array_values(array_filter($clients, fn($c) => (int)($c['_live_online'] ?? 0) === $want));
        $total_records = count($clients);
        $total_pages   = 1;
        $page          = 1;
    }
}

/* Dropdown data (schema-aware names — ডাইনামিক এক্সপ্রেশন ইউজ) */
$packages = $pdo->query("SELECT id, ".($pkgNameParts ? ('COALESCE('.implode(',', array_map(fn($c)=>"`$c`",$pkgCols?array_intersect(['name','title','package_name'],$pkgCols):[])).')') : 'NULL')." AS name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$routers  = $pdo->query("SELECT id, ".($rtNameParts ? $ROUTER_NAME_EXPR : 'id')." AS name FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('distinct_values')) {
  function distinct_values(PDO $pdo, string $table, string $col): array {
    try {
      $st = $pdo->query("SELECT DISTINCT `$col` AS v FROM `$table` WHERE `$col` IS NOT NULL AND `$col`<>'' ORDER BY `$col` ASC");
      return array_values(array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) {
      return [];
    }
  }
}

$zones = $hasArea ? distinct_values($pdo, 'clients', $AREA_COL) : [];
$sub_zones_list = $hasSubZone ? distinct_values($pdo, 'clients', $SUB_ZONE_COL) : [];
$boxes = $hasBox ? distinct_values($pdo, 'clients', $BOX_COL) : [];
$protocols = ($PROTOCOL_COL !== '') ? distinct_values($pdo, 'clients', $PROTOCOL_COL) : [];
$client_types = ($CLIENT_TYPE_COL !== '') ? distinct_values($pdo, 'clients', $CLIENT_TYPE_COL) : [];
$connection_types = ($CONN_TYPE_COL !== '') ? distinct_values($pdo, 'clients', $CONN_TYPE_COL) : [];
$b_statuses = ($B_STATUS_COL !== '') ? distinct_values($pdo, 'clients', $B_STATUS_COL) : [];
$m_statuses = ($M_STATUS_COL !== '') ? distinct_values($pdo, 'clients', $M_STATUS_COL) : [];
$custom_statuses = ($CUSTOM_STATUS_COL !== '') ? distinct_values($pdo, 'clients', $CUSTOM_STATUS_COL) : [];

$profiles = [];
if ($PROFILE_COL !== '') {
  $profiles = distinct_values($pdo, 'clients', $PROFILE_COL);
} elseif (!empty($pkgProfileCols)) {
  $tmp = [];
  foreach ($pkgProfileCols as $col) {
    $tmp = array_merge($tmp, distinct_values($pdo, 'packages', $col));
  }
  $profiles = array_values(array_unique($tmp));
  sort($profiles, SORT_NATURAL | SORT_FLAG_CASE);
}

/* UI: adv filter active? */
$adv_active = (
  $package_id>0 || $router_id>0 || ($hasArea && $zone!=='') || ($hasSubZone && $sub_zone!=='') || ($hasBox && $box!=='') ||
  $protocol!=='' || $profile!=='' || $client_type!=='' || $connection_type!=='' ||
  $b_status!=='' || $m_status!=='' || $custom_status!=='' ||
  ($hasJoin && ($join_from!=='' || $join_to!=='')) || ($hasExpire && ($exp_from!=='' || $exp_to!=='')) ||
  $from_date!=='' || $to_date!==''
);

/* ====== Page header include ====== */
$_active    = 'clients';         // বাংলা নোট: সাইডবার Active highlight
$page_title = 'All Clients';
require __DIR__ . '/../partials/partials_header.php';
?>
<div class="container-fluid">

  <!-- Header -->
  <?php
    $headerStatus = $status;
    if ($headerStatus === '' && $CUSTOM_STATUS_COL !== '' && $custom_status !== '') {
      $headerStatus = $custom_status;
    }
  ?>
  <div class="mb-2 d-flex flex-wrap align-items-center gap-2">
    <h4 class="mb-0">
      <?php
        if ($headerStatus === 'active')      echo "Active Clients";
        elseif ($headerStatus === 'inactive') echo "Inactive Clients";
        elseif ($headerStatus === 'online')   echo "Online Clients";
        elseif ($headerStatus === 'offline')  echo "Offline Clients";
        elseif ($headerStatus !== '')         echo htmlspecialchars(ucfirst($headerStatus))." Clients";
        else                                  echo "All Clients";
      ?>
    </h4>
    <span class="text-muted small">Total: <?= number_format($total_records) ?></span>

    <div class="ms-auto d-flex gap-2">
      <?php
        $qsLiveOn  = $_GET; $qsLiveOn['live']=1;  $qsLiveOn['page']=1;
        $qsLiveOff = $_GET; $qsLiveOff['live']=0; $qsLiveOff['page']=1;
      ?>
      <a href="?<?= http_build_query($qsLiveOn) ?>" class="btn btn-outline-success btn-sm <?= $live? 'active':'' ?>">
        <i class="bi bi-lightning-charge"></i> Live ON
      </a>
      <a href="?<?= http_build_query($qsLiveOff) ?>" class="btn btn-outline-secondary btn-sm <?= !$live? 'active':'' ?>">
        <i class="bi bi-lightning"></i> Live OFF
      </a>
    </div>
  </div>

  <!-- Filter toolbar -->
  <form method="GET" class="filter-card card border-0 shadow-sm mb-3">
    <!-- keep sort/dir + live -->
    <?php if (!empty($_GET['sort'])): ?>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort']) ?>">
    <?php endif; ?>
    <?php if (!empty($_GET['dir'])): ?>
      <input type="hidden" name="dir" value="<?= htmlspecialchars($_GET['dir']) ?>">
    <?php endif; ?>
    <input type="hidden" name="live" value="<?= (int)$live ?>">
    <div class="card-body">
      <div class="row g-2 mt-1 justify-content-end">
        <div class="col-12 col-md-3 d-grid">
          <button class="btn btn-primary btn-sm filter-toggle-btn" type="button" id="toggle-filters" aria-expanded="<?= $adv_active ? 'true' : 'false' ?>">
            <i class="bi bi-filter"></i> Filters
          </button>
        </div>
      </div>

      <div class="filter-grid mt-3 <?= $adv_active ? '' : 'd-none' ?>" id="filters-panel">
        <div class="row g-2 g-md-3">
          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Server</label>
            <select name="router_id" class="form-select form-select-sm">
              <option value="0">Select</option>
              <?php foreach($routers as $rt): ?>
                <option value="<?= (int)$rt['id'] ?>" <?= $router_id==(int)$rt['id']?'selected':'' ?>>
                  <?= htmlspecialchars($rt['name'] ?? 'Router #'.(int)$rt['id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Protocol Type</label>
            <select name="protocol" class="form-select form-select-sm" <?= $PROTOCOL_COL==='' ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($protocols as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $protocol===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Profile</label>
            <select name="profile" class="form-select form-select-sm" <?= empty($profiles) ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($profiles as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $profile===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Zone</label>
            <select name="zone" class="form-select form-select-sm" <?= !$hasArea ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($zones as $z): ?>
                <option value="<?= htmlspecialchars($z) ?>" <?= $zone===$z?'selected':'' ?>><?= htmlspecialchars($z) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Sub Zone</label>
            <select name="sub_zone" class="form-select form-select-sm" <?= !$hasSubZone ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($sub_zones_list as $sz): ?>
                <option value="<?= htmlspecialchars($sz) ?>" <?= $sub_zone===$sz?'selected':'' ?>><?= htmlspecialchars($sz) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Box</label>
            <select name="box" class="form-select form-select-sm" <?= !$hasBox ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($boxes as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>" <?= $box===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Package</label>
            <select name="package_id" class="form-select form-select-sm">
              <option value="0">Select</option>
              <?php foreach($packages as $pkg): ?>
                <option value="<?= (int)$pkg['id'] ?>" <?= $package_id==(int)$pkg['id']?'selected':'' ?>>
                  <?= htmlspecialchars($pkg['name'] ?? 'N/A') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Client Type</label>
            <select name="client_type" class="form-select form-select-sm" <?= $CLIENT_TYPE_COL==='' ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($client_types as $ct): ?>
                <option value="<?= htmlspecialchars($ct) ?>" <?= $client_type===$ct?'selected':'' ?>><?= htmlspecialchars($ct) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Connection Type</label>
            <select name="connection_type" class="form-select form-select-sm" <?= $CONN_TYPE_COL==='' ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($connection_types as $ct): ?>
                <option value="<?= htmlspecialchars($ct) ?>" <?= $connection_type===$ct?'selected':'' ?>><?= htmlspecialchars($ct) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">B.Status</label>
            <select name="b_status" class="form-select form-select-sm" <?= $B_STATUS_COL==='' ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($b_statuses as $bs): ?>
                <option value="<?= htmlspecialchars($bs) ?>" <?= $b_status===$bs?'selected':'' ?>><?= htmlspecialchars($bs) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">M.Status</label>
            <select name="m_status" class="form-select form-select-sm" <?= $M_STATUS_COL==='' ? 'disabled' : '' ?>>
              <option value="">Select</option>
              <?php foreach($m_statuses as $ms): ?>
                <option value="<?= htmlspecialchars($ms) ?>" <?= $m_status===$ms?'selected':'' ?>><?= htmlspecialchars($ms) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">Custom Status</label>
            <select name="custom_status" class="form-select form-select-sm">
              <?php
                $status_opts = [''=>'Select','active'=>'Active','inactive'=>'Inactive'];
                if ($hasOnline || $live) { $status_opts['online']='Online'; $status_opts['offline']='Offline'; }
              ?>
              <?php if ($CUSTOM_STATUS_COL !== ''): ?>
                <option value="">Select</option>
                <?php foreach($custom_statuses as $cs): ?>
                  <option value="<?= htmlspecialchars($cs) ?>" <?= $custom_status===$cs?'selected':'' ?>><?= htmlspecialchars($cs) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach($status_opts as $k=>$v): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= $custom_status===$k?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <?php $from_val = $from_date !== '' ? $from_date : $join_from; ?>
          <?php $to_val = $to_date !== '' ? $to_date : $join_to; ?>
          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">From Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_val) ?>" class="form-control form-control-sm">
          </div>
          <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label mb-1 text-uppercase small fw-semibold">To Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_val) ?>" class="form-control form-control-sm">
          </div>
        </div>
      </div>

      <div class="row g-2 mt-3 <?= $adv_active ? '' : 'd-none' ?>" id="filters-actions">
        <div class="col-12 col-md-3 d-grid">
          <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Apply</button>
        </div>
        <div class="col-12 col-md-3 d-grid">
          <?php
            $base = $_GET;
            unset($base['status'],$base['custom_status'],$base['search'],$base['package_id'],$base['router_id'],$base['area'],$base['zone'],
                  $base['sub_zone'],$base['box'],$base['protocol'],$base['profile'],$base['client_type'],$base['connection_type'],
                  $base['b_status'],$base['m_status'],$base['from_date'],$base['to_date'],$base['join_from'],$base['join_to'],
                  $base['exp_from'],$base['exp_to'],$base['page']);
            $reset_qs = http_build_query($base);
          ?>
          <a class="btn btn-outline-secondary btn-sm" href="?<?= $reset_qs ?>">
            <i class="bi bi-x-circle"></i> Reset
          </a>
        </div>
      </div>

      <div class="<?= $adv_active ? '' : 'd-none' ?>" id="filters-badges">
        <?php
          $badges = [];
          if ($custom_status!=='') $badges[] = 'Status: '.htmlspecialchars($custom_status);
          if ($package_id>0)    { foreach($packages as $pkg){ if((int)$pkg['id']===$package_id){ $badges[]='Package: '.htmlspecialchars($pkg['name']??''); break; } } }
          if ($router_id>0)     { foreach($routers as $rt){ if((int)$rt['id']===$router_id){ $badges[]='Server: '.htmlspecialchars($rt['name']??('Router #'.$router_id)); break; } } }
          if ($hasArea && $zone!=='')       $badges[] = 'Zone: '.htmlspecialchars($zone);
          if ($hasSubZone && $sub_zone!=='') $badges[] = 'Sub Zone: '.htmlspecialchars($sub_zone);
          if ($hasBox && $box!=='')          $badges[] = 'Box: '.htmlspecialchars($box);
          if ($protocol!=='')               $badges[] = 'Protocol: '.htmlspecialchars($protocol);
          if ($profile!=='')                $badges[] = 'Profile: '.htmlspecialchars($profile);
          if ($client_type!=='')            $badges[] = 'Client Type: '.htmlspecialchars($client_type);
          if ($connection_type!=='')        $badges[] = 'Conn Type: '.htmlspecialchars($connection_type);
          if ($b_status!=='')               $badges[] = 'B.Status: '.htmlspecialchars($b_status);
          if ($m_status!=='')               $badges[] = 'M.Status: '.htmlspecialchars($m_status);
          if ($hasJoin && $join_from!=='')  $badges[] = 'From: '.htmlspecialchars($join_from);
          if ($hasJoin && $join_to!=='')    $badges[] = 'To: '.htmlspecialchars($join_to);
          if ($hasExpire && $exp_from!=='') $badges[] = 'Expire ≥ '.htmlspecialchars($exp_from);
          if ($hasExpire && $exp_to!=='')   $badges[] = 'Expire ≤ '.htmlspecialchars($exp_to);
        ?>
        <?php if (!empty($badges)): ?>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <?php foreach($badges as $b): ?>
              <span class="badge rounded-pill text-bg-light border"><?= $b ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Bulk action bar (+ Export) -->
  <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
    <button id="bulk-enable" class="btn btn-success btn-sm" disabled>Enable Selected</button>
    <button id="bulk-disable" class="btn btn-danger btn-sm" disabled>Disable Selected</button>
    <span class="text-muted small" id="sel-counter">(0 selected)</span>

    <?php
      $export_qs = http_build_query([
        'search'     => $search,
        'status'     => $status,
        'package_id' => $package_id,
        'router_id'  => $router_id,
        'area'       => $hasArea ? $zone : '',
        'join_from'  => $hasJoin ? $join_from : '',
        'join_to'    => $hasJoin ? $join_to   : '',
        'exp_from'   => $hasExpire ? $exp_from : '',
        'exp_to'     => $hasExpire ? $exp_to   : '',
        'sort'       => $sort,
        'dir'        => $dirRaw,
        'live'       => $live,
      ]);
    ?>
    <a class="btn btn-outline-secondary btn-sm" href="export_clients.php?<?= $export_qs ?>">
      <i class="bi bi-filetype-csv"></i> Export CSV
    </a>
  </div>

  <!-- Table -->
  <?php $showOnlineCol = ($live || $hasOnline); ?>
  <div class="table-container table-responsive mt-3">
    <table class="table table-hover align-middle table-app table-stack">
      <thead>
        <tr>
          <th style="width:32px;"><input type="checkbox" id="select-all"></th>
          <th><?= sort_link('id', 'Client ID') ?></th>
          <th><?= sort_link('name',   'Name') ?></th>
          <?php if ($hasArea): ?><th><?= sort_link('area', 'Area') ?></th><?php endif; ?>
          <th><?= sort_link('pppoe',  'PPPoE ID') ?></th>
          <th><?= sort_link('phone',  'Phone') ?></th>
          <th><?= sort_link('package','Package') ?></th>
          <th><?= sort_link('status', 'Status') ?></th>
          <?php if ($showOnlineCol): ?><th><?= $hasOnline ? sort_link('online','Online') : 'Online' ?></th><?php endif; ?>
          <?php if ($hasJoin): ?><th><?= sort_link('join',   'Join Date') ?></th><?php endif; ?>
          <?php if ($hasExpire): ?><th><?= sort_link('expiry','Expiry') ?></th><?php endif; ?>
          <th><?= sort_link('balance','Balance') ?></th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($clients): foreach ($clients as $client): ?>
        <tr>
          <td data-label="Select">
            <input type="checkbox" class="row-check"
                   value="<?= (int)$client['id']; ?>"
                   data-router="<?= (int)($client['router_id'] ?? 0); ?>">
          </td>
          <td data-label="Client ID" class="text-monospace"><?= (int)$client['id']; ?></td>

          <td data-label="Name"><?= htmlspecialchars($client['name']); ?></td>

          <?php if ($hasArea): ?>
          <td data-label="Area"><?= htmlspecialchars($client[$AREA_COL] ?? ''); ?></td>
          <?php endif; ?>

          <td data-label="PPPoE ID">
            <a href="client_view.php?id=<?= (int)$client['id']; ?>"
               class="text-decoration-none"
               title="View: <?= (int)$client['id'] ?>">
              <?= htmlspecialchars($client['pppoe_id'] ?? '') ?>
            </a>
          </td>

          <td data-label="Phone"><?= htmlspecialchars($client['mobile'] ?? '') ?></td>

          <td data-label="Package"><?= htmlspecialchars($client['package_name'] ?? 'N/A'); ?></td>

          <td data-label="Status">
            <?php if (($client['status'] ?? '') === 'active'): ?>
              <span class="badge status-pill bg-success">Active</span>
            <?php elseif (($client['status'] ?? '') === 'inactive'): ?>
              <span class="badge status-pill bg-danger">Inactive</span>
            <?php else: ?>
              <span class="badge status-pill bg-secondary"><?= htmlspecialchars($client['status'] ?? 'N/A'); ?></span>
            <?php endif; ?>
          </td>

          <?php if ($showOnlineCol): ?>
          <td data-label="Online">
            <?php
              $onlineLive = isset($client['_live_online']) ? (int)$client['_live_online'] : null;
              if ($live && $onlineLive !== null) {
                  echo $onlineLive ? '<span class="badge status-pill bg-success">Online</span>' : '<span class="badge status-pill bg-secondary">Offline</span>';
              } elseif ($hasOnline && (int)($client['is_online'] ?? 0) === 1) {
                  echo '<span class="badge status-pill bg-success">Online</span>';
              } else {
                  echo '<span class="badge status-pill bg-secondary">Offline</span>';
              }
            ?>
          </td>
          <?php endif; ?>

          <?php if ($hasJoin): ?>
          <td data-label="Join Date"><?= htmlspecialchars($client['join_date'] ?? ''); ?></td>
          <?php endif; ?>

          <?php if ($hasExpire): ?>
          <td data-label="Expiry"><?= htmlspecialchars($client['expiry_date'] ?? ''); ?></td>
          <?php endif; ?>

          <td data-label="Balance"><?= number_format((float)($client['ledger_balance'] ?? 0), 0, '.', ',') ?></td>

          <td data-label="Action">
            <div class="btn-group btn-group-sm" role="group">
              <a href="client_view.php?id=<?= $client['pppoe_id']; ?>" class="btn btn-outline-primary" title="View Client">
                <i class="bi bi-eye"></i>
              </a>
              <a href="client_edit.php?id=<?= (int)$client['id']; ?>" class="btn btn-outline-primary" title="Edit Client">
                <i class="bi bi-pencil-square"></i>
              </a>
              <button
                type="button"
                class="btn btn-outline-primary btn-send-msg"
                title="Send Message"
                data-bs-toggle="modal"
                data-bs-target="#sendMsgModal"
                data-name="<?= h($client['name'] ?? '') ?>"
                data-code="<?= h($client['client_code'] ?? '') ?>"
                data-id="<?= (int)($client['id'] ?? 0) ?>"
                data-pppoe="<?= h($client['pppoe_id'] ?? '') ?>"
                data-pppoe-pass="<?= h($client['pppoe_pass'] ?? '') ?>"
                data-mobile="<?= h($client['mobile'] ?? '') ?>"
                data-package="<?= h($client['package_name'] ?? '') ?>"
                data-router-ip="<?= h($client['router_ip'] ?? '') ?>"
                data-router-user="<?= h($client['router_user'] ?? '') ?>"
                data-router-pass="<?= h($client['router_pass'] ?? '') ?>"
              >
                <i class="bi bi-envelope"></i>
              </button>
              <button class="btn btn-outline-primary" title="Change Package" onclick="bp2HandleBulkProfile()">
                <i class="bi bi-shuffle"></i>
              </button>

              <?php if (($client['status'] ?? '') === 'active'): ?>
                <button class="btn btn-sm btn-danger"
                        onclick="changeStatus(this, <?= (int)$client['id']; ?>, 'disable')"
                        title="Disable Client">
                  <i class="bi bi-x-square"></i>
                </button>
              <?php else: ?>
                <button class="btn btn-sm btn-success"
                        onclick="changeStatus(this, <?= (int)$client['id']; ?>, 'enable')"
                        title="Enable Client">
                  <i class="bi bi-file-check"></i>
                </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="<?= 9 + (int)$showOnlineCol + (int)$hasJoin + (int)$hasExpire + (int)$hasArea ?>" class="text-center text-muted">No clients found.</td></tr>
      <?php endif; ?>
      </tbody>
      </table>
    </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1,$page - 1)])); ?>">Previous</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if (($end - $start + 1) < 5) {
            if ($start == 1) { $end = min($total_pages, $start + 4); }
            elseif ($end == $total_pages) { $start = max(1, $end - 4); }
        }
        for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?= $i; ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages,$page + 1)])); ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

</div><!-- /.container-fluid -->

<!-- Send Message Modal -->
<div class="modal fade" id="sendMsgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content send-msg-card">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-dots me-1"></i> Send Message</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start gap-3">
          <div class="msg-icon"><i class="bi bi-pencil-square"></i></div>
          <textarea class="form-control" id="smsMessage" rows="5"></textarea>
        </div>
        <!-- <div class="form-text small mt-2">Message content is auto-filled with client and server details.</div> -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="smsSendBtn">Send</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('toggle-filters');
  const panel = document.getElementById('filters-panel');
  const actions = document.getElementById('filters-actions');
  const badges = document.getElementById('filters-badges');
  if (!toggle || !panel || !actions || !badges) return;
  toggle.addEventListener('click', () => {
    const willShow = panel.classList.contains('d-none');
    panel.classList.toggle('d-none', !willShow);
    actions.classList.toggle('d-none', !willShow);
    badges.classList.toggle('d-none', !willShow);
    toggle.setAttribute('aria-expanded', willShow ? 'true' : 'false');
  });
});

/* ====== API endpoints ====== */
const API_SINGLE       = '../api/control.php';
const API_BULK         = '../api/bulk_control.php';
const API_BULK_PROFILE = '../api/bulk_profile.php';
const API_BULK_NOTIFY  = '../api/bulk_notify.php';
const API_SUGGEST      = '../api/suggest_clients.php';

/* ====== CSRF (optional) ====== */
const CSRF = window.CSRF_TOKEN || '';

/* ====== Toast ====== */
function showToast(message, type='success', timeout=3000){
  const box = document.createElement('div');
  box.className = 'app-toast ' + (type === 'success' ? 'success' : 'error');
  box.textContent = message || '';
  document.body.appendChild(box);
  setTimeout(()=> box.classList.add('hide'), timeout - 250);
  setTimeout(()=> box.remove(), timeout);
}

/* ====== Confirm ====== */
function customConfirm({title='Confirm', message='Are you sure?', okText='OK', cancelText='Cancel'}){
  return new Promise((resolve)=>{
    const bd = document.createElement('div');
    bd.className = 'app-confirm-backdrop';
    bd.innerHTML = `
      <div class="app-confirm-box" role="dialog" aria-modal="true">
        <div class="app-confirm-title">${title}</div>
        <div class="app-confirm-text">${message}</div>
        <div class="app-confirm-actions">
          <button class="app-btn secondary" data-act="cancel">Cancel</button>
          <button class="app-btn primary" data-act="ok">OK</button>
        </div>
      </div>`;
    document.body.appendChild(bd);
    const close=(v)=>{ document.removeEventListener('keydown', onKey); bd.remove(); resolve(v); };
    const onKey=(e)=>{ if(e.key==='Escape') close(false); if(e.key==='Enter') close(true); };
    bd.addEventListener('click', e=>{ if(e.target.dataset.act==='ok') close(true); if(e.target.dataset.act==='cancel'||e.target===bd) close(false); });
    document.addEventListener('keydown', onKey);
    setTimeout(()=> bd.querySelector('[data-act="ok"]')?.focus(), 10);
  });
}

/* ====== Send Message Modal ====== */
(function(){
  const modalEl = document.getElementById('sendMsgModal');
  if (!modalEl) return;
  const msgBox = document.getElementById('smsMessage');
  const sendBtn = document.getElementById('smsSendBtn');

  function buildMessage(data){
    const name = data.name || 'গ্রাহক';
    const id = data.id || data.code || '';
    const pppoe = data.pppoe || '';
    const pass = data.pppoePass || '';
   
    return `প্রিয় ${name}, আপনার কোড হচ্ছেঃ ${id}, আইপি হচ্ছেঃ ${pppoe}, পাসওয়ার্ড হচ্ছেঃ ${pass}  ধন্যবাদ।`;
  }

  document.querySelectorAll('.btn-send-msg').forEach((btn) => {
    btn.addEventListener('click', () => {
      const data = {
        name: btn.dataset.name || '',
        id: btn.dataset.id || '',
        pppoe: btn.dataset.pppoe || '',
        pppoePass: btn.dataset.pppoePass || '',
        mobile: btn.dataset.mobile || '',
        pkg: btn.dataset.package || '',
        routerIp: btn.dataset.routerIp || '',
        routerUser: btn.dataset.routerUser || '',
        routerPass: btn.dataset.routerPass || ''
      };
      const msg = buildMessage(data);
      if (msgBox) msgBox.value = msg;
      if (sendBtn) sendBtn.dataset.mobile = data.mobile;
      if (window.bootstrap && bootstrap.Modal) {
        const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
        inst.show();
      }
    });
  });

  if (sendBtn) {
    sendBtn.addEventListener('click', async () => {
      const msg = (msgBox && msgBox.value ? msgBox.value.trim() : '');
      if (msg === '') {
        showToast('Message is empty.', 'error', 2500);
        return;
      }
      const to = sendBtn.dataset.mobile || '';
      if (to) {
        window.location.href = `sms:${to}?body=${encodeURIComponent(msg)}`;
      } else {
        try {
          await navigator.clipboard.writeText(msg);
          showToast('Message copied.', 'success', 2200);
        } catch (e) {
          showToast('Copy failed.', 'error', 2200);
        }
      }
    });
  }
})();

/* ====== Single enable/disable ====== */
async function changeStatus(btn, id, action){
  const ok = await customConfirm({
    title: (action==='disable')?'Disable client?':'Enable client?',
    message: `Are you sure you want to ${action} this client?`,
    okText: (action==='disable')?'Disable':'Enable',
    cancelText: 'Cancel'
  });
  if (!ok) return;

  const oldHTML = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';

  fetch(`${API_SINGLE}?action=${encodeURIComponent(action)}&id=${encodeURIComponent(id)}`, {
    headers: CSRF ? {'X-CSRF-Token': CSRF} : {}
  })
    .then(r=>r.json())
    .then(data=>{
      if (data.status === 'success'){
        showToast(data.message || 'Success', 'success', 2500);
        setTimeout(()=> location.reload(), 800);
      } else {
        showToast(data.message || 'Operation failed', 'error', 3000);
        btn.disabled=false; btn.innerHTML=oldHTML;
      }
    })
    .catch(()=>{
      showToast('Request failed', 'error', 3000);
      btn.disabled=false; btn.innerHTML=oldHTML;
    });
}

/* ====== Bulk selection & actions ====== */
const sel = {
  set:new Set(), boxAll:null, boxes:[], btnE:null, btnD:null, counter:null,
  init(){
    this.boxAll  = document.getElementById('select-all');
    this.boxes   = Array.from(document.querySelectorAll('.row-check'));
    this.btnE    = document.getElementById('bulk-enable');
    this.btnD    = document.getElementById('bulk-disable');
    this.counter = document.getElementById('sel-counter');

    this.boxAll?.addEventListener('change', ()=>{
      const checked=this.boxAll.checked;
      this.boxes.forEach(b=>{ b.checked=checked; if(checked) this.set.add(b.value); else this.set.delete(b.value); });
      this.sync();
    });
    this.boxes.forEach(b=>{
      b.addEventListener('change', ()=>{
        if (b.checked) this.set.add(b.value); else this.set.delete(b.value);
        this.sync();
      });
    });

    this.btnE?.addEventListener('click', ()=> this.bulkEnableDisable('enable'));
    this.btnD?.addEventListener('click', ()=> this.bulkEnableDisable('disable'));

    this.sync();
  },
  selectedIds(){ return Array.from(this.set).map(v=>parseInt(v,10)).filter(Boolean); },
  sync(){
    const n=this.set.size;
    if (this.counter) this.counter.textContent=`(${n} selected)`;
    const dis=(n===0);
    [this.btnE,this.btnD].forEach(b=>{ if(b) b.disabled=dis; });
    if (this.boxAll){
      if(n===0){ this.boxAll.indeterminate=false; this.boxAll.checked=false; }
      else if(n===this.boxes.length){ this.boxAll.indeterminate=false; this.boxAll.checked=true; }
      else { this.boxAll.indeterminate=true; }
    }
  },
  async bulkEnableDisable(action){
    const ids=this.selectedIds(); if(!ids.length) return;
    const ok = await customConfirm({
      title:(action==='disable')?'Disable selected?':'Enable selected?',
      message:`Are you sure you want to ${action} ${ids.length} client(s)?`,
      okText:(action==='disable')?'Disable':'Enable', cancelText:'Cancel'
    });
    if(!ok) return;
    fetch('../api/bulk_control.php', {
      method:'POST',
      headers:{'Content-Type':'application/json', ...(CSRF? {'X-CSRF-Token': CSRF}: {})},
      body:JSON.stringify({ action, ids })
    }).then(r=>r.json()).then(data=>{
      if(data.status==='success'){
        const msg=`Done: ${data.succeeded}/${data.processed} succeeded` + (data.failed?`, ${data.failed} failed`:``);
        showToast(msg, data.failed?'error':'success', 2800);
        setTimeout(()=> location.reload(), 800);
      } else { showToast(data.message||'Bulk operation failed', 'error', 3500); }
    }).catch(()=> showToast('Bulk request failed', 'error', 3500));
  },
};
document.addEventListener('DOMContentLoaded', ()=> sel.init());

/* ====== Bulk profile change (stub) ====== */
function bp2HandleBulkProfile(){
  showToast('Bulk profile change UI coming soon', 'success', 2000);
}

/* ====== Suggestion dropdown ====== */
(function(){
  const box   = document.getElementById('search-input');
  const group = document.getElementById('search-group');
  if (!box || !group) return;

  const drop = document.createElement('div');
  drop.className = 'suggest-box d-none';
  group.appendChild(drop);

  let active=-1, lastQ='';

  const hide = ()=> drop.classList.add('d-none');
  const show = ()=> drop.classList.remove('d-none');
  const clear= ()=>{ drop.innerHTML=''; active=-1; };

  const esc = s => (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));

  function render(list){
    clear();
    if (!list || !list.length){ hide(); return; }
    list.forEach(r=>{
      const el = document.createElement('div');
      el.className = 'suggest-item';
      el.innerHTML = `
        <div class="suggest-left">
          <span class="suggest-name">${esc(r.name||'Unknown')}</span>
          <span class="suggest-meta">(${esc(r.pppoe_id||'-')})</span>
        </div>
        <div class="suggest-meta">${esc(r.client_code||'')}${r.mobile?' • '+esc(r.mobile):''}</div>`;
      el.addEventListener('click', ()=>{ window.location.href = 'client_view.php?id=' + encodeURIComponent(r.id); });
      drop.appendChild(el);
    });
    show();
  }

  let t=null;
  async function fetchSuggest(q){
    const res = await fetch(`${API_SUGGEST}?q=${encodeURIComponent(q)}`, { credentials:'same-origin', headers: CSRF ? {'X-CSRF-Token': CSRF} : {} });
    if (!res.ok) throw new Error('HTTP '+res.status);
    return res.json();
  }

  function onInput(){
    const q = box.value.trim();
    if (q.length < 2){ clear(); hide(); return; }
    if (q === lastQ) return;
    lastQ = q;
    clearTimeout(t);
    t = setTimeout(async ()=>{
      try{
        const data = await fetchSuggest(q);
        if (box.value.trim() !== q) return;
        render(data.results || data.items || []);
      }catch(e){ clear(); hide(); }
    }, 200);
  }

  function onKey(e){
    if (drop.classList.contains('d-none')) return;
    const nodes = Array.from(drop.querySelectorAll('.suggest-item'));
    if (!nodes.length) return;

    if (e.key==='ArrowDown'){ e.preventDefault(); active=(active+1)%nodes.length; update(nodes); }
    else if (e.key==='ArrowUp'){ e.preventDefault(); active=(active-1+nodes.length)%nodes.length; }
    else if (e.key==='Enter' && active>=0){ e.preventDefault(); nodes[active].click(); }
    else if (e.key==='Escape'){ hide(); }
    update(nodes);
  }
  function update(nodes){
    nodes.forEach(n=>n.classList.remove('active'));
    if (active>=0 && active<nodes.length){
      nodes[active].classList.add('active');
      const el = nodes[active], top=el.offsetTop, bottom=top+el.offsetHeight;
      if (top < drop.scrollTop) drop.scrollTop = top;
      else if (bottom > drop.scrollTop + drop.clientHeight) drop.scrollTop = bottom - drop.clientHeight;
    }
  }

  document.addEventListener('click', e=>{ if (!group.contains(e.target)) hide(); });
  box.addEventListener('input', onInput);
  box.addEventListener('focus', onInput);
  box.addEventListener('keydown', onKey);
})();
</script>

<?php
require __DIR__ . '/../partials/partials_footer.php';
