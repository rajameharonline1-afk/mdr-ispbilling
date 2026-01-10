<?php
// /cron/auto_link_pppoe_olt.php
// Auto-link clients to OLT cache using PPPoE active sessions (every 5 minutes).
// CLI: php /var/www/isp_billing/cron/auto_link_pppoe_olt.php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

function println(string $s=''){ echo $s.PHP_EOL; }

function norm_mac(string $s): string {
  $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $s));
  if (strlen($hex) < 12) return '';
  $hex = substr($hex, 0, 12);
  return implode(':', str_split($hex, 2));
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

function onu_numeric(?string $onu): int {
  if($onu && preg_match('/(\d+)/', $onu, $m)){
    return (int)$m[1];
  }
  return PHP_INT_MAX;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// optional lock to avoid overlap
$lock = $pdo->query("SELECT GET_LOCK('cron_pppoe_olt_link', 1)")->fetchColumn();
if ((int)$lock !== 1) {
  println("Lock busy, skipping.");
  exit;
}

try {
  $cols = $pdo->query("SHOW COLUMNS FROM routers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $hasStatus = in_array('status', $cols, true);
  $hasType = in_array('type', $cols, true);

  $sqlRouters = "SELECT id, name, ip, username, password, api_port FROM routers WHERE 1";
  $params = [];
  if ($hasType) { $sqlRouters .= " AND type='mikrotik'"; }
  if ($hasStatus){ $sqlRouters .= " AND status=1"; }
  $st = $pdo->prepare($sqlRouters);
  $st->execute($params);
  $routers = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$routers) {
    println("No routers found.");
    exit;
  }

  $ridList = array_map(fn($r) => $r['id'], $routers);
  $placeholders = implode(',', array_fill(0, count($ridList), '?'));
  $clientIdx = []; // [router_id][pppoe_id] => client_id
  $sqlC = "SELECT id, router_id, pppoe_id, router_mac FROM clients WHERE router_id IN ($placeholders)";
  $stc = $pdo->prepare($sqlC);
  $stc->execute($ridList);
  while ($c = $stc->fetch(PDO::FETCH_ASSOC)) {
    $rid = (string)$c['router_id'];
    $pp  = trim((string)$c['pppoe_id']);
    if ($pp === '') continue;
    $clientIdx[$rid][$pp] = [
      'id' => (int)$c['id'],
      'router_mac' => trim((string)($c['router_mac'] ?? '')),
    ];
  }

  $updRouterMac = $pdo->prepare("
    UPDATE clients
       SET router_mac = :mac, updated_at = NOW()
     WHERE id = :id
       AND (router_mac IS NULL OR router_mac = '' OR router_mac <> :mac)
  ");

  $updCacheClient = $pdo->prepare("
    UPDATE olt_mac_cache
       SET client_id = :client_id
     WHERE (client_id IS NULL OR client_id = 0)
       AND REPLACE(LOWER(CONVERT(mac USING utf8mb4)),':','') = :mac_clean
  ");

  $selLatest = $pdo->prepare("
    SELECT c.olt_id, c.port, c.onu, o.vendor
      FROM olt_mac_cache c
      LEFT JOIN olts o ON o.id = c.olt_id
     WHERE REPLACE(LOWER(CONVERT(c.mac USING utf8mb4)),':','') = :mac_clean
     ORDER BY c.learned_at DESC
     LIMIT 1
  ");

  $updClientOlt = $pdo->prepare("
    UPDATE clients
       SET olt_id = ?, olt_vendor = ?, olt_port = ?, olt_onu = ?, last_linked_at = NOW()
     WHERE id = ?
  ");

  $totalRouters = 0;
  $totalSessions = 0;
  $totalMatches = 0;
  $totalUpdates = 0;
  $macToClient = []; // mac_clean => [client_id]

  foreach ($routers as $r) {
    $totalRouters++;
    $rid = (string)$r['id'];
    println("Router #{$r['id']} {$r['name']} ({$r['ip']}): connecting...");

    $API = new RouterosAPI();
    $API->port = intval($r['api_port'] ?: 8728);
    $API->timeout = 5;

    if (!$API->connect($r['ip'], $r['username'], $r['password'])) {
      println("  ERROR: connect failed");
      continue;
    }

    $API->write('/ppp/active/print', false);
    $API->write('.proplist=name,caller-id');
    $resp = $API->read();
    $API->disconnect();

    if (!is_array($resp)) {
      println("  WARN: no active list.");
      continue;
    }

    $idx = $clientIdx[$rid] ?? [];
    foreach ($resp as $row) {
      $pp = trim((string)($row['name'] ?? ''));
      if ($pp === '') continue;
      $totalSessions++;

      $cid = trim((string)($row['caller-id'] ?? ''));
      $mac = norm_mac($cid);
      if ($mac === '') continue;

      $match = $idx[$pp] ?? null;
      if (!$match) continue;

      $totalMatches++;
      $clientId = (int)$match['id'];
      $updRouterMac->execute([':mac' => $mac, ':id' => $clientId]);
      if ($updRouterMac->rowCount() > 0) $totalUpdates++;

      $macClean = strtolower(str_replace(':', '', $mac));
      $macToClient[$macClean] = $clientId;
    }
  }

  foreach ($macToClient as $macClean => $clientId) {
    $updCacheClient->execute([':client_id' => $clientId, ':mac_clean' => $macClean]);
    $selLatest->execute([':mac_clean' => $macClean]);
    $row = $selLatest->fetch(PDO::FETCH_ASSOC);
    if (!$row) continue;
    $oltId = (int)($row['olt_id'] ?? 0);
    $portNorm = normalize_port_label($row['port'] ?? '');
    $onuNum = onu_numeric((string)($row['onu'] ?? ''));
    if ($oltId > 0 && $portNorm !== '—' && $onuNum !== PHP_INT_MAX) {
      $vendor = trim((string)($row['vendor'] ?? ''));
      $updClientOlt->execute([$oltId, $vendor !== '' ? $vendor : null, $portNorm, $onuNum, $clientId]);
    }
  }

  println("----");
  println("Routers processed : {$totalRouters}");
  println("Live sessions read: {$totalSessions}");
  println("Matched clients   : {$totalMatches}");
  println("Router MAC updates: {$totalUpdates}");
  println("OLT link updates  : ".count($macToClient));
} finally {
  $pdo->query("SELECT RELEASE_LOCK('cron_pppoe_olt_link')");
}
