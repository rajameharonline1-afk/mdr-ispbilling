<?php
$isCli = PHP_SAPI === 'cli';
// ======================
// পরিবেশ প্রস্তুতি (CLI/Browser)
// ======================
require_once __DIR__ . '/../app/config.php';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($isCli || ($token && defined('CRON_TOKEN') && $token === CRON_TOKEN)) {
  require_once __DIR__ . '/../app/db.php';
} else {
  require_once __DIR__ . '/../app/require_login.php';
  require_once __DIR__ . '/../app/db.php';
}
require_once __DIR__ . '/../app/telnet.php';
require_once __DIR__ . '/../app/security_helpers.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

 $request = $_GET ?? [];
 if ($isCli) {
   $cliArgs = [];
   foreach (array_slice($argv ?? [], 1) as $arg) {
     $arg = (string)$arg;
     if ($arg === '--full' || $arg === '-F') { $cliArgs['mode'] = 'full'; continue; }
     if ($arg === '--fast') { $cliArgs['mode'] = 'fast'; continue; }
     if ($arg === '--rx' || $arg === '--diag') { $cliArgs['mode'] = 'rx'; continue; }
     if ($arg === '--debug' || $arg === '-d') { $cliArgs['debug'] = 1; continue; }
     $arg = ltrim($arg, '-');
     if (strpos($arg, '=') !== false) {
       [$k, $v] = explode('=', $arg, 2);
       if($k !== '') $cliArgs[$k] = $v;
     }
   }
   $request = array_merge($request, $cliArgs);
 }

 $db = db();
 $debugMode = isset($request['debug']);
 // ডিফল্ট মোড আগে ছিল "fast" (শুধু MAC টেবিল); এতে RX / অপটিক পাওয়ার সংগ্রহ হতো না।
 // এখন ডিফল্ট মোড "rx" করা হল যাতে অপারেটর আলাদা ফ্ল্যাগ না দিলেও L(dBm) ফিল্ড পূরণ হয়।
 $mode = strtolower(trim((string)($request['mode'] ?? 'rx')));
 $mode = match($mode){
   'full','rx' => $mode,
   'diag','diagnostic','rxfast' => 'rx',
   default => 'fast'
 };
 $fullMode = $mode === 'full';
 $rxMode = $mode === 'rx';
 $fastMode = !$fullMode && !$rxMode;
 $diagEnabled = $fullMode || $rxMode;
 $statusTimeout = $fastMode ? 6 : 10;
 // opm-diag output can be lengthy; allow more time when RX is requested
$diagTimeout = $diagEnabled ? ($fullMode ? 24 : 18) : 0;
$macTimeout = $fastMode ? 8 : 10;
$descTimeout = $fullMode ? 15 : 10;
$clientMacTimeout = $fullMode ? 18 : 12;
$overallStartedAt = microtime(true);
$overallLimitSec = 600; // keep well under dashboard cURL timeout

// ======================
// সহায়ক ইউটিলিটি ও কনভার্সন ফাংশনসমূহ
// ======================

function decrypt_olt_secret(?string $ciphertext): ?string {
  if($ciphertext === null || $ciphertext === '') return null;
  $plain = decrypt_password($ciphertext, ENCRYPTION_KEY);
  if($plain === false){
    return null;
  }
  return $plain;
}

function norm_mac($mac): ?string {
  if($mac === null) return null;
  $hex = preg_replace('/[^0-9a-fA-F]/', '', (string)$mac);
  if(strlen($hex) < 12) return null;
  $hex = substr(strtolower($hex), 0, 12);
  return implode(':', str_split($hex, 2));
}

function normalize_mac_for_lookup(?string $mac): ?string {
  return norm_mac($mac);
}

function normalize_port_label(?string $label): string {
  if(!$label) return '—';
  $label = trim($label);
  if(preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/i', $label, $m)){
    $slot = str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return strtoupper($m[1])." 0/{$slot}";
  }
  if(preg_match('/0\/(\d{1,2})/i', $label, $m)){
    $slot = str_pad($m[1], 2, '0', STR_PAD_LEFT);
    return "PON 0/{$slot}";
  }
  return strtoupper($label);
}

function onu_numeric($onu): int {
  if($onu === null) return PHP_INT_MAX;
  if(is_int($onu)) return $onu;
  if(is_string($onu) && preg_match('/(\d+)/', $onu, $m)){
    return (int)$m[1];
  }
  return PHP_INT_MAX;
}

function auto_link_client_olt_from_cache(PDO $db, int $clientId, array $fields): array {
  $tokens = [];
  $addToken = static function($val) use (&$tokens) {
    $val = trim((string)$val);
    if($val !== '') $tokens[] = $val;
  };
  $addToken($fields['pppoe_id'] ?? '');
  $addToken($fields['client_code'] ?? '');
  $addToken($fields['name'] ?? '');
  $addToken($fields['mobile'] ?? '');

  if(!$tokens) return ['ok'=>false, 'reason'=>'no_tokens'];

  $fetchRow = static function(string $sql, array $params) use ($db): ?array {
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
  };

  $match = null;
  try{
    foreach($tokens as $token){
      $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE LOWER(description)=? ORDER BY learned_at DESC LIMIT 1", [strtolower($token)]);
      if($match) break;
    }
    if(!$match){
      foreach($tokens as $token){
        $mac = normalize_mac_for_lookup($token);
        if(!$mac) continue;
        $match = $fetchRow("SELECT * FROM olt_mac_cache WHERE REPLACE(LOWER(mac),':','') = ? ORDER BY learned_at DESC LIMIT 1", [strtolower(str_replace(':','',$mac))]);
        if($match) break;
      }
    }
  }catch(Throwable $e){
    return ['ok'=>false, 'reason'=>'lookup_failed'];
  }

  if(!$match) return ['ok'=>false, 'reason'=>'not_found'];

  $onuText = (string)($match['onu'] ?? '');
  $onuId = null;
  if(preg_match('/(\d+)/', $onuText, $m)){
    $onuId = (string)(int)$m[1];
  }
  $macAddr = normalize_mac_for_lookup($match['mac'] ?? '') ?? ($match['mac'] ?? null);
  $vendor = null;
  try{
    $st = $db->prepare("SELECT vendor FROM olts WHERE id=? LIMIT 1");
    $st->execute([(int)$match['olt_id']]);
    $vendor = $st->fetchColumn() ?: null;
  }catch(Throwable $e){}

  try{
    $upd = $db->prepare("UPDATE clients
                          SET olt_id=:oid,
                              olt_vendor=:vendor,
                              olt_port=:port,
                              olt_onu=:onu,
                              caller_mac=:mac,
                              last_linked_at=:linked
                          WHERE id=:id");
    $upd->execute([
      ':oid' => $match['olt_id'] ?? null,
      ':vendor' => $vendor,
      ':port' => $match['port'] ?? null,
      ':onu' => $onuId,
      ':mac' => $macAddr,
      ':linked' => $match['learned_at'] ?? null,
      ':id' => $clientId,
    ]);
  }catch(Throwable $e){
    return ['ok'=>false, 'reason'=>'update_failed'];
  }

  $portLabel = (string)($match['port'] ?? '');
  if($portLabel !== '' && $onuId){
    try{
      if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $portLabel, $pm)){
        $family = strtolower($pm[1]);
        $slot = (int)$pm[2];
        $iface = '0/'.$slot;
        $rxVal = null;
        if(isset($match['rx_power_dbm']) && $match['rx_power_dbm'] !== '' && is_numeric($match['rx_power_dbm'])){
          $rxVal = (float)$match['rx_power_dbm'];
        }
        $status = strtolower((string)($match['status'] ?? 'online'));
        $invSql = "INSERT INTO onu_inventory (olt_id,family,iface,onu_id,client_id,is_active,last_status,last_rx_dbm,last_updated)
                   VALUES (?,?,?,?,?,1,?,?,?)
                   ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), last_status=VALUES(last_status), last_rx_dbm=VALUES(last_rx_dbm), last_updated=VALUES(last_updated)";
        $db->prepare($invSql)->execute([
          (int)$match['olt_id'],
          $family,
          $iface,
          (int)$onuId,
          $clientId,
          $status !== '' ? $status : 'online',
          $rxVal,
          $match['learned_at'] ?? null,
        ]);
        if($macAddr){
          $invIdStmt = $db->prepare("SELECT id FROM onu_inventory WHERE olt_id=? AND family=? AND iface=? AND onu_id=? LIMIT 1");
          $invIdStmt->execute([(int)$match['olt_id'], $family, $iface, (int)$onuId]);
          $targetId = (int)($invIdStmt->fetchColumn() ?: 0);
          if($targetId > 0){
            $mapSql = "INSERT INTO onu_mac_map (target_id, mac, last_seen)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE mac=VALUES(mac), last_seen=VALUES(last_seen)";
            $db->prepare($mapSql)->execute([$targetId, $macAddr, $match['learned_at'] ?? date('Y-m-d H:i:s')]);
          }
        }
      }
    }catch(Throwable $e){
      // ignore inventory sync failures
    }
  }

  return [
    'ok'=>true,
    'port'=>$portLabel,
    'onu'=>$onuId,
    'olt'=>$match['olt_id'] ?? null,
  ];
}

