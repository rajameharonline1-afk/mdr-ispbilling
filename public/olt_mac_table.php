<?php
// (বাংলা) OLT MAC Table পেজের সমস্ত PHP লজিক এখানে রাখা হয়েছে

// ---------- অথেনটিকেশন + কোর ডিপেনডেন্সি লোড ----------
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/telnet.php';
require_once __DIR__ . '/../app/security_helpers.php';

$page_title = 'OLT MAC Table';
$_active    = 'olt_mac';

$db = db();

// AJAX রিকোয়েস্ট হলে শুধু JSON রেসপন্স তৈরি হবে
$isAjax = (string)($_GET['ajax'] ?? '') === '1';

// ---------- সাধারণ হেল্পার ফাংশনসমূহ: স্যানিটাইজ, সময়, পোর্ট/ONU/ম্যাক ফরম্যাট ----------
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
  if (!empty($row['onu_display_id'])) {
    return (string)$row['onu_display_id'];
  }
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
  $trim = preg_replace('/[^A-Za-z0-9_.\-+\/]/', '', $trim);
  if(function_exists('mb_substr')){
    $trim = mb_substr($trim, 0, 60, 'UTF-8');
  } else {
    $trim = substr($trim, 0, 60);
  }
  return [$trim, $trim !== $original];
}

function build_onu_row_key(int $oltId, string $portLabel, int $onuNum): string {
  return $oltId . '|' . normalize_port_label($portLabel) . '|' . $onuNum;
}

function persist_monitor_description(PDO $db, int $oltId, string $portLabel, int $onuNum, ?string $description): void {
  if ($oltId <= 0 || $onuNum <= 0) {
    return;
  }

  $normalizedPort = normalize_port_label($portLabel);

  // Keep legacy cache aligned for app-level visibility where available.
  try {
    $updLegacy = $db->prepare("UPDATE olt_mac_cache SET description = ? WHERE olt_id = ? AND onu = ?");
    $updLegacy->execute([$description, $oltId, $onuNum]);
  } catch (Throwable $e) {
  }

  // Update latest monitor payload so table reflects change immediately.
  try {
    $st = $db->prepare("SELECT id, data_json FROM onu_monitor_cache WHERE olt_id = ? ORDER BY generated_at DESC LIMIT 3");
    $st->execute([$oltId]);
    while ($cache = $st->fetch(PDO::FETCH_ASSOC)) {
      $payload = json_decode((string)($cache['data_json'] ?? ''), true);
      if (!is_array($payload) || !is_array($payload['groups'] ?? null)) {
        continue;
      }
      $changed = false;
      foreach ($payload['groups'] as $groupKey => $group) {
        if (!is_array($group) || !is_array($group['list'] ?? null)) {
          continue;
        }
        foreach ($group['list'] as $idx => $item) {
          [$family, $slot, $port, $onu] = parse_monitor_iface($item['iface'] ?? '');
          if ($onu === null || (int)$onu !== $onuNum) {
            continue;
          }
          $itemPort = build_monitor_port_label($family, $slot, $port);
          if (normalize_port_label($itemPort) !== $normalizedPort) {
            continue;
          }
          $payload['groups'][$groupKey]['list'][$idx]['description'] = $description;
          $payload['groups'][$groupKey]['list'][$idx]['desc'] = $description;
          $changed = true;
        }
      }
      if ($changed) {
        $upd = $db->prepare("UPDATE onu_monitor_cache SET data_json = ? WHERE id = ?");
        $upd->execute([json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$cache['id']]);
        break;
      }
    }
  } catch (Throwable $e) {
  }
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

function parse_monitor_iface(?string $iface): array {
  $txt = strtoupper(trim((string)$iface));
  if($txt === '') return [null, null, null, null];
  $family = null;
  if(str_starts_with($txt, 'EPON')) $family = 'EPON';
  elseif(str_starts_with($txt, 'GPON')) $family = 'GPON';

  if(preg_match('/(EPON|GPON)\s*0*([0-9]+)\/\s*0*([0-9]+)\s*:\s*0*([0-9]+)/', $txt, $m)){
    return [strtoupper($m[1]), (int)$m[2], (int)$m[3], (int)$m[4]];
  }
  if(preg_match('/(EPON|GPON)\s*0*([0-9]+)\s*ONU\s*0*([0-9]+)/', $txt, $m)){
    return [strtoupper($m[1]), (int)$m[2], null, (int)$m[3]];
  }
  if(preg_match('/PON\s*0*([0-9]+)\s*:\s*0*([0-9]+)/', $txt, $m)){
    return [$family, null, (int)$m[1], (int)$m[2]];
  }
  if(preg_match('/(\d+)\s*\/\s*(\d+)\s*:\s*(\d+)/', $txt, $m)){
    return [$family, (int)$m[1], (int)$m[2], (int)$m[3]];
  }
  if(preg_match('/(\d+)\s*:\s*(\d+)/', $txt, $m)){
    return [$family, null, (int)$m[1], (int)$m[2]];
  }
  if(preg_match('/(\d+)/', $txt, $m)){
    return [$family, null, null, (int)$m[1]];
  }
  return [null, null, null, null];
}

function build_monitor_port_label(?string $family, ?int $slot, ?int $port): string {
  $fam = $family ?: 'PON';
  if($slot !== null && $port !== null){
    return sprintf('%s %d/%d', $fam, $slot, $port);
  }
  if($port !== null){
    return sprintf('%s 0/%d', $fam, $port);
  }
  if($slot !== null){
    return sprintf('%s 0/%d', $fam, $slot);
  }
  return '—';
}

function parse_rx_metric($raw){
  if($raw === null) return null;
  if(is_numeric($raw)) return (float)$raw;
  if(is_string($raw)){
    $trim = trim($raw);
    if($trim === '' || strcasecmp($trim, 'N/A') === 0) return null;
    if(is_numeric($trim)) return (float)$trim;
    if(preg_match('/-?\d+(?:\.\d+)?/', $trim, $m)) return (float)$m[0];
    return $trim;
  }
  return null;
}

// ---------- ডেটাবেজ হেল্পার: ONU/ক্লায়েন্ট/ম্যাক সম্পর্কিত ম্যাপ তৈরির ফাংশন ----------
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
  $hasRxPower = false;
  $hasMacAddress = false;

  $clientParams = [];
  $clientWhere  = '';
  if($oltId > 0){
    $clientWhere = ' WHERE c.olt_id = ?';
    $clientParams[] = $oltId;
  }
  try{
    $clientCols = $db->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    $hasOnuMac = in_array('onu_mac', $clientCols, true);
    $hasRxPower = in_array('rx_power', $clientCols, true);
    $hasMacAddress = in_array('mac_address', $clientCols, true);
    foreach (['area','zone','location'] as $c) { if (in_array($c, $clientCols, true)) { $areaCol = $c; break; } }
    foreach (['sub_zone','subzone','sub_area'] as $c) { if (in_array($c, $clientCols, true)) { $subZoneCol = $c; break; } }
    foreach (['box','distribution_box','box_name'] as $c) { if (in_array($c, $clientCols, true)) { $boxCol = $c; break; } }
    $selectCols = "c.id, c.name, c.client_code, c.pppoe_id, c.caller_mac, c.router_mac, c.ap_mac";
    if($hasOnuMac) $selectCols .= ", c.onu_mac";
    if($hasMacAddress) $selectCols .= ", c.mac_address";
    if($hasRxPower) $selectCols .= ", c.rx_power";
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
        'rx_power' => $hasRxPower ? ($r['rx_power'] ?? null) : null,
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
      if($hasMacAddress) $macFields[] = 'mac_address';
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
    // ডাটাবেজের binding মিল না থাকলেও সারি ফাঁকা না রাখার জন্য ক্লায়েন্ট তথ্য দেখানো হচ্ছে।
    return [
      'id' => $cacheClientId,
      'name' => $r['name'] ?? '',
      'pppoe_id' => $r['pppoe_id'] ?? '',
      'client_code' => $r['client_code'] ?? '',
      'area' => $r['area_name'] ?? '',
      'sub_zone' => $r['sub_zone_name'] ?? '',
      'box' => $r['box_name'] ?? '',
      'match_via' => 'client_id',
    ];
  }
  if($oltId > 0 && $port && $onu !== PHP_INT_MAX){
    $key = "{$oltId}|{$port}|{$onu}";
    if(isset($lookup['byKey'][$key])) return $lookup['byKey'][$key] + ['match_via' => 'port_onu'];
  }
  $macKey = normalize_mac_key($row['mac'] ?? null);
  if($macKey && isset($lookup['byMac'][$macKey])){
    return $lookup['byMac'][$macKey] + ['match_via' => 'mac'];
  }
  return null;
}

