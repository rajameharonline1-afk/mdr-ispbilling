<?php
if (PHP_SAPI === 'cli') {
  require_once __DIR__ . '/../app/config.php';
  require_once __DIR__ . '/../app/db.php';
  require_once __DIR__ . '/../app/telnet.php';
} else {
  require_once __DIR__ . '/../app/require_login.php';
  require_once __DIR__ . '/../app/telnet.php';
}
require_once __DIR__ . '/../app/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(0);

$db = db();
$debugMode = isset($_GET['debug']);
$mode = strtolower(trim((string)($_GET['mode'] ?? 'fast')));
$fastMode = $mode !== 'full';

function decrypt_olt_secret(?string $ciphertext): ?string {
  if($ciphertext === null || $ciphertext === '') return null;
  $plain = decrypt_password($ciphertext, ENCRYPTION_KEY);
  if($plain === false){
    return null;
  }
  return $plain;
}

function load_existing_mac_rows(PDO $db, int $oltId): array {
  $stmt = $db->prepare("SELECT mac,vlan,port,onu,status,description,distance_m,temperature_c,supply_voltage_v,tx_bias_ma,tx_power_dbm,rx_power_dbm,last_dereg_reason,last_dereg_time,client_id,clients
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
  $fields = ['vlan','port','onu','status','description','distance_m','temperature_c','supply_voltage_v','tx_bias_ma','tx_power_dbm','rx_power_dbm','last_dereg_reason','last_dereg_time','client_id','clients'];
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
    $stmt = $db->query("SELECT id, caller_mac, router_mac, ap_mac FROM clients");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
      $cid = (int)($row['id'] ?? 0);
      if(!$cid) continue;
      foreach(['caller_mac','router_mac','ap_mac'] as $f){
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
    'temperature_c'    => "ADD COLUMN temperature_c decimal(10,2) DEFAULT NULL AFTER distance_m",
    'supply_voltage_v' => "ADD COLUMN supply_voltage_v decimal(10,2) DEFAULT NULL AFTER temperature_c",
    'tx_bias_ma'       => "ADD COLUMN tx_bias_ma decimal(10,2) DEFAULT NULL AFTER supply_voltage_v",
    'tx_power_dbm'     => "ADD COLUMN tx_power_dbm decimal(10,2) DEFAULT NULL AFTER tx_bias_ma",
    'rx_power_dbm'     => "ADD COLUMN rx_power_dbm decimal(10,2) DEFAULT NULL AFTER tx_power_dbm",
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

ensure_olt_mac_cache_schema($db);
function ensure_onu_client_mac_table(PDO $db): void {
  static $checked = false;
  if($checked) return;
  try{
    $db->query("SELECT 1 FROM olt_onu_client_macs LIMIT 1");
    $checked = true;
    return;
  } catch(PDOException $e){
    if(strpos($e->getMessage(), '42S02') === false){
      throw $e;
    }
  }
  $ddl = "CREATE TABLE IF NOT EXISTS `olt_onu_client_macs` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `olt_id` int unsigned NOT NULL,
            `family` varchar(8) NOT NULL,
            `slot` smallint unsigned NOT NULL,
            `onu` smallint unsigned NOT NULL,
            `vlan` int unsigned DEFAULT NULL,
            `mac` varchar(32) NOT NULL,
            `learned_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_onu_mac` (`olt_id`,`family`,`slot`,`onu`,`mac`),
            KEY `idx_mac_lookup` (`mac`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  $db->exec($ddl);
  $checked = true;
}
ensure_onu_client_mac_table($db);

function norm_mac(string $mac): ?string {
  $mac = strtolower(trim($mac));
  $mac = preg_replace('/[^0-9a-f]/', '', $mac);
  if(strlen($mac)!==12) return null;
  return implode(':', str_split($mac, 2));
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
      'vlan' => null,
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

function onu_numeric($onu): int {
  if($onu === null) return PHP_INT_MAX;
  if(is_int($onu)) return $onu;
  if(is_string($onu) && preg_match('/(\d+)/', $onu, $m)){
    return (int)$m[1];
  }
  return PHP_INT_MAX;
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
  $lines = preg_split('/\R/', $txt);
  foreach($lines as $line){
    $line = trim($line);
    if($line === '' || (stripos($line,'EPON') === false && stripos($line,'GPON') === false)) continue;
    $parts = preg_split('/\s{2,}/', $line);
    if(count($parts) < 6) continue;
    $idData = parse_onu_identifier($parts[0]);
    if(!$idData) continue;
    [$family, $slot, $onu] = $idData;
    $key = canonical_spo_key($family, $slot, $onu);
    if(!$key) continue;
    $map[$key] = is_numeric($parts[5] ?? null) ? (float)$parts[5] : null;
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
      20
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

function fetch_onu_client_mac_tables(string $host, int $telnetPort, string $username, string $password, string $enablePass, array $portOnuMap, bool $debugMode, ?int $oltId, array &$errorBucket): array {
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
      25
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

function save_onu_client_mac_rows(PDO $db, int $oltId, array $data): void {
  if(!$data) return;
  ensure_onu_client_mac_table($db);
  $del = $db->prepare("DELETE FROM olt_onu_client_macs WHERE olt_id = ? AND family = ? AND slot = ? AND onu = ?");
  $ins = $db->prepare("INSERT INTO olt_onu_client_macs (olt_id,family,slot,onu,vlan,mac,learned_at)
                       VALUES (?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE vlan=VALUES(vlan), learned_at=VALUES(learned_at)");
  foreach($data as $entry){
    $family = strtoupper($entry['family'] ?? '');
    $slot   = (int)($entry['slot'] ?? 0);
    $onu    = (int)($entry['onu'] ?? 0);
    if($family === '' || $slot <= 0 || $onu <= 0){
      continue;
    }
    $del->execute([$oltId, $family, $slot, $onu]);
    foreach($entry['rows'] as $row){
      $mac = norm_mac($row['mac'] ?? '');
      if(!$mac) continue;
      $vlan = isset($row['vlan']) && $row['vlan'] !== '' ? (int)$row['vlan'] : null;
      $ins->execute([$oltId, $family, $slot, $onu, $vlan, $mac]);
    }
  }
}

$filterOlt = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : 0;

$query = "SELECT id,name,host,telnet_port,ssh_port,username,password,enable_password,is_active
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

if(!$olts){
  echo json_encode(['ok'=>false,'error'=>'রিফ্রেশ চালানোর জন্য কোনো OLT পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE); exit;
}

try{
  $ins = $db->prepare("INSERT INTO olt_mac_cache (olt_id,client_id,clients,mac,vlan,port,onu,status,description,distance_m,temperature_c,supply_voltage_v,tx_bias_ma,tx_power_dbm,rx_power_dbm,last_dereg_reason,last_dereg_time,learned_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE
                         client_id=VALUES(client_id),
                         clients=VALUES(clients),
                         vlan=VALUES(vlan),
                         port=VALUES(port),
                         onu=VALUES(onu),
                         status=VALUES(status),
                         description=VALUES(description),
                         distance_m=VALUES(distance_m),
                         temperature_c=VALUES(temperature_c),
                         supply_voltage_v=VALUES(supply_voltage_v),
                         tx_bias_ma=VALUES(tx_bias_ma),
                         tx_power_dbm=VALUES(tx_power_dbm),
                         rx_power_dbm=VALUES(rx_power_dbm),
                         last_dereg_reason=VALUES(last_dereg_reason),
                         last_dereg_time=VALUES(last_dereg_time),
                         learned_at=VALUES(learned_at)");
}catch(PDOException $e){
  if(strpos($e->getMessage(),'42S02')!==false){
    echo json_encode([
      'ok'=>false,
      'error'=>'Table `olt_mac_cache` অনুপস্থিত। অনুগ্রহ করে এটি তৈরি করে আবার চেষ্টা করুন।'
    ]);
    exit;
  }
  throw $e;
}

$summary = [
  'ok'=>true,
  'seen'=>0,
  'inserted'=>0,
  'updated'=>0,
  'per_olt'=>[],
  'errors'=>[]
];

// MAC -> client_id ম্যাপ (caller/router/ap MAC থেকে)
$clientMacMap = load_client_mac_map($db);

if($debugMode){
  $summary['debug'] = [];
}

foreach($olts as $olt){
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
  $statusOut = telnet_run_commands(
    $host, $port, $user, $pass,
    ['configure terminal','show onu status all','exit'],
    null, true, $enable, $debugMode, $fastMode ? 8 : 15
  );
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

  if(!$fastMode){
    $diagOut = telnet_run_commands(
      $host, $port, $user, $pass,
      ['configure terminal','show onu opm-diag all','exit'],
      null, true, $enable, $debugMode, 25
    );
    if($diagOut['ok']){
      $diagByKey = parse_onu_rx_metrics($diagOut['output'] ?? '');
      if($debugMode){
        $summary['debug'][] = [
          'olt_id' => $olt['id'],
          'host' => $host,
          'raw_diag_sample' => mb_substr($diagOut['output'] ?? '', 0, 2000),
        ];
      }
    } else {
      $summary['errors'][] = "OLT {$olt['id']} ({$host}) অপটিক-পাওয়ার কমান্ড ব্যর্থ: {$diagOut['error']}";
    }
  }

  $cmds = ['configure terminal','show mac address-table','exit'];
  $out = telnet_run_commands($host, $port, $user, $pass, $cmds, null, true, $enable, $debugMode, $fastMode ? 10 : 15);
  if(!$out['ok']){
    $summary['errors'][] = "OLT {$olt['id']} ({$host}): MAC টেবিল কমান্ড ব্যর্থ - {$out['error']}";
    continue;
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
    if(!$fastMode && $statusKey && isset($diagByKey[$statusKey])){
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
    if($fastMode) continue;
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
  if(!$fastMode && $portOnuMap){
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
      'rx_power_dbm'     => (!$fastMode && isset($diagByKey[$key])) ? $diagByKey[$key] : null,
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

  if(!$fastMode){
    $clientMacTargets = [];
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
    if($clientMacTargets){
      $clientMacData = fetch_onu_client_mac_tables(
        $host,
        $port,
        $user,
        $pass,
        $enable,
        $clientMacTargets,
        $debugMode,
        $olt['id'] ?? null,
        $summary['errors']
      );
      if($clientMacData){
        save_onu_client_mac_rows($db, (int)$olt['id'], $clientMacData);
      }
    }
  }

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
      'mac' => $macValue,
      'vlan' => $row['vlan'] ?? null,
      'port' => $row['port'] ?? null,
      'onu'  => $row['onu'] ?? null,
      'status' => $row['status'] ?? null,
      'description' => $row['description'] ?? null,
      'distance_m'  => $row['distance_m'] ?? null,
      'temperature_c' => null,
      'supply_voltage_v' => null,
      'tx_bias_ma' => null,
      'tx_power_dbm' => null,
      'rx_power_dbm' => $row['rx_power_dbm'] ?? null,
      'last_dereg_reason' => $row['last_dereg_reason'] ?? null,
      'last_dereg_time'   => $row['last_dereg_time'] ?? null,
    ];
    if(isset($existingRows[$macKey]) && mac_payloads_equal($existingRows[$macKey], $payload)){
      continue;
    }
    try{
      $ins->execute([
        $olt['id'],
        $payload['client_id'],
        $payload['clients'],
        $payload['mac'],
        $payload['vlan'],
        $payload['port'],
        $payload['onu'],
        $payload['status'],
        $payload['description'],
        $payload['distance_m'],
        $payload['temperature_c'],
        $payload['supply_voltage_v'],
        $payload['tx_bias_ma'],
        $payload['tx_power_dbm'],
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
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE);