function sync_onu_inventory_from_payload(PDO $db, int $oltId, array $payload, ?string $learnedAt): void {
  if($oltId <= 0) return;
  $portLabel = (string)($payload['port'] ?? '');
  if($portLabel === '') return;
  if(!preg_match('/(EPON|GPON)\\s*0\\/(\\d+)/i', $portLabel, $pm)) return;
  $family = strtolower($pm[1]);
  $slot = (int)$pm[2];
  $onuId = onu_numeric($payload['onu'] ?? $payload['onu_num'] ?? null);
  if($onuId === PHP_INT_MAX || $slot <= 0) return;
  $iface = '0/'.$slot;
  $rxVal = null;
  if(isset($payload['rx_power_dbm']) && $payload['rx_power_dbm'] !== '' && is_numeric($payload['rx_power_dbm'])){
    $rxVal = (float)$payload['rx_power_dbm'];
  }
  $status = strtolower((string)($payload['status'] ?? 'online'));
  $status = $status !== '' ? $status : 'online';
  $macAddr = norm_mac($payload['mac'] ?? '');
  $seenAt = $learnedAt ?: date('Y-m-d H:i:s');
  try{
    $invSql = "INSERT INTO onu_inventory (olt_id,family,iface,onu_id,client_id,is_active,last_status,last_rx_dbm,last_updated)
               VALUES (?,?,?,?,?,1,?,?,?)
               ON DUPLICATE KEY UPDATE client_id=VALUES(client_id), last_status=VALUES(last_status), last_rx_dbm=VALUES(last_rx_dbm), last_updated=VALUES(last_updated)";
    $db->prepare($invSql)->execute([
      $oltId,
      $family,
      $iface,
      (int)$onuId,
      (int)($payload['client_id'] ?? 0),
      $status,
      $rxVal,
      $seenAt,
    ]);
    if($macAddr){
      $invIdStmt = $db->prepare("SELECT id FROM onu_inventory WHERE olt_id=? AND family=? AND iface=? AND onu_id=? LIMIT 1");
      $invIdStmt->execute([$oltId, $family, $iface, (int)$onuId]);
      $targetId = (int)($invIdStmt->fetchColumn() ?: 0);
      if($targetId > 0){
        $mapSql = "INSERT INTO onu_mac_map (target_id, mac, last_seen)
                   VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE mac=VALUES(mac), last_seen=VALUES(last_seen)";
        $db->prepare($mapSql)->execute([$targetId, $macAddr, $seenAt]);
      }
    }
  }catch(Throwable $e){
    // inventory sync failures are non-fatal
  }
}

function load_existing_mac_rows(PDO $db, int $oltId): array {
  $stmt = $db->prepare("SELECT mac,vlan,port,onu,status,description,distance_m,rx_power_dbm,last_dereg_reason,last_dereg_time,client_id,clients,vendor,client_mac,client_mac_vlan,client_mac_learned_at,learned_at
                        FROM olt_mac_cache
                        WHERE olt_id = ?");
  $stmt->execute([$oltId]);
  $rows = [];
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $mac = strtolower(trim((string)$row['mac']));
    if($mac === '') continue;
    $rows[$mac] = $row;
  }
  return $rows;
}

function normalize_compare_value($value){
  if($value === null) return null;
  if(is_string($value)){
    $value = trim($value);
    if($value === '') return null;
    if(is_numeric($value)){
      return (float)$value;
    }
    return $value;
  }
  if(is_numeric($value)){
    return (float)$value;
  }
  return $value;
}

function values_equal($a,$b): bool {
  $na = normalize_compare_value($a);
  $nb = normalize_compare_value($b);
  if($na === null && $nb === null) return true;
  if(is_float($na) || is_float($nb)){
    if($na === null || $nb === null) return false;
    return abs((float)$na - (float)$nb) < 0.0001;
  }
  return $na === $nb;
}

function mac_payloads_equal(array $existing, array $payload): bool {
  $fields = ['vlan','port','onu','status','description','distance_m','rx_power_dbm','last_dereg_reason','last_dereg_time','client_id','clients','vendor','client_mac','client_mac_vlan','client_mac_learned_at'];
  foreach($fields as $field){
    $oldVal = $existing[$field] ?? null;
    $newVal = $payload[$field] ?? null;
    if(!values_equal($oldVal, $newVal)){
      return false;
    }
  }
  return true;
}

function load_client_mac_map(PDO $db): array {
  $map = [];
  try{
    $cols = $db->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $hasMacAddress = in_array('mac_address', $cols, true);
    $hasOnuMac     = in_array('onu_mac', $cols, true);
    $select = "id, caller_mac, router_mac, ap_mac";
    if($hasMacAddress) $select .= ", mac_address";
    if($hasOnuMac) $select .= ", onu_mac";
    $stmt = $db->query("SELECT {$select} FROM clients");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      $cid = (int)($row['id'] ?? 0);
      if(!$cid) continue;
      $macFields = ['caller_mac','router_mac','ap_mac'];
      if($hasMacAddress) $macFields[] = 'mac_address';
      if($hasOnuMac) $macFields[] = 'onu_mac';
      foreach($macFields as $f){
        $mk = norm_mac($row[$f] ?? null);
        if($mk) $map[$mk] = $cid;
      }
    }
  }catch(Throwable $e){}
  return $map;
}

function ensure_olt_mac_cache_schema(PDO $db): void {
  static $checked = false;
  if($checked) return;
  try {
    $columns = $db->query("SHOW COLUMNS FROM olt_mac_cache")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  } catch(PDOException $e){
    if(strpos($e->getMessage(),'42S02') !== false){
      return; // টেবিল পরে তৈরী হলে insert ব্লকে কাভার হবে
    }
    throw $e;
  }
  $needed = [
    'last_dereg_reason' => "ADD COLUMN last_dereg_reason varchar(255) DEFAULT NULL AFTER rx_power_dbm",
    'last_dereg_time'   => "ADD COLUMN last_dereg_time datetime DEFAULT NULL AFTER last_dereg_reason",
    'clients'           => "ADD COLUMN clients varchar(255) DEFAULT NULL AFTER mac",
    'client_id'       => "ADD COLUMN client_id int unsigned DEFAULT NULL AFTER olt_id",
    'description'      => "ADD COLUMN description varchar(255) DEFAULT NULL AFTER onu",
    'distance_m'       => "ADD COLUMN distance_m decimal(10,2) DEFAULT NULL AFTER description",
    'rx_power_dbm'     => "ADD COLUMN rx_power_dbm decimal(10,2) DEFAULT NULL AFTER distance_m",
    'vendor'           => "ADD COLUMN vendor varchar(64) DEFAULT NULL AFTER olt_id",
    'client_mac'       => "ADD COLUMN client_mac varchar(32) DEFAULT NULL AFTER clients",
    'client_mac_vlan'  => "ADD COLUMN client_mac_vlan int unsigned DEFAULT NULL AFTER client_mac",
    'client_mac_learned_at' => "ADD COLUMN client_mac_learned_at datetime DEFAULT NULL AFTER client_mac_vlan",
  ];
  $ddl = [];
  foreach($needed as $col => $sql){
    if(!in_array($col, $columns, true)){
      $ddl[] = $sql;
    }
  }
  if($ddl){
    $db->exec('ALTER TABLE olt_mac_cache ' . implode(', ', $ddl));
  }
  $checked = true;
}

// ======================
// স্কিমা প্রস্তুতি (olt_mac_cache কলামগুলো নিশ্চিত করা)
// ======================
ensure_olt_mac_cache_schema($db);

// ======================
// রিকোয়েস্ট কন্ট্রোল ফ্ল্যাগ
// ======================
$skipPppoe = isset($request['skip_pppoe']) && (string)$request['skip_pppoe'] === '1';

// ======================
// গ্লোবাল লক (cron ওভারল্যাপ প্রতিরোধ)
// ======================
$globalLockAcquired = false;
try{
  $lockStmt = $db->prepare("SELECT GET_LOCK(?, 1)");
  $lockStmt->execute(['cron_olt_mac_refresh']);
  $globalLockAcquired = (int)($lockStmt->fetchColumn() ?: 0) === 1;
}catch(Throwable $e){
  $globalLockAcquired = false;
}
if(!$globalLockAcquired){
  $resp = ['ok'=>false, 'error'=>'lock_busy'];
  if($isCli){
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
  } else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
  }
  exit;
}

function canonical_spo_key(?string $family, ?int $slot, ?int $onu): ?string {
  if(!$family || $slot === null || $onu === null) return null;
  return sprintf('%s0/%d:%d', strtoupper($family), (int)$slot, (int)$onu);
}

