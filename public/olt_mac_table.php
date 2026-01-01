<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/telnet.php';
require_once __DIR__ . '/../app/security_helpers.php';

$page_title = 'OLT MAC Table';
$_active    = 'olt_mac';

$db = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function diff_for_humans(?string $dt): string {
  if(!$dt) return '-';
  $ts = strtotime($dt);
  if(!$ts) return '-';
  $diff = time() - $ts;
  if($diff < 60) return $diff.'s ago';
  if($diff < 3600) return round($diff/60).'m ago';
  if($diff < 86400) return round($diff/3600).'h ago';
  return round($diff/86400).'d ago';
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

function port_sort_key(array $row): string {
  $port = $row['normalized_port'] ?? ($row['port'] ?? '');
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $port, $m)){
    $family = strtoupper($m[1]) === 'GPON' ? 'B' : 'A';
    return $family . sprintf('%02d', (int)$m[2]);
  }
  if(preg_match('/PON\s*0\/(\d+)/i', $port, $m)){
    return 'C' . sprintf('%02d', (int)$m[1]);
  }
  return 'Z' . strtoupper($port);
}

function format_onu_identifier(array $row): string {
  $port = $row['normalized_port'] ?? '';
  $onuNum = onu_numeric($row['onu'] ?? '');
  if($onuNum === PHP_INT_MAX){
    return $port !== '' ? $port : '—';
  }
  $family = 'PON';
  $slot = null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', $port, $m)){
    $family = strtoupper($m[1]);
    $slot = (int)$m[2];
  } elseif(preg_match('/PON\s*0\/(\d+)/i', $port, $m)){
    $family = 'PON';
    $slot = (int)$m[1];
  }
  if($slot !== null){
    return sprintf('%s0/%d:%d', $family, $slot, $onuNum);
  }
  return trim($port . ($port && $row['onu'] ? ' ' : '') . ($row['onu'] ?? ''));
}

function format_metric($value, int $decimals = 2): string {
  if($value === null) return '—';
  $raw = trim((string)$value);
  if($raw === '' || strtoupper($raw) === 'N/A') return '—';
  if(is_numeric($raw)){
    $num = (float)$raw;
    $formatted = number_format($num, $decimals, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
  }
  return $raw;
}

function decrypt_olt_secret(?string $ciphertext): ?string {
  if($ciphertext === null || $ciphertext === '') return null;
  $plain = decrypt_password($ciphertext, ENCRYPTION_KEY);
  return $plain === false ? null : $plain;
}

function sanitize_onu_description(?string $value): array {
  $trim = $value === null ? '' : trim((string)$value);
  if($trim === '') return ['', false];
  $original = $trim;
  $trim = preg_replace('/\s+/', '-', $trim);
  $trim = preg_replace('/[^A-Za-z0-9_.\\-+\\/]/', '', $trim);
  if(function_exists('mb_substr')){
    $trim = mb_substr($trim, 0, 60, 'UTF-8');
  } else {
    $trim = substr($trim, 0, 60);
  }
  return [$trim, $trim !== $original];
}

function parse_port_label_for_cli(?string $label): ?array {
  if(!$label) return null;
  $label = strtoupper(trim($label));
  if($label === '') return null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d{1,2})/', $label, $m)){
    $family = strtolower($m[1]) === 'gpon' ? 'gpon' : 'epon';
    $slot = (int)$m[2];
    return [$family, '0/'.$slot];
  }
  if(preg_match('/0\/(\d{1,2})/', $label, $m)){
    return ['epon', '0/'.(int)$m[1]];
  }
  return null;
}

function parse_onu_number_from_label(?string $label): ?int {
  if(!$label) return null;
  if(preg_match('/(\d{1,3})/', $label, $m)){
    return (int)$m[1];
  }
  return null;
}

function normalize_mac_key(?string $mac): ?string {
  if($mac === null) return null;
  $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', (string)$mac));
  return strlen($hex) === 12 ? $hex : null;
}

function format_mac_display(?string $mac): ?string {
  $key = normalize_mac_key($mac);
  if(!$key) return null;
  return strtoupper(implode(':', str_split($key, 2)));
}

function load_onu_client_mac_rows(PDO $db, int $oltId): array {
  if($oltId <= 0) return [];
  try{
    $stmt = $db->prepare("SELECT family, slot, onu, vlan, mac, learned_at
                          FROM olt_onu_client_macs
                          WHERE olt_id = ?");
    $stmt->execute([$oltId]);
  } catch(Throwable $e){
    return [];
  }
  $map = [];
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $family = strtoupper($row['family'] ?? '');
    $slot = (int)($row['slot'] ?? 0);
    $onu = (int)($row['onu'] ?? 0);
    if($family === '' || $slot <= 0 || $onu <= 0) continue;
    $key = "{$family}|{$slot}|{$onu}";
    $macDisplay = format_mac_display($row['mac'] ?? '') ?? strtoupper((string)($row['mac'] ?? ''));
    $map[$key][] = [
      'mac' => $macDisplay,
      'vlan'=> $row['vlan'] ?? null,
      'learned_at' => $row['learned_at'] ?? null,
    ];
  }
  return $map;
}

