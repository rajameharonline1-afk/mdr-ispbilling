<?php
/**
 * /api/client_live_status.php
 * Live PPPoE status + instant TX/RX (auto unit: Kbps/Mbps) + total usage (GB)
 * Requires: app/require_login.php, app/db.php, app/routeros_api.class.php
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/config.php';
require_once $ROOT . '/app/db.php';

// -------- Cron token bypass (CLI/cron without session) ----------
$cronTokenConf = null;
if (getenv('CRON_TOKEN'))                       $cronTokenConf = getenv('CRON_TOKEN');
elseif (defined('CRON_TOKEN'))                  $cronTokenConf = (string)CRON_TOKEN;
elseif (is_readable($ROOT.'/storage/cron_token.txt'))
  $cronTokenConf = trim((string)@file_get_contents($ROOT.'/storage/cron_token.txt'));

$cronOk = false;
if ($cronTokenConf) {
  $provided = $_GET['cron_token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? null);
  if ($provided && hash_equals((string)$cronTokenConf, (string)$provided)) {
    $cronOk = true;
  }
}
if (!$cronOk && php_sapi_name() === 'cli') {
  // CLI fallback: allow if env matches
  $cliTok = getenv('CRON_TOKEN');
  if ($cliTok && hash_equals((string)$cronTokenConf, (string)$cliTok)) $cronOk = true;
}

if (!$cronOk) {
  require_once $ROOT . '/app/require_login.php';
}

require_once $ROOT . '/app/routeros_api.class.php';

function jexit(array $a): void {
	echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; 
	}


function normalize_mac_from_string($s){
    // ইনপুট থেকে হেক্সগুলো নিয়ে 12 ক্যারেক্টার করলে নরমালাইজ হবে
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string)$s));
    if (strlen($hex) < 12) return null;
    $hex = substr($hex, 0, 12);
    return implode(':', str_split($hex, 2));   // XX:XX:XX:XX:XX:XX
}
function mac_prefix6($mac){  // AABBCC
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string)$mac));
    return (strlen($hex)>=6) ? substr($hex, 0, 6) : null;
}
function vendor_lookup_cached($prefix6){
    $stmt = db()->prepare("SELECT vendor FROM mac_vendors WHERE mac_prefix=? LIMIT 1");
    $stmt->execute([$prefix6]);
    $v = $stmt->fetchColumn();
    return $v ?: null;
}
function vendor_cache_save($prefix6, $vendor){
    $stmt = db()->prepare("INSERT INTO mac_vendors(mac_prefix, vendor, updated_at)
                           VALUES(?, ?, NOW())
                           ON DUPLICATE KEY UPDATE vendor=VALUES(vendor), updated_at=NOW()");
    $stmt->execute([$prefix6, $vendor]);
}
function vendor_lookup_online($mac){
    // api.macvendors.com খুব লাইটওয়েট; rate-limit খেয়াল রাখুন
    $url = 'https://api.macvendors.com/' . rawurlencode($mac);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'ISP-Billing/1.0'
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $res !== false) {
        // API প্লেইন টেক্সট রিটার্ন করে
        return trim($res);
    }
    return null;
}

function normalize_mac_key(?string $mac): ?string {
    if(!$mac) return null;
    $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', $mac));
    if(strlen($hex) < 12) return null;
    return substr($hex, 0, 12);
}

function normalize_mac_display(?string $mac): ?string {
    $key = normalize_mac_key($mac);
    if(!$key) return null;
    return strtoupper(implode(':', str_split($key, 2)));
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

function fetch_olt_meta(PDO $pdo, int $oltId): array {
    if($oltId <= 0) return ['name'=>null,'host'=>null,'vendor'=>null];
    try{
        $st = $pdo->prepare("SELECT name, host, vendor FROM olts WHERE id=? LIMIT 1");
        $st->execute([$oltId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if($row){
            return [
                'name' => $row['name'] ?? null,
                'host' => $row['host'] ?? null,
                'vendor' => $row['vendor'] ?? null,
            ];
        }
    }catch(Throwable $e){}
    return ['name'=>null,'host'=>null,'vendor'=>null];
}

function find_olt_binding(PDO $pdo, array $macCandidates, ?int $preferredOltId = null): ?array {
    $normalized = [];
    foreach($macCandidates as $mac){
        $key = normalize_mac_key($mac);
        if($key) $normalized[$key] = true;
    }
    if(!$normalized) return null;
    $macList = array_keys($normalized);
    $expr = "LOWER(REPLACE(REPLACE(REPLACE(%s,':',''),'-',''),'.',''))";

    $buildWhere = function(string $field) use ($expr, $macList): array {
        $clauses = [];
        $params = [];
        foreach($macList as $key){
            $clauses[] = sprintf($expr, $field) . ' = ?';
            $params[] = $key;
        }
        return ['sql' => '(' . implode(' OR ', $clauses) . ')', 'params' => $params];
    };

    $runOne = function(string $sql, array $params) use ($pdo){
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    $binding = null;
    $w = $buildWhere('mac');
    $sqlMacs = "SELECT olt_id, family, slot, onu, vlan, mac, learned_at FROM olt_onu_client_macs WHERE {$w['sql']}";
    if($preferredOltId){
        $row = $runOne($sqlMacs . " AND olt_id = ? ORDER BY learned_at DESC LIMIT 1", array_merge($w['params'], [$preferredOltId]));
        if($row) $binding = $row + ['source'=>'olt_onu_client_macs'];
    }
    if(!$binding){
        $row = $runOne($sqlMacs . " ORDER BY learned_at DESC LIMIT 1", $w['params']);
        if($row) $binding = $row + ['source'=>'olt_onu_client_macs'];
    }
    if(!$binding){
        $wc = $buildWhere('mac');
        $sqlCache = "SELECT olt_id, port, onu, mac, learned_at, rx_power_dbm FROM olt_mac_cache WHERE {$wc['sql']}";
        if($preferredOltId){
            $row = $runOne($sqlCache . " AND olt_id = ? ORDER BY learned_at DESC LIMIT 1", array_merge($wc['params'], [$preferredOltId]));
            if($row) $binding = $row + ['source'=>'olt_mac_cache'];
        }
        if(!$binding){
            $row = $runOne($sqlCache . " ORDER BY learned_at DESC LIMIT 1", $wc['params']);
            if($row) $binding = $row + ['source'=>'olt_mac_cache'];
        }
    }
    if(!$binding){
        $wi = $buildWhere('omm.mac');
        $sqlInv = "SELECT oi.olt_id, oi.iface, oi.onu_id, omm.mac, oi.last_updated
                   FROM onu_mac_map omm
                   JOIN onu_inventory oi ON oi.id = omm.target_id
                   WHERE {$wi['sql']}
                   ORDER BY omm.last_seen DESC
                   LIMIT 1";
        $row = $runOne($sqlInv, $wi['params']);
        if($row){
          $binding = [
            'olt_id' => $row['olt_id'] ?? null,
            'port'   => $row['iface'] ?? null,
            'onu'    => $row['onu_id'] ?? null,
            'mac'    => $row['mac'] ?? null,
            'learned_at' => $row['last_updated'] ?? null,
            'source'=> 'onu_mac_map',
          ];
        }
    }
    if(!$binding){
        $wl = $buildWhere('mac');
        $row = $runOne("SELECT olt_id, port, onu, mac, learned_at, rx_power_dbm FROM olt_mac_cache WHERE {$wl['sql']} ORDER BY learned_at DESC LIMIT 1", $wl['params']);
        if($row) $binding = $row + ['source'=>'olt_mac_cache'];
    }
    if(!$binding) return null;

    $macDisplay = normalize_mac_display($binding['mac'] ?? null) ?? ($binding['mac'] ?? null);
    $portLabel  = null;
    if(!empty($binding['port'])){
        $portLabel = normalize_port_label($binding['port']);
    } elseif(!empty($binding['family']) && !empty($binding['slot'])){
        $portLabel = normalize_port_label(sprintf('%s 0/%02d', $binding['family'], (int)$binding['slot']));
    }
    $meta = fetch_olt_meta($pdo, (int)($binding['olt_id'] ?? 0));
    return [
        'olt_id'  => (int)($binding['olt_id'] ?? 0) ?: null,
        'port'    => $portLabel ?? ($binding['port'] ?? null),
        'onu'     => isset($binding['onu']) ? (int)$binding['onu'] : null,
        'mac'     => $macDisplay,
        'vlan'    => $binding['vlan'] ?? null,
        'learned_at' => $binding['learned_at'] ?? null,
        'source'  => $binding['source'] ?? null,
        'rx_power_dbm' => $binding['rx_power_dbm'] ?? null,
        'name'    => $meta['name'],
        'host'    => $meta['host'],
        'vendor'  => $meta['vendor'],
        'port_label' => $portLabel,
    ];
}

function lookup_rx_power(PDO $pdo, ?int $oltId, array $macCandidates): array {
    $normalized = [];
    foreach($macCandidates as $mac){
        $key = normalize_mac_key($mac);
        if($key) $normalized[$key] = true;
    }
    if(!$normalized){
        return ['value'=>null,'updated'=>null,'port'=>null,'onu'=>null,'mac'=>null];
    }
    $expr = "LOWER(REPLACE(REPLACE(REPLACE(mac,':',''),'-',''),'.',''))";
    $clauses = [];
    $params = [];
    foreach(array_keys($normalized) as $key){
        $clauses[] = "$expr = ?";
        $params[] = $key;
    }
    $sql = "SELECT mac, port, onu, rx_power_dbm, learned_at
            FROM olt_mac_cache
            WHERE (" . implode(' OR ', $clauses) . ")";
    if($oltId){
        $sql .= " AND olt_id = ?";
        $params[] = $oltId;
    }
    $sql .= " ORDER BY learned_at DESC LIMIT 1";
    try{
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if($row){
            return [
                'value' => $row['rx_power_dbm'] !== null ? (float)$row['rx_power_dbm'] : null,
                'updated'=> $row['learned_at'] ?? null,
                'port'   => $row['port'] ?? null,
                'onu'    => $row['onu'] ?? null,
                'mac'    => $row['mac'] ?? null,
            ];
        }
    } catch(Throwable $e){
        // Table may not exist; ignore.
    }
    return ['value'=>null,'updated'=>null,'port'=>null,'onu'=>null,'mac'=>null];
}

/** Get client id/pppoe from GET/POST/JSON */
$id = 0; $pppoe = null;
if (isset($_GET['id'])) $id = (int)$_GET['id'];
elseif (isset($_POST['id'])) $id = (int)$_POST['id'];

