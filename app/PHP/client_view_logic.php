<?php
// /public/client_view.php
// Client profile + live PPPoE status + actions (enable/disable/kick) + Auto-control trigger + Pay Bill (advance allowed)
// নোট: UI ইংরেজি; শুধু কমেন্ট বাংলায়

declare(strict_types=1);

require_once __DIR__ . '/../require_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../routeros_api.class.php';
require_once __DIR__ . '/../mac_lookup.php';

const ONU_MONITOR_CACHE_TTL = 300;

/* ---------------- Security: CSRF token ---------------- */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function validateClientId($id){ return (is_numeric($id) && (int)$id > 0) ? (int)$id : 0; }
if (!function_exists('col_exists_local')) {
  function col_exists_local(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('onu_numeric')) {
  function onu_numeric(?string $onu): int {
    if ($onu && preg_match('/(\d+)/', $onu, $m)) {
      return (int)$m[1];
    }
    return PHP_INT_MAX;
  }
}
if (!function_exists('first_non_empty')) {
  function first_non_empty(array $row, array $keys) {
    foreach ($keys as $k) {
      if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $row[$k];
    }
    return null;
  }
}
if (!function_exists('fmt_display_dt')) {
  function fmt_display_dt($val): ?string {
    if ($val === null || $val === '') return null;
    $ts = strtotime((string)$val);
    if ($ts) return date('Y-m-d H:i:s', $ts);
    $t = trim((string)$val);
    return $t !== '' ? $t : null;
  }
}

/* ---------------- Input: client id / PPPoE name ---------------- */
$paramId   = trim((string)($_GET['id'] ?? ''));
$paramPpp  = trim((string)($_GET['pppoe_id'] ?? ''));
if ($paramId === '' && $paramPpp === '') { header("Location: /public/clients.php"); exit; }

/* ---------------- DB + schema-awareness ---------------- */
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* (বাংলা) প্যাকেজ/রাউটার টেবিলের নাম কলাম ডাইনামিকলি ঠিক করা */
$pkgCols = $rtCols = [];
try { $pkgCols = $pdo->query("SHOW COLUMNS FROM packages")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch (Throwable $e) {}
try { $rtCols  = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN)  ?: []; } catch (Throwable $e) {}

$pkgNameParts = [];
foreach (['name','title','package_name'] as $c) { if (in_array($c,$pkgCols,true)) $pkgNameParts[] = "p.`$c`"; }
$PKG_NAME_EXPR = $pkgNameParts ? ('COALESCE('.implode(',', $pkgNameParts).')') : 'NULL';

$rtNameParts = [];
foreach (['name','identity','host','ip'] as $c) { if (in_array($c,$rtCols,true)) $rtNameParts[] = "r.`$c`"; }
$ROUTER_NAME_EXPR = $rtNameParts ? ('COALESCE('.implode(',', $rtNameParts).')') : 'r.id';

/* ---------------- Load client (+package +router) ---------------- */
$sqlBase = "SELECT c.*,
                   {$PKG_NAME_EXPR}   AS package_name,
                   {$ROUTER_NAME_EXPR} AS router_name,
                   r.ip AS router_ip, r.username, r.password, r.api_port,
                   o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor
            FROM clients c
            LEFT JOIN packages p ON c.package_id = p.id
            LEFT JOIN routers  r ON c.router_id  = r.id
            LEFT JOIN olts     o ON c.olt_id     = o.id";

$lookups = [];
$seen    = [];
$addLookup = function(string $type, $value) use (&$lookups,&$seen) {
  if ($value === '' || $value === null) return;
  $key = $type . ':' . $value;
  if (isset($seen[$key])) return;
  $seen[$key] = true;
  $lookups[] = [$type, $value];
};

if ($paramPpp !== '') {
  $addLookup('pppoe', $paramPpp);
}
if ($paramId !== '') {
  if (ctype_digit($paramId)) {
    $addLookup('id', (int)$paramId);
    if ($paramPpp === '') {
      $addLookup('pppoe', $paramId);
    }
  } else {
    $addLookup('pppoe', $paramId);
  }
}

$client = null;
foreach ($lookups as [$type, $value]) {
  try{
    $stmt = $pdo->prepare($sqlBase . ($type === 'id' ? " WHERE c.id = ? LIMIT 1" : " WHERE c.pppoe_id = ? LIMIT 1"));
    $stmt->execute([$value]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }catch(Throwable $e){
    $client = null;
  }
  if ($client) break;
}

if (!$client) { header("Location: /public/clients.php"); exit; }

// Normalize common fields so the view always shows data even if schema varies slightly
$addr = first_non_empty($client, ['address','present_address','addr','current_address']);
if ($addr !== null) $client['address'] = $addr;
$client_area = first_non_empty($client, ['area','zone','zone_name']);
if ($client_area !== null) $client['area'] = $client_area;
$client_sub_zone = first_non_empty($client, ['sub_zone','subzone','sub_zone_name']);
if ($client_sub_zone !== null) $client['sub_zone'] = $client_sub_zone;
$client_box = first_non_empty($client, ['box','box_no','box_id']);
if ($client_box !== null) $client['box'] = $client_box;
$expiry = first_non_empty($client, ['expiry_date','expire_date','next_due_date']);
if ($expiry !== null) $client['expiry_date'] = $expiry;
$joinDate = first_non_empty($client, ['join_date','created_at']);
if ($joinDate !== null) $client['join_date'] = $joinDate;
$creator = first_non_empty($client, ['created_by','added_by','user_id']);
if ($creator !== null) $client['created_by'] = $creator;
$mobile = first_non_empty($client, ['mobile','phone','contact','mobile_no','msisdn']);
if ($mobile !== null) $client['mobile'] = $mobile;
if (empty($client['router_name'])) {
  if (!empty($client['router_ip']))      $client['router_name'] = $client['router_ip'];
  elseif (!empty($client['router_id']))  $client['router_name'] = 'Router #'.(int)$client['router_id'];
}
$pkgNameFromClient = $client['package_name'] ?? null;
if (!$pkgNameFromClient && !empty($client['package'])) {
  $client['package_name'] = $client['package'];
}

$client_id = (int)$client['id'];
$pppoe_id  = (string)($client['pppoe_id'] ?? '');

function norm_mac(?string $mac): ?string {
  if(!$mac) return null;
  $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $mac));
  if(strlen($hex)!==12) return null;
  return implode(':', str_split($hex,2));
}