function load_client_lookup(PDO $db, int $oltId): array {
  $byKey = [];
  $byOnu = [];
  $byMac = [];
  $clientsById = [];
  $clientCols = [];
  $hasOnuMac = false;
  $areaCol = '';
  $subZoneCol = '';
  $boxCol = '';

  $clientParams = [];
  $clientWhere  = '';
  if($oltId > 0){
    $clientWhere = ' WHERE c.olt_id = ?';
    $clientParams[] = $oltId;
  }
  try{
    $clientCols = $db->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    $hasOnuMac = in_array('onu_mac', $clientCols, true);
    foreach (['area','zone','location'] as $c) { if (in_array($c, $clientCols, true)) { $areaCol = $c; break; } }
    foreach (['sub_zone','subzone','sub_area'] as $c) { if (in_array($c, $clientCols, true)) { $subZoneCol = $c; break; } }
    foreach (['box','distribution_box','box_name'] as $c) { if (in_array($c, $clientCols, true)) { $boxCol = $c; break; } }
    $selectCols = "c.id, c.name, c.client_code, c.pppoe_id, c.caller_mac, c.router_mac, c.ap_mac";
    if($hasOnuMac) $selectCols .= ", c.onu_mac";
    $selectCols .= ", c.olt_id, c.olt_port, c.olt_onu";
    if($areaCol !== '') $selectCols .= ", c.`{$areaCol}` AS area_name";
    if($subZoneCol !== '') $selectCols .= ", c.`{$subZoneCol}` AS sub_zone_name";
    if($boxCol !== '') $selectCols .= ", c.`{$boxCol}` AS box_name";
    $st = $db->prepare("SELECT {$selectCols} FROM clients c{$clientWhere}");
    $st->execute($clientParams);
    while($r = $st->fetch(PDO::FETCH_ASSOC)){
      $cid = (int)$r['id'];
      $clientsById[$cid] = $r;
      $meta = [
        'id' => $cid,
        'name' => $r['name'] ?? '',
        'pppoe_id' => $r['pppoe_id'] ?? '',
        'client_code' => $r['client_code'] ?? '',
        'area' => $r['area_name'] ?? '',
        'sub_zone' => $r['sub_zone_name'] ?? '',
        'box' => $r['box_name'] ?? '',
      ];
      $clientOlt = (int)($r['olt_id'] ?? 0);
      $portNorm  = normalize_port_label($r['olt_port'] ?? '');
      $onuNum    = onu_numeric($r['olt_onu'] ?? '');
      if($clientOlt > 0 && $onuNum !== PHP_INT_MAX){
        $key = "{$clientOlt}|{$portNorm}|{$onuNum}";
        $byKey[$key] = $meta + ['port' => $portNorm, 'onu' => $onuNum, 'source' => 'client'];
        $byOnu["{$clientOlt}|{$onuNum}"] = $meta + ['port' => $portNorm, 'onu' => $onuNum, 'source' => 'client'];
      }
      $macFields = ['caller_mac','router_mac','ap_mac'];
      if($hasOnuMac) $macFields[] = 'onu_mac';
      foreach($macFields as $mf){
        $mk = normalize_mac_key($r[$mf] ?? null);
        if($mk) $byMac[$mk] = $meta + ['source' => 'client_mac'];
      }
    }
  }catch(Throwable $e){}

  $invParams = [];
  $invWhere = '';
  if($oltId > 0){ $invWhere = ' WHERE oi.olt_id = ?'; $invParams[] = $oltId; }
  try{
    $stInv = $db->prepare("SELECT oi.client_id, oi.olt_id, oi.iface, oi.onu_id
                           FROM onu_inventory oi{$invWhere}");
    $stInv->execute($invParams);
    while($row = $stInv->fetch(PDO::FETCH_ASSOC)){
      $cid = (int)($row['client_id'] ?? 0);
      if(!$cid || !isset($clientsById[$cid])) continue;
      $meta = [
        'id' => $cid,
        'name' => $clientsById[$cid]['name'] ?? '',
        'pppoe_id' => $clientsById[$cid]['pppoe_id'] ?? '',
        'client_code' => $clientsById[$cid]['client_code'] ?? '',
      ];
      $invOlt  = (int)($row['olt_id'] ?? 0);
      $portNorm = normalize_port_label($row['iface'] ?? '');
      $onuNum   = onu_numeric($row['onu_id'] ?? '');
      if($invOlt > 0 && $onuNum !== PHP_INT_MAX){
        $key = "{$invOlt}|{$portNorm}|{$onuNum}";
        $byKey[$key] = $meta + ['port' => $portNorm, 'onu' => $onuNum, 'source' => 'onu_inventory'];
        $byOnu["{$invOlt}|{$onuNum}"] = $meta + ['port' => $portNorm, 'onu' => $onuNum, 'source' => 'onu_inventory'];
      }
    }
  }catch(Throwable $e){}

  if($oltId > 0){
    try{
      $stMac = $db->prepare("SELECT family, slot, onu, mac FROM olt_onu_client_macs WHERE olt_id = ?");
      $stMac->execute([$oltId]);
      while($r = $stMac->fetch(PDO::FETCH_ASSOC)){
        $macKey = normalize_mac_key($r['mac'] ?? '');
        if(!$macKey) continue;
        $family = strtoupper($r['family'] ?? '');
        $slot   = (int)($r['slot'] ?? 0);
        $onuNum = (int)($r['onu'] ?? 0);
        if($family === '' || $slot <= 0 || $onuNum <= 0) continue;
        $iface  = "{$family} 0/".str_pad((string)$slot, 2, '0', STR_PAD_LEFT);
        $portNorm = normalize_port_label($iface);
        $clientForOnu = $byKey["{$oltId}|{$portNorm}|{$onuNum}"] ?? null;
        if($clientForOnu){
          $byMac[$macKey] = $clientForOnu + ['source' => 'onu_client_mac'];
        }
      }
    }catch(Throwable $e){}
  }

  return ['byKey' => $byKey, 'byOnu' => $byOnu, 'byMac' => $byMac, 'byId' => $clientsById];
}

function match_client_for_mac_row(array $row, array $lookup): ?array {
  $oltId = (int)($row['olt_id'] ?? 0);
  $port  = $row['normalized_port'] ?? normalize_port_label($row['port'] ?? '');
  $onu   = onu_numeric($row['onu'] ?? '');
  $cacheClientId = (int)($row['client_id'] ?? 0);
  if($cacheClientId > 0 && isset($lookup['byId'][$cacheClientId])){
    $r = $lookup['byId'][$cacheClientId];
    $cOlt = (int)($r['olt_id'] ?? 0);
    $cPort = normalize_port_label($r['olt_port'] ?? '');
    $cOnu = onu_numeric($r['olt_onu'] ?? '');
    if ($cOlt === $oltId && $cPort === $port && $cOnu === $onu) {
      return [
        'id' => $cacheClientId,
        'name' => $r['name'] ?? '',
        'pppoe_id' => $r['pppoe_id'] ?? '',
        'client_code' => $r['client_code'] ?? '',
        'area' => $r['area_name'] ?? '',
        'sub_zone' => $r['sub_zone_name'] ?? '',
        'box' => $r['box_name'] ?? '',
        'match_via' => 'client_binding',
      ];
    }
  }
  if($oltId > 0 && $port && $onu !== PHP_INT_MAX){
    $key = "{$oltId}|{$port}|{$onu}";
    if(isset($lookup['byKey'][$key])) return $lookup['byKey'][$key] + ['match_via' => 'port_onu'];
  }
  return null;
}

function update_client_olt_binding(PDO $db, array $row, int $clientId): void {
  if ($clientId <= 0) return;
  $oltId = (int)($row['olt_id'] ?? 0);
  $portNorm = normalize_port_label($row['port'] ?? '');
  $onuNum = onu_numeric((string)($row['onu'] ?? ''));
  if ($oltId <= 0 || $portNorm === '—' || $onuNum === PHP_INT_MAX) return;

  $cur = $db->prepare("SELECT olt_id, olt_port, olt_onu FROM clients WHERE id = ? LIMIT 1");
  $cur->execute([$clientId]);
  $existing = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
  $curOlt = (int)($existing['olt_id'] ?? 0);
  $curPort = normalize_port_label($existing['olt_port'] ?? '');
  $curOnu = onu_numeric($existing['olt_onu'] ?? '');
  $hasBinding = ($curOlt > 0 && $curPort !== '—' && $curOnu !== PHP_INT_MAX);

  // Only set when empty or already matches this row to avoid spreading one client across rows.
  if ($hasBinding && !($curOlt === $oltId && $curPort === $portNorm && $curOnu === $onuNum)) {
    return;
  }

  $oltVendor = trim((string)($row['olt_vendor'] ?? ''));
  $st = $db->prepare("UPDATE clients
                        SET olt_id = ?,
                            olt_vendor = ?,
                            olt_port = ?,
                            olt_onu = ?,
                            last_linked_at = NOW()
                      WHERE id = ?");
  $st->execute([$oltId, $oltVendor !== '' ? $oltVendor : null, $portNorm, $onuNum, $clientId]);
}

function backfill_cache_client_id(PDO $db, array $row, array $lookup): ?int {
  if (!empty($row['client_id'])) return (int)$row['client_id'];
  $oltId = (int)($row['olt_id'] ?? 0);
  $port  = $row['normalized_port'] ?? normalize_port_label($row['port'] ?? '');
  $onu   = onu_numeric($row['onu'] ?? '');
  if ($oltId <= 0 || $onu === PHP_INT_MAX) return null;
  $key = "{$oltId}|{$port}|{$onu}";
  $match = $lookup['byKey'][$key] ?? null;
  if (!$match || empty($match['id'])) return null;
  $cid = (int)$match['id'];
  if ($cid <= 0) return null;
  $st = $db->prepare("UPDATE olt_mac_cache SET client_id = ? WHERE id = ? AND (client_id IS NULL OR client_id = 0)");
  $st->execute([$cid, (int)$row['id']]);
  return $cid;
}

function client_mac_lookup_key(array $row): ?string {
  $port = $row['normalized_port'] ?? normalize_port_label($row['port'] ?? '');
  $onuNum = onu_numeric($row['onu'] ?? '');
  if($onuNum === PHP_INT_MAX) return null;
  if(preg_match('/(EPON|GPON)\s*0\/(\d+)/i', (string)$port, $m)){
    return strtoupper($m[1]).'|'.(int)$m[2].'|'.$onuNum;
  }
  return null;
}

function rx_power_meta($value): array {
  if($value === null || $value === '' || !is_numeric($value)){
    return [null, null];
  }
  $num = (float)$value;
  if($num >= -24 && $num <= -1){
    return ['Good', 'bg-success'];
  }
  if($num >= -26 && $num < -24){
    return ['Warn', 'bg-warning text-dark'];
  }
  return ['Critical', 'bg-danger'];
}

function onu_numeric(?string $onu): int {
  if($onu && preg_match('/(\d+)/', $onu, $m)){
    return (int)$m[1];
  }
  return PHP_INT_MAX;
}

function normalize_olt_status(?string $status): ?string {
  $s = strtolower(trim((string)$status));
  if($s === '') return null;
  return match($s){
    'online','up','active','reachable','yes','true','1'   => 'online',
    'offline','down','inactive','failed','no','false','0' => 'offline',
    'unknown' => 'unknown',
    default => null,
  };
}

function status_badge_meta(?string $status): array {
  $normalized = normalize_olt_status($status);
  return match($normalized){
    'online' => ['Online','text-success fw-semibold'],
    'offline' => ['Offline','text-danger fw-semibold'],
    default => ['Unknown','text-secondary'],
  };
}

function status_rank(?string $status): int {
  $normalized = normalize_olt_status($status);
  return match($normalized){
    'online' => 2,
    'offline' => 1,
    default => 0,
  };
}

function resolve_row_status(array $row): ?string {
  $rx = $row['rx_power_dbm'] ?? null;
  if($rx !== null && $rx !== '' && is_numeric($rx)) return 'online';
  $reason = strtolower(trim((string)($row['last_dereg_reason'] ?? '')));
  $hasReason = ($reason !== '' && $reason !== 'n/a' && $reason !== '--' && $reason !== '—');
  $deregTime = trim((string)($row['last_dereg_time'] ?? ''));
  $hasDeregTime = ($deregTime !== '' && $deregTime !== '0000-00-00 00:00:00');
  $normalized = normalize_olt_status($row['status'] ?? null);
  if($hasReason || $hasDeregTime) return 'offline';
  if($normalized === 'offline') return 'offline';
  if($normalized === 'online') return 'online';
  return $normalized;
}

$filterOlt   = (int)($_GET['olt_id'] ?? 0);
$perPage     = 1000;

$link_notice = '';
$link_error = '';
$config_notice = '';
$config_error = '';
$expandedRowId = 0;
$toast_message = '';
$toast_type = '';
$config_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'link_client') {
  if (!csrf_verify()) {
    $link_error = 'Invalid CSRF token.';
  } else {
    $rowId = (int)($_POST['row_id'] ?? 0);
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($rowId <= 0) {
      $link_error = 'Row not found. Please reload the page.';
    } elseif ($clientId <= 0) {
      $link_error = 'Enter a valid Client ID.';
    } else {
      $stRow = $db->prepare("SELECT id, olt_id, port, onu FROM olt_mac_cache WHERE id = ? LIMIT 1");
      $stRow->execute([$rowId]);
      $row = $stRow->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $link_error = 'OLT MAC row not found.';
      } else {
        $oltId = (int)($row['olt_id'] ?? 0);
        $portNorm = normalize_port_label($row['port'] ?? '');
        $onuNum = onu_numeric((string)($row['onu'] ?? ''));
        if ($oltId <= 0 || $portNorm === '—' || $onuNum === PHP_INT_MAX) {
          $link_error = 'Missing OLT/port/ONU data for this row.';
        } else {
          $stClient = $db->prepare("SELECT id FROM clients WHERE id = ? LIMIT 1");
          $stClient->execute([$clientId]);
          if (!$stClient->fetchColumn()) {
            $link_error = 'Client not found.';
          } else {
            $oltVendor = null;
            $stOlt = $db->prepare("SELECT vendor FROM olts WHERE id = ? LIMIT 1");
            $stOlt->execute([$oltId]);
            $oltVendor = $stOlt->fetchColumn() ?: null;
            $updClient = $db->prepare("UPDATE clients
                                         SET olt_id = ?, olt_vendor = ?, olt_port = ?, olt_onu = ?, last_linked_at = NOW()
                                       WHERE id = ?");
            $updClient->execute([$oltId, $oltVendor, $portNorm, $onuNum, $clientId]);
            $updCache = $db->prepare("UPDATE olt_mac_cache SET client_id = ? WHERE id = ?");
            $updCache->execute([$clientId, $rowId]);
            $link_notice = "Linked client #{$clientId} to {$portNorm} ONU {$onuNum}.";
          }
        }
      }
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_desc') {
  if (!csrf_verify()) {
    $config_error = 'Invalid CSRF token.';
  } else {
    $rowId = (int)($_POST['row_id'] ?? 0);
    $expandedRowId = $rowId;
    $descRaw = trim((string)($_POST['description'] ?? ''));
    if ($descRaw !== '') {
      if (function_exists('mb_substr')) {
        $descRaw = mb_substr($descRaw, 0, 255, 'UTF-8');
      } else {
        $descRaw = substr($descRaw, 0, 255);
      }
    }
    if ($rowId <= 0) {
      $config_error = 'Invalid row.';
    } else {
      $stRow = $db->prepare("SELECT c.*, o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor, o.telnet_port, o.ssh_port, o.username, o.password, o.enable_password
                             FROM olt_mac_cache c
                             LEFT JOIN olts o ON o.id = c.olt_id
                             WHERE c.id = ? LIMIT 1");
      $stRow->execute([$rowId]);
      $row = $stRow->fetch(PDO::FETCH_ASSOC);
      if(!$row){
        $config_error = 'OLT MAC row not found.';
      } else {
        $oltId = (int)($row['olt_id'] ?? 0);
        if($oltId <= 0){
          $config_error = 'OLT info missing.';
        } else {
          $username = trim((string)($row['username'] ?? ''));
          $password = decrypt_olt_secret($row['password'] ?? '');
          if($username === '' || $password === null){
            $config_error = 'OLT username/password missing.';
          } else {
            $enablePass = decrypt_olt_secret($row['enable_password'] ?? '') ?: $password;
            $parsedPort = parse_port_label_for_cli($row['port'] ?? '');
            if(!$parsedPort){
              $config_error = 'Port format not recognized.';
            } else {
              [$familyCli, $iface] = $parsedPort;
              $onuNum = parse_onu_number_from_label($row['onu'] ?? '');
              if($onuNum === null){
                $config_error = 'ONU number not found.';
              } else {
                [$descSanitized,] = sanitize_onu_description($descRaw);
                $port = (int)($row['telnet_port'] ?? 0);
                if($port < 1){
                  $port = (int)($row['ssh_port'] ?? 23);
                }
                if($port < 1) $port = 23;
                $commands = [
                  'configure terminal',
                  "interface {$familyCli} {$iface}",
                  $descSanitized === '' ? "onu {$onuNum} description default" : "onu {$onuNum} description {$descSanitized}",
                  'exit',
                  'exit',
                ];
                $result = telnet_run_commands(
                  $row['olt_host'],
                  $port,
                  $username,
                  $password,
                  $commands,
                  null,
                  true,
                  $enablePass,
                  false,
                  20
                );
                if(!$result['ok']){
                  $config_error = $result['error'] ?? 'Device update failed.';
                } else {
                  $upd = $db->prepare("UPDATE olt_mac_cache SET description = ? WHERE id = ?");
                  $upd->execute([$descSanitized !== '' ? $descSanitized : null, $rowId]);
                  $config_notice = 'Description updated Success .';
                  $config_ok = true;
                }
              }
            }
          }
        }
      }
    }
  }
}
if ($config_notice !== '') { $toast_message = $config_notice; $toast_type = 'success'; }
elseif ($config_error !== '') { $toast_message = $config_error; $toast_type = 'error'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_desc' && (string)($_POST['ajax'] ?? '') === '1') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => $config_ok,
    'message' => $config_ok ? $config_notice : ($config_error ?: 'Save failed.'),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$where = [];
$params = [];
if($filterOlt > 0){
  $where[] = 'c.olt_id = ?';
  $params[] = $filterOlt;
}
$sql = "SELECT c.*, o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor
        FROM olt_mac_cache c
        LEFT JOIN olts o ON o.id = c.olt_id";
if($where){
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY c.learned_at DESC LIMIT {$perPage}";
try {
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $tableMissing = false;
} catch (PDOException $e) {
  if (strpos($e->getMessage(), '42S02') !== false) {
    $rows = [];
    $tableMissing = true;
  } else {
    throw $e;
  }
}

$groupedMacs = [];
$clientMacCache = $filterOlt > 0 ? load_onu_client_mac_rows($db, $filterOlt) : [];
$clientLookup   = load_client_lookup($db, $filterOlt);
if(!$tableMissing && $rows){
  $processedRows = [];
  foreach($rows as $row){
    $row['normalized_port'] = normalize_port_label($row['port'] ?? '');
    $slot = null;
    if(preg_match('/0\/(\d{1,2})/', $row['normalized_port'], $m)){
      $slot = (int)$m[1];
    } elseif(preg_match('/PON\s*(\d+)/i', $row['normalized_port'], $m)){
      $slot = (int)$m[1];
    }
    $onuRaw = trim((string)($row['onu'] ?? ''));
    if(!$slot || $onuRaw === '' || $onuRaw === '—'){
      continue;
    }
    $row['pon_slot'] = $slot;
    $label = 'PON '.$slot;
    $backfilled = backfill_cache_client_id($db, $row, $clientLookup);
    if($backfilled){
      $row['client_id'] = $backfilled;
      update_client_olt_binding($db, $row, $backfilled);
    } elseif(!empty($row['client_id'])) {
      update_client_olt_binding($db, $row, (int)$row['client_id']);
    }
    $row['client_meta'] = match_client_for_mac_row($row, $clientLookup);
    $processedRows[] = $row;
  }
  $rows = $processedRows;
  usort($rows, function($a,$b){
    $ka = port_sort_key($a);
    $kb = port_sort_key($b);
    if($ka === $kb){
      $ta = strtotime($a['learned_at'] ?? '') ?: 0;
      $tb = strtotime($b['learned_at'] ?? '') ?: 0;
      return $tb <=> $ta;
    }
    return strcmp($ka, $kb);
  });
  foreach($rows as $row){
    $slot = $row['pon_slot'];
    if(!$slot) continue;
    $label = 'PON '.$slot;
    if(!isset($groupedMacs[$label])){
      $groupedMacs[$label] = [
        'label' => $label,
        'slot'  => $slot,
        'rows'  => []
      ];
    }
    $groupedMacs[$label]['rows'][] = $row;
  }
  uasort($groupedMacs, function($a,$b){
    if($a['slot'] === $b['slot']){
      return strcmp($a['label'], $b['label']);
    }
    return $a['slot'] <=> $b['slot'];
  });
  foreach($groupedMacs as &$g){
    $bestByKey = [];
    foreach($g['rows'] as $row){
      $onuNum = onu_numeric($row['onu'] ?? '');
      $baseKey = $row['normalized_port'] ?? '';
      if($onuNum !== PHP_INT_MAX){
        $dedupKey = "{$baseKey}|{$onuNum}";
      } else {
        $dedupKey = "{$baseKey}|".strtolower($row['mac'] ?? '');
      }
      if(!isset($bestByKey[$dedupKey])){
        $bestByKey[$dedupKey] = $row;
        continue;
      }
      $current = $bestByKey[$dedupKey];
      $newTs = strtotime($row['learned_at'] ?? '') ?: 0;
      $oldTs = strtotime($current['learned_at'] ?? '') ?: 0;
      if($newTs !== $oldTs){
        if($newTs > $oldTs){
          $bestByKey[$dedupKey] = $row;
        }
        continue;
      }
      if(status_rank(resolve_row_status($row)) > status_rank(resolve_row_status($current))){
        $bestByKey[$dedupKey] = $row;
      }
    }
    $filtered = array_values($bestByKey);
    usort($filtered, function($a,$b){
      $na = onu_numeric($a['onu'] ?? '');
      $nb = onu_numeric($b['onu'] ?? '');
      if($na === $nb){
        return strcmp($a['normalized_port'], $b['normalized_port']);
      }
      return $na <=> $nb;
    });
    $g['rows'] = $filtered;
  }
  unset($g);
}
$statWhere = [];
$statParams = [];
if($filterOlt > 0){
  $statWhere[] = 'olt_id = ?';
  $statParams[] = $filterOlt;
}
$statSql = "SELECT COUNT(*) AS total_entries,
                   COUNT(DISTINCT olt_id) AS olt_count,
                   MAX(learned_at) AS last_learned
            FROM olt_mac_cache";
if($statWhere){
  $statSql .= ' WHERE ' . implode(' AND ', $statWhere);
}
if(!$tableMissing){
  $statStmt = $db->prepare($statSql);
  $statStmt->execute($statParams);
  $stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_entries'=>0,'olt_count'=>0,'last_learned'=>null];
} else {
  $stats = ['total_entries'=>0,'olt_count'=>0,'last_learned'=>null];
}

$recentSql = "SELECT COUNT(*) FROM olt_mac_cache WHERE learned_at >= NOW() - INTERVAL 1 DAY";
if($filterOlt > 0){
  $recentSql .= " AND olt_id = ?";
  $recentParams = [$filterOlt];
} else {
  $recentParams = [];
}
if(!$tableMissing){
  $recentStmt = $db->prepare($recentSql);
  $recentStmt->execute($recentParams);
  $recent24 = (int)$recentStmt->fetchColumn();
} else {
  $recent24 = 0;
}

$lastLearnedAt = $stats['last_learned'] ?? null;
$lastLearnedHuman = $lastLearnedAt ? diff_for_humans($lastLearnedAt) : '—';

$olts = $db->query("SELECT id, name, host FROM olts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedOlt = null;
if($filterOlt > 0){
  foreach($olts as $o){ if($o['id']===$filterOlt){ $selectedOlt=$o; break; } }
}
?>
<?php require_once __DIR__ . '/../partials/partials_header.php'; ?>

  <form class="card">
          
       <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="md-0">OLT MAC Table</h4>
      <?php $refreshUrl = '/api/olt_mac_refresh_telnet.php' . ($filterOlt > 0 ? ('?olt_id=' . $filterOlt) : ''); ?>
      <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary btn-sm" href="/public/sync_clients_router_mac.php?mode=if_diff" target="_blank" rel="noopener">
            <i class="bi bi-hdd-network"></i> Sync Router MACs
          </a>
          <button type="button"
                  class="btn btn-primary btn-sm telnet-refresh-btn"
                  data-url="<?=h($refreshUrl . (strpos($refreshUrl,'?')!==false ? '&' : '?') . 'mode=full');?>">
            <span class="btn-label"><i class="bi bi-arrow-clockwise"></i> Full Sync</span>
          </button>
        </div>
      </div>
      <div id="telnetRefreshStatus" class="text-muted small text-end"></div>

      <?php if($link_notice): ?>
        <div class="alert alert-success mb-0"><?= h($link_notice) ?></div>
      <?php elseif($link_error): ?>
        <div class="alert alert-danger mb-0"><?= h($link_error) ?></div>
      <?php endif; ?>

          <?php if(!empty($tableMissing)): ?>
            <div class="alert alert-warning shadow-sm">
              <h6 class="mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>MAC cache table পাওয়া যায়নি</h6>
              <p class="mb-1">ডাটাবেসে <code>olt_mac_cache</code> টেবিল অনুপস্থিত থাকার কারণে ডেটা লোড করা যাচ্ছে না। নীচের SQL চালিয়ে টেবিল তৈরী করুন:</p>
        <pre class="bg-dark text-white p-3 rounded small mb-2">CREATE TABLE `olt_mac_cache` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `olt_id` int unsigned NOT NULL,
          `client_id` int unsigned DEFAULT NULL,
          `mac` varchar(32) NOT NULL,
          `clients` varchar(255) DEFAULT NULL,
          `vlan` smallint unsigned DEFAULT NULL,
          `port` varchar(64) DEFAULT NULL,
          `onu` varchar(32) DEFAULT NULL,
          `status` enum('online','offline','unknown') DEFAULT NULL,
          `description` varchar(255) DEFAULT NULL,
          `distance_m` decimal(10,2) DEFAULT NULL,
          `temperature_c` decimal(10,2) DEFAULT NULL,
          `supply_voltage_v` decimal(10,2) DEFAULT NULL,
          `tx_bias_ma` decimal(10,2) DEFAULT NULL,
          `tx_power_dbm` decimal(10,2) DEFAULT NULL,
          `rx_power_dbm` decimal(10,2) DEFAULT NULL,
          `last_dereg_reason` varchar(255) DEFAULT NULL,
          `last_dereg_time` datetime DEFAULT NULL,
          `learned_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_olt_mac` (`olt_id`,`mac`),
          KEY `idx_mac` (`mac`),
          KEY `idx_port` (`port`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
              <p class="mb-0 text-muted small">SQL রান হওয়ার পর এই পেজ রিফ্রেশ করুন।</p>
            </div>
          <?php endif; ?>
    <div class="card-body row g-3 align-items-end">


      <div class="col-md-10">
        <label class="form-label text-muted small text-uppercase">OLT</label>
        <select name="olt_id" class="form-select">
          <option value="0">Select an OLT</option>
          <?php foreach($olts as $olt): ?>
            <option value="<?=$olt['id'];?>" <?=$filterOlt===$olt['id']?'selected':'';?>>
              <?=h($olt['name'] ?: $olt['host']);?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Apply</button>
      </div>
    </div>
  </form>
<?php if($selectedOlt): ?>
  <div class="card shadow-sm">
    
    <div class="card-header bg-body">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-semibold">OLT NAME : <?=h($selectedOlt['name'] ?: 'Unnamed OLT');?> |IP: <?=h($selectedOlt['host'] ?? '');?>|Cache Time: <i class="bi bi-hdd-fill"></i> <?=h($lastLearnedHuman);?><?php if($lastLearnedAt): ?> <span class="text-muted small">(<?=h($lastLearnedAt);?>)</span><?php endif; ?></span>
        <span class="text-muted small"><?=count($rows);?> entries</span>
      </div>
      
    </div>
    <div class="accordion" id="ponMacAccordion">
      <?php if($groupedMacs): $idx=0; foreach($groupedMacs as $label => $group): $cid='ponMac-'.$idx++; ?>
        <div class="accordion-item">
          
          <h2 class="accordion-header" id="heading-<?=$cid;?>">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?=$cid;?>" aria-expanded="false" aria-controls="<?=$cid;?>">
              <span class="fw-semibold me-3"><?=h($label);?></span>
              <span class="badge text-bg-primary"><?=$group['rows'] ? count($group['rows']) : 0;?> ONU</span>
            </button>
          </h2>
          
          <div id="<?=$cid;?>" class="accordion-collapse collapse" aria-labelledby="heading-<?=$cid;?>" data-bs-parent="#ponMacAccordion">
            <div class="accordion-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle olt-mac-table table-app">
                  <thead>
                    <tr>
                      <th>ONU ID</th>
                      <th>Client</th>
                      <th>Area</th>
                      <th>Sub Zone</th>
                      <th>Box</th>
                      <th>Description</th>
                      <th>MAC</th>
                      <th>VLAN</th>
                      
                      <th>Status</th>
                      <th class="olt-dist-col">Dist.(m)</th>
                      <th class="olt-rx-col">L.(dBm)</th>
                      <th>LDR</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($group['rows'] as $row): ?>
                      <tr>
                        <td><?=h(format_onu_identifier($row));?></td>
                        <td>
                          <?php if(!empty($row['client_meta'])): $cm=$row['client_meta']; ?>
                            <a href="/public/client_view.php?id=<?=$cm['id'];?>" class="fw-semibold text-decoration-none">
                              <?= (int)($cm['id'] ?? 0); ?>: <?= h($cm['name'] ?? ''); ?>
                            </a>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td><?= !empty($row['client_meta']) && ($row['client_meta']['area'] ?? '') !== '' ? h($row['client_meta']['area']) : '—' ?></td>
                        <td><?= !empty($row['client_meta']) && ($row['client_meta']['sub_zone'] ?? '') !== '' ? h($row['client_meta']['sub_zone']) : '—' ?></td>
                        <td><?= !empty($row['client_meta']) && ($row['client_meta']['box'] ?? '') !== '' ? h($row['client_meta']['box']) : '—' ?></td>
                        <td><span class="desc-text" data-row-id="<?=$row['id'];?>"><?=h($row['description'] !== null && $row['description'] !== '' ? $row['description'] : '—');?></span></td>
                        <td><code class="fw-semibold"><?=h(strtoupper($row['mac']));?></code></td>
                        <?php
                          $clientKey = client_mac_lookup_key($row);
                          $clientMacRows = $clientKey && isset($clientMacCache[$clientKey]) ? $clientMacCache[$clientKey] : [];
                          $vlanBadges = [];
                          if(isset($row['vlan']) && $row['vlan'] !== null && $row['vlan'] !== ''){
                            $vlanBadges[] = $row['vlan'];
                          }
                          if(!$vlanBadges && $clientMacRows){
                            foreach($clientMacRows as $cm){
                              if(isset($cm['vlan']) && $cm['vlan'] !== null && $cm['vlan'] !== ''){
                                $vlanBadges[] = $cm['vlan'];
                              }
                            }
                            $vlanBadges = array_values(array_unique($vlanBadges));
                          }
                        ?>
                        <td>
                          <?php if($vlanBadges): ?>
                            <?php foreach($vlanBadges as $vb): ?>
                              <span class="badge text-bg-secondary me-1 mb-1"><?=h($vb);?></span>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php [$statusLabel,$statusClass] = status_badge_meta(resolve_row_status($row)); ?>
                          <span class="<?=$statusClass;?>"><?=$statusLabel;?></span>
                        </td>
                        
                        <?php
                          $distanceRaw = $row['distance_m'] ?? null;
                          $rxRaw = $row['rx_power_dbm'] ?? null;
                          $distanceIsNumeric = $distanceRaw !== null && $distanceRaw !== '' && is_numeric($distanceRaw);
                          $rxIsNumeric = $rxRaw !== null && $rxRaw !== '' && is_numeric($rxRaw);
                          $distanceNum = $distanceIsNumeric ? (float)$distanceRaw : null;
                          $rxNum = $rxIsNumeric ? (float)$rxRaw : null;
                          if($distanceNum !== null && $distanceNum < 0){
                            if($rxNum !== null && $rxNum > 0){
                              $tmp = $rxRaw;
                              $rxRaw = $distanceRaw;
                              $distanceRaw = $tmp;
                            } else {
                              $rxRaw = $distanceRaw;
                              $distanceRaw = null;
                            }
                          } elseif($rxNum !== null && $rxNum > 100 && ($distanceNum === null || $distanceNum < 0)){
                            $tmp = $rxRaw;
                            $rxRaw = $distanceRaw;
                            $distanceRaw = $tmp;
                          }
                          $distanceDisplay = format_metric($distanceRaw, 0);
                          $rxDisplay = format_metric($rxRaw);
                          [$rxLabel,$rxClass] = rx_power_meta($rxRaw);
                        ?>
                        <td class="olt-dist-col"><?=h($distanceDisplay);?></td>
                        <td class="olt-rx-cell olt-rx-col">
                          <span><?=h($rxDisplay);?></span>
                          <?php if($rxLabel): ?>
                            <span class="badge <?=$rxClass;?>"><?=$rxLabel;?></span>
                          <?php endif; ?>
                        </td>
                        <td class="olt-dereg-cell">
                          <?php
                            $deregReason = $row['last_dereg_reason'] ?? null;
                            $deregTime = $row['last_dereg_time'] ?? null;
                            $deregReasonDisplay = ($deregReason === null || $deregReason === '') ? '—' : $deregReason;
                          ?>
                          <div><?=h($deregReasonDisplay);?></div>
                          <?php if($deregTime): ?>
                            <div class="text-muted small"><?=h($deregTime);?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <button class="btn btn-outline-primary btn-sm btn-config"
                                  type="button"
                                  data-row-id="<?=$row['id'];?>"
                                  data-desc="<?=h($row['description'] ?? '');?>">
                            Configure
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="p-4 text-center text-muted">এই মানদণ্ডে কোনো MAC পাওয়া যায়নি।</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<style>
.olt-mac-table{
  table-layout: fixed;
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
}
.olt-mac-table th,
.olt-mac-table td{
  vertical-align: middle;
  border: 1px solid #e1e5ee;
  text-align: center;
}
.olt-mac-table thead th{
  white-space: normal;
  line-height: 1.1;
  text-align: center;
  border: 1px solid #e1e5ee;
}
.olt-mac-table thead th:nth-child(8){
  text-align: center;
}
.olt-mac-table th:nth-child(1),
.olt-mac-table td:nth-child(1){
  width: 10%;
  text-align: center;
}
.olt-mac-table th:nth-child(2),
.olt-mac-table td:nth-child(2){
  width: 14%;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
}
.olt-mac-table th:nth-child(3),
.olt-mac-table td:nth-child(3){
  width: 8%;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
}
.olt-mac-table th:nth-child(4),
.olt-mac-table td:nth-child(4){
  width: 8%;
  text-align: center;
}
.olt-mac-table th:nth-child(5),
.olt-mac-table td:nth-child(5){
  width: 8%;
  text-align: center;
}
.olt-mac-table th:nth-child(6),
.olt-mac-table td:nth-child(6){
  width: 14%;
  text-align: center;
}
.olt-mac-table th:nth-child(7),
.olt-mac-table td:nth-child(7){
  width: 12%;
  text-align: center;
  font-variant-numeric: tabular-nums;
  min-width: 110px;
}
.olt-mac-table th:nth-child(8),
.olt-mac-table td:nth-child(8){
  width: 6%;
  text-align: center;
  font-variant-numeric: tabular-nums;
  min-width: 70px;
}
.olt-mac-table th:nth-child(9),
.olt-mac-table td:nth-child(9){
  width: 7%;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
}
.olt-mac-table th:nth-child(10),
.olt-mac-table td:nth-child(10){
  width: 8%;
  text-align: center;
}
.olt-mac-table th:nth-child(11),
.olt-mac-table td:nth-child(11){
  width: 9%;
  text-align: center;
  font-variant-numeric: tabular-nums;
  min-width: 90px;
}
.olt-mac-table th:nth-child(12),
.olt-mac-table td:nth-child(12){
  width: 12%;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
}
.olt-mac-table th:nth-child(13),
.olt-mac-table td:nth-child(13){
  width: 6%;
  text-align: center;
}
.olt-mac-table td:nth-child(1),
.olt-mac-table td:nth-child(7),
.olt-mac-table td:nth-child(8),
.olt-mac-table td:nth-child(9),
.olt-mac-table td:nth-child(10),
.olt-mac-table td:nth-child(11),
.olt-mac-table td:nth-child(13){
  white-space: nowrap;
}
.olt-rx-cell{
  width: 9%;
  text-align: center;
  white-space: normal;
  overflow-wrap: anywhere;
  min-width: 90px;
}

@media (max-width: 767.98px){
  .olt-mac-table{
    table-layout: auto;
    min-width: 1120px;
  }
  .olt-mac-table th,
  .olt-mac-table td{
    width: auto !important;
  }
  .olt-mac-table thead th{
    white-space: nowrap;
    font-size: .8rem;
  }
  .olt-mac-table td{
    font-size: .85rem;
  }
  .table-responsive{
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}
/* Smooth dropdown animation (page-specific; gentle up/down) */
.dropdown-menu{
  opacity:0;
  transform: translateY(8px) scale(.98);
  transition: opacity .18s ease, transform .22s ease, max-height .25s ease;
  display:block;
  visibility:hidden;
  will-change: opacity, transform, max-height;
  max-height: 0;
  overflow: hidden;
  pointer-events: none;
}
.dropdown-menu.show{
  opacity:1;
  transform: translateY(0) scale(1);
  visibility:visible;
  max-height: 60vh;
  overflow: auto;
  pointer-events: auto;
}
</style>
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
<!-- Config modal -->
<div class="modal fade" id="onuConfigModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="onuConfigForm">
      <div class="modal-header">
        <h5 class="modal-title">ONU Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= csrf_input_html(); ?>
        <input type="hidden" name="action" value="update_desc">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="row_id" id="cfgRowId" value="">
        <label class="form-label text-muted small text-uppercase">Description</label>
        <textarea name="description" id="cfgDesc" rows="3" class="form-control" placeholder="e.g. Building-3, 3rd Floor"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<?php if($toast_message !== '' && $toast_type !== ''): ?>
<script>
  if (window.showToast) {
    showToast('<?= h($toast_message) ?>', '<?= h($toast_type) ?>', 3000);
  }
</script>
<?php endif; ?>
<script>
(function(){
  const buttons = document.querySelectorAll('.telnet-refresh-btn');
  if(!buttons.length) return;
  const statusEl = document.getElementById('telnetRefreshStatus');
  function resetAfter(btn, originalHtml){
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    setTimeout(()=>{ if(statusEl) statusEl.textContent=''; }, 7000);
  }
  async function handleClick(e){
    const btn = e.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing…';
    if(statusEl) statusEl.textContent = 'রিফ্রেশ চলছে…';
    try{
      const resp = await fetch(btn.dataset.url, {credentials: 'same-origin'});
      const data = await resp.json();
      if(data.ok){
        const seen = data.seen ?? 0;
        if(statusEl) statusEl.textContent = 'রিফ্রেশ সম্পন্ন। নতুন ডেটা: ' + seen + ' MAC। পৃষ্ঠা আপডেট হচ্ছে…';
        setTimeout(()=>window.location.reload(), 1200);
      } else {
        if(statusEl) statusEl.textContent = data.error || 'রিফ্রেশ ব্যর্থ হয়েছে।';
        resetAfter(btn, originalHtml);
      }
    }catch(err){
      if(statusEl) statusEl.textContent = 'রিফ্রেশ ব্যর্থ: ' + err.message;
      resetAfter(btn, originalHtml);
    }
  }
  buttons.forEach(btn => btn.addEventListener('click', handleClick));
})();
</script>
<script>
  const ensureHolder = () => {
    let holder = document.getElementById('app-toast-holder');
    if (!holder){
      holder = document.createElement('div');
      holder.id = 'app-toast-holder';
      document.body.appendChild(holder);
    }
    return holder;
  };
  const showSavingToast = () => {
    const existing = document.getElementById('saving-toast');
    if (existing) return existing;
    const holder = ensureHolder();
    const el = document.createElement('div');
    el.id = 'saving-toast';
    el.className = 'app-toast app-toast--info show';
    const row = document.createElement('div');
    row.className = 'app-toast__row';
    const icon = document.createElement('div');
    icon.className = 'app-toast__icon';
    icon.textContent = '⏳';
    const body = document.createElement('div');
    body.className = 'app-toast__body';
    const t = document.createElement('div');
    t.className = 'app-toast__title';
    t.textContent = 'Saving';
    const msg = document.createElement('div');
    msg.className = 'app-toast__msg';
    msg.textContent = 'Please wait...';
    body.appendChild(t);
    body.appendChild(msg);
    row.appendChild(icon);
    row.appendChild(body);
    el.appendChild(row);
    holder.appendChild(el);
    return el;
  };
  const hideSavingToast = () => {
    const el = document.getElementById('saving-toast');
    if (!el) return;
    el.classList.add('hide');
    el.addEventListener('transitionend', ()=>{ el.remove(); }, {once:true});
  };

  const modalEl = document.getElementById('onuConfigModal');
  const modalForm = document.getElementById('onuConfigForm');
  const rowIdEl = document.getElementById('cfgRowId');
  const descEl = document.getElementById('cfgDesc');
  let modalInstance = null;
  const ensureModal = () => {
    if (!modalEl || !window.bootstrap) return null;
    if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
    return modalInstance;
  };
  document.querySelectorAll('.btn-config').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if (!rowIdEl || !descEl) return;
      rowIdEl.value = btn.getAttribute('data-row-id') || '';
      descEl.value = btn.getAttribute('data-desc') || '';
      const m = ensureModal();
      if (m) m.show();
    });
  });
  if (modalForm){
    modalForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      showSavingToast();
      const data = new FormData(modalForm);
      try{
        const res = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
        const json = await res.json();
        hideSavingToast();
        if (json && json.ok){
          if (window.showToast) showToast(json.message || 'Saved', 'success', 3000);
          const rid = rowIdEl ? rowIdEl.value : '';
          if (rid){
            const cell = document.querySelector(`.desc-text[data-row-id="${rid}"]`);
            if (cell) cell.textContent = descEl ? (descEl.value || '—') : cell.textContent;
          }
          if (modalInstance) modalInstance.hide();
        } else {
          if (window.showToast) showToast((json && json.message) || 'Save failed', 'error', 3000);
        }
      } catch (_e){
        hideSavingToast();
        if (window.showToast) showToast('Save failed', 'error', 3000);
      }
    });
  }
</script>