function row_matches_client_code(array $row, string $code): bool {
  $needle = strtolower(trim($code));
  if($needle === '') return true;

  $meta = $row['client_meta'] ?? null;
  if(is_array($meta)){
    $metaCode = strtolower(trim((string)($meta['client_code'] ?? '')));
    if($metaCode !== '' && $metaCode === $needle) return true;

    $metaId = strtolower((string)($meta['id'] ?? ''));
    if($metaId !== '' && $metaId === $needle) return true;
  }

  $clientId = strtolower((string)($row['client_id'] ?? ''));
  if($clientId !== '' && $clientId === $needle) return true;

  if(!empty($row['clients']) && stripos((string)$row['clients'], $code) !== false){
    return true;
  }

  return false;
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

  // আগে থেকে সেট না থাকলে বা বর্তমান রেকর্ডের সাথে মিললে তবেই আপডেট করা হবে; নইলে একই ক্লায়েন্ট একাধিক সারিতে ছড়িয়ে পড়বে।
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
  if(!$match && ($mk = normalize_mac_key($row['mac'] ?? null))){
    $match = $lookup['byMac'][$mk] ?? null;
  }
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

function choose_rx_power($monitorValue, $clientValue){
  $monIsNum = $monitorValue !== null && $monitorValue !== '' && is_numeric($monitorValue);
  $clientIsNum = $clientValue !== null && $clientValue !== '' && is_numeric($clientValue);
  if($monIsNum && $clientIsNum){
    return (float)$monitorValue;
  }
  if($monIsNum) return (float)$monitorValue;
  if($clientIsNum) return (float)$clientValue;
  return $monitorValue ?? $clientValue;
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

// ---------- কুয়েরি/ফিল্টার ইনপুট ----------
$filterOlt   = (int)($_GET['olt_id'] ?? 0);
$filterPon   = (int)($_GET['pon'] ?? 0);
$filterOnu   = (int)($_GET['onu'] ?? 0);
$filterClientCode = trim((string)($_GET['client_code'] ?? ''));
if(strlen($filterClientCode) > 50){
  $filterClientCode = substr($filterClientCode, 0, 50);
}
$perPage     = 1000;

$link_notice = '';
$link_error = '';
$config_notice = '';
$config_error = '';
$expandedRowId = 0;
$toast_message = '';
$toast_type = '';
$config_ok = false;
$config_row_key = '';

// ---------- ফর্ম অ্যাকশন: ক্লায়েন্টকে নির্দিষ্ট ONU রো-এর সাথে লিংক ----------
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

// ---------- ফর্ম অ্যাকশন: ONU description ডিভাইসে আপডেট ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_desc') {
  if (!csrf_verify()) {
    $config_error = 'Invalid CSRF token.';
  } else {
    $rowId = (int)($_POST['row_id'] ?? 0);
    $cfgOltId = (int)($_POST['cfg_olt_id'] ?? 0);
    $cfgPort = trim((string)($_POST['cfg_port'] ?? ''));
    $cfgOnu = (int)($_POST['cfg_onu'] ?? 0);
    $expandedRowId = $rowId;
    $descRaw = trim((string)($_POST['description'] ?? ''));
    if ($descRaw !== '') {
      if (function_exists('mb_substr')) {
        $descRaw = mb_substr($descRaw, 0, 255, 'UTF-8');
      } else {
        $descRaw = substr($descRaw, 0, 255);
      }
    }
    if ($rowId <= 0 && ($cfgOltId <= 0 || $cfgPort === '' || $cfgOnu <= 0)) {
      $config_error = 'Invalid row.';
    } else {
      $row = null;
      if ($rowId > 0) {
        $stRow = $db->prepare("SELECT c.*, o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor, o.telnet_port, o.ssh_port, o.username, o.password, o.enable_password
                               FROM olt_mac_cache c
                               LEFT JOIN olts o ON o.id = c.olt_id
                               WHERE c.id = ? LIMIT 1");
        $stRow->execute([$rowId]);
        $row = $stRow->fetch(PDO::FETCH_ASSOC);
      }
      if (!$row && $cfgOltId > 0) {
        $stOlt = $db->prepare("SELECT o.id AS olt_id, o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor, o.telnet_port, o.ssh_port, o.username, o.password, o.enable_password
                               FROM olts o WHERE o.id = ? LIMIT 1");
        $stOlt->execute([$cfgOltId]);
        $oltRow = $stOlt->fetch(PDO::FETCH_ASSOC);
        if ($oltRow) {
          $row = $oltRow;
          $row['port'] = $cfgPort;
          $row['onu'] = (string)$cfgOnu;
          $row['id'] = 0;
        }
      }
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
                  $newDesc = $descSanitized !== '' ? $descSanitized : null;
                  if ($rowId > 0) {
                    $upd = $db->prepare("UPDATE olt_mac_cache SET description = ? WHERE id = ?");
                    $upd->execute([$newDesc, $rowId]);
                  }
                  $config_row_key = build_onu_row_key($oltId, (string)($row['port'] ?? $cfgPort), $onuNum);
                  persist_monitor_description($db, $oltId, (string)($row['port'] ?? $cfgPort), $onuNum, $newDesc);
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

// AJAX সাবমিটের দ্রুত রিটার্ন
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_desc' && (string)($_POST['ajax'] ?? '') === '1') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => $config_ok,
    'message' => $config_ok ? $config_notice : ($config_error ?: 'Save failed.'),
    'row_key' => $config_row_key,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$usingMonitorCache = false;
$monitorTableMissing = false;
$rows = [];
$monitorRowCount = 0;
$monitorOltIds = [];
$legacyCacheByKey = [];
$legacyCacheByMac = [];
$monitorLastGeneratedTs = null;
$latestRowTs = null;
$monitorRecent24 = 0;
$stats = ['total_entries'=>0,'olt_count'=>0,'last_learned'=>null];
$recent24 = 0;

// ---------- ডেটা লোড: প্রথমে onu_monitor_cache (নতুন স্কিমা), না থাকলে olt_mac_cache ----------
try{
  $monParams = [];
  $monWhere  = '';
  if($filterOlt > 0){
    $monWhere = 'WHERE c.olt_id = ?';
    $monParams[] = $filterOlt;
  }
  $monSql = "SELECT c.*, o.name AS olt_name, o.host AS olt_host, o.vendor AS olt_vendor
             FROM onu_monitor_cache c
             LEFT JOIN olts o ON o.id = c.olt_id
             {$monWhere}
             ORDER BY c.generated_at DESC";
  $monStmt = $db->prepare($monSql);
  $monStmt->execute($monParams);
  $cacheRows = $monStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($cacheRows as $cacheRow){
    $usingMonitorCache = true;
    $oltId = (int)($cacheRow['olt_id'] ?? 0);
    if($oltId > 0) $monitorOltIds[$oltId] = true;
    $generatedAt = $cacheRow['generated_at'] ?? null;
    $genTs = $generatedAt ? (strtotime((string)$generatedAt) ?: null) : null;
    if($genTs !== null){
      if($monitorLastGeneratedTs === null || $genTs > $monitorLastGeneratedTs){
        $monitorLastGeneratedTs = $genTs;
      }
    }
    $payload = json_decode($cacheRow['data_json'] ?? '', true);
    if(is_array($payload) && is_array(($payload['groups'] ?? null))){
      foreach($payload['groups'] as $ponLabel => $group){
        if(empty($group['list']) || !is_array($group['list'])) continue;
        foreach($group['list'] as $item){
          [$family,$slot,$port,$onu] = parse_monitor_iface($item['iface'] ?? '');
          if($onu === null) continue;
          $rawPort = build_monitor_port_label($family, $slot, $port);
          $normalizedPort = normalize_port_label($rawPort);
          $ponSlot = $port ?? $slot;
          if($ponSlot === null && preg_match('/(\d+)/', (string)$ponLabel, $m)){ $ponSlot = (int)$m[1]; }
          $rxVal = parse_rx_metric($item['rx'] ?? ($item['rx_power'] ?? null));
          $macDisplay = format_mac_display($item['mac'] ?? '') ?? strtoupper((string)($item['mac'] ?? ''));
          $vlanVal = $item['vlan'] ?? ($cacheRow['vlan'] ?? null);
          if($vlanVal === '' || $vlanVal === 'N/A') $vlanVal = null;
          $distanceVal = $item['distance_m'] ?? null;
          if($distanceVal !== null && is_numeric($distanceVal)){
            $distanceVal = (float)$distanceVal;
            if($distanceVal <= 0) $distanceVal = null;
          }
          $deregReason = $item['last_dereg_reason'] ?? null;
          $deregTime = $item['last_dereg_time'] ?? null;
          $rowTs = $genTs;
          $row = [
            'id' => 0,
            'olt_id' => $oltId,
            'olt_vendor' => $cacheRow['olt_vendor'] ?? null,
            'olt_name' => $cacheRow['olt_name'] ?? null,
            'olt_host' => $cacheRow['olt_host'] ?? null,
            'port' => $rawPort,
            'normalized_port' => $normalizedPort,
            'onu' => $onu,
            'mac' => $macDisplay,
            'description' => $item['description'] ?? ($item['desc'] ?? null),
            'status' => $item['status'] ?? null,
            'rx_power_dbm' => $rxVal,
            'distance_m' => $distanceVal,
            'vlan' => $vlanVal,
            'last_dereg_reason' => $deregReason,
            'last_dereg_time' => $deregTime,
            'learned_at' => $generatedAt,
            'client_id' => null,
            'pon_slot' => $ponSlot,
            'cache_source' => 'onu_monitor_cache',
          ];
          $rows[] = $row;
          $monitorRowCount++;
          if($rowTs !== null && $rowTs >= time() - 86400){
            $monitorRecent24++;
          }
        }
      }
      continue;
    }

    // ফ্যালব্যাক: নতুন স্কিমায় প্রতি ONU সরাসরি onu_monitor_cache টেবিলে থাকতে পারে।
    $flatPort = $cacheRow['port'] ?? ($cacheRow['iface'] ?? ($cacheRow['pon_port'] ?? null));
    $normalizedPort = normalize_port_label($flatPort);
    $flatOnu = $cacheRow['onu'] ?? ($cacheRow['onu_id'] ?? ($cacheRow['onu_no'] ?? null));
    $rxVal = parse_rx_metric($cacheRow['rx_power_dbm'] ?? ($cacheRow['rx_power'] ?? ($cacheRow['rx'] ?? null)));
    $distanceVal = $cacheRow['distance_m'] ?? ($cacheRow['distance'] ?? null);
    if($distanceVal !== null && is_numeric($distanceVal)){
      $distanceVal = (float)$distanceVal;
      if($distanceVal <= 0) $distanceVal = null;
    }
    $ponSlot = null;
    foreach(['pon_slot','pon','slot','pon_id','pon_port'] as $f){
      if(isset($cacheRow[$f]) && $cacheRow[$f] !== null && $cacheRow[$f] !== ''){
        $ponSlot = (int)$cacheRow[$f];
        if($ponSlot <= 0) $ponSlot = null;
        break;
      }
    }
    $learnedAt = $generatedAt ?? ($cacheRow['learned_at'] ?? ($cacheRow['updated_at'] ?? ($cacheRow['created_at'] ?? null)));
    $rowTs = $learnedAt ? (strtotime((string)$learnedAt) ?: null) : $genTs;
    if($rowTs !== null){
      if($monitorLastGeneratedTs === null || $rowTs > $monitorLastGeneratedTs){
        $monitorLastGeneratedTs = $rowTs;
      }
    }
    $row = [
      'id' => (int)($cacheRow['id'] ?? 0),
      'olt_id' => $oltId,
      'olt_vendor' => $cacheRow['olt_vendor'] ?? ($cacheRow['vendor'] ?? null),
      'olt_name' => $cacheRow['olt_name'] ?? null,
      'olt_host' => $cacheRow['olt_host'] ?? null,
      'port' => $flatPort,
      'normalized_port' => $normalizedPort,
      'onu' => $flatOnu,
      'mac' => format_mac_display($cacheRow['mac'] ?? '') ?? strtoupper((string)($cacheRow['mac'] ?? '')),
      'description' => $cacheRow['description'] ?? ($cacheRow['desc'] ?? null),
      'status' => $cacheRow['status'] ?? null,
      'rx_power_dbm' => $rxVal,
      'distance_m' => $distanceVal,
      'vlan' => $cacheRow['vlan'] ?? null,
      'last_dereg_reason' => $cacheRow['last_dereg_reason'] ?? null,
      'last_dereg_time' => $cacheRow['last_dereg_time'] ?? null,
      'learned_at' => $learnedAt,
      'client_id' => $cacheRow['client_id'] ?? null,
      'pon_slot' => $ponSlot,
      'cache_source' => 'onu_monitor_cache',
    ];
    $rows[] = $row;
    $monitorRowCount++;
    if($rowTs !== null && $rowTs >= time() - 86400){
      $monitorRecent24++;
    }
  }
} catch (PDOException $e) {
  if (strpos($e->getMessage(), '42S02') !== false) {
    $monitorTableMissing = true;
  } else {
    throw $e;
  }
}

if($usingMonitorCache){
  // মনিটর ডাটা ফাঁকা হলে olt_mac_cache থেকে RX/দূরত্ব এনে ফ্যালব্যাক তৈরি
  try{
    $legacyParams = [];
    $where = '';
    if($filterOlt > 0){
      $where = 'WHERE olt_id = ?';
      $legacyParams[] = $filterOlt;
    } elseif($monitorOltIds){
      $placeholders = implode(',', array_fill(0, count($monitorOltIds), '?'));
      $where = "WHERE olt_id IN ({$placeholders})";
      $legacyParams = array_keys($monitorOltIds);
    }
    $legacySql = "SELECT olt_id, port, onu, mac, rx_power_dbm, distance_m FROM olt_mac_cache {$where}";
    $legacyStmt = $db->prepare($legacySql);
    $legacyStmt->execute($legacyParams);
    while($lr = $legacyStmt->fetch(PDO::FETCH_ASSOC)){
      $oltId = (int)($lr['olt_id'] ?? 0);
      $portNorm = normalize_port_label($lr['port'] ?? '');
      $onuNum = onu_numeric($lr['onu'] ?? '');
      if($oltId > 0 && $portNorm !== '—' && $onuNum !== PHP_INT_MAX){
        $legacyCacheByKey["{$oltId}|{$portNorm}|{$onuNum}"] = [
          'rx' => $lr['rx_power_dbm'] ?? null,
          'distance' => $lr['distance_m'] ?? null,
        ];
      }
      $mk = normalize_mac_key($lr['mac'] ?? null);
      if($mk){
        $legacyCacheByMac[$mk] = [
          'rx' => $lr['rx_power_dbm'] ?? null,
          'distance' => $lr['distance_m'] ?? null,
          'olt_id' => $oltId,
          'port' => $portNorm,
          'onu' => $onuNum,
        ];
      }
    }
  } catch(Throwable $e){}

  $stats = [
    'total_entries' => $monitorRowCount,
    'olt_count' => count($monitorOltIds),
    'last_learned' => $monitorLastGeneratedTs ? date('Y-m-d H:i:s', $monitorLastGeneratedTs) : null,
  ];
  $recent24 = $monitorRecent24;
  $tableMissing = false;
} else {
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
}

// ---------- প্রসেসিং: ফিল্টার, ক্লায়েন্ট-ম্যাপিং, ডিডুপ ----------
$groupedMacs = [];
$ponSummary = [];
$ponTotals  = ['total_pons'=>0,'total_onu'=>0];
$clientMacCache = $filterOlt > 0 ? load_onu_client_mac_rows($db, $filterOlt) : [];
$clientLookup   = load_client_lookup($db, $filterOlt);
if(!$tableMissing && $rows){
  $processedRows = [];
  foreach($rows as $row){
    $row['normalized_port'] = normalize_port_label($row['port'] ?? '');
    $slot = isset($row['pon_slot']) && $row['pon_slot'] !== null ? (int)$row['pon_slot'] : null;
    if($slot === 0) $slot = null;
    if($slot === null){
      if(preg_match('/0\/(\d{1,2})/', $row['normalized_port'], $m)){
        $slot = (int)$m[1];
      } elseif(preg_match('/PON\s*(\d+)/i', $row['normalized_port'], $m)){
        $slot = (int)$m[1];
      }
    }
    $onuRaw = trim((string)($row['onu'] ?? ''));
    $onuNum = onu_numeric($row['onu'] ?? '');
    if(!$slot || $onuRaw === '' || $onuRaw === '—'){
      continue;
    }
    if($filterOnu > 0 && $onuNum !== $filterOnu){
      continue;
    }
    $row['pon_slot'] = $slot;
    $label = 'PON '.$slot;
    if($usingMonitorCache){
      $row['client_id'] = $row['client_id'] ?? null;
    } else {
      $backfilled = backfill_cache_client_id($db, $row, $clientLookup);
      if($backfilled){
        $row['client_id'] = $backfilled;
        update_client_olt_binding($db, $row, $backfilled);
      } elseif(!empty($row['client_id'])) {
        update_client_olt_binding($db, $row, (int)$row['client_id']);
      }
    }
    $row['client_meta'] = match_client_for_mac_row($row, $clientLookup);
    // client_id থাকলেও কোড/নাম অনুপস্থিত হলে পরের ধাপে দেখানোর জন্য এখানে ধরে রাখা হচ্ছে।
    if (empty($row['client_meta']) && !empty($row['client_id'])) {
      $cidLookup = (int)$row['client_id'];
      if ($cidLookup > 0 && isset($clientLookup['byId'][$cidLookup])) {
        $row['client_code_lookup'] = $clientLookup['byId'][$cidLookup]['client_code'] ?? '';
        $row['client_name_lookup'] = $clientLookup['byId'][$cidLookup]['name'] ?? '';
      }
    }
    // যদি "clients" কলামে সংখ্যাসূচক আইডি থাকে, সেটিকে কোড/নামে ম্যাপ করে দেখানো হবে।
    if (empty($row['client_meta']) && empty($row['client_code_lookup']) && isset($row['clients'])) {
      $clientsRaw = trim((string)$row['clients']);
      if ($clientsRaw !== '' && ctype_digit($clientsRaw)) {
        $cidLookup = (int)$clientsRaw;
        if ($cidLookup > 0 && isset($clientLookup['byId'][$cidLookup])) {
          $row['client_code_lookup'] = $clientLookup['byId'][$cidLookup]['client_code'] ?? '';
          $row['client_name_lookup'] = $clientLookup['byId'][$cidLookup]['name'] ?? '';
          $row['client_id_lookup'] = $cidLookup;
        }
      }
    }
    if($filterClientCode !== '' && !row_matches_client_code($row, $filterClientCode)){
      continue;
    }
    if((!isset($row['description']) || $row['description'] === null || $row['description'] === '') && !empty($row['client_meta']['name'])){
      $row['description'] = $row['client_meta']['name'];
    }
    if($usingMonitorCache){
      $mk = normalize_mac_key($row['mac'] ?? null);
      $fallback = null;
      if($row['olt_id'] && $row['normalized_port'] && ($onuNum = onu_numeric($row['onu'] ?? '')) !== PHP_INT_MAX){
        $cacheKey = "{$row['olt_id']}|{$row['normalized_port']}|{$onuNum}";
        $fallback = $legacyCacheByKey[$cacheKey] ?? null;
      }
      if(!$fallback && $mk && isset($legacyCacheByMac[$mk])){
        $fallback = $legacyCacheByMac[$mk];
      }
      if($fallback){
        if(($row['rx_power_dbm'] === null || $row['rx_power_dbm'] === '' || !is_numeric($row['rx_power_dbm'])) && isset($fallback['rx']) && $fallback['rx'] !== null && $fallback['rx'] !== '' && is_numeric($fallback['rx'])){
          $row['rx_power_dbm'] = (float)$fallback['rx'];
        }
        if(($row['distance_m'] === null || $row['distance_m'] === '' || !is_numeric($row['distance_m'])) && isset($fallback['distance']) && $fallback['distance'] !== null && $fallback['distance'] !== '' && is_numeric($fallback['distance'])){
          $row['distance_m'] = (float)$fallback['distance'];
        }
      }
    }
    $clientRxPower = $row['client_meta']['rx_power'] ?? null;
    if($usingMonitorCache){
      $row['rx_power_dbm'] = choose_rx_power($row['rx_power_dbm'] ?? null, $clientRxPower);
      if(!empty($row['distance_m']) && is_numeric($row['distance_m']) && (float)$row['distance_m'] <= 0){
        $row['distance_m'] = null;
      }
    } else {
      // ক্যাশে RX না থাকলে ক্লায়েন্ট প্রোফাইলে থাকা rx_power মান ব্যবহার করা হবে।
      $row['rx_power_dbm'] = choose_rx_power($row['rx_power_dbm'] ?? null, $clientRxPower);
    }
    if(!empty($row['last_dereg_time'])){
      $tsLdr = strtotime((string)$row['last_dereg_time']);
      if($tsLdr){ $row['last_dereg_time'] = date('Y-m-d H:i', $tsLdr); }
    }
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
      $oltKey = (int)($row['olt_id'] ?? 0);
      if($onuNum !== PHP_INT_MAX){
        $dedupKey = "{$oltKey}|{$baseKey}|{$onuNum}";
      } else {
        $dedupKey = "{$oltKey}|{$baseKey}|".strtolower($row['mac'] ?? '');
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

  // EPONO সিরিয়াল ম্যাপ: PON স্লট অনুযায়ী EPONO/1, EPONO/2 ... এবং প্রতি PON-এ ONU :1, :2 ...
  $ponSerialBySlot = [];
  $serialCounter = 1;
  foreach($groupedMacs as $g){
    $slot = (int)($g['slot'] ?? 0);
    if($slot <= 0) continue;
    $ponSerialBySlot[$slot] = $serialCounter++;
  }
  foreach($groupedMacs as &$g){
    $slot = (int)($g['slot'] ?? 0);
    $ponSerial = $ponSerialBySlot[$slot] ?? null;
    $g['label'] = $ponSerial !== null ? ('EPONO/'.$ponSerial) : ($g['label'] ?? 'EPONO/-');

    $onuSerialByNumber = [];
    $onuList = [];
    foreach(($g['rows'] ?? []) as $r){
      $onuNum = onu_numeric($r['onu'] ?? '');
      if($onuNum !== PHP_INT_MAX){
        $onuList[] = $onuNum;
      }
    }
    $onuList = array_values(array_unique($onuList));
    sort($onuList, SORT_NUMERIC);
    foreach($onuList as $idx => $onuNum){
      $onuSerialByNumber[(int)$onuNum] = $idx + 1;
    }

    foreach($g['rows'] as &$r){
      $onuNum = onu_numeric($r['onu'] ?? '');
      $onuSerial = ($onuNum !== PHP_INT_MAX) ? ($onuSerialByNumber[$onuNum] ?? null) : null;
      $r['pon_serial'] = $ponSerial;
      $r['onu_serial'] = $onuSerial;
      $r['onu_display_id'] = 'EPONO/'.($ponSerial ?? '-').':'.($onuSerial ?? '-');
    }
    unset($r);
  }
  unset($g);
}
$ponOptions = [];
if ($groupedMacs) {
  foreach ($groupedMacs as $g) {
    $cnt = isset($g['rows']) && is_array($g['rows']) ? count($g['rows']) : 0;
    $slot = (int)($g['slot'] ?? 0);
    $label = $g['label'] ?? ($slot ? ('PON '.$slot) : 'PON');
    $ponSummary[] = ['slot'=>$slot, 'label'=>$label, 'count'=>$cnt];
    $ponTotals['total_pons']++;
    $ponTotals['total_onu'] += $cnt;
  }
  $ponOptions = [];
  foreach($groupedMacs as $g){
    $slotVal = (int)($g['slot'] ?? 0);
    if($slotVal <= 0) continue;
    $ponOptions[] = [
      'slot' => $slotVal,
      'label' => (string)($g['label'] ?? ('EPONO/'.$slotVal)),
    ];
  }
}
// Default: যদি কোনো PON সিলেক্ট না থাকে, প্রথম PON দেখাবে
if($filterPon > 0 && $groupedMacs){
  $groupedMacs = array_filter($groupedMacs, fn($g)=> (int)($g['slot'] ?? 0) === $filterPon);
}
if($filterOnu > 0 && $groupedMacs){
  foreach($groupedMacs as &$g){
    $g['rows'] = array_values(array_filter($g['rows'], fn($r)=> onu_numeric($r['onu'] ?? '') === $filterOnu));
  }
  unset($g);
  $groupedMacs = array_filter($groupedMacs, fn($g)=> !empty($g['rows']));
}
$latestRowTs = null;
if($rows){
  foreach($rows as $r){
    $ts = strtotime($r['learned_at'] ?? '');
    if($ts && ($latestRowTs === null || $ts > $latestRowTs)){
      $latestRowTs = $ts;
    }
  }
}
if(!$usingMonitorCache){
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
  }
}

$lastLearnedAt = $stats['last_learned'] ?? null;
if($latestRowTs !== null){
  $statTs = $lastLearnedAt ? strtotime((string)$lastLearnedAt) : null;
  if($statTs === false || $statTs === null || $latestRowTs > $statTs){
    $lastLearnedAt = date('Y-m-d H:i:s', $latestRowTs);
  }
}
$lastLearnedHuman = $lastLearnedAt ? diff_for_humans($lastLearnedAt) : '—';
$olts = $db->query("SELECT id, name, host FROM olts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedOlt = null;
if($filterOlt > 0){
  foreach($olts as $o){ if($o['id']===$filterOlt){ $selectedOlt=$o; break; } }
}

// ---------- UI রেন্ডার ফাংশন (টেবিল + সামারি) ----------
function render_olt_table_block(array $groupedMacs, array $clientMacCache, int $filterOlt): void
{
?>
  <div class="container-fluid olt-table-area">
    <div class="table-responsive olt-table-wrap">
      <?php if ($filterOlt <= 0): ?>
        <!-- <div class="p-4 text-center text-muted">দয়া করে প্রথমে OLT সিলেক্ট করুন।</div> -->
      <?php elseif ($groupedMacs): ?>
        <table class="table table-hover table-sm align-middle olt-mac-table table-app">
          <thead class="olt-table-header">
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
            <?php foreach ($groupedMacs as $group): ?>
              <?php foreach ($group['rows'] as $row): ?>
                <tr>
                  <td data-label="ONU ID"><?= h(format_onu_identifier($row)); ?></td>
                  <td data-label="Client">
                    <?php
                    $clientValue = '';
                    $clientLinkId = null;
                    if (!empty($row['client_meta'])) {
                      $cm = $row['client_meta'];
                      $clientCodeDisplay = trim((string)($cm['client_code'] ?? ''));
                      $clientNameDisplay = trim((string)($cm['name'] ?? ''));
                      if ($clientCodeDisplay !== '') {
                        $clientValue = $clientCodeDisplay . ($clientNameDisplay !== '' ? ':' . $clientNameDisplay : '');
                      } elseif ($clientNameDisplay !== '') {
                        $clientValue = $clientNameDisplay;
                      }
                      $clientIdInt = isset($cm['id']) ? (int)$cm['id'] : 0;
                      $clientLinkId = $clientIdInt > 0 ? $clientIdInt : null;
                    } elseif (isset($row['clients'])) {
                      $clientsRaw = trim((string)$row['clients']);
                      if ($clientsRaw !== '' && ctype_digit($clientsRaw)) {
                        $maybeId = (int)$clientsRaw;
                        if ($maybeId > 0) {
                          $clientLinkId = $maybeId;
                        }
                        $codeLookup = trim((string)($row['client_code_lookup'] ?? ''));
                        $nameLookup = trim((string)($row['client_name_lookup'] ?? ''));
                        if ($codeLookup !== '') {
                          $clientValue = $codeLookup . ($nameLookup !== '' ? ':' . $nameLookup : '');
                        } elseif ($nameLookup !== '') {
                          $clientValue = $nameLookup;
                        }
                      } else {
                        $clientValue = $clientsRaw;
                      }
                    } else {
                      $clientIdRaw = isset($row['client_id']) ? (int)$row['client_id'] : 0;
                      $fallbackCode = trim((string)($row['client_code_lookup'] ?? $row['client_code'] ?? ''));
                      $fallbackName = trim((string)($row['client_name_lookup'] ?? ''));
                      if ($fallbackCode !== '') {
                        $clientValue = $fallbackCode . ($fallbackName !== '' ? ':' . $fallbackName : '');
                      } elseif ($fallbackName !== '') {
                        $clientValue = $fallbackName;
                      }
                      if ($clientIdRaw > 0) {
                        $clientLinkId = $clientIdRaw;
                      }
                    }
                    $clientValueTrim = trim($clientValue);
                    $clientMissing = $clientValueTrim === '' || $clientValueTrim === '-' || $clientValueTrim === '—';
                    ?>
                    <?php if ($clientMissing): ?>
                      <span class="text-muted "> — </span>
                    <?php elseif ($clientLinkId !== null): ?>
                      <a href="/public/client_view.php?id=<?= $clientLinkId; ?>" class="fw-semibold text-decoration-none">
                        <?= h($clientValue); ?>
                      </a>
                    <?php else: ?>
                      <span class="fw-semibold"><?= h($clientValue); ?></span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Area"><?= !empty($row['client_meta']) && ($row['client_meta']['area'] ?? '') !== '' ? h($row['client_meta']['area']) : '—' ?></td>
                  <td data-label="Sub Zone"><?= !empty($row['client_meta']) && ($row['client_meta']['sub_zone'] ?? '') !== '' ? h($row['client_meta']['sub_zone']) : '—' ?></td>
                  <td data-label="Box"><?= !empty($row['client_meta']) && ($row['client_meta']['box'] ?? '') !== '' ? h($row['client_meta']['box']) : '—' ?></td>
                  <?php
                    $cfgPortLabel = (string)($row['normalized_port'] ?? normalize_port_label($row['port'] ?? ''));
                    $cfgOnuNum = onu_numeric($row['onu'] ?? '');
                    $cfgOltId = (int)($row['olt_id'] ?? 0);
                    $cfgRowKey = ($cfgOltId > 0 && $cfgPortLabel !== '—' && $cfgOnuNum !== PHP_INT_MAX)
                      ? build_onu_row_key($cfgOltId, $cfgPortLabel, $cfgOnuNum)
                      : '';
                  ?>
                  <td data-label="Description"><span class="desc-text" data-row-id="<?= (int)($row['id'] ?? 0); ?>" data-row-key="<?= h($cfgRowKey); ?>"><?= h($row['description'] !== null && $row['description'] !== '' ? $row['description'] : '—'); ?></span></td>
                  <td data-label="MAC"><code class="fw-semibold"><?= h(strtoupper($row['mac'])); ?></code></td>
                  <?php
                  $clientKey = client_mac_lookup_key($row);
                  $clientMacRows = $clientKey && isset($clientMacCache[$clientKey]) ? $clientMacCache[$clientKey] : [];
                  $vlanBadges = [];
                  if (isset($row['vlan']) && $row['vlan'] !== null && $row['vlan'] !== '') {
                    $vlanBadges[] = $row['vlan'];
                  }
                  if (!$vlanBadges && $clientMacRows) {
                    foreach ($clientMacRows as $cm) {
                      if (isset($cm['vlan']) && $cm['vlan'] !== null && $cm['vlan'] !== '') {
                        $vlanBadges[] = $cm['vlan'];
                      }
                    }
                    $vlanBadges = array_values(array_unique($vlanBadges));
                  }
                  ?>
                  <td data-label="VLAN">
                    <?php if ($vlanBadges): ?>
                      <?php foreach ($vlanBadges as $vb): ?>
                        <span class="badge text-bg-secondary me-1 mb-1"><?= h($vb); ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Status">
                    <?php [$statusLabel, $statusClass] = status_badge_meta(resolve_row_status($row)); ?>
                    <span class="<?= $statusClass; ?>"><?= $statusLabel; ?></span>
                  </td>
                  <?php
                  $distanceRaw = $row['distance_m'] ?? null;
                  $rxRaw = $row['rx_power_dbm'] ?? null;
                  $distanceIsNumeric = $distanceRaw !== null && $distanceRaw !== '' && is_numeric($distanceRaw);
                  $rxIsNumeric = $rxRaw !== null && $rxRaw !== '' && is_numeric($rxRaw);
                  $distanceNum = $distanceIsNumeric ? (float)$distanceRaw : null;
                  $rxNum = $rxIsNumeric ? (float)$rxRaw : null;
                  if ($distanceNum !== null && $distanceNum < 0) {
                    if ($rxNum !== null && $rxNum > 0) {
                      $tmp = $rxRaw;
                      $rxRaw = $distanceRaw;
                      $distanceRaw = $tmp;
                    } else {
                      $rxRaw = $distanceRaw;
                      $distanceRaw = null;
                    }
                  } elseif ($rxNum !== null && $rxNum > 100 && ($distanceNum === null || $distanceNum < 0)) {
                    $tmp = $rxRaw;
                    $rxRaw = $distanceRaw;
                    $distanceRaw = $tmp;
                  }
                  $distanceDisplay = format_metric($distanceRaw, 0);
                  $rxDisplay = format_metric($rxRaw);
                  [$rxLabel, $rxClass] = rx_power_meta($rxRaw);
                  ?>
                  <td class="olt-dist-col" data-label="Dist.(m)"><?= h($distanceDisplay); ?></td>
                  <td class="olt-rx-cell olt-rx-col" data-label="L.(dBm)">
                    <span><?= h($rxDisplay); ?></span>
                    <?php if ($rxLabel): ?>
                      <span class="badge <?= $rxClass; ?>"><?= $rxLabel; ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="olt-dereg-cell" data-label="LDR">
                    <?php
                    $deregReason = $row['last_dereg_reason'] ?? null;
                    $deregTime = $row['last_dereg_time'] ?? null;
                    $deregReasonDisplay = ($deregReason === null || $deregReason === '') ? '—' : $deregReason;
                    ?>
                    <div><?= h($deregReasonDisplay); ?></div>
                    <?php if ($deregTime): ?>
                      <div class="text-muted small"><?= h($deregTime); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end" data-label="Action">
                    <?php $cfgDisabled = ($cfgRowKey === ''); ?>
                    <button class="btn btn-outline-primary btn-sm btn-config"
                      type="button"
                      <?= $cfgDisabled ? 'disabled' : ''; ?>
                      data-row-id="<?= (int)($row['id'] ?? 0); ?>"
                      data-row-key="<?= h($cfgRowKey); ?>"
                      data-olt-id="<?= (int)$cfgOltId; ?>"
                      data-port="<?= h($cfgPortLabel); ?>"
                      data-onu="<?= $cfgOnuNum === PHP_INT_MAX ? '' : (int)$cfgOnuNum; ?>"
                      data-source="<?= h((string)($row['cache_source'] ?? '')); ?>"
                      data-desc="<?= h($row['description'] ?? ''); ?>">
                      Configure
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="p-4 text-center text-muted">ONU MAC পাওয়া যায়নি।</div>
      <?php endif; ?>
    </div>
  </div>
<?php
}

function render_olt_summary_block(?array $selectedOlt, array $ponSummary, array $ponTotals, int $filterOlt): void
{
  if (!$selectedOlt || $filterOlt <= 0) {
    echo '';
    return;
  }
?>
  <div class="d-flex align-items-center justify-content-between olt-summary-block">
    <div class=" accordion-body">
      <span class="fw-semibold">OLT NAME : <?= h($selectedOlt['name'] ?: 'Unnamed OLT'); ?> |IP: <?= h($selectedOlt['host'] ?? ''); ?></span>
    </div>
    <?php if (!empty($ponSummary)): ?>
      <div class=" d-flex align-items-center gap-2 flex-wrap">
        <span class="text-muted small">PON Ports: <?= (int)$ponTotals['total_pons']; ?> • Total ONU: <?= (int)$ponTotals['total_onu']; ?></span>
        <?php foreach ($ponSummary as $ps): ?>
          <span class="badge text-bg-light border"><?= h($ps['label'] ?? 'PON :'); ?> • ONU : <?= (int)($ps['count'] ?? 0); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php
}

// ---------- AJAX রিকোয়েস্ট হলে HTML ব্লক JSON আকারে পাঠানো ----------
if ($isAjax) {
  $tableHtml = '';
  if ($filterOlt <= 0) {
    // $tableHtml = '<div class="p-4 text-center text-muted">দয়া করে প্রথমে OLT সিলেক্ট করুন।</div>';
  } else {
    ob_start();
    render_olt_table_block($groupedMacs, $clientMacCache, $filterOlt);
    $tableHtml = ob_get_clean();
  }
  ob_start();
  render_olt_summary_block($selectedOlt ?? null, $ponSummary ?? [], $ponTotals ?? [], $filterOlt);
  $summaryHtml = ob_get_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'html' => $tableHtml,
    'summary' => $summaryHtml,
    'pon_options' => $filterOlt > 0 ? array_values($ponOptions ?? []) : [],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- ফুল পেজ রেন্ডার ----------
require_once __DIR__ . '/../partials/partials_header.php';
?>
<link rel="stylesheet" href="/assets/css/custom_modern.css?v=<?= filemtime(__DIR__ . '/../assets/css/custom_modern.css'); ?>">
<div class="olt-mac-wrap container-fluid">
  <div class="container-admin olt-mac-table-page">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-semibold">ONU Table</span>
        <form id="clientCodeSearchForm" class="d-flex align-items-center gap-2 fw-semibold" method="get" action="">
          <input type="hidden" name="olt_id" value="<?= (int)$filterOlt; ?>">
          <input type="hidden" name="pon" value="<?= (int)$filterPon; ?>">
          <input id="clientCodeInput" type="text" name="client_code" class="form-control form-control-sm" placeholder="Client code" value="<?= h($filterClientCode); ?>" style="min-width: 180px;" autocomplete="off">
        </form>
      </div>
      <span class="fw-semibold"><i class="bi bi-hdd-fill"></i> <?= h($lastLearnedHuman); ?></span>
    </div>
    <div class="container-fluid olt-sticky-header">
      <div class="olt-filters-wrap mb-3">
        <form id="oltFiltersForm" class="card shadow-sm" method="get" action="">
          <div class="d-flex justify-content-between align-items-center gap-3 p-3 flex-wrap">
            <input type="hidden" name="client_code" id="clientCodeHidden" value="<?= h($filterClientCode); ?>">
            <div class="fw-semibold">
              <select name="olt_id" class="form-select" required>
                <option value="0" disabled <?= $filterOlt === 0 ? 'selected' : ''; ?>>OLT সিলেক্ট করুন</option>
                <?php foreach ($olts as $olt): ?>
                  <?php $oltId = (int)$olt['id']; ?>
                  <option value="<?= $oltId; ?>" <?= (int)$filterOlt === $oltId ? 'selected' : ''; ?>>
                    <?= h($olt['name'] ?: $olt['host']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="fw-semibold">
              <select name="pon" class="form-select" <?= $filterOlt === 0 ? 'disabled' : ''; ?>>
                <option value="0" disabled <?= $filterPon === 0 ? 'selected' : ''; ?>>PON সিলেক্ট করুন</option>
                <?php if (!empty($ponOptions) && $filterOlt > 0): ?>
                  <?php foreach ($ponOptions as $ponOpt): ?>
                    <?php $slotVal = (int)($ponOpt['slot'] ?? 0); ?>
                    <?php $slotLabel = trim((string)($ponOpt['label'] ?? 'EPONO/'.$slotVal)); ?>
                    <option value="<?= $slotVal; ?>" <?= (int)$filterPon === $slotVal ? 'selected' : ''; ?>>
                      <?= h($slotLabel); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

          </div>


          <div id="oltSummary">
            <?php render_olt_summary_block($selectedOlt ?? null, $ponSummary ?? [], $ponTotals ?? [], $filterOlt); ?>
          </div>
        </form>
      </div>
    </div>

    <?php render_olt_table_block($groupedMacs, $clientMacCache, $filterOlt); ?>
  </div>
</div>

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
        <input type="hidden" name="cfg_row_key" id="cfgRowKey" value="">
        <input type="hidden" name="cfg_olt_id" id="cfgOltId" value="">
        <input type="hidden" name="cfg_port" id="cfgPort" value="">
        <input type="hidden" name="cfg_onu" id="cfgOnu" value="">
        <input type="hidden" name="cfg_source" id="cfgSource" value="">
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

<?php if ($toast_message !== '' && $toast_type !== ''): ?>
  <script>
    window.addEventListener('load', function(){
      if (window.showToast) {
        showToast('<?= h($toast_message) ?>', '<?= h($toast_type) ?>', 3000);
      }
    });
  </script>
<?php endif; ?>
<script src="/assets/js/olt_mac_table.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/olt_mac_table.js'); ?>" defer></script>
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