function parse_onu_iface_triplet(?string $iface): array {
  $txt = strtoupper(trim((string)$iface));
  if ($txt === '') return [null,null,null];
  if (preg_match('/(?:EPON|GPON)\s*0*([0-9]+)\s*ONU\s*0*([0-9]+)/', $txt, $m)) {
    $rawPort = (int)$m[1];
    $slot    = ($rawPort >= 10) ? (int)floor($rawPort / 10) : null;
    $port    = ($rawPort >= 10) ? ($rawPort % 10) : $rawPort;
    return [$slot, $port, (int)$m[2]];
  }
  if (preg_match('/(?:EPON|GPON)\s*0*([0-9]+)\/\s*0*([0-9]+)\s*:\s*0*([0-9]+)/', $txt, $m))
    return [(int)$m[1], (int)$m[2], (int)$m[3]];
  if (preg_match('/(?:EPON|GPON)\s*0*([0-9]+)\/\s*0*([0-9]+)/', $txt, $m))
    return [(int)$m[1], (int)$m[2], null];
  if (preg_match('/PON\s*0*([0-9]+)\s*:\s*0*([0-9]+)/', $txt, $m))
    return [null, (int)$m[1], (int)$m[2]];
  if (preg_match('/(\d+)\s*\/\s*(\d+)\s*:\s*(\d+)/', $txt, $m))
    return [(int)$m[1], (int)$m[2], (int)$m[3]];
  if (preg_match('/(\d+)\s*:\s*(\d+)/', $txt, $m))
    return [null, (int)$m[1], (int)$m[2]];
  if (preg_match('/(\d+)/', $txt, $m))
    return [null,null,(int)$m[1]];
  return [null,null,null];
}

function parse_port_hint(?string $text): ?int {
  if(!$text) return null;
  $txt = strtoupper((string)$text);
  if (preg_match('/PON\s*(\d+)/', $txt, $m)) return (int)$m[1];
  if (preg_match('/\/\s*(\d+)/', $txt, $m)) return (int)$m[1];
  if (preg_match('/\b(\d+)\b/', $txt, $m)) return (int)$m[1];
  return null;
}