function parse_onu_identifier(string $id): ?array {
  $normalized = strtoupper(str_replace(' ', '', $id));
  if(preg_match('/(EPON|GPON)0\/(\d+):(\d+)/', $normalized, $m)){
    return [strtoupper($m[1]), (int)$m[2], (int)$m[3]];
  }
  if(preg_match('/(EPON|GPON)0\/(\d+)\/(\d+)/', $normalized, $m)){
    return [strtoupper($m[1]), (int)$m[2], (int)$m[3]];
  }
  if(preg_match('/0\/(\d+)\/(\d+)/', $normalized, $m)){
    return ['EPON', (int)$m[1], (int)$m[2]];
  }
  return null;
}

function parse_mac_entries(string $txt): array {
  $rows = [];
  $lines = preg_split('/\R/', $txt);
  foreach($lines as $line){
    $line = trim($line);
    if($line === '') continue;
    if(!preg_match('/((?:[0-9a-f]{2}[-:]){5}[0-9a-f]{2}|[0-9a-f]{4}[-:][0-9a-f]{4}[-:][0-9a-f]{4}|[0-9a-f]{4}\.[0-9a-f]{4}\.[0-9a-f]{4})/i', $line, $m)) continue;
    $mac = norm_mac($m[1]);
    if(!$mac) continue;

    // VLAN বের করা: MAC টোকেনের ঠিক আগের টোকেন যদি 1-4094 সংখ্যার VLAN হয়।
    $vlan = null;
    $parts = preg_split('/\s+/', $line);
    $macPattern = '/^(?:[0-9a-f]{2}[-:]){5}[0-9a-f]{2}$|^[0-9a-f]{4}[-:][0-9a-f]{4}[-:][0-9a-f]{4}$|^[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}$/i';
    foreach($parts as $idx => $tok){
      if(preg_match($macPattern, $tok)){
        if($idx > 0 && ctype_digit($parts[$idx-1] ?? '') ){
          $v = (int)$parts[$idx-1];
          if($v >= 1 && $v <= 4094){
            $vlan = $v;
            break;
          }
        }
      }
    }

    $portLabel = null;
    $onu = null;
    $family = null;
    $slotInt = null;

    if(preg_match('/(EPON|GPON)\s*0\/(\d+)\s*(?::|\/)?\s*(\d{1,3})?/i', $line, $pm)){
      $family = strtoupper($pm[1]);
      $slot   = str_pad($pm[2], 2, '0', STR_PAD_LEFT);
      $portLabel = "{$family} 0/{$slot}";
      $slotInt = (int)$pm[2];
      if(!empty($pm[3])) $onu = (int)$pm[3];
    } elseif(preg_match('/\b(EPON|GPON)\b/i', $line, $pm)){
      $portLabel = strtoupper($pm[1]);
      $family = strtoupper($pm[1]);
    } else {
      $tokens = preg_split('/\s+/', $line);
      $tokens = array_values(array_filter($tokens, fn($t)=>$t!==''));
      $tokens = array_map(fn($t)=>strtoupper($t), $tokens);
      $badTail = ['DYNAMIC','STATIC','SECURE','FORWARD','LEARN','AGEING','AGING','YES','NO','MAC','ADDRESS','TABLE'];
      while($tokens){
        $candidate = array_pop($tokens);
        if(in_array($candidate, $badTail, true)) continue;
        $portLabel = $candidate;
        break;
      }
    }

    if($portLabel && preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/i', $portLabel, $norm)){
      $family = strtoupper($norm[1]);
      $slot   = str_pad($norm[2], 2, '0', STR_PAD_LEFT);
      $slotInt = (int)$norm[2];
      $portLabel = "{$family} 0/{$slot}";
    } elseif($portLabel && preg_match('/PON\s*0\/(\d{1,2})/i', $portLabel, $norm)){
      $slot   = str_pad($norm[1], 2, '0', STR_PAD_LEFT);
      $slotInt = (int)$norm[1];
      $portLabel = "PON 0/{$slot}";
      if(!$family) $family = 'EPON';
    } elseif($portLabel && preg_match('/0\/(\d{1,2})/i', $portLabel, $norm)){
      $slot   = str_pad($norm[1], 2, '0', STR_PAD_LEFT);
      $slotInt = (int)$norm[1];
      $portLabel = "PON 0/{$slot}";
      if(!$family) $family = 'EPON';
    }
    if(!$family) $family = 'EPON';

    $rows[] = [
      'mac'  => $mac,
      'port' => $portLabel,
      'onu'  => $onu ? ('ONU '.$onu) : null,
      'vlan' => $vlan,
      'onu_num' => $onu,
      'family'  => $family,
      'slot'    => $slotInt,
    ];
  }
  return $rows;
}

function normalize_status(?string $status): ?string {
  $s = strtolower(trim((string)$status));
  if($s === '') return null;
  return match($s){
    'online','up','active','reachable'   => 'online',
    'offline','down','inactive','failed' => 'offline',
    default => null,
  };
}

function canonical_key_from_row(array $row): ?string {
  $port = $row['port'] ?? '';
  $onu  = onu_numeric($row['onu'] ?? null);
  if($onu === PHP_INT_MAX) return null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', (string)$port, $m)){
    return canonical_spo_key(strtoupper($m[1]), (int)$m[2], $onu);
  }
  if(preg_match('/PON\s*0\/(\d+)/i', (string)$port, $m)){
    return canonical_spo_key('EPON', (int)$m[1], $onu);
  }
  return null;
}

function parse_onu_datetime($value): ?string {
  $raw = trim((string)$value);
  if($raw === '' || strcasecmp($raw, 'n/a') === 0 || $raw === '—') return null;
  $raw = str_replace('/', '-', $raw);
  $ts = strtotime($raw);
  if($ts === false) return null;
  return date('Y-m-d H:i:s', $ts);
}

function normalize_reason_value($value): ?string {
  $raw = trim((string)$value);
  if($raw === '') return null;
  if(strcasecmp($raw, 'n/a') === 0) return 'N/A';
  if($raw === '--' || $raw === '—') return null;
  return $raw;
}

function parse_onu_statuses(string $txt): array {
  $mapByKey = [];
  $mapByMac = [];
  $linePattern = '/^(?<id>\\S+)\\s+(?<status>\\S+)\\s+(?<mac>(?:[0-9a-f]{2}[-:]){5}[0-9a-f]{2}|[0-9a-f]{4}[-:][0-9a-f]{4}[-:][0-9a-f]{4}|[0-9a-f]{4}\\.[0-9a-f]{4}\\.[0-9a-f]{4}|N\\/A|--|—)\\s+(?<distance>\\d+(?:\\.\\d+)?|N\\/A|--|—)\\s+(?<rtt>\\d+(?:\\.\\d+)?|N\\/A|--|—)\\s+(?<lastreg>(?:\\d{4}[\\/\\-]\\d{2}[\\/\\-]\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}|N\\/A|--|—))\\s+(?<lastdereg>(?:\\d{4}[\\/\\-]\\d{2}[\\/\\-]\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}|N\\/A|--|—))\\s+(?<reason>.+?)\\s+(?<alive>(?:\\d+\\s+)?\\d{1,2}:\\d{2}:\\d{2})\\s+(?<upgrade>\\S+)$/i';
  $lines = preg_split('/\R/', $txt);
  foreach($lines as $line){
    $line = trim($line);
    if($line === '' || (stripos($line,'EPON') === false && stripos($line,'GPON') === false)) continue;
    $entry = null;
    $family = null;
    $slot = null;
    $onu = null;
    $mac = null;
    if(preg_match($linePattern, $line, $m)){
      $idData = parse_onu_identifier($m['id'] ?? '');
      if(!$idData) continue;
      [$family, $slot, $onu] = $idData;
      $statusRaw = strtolower($m['status'] ?? '');
      if($statusRaw !== 'online' && $statusRaw !== 'offline') continue;
      $macRaw = strtoupper(trim($m['mac'] ?? ''));
      $mac    = ($macRaw === 'N/A' || $macRaw === '--' || str_starts_with($macRaw, '—')) ? null : (norm_mac($macRaw) ?? null);
      $distanceRaw = $m['distance'] ?? null;
      $distanceVal = is_numeric(str_replace([','], '', (string)$distanceRaw)) ? (float)str_replace([','], '', (string)$distanceRaw) : null;
      $lastDeregTimeRaw = $m['lastdereg'] ?? null;
      $lastDeregReasonRaw = $m['reason'] ?? null;
      $entry = [
        'status'      => $statusRaw,
        'mac'         => $mac,
        'family'      => $family,
        'slot'        => $slot,
        'onu_num'     => $onu,
        'description' => null,
        'distance_m'  => $distanceVal,
        'last_dereg_time'   => parse_onu_datetime($lastDeregTimeRaw),
        'last_dereg_reason' => normalize_reason_value($lastDeregReasonRaw),
      ];
    } else {
      $parts = preg_split('/\s{2,}/', $line);
      if(count($parts) < 4) continue;
      $idData = parse_onu_identifier($parts[0]);
      if(!$idData) continue;
      [$family, $slot, $onu] = $idData;
      $statusRaw = strtolower($parts[1] ?? '');
      if($statusRaw !== 'online' && $statusRaw !== 'offline') continue;
      $macRaw = strtoupper(trim($parts[2] ?? ''));
      $mac    = ($macRaw === 'N/A' || $macRaw === '--' || str_starts_with($macRaw, '—')) ? null : (norm_mac($macRaw) ?? null);
      $cursor = 3;
      $description = null;
      $distance = null;
      if(isset($parts[$cursor]) && !is_numeric(str_replace([','], '', $parts[$cursor]))){
        $description = trim($parts[$cursor], "\" ");
        $cursor++;
      }
      if(isset($parts[$cursor]) && is_numeric(str_replace([','], '', $parts[$cursor]))){
        $distance = (float)$parts[$cursor];
        $cursor++;
      }
      if(isset($parts[$cursor])){ // RTT/TQ কলাম (optional)
        $remaining = count($parts) - $cursor;
        if($remaining > 3){
          $maybeRtt = $parts[$cursor];
          if(!preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{2}/', $maybeRtt)){
            $cursor++;
          }
        }
      }
      $lastRegRaw = $parts[$cursor] ?? null; $cursor++;
      $lastDeregTimeRaw = $parts[$cursor] ?? null; $cursor++;
      $lastDeregReasonRaw = $parts[$cursor] ?? null; $cursor++;
      $entry = [
        'status'      => $statusRaw,
        'mac'         => $mac,
        'family'      => $family,
        'slot'        => $slot,
        'onu_num'     => $onu,
        'description' => $description === '' || $description === '—' ? null : $description,
        'distance_m'  => $distance,
        'last_dereg_time'   => parse_onu_datetime($lastDeregTimeRaw),
        'last_dereg_reason' => normalize_reason_value($lastDeregReasonRaw),
      ];
    }
    if(!$entry) continue;
    $key    = canonical_spo_key($family, $slot, $onu);
    if($key) $mapByKey[$key] = $entry;
    if($mac) $mapByMac[$mac] = $entry;
  }
  return ['byKey' => $mapByKey, 'byMac' => $mapByMac];
}

