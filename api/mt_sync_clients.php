<?php
// /api/mt_sync_clients.php
// Purpose: Sync PPPoE users from MikroTik into `clients` with enriched comment parsing + audit logging.
// বাংলা নির্দেশক কমেন্ট দেওয়া হয়েছে টোকেন যাচাই, রেজেক্স পার্সিং, ও অডিট লগ লিংক বোঝাতে।

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ---------- Token check ----------
$token = $_GET['token'] ?? $_POST['token'] ?? '';
require_once __DIR__ . '/../app/config.php';
if (!$token || !defined('CRON_TOKEN') || $token !== CRON_TOKEN) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

// ---------- Helpers ----------
function parse_comment(string $comment): array {
    $out = [
        'client_code' => null,
        'name'        => null,
        'mobile'      => null,
        'address'     => null,
        'nid_no'      => null,
        'join_date'   => null,
        'bill_amount' => null,
    ];
    $pattern = '/Client\s*Code:\s*(.*?)\s*\|\s*Client\s*Name:\s*(.*?)\s*\|\s*Contact\s*Number:\s*(.*?)\s*\|\s*Zone\s*Name:\s*(.*?)\s*\|\s*Present\s*Address:\s*(.*?)\s*\|\s*NID\s*No:\s*(.*?)\s*\|\s*Joining\s*Date:\s*(.*?)\s*\|\s*Monthly\s*Bill:\s*(.*)/i';
    if (preg_match($pattern, $comment, $m)) {
        $out['client_code'] = trim($m[1] ?? '');
        $out['name']        = trim($m[2] ?? '');
        $out['mobile']      = trim($m[3] ?? '');
        $out['address']     = trim($m[5] ?? '');
        $out['nid_no']      = trim($m[6] ?? '');
        $out['join_date']   = trim($m[7] ?? '');
        $out['bill_amount'] = trim($m[8] ?? '');
    }
    return $out;
}

function normalize_mobile(?string $raw): ?string {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') return null;
    if (strlen($digits) > 13) $digits = substr($digits, -13);
    return $digits;
}

function normalize_date(?string $raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    $ts = strtotime($raw);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

function normalize_amount(?string $raw): ?float {
    if ($raw === null) return null;
    $num = preg_replace('/[^\d\.]+/', '', $raw);
    if ($num === '') return null;
    return (float)$num;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch routers
$routers = $pdo->query("SELECT id, ip, username, password, api_port FROM routers WHERE 1")->fetchAll(PDO::FETCH_ASSOC);
if (!$routers) {
    echo json_encode(['ok' => false, 'error' => 'No routers found']);
    exit;
}

$ins = $pdo->prepare("
    INSERT INTO clients (pppoe_id, router_id, client_code, name, mobile, address, nid, monthly_bill, join_date, pppoe_pass, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      router_id=VALUES(router_id),
      client_code=VALUES(client_code),
      name=VALUES(name),
      mobile=VALUES(mobile),
      address=VALUES(address),
      nid=VALUES(nid),
      monthly_bill=VALUES(monthly_bill),
      join_date=VALUES(join_date),
      pppoe_pass=VALUES(pppoe_pass),
      updated_at=VALUES(updated_at),
      id=LAST_INSERT_ID(id)
");

$audit = $pdo->prepare("
    INSERT INTO audit_logs (entity, entity_id, action, user_id, created_at)
    VALUES ('clients', ?, 'MikroTik Sync', 0, NOW())
");

$summary = ['ok' => true, 'routers' => [], 'inserted' => 0, 'updated' => 0, 'errors' => []];

foreach ($routers as $router) {
    $rid = (int)$router['id'];
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($router['ip'], $router['username'], $router['password'], (int)$router['api_port'])) {
        $summary['errors'][] = "Router #{$rid} connect failed";
        continue;
    }

    // RouterOS v7 compatibility with password visibility
    $API->write('/ppp/secret/print', false);
    $API->write('=show-sensitive=');
    $API->write('=.proplist=name,password,comment,disabled');
    $secrets = $API->read();
    
    $API->disconnect();

    if (!is_array($secrets) || count($secrets) === 0) {
        $summary['routers'][] = ['router_id' => $rid, 'fetched' => 0, 'synced' => 0];
        continue;
    }

    $synced = 0;
    foreach ($secrets as $row) {
        $pppoe = trim((string)($row['name'] ?? ''));
        if ($pppoe === '') continue;

        $parsed = parse_comment((string)($row['comment'] ?? ''));
        $clientCode = $parsed['client_code'] ?: $pppoe;
        $name   = $parsed['name'] ?: $pppoe;
        $mobile = normalize_mobile($parsed['mobile']);
        $addr   = $parsed['address'] ?: null;
        $nid    = $parsed['nid_no'] ?: null;
        $join   = normalize_date($parsed['join_date']) ?: date('Y-m-d'); // (বাংলা) join_date না থাকলে আজকের তারিখ বসাই যেন NOT NULL ভাঙে না
        $bill   = normalize_amount($parsed['bill_amount']);
        $pwd    = $row['password'] ?? null;

        try {
            $ins->execute([$pppoe, $rid, $clientCode, $name, $mobile, $addr, $nid, $bill, $join, $pwd]);
            $cid = (int)$pdo->lastInsertId();
            if ($cid > 0) {
                $audit->execute([$cid]);
            }
            $synced++;
        } catch (Throwable $e) {
            $summary['errors'][] = "Client {$pppoe} failed: " . $e->getMessage();
        }
    }
    $summary['routers'][] = ['router_id' => $rid, 'fetched' => count($secrets), 'synced' => $synced];
    $summary['updated'] += $synced;
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE);
