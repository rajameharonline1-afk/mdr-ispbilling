<?php
// /public/client_view.php
// Client profile + live PPPoE status + actions (enable/disable/kick) + Auto-control trigger + Pay Bill (advance allowed)
// নোট: UI ইংরেজি; শুধু কমেন্ট বাংলায়

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';
require_once __DIR__ . '/../app/mac_lookup.php';

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
$live_ip   = '—';
$last_seen = '—';
$is_online = false;
$rx_prefill = ($invRow && $invRow['last_rx_dbm'] !== null) ? (float)$invRow['last_rx_dbm'] : null;
if($monitor_rx !== null){
  $rx_prefill = (float)$monitor_rx;
}
$rx_prefill_meta = rx_badge_meta($rx_prefill);
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
$onu_mac = $onu_mac ?: ($client['caller_mac'] ?? null);
$router_mac_display = norm_mac($client['router_mac'] ?? ($client['caller_mac'] ?? ($client['ap_mac'] ?? ($client['onu_mac'] ?? null))));
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
include __DIR__ . '/../partials/partials_header.php';
?>
<!-- CSRF for JS -->
<meta name="csrf-token" content="<?= h($csrf) ?>">

<style>
/* ==== Light theme ==== */
.card-block{ border:1px solid #e5e7eb; border-radius:.75rem; background:#ffffff; }
.card-block .card-title{ font-weight:700; padding:.65rem .9rem; border-bottom:1px solid #eef1f4; background:#f8f9fa; }

/* Avatar */
.header-avatar{ width:70px; height:70px; border-radius:15%; overflow:hidden; border:1px solid #e5e7eb; background:#f2f4f7; }
.header-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
.header-avatar .avatar-fallback{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#5c6b7a; }

.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

/* Key/Value table */
.table-kv{ --pad-y:.45rem; --pad-x:.6rem; width:100%; table-layout:fixed; }
.table-kv td{ padding: var(--pad-y) var(--pad-x); vertical-align: middle; }
.table-kv td.k, .table-kv td.v{ white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
.table-kv i.bi{ color:#0d6efd; margin-right:.4rem; vertical-align:-.05rem; }
.table-kv colgroup col:first-child{ width: 42%; }
.table-kv colgroup col:last-child { width: 58%; }

.table-responsive{ border-radius:.5rem; }

/* Small buttons */
.kv-actions .btn, .table-kv .btn{ padding:.2rem .5rem; font-size:.8rem; }

/* Actions / modal */
.card-actions{ background:#f5f6f8; border-top:1px solid #e5e7eb; padding:.65rem .9rem; }
#renewModal .modal-content{ background:#f5f6f8; border:1px solid #e5e7eb; }
#renewModal .modal-header{ background:#f8f9fa; border-bottom-color:#e5e7eb; }
#renewModal .modal-footer{ background:#eef1f4; border-top-color:#e5e7eb; }

/* Toast + Confirm (ARIA friendly) */
.app-confirm-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center; z-index:2000; }
.app-confirm-box{ background:#fff; border-radius:12px; width:360px; max-width:92vw; padding:18px; box-shadow:0 10px 30px rgba(0,0,0,.25); }
.app-confirm-title{ font-size:16px; font-weight:600; margin:0 0 6px; }
.app-confirm-text{ font-size:14px; color:#333; margin:0 0 14px; }
.app-confirm-actions{ display:flex; gap:8px; justify-content:center; }
.app-btn{ border:0; border-radius:8px; padding:8px 12px; font-size:14px; cursor:pointer; min-width:110px; }
.app-btn.secondary{ background:#e9ecef; } .app-btn.primary{ background:#0d6efd; color:#fff; }
@media (max-width:480px){ .app-confirm-actions{ flex-direction: column; } .app-btn{ width:100%; } }

.app-toast{ position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); z-index:2100; min-width:280px; max-width:90vw; text-align:center; padding:14px 18px; border-radius:12px; color:#fff; background:#0d6efd; box-shadow:0 16px 40px rgba(0,0,0,.25); }
.app-toast[role="status"]{ aria-live:polite; }
.app-toast.success{ background:#198754 !important; }
.app-toast.error{ background:#dc3545 !important; }
.app-toast.hide{ opacity:0; transform:translate(-50%,-60%); transition:opacity .25s, transform .25s; }
</style>

<div class="container-fluid py-3 text-start">

  <!-- Header -->
  <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="header-avatar">
        <?php if ($photo_url): ?>
          <img src="<?= h($photo_url) ?>" referrerpolicy="no-referrer" alt="<?= h($client['name'] ?? 'Photo') ?>">
        <?php else: ?>
          <div class="avatar-fallback"><?= h($client_initial) ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-column">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person-vcard"></i>
          <!-- <span class="fw-bold">Details:<?= h($client['name']) ?></span> -->
          <span class="fw-bold">Customer Information</span>
          
        </div>
      </div>
    </div>

    <div class="ms-auto d-flex flex-wrap gap-2">
      <a href="/public/clients.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
      <a href="/public/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Edit Info</a>
      <a href="/public/audit_logs.php?client_id=<?= (int)$client['id'] ?>" class="btn btn-outline-dark btn-sm" target="_blank" rel="noopener"><i class="bi bi-clock-history"></i> Logs</a>
      <?php if (!$isLeft): ?>
        <?php if ($stVal === 'active'): ?>
          <button class="btn btn-danger btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>, 'disable')"><i class="bi bi-x-square"></i> Disable</button>
        <?php else: ?>
          <button class="btn btn-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>, 'enable')"><i class="bi bi-file-check"></i> Enable</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isLeft): ?>
    <div class="alert alert-dark d-flex align-items-center" role="alert">
      <i class="bi bi-person-dash me-2"></i>
      This client is marked as <strong class="ms-1">Left</strong>. Router actions are disabled.
    </div>
  <?php endif; ?>

  <div class="row g-3">

    <!-- Account Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title">Account Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr><td class="k"><i class="bi bi-upc-scan"></i> Client ID</td><td class="v mono"><?= h($client['id'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-person"></i> Name</td><td class="v"><?= h($client['name'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-geo-alt"></i> Address</td><td class="v"><?= nl2br(h($client['address'] ?: '-')) ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-telephone"></i> Mobile No.</td>
                <td class="v">
                  <?php if ($m): ?>
                    <span class="me-1"><?= h($m) ?></span><br>
                    <a class="btn btn-outline-secondary btn-sm me-1" href="tel:+88<?= h($m) ?>" title="Call"><i class="bi bi-telephone"></i></a>
                    <a class="btn btn-outline-secondary btn-sm me-1" href="sms:+88<?= h($m) ?>" title="SMS"><i class="bi bi-chat-dots"></i></a>
                    <a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/+88<?= preg_replace('/\D/','',$m) ?>" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                  <?php else: ?>-<?php endif; ?>
                </td>
              </tr>
              <tr><td class="k"><i class="bi bi-envelope"></i> Email</td><td class="v"><?= h($client['email'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-card-list"></i> NID No.</td><td class="v"><?= h($client['nid'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-card-list"></i> DOB</td><td class="v"><?= h($client['dob'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Area</td><td class="v"><?= h($client['area'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Sub Zone</td><td class="v"><?= h($client['sub_zone'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-box2"></i> Box</td><td class="v"><?= h($client['box'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-map"></i> Join Date</td><td class="v"><?= h($client['join_date'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-calendar2-week"></i> Update</td><td class="v"><?= h($client['updated_at'] ?? '-') ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <a href="/public/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Edit Info</a>
          <?php if ($m): ?><a href="sms:<?= h($m) ?>" class="btn btn-success btn-sm"><i class="bi bi-envelope"></i> Send SMS</a><?php endif; ?>
          <a href="/public/audit_logs.php?client_id=<?= (int)$client['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-dark btn-sm"><i class="bi bi-clock-history"></i> Logs</a>
        </div>
      </div>
    </div>

    <!-- Billing Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title d-flex justify-content-between align-items-center">Billing Information<span class="badge <?= $badge ?>"><?= $stLabel ?></span></div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr><td class="k"><i class="bi bi-diagram-3"></i>Conn. Type</td><td class="v mono"><?= strtoupper($client['connection_type'] ?? 'PPPOE') ?></td></tr>
              <tr><td class="k"><i class="bi bi-box2-fill"></i>Package</td><td class="v fw-bold"><?= h($client['package_name'] ?: 'N/A') ?></td></tr>
              <tr><td class="k"><i class="bi bi-cash-coin"></i>Packg Price</td><td class="v"><?= h($client['monthly_bill'] ?: '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-arrow-repeat"></i>Bill Cycle</td><td class="v">Monthly</td></tr>
              <tr><td class="k"><i class="bi bi-ui-checks"></i>Bill Type</td><td class="v">Prepaid</td></tr>
              <tr>
                <td class="k"><i class="bi bi-flag"></i> Bill Status</td>
                <td class="v">
                  <?php $expired = !empty($client['expiry_date']) && (strtotime($client['expiry_date']) < time()); ?>
                  <?php if ($expired): ?><span class="text-danger fw-bold">Expired</span>
                  <?php else: ?><span class="text-success">Running</span><?php endif; ?>
                  <span class="ms-3"><i class="bi bi-circle-fill <?= ($stVal==='active')?'text-success':'text-danger' ?>"></i> <span class="ms-1"><?= ($stVal==='active')? 'Enabled':'Disabled' ?></span></span>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-calendar-date"></i> Expiry Date</td>
                <td class="v">
                  <span><?= h($client['expiry_date'] ?? '-') ?></span>
                  <button class="btn btn-outline-secondary btn-sm ms-1" title="Calendar"><i class="bi bi-calendar3"></i></button>
                </td>
              </tr>
              <tr><td class="k"><i class="bi bi-wallet2"></i> Balance</td><td class="v"><span class="badge <?= $displayClass ?>"><?= number_format($display_balance,2) ?> (<?= $displayText ?>)</span></td></tr>
              <tr><td class="k"><i class="bi bi-person-check"></i>Connect By</td><td class="v"><?= h($client['created_by'] ?? '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-geo"></i> Location</td><td class="v"><?= h($client['area'] ?: '-') ?></td></tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <!-- Pay Bill: always enabled -->
          <a href="<?= h($pay_url) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-cash-coin"></i> Pay Bill
          </a>

          <!-- Renew (invoice create) -->
          <button id="btnRenew" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#renewModal" title="Create Invoice">
            <i class="bi bi-receipt"></i> Create Invoice
          </button>

          <a href="/public/client_invoices.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-receipt"></i> Invoices</a>
          <a href="/public/client_payments.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-cash-coin"></i> Payments</a>

          <?php if (!$isLeft): ?>
            <?php if ($stVal==='active'): ?>
              <button class="btn btn-outline-danger btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'disable')"><i class="bi bi-x-octagon"></i> Disable</button>
              <button class="btn btn-outline-warning btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'kick')"><i class="bi bi-plug"></i> Disconnect</button>
            <?php else: ?>
              <button class="btn btn-outline-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'enable')"><i class="bi bi-check2-circle"></i> Enable</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Server Information -->
    <div class="col-12 col-lg-4">
      <div class="card-block h-100">
        <div class="card-title">Server Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm align-middle mb-0 table-kv table-borderless">
            <colgroup><col><col></colgroup>
            <tbody>
              <tr>
                <td class="k"><i class="bi bi-hdd-network"></i> Server</td>
                <td class="v"><?= h($client['router_name'] ?: '-') ?></td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-person-badge"></i> Username</td>
                <td class="v mono">
                  <span id="pppoe-username"><?= h($client['pppoe_id'] ?: '-') ?></span>
                  <button type="button"
                          class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#pppoe-username"
                          title="Copy Username">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-key"></i> Password</td>
                <td class="v mono">
                  <?php $pp = $mk_secret_pass ?: ($client['pppoe_pass'] ?? ($client['pppoe_password'] ?? ($client['ppp_pass'] ?? ''))); // (বাংলা) স্কিমা ভিন্নতা গার্ড ?>
                  <span id="ppp-mask" data-revealed="0"><?= $pp ? str_repeat('•', max(6, strlen($pp))) : '-' ?></span>
                  <?php if ($pp): ?>
                    <button class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                            data-copy="<?= h($pp) ?>" title="Copy">
                      <i class="bi bi-clipboard"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ms-1" id="ppp-eye" title="Show/Hide">
                      <i class="bi bi-eye"></i>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
              <tr>
                <td class="k"><i class="bi bi-ethernet"></i>Router Mac</td>
                <td class="v mono">
                  <span id="router-mac"><?= $router_mac_display ? h($router_mac_display) : '—' ?></span>
                  <button type="button" id="btn-copy-router" class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#router-mac" title="Copy Router Mac">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr>
              <!-- <tr>
                <td class="k"><i class="bi bi-ethernet"></i> Active Mac</td>
                <td class="v mono">
                  <span id="active-mac">—</span>
                  <button id="btn-copy-active" class="btn btn-outline-secondary btn-sm ms-1 btn-copy"
                          data-copy-el="#active-mac" title="Copy" style="display:none;">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr> -->
              <tr><td class="k"><i class="bi bi-cpu"></i> Vendor</td><td class="v" id="device-vendor"><?= $device_vendor ? h($device_vendor) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-pc-display"></i> IP Address</td><td class="v mono" id="live-ip"><?= h($live_ip) ?></td></tr>
              <tr><td class="k"><i class="bi bi-stopwatch"></i> Uptime</td><td class="v" id="uptime">—</td></tr>
              <tr>
                <td class="k"><i class="bi bi-wifi"></i> Status</td>
                <td class="v"><span id="live-status" class="badge <?= $is_online?'bg-success':'bg-danger' ?>"><?= $is_online?'Online':'Offline' ?></span></td>
              </tr>
              <tr><td class="k"><i class="bi bi-alarm"></i>Last Logout</td><td class="v" id="last-seen"><?= h($last_seen) ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-bar-chart-line"></i> Data Used</td>
                <td class="v"><span id="total-dl">—</span> Download <br> <span id="total-ul">—</span> Upload</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <a href="/public/client_live_graph.php?id=<?= (int)$client['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up"></i> Live Graph</a>
          <a href="#" class="btn btn-outline-secondary btn-sm"><i class="bi bi-link-45deg"></i> Bind Mac</a>
          <?php if (!$isLeft): ?>
            <?php if ($stVal==='active'): ?>
              <button class="btn btn-outline-warning btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'kick')"><i class="bi bi-plug"></i> Disconnect</button>
            <?php else: ?>
              <button class="btn btn-outline-success btn-sm" onclick="changeStatus(this, <?= (int)$client['id'] ?>,'enable')"><i class="bi bi-plug"></i> Connect</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- OLT Information -->
    <div class="col-12 col-lg-4 mt-3 mt-lg-0">
      <div class="card-block h-100">
        <div class="card-title">OLT Information</div>
        <div class="table-responsive p-2">
          <table class="table table-sm table-borderless table-kv mb-0">
            <tbody>
              <tr><td class="k"><i class="bi bi-lightning-charge"></i> OLT</td><td class="v" id="olt-name"><?= $olt_linked && $olt_name ? h($olt_name) : ($olt_linked ? ('OLT #'.(int)$client['olt_id']) : '-') ?></td></tr>
              <tr><td class="k"><i class="bi bi-cpu"></i> Vendor</td><td class="v" id="olt-vendor"><?= $olt_linked && $olt_vendor ? h($olt_vendor) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-hdd-network"></i> Host/IP</td><td class="v" id="olt-host"><?= $olt_linked && $olt_host ? h($olt_host) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-diagram-2"></i> PON Port</td><td class="v" id="olt-port"><?= $pon_port_display ? h($pon_port_display) : ($pon_display ? h($pon_display) : ($pon_iface ? h($pon_iface) : '—')) ?></td></tr>
              <tr><td class="k"><i class="bi bi-disc"></i> ONU ID</td><td class="v" id="olt-onu"><?= ($onu_id_display !== null && $onu_id_display !== '' ? h((string)$onu_id_display) : '—') ?></td></tr>
              <tr><td class="k"><i class="bi bi-upc-scan"></i> ONU MAC</td><td class="v mono" id="olt-mac"><?= $onu_mac ? h(strtoupper($onu_mac)) : '—' ?></td></tr>
              <tr><td class="k"><i class="bi bi-calendar2-week"></i> Last Linked</td><td class="v" id="olt-linked-at"><?= $last_linked_display ? h($last_linked_display) : '—' ?></td></tr>
              <tr>
                <td class="k"><i class="bi bi-broadcast-pin"></i> Last Rx (dBm)</td>
                <td class="v">
                  <span id="olt-last-rx-value"><?= $rx_prefill !== null ? h($rx_prefill).' dBm' : '—' ?></span>
                  <?php if($rx_prefill_meta[0]): ?>
                    <span id="olt-last-rx-badge" class="badge <?= $rx_prefill_meta[1]; ?> ms-2"><?= $rx_prefill_meta[0]; ?></span>
                  <?php else: ?>
                    <span id="olt-last-rx-badge"></span>
                  <?php endif; ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-actions d-flex flex-wrap gap-2">
          <span id="olt-unlinked-hint" class="text-muted small" <?=$olt_linked?'style="display:none"':'';?>>No OLT linked</span>
          <a id="olt-view-link" href="/olt/index.php" class="btn btn-outline-primary btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-diagram-3"></i> View OLT</a>
          <a id="olt-onu-monitor-link" href="/public/onu_monitor.php<?= $olt_linked ? ('?olt_id='.(int)$client['olt_id']) : ''; ?>" class="btn btn-outline-secondary btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-broadcast-pin"></i> ONU Monitor</a>
          <a id="olt-mac-cache-link" href="/public/olt_mac_table.php<?= $olt_linked ? ('?olt_id='.(int)$client['olt_id']) : ''; ?>" class="btn btn-outline-info btn-sm <?=$olt_linked?'':'d-none';?>" target="_blank"><i class="bi bi-table"></i> MAC Cache</a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===================== RENEW MODAL ===================== -->
<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="renewForm">
      <div class="modal-header">
        <h6 class="modal-title" id="renewModalLabel"><i class="bi bi-arrow-repeat"></i> Renew — <?= h($client['name']) ?> (<?= h($client['client_code']) ?>)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Months</label>
            <select name="months" id="rn_months" class="form-select form-select-sm">
              <?php for($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $i===1?'selected':'' ?>><?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="rn_amount" class="form-control form-control-sm"
                   value="<?= is_numeric($client['monthly_bill']??null)? (0+$client['monthly_bill']) : 0 ?>">
          </div>

          <div class="col-6">
            <label class="form-label">Method</label>
            <select name="method" id="rn_method" class="form-select form-select-sm">
              <option value="Cash">Cash</option>
              <option value="bKash">bKash</option>
              <option value="Nagad">Nagad</option>
              <option value="Bank">Bank</option>
              <option value="Online">Online</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Invoice Date</label>
            <input type="date" name="invoice_date" id="rn_invoice_date" class="form-control form-control-sm"
                   value="<?= date('Y-m-d') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Note (optional)</label>
            <input type="text" name="note" id="rn_note" class="form-control form-control-sm" placeholder="e.g. Monthly renewal">
          </div>

          <div class="col-12 mt-2">
            <div class="alert alert-light border d-flex align-items-center gap-2 py-2 mb-0">
              <i class="bi bi-calendar-check text-primary"></i>
              <div>
                <div class="small text-muted">Current Expiry:</div>
                <div class="fw-semibold" id="rn_exp_current"><?= h($client['expiry_date'] ?: '—') ?></div>
              </div>
              <div class="ms-3">
                <div class="small text-muted">New Expiry (est.):</div>
                <div class="fw-semibold text-success" id="rn_exp_new">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between">
        <span class="small text-muted">Package: <?= h($client['package_name'] ?: 'N/A') ?> • Bill: <?= h($client['monthly_bill'] ?? '0') ?></span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary btn-sm">Create Invoice & Renew</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- =================== /RENEW MODAL =================== -->

<script>
const API_SINGLE = '/api/control.php'; // বাংলা: action endpoint
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const LIVE_STATUS_TIMEOUT_MS = 20000; // SNMP-heavy live status calls can take >10s; allow enough time
const INITIAL_OLT_BINDING = <?= json_encode($initialOltBinding, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
let currentOltBinding = INITIAL_OLT_BINDING && INITIAL_OLT_BINDING.olt_id ? INITIAL_OLT_BINDING : null;
const rxValueEl = document.getElementById('olt-last-rx-value');
const rxBadgeEl = document.getElementById('olt-last-rx-badge');

function rxBadgeMeta(val){
  if(val === null || val === undefined || val === '') return [null,null];
  const num = Number(val);
  if(!Number.isFinite(num)) return [null,null];
  if(num >= -24 && num <= -1) return ['Good','text-bg-success'];
  if(num >= -26 && num < -24) return ['Warn','text-bg-warning text-dark'];
  return ['Critical','text-bg-danger'];
}

function updateRxDisplay(val, opts={force:false}){
  const hasVal = val !== null && val !== undefined && val !== '' && Number.isFinite(Number(val));
  if(!hasVal && !opts.force){
    // Keep whatever was already displayed if no new value arrived
    return;
  }
  const txt = hasVal ? `${Number(val).toFixed(2)} dBm` : '—';
  if(rxValueEl) rxValueEl.textContent = txt;
  if(rxBadgeEl){
    const [label, cls] = hasVal ? rxBadgeMeta(val) : [null,null];
    rxBadgeEl.className = 'badge ms-2';
    if(label && cls){
      rxBadgeEl.textContent = label;
      rxBadgeEl.className = `badge ms-2 ${cls}`;
      rxBadgeEl.style.display = '';
    } else {
      rxBadgeEl.textContent = '';
      rxBadgeEl.style.display = 'none';
    }
  }
}

function renderOltBinding(binding){
  if(!binding) return;
  currentOltBinding = binding;
  const nameEl   = document.getElementById('olt-name');
  const hostEl   = document.getElementById('olt-host');
  const vendorEl = document.getElementById('olt-vendor');
  const portEl   = document.getElementById('olt-port');
  const onuEl    = document.getElementById('olt-onu');
  const macEl    = document.getElementById('olt-mac');
  const linkEl   = document.getElementById('olt-linked-at');
  const unlinked = document.getElementById('olt-unlinked-hint');
  const viewL    = document.getElementById('olt-view-link');
  const onuL     = document.getElementById('olt-onu-monitor-link');
  const macL     = document.getElementById('olt-mac-cache-link');

  if(nameEl)   nameEl.textContent = binding.name || (binding.olt_id ? `OLT #${binding.olt_id}` : (nameEl.textContent || '—'));
  if(hostEl)   hostEl.textContent = binding.host || hostEl.textContent || '—';
  if(vendorEl) vendorEl.textContent = binding.vendor || vendorEl.textContent || '—';
  if(portEl)   portEl.textContent = binding.port || binding.port_label || portEl.textContent || '—';
  if(onuEl)    onuEl.textContent  = binding.onu ? ((binding.port || binding.port_label) ? `${binding.port || binding.port_label}:${binding.onu}` : binding.onu) : (onuEl.textContent || '—');
  if(macEl)    macEl.textContent  = binding.mac ? binding.mac.toUpperCase() : (macEl.textContent || '—');
  if(linkEl && binding.learned_at) linkEl.textContent = binding.learned_at;
  updateRxDisplay(binding.rx_power_dbm);

  if(binding.olt_id){
    if(unlinked) unlinked.style.display = 'none';
    if(viewL){ viewL.classList.remove('d-none'); viewL.href = '/olt/index.php'; }
    if(onuL){ onuL.classList.remove('d-none'); onuL.href = `/public/onu_monitor.php?olt_id=${binding.olt_id}`; }
    if(macL){ macL.classList.remove('d-none'); macL.href = `/public/olt_mac_table.php?olt_id=${binding.olt_id}`; }
  }
}

renderOltBinding(currentOltBinding);

/* ===== Toast ===== */
function showToast(msg, type='success', timeout=2800){
  const box = document.createElement('div');
  box.className = 'app-toast ' + (type==='success' ? 'success' : 'error');
  box.setAttribute('role','status');
  box.textContent = msg || 'Done';
  document.body.appendChild(box);
  setTimeout(()=> box.classList.add('hide'), timeout-200);
  setTimeout(()=> box.remove(), timeout);
}
/* Restore toast after reload */
document.addEventListener('DOMContentLoaded', ()=>{
  const t = sessionStorage.getItem('toast');
  if (t){ try{ const o=JSON.parse(t); showToast(o.message, o.type||'success', 2800); }catch{} sessionStorage.removeItem('toast'); }
});

/* ===== Confirm dialog ===== */
function customConfirm({title='Confirm', message='Are you sure?', okText='OK', cancelText='Cancel'}){
  return new Promise((resolve)=>{
    const bd = document.createElement('div');
    bd.className = 'app-confirm-backdrop';
    bd.innerHTML = `
      <div class="app-confirm-box" role="dialog" aria-modal="true" aria-label="${title}">
        <div class="app-confirm-title">${title}</div>
        <div class="app-confirm-text">${message}</div>
        <div class="app-confirm-actions">
          <button class="app-btn secondary" data-act="cancel">${cancelText}</button>
          <button class="app-btn primary" data-act="ok">${okText}</button>
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

/* ===== Enable/Disable/Kick — POST + CSRF ===== */
async function changeStatus(btn, id, action){
  const ok = await customConfirm({
    title: (action==='disable')?'Disable client?':(action==='kick'?'Disconnect client?':'Enable client?'),
    message: `Are you sure you want to ${action} this client?`,
    okText: (action==='disable')?'Disable':'Yes', cancelText: 'Cancel'
  });
  if(!ok) return;

  const oldHTML = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';

  fetch(API_SINGLE, {
    method: 'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ action, id: String(id), csrf_token: CSRF })
  })
    .then(r=>r.json())
    .then(data=>{
      if (data.status === 'success'){
        const msg = data.message || 'Done';
        sessionStorage.setItem('toast', JSON.stringify({message: msg, type:'success'}));
        location.reload();
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

/* ===== Auto-control trigger — POST + CSRF ===== */
async function autoRecheck(btn, id){
  const ok = await customConfirm({
    title: 'Auto re-evaluate?',
    message: 'Run auto control now based on current ledger balance.',
    okText: 'Run now', cancelText: 'Cancel'
  });
  if(!ok) return;

  const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';

  try{
    const res = await fetch('/api/auto_control_client.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({client_id: String(id), csrf_token: CSRF})
    });
    const j = await res.json();
    if (j.ok){
      sessionStorage.setItem('toast', JSON.stringify({message: j.msg || ('Action: '+(j.action||'done')), type:'success'}));
      location.reload();
    } else {
      showToast(j.msg || 'Auto control failed', 'error', 3000);
      btn.disabled=false; btn.innerHTML=old;
    }
  } catch(e){
    showToast('Request failed', 'error', 3000);
    btn.disabled=false; btn.innerHTML=old;
  }
}

/* ===== Copy ===== */
async function __copyTextRobust(t){
  t = (t || '').trim();
  if (!t || t === '-' || t === '—') throw new Error('empty');
  if (navigator.clipboard && window.isSecureContext !== false) {
    await navigator.clipboard.writeText(t);
    return;
  }
  const ta = document.createElement('textarea');
  ta.value = t; ta.setAttribute('readonly',''); ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, t.length);
  const ok = document.execCommand('copy');
  document.body.removeChild(ta);
  if (!ok) throw new Error('fallback-failed');
}
document.addEventListener('click', async function(e){
  const btn = e.target.closest('.btn-copy');
  if(!btn) return;
  let text = (btn.getAttribute('data-copy') || '').trim();
  if (!text) {
    const sel = btn.getAttribute('data-copy-el');
    if (sel) {
      const el = document.querySelector(sel);
      if (el) text = (el.textContent || '').trim();
    }
  }
  try { await __copyTextRobust(text); showToast('copied','success',1600); }
  catch(err){ showToast('copy failed','error',1800); }
});

/* ===== Password eye toggle ===== */
document.getElementById('ppp-eye')?.addEventListener('click', ()=>{
  const m = document.getElementById('ppp-mask');
  if (!m) return;
  if (m.dataset.revealed === '1') {
    m.textContent = '<?= $pp ? str_repeat('•', max(6, strlen($pp))) : '-' ?>';
    m.dataset.revealed = '0';
  } else {
    m.textContent = '<?= h($pp) ?>';
    m.dataset.revealed = '1';
  }
});

/* ===== Live status via API (10s; backoff) ===== */
let liveTimer = null, inflight = false, backoff = 10000;
function loadLiveStatus(){
  if(inflight) return;
  inflight = true;
  const ctl = new AbortController();
  const t = setTimeout(()=>ctl.abort(), LIVE_STATUS_TIMEOUT_MS);

  fetch('/api/client_live_status.php?id=<?= (int)$client['id']; ?>', {cache:'no-store', signal: ctl.signal})
    .then(res=>res.json()).then(d=>{
      const dv = document.getElementById('device-vendor');
      if (dv && d.device_vendor && d.device_vendor.trim() !== '') {
        dv.textContent = d.device_vendor;
      }

      const rmacEl = document.getElementById('router-mac');
      const amacEl = document.getElementById('active-mac');
      const rBtn   = document.getElementById('btn-copy-router');
      const aBtn   = document.getElementById('btn-copy-active');

      const rmac = (d.router_mac && d.router_mac.trim()!=='') ? d.router_mac : (d.caller_id || '—');
      const amac = (d.active_mac && d.active_mac.trim()!=='') ? d.active_mac : (d.caller_id || '—');

      if (rmacEl) rmacEl.textContent = rmac || '—';
      if (amacEl) amacEl.textContent = amac || '—';

      if (rBtn){
        if (rmac && rmac!=='—'){ rBtn.style.display=''; rBtn.setAttribute('data-copy', rmac); rBtn.removeAttribute('data-copy-el'); }
        else { rBtn.style.display='none'; rBtn.setAttribute('data-copy',''); }
      }
      if (aBtn){
        if (amac && amac!=='—'){ aBtn.style.display=''; aBtn.setAttribute('data-copy', amac); aBtn.removeAttribute('data-copy-el'); }
        else { aBtn.style.display='none'; aBtn.setAttribute('data-copy',''); }
      }

      if (dv && (dv.textContent==='—' || dv.textContent==='' || dv.textContent==='Unknown Vendor') && rmac && rmac!=='—'){
        fetch('/api/mac_vendor.php?mac='+encodeURIComponent(rmac), {cache:'no-store'})
          .then(r=>r.json()).then(j=>{ if (j && j.vendor) dv.textContent = j.vendor; }).catch(()=>{});
      }

      const ip = document.getElementById('live-ip');
      const up = document.getElementById('uptime');
      const st = document.getElementById('live-status');
      const ls = document.getElementById('last-seen');
      const dl = document.getElementById('total-dl');
      const ul = document.getElementById('total-ul');
      const rx = document.getElementById('rx-rate');
      const tx = document.getElementById('tx-rate');
      const namePill = document.getElementById('name-online');
      const binding = d.olt_binding;

      if(ip) ip.textContent = (d.ip ?? '—');
      if(up) up.textContent = (d.uptime ?? '—');
      if(ls) ls.textContent = (d.last_seen ?? '—');
      if(dl) dl.textContent = (d.total_download_gb!=null ? d.total_download_gb+' GB' : '—');
      if(ul) ul.textContent = (d.total_upload_gb!=null   ? d.total_upload_gb  +' GB' : '—');
      if(rx) rx.textContent = d.rx_rate || '0 Kbps';
      if(tx) tx.textContent = d.tx_rate || '0 Kbps';
      updateRxDisplay(d.rx_power_dbm);
      if(binding && binding.olt_id){
        renderOltBinding(binding);
      }

      if(st){
        st.textContent = d.online ? 'Online':'Offline';
        st.className   = 'badge ' + (d.online ? 'bg-success' : 'bg-danger');
      }
      if(namePill){
        namePill.innerHTML = `<i class="bi bi-wifi"></i> ${d.online ? 'Online' : 'Offline'}`;
        namePill.className = 'badge ' + (d.online ? 'bg-success' : 'bg-secondary');
        namePill.style.backgroundColor = d.online ? '#198754' : '#6c757d';
      }

      backoff = 10000;
    })
    .catch(()=>{ backoff = Math.min(backoff * 1.5, 30000); })
    .finally(()=>{ clearTimeout(t); inflight=false; });
}
function startLive(){ if (!liveTimer) liveTimer = setInterval(loadLiveStatus, backoff); }
function stopLive(){ if (liveTimer) { clearInterval(liveTimer); liveTimer = null; } }
document.addEventListener('visibilitychange', ()=> {
  if (document.hidden) stopLive(); else { loadLiveStatus(); startLive(); }
});
setInterval(()=>{ if (liveTimer){ clearInterval(liveTimer); liveTimer = setInterval(loadLiveStatus, backoff); } }, 3000);

loadLiveStatus(); startLive();

/* ===== Renew submit (invoice+renew) ===== */
(function(){
  const monthsEl = document.getElementById('rn_months');
  const amountEl = document.getElementById('rn_amount');
  const invDateEl= document.getElementById('rn_invoice_date');
  const formEl   = document.getElementById('renewForm');

  const monthlyBill = Number(<?= json_encode((float)($client['monthly_bill'] ?? 0)) ?>);
  const expCur = <?= json_encode($client['expiry_date'] ?? '') ?>;

  function addMonths(dateStr, m){
    if(!dateStr) return '';
    const d = new Date(dateStr+'T00:00:00');
    if(isNaN(d)) return '';
    const dd = new Date(d.getTime()); dd.setMonth(dd.getMonth() + m);
    return `${dd.getFullYear()}-${String(dd.getMonth()+1).padStart(2,'0')}-${String(dd.getDate()).padStart(2,'0')}`;
  }
  function todayYMD(){
    const d=new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }
  function maxDate(a,b){ if(!a) return b; if(!b) return a; return (a>b)?a:b; }

  document.getElementById('renewModal')?.addEventListener('shown.bs.modal', ()=>{
    const m = parseInt(monthsEl.value||'1',10);
    if (!amountEl.dataset.touched) amountEl.value = (monthlyBill * (isNaN(m)?1:m)).toFixed(2);
    const base = maxDate(todayYMD(), (expCur||'')); // base = today বা current expiry এর বড় যেটা
    document.getElementById('rn_exp_new').textContent = base ? addMonths(base, isNaN(m)?1:m) : '—';
    document.getElementById('rn_exp_current').textContent = (expCur||'—');
  });

  monthsEl?.addEventListener('change', ()=>{
    const m = parseInt(monthsEl.value||'1',10);
    if (!amountEl.dataset.touched) amountEl.value = (monthlyBill * (isNaN(m)?1:m)).toFixed(2);
    const base = maxDate(todayYMD(), (expCur||'')); 
    document.getElementById('rn_exp_new').textContent = base ? addMonths(base, isNaN(m)?1:m) : '—';
  });
  amountEl?.addEventListener('input', ()=>{ amountEl.dataset.touched = '1'; });

  formEl?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const months = parseInt(monthsEl.value||'1',10);
    const amount = Number(amountEl.value||'0');
    const method = document.getElementById('rn_method').value || 'Cash';
    const note   = document.getElementById('rn_note').value || '';
    const invdt  = invDateEl.value || todayYMD();
    if(isNaN(months) || months<=0){ showToast('Invalid months','error'); return; }
    if(isNaN(amount) || amount<=0){ showToast('Invalid amount','error'); return; }

    try{
      const res = await fetch('/api/renew.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ client_id: <?= (int)$client['id'] ?>, months, amount, method, note, invoice_date: invdt, csrf_token: CSRF })
      });
      const data = await res.json();
      if (data.status === 'success'){
        showToast(data.message || 'Renewed & Invoiced','success',2200);
        const url = data.invoice_id
          ? `/public/invoice_view.php?id=${encodeURIComponent(data.invoice_id)}`
          : `/public/invoices.php?client_id=<?= (int)$client['id'] ?>`;
        setTimeout(()=> window.location.href = url, 700);
      } else {
        showToast(data.message || 'Renew failed','error',3000);
      }
    }catch(err){ showToast('Request failed','error',3000); }
  });
})();
</script>

<?php include __DIR__ . '/../partials/client_recent.php'; ?>
<?php include __DIR__ . '/../partials/client_ledger_widget.php'; ?>
<?php include __DIR__ . '/../partials/client_activity_widget.php'; ?>
<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