function parse_onu_rx_metrics(string $txt): array {
  $map = [];
  // প্রথমে পুরো টেক্সটে গ্লোবাল প্যাটার্ন খুঁজে দেখি (মাল্টি-লাইন, ভ্যারিয়েবল স্পেসিং)
  $rxPattern = '/(EPON|GPON)0\/(\d+):(\d+)[^\r\n]*?(-?\d+(?:\.\d+)?)[^\r\n]*?(-?\d+(?:\.\d+)?)[^\r\n]*?(-?\d+(?:\.\d+)?)[^\r\n]*?(-?\d+(?:\.\d+)?)[^\r\n]*?(-?\d+(?:\.\d+)?)/i';
  if(preg_match_all($rxPattern, $txt, $m, PREG_SET_ORDER)){
    foreach($m as $row){
      $family = strtoupper($row[1]);
      $slot   = (int)$row[2];
      $onu    = (int)$row[3];
      $rxVal  = is_numeric($row[8]) ? (float)$row[8] : null;
      $key = canonical_spo_key($family, $slot, $onu);
      if($key) $map[$key] = $rxVal;
    }
  }
  // যদি এখনও খালি থাকে, প্রতি লাইন স্প্লিট করে শেষ কলাম ধরে চেষ্টা
  if(!$map){
    $lines = preg_split('/\R/', $txt);
    foreach($lines as $line){
      $line = trim($line);
      if($line === '' || (stripos($line,'EPON') === false && stripos($line,'GPON') === false)) continue;
      // কন্ট্রোল ক্যারেক্টার বাদ
      $line = preg_replace('/[^\PC\s]/u', '', $line);
      $parts = preg_split('/\s+/', $line);
      if(count($parts) < 2) continue;
      $idData = parse_onu_identifier($parts[0]);
      if(!$idData) continue;
      [$family, $slot, $onu] = $idData;
      $key = canonical_spo_key($family, $slot, $onu);
      if(!$key) continue;
      $rxRaw = $parts[count($parts)-1] ?? null; // শেষ ইনডেক্সেই RX
      $map[$key] = is_numeric($rxRaw) ? (float)$rxRaw : null;
    }
  }
  return $map;
}

function parse_onu_description_cli(string $txt): array {
  $map = [];
  $current = null;
  $lines = preg_split('/\R/', $txt);
  foreach($lines as $line){
    $line = trim($line);
    if($line === '') continue;
    if(preg_match('/show\s+onu\s+(\d+)\s+description/i', $line, $m)){
      $current = (int)$m[1];
      continue;
    }
    if($current !== null && preg_match('/^description\s*:?\s*(.+)$/i', $line, $m)){
      $value = trim($m[1], "\" ");
      if($value === '--' || strcasecmp($value, 'n/a') === 0){
        $value = '';
      }
      $map[$current] = $value;
      $current = null;
      continue;
    }
    if($current !== null && stripos($line, 'msg:') === 0){
      $current = null;
      continue;
    }
    if(preg_match('/[>#]\s*$/', $line)){
      $current = null;
    }
  }
  return $map;
}

function fetch_onu_descriptions_for_ports(string $host, int $telnetPort, string $username, string $password, string $enablePass, array $portOnuMap, bool $debugMode, ?int $oltId, array &$errorBucket): array {
  global $descTimeout;
  $results = [];
  foreach($portOnuMap as $key => $info){
    $slot = $info['slot'] ?? null;
    if($slot === null || empty($info['onus'])) continue;
    $family = strtolower($info['family'] ?? 'epon');
    $familyCmd = $family === 'gpon' ? 'gpon' : 'epon';
    $iface = "0/{$slot}";
    $commands = ['configure terminal', "interface {$familyCmd} {$iface}"];
    foreach(array_keys($info['onus']) as $onuId){
      $commands[] = "show onu {$onuId} description";
    }
    $commands[] = 'exit';
    $commands[] = 'exit';
    $resp = telnet_run_commands(
      $host,
      $telnetPort,
      $username,
      $password,
      $commands,
      null,
      true,
      $enablePass,
      $debugMode,
      $descTimeout
    );
    if(!$resp['ok']){
      $label = $oltId ? "OLT {$oltId}" : 'OLT';
      $errorBucket[] = "{$label} ({$host}) desc: ".($resp['error'] ?? 'Unknown error');
      continue;
    }
    $parsed = parse_onu_description_cli($resp['output'] ?? '');
    if($parsed){
      $results[$key] = $parsed;
    }
  }
  return $results;
}

function parse_onu_mac_address_tables(string $txt): array {
  $map = [];
  $lines = preg_split('/\R/', $txt);
  $current = null;
  foreach($lines as $line){
    $trim = trim($line);
    if(preg_match('/show\s+onu\s+(\d+)\s+mac-address-table/i', $line, $m)){
      $current = (int)$m[1];
      continue;
    }
    if($current === null) continue;
    if($trim === '' || stripos($trim, 'mac address table') === 0) continue;
    if(strpos($trim, '----') === 0) continue;
    if(stripos($trim, 'index') === 0) continue;
    if(stripos($trim, 'total addresses') === 0){
      $current = null;
      continue;
    }
    if(preg_match('/[>#]\s*$/', $trim)){
      $current = null;
      continue;
    }
    if(preg_match('/^\d+\s+(\d+)\s+([0-9a-f:\.-]+)\s+([A-Z]+0\/\d+)\s+(\d+)/i', $line, $m)){
      $vlan = is_numeric($m[1]) ? (int)$m[1] : null;
      $mac = norm_mac($m[2]);
      if(!$mac) continue;
      $map[$current][] = [
        'mac'  => $mac,
        'vlan' => $vlan,
      ];
      continue;
    }
  }
  return $map;
}

function fetch_onu_client_mac_tables(string $host, int $telnetPort, string $username, string $password, string $enablePass, array $portOnuMap, bool $debugMode, ?int $oltId, array &$errorBucket, int $timeout): array {
  $results = [];
  foreach($portOnuMap as $info){
    $slot = $info['slot'] ?? null;
    if($slot === null || empty($info['onus'])) continue;
    $family = strtoupper($info['family'] ?? 'EPON');
    $familyCmd = strtolower($family) === 'gpon' ? 'gpon' : 'epon';
    $iface = "0/{$slot}";
    $commands = ['configure terminal', "interface {$familyCmd} {$iface}"];
    foreach(array_keys($info['onus']) as $onuId){
      $commands[] = "show onu {$onuId} mac-address-table";
    }
    $commands[] = 'exit';
    $commands[] = 'exit';
    $resp = telnet_run_commands(
      $host,
      $telnetPort,
      $username,
      $password,
      $commands,
      null,
      true,
      $enablePass,
      $debugMode,
      $timeout
    );
    if(!$resp['ok']){
      $label = $oltId ? "OLT {$oltId}" : 'OLT';
      $errorBucket[] = "{$label} {$family} {$iface} ক্লায়েন্ট MAC টেবিল পড়তে ব্যর্থ: ".($resp['error'] ?? 'অজানা ত্রুটি');
      continue;
    }
    $parsed = parse_onu_mac_address_tables($resp['output'] ?? '');
    if(!$parsed) continue;
    foreach($parsed as $onuId => $rows){
      if(!$rows) continue;
      $key = canonical_spo_key($family, $slot, (int)$onuId);
      if(!$key) continue;
      $results[$key] = [
        'family' => $family,
        'slot'   => (int)$slot,
        'onu'    => (int)$onuId,
        'rows'   => array_values($rows),
      ];
    }
  }
  return $results;
}