function fetch_onu_monitor_match(PDO $pdo, array $client, array $macCandidates): ?array {
  $oltId = (int)($client['olt_id'] ?? 0);
  if ($oltId <= 0) return null;
  try{
    $st = $pdo->prepare("SELECT data_json, generated_at FROM onu_monitor_cache WHERE olt_id=? LIMIT 1");
    $st->execute([$oltId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
  }catch(Throwable $e){
    return null;
  }
  if(!$row) return null;
  $ts = strtotime((string)($row['generated_at'] ?? ''));
  if(!$ts || (time()-$ts) > ONU_MONITOR_CACHE_TTL) return null;
  $payload = json_decode($row['data_json'] ?? '', true);
  if(!$payload || !is_array($payload['groups'] ?? null)) return null;

  $macSet = [];
  foreach($macCandidates as $mac => $dummy){
    $norm = norm_mac($mac);
    if($norm) $macSet[$norm] = true;
  }
  if(!empty($client['caller_mac'])){
    $norm = norm_mac($client['caller_mac']);
    if($norm) $macSet[$norm] = true;
  }

  $targetPort = parse_port_hint($client['olt_port'] ?? '');
  $targetOnu  = is_numeric($client['olt_onu'] ?? null) ? (int)$client['olt_onu'] : null;

  $best = null; $bestScore = -1;
  foreach($payload['groups'] as $pon=>$group){
    foreach(($group['list'] ?? []) as $row){
      $iface = (string)($row['iface'] ?? '');
      [$slot,$port,$onu] = parse_onu_iface_triplet($iface);
      $mac = norm_mac($row['mac'] ?? '');
      $score = 0;
      if($mac && isset($macSet[$mac])) $score += 4;
      if($targetOnu !== null && $onu !== null && $onu === $targetOnu) $score += 3;
      if($targetPort !== null && $port !== null && $port === $targetPort) $score += 1;
      if($score <= 0) continue;
      if($score > $bestScore){
        $bestScore = $score;
        $rxVal = null;
        $rawRx = $row['rx'] ?? null;
        if(is_numeric($rawRx)){
          $rxVal = (float)$rawRx;
        } elseif(is_string($rawRx) && preg_match('/-?\d+(?:\.\d+)?/', $rawRx, $m)) {
          $rxVal = (float)$m[0];
        }
        $best = [
          'iface' => $iface ?: null,
          'slot'  => $slot,
          'port'  => $port,
          'onu'   => $onu,
          'mac'   => $mac ?: ($row['mac'] ?? null),
          'rx'    => $rxVal,
          'raw_rx'=> $rawRx,
          'pon_label' => $pon,
        ];
      }
    }
  }
  return $best;
}

function rx_badge_meta($value): array {
  if($value === null || $value === '' || !is_numeric($value)){
    return [null, null];
  }
  $num = (float)$value;
  if($num >= -24 && $num <= -1){
    return ['Good', 'text-bg-success'];
  }
  if($num >= -26 && $num < -24){
    return ['Warn', 'text-bg-warning text-dark'];
  }
  return ['Critical', 'text-bg-danger'];
}

$invRow = null; $cacheRow = null;
$macCandidates = [];
$addMacCandidate = function($mac) use (&$macCandidates){
  if(!$mac) return;
  $norm = norm_mac($mac);
  if($norm){ $macCandidates[$norm] = 1; }
};
$addMacCandidate($client['caller_mac'] ?? null);
$addMacCandidate($client['router_mac'] ?? null);
$addMacCandidate($client['ap_mac'] ?? null);

try{
  $stInv = $pdo->prepare("
    SELECT oi.*, omm.mac AS mapped_mac
    FROM onu_inventory oi
    LEFT JOIN onu_mac_map omm ON omm.target_id = oi.id
    WHERE oi.client_id = ?
    ORDER BY omm.last_seen DESC, oi.last_updated DESC
    LIMIT 1
  ");
  $stInv->execute([(int)$client['id']]);
  $invRow = $stInv->fetch(PDO::FETCH_ASSOC) ?: null;
}catch(Throwable $e){ $invRow = null; }

if(!$invRow && $macCandidates){
  try{
    $in = implode(',', array_fill(0,count($macCandidates),'?'));
    $sql = "
      SELECT oi.*, omm.mac AS mapped_mac
      FROM onu_mac_map omm
      JOIN onu_inventory oi ON oi.id = omm.target_id
      WHERE omm.mac IN ($in)
      ORDER BY omm.last_seen DESC
      LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array_keys($macCandidates));
    $invRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }catch(Throwable $e){}
}

$binding = [
  'olt_id' => (int)($client['olt_id'] ?? 0),
  'port'   => $client['olt_port'] ?? null,
  'onu'    => ($client['olt_onu'] ?? null) !== null ? onu_numeric((string)$client['olt_onu']) : null,
  'linked_at' => $client['last_linked_at'] ?? null,
  'mac'    => null,
  'source' => 'client',
];
if($invRow){
  if(!empty($invRow['olt_id'])){ $binding['olt_id'] = (int)$invRow['olt_id']; $binding['source'] = 'onu_inventory'; }
  if(!empty($invRow['iface'])) { $binding['port'] = $invRow['iface']; $binding['source'] = 'onu_inventory'; }
  if(isset($invRow['onu_id']) && $invRow['onu_id'] !== '') { $binding['onu'] = onu_numeric((string)$invRow['onu_id']); $binding['source'] = 'onu_inventory'; }
  if(!empty($invRow['last_updated'])){ $binding['linked_at'] = $invRow['last_updated']; }
  if(!empty($invRow['mapped_mac'])){
    $m = norm_mac($invRow['mapped_mac']) ?: $invRow['mapped_mac'];
    $binding['mac'] = $m;
  }
}
$preferredOltId = $binding['olt_id'] ?? 0;
if(!empty($binding['mac'])) $macCandidates[$binding['mac']] = 1;

if($macCandidates){
  try{
    $in = implode(',', array_fill(0,count($macCandidates),'?'));
    $sql = "SELECT * FROM olt_mac_cache WHERE mac IN ($in)";
    $params = array_keys($macCandidates);
    if($preferredOltId > 0){
      $sql .= " AND olt_id = ?";
      $params[] = $preferredOltId;
    }
    $sql .= " ORDER BY learned_at DESC LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $cacheRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if(!$cacheRow && $preferredOltId > 0){
      // fallback without olt_id filter if no match found
      $sql = "SELECT * FROM olt_mac_cache WHERE mac IN ($in) ORDER BY learned_at DESC LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute(array_keys($macCandidates));
      $cacheRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
  }catch(Throwable $e){}
}
if(!$cacheRow && $preferredOltId > 0 && isset($binding['onu']) && $binding['onu'] !== ''){
  try{
    $st = $pdo->prepare("SELECT * FROM olt_mac_cache WHERE olt_id=? AND onu=? ORDER BY learned_at DESC LIMIT 1");
    $st->execute([(int)$preferredOltId, (string)$binding['onu']]);
    $cacheRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }catch(Throwable $e){}
}
if(!$cacheRow && $macCandidates){
  try{
    $in = implode(',', array_fill(0,count($macCandidates),'?'));
    $sql = "SELECT * FROM olt_onu_client_macs WHERE mac IN ($in)";
    $params = array_keys($macCandidates);
    if($preferredOltId > 0){
      $sql .= " AND olt_id = ?";
      $params[] = $preferredOltId;
    }
    $sql .= " ORDER BY learned_at DESC LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if(!$row && $preferredOltId > 0){
      $sql = "SELECT * FROM olt_onu_client_macs WHERE mac IN ($in) ORDER BY learned_at DESC LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute(array_keys($macCandidates));
      $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if($row){
      $family = strtoupper((string)($row['family'] ?? 'EPON'));
      $slot   = (int)($row['slot'] ?? 0);
      $iface = trim(sprintf('%s 0/%02d', $family, $slot));
      $cacheRow = [
        'olt_id' => $row['olt_id'] ?? null,
        'port'   => $iface,
        'onu'    => $row['onu'] ?? null,
        'mac'    => $row['mac'] ?? null,
        'learned_at' => $row['learned_at'] ?? null,
      ];
    }
  }catch(Throwable $e){}
}

if($cacheRow){
  $cacheOlt = (int)($cacheRow['olt_id'] ?? 0);
  $cacheOnu = onu_numeric($cacheRow['onu'] ?? '');
  $matchesOlt = ($preferredOltId <= 0) || !$cacheOlt || $cacheOlt === $preferredOltId;
  $matchesOnu = ($binding['onu'] === null) || $cacheOnu === PHP_INT_MAX || (int)$binding['onu'] === $cacheOnu;
  if($matchesOlt && $matchesOnu){
    if($cacheOlt){ $binding['olt_id'] = $cacheOlt; $preferredOltId = $cacheOlt; }
    if(!empty($cacheRow['port'])) $binding['port'] = $cacheRow['port'];
    if($cacheOnu !== PHP_INT_MAX) $binding['onu'] = $cacheOnu;
    if(!empty($cacheRow['mac'])){
      $binding['mac'] = norm_mac($cacheRow['mac']) ?: $cacheRow['mac'];
      $macCandidates[$binding['mac']] = 1;
    }
    if(!empty($cacheRow['learned_at'])) $binding['linked_at'] = $cacheRow['learned_at'];
    if($binding['source'] !== 'onu_inventory') $binding['source'] = 'olt_mac_cache';
  }
} elseif($invRow){
  if($binding['linked_at'] === null && !empty($invRow['last_updated'])) $binding['linked_at'] = $invRow['last_updated'];
}

$client['olt_id'] = $binding['olt_id'] ?: $client['olt_id'];
if(!empty($binding['port'])) $client['olt_port'] = $binding['port'];
if($binding['onu'] !== null && $binding['onu'] !== PHP_INT_MAX) $client['olt_onu'] = $binding['onu'];
if(!empty($binding['linked_at'])) $client['last_linked_at'] = $binding['linked_at'];
if(!empty($binding['mac'])) $macCandidates[$binding['mac']] = 1;

$monitorMatch = fetch_onu_monitor_match($pdo, $client, $macCandidates);
$monitor_iface = null;
$monitor_onu = null;
$monitor_rx = null;
if($monitorMatch){
  $monitor_iface = $monitorMatch['iface'] ?? null;
  $monitor_onu   = $monitorMatch['onu'] ?? null;
  $monitor_rx    = $monitorMatch['rx'] ?? null;
  if($monitor_iface){ $client['olt_port'] = $monitor_iface; $binding['port'] = $monitor_iface; }
  if($monitor_onu !== null){   $client['olt_onu']  = $monitor_onu; $binding['onu'] = $monitor_onu; }
  if(!empty($monitorMatch['mac'])){
    $normMon = norm_mac($monitorMatch['mac']);
    $onu_mac = $normMon ?: $monitorMatch['mac'];
    if($normMon) $macCandidates[$normMon] = 1;
    elseif(is_string($monitorMatch['mac'])) $macCandidates[$monitorMatch['mac']] = 1;
    if($binding['mac'] === null) $binding['mac'] = $onu_mac;
  }
}

/* (বাংলা) ইনফার্ড OLT আইডি থাকলে, সঠিক OLT তথ্য আবার লোড করি যেন UI তে নাম/হোস্ট/vendor মেলে */
if (!empty($client['olt_id'])) {
  $client['olt_id'] = (int)$client['olt_id'];
  try{
    $stOlt = $pdo->prepare("SELECT name, host, vendor FROM olts WHERE id=? LIMIT 1");
    $stOlt->execute([$client['olt_id']]);
    $oltRow = $stOlt->fetch(PDO::FETCH_ASSOC);
    if($oltRow){
      $client['olt_name']   = $oltRow['name']   ?? $client['olt_name']   ?? null;
      $client['olt_host']   = $oltRow['host']   ?? $client['olt_host']   ?? null;
      $client['olt_vendor'] = $oltRow['vendor'] ?? $client['olt_vendor'] ?? null;
    }
  }catch(Throwable $e){
    // ignore lookup failure
  }
}

/* ---------------- Photo helpers ---------------- */
$photo_url      = trim($client['photo_url'] ?? '');
$client_initial = mb_strtoupper(mb_substr($client['name'] ?? '?', 0, 1, 'UTF-8'));
$m              = trim($client['mobile'] ?? '');

/* ---------------- Live placeholders (AJAX will fill) ---------------- */
$live_ip = first_non_empty($client, ['ip_address','ip','ipv4','client_ip','last_ip']);
if ($live_ip === null || $live_ip === '') $live_ip = '—';
$uptime_raw = first_non_empty($client, ['uptime','session_uptime']);
if (!$uptime_raw && !empty($client['last_sync_time'])) $uptime_raw = $client['last_sync_time'];
$uptime_display = $uptime_raw ? fmt_display_dt($uptime_raw) ?? (string)$uptime_raw : '—';
$last_seen_raw = first_non_empty($client, ['last_logout_at','last_sync_time','last_seen']);
$last_seen = fmt_display_dt($last_seen_raw) ?? '—';
$is_online = false;
if (isset($client['is_online'])) {
  $v = $client['is_online'];
  $is_online = is_numeric($v) ? ((int)$v === 1) : in_array(strtolower((string)$v), ['yes','true','online','active'], true);
}
// Stored traffic usage fallback (client_traffic_log)
$data_dl = $data_ul = null;
$data_usage_asof = null;
try{
  $st = $pdo->prepare("SELECT total_download_gb, total_upload_gb, log_time FROM client_traffic_log WHERE client_id=? ORDER BY log_time DESC LIMIT 1");
  $st->execute([$client_id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if($row){
    $data_dl = is_numeric($row['total_download_gb']) ? (float)$row['total_download_gb'] : null;
    $data_ul = is_numeric($row['total_upload_gb']) ? (float)$row['total_upload_gb'] : null;
    $data_usage_asof = $row['log_time'] ?? null;
  }
}catch(Throwable $e){}
$data_dl_text = ($data_dl !== null) ? (number_format($data_dl, 3).' GB') : '—';
$data_ul_text = ($data_ul !== null) ? (number_format($data_ul, 3).' GB') : '—';
if ($uptime_display === '—' && $data_usage_asof) {
  // যদি লাইভ আপটাইম না থাকে, শেষ ট্রাফিক লগের সময় দেখাই
  $uptime_display = 'Last log: ' . (fmt_display_dt($data_usage_asof) ?? $data_usage_asof);
}
$rx_prefill = ($invRow && $invRow['last_rx_dbm'] !== null) ? (float)$invRow['last_rx_dbm'] : null;
if($monitor_rx !== null){
  $rx_prefill = (float)$monitor_rx;
}
$rx_from_cache = null;
if(is_array($cacheRow)){
  $rx_from_cache = $cacheRow['rx_power_dbm'] ?? ($cacheRow['rx_power'] ?? ($cacheRow['rx'] ?? null));
}
if($rx_prefill === null && $rx_from_cache !== null){
  if(is_numeric($rx_from_cache)){
    $rx_prefill = (float)$rx_from_cache;
  } elseif(is_string($rx_from_cache) && preg_match('/-?\d+(?:\.\d+)?/', $rx_from_cache, $m)) {
    $rx_prefill = (float)$m[0];
  }
}
$rx_prefill = $rx_prefill ?? (is_numeric($client['rx_power'] ?? null) ? (float)$client['rx_power'] : null);
$olt_linked = !empty($client['olt_id']);
$olt_name   = $client['olt_name'] ?? null;
$olt_host   = $client['olt_host'] ?? null;
$olt_vendor = $client['olt_vendor'] ?? null;
$onu_mac    = null;
$onu_mac    = $onu_mac ?? ($binding['mac'] ?? ($invRow['mapped_mac'] ?? ($cacheRow['mac'] ?? null)));
$pon_iface_raw  = $binding['port'] ?? ($monitor_iface ?? ($cacheRow['port'] ?? ($invRow['iface'] ?? ($client['olt_port'] ?? null))));
$pon_iface  = $pon_iface_raw;
$pon_display = null;
$pon_compact = null;
$pon_port_display = null;
if ($pon_iface_raw) {
  $pon_display = $pon_iface_raw;
  $upperIface = strtoupper($pon_iface_raw);
  if (strpos($upperIface, 'EPON') === false && strpos($upperIface, 'GPON') === false) {
    $family = !empty($invRow['family']) ? strtoupper((string)$invRow['family']) : null;
    if ($family) {
      if (strpos($pon_iface_raw, '/') !== false) {
        $seg = $pon_iface_raw;
        if (strpos($seg, '0/') !== 0) $seg = '0/'.$seg;
        $pon_display = $family.$seg;
      } else {
        $pon_display = $family.'0/'.$pon_iface_raw;
      }
    }
  }
  $pon_compact = strtoupper(str_replace(' ', '', $pon_display ?? $pon_iface_raw));
  $srcForPort = $pon_display ?? $pon_iface_raw;
  if (preg_match('/\/(\d+)(?::\d+)?$/', $srcForPort, $mm)) {
    $pon_port_display = 'PON'.$mm[1];
  } elseif (preg_match('/PON\s*(\d+)/i', $srcForPort, $mm)) {
    $pon_port_display = 'PON'.$mm[1];
  }
}
$onu_id_display = ($monitor_onu !== null ? $monitor_onu : null);
if ($onu_id_display === null) {
  $onu_id_display = $binding['onu'] ?? ($cacheRow['onu'] ?? ($invRow['onu_id'] ?? ($client['olt_onu'] ?? null)));
}
$onu_full = null;
if ($onu_id_display !== null && $onu_id_display !== '') {
  $base = $pon_compact ?: ($pon_iface_raw ? strtoupper(str_replace(' ', '', $pon_iface_raw)) : null);
  if ($base) {
    $onu_full = $base . ':' . $onu_id_display;
  } else {
    $onu_full = $onu_id_display;
  }
}
$last_linked_display = $binding['linked_at'] ?? ($cacheRow['learned_at'] ?? ($invRow['last_updated'] ?? ($client['last_linked_at'] ?? null)));
$initialOltBinding = [
  'olt_id'  => $binding['olt_id'] ?: null,
  'port'    => $binding['port'] ?? null,
  'onu'     => $binding['onu'] ?? null,
  'mac'     => $binding['mac'] ?? null,
  'learned_at' => $last_linked_display,
  'source'  => $binding['source'] ?? null,
  'name'    => $olt_name,
  'host'    => $olt_host,
  'vendor'  => $olt_vendor,
];

if (!empty($client['olt_id']) && !empty($client['olt_port']) && !empty($client['olt_onu'])) {
  try{
    if($rx_prefill === null){
      $stRx = $pdo->prepare("SELECT last_rx_dbm FROM onu_inventory WHERE olt_id=? AND iface=? AND onu_id=? ORDER BY last_updated DESC LIMIT 1");
      $stRx->execute([(int)$client['olt_id'], trim((string)$client['olt_port']), (int)$client['olt_onu']]);
      $rx_prefill = $stRx->fetchColumn();
      if ($rx_prefill !== false && $rx_prefill !== null) {
        $rx_prefill = (float)$rx_prefill;
      } else {
        $rx_prefill = null;
      }
    }
  }catch(Throwable $e){
    $rx_prefill = null;
  }

  try{
    if(!$onu_mac){
      $stMac = $pdo->prepare("
        SELECT omm.mac
        FROM onu_inventory oi
        LEFT JOIN onu_mac_map omm ON omm.target_id = oi.id
        WHERE oi.client_id = ?
        ORDER BY omm.last_seen DESC
        LIMIT 1
      ");
      $stMac->execute([(int)$client['id']]);
      $onu_mac = $stMac->fetchColumn() ?: null;
    }
  }catch(Throwable $e){
    $onu_mac = null;
  }
}
$rx_prefill_meta = rx_badge_meta($rx_prefill);
$initialOltBinding['rx_power_dbm'] = $rx_prefill;
$onu_mac = $onu_mac ?: ($client['caller_mac'] ?? null);
$router_mac_raw = $client['router_mac']
               ?? ($binding['mac'] ?? null)
               ?? ($onu_mac ?: null)
               ?? ($client['caller_mac'] ?? null)
               ?? ($client['ap_mac'] ?? null)
               ?? ($client['last_seen_mac'] ?? null)
               ?? ($client['onu_mac'] ?? null);
$router_mac_display = norm_mac($router_mac_raw);
if(!$router_mac_display && $router_mac_raw){
  $router_mac_display = trim((string)$router_mac_raw);
}
if(!$router_mac_display && $onu_mac){
  $router_mac_display = norm_mac($onu_mac) ?: trim((string)$onu_mac);
}
$device_vendor = null;
if ($router_mac_display && function_exists('mac_vendor_lookup')) {
  $device_vendor = mac_vendor_lookup($router_mac_display);
}
if ($router_mac_display && (!$device_vendor || $device_vendor === 'Unknown Vendor')) {
  try {
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $router_mac_display));
    $prefix = substr($hex, 0, 6);
    if ($prefix && $pdo->query("SHOW TABLES LIKE 'mac_vendors'")->fetchColumn()) {
      $stv = $pdo->prepare("SELECT vendor FROM mac_vendors WHERE mac_prefix=? LIMIT 1");
      $stv->execute([$prefix]);
      $v = $stv->fetchColumn();
      if ($v) $device_vendor = $v;
    }
  } catch (Throwable $e) {
    // ignore vendor lookup errors
  }
}

/* ---------------- MikroTik secret password + status (best-effort) ---------------- */
$mk_secret_pass = '';
$mk_secret_found = false;
$mk_secret_disabled = null;
try {
  $rt_ip   = trim((string)($client['router_ip'] ?? ''));
  $rt_user = trim((string)($client['username'] ?? ''));
  $rt_pass = (string)($client['password'] ?? '');
  $rt_port = (int)($client['api_port'] ?? 8728) ?: 8728;
  if ($rt_ip !== '' && $rt_user !== '' && $rt_pass !== '' && $pppoe_id !== '') {
    $API = new RouterosAPI();
    $API->debug = false;
    if ($API->connect($rt_ip, $rt_user, $rt_pass, $rt_port)) {
      $secret = $API->comm('/ppp/secret/print', ['?name'=>$pppoe_id, '.proplist'=>'password,disabled']);
      if (is_array($secret) && isset($secret[0])) {
        $mk_secret_found = true;
        if (isset($secret[0]['password'])) {
          $mk_secret_pass = (string)$secret[0]['password'];
        }
        if (array_key_exists('disabled', $secret[0])) {
          $val = strtolower(trim((string)$secret[0]['disabled']));
          $mk_secret_disabled = in_array($val, ['true','yes','1','on'], true);
        }
      }
      $API->disconnect();
    }
  }
} catch (Throwable $e) {
  // ignore MikroTik fetch errors
}

/* ---------------- Status badges (with Left) ---------------- */
$stVal  = strtolower(trim($client['status'] ?? 'active'));
$isLeft = (int)($client['is_left'] ?? 0) === 1;
if ($mk_secret_found && $mk_secret_disabled !== null) {
  $stVal = $mk_secret_disabled ? 'inactive' : 'active';
}

if ($isLeft) { $badge='bg-dark'; $stLabel='Left'; }
elseif (in_array($stVal, ['inactive','deactive','disabled','expired','blocked'], true)) { $badge='bg-danger';  $stLabel='Inactive'; }
elseif (in_array($stVal, ['pending','hold'], true)) { $badge='bg-warning text-dark'; $stLabel='Pending';  }
else { $badge='bg-success'; $stLabel='Active'; }

/* ---------------- Ledger badge color ---------------- */
$ledger = (float)($client['ledger_balance'] ?? 0);
if ($ledger < 0) { $ledgerClass='bg-danger';  $ledgerText='Due'; }
elseif ($ledger > 0){ $ledgerClass='bg-success'; $ledgerText='Advance'; }
else { $ledgerClass='bg-secondary'; $ledgerText='Clear'; }

/* ---------------- Invoice balance (match invoices.php) ---------------- */
$invoice_balance = 0.0;
try {
  $invAmountCol = col_exists_local($pdo,'invoices','payable') ? 'payable'
               : (col_exists_local($pdo,'invoices','net_amount') ? 'net_amount'
               : (col_exists_local($pdo,'invoices','amount') ? 'amount'
               : (col_exists_local($pdo,'invoices','total') ? 'total' : 'total')));
  $payFk = col_exists_local($pdo,'payments','invoice_id') ? 'invoice_id'
         : (col_exists_local($pdo,'payments','bill_id') ? 'bill_id' : null);
  $hasPayDiscount = col_exists_local($pdo,'payments','discount');
  $payNetExpr = $hasPayDiscount
    ? "COALESCE(SUM(pm.amount - COALESCE(pm.discount,0)),0)"
    : "COALESCE(SUM(pm.amount),0)";
  $paidExpr = $payFk ? "(SELECT $payNetExpr FROM payments pm WHERE pm.`$payFk`=i.id)" : "0";
  $invWhere = [];
  if (col_exists_local($pdo,'invoices','is_void')) $invWhere[] = "i.is_void=0";
  if (col_exists_local($pdo,'invoices','is_deleted')) $invWhere[] = "i.is_deleted=0";
  if (col_exists_local($pdo,'invoices','deleted_at')) $invWhere[] = "i.deleted_at IS NULL";
  if (col_exists_local($pdo,'invoices','status')) $invWhere[] = "COALESCE(i.status,'') NOT IN ('void','deleted','cancelled','canceled')";
  $whereSql = $invWhere ? (' AND '.implode(' AND ',$invWhere)) : '';
  $st = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(0, COALESCE(i.`$invAmountCol`,0) - $paidExpr)),0) FROM invoices i WHERE i.client_id=?".$whereSql);
  $st->execute([$client_id]);
  $invoice_balance = (float)$st->fetchColumn();
} catch (Throwable $e) {
  $invoice_balance = 0.0;
}
$display_balance = $invoice_balance;
$displayClass = $display_balance > 0 ? 'bg-danger' : 'bg-secondary';
$displayText  = $display_balance > 0 ? 'Due' : 'Clear';

/* ---------------- Payment link (schema-aware invoice lookup + advance fallback) ---------------- */
/* বাংলা: return URL সবসময় relative path রাখব—Host header এর উপর ভরসা নয় */
$current_path = $_SERVER['REQUEST_URI'] ?? ('/public/client_view.php?id='.$pppoe_id);

$payInvoiceId = 0;
try{
  $invCols = $pdo->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch(Throwable $e){ $invCols = []; }

/* বাংলা: ১) current month unpaid/partial/due খুঁজি ২) না পেলে latest unpaid/partial  */
if (in_array('billing_month',$invCols,true)) {
  $ms = date('Y-m-01'); $me = date('Y-m-t');
  $q1 = $pdo->prepare("SELECT id FROM invoices 
                       WHERE client_id=? AND billing_month BETWEEN ? AND ?
                         AND LOWER(status) IN ('unpaid','partial','due','partially_paid')
                       ORDER BY id DESC LIMIT 1");
  $q1->execute([$client_id, $ms, $me]);
  $payInvoiceId = (int)($q1->fetchColumn() ?: 0);
} elseif (in_array('month',$invCols,true) && in_array('year',$invCols,true)) {
  $q1 = $pdo->prepare("SELECT id FROM invoices
                       WHERE client_id=? AND month=? AND year=? 
                         AND LOWER(status) IN ('unpaid','partial','due','partially_paid')
                       ORDER BY id DESC LIMIT 1");
  $q1->execute([$client_id, (int)date('n'), (int)date('Y')]);
  $payInvoiceId = (int)($q1->fetchColumn() ?: 0);
}
if ($payInvoiceId <= 0){
  $q2 = $pdo->prepare("SELECT id FROM invoices
                       WHERE client_id=? AND LOWER(status) IN ('unpaid','partial','due','partially_paid')
                       ORDER BY id DESC LIMIT 1");
  $q2->execute([$client_id]);
  $payInvoiceId = (int)($q2->fetchColumn() ?: 0);
}

/* নিরাপদ চেক: ইনভয়েস সত্যিই client_id এর সাথে মেলে কিনা */
$validInvoiceId = 0;
if ($payInvoiceId > 0){
  $chk = $pdo->prepare("SELECT id FROM invoices WHERE id=? AND client_id=? LIMIT 1");
  $chk->execute([$payInvoiceId, $client_id]);
  $validInvoiceId = (int)($chk->fetchColumn() ?: 0);
}

/* link: সব সময় client_id; invoice থাকলে invoice_id যোগ, না থাকলে advance ফ্লো */
$pay_url = '/public/payment_add.php?client_id='.(int)$client_id
         . ($validInvoiceId>0 ? '&invoice_id='.$validInvoiceId : '&purpose=advance')
         . '&return='.urlencode($current_path);

/* ---------------- Header ---------------- */
$page_title = 'Client View';