if (isset($_GET['pppoe_id'])) $pppoe = trim((string)$_GET['pppoe_id']);
elseif (isset($_POST['pppoe_id'])) $pppoe = trim((string)$_POST['pppoe_id']);
else {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['id'])) $id = (int)$decoded['id'];
            if (isset($decoded['pppoe_id'])) $pppoe = trim((string)$decoded['pppoe_id']);
        }
    }
}
if ($id <= 0 && ($pppoe === null || $pppoe === '')) jexit(['status'=>'error','message'=>'Invalid client id','online'=>false]);

try {
    // 1) Load client + router info
    $pdo = db();
    $params = [];
    $where = '';
    if ($id > 0) { $where = 'WHERE c.id = ?'; $params[] = $id; }
    elseif ($pppoe !== null && $pppoe !== '') { $where = 'WHERE c.pppoe_id = ?'; $params[] = $pppoe; }

    $st = $pdo->prepare("
        SELECT c.id, c.pppoe_id, c.status, c.router_id, c.expiry_date,
               c.router_mac, c.ap_mac, c.olt_id, c.caller_mac,
               r.ip AS router_ip, r.username, r.password, r.api_port
        FROM clients c
        LEFT JOIN routers r ON r.id = c.router_id
        {$where}
        LIMIT 1
    ");
    $st->execute($params);
    $c = $st->fetch(PDO::FETCH_ASSOC);

    if (!$c || empty($c['router_ip'])) {
        jexit([
            'status'=>'ok','online'=>false,'ip'=>null,'uptime'=>null,'last_seen'=>null,
            'total_download_gb'=>null,'total_upload_gb'=>null,
            'rx_kbps'=>0,'tx_kbps'=>0,'rx_rate'=>'0 Kbps','tx_rate'=>'0 Kbps',
            'iface'=>null,'note'=>'router missing',
            'caller_id'=>null
        ]);
    }

    $pppName = trim((string)($c['pppoe_id'] ?? ''));
    if ($pppName === '') {
        jexit([
            'status'=>'ok','online'=>false,'ip'=>null,'uptime'=>null,'last_seen'=>null,
            'total_download_gb'=>null,'total_upload_gb'=>null,
            'rx_kbps'=>0,'tx_kbps'=>0,'rx_rate'=>'0 Kbps','tx_rate'=>'0 Kbps',
            'iface'=>null,'note'=>'empty ppp name',
            'caller_id'=>null
        ]);
    }

    // 2) Connect to RouterOS
    $API = new RouterosAPI();
    $API->debug = false;
    $api_port = (int)($c['api_port'] ?? 8728);
    if (!$API->connect($c['router_ip'], $c['username'], $c['password'], $api_port)) {
        jexit([
            'status'=>'ok','online'=>false,'ip'=>null,'uptime'=>null,'last_seen'=>null,
            'total_download_gb'=>null,'total_upload_gb'=>null,
            'rx_kbps'=>0,'tx_kbps'=>0,'rx_rate'=>'0 Kbps','tx_rate'=>'0 Kbps',
            'iface'=>null,'note'=>'api connect failed',
            'caller_id'=>null
        ]);
    }

    $note = [];

    // 3) Active PPP -> online/ip/uptime (+ caller_id)
    $active    = $API->comm('/ppp/active/print', ["?name" => $pppName]);
    $isOnline  = !empty($active);
    $ip        = $isOnline ? ($active[0]['address'] ?? null) : null;
    $uptime    = $isOnline ? ($active[0]['uptime']  ?? null) : null;
    $caller_id = $isOnline ? ($active[0]['caller-id'] ?? null) : null;

    // 4) Resolve dynamic interface (multi-fallback)
    $ifaceName = null;
    if ($isOnline) {
        // (a) direct guess: pppoe-<username>
        $guess = 'pppoe-' . $pppName;
        $row = $API->comm('/interface/print', ['?name' => $guess]);
        if (!empty($row[0]['name'])) { $ifaceName = $row[0]['name']; $note[]='iface:direct'; }
    }
    if ($isOnline && !$ifaceName) {
        // (b) scan all pppoe-in, running + contains username
        $all = $API->comm('/interface/print', ['?type' => 'pppoe-in']);
        if (!empty($all) && is_array($all)) {
            foreach ($all as $r) {
                $n = $r['name'] ?? '';
                $running = ($r['running'] ?? 'false') === 'true';
                if ($running && $n !== '' && stripos($n, $pppName) !== false) { $ifaceName = $n; $note[]='iface:pppoe-in-like'; break; }
            }
        }
    }
    if ($isOnline && !$ifaceName && $ip) {
        // (c) firewall connection mapping by client IP
        $conn = $API->comm('/ip/firewall/connection/print', ['?src-address' => $ip]);
        if (empty($conn)) { $conn = $API->comm('/ip/firewall/connection/print', ['?dst-address' => $ip]); }
        if (!empty($conn[0])) {
            $ii = $conn[0]['in-interface']  ?? null;
            $oi = $conn[0]['out-interface'] ?? null;
            $ifaceName = $ii ?: $oi;
            if ($ifaceName) $note[]='iface:fw-conn-map';
        }
    }

    // 5) Read live rates + totals (with auto-unit formatting)
    $rx_kbps = 0.0; $tx_kbps = 0.0;
    $rx_rate = '0 Kbps'; $tx_rate = '0 Kbps';
    $total_dl_gb = null; $total_ul_gb = null;

    if ($isOnline && $ifaceName) {
        // Correct param: 'interface'
        $mon = $API->comm('/interface/monitor-traffic', [
            'interface' => $ifaceName,
            'once'      => ''
        ]);

        if (!empty($mon[0])) {
            // Strip non-digits; RouterOS may return values with commas
            $rx_bps = (int)preg_replace('/\D+/', '', (string)($mon[0]['rx-bits-per-second'] ?? '0'));
            $tx_bps = (int)preg_replace('/\D+/', '', (string)($mon[0]['tx-bits-per-second'] ?? '0'));

            // Base Kbps
            $rx_kbps = $rx_bps / 1000;
            $tx_kbps = $tx_bps / 1000;

            // Auto unit => Kbps (<1000) / Mbps (>=1000)
            $rx_rate = ($rx_kbps >= 1000)
                ? (round($rx_kbps/1000, 2) . ' Mbps')
                : (round($rx_kbps, 1) . ' Kbps');

            $tx_rate = ($tx_kbps >= 1000)
                ? (round($tx_kbps/1000, 2) . ' Mbps')
                : (round($tx_kbps, 1) . ' Kbps');
        } else {
            $note[]='monitor-empty';
        }

        // Totals: bytes → GB
        $ifaceStats = $API->comm('/interface/print', ['?name' => $ifaceName]);
        if (!empty($ifaceStats[0])) {
            $rx_byte = (float)($ifaceStats[0]['rx-byte'] ?? 0);
            $tx_byte = (float)($ifaceStats[0]['tx-byte'] ?? 0);
            $div = 1024*1024*1024; // GiB
            $total_dl_gb = round($rx_byte / $div, 3); // Download = RX from NAS to client
            $total_ul_gb = round($tx_byte / $div, 3); // Upload   = TX from client to NAS
        } else {
            $note[]='iface-stats-empty';
        }
    }

 
    $lastSeen = null;
 
        $secret = $API->comm('/ppp/secret/print', ["?name" => $pppName]);
    if (!empty($secret[0]['last-logged-out'])) $lastSeen = $secret[0]['last-logged-out'];

    $macList = [$caller_id ?? null, $c['caller_mac'] ?? null, $c['router_mac'] ?? null, $c['ap_mac'] ?? null];
    $arpMac = null;
    if ($isOnline && $ip) {
        $arp = $API->comm('/ip/arp/print', ['?address' => $ip]);
        if (!empty($arp[0]['mac-address'])) {
            $arpMac = $arp[0]['mac-address'];
            $macList[] = $arpMac;
        }
    }
    $rxInfo = lookup_rx_power(
        $pdo,
        isset($c['olt_id']) ? (int)$c['olt_id'] : null,
        $macList
    );
    $oltBinding = find_olt_binding(
        $pdo,
        $macList,
        isset($c['olt_id']) ? (int)$c['olt_id'] : null
    );


    $API->disconnect();

    jexit([
        'status' => 'ok',
        'online' => $isOnline,
        'ip'     => $ip,
        'uptime' => $uptime,
        'last_seen' => $lastSeen,
        'iface'  => $ifaceName,
        'total_download_gb' => $total_dl_gb,
        'total_upload_gb'   => $total_ul_gb,
        // Raw ints (rounded) for any charts
        'rx_kbps' => (int)round($rx_kbps),
        'tx_kbps' => (int)round($tx_kbps),
        // Human friendly strings with auto unit
        'rx_rate' => $rx_rate,
        'tx_rate' => $tx_rate,
        'rx_power_dbm' => $rxInfo['value'],
        'rx_power_updated_at' => $rxInfo['updated'],
        'caller_id' => $caller_id,   // <-- NEW: caller-id যোগ করা হলো
        'arp_mac' => $arpMac,
        'olt_binding' => $oltBinding,
        'note' => implode(',', $note),
    ]);

} catch (Throwable $e) {
    jexit([
        'status'=>'error','message'=>$e->getMessage(),
        'online'=>false,'ip'=>null,'uptime'=>null,'last_seen'=>null,
        'total_download_gb'=>null,'total_upload_gb'=>null,
        'rx_kbps'=>0,'tx_kbps'=>0,'rx_rate'=>'0 Kbps','tx_rate'=>'0 Kbps',
        'iface'=>null,
        'caller_id'=>null
    ]);
}