// ======================
// PPPoE সেশন থেকে ক্লায়েন্ট ম্যাপিং ও ক্যাশ আপডেট
// ======================
function run_pppoe_linking(PDO $db, bool $isCli, bool $debugMode): array {
  $logs = [];
  $errors = [];
  $stats = [
    'ok' => true,
    'lock_acquired' => false,
    'routers_processed' => 0,
    'sessions_read' => 0,
    'matched_clients' => 0,
    'router_mac_updates' => 0,
    'olt_link_updates' => 0,
  ];

  $log = static function(string $msg) use ($isCli, &$logs): void {
    $logs[] = $msg;
    if($isCli){
      echo $msg.PHP_EOL;
    }
  };

  try{
    $lockStmt = $db->query("SELECT GET_LOCK('cron_pppoe_olt_link', 1)");
    $lock = (int)($lockStmt->fetchColumn() ?? 0);
  }catch(Throwable $e){
    $stats['ok'] = false;
    $errors[] = 'lock_failed: '.$e->getMessage();
    if($errors) $stats['errors'] = $errors;
    if($debugMode) $stats['logs'] = $logs;
    return $stats;
  }

  if($lock !== 1){
    $stats['ok'] = false;
    $errors[] = 'lock_busy';
    if($errors) $stats['errors'] = $errors;
    if($debugMode) $stats['logs'] = $logs;
    return $stats;
  }
  $stats['lock_acquired'] = true;

  try{
    try{
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }catch(Throwable $e){}

    $cols = $db->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $hasStatus = in_array('status', $cols, true);
    $hasType = in_array('type', $cols, true);

    $sqlRouters = "SELECT id, name, ip, username, password, api_port FROM routers WHERE 1";
    if($hasType) { $sqlRouters .= " AND type='mikrotik'"; }
    if($hasStatus){ $sqlRouters .= " AND status=1"; }
    $st = $db->prepare($sqlRouters);
    $st->execute();
    $routers = $st->fetchAll(PDO::FETCH_ASSOC);

    if(!$routers){
      if($debugMode || $isCli){
        $log("No routers found.");
      }
      return $stats;
    }

    $ridList = array_map(fn($r) => $r['id'], $routers);
    $placeholders = implode(',', array_fill(0, count($ridList), '?'));
    $clientIdx = [];
    $sqlC = "SELECT id, router_id, pppoe_id, router_mac FROM clients WHERE router_id IN ($placeholders)";
    $stc = $db->prepare($sqlC);
    $stc->execute($ridList);
    while($c = $stc->fetch(PDO::FETCH_ASSOC)){
      $rid = (string)$c['router_id'];
      $pp  = trim((string)$c['pppoe_id']);
      if($pp === '') continue;
      $clientIdx[$rid][$pp] = [
        'id' => (int)$c['id'],
        'router_mac' => trim((string)($c['router_mac'] ?? '')),
      ];
    }

    $updRouterMac = $db->prepare("
      UPDATE clients
         SET router_mac = :mac, updated_at = NOW()
       WHERE id = :id
         AND (router_mac IS NULL OR router_mac = '' OR router_mac <> :mac)
    ");
    $selRouterMacOwner = $db->prepare("SELECT id FROM clients WHERE router_mac = :mac LIMIT 1");

  $updCacheClient = $db->prepare("
      UPDATE olt_mac_cache
         SET client_id = :client_id
       WHERE (client_id IS NULL OR client_id = 0)
         AND REPLACE(LOWER(CONVERT(mac USING utf8mb4)),':','') = :mac_clean
    ");

    $selLatest = $db->prepare("
      SELECT c.olt_id, c.port, c.onu, o.vendor
        FROM olt_mac_cache c
        LEFT JOIN olts o ON o.id = c.olt_id
       WHERE REPLACE(LOWER(CONVERT(c.mac USING utf8mb4)),':','') = :mac_clean
       ORDER BY c.learned_at DESC
       LIMIT 1
    ");

    $updClientOlt = $db->prepare("
      UPDATE clients
         SET olt_id = ?, olt_vendor = ?, olt_port = ?, olt_onu = ?, last_linked_at = NOW()
       WHERE id = ?
    ");
    $updCallerMac = $db->prepare("
      UPDATE clients
         SET caller_mac = :mac, updated_at = NOW()
       WHERE id = :id
         AND (caller_mac IS NULL OR caller_mac = '' OR caller_mac <> :mac)
    ");

    $macToClient = [];

    foreach($routers as $r){
      $stats['routers_processed']++;
      $rid = (string)$r['id'];
      if($debugMode || $isCli){
        $log("Router #{$r['id']} {$r['name']} ({$r['ip']}): connecting...");
      }

      $API = new RouterosAPI();
      $API->port = intval($r['api_port'] ?: 8728);
      $API->timeout = 5;

      if(!$API->connect($r['ip'], $r['username'], $r['password'])){
        $errors[] = "router_{$rid}_connect_failed";
        if($debugMode || $isCli){
          $log("  ERROR: connect failed");
        }
        continue;
      }

      $API->write('/ppp/active/print', false);
      $API->write('.proplist=name,caller-id');
      $resp = $API->read();
      $API->disconnect();

      if(!is_array($resp)){
        if($debugMode || $isCli){
          $log("  WARN: no active list.");
        }
        continue;
      }

      $idx = $clientIdx[$rid] ?? [];
      foreach($resp as $row){
        $pp = trim((string)($row['name'] ?? ''));
        if($pp === '') continue;
        $stats['sessions_read']++;

        $cid = trim((string)($row['caller-id'] ?? ''));
        $mac = norm_mac($cid);
        if(!$mac) continue;

        $match = $idx[$pp] ?? null;
        if(!$match) continue;

        $stats['matched_clients']++;
        $clientId = (int)$match['id'];

        $macClean = strtolower(str_replace(':', '', $mac));

        try{
          $selRouterMacOwner->execute([':mac' => $mac]);
          $ownerId = (int)($selRouterMacOwner->fetchColumn() ?: 0);
        }catch(Throwable $e){
          $ownerId = 0;
        }

        if($ownerId && $ownerId !== $clientId){
          // MAC already belongs to another client; keep existing owner to avoid constraint violation.
          $macToClient[$macClean] = $ownerId;
          $errors[] = "router_mac_in_use: {$mac} by client {$ownerId}, skipped update for client {$clientId}";
        } else {
          try{
            $updRouterMac->execute([':mac' => $mac, ':id' => $clientId]);
            if($updRouterMac->rowCount() > 0){
              $stats['router_mac_updates']++;
            }
          }catch(Throwable $e){
            $errors[] = "router_mac_update_failed: {$mac} client {$clientId} - ".$e->getMessage();
          }
          try{
            $updCallerMac->execute([':mac' => $mac, ':id' => $clientId]);
          }catch(Throwable $e){
            // caller_mac unique না হলে error আসবে না, তাই স্কিপ
          }
          $macToClient[$macClean] = $clientId;
        }
      }
    }

    foreach($macToClient as $macClean => $clientId){
      $updCacheClient->execute([':client_id' => $clientId, ':mac_clean' => $macClean]);
      $selLatest->execute([':mac_clean' => $macClean]);
      $row = $selLatest->fetch(PDO::FETCH_ASSOC);
      if(!$row) continue;
      $oltId = (int)($row['olt_id'] ?? 0);
      $portNorm = normalize_port_label($row['port'] ?? '');
      $onuNum = onu_numeric($row['onu'] ?? null);
      if($oltId > 0 && $portNorm !== '—' && $onuNum !== PHP_INT_MAX){
        $vendor = trim((string)($row['vendor'] ?? ''));
        $updClientOlt->execute([$oltId, $vendor !== '' ? $vendor : null, $portNorm, $onuNum, $clientId]);
        $stats['olt_link_updates']++;
      }
    }
  }catch(Throwable $e){
    $stats['ok'] = false;
    $errors[] = 'pppoe_link_failed: '.$e->getMessage();
  }finally{
    try{
      $db->query("SELECT RELEASE_LOCK('cron_pppoe_olt_link')");
    }catch(Throwable $e){}
  }

  if($errors) $stats['errors'] = $errors;
  if($debugMode) $stats['logs'] = $logs;

  return $stats;
}

// ======================
// ইনপুট ফিল্টার (OLT নির্বাচন)
// ======================
$filterOlt = isset($request['olt_id']) ? (int)$request['olt_id'] : 0;

$query = "SELECT id,name,vendor,host,telnet_port,ssh_port,username,password,enable_password,is_active
          FROM olts";
$params = [];
if($filterOlt > 0){
  $query .= " WHERE id = ?";
  $params[] = $filterOlt;
} else {
  $query .= " WHERE is_active = 1";
}
$query .= " ORDER BY id ASC";
$st = $db->prepare($query);
$st->execute($params);
$olts = $st->fetchAll(PDO::FETCH_ASSOC);

$summary = [
  'ok'=>true,
  'seen'=>0,
  'inserted'=>0,
  'updated'=>0,
  'per_olt'=>[],
  'errors'=>[]
];

if($debugMode){
  $summary['debug'] = [];
}

$prepOk = true;
if(!$olts){
  $summary['ok'] = false;
  $summary['errors'][] = 'রিফ্রেশ চালানোর জন্য কোনো OLT পাওয়া যায়নি।';
  $prepOk = false;
}

if($prepOk){
  try{
  $ins = $db->prepare("INSERT INTO olt_mac_cache (olt_id,vendor,client_id,clients,client_mac,client_mac_vlan,client_mac_learned_at,mac,vlan,port,onu,status,description,distance_m,rx_power_dbm,last_dereg_reason,last_dereg_time,learned_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE
                         vendor=VALUES(vendor),
                         client_id=VALUES(client_id),
                         clients=VALUES(clients),
                         client_mac=VALUES(client_mac),
                         client_mac_vlan=VALUES(client_mac_vlan),
                         client_mac_learned_at=VALUES(client_mac_learned_at),
                         vlan=VALUES(vlan),
                         port=VALUES(port),
                         onu=VALUES(onu),
                         status=VALUES(status),
                         description=VALUES(description),
                         distance_m=VALUES(distance_m),
                         rx_power_dbm=VALUES(rx_power_dbm),
                         last_dereg_reason=VALUES(last_dereg_reason),
                         last_dereg_time=VALUES(last_dereg_time),
                         learned_at=VALUES(learned_at)");
    $touchLearnedAt = $db->prepare("UPDATE olt_mac_cache SET learned_at = NOW() WHERE olt_id = ? AND mac = ?");
  }catch(PDOException $e){
    if(strpos($e->getMessage(),'42S02')!==false){
      $summary['ok'] = false;
      $summary['errors'][] = 'Table `olt_mac_cache` অনুপস্থিত। অনুগ্রহ করে এটি তৈরি করে আবার চেষ্টা করুন।';
      $prepOk = false;
    } else {
      throw $e;
    }
  }
}

// MAC -> client_id ম্যাপ (caller/router/ap MAC থেকে)
$clientMacMap = $prepOk ? load_client_mac_map($db) : [];

// ======================
// মূল প্রসেসিং লুপ (OLT ধরে ধরে রিফ্রেশ)
// ======================
if($prepOk){
  foreach($olts as $olt){
  if ((microtime(true) - $overallStartedAt) > $overallLimitSec) {
    $summary['errors'][] = 'overall_timeout_before_olt';
    $summary['ok'] = false;
    break;
  }
  if($isCli){
    echo "[OLT {$olt['id']}] {$olt['host']} - refreshing...".PHP_EOL;
  }
  $host = trim((string)$olt['host']);
  $port = (int)($olt['telnet_port'] ?? $olt['ssh_port'] ?? 23);
  if($port < 1) $port = 23;
  $user = (string)$olt['username'];
  $encPass = $olt['password'] ?? '';
  $pass = decrypt_olt_secret($encPass);
  if($pass === null){
    $summary['errors'][] = "OLT {$olt['id']} ({$host}): লগইন পাসওয়ার্ড ডিক্রিপ্ট করা যাচ্ছে না";
    continue;
  }
  $encEnable = $olt['enable_password'] ?? '';
  $enable = decrypt_olt_secret($encEnable);
  if($enable === null){
    $enable = $pass;
  }

  if($host === '' || $user === ''){
    $summary['errors'][] = "OLT {$olt['id']}: লগইন তথ্য অসম্পূর্ণ";
    continue;
  }

  $statusByKey = [];
  $statusByMac = [];
  $diagByKey = [];
  $oltStarted = microtime(true);
  $statusOut = telnet_run_commands(
    $host, $port, $user, $pass,
    ['configure terminal','show onu status all','exit'],
    null, true, $enable, $debugMode, $statusTimeout
  );
  if ((microtime(true)-$oltStarted) > $overallLimitSec) {
    $summary['errors'][] = "olt_{$olt['id']}_timeout_status";
    $summary['ok'] = false;
    break;
  }
  if($statusOut['ok']){
    $statusData = parse_onu_statuses($statusOut['output'] ?? '');
    $statusByKey = $statusData['byKey'];
    $statusByMac = $statusData['byMac'];
    if($debugMode){
      $summary['debug'][] = [
        'olt_id' => $olt['id'],
        'host' => $host,
        'raw_status_sample' => mb_substr($statusOut['output'] ?? '', 0, 2000),
      ];
    }
  } else {
    $summary['errors'][] = "OLT {$olt['id']} ({$host}) স্ট্যাটাস কমান্ড ব্যর্থ: {$statusOut['error']}";
  }

  if($diagEnabled){
    $diagByKey = [];
    $diagOutLog = null;
    $diagCmdAttempts = [
      ['label' => 'config', 'cmds' => ['configure terminal','show onu opm-diag all','exit']],
      ['label' => 'direct', 'cmds' => ['show onu opm-diag all']],
    ];
    foreach($diagCmdAttempts as $attempt){
      $diagOut = telnet_run_commands(
        $host, $port, $user, $pass,
        $attempt['cmds'],
        null, true, $enable, $debugMode, $diagTimeout
      );
      $diagOutLog = $diagOutLog ?: $diagOut;
      if($diagOut['ok']){
        $parsed = parse_onu_rx_metrics($diagOut['output'] ?? '');
        if($parsed){
          $diagByKey = $parsed;
          $diagOutLog = $diagOut;
          break;
        }
      }
    }
    $summary['diag_count'][$olt['id']] = count($diagByKey);
    if(empty($diagByKey)){
      if($diagOutLog && ($diagOutLog['ok'] ?? false)){
        $summary['errors'][] = "OLT {$olt['id']} ({$host}) opm-diag parsed 0 rows (config/direct fallback tried); sample: ".substr(str_replace("\n",' ',$diagOutLog['output'] ?? ''),0,200);
        if($isCli){
          $sample = substr($diagOutLog['output'] ?? '', 0, 400);
          echo "[OLT {$olt['id']}] opm-diag raw sample (first 400 chars):".PHP_EOL.$sample.PHP_EOL;
        }
      } else {
        $err = $diagOutLog['error'] ?? 'অজানা ত্রুটি';
        $summary['errors'][] = "OLT {$olt['id']} ({$host}) অপটিক-পাওয়ার কমান্ড ব্যর্থ: {$err}";
      }
    } else {
      if($debugMode){
        $summary['debug'][] = [
          'olt_id' => $olt['id'],
          'host' => $host,
          'raw_diag_sample' => mb_substr($diagOutLog['output'] ?? '', 0, 2000),
        ];
      }
    }
  }

  $cmds = ['configure terminal','show mac address-table','exit'];
  $out = telnet_run_commands($host, $port, $user, $pass, $cmds, null, true, $enable, $debugMode, $macTimeout);
  if(!$out['ok']){
    $summary['errors'][] = "OLT {$olt['id']} ({$host}): MAC টেবিল কমান্ড ব্যর্থ - {$out['error']}";
    continue;
  }
  if ((microtime(true)-$oltStarted) > $overallLimitSec) {
    $summary['errors'][] = "olt_{$olt['id']}_timeout_mac";
    $summary['ok'] = false;
    break;
  }
  if($debugMode){
    $summary['debug'][] = [
      'olt_id' => $olt['id'],
      'host' => $host,
      'raw_sample' => mb_substr($out['output'] ?? '', 0, 4000),
    ];
  }

  $entries = parse_mac_entries($out['output'] ?? '');
  $seenKeys = [];
  foreach($entries as &$entry){
    $family = strtoupper($entry['family'] ?? 'EPON');
    $slot = $entry['slot'] ?? null;
    $onuNum = $entry['onu_num'] ?? onu_numeric($entry['onu'] ?? null);
    $statusKey = canonical_spo_key($entry['family'] ?? null, $entry['slot'] ?? null, $entry['onu_num'] ?? null);
    if($statusKey) $seenKeys[$statusKey] = true;
    if($statusKey && isset($statusByKey[$statusKey])){
      $entry['status'] = $statusByKey[$statusKey]['status'] ?? null;
      $entry['status'] = normalize_status($entry['status']);
      if(empty($entry['mac']) && !empty($statusByKey[$statusKey]['mac'])){
        $entry['mac'] = $statusByKey[$statusKey]['mac'];
      }
      if(isset($statusByKey[$statusKey]['description'])){
        $entry['description'] = $statusByKey[$statusKey]['description'];
      }
      if(isset($statusByKey[$statusKey]['distance_m'])){
        $entry['distance_m'] = $statusByKey[$statusKey]['distance_m'];
      }
      if(isset($statusByKey[$statusKey]['last_dereg_reason'])){
        $entry['last_dereg_reason'] = $statusByKey[$statusKey]['last_dereg_reason'];
      }
      if(isset($statusByKey[$statusKey]['last_dereg_time'])){
        $entry['last_dereg_time'] = $statusByKey[$statusKey]['last_dereg_time'];
      }
    } elseif(!empty($entry['mac'])){
      $macKey = norm_mac($entry['mac']);
      if($macKey && isset($statusByMac[$macKey])){
        $entry['status'] = $statusByMac[$macKey]['status'] ?? null;
        $entry['status'] = normalize_status($entry['status']);
        if(empty($entry['onu_num']) && !empty($statusByMac[$macKey]['onu_num'])){
          $entry['onu_num'] = $statusByMac[$macKey]['onu_num'];
          $entry['onu'] = 'ONU '.$entry['onu_num'];
        }
        if(empty($statusKey)){
          $statusKey = canonical_spo_key($statusByMac[$macKey]['family'] ?? null, $statusByMac[$macKey]['slot'] ?? null, $statusByMac[$macKey]['onu_num'] ?? null);
          if($statusKey) $seenKeys[$statusKey] = true;
        }
        if(isset($statusByMac[$macKey]['description'])){
          $entry['description'] = $statusByMac[$macKey]['description'];
        }
        if(isset($statusByMac[$macKey]['distance_m'])){
          $entry['distance_m'] = $statusByMac[$macKey]['distance_m'];
        }
        if(isset($statusByMac[$macKey]['last_dereg_reason'])){
          $entry['last_dereg_reason'] = $statusByMac[$macKey]['last_dereg_reason'];
        }
        if(isset($statusByMac[$macKey]['last_dereg_time'])){
          $entry['last_dereg_time'] = $statusByMac[$macKey]['last_dereg_time'];
        }
      }
    }
    $entry['temperature_c'] = null;
    $entry['supply_voltage_v'] = null;
    $entry['tx_bias_ma'] = null;
    $entry['tx_power_dbm'] = null;
    if($diagEnabled && $statusKey && isset($diagByKey[$statusKey])){
      $entry['rx_power_dbm'] = $diagByKey[$statusKey];
    } else {
      $entry['rx_power_dbm'] = null;
    }
  }
  unset($entry);
  $existingRows = load_existing_mac_rows($db, (int)$olt['id']);
  $portOnuMap = [];
  foreach($entries as $idx => &$entry){
    $descVal = trim((string)($entry['description'] ?? ''));
    $macKey = norm_mac($entry['mac'] ?? '');
    if($macKey && (!isset($entry['rx_power_dbm']) || $entry['rx_power_dbm'] === null)){
      $existingRx = $existingRows[$macKey]['rx_power_dbm'] ?? null;
      if($existingRx !== null && $existingRx !== ''){
        $entry['rx_power_dbm'] = $existingRx;
      }
    }
    if($descVal !== '' && $descVal !== '—') continue;
    if($macKey && trim((string)($existingRows[$macKey]['description'] ?? '')) !== ''){
      $entry['description'] = $existingRows[$macKey]['description'];
      continue;
    }
    $family = strtoupper($entry['family'] ?? 'EPON');
    $slot = $entry['slot'] ?? null;
    $onuNum = $entry['onu_num'] ?? onu_numeric($entry['onu'] ?? null);
    if($slot === null || $onuNum === PHP_INT_MAX) continue;
    $mapKey = "{$family}|{$slot}";
    if(!isset($portOnuMap[$mapKey])){
      $portOnuMap[$mapKey] = [
        'family' => $family,
        'slot'   => $slot,
        'onus'   => []
      ];
    }
    $portOnuMap[$mapKey]['onus'][$onuNum] = true;
  }
  unset($entry);
  $descMap = [];
  // rxMode/fullMode উভয়েই বর্ণনা (description) টেনে আনা হবে, যাতে টেবিলের Description কলাম ফাঁকা না থাকে।
  if(($fullMode || $rxMode) && $portOnuMap){
    $errorBucket = &$summary['errors'];
    $descMap = fetch_onu_descriptions_for_ports(
      $host,
      $port,
      $user,
      $pass,
      $enable,
      $portOnuMap,
      $debugMode,
      $olt['id'] ?? null,
      $errorBucket
    );
    if($descMap){
      foreach($entries as &$entry){
        $family = strtoupper($entry['family'] ?? 'EPON');
        $slot = $entry['slot'] ?? null;
        $onuNum = $entry['onu_num'] ?? onu_numeric($entry['onu'] ?? null);
        if($slot === null || $onuNum === PHP_INT_MAX) continue;
        $key = "{$family}|{$slot}";
        if(isset($descMap[$key][$onuNum]) && $descMap[$key][$onuNum] !== ''){
          $entry['description'] = $descMap[$key][$onuNum];
        }
      }
      unset($entry);
    }
  }
  $existingByKey = [];
  foreach($existingRows as $macKey => $row){
    $key = canonical_key_from_row($row);
    if($key) $existingByKey[$key] = $macKey;
  }
  foreach($statusByKey as $key => $info){
    if(isset($seenKeys[$key])) continue;
    if(empty($info['mac'])){
      if(isset($existingByKey[$key])){
        $info['mac'] = $existingByKey[$key];
      } else {
        continue;
      }
    }
    $family = $info['family'] ?? 'EPON';
    $slotInt = $info['slot'] ?? null;
    $slotLabel = $slotInt !== null ? str_pad($slotInt, 2, '0', STR_PAD_LEFT) : null;
    $portLabel = $slotLabel ? "{$family} 0/{$slotLabel}" : null;
    $entries[] = [
      'mac'  => $info['mac'],
      'vlan' => null,
      'port' => $portLabel,
      'onu'  => $info['onu_num'] ? ('ONU '.$info['onu_num']) : null,
      'onu_num' => $info['onu_num'],
      'family'  => $family,
      'slot'    => $slotInt,
      'status'  => normalize_status($info['status'] ?? null),
      'last_dereg_reason' => $info['last_dereg_reason'] ?? null,
      'last_dereg_time'   => $info['last_dereg_time'] ?? null,
      'description' => $info['description'] ?? null,
      'distance_m'  => $info['distance_m'] ?? null,
      'temperature_c'    => null,
      'supply_voltage_v' => null,
      'tx_bias_ma'       => null,
      'tx_power_dbm'     => null,
      'rx_power_dbm'     => ($diagEnabled && isset($diagByKey[$key])) ? $diagByKey[$key] : null,
      'learned_at' => date('Y-m-d H:i:s'),
    ];
  }
  usort($entries, function($a,$b){
    $pa = $a['port'] ?? '';
    $pb = $b['port'] ?? '';
    $getKey = function($p){
      if(preg_match('/(GPON|EPON)\s*0\/(\d+)/i', $p, $m)){
        return sprintf('%s-%02d', strtoupper($m[1]), (int)$m[2]);
      }
      if(preg_match('/PON\s*0\/(\d+)/i', $p, $m)){
        return sprintf('PON-%02d', (int)$m[1]);
      }
      return $p;
    };
    return strcmp($getKey($pa), $getKey($pb));
  });
  $summary['per_olt'][$olt['id']] = count($entries);
  $summary['seen'] += count($entries);

  $clientMacTargets = [];
  $clientMacEnriched = [];
  foreach($entries as $entry){
    $family = strtoupper($entry['family'] ?? '');
    $slot   = $entry['slot'] ?? null;
    $onuNum = $entry['onu_num'] ?? onu_numeric($entry['onu'] ?? null);
    if($family === '' || $slot === null || $onuNum === PHP_INT_MAX) continue;
    $targetKey = "{$family}|{$slot}";
    if(!isset($clientMacTargets[$targetKey])){
      $clientMacTargets[$targetKey] = [
        'family' => $family,
        'slot'   => (int)$slot,
        'onus'   => []
      ];
    }
    $clientMacTargets[$targetKey]['onus'][$onuNum] = true;
  }
  // ক্লায়েন্ট MAC ডাটা একত্রীকরণ (olt_onu_client_macs টেবিল আর ব্যবহার নেই)
  if($fullMode && $clientMacTargets){
    $clientMacData = fetch_onu_client_mac_tables(
      $host,
      $port,
      $user,
      $pass,
      $enable,
      $clientMacTargets,
      $debugMode,
      $olt['id'] ?? null,
      $summary['errors'],
      $clientMacTimeout
    );
    if($clientMacData){
      foreach($clientMacData as $entry){
        $key = canonical_spo_key($entry['family'] ?? null, $entry['slot'] ?? null, $entry['onu'] ?? null);
        if(!$key || empty($entry['rows'])) continue;
        $first = $entry['rows'][0];
        $m = norm_mac($first['mac'] ?? '');
        $clientMacEnriched[$key] = [
          'client_mac' => $m,
          'client_mac_vlan' => isset($first['vlan']) && $first['vlan'] !== '' ? (int)$first['vlan'] : null,
          'client_mac_learned_at' => date('Y-m-d H:i:s'),
        ];
      }
    }
  }

  // olt_mac_cache আপডেট/ইনসার্ট
  foreach($entries as $row){
    $macValue = $row['mac'] ?? '';
    $macKey = strtolower(trim((string)$macValue));
    if($macKey === '') continue;
    $row['status'] = normalize_status($row['status'] ?? null);
    if(
      (!isset($row['description']) || $row['description'] === null || trim((string)$row['description']) === '' || $row['description'] === '—') &&
      isset($existingRows[$macKey]['description']) &&
      trim((string)$existingRows[$macKey]['description']) !== ''
    ){
      $row['description'] = $existingRows[$macKey]['description'];
    }
    if(empty($row['last_dereg_reason']) && !empty($existingRows[$macKey]['last_dereg_reason'] ?? null)){
      $row['last_dereg_reason'] = $existingRows[$macKey]['last_dereg_reason'];
    }
    if(empty($row['last_dereg_time']) && !empty($existingRows[$macKey]['last_dereg_time'] ?? null)){
      $row['last_dereg_time'] = $existingRows[$macKey]['last_dereg_time'];
    }
    $clientId = null;
    if($macKey !== '' && isset($clientMacMap[$macKey])){
      $clientId = (int)$clientMacMap[$macKey];
    } elseif(isset($existingRows[$macKey]['client_id'])){
      $clientId = (int)$existingRows[$macKey]['client_id'];
    }
    $clientLabel = $clientId ? (string)$clientId : ($existingRows[$macKey]['clients'] ?? null);
    $payload = [
      'client_id' => $clientId ?: null,
      'clients' => $clientLabel !== '' ? $clientLabel : null,
      'client_mac' => null,
      'client_mac_vlan' => null,
      'client_mac_learned_at' => null,
      'mac' => $macValue,
      'vlan' => $row['vlan'] ?? null,
      'port' => $row['port'] ?? null,
      'onu'  => $row['onu'] ?? null,
      'status' => $row['status'] ?? null,
      'description' => $row['description'] ?? null,
      'distance_m'  => $row['distance_m'] ?? null,
      'rx_power_dbm' => $row['rx_power_dbm'] ?? null,
      'last_dereg_reason' => $row['last_dereg_reason'] ?? null,
      'last_dereg_time'   => $row['last_dereg_time'] ?? null,
      'vendor' => $olt['vendor'] ?? null,
    ];
    $cmKey = canonical_spo_key($row['family'] ?? null, $row['slot'] ?? null, $row['onu_num'] ?? onu_numeric($row['onu'] ?? null));
    if($cmKey && isset($clientMacEnriched[$cmKey])){
      $payload['client_mac'] = $clientMacEnriched[$cmKey]['client_mac'];
      $payload['client_mac_vlan'] = $clientMacEnriched[$cmKey]['client_mac_vlan'];
      $payload['client_mac_learned_at'] = $clientMacEnriched[$cmKey]['client_mac_learned_at'];
    }
    if(!$payload['client_mac'] && $payload['client_id']){
      $payload['client_mac'] = norm_mac($macValue);
    }

    if($payload['client_id']){
      $learnedAt = $existingRows[$macKey]['learned_at'] ?? date('Y-m-d H:i:s');
      sync_onu_inventory_from_payload($db, (int)$olt['id'], $payload, $learnedAt);
      $onuNumNorm = onu_numeric($payload['onu'] ?? null);
      $oltVendor = $olt['vendor'] ?? null;
      try{
        $updateClientLinkFromCache ??= $db->prepare("UPDATE clients
                                                     SET olt_id=?, olt_vendor=?, olt_port=?, olt_onu=?, caller_mac=IFNULL(NULLIF(caller_mac,''), ?), last_linked_at=NOW()
                                                     WHERE id=?");
        $updateClientLinkFromCache->execute([
          (int)$olt['id'],
          $oltVendor !== '' ? $oltVendor : null,
          $payload['port'] ?? null,
          $onuNumNorm !== PHP_INT_MAX ? $onuNumNorm : null,
          $macValue ?: null,
          (int)$payload['client_id'],
        ]);
      }catch(Throwable $e){
        $summary['errors'][] = "OLT {$olt['id']}: client link sync ব্যর্থ - ".$e->getMessage();
      }
    }
    // rx_power_dbm ফাঁকা থাকলে কেবল পূর্বের non-zero মান রাখার চেষ্টা করি (নইলে NULL)
    if($payload['rx_power_dbm'] === null){
      $prevRx = $existingRows[$macKey]['rx_power_dbm'] ?? null;
      if(is_numeric($prevRx) && (float)$prevRx !== 0.0){
        $payload['rx_power_dbm'] = (float)$prevRx;
      }
    }
    $comparePayload = $payload;
    if(isset($existingRows[$macKey]) && mac_payloads_equal($existingRows[$macKey], $comparePayload)){
      try{
        $touchLearnedAt->execute([$olt['id'], $macValue]);
        $existingRows[$macKey]['learned_at'] = date('Y-m-d H:i:s');
      }catch(PDOException $e){
        $summary['errors'][] = "OLT {$olt['id']}: learned_at আপডেট ব্যর্থ - ".$e->getMessage();
      }
      continue;
    }
    // Default fallbacks to avoid NULL persistence (DB write only)
    $payload['status'] = $payload['status'] ?? 'unknown';
    $payload['description'] = $payload['description'] ?? '';
    $payload['distance_m'] = is_numeric($payload['distance_m']) ? (float)$payload['distance_m'] : 0;
    $payload['rx_power_dbm'] = is_numeric($payload['rx_power_dbm']) ? (float)$payload['rx_power_dbm'] : null;
    $payload['last_dereg_reason'] = $payload['last_dereg_reason'] ?? '';
    $ldt = trim((string)($payload['last_dereg_time'] ?? ''));
    $payload['last_dereg_time'] = $ldt === '' ? null : $ldt;
    if($payload['vlan'] === null || $payload['vlan'] === '') $payload['vlan'] = 0;
    if($payload['port'] === null) $payload['port'] = '';
    if($payload['onu'] === null) $payload['onu'] = '';
    if($payload['vendor'] === null) $payload['vendor'] = '';
    if($payload['client_mac_vlan'] !== null && $payload['client_mac_vlan'] !== '') $payload['client_mac_vlan'] = (int)$payload['client_mac_vlan'];

    try{
      $ins->execute([
        $olt['id'],
        $payload['vendor'],
        $payload['client_id'],
        $payload['clients'],
        $payload['client_mac'],
        $payload['client_mac_vlan'],
        $payload['client_mac_learned_at'],
        $payload['mac'],
        $payload['vlan'],
        $payload['port'],
        $payload['onu'],
        $payload['status'],
        $payload['description'],
        $payload['distance_m'],
        $payload['rx_power_dbm'],
        $payload['last_dereg_reason'],
        $payload['last_dereg_time'],
      ]);
      $rc = $ins->rowCount();
      if($rc === 1) $summary['inserted']++;
      elseif($rc === 2) $summary['updated']++;
      if($rc > 0){
        $existingRows[$macKey] = $payload;
      }
    }catch(PDOException $e){
      $summary['errors'][] = "OLT {$olt['id']}: ডাটাবেস আপডেট ব্যর্থ - ".$e->getMessage();
      break;
    }
  }
  if($isCli){
    $count = $summary['per_olt'][$olt['id']] ?? 0;
    $diagC = $summary['diag_count'][$olt['id']] ?? 0;
    echo "[OLT {$olt['id']}] done. Entries: {$count} | RX parsed: {$diagC}".PHP_EOL;
  }
}

}

$pppoeStats = ['ok'=>true];
if (!$skipPppoe) {
  if ((microtime(true)-$overallStartedAt) <= $overallLimitSec - 30) {
    $pppoeStats = run_pppoe_linking($db, $isCli, $debugMode);
  } else {
    $pppoeStats = ['ok'=>false,'errors'=>['pppoe_skipped_due_timeout_buffer']];
    $summary['errors'][] = 'pppoe_skipped_due_timeout_buffer';
    $summary['ok'] = false;
  }
} else {
  $pppoeStats = ['ok'=>true,'skipped'=>true];
}

$result = [
  'ok' => ($summary['ok'] ?? false) && ($pppoeStats['ok'] ?? true),
  'olt' => $summary,
  'pppoe_link' => $pppoeStats,
];

// গ্লোবাল লক রিলিজ
try{
  $db->query("SELECT RELEASE_LOCK('cron_olt_mac_refresh')");
}catch(Throwable $e){}

// ======================
// আউটপুট (CLI/ব্রাউজার JSON)
// ======================
echo json_encode($result, JSON_UNESCAPED_UNICODE);
