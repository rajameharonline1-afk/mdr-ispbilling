<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/olt_schema.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $msg): void {
    echo json_encode(['ok'=>$ok, $ok ? 'description' : 'error' => $msg]);
    exit;
}

// Read JSON body
$payload = json_decode(file_get_contents('php://input') ?: 'null', true);
$olt_id = (int)($payload['olt_id'] ?? 0);
if ($olt_id <= 0) respond(false, 'Invalid OLT ID.');

$pdo = db();

// Detect available columns (schema compatibility)
$columns = $pdo->query("SHOW COLUMNS FROM olts")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$colSet = array_flip($columns);
$select = ['id','host'];
if (isset($colSet['ssh_port'])) $select[] = 'ssh_port';
if (isset($colSet['name'])) $select[] = 'name';
if (isset($colSet['vendor'])) $select[] = 'vendor';
$sql = "SELECT " . implode(',', $select) . " FROM olts WHERE id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$olt_id]);
$olt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$olt) respond(false, 'OLT not found.');

$host = trim((string)$olt['host']);
$port = (int)($olt['ssh_port'] ?? 22);
if ($host === '') respond(false, 'Host is empty.');
if ($port <= 0) $port = 22;

// Probe connectivity (primary port then telnet fallback)
$probe = function(string $targetHost, int $targetPort) {
    $errno = $errstr = null;
    $fp = @stream_socket_client("tcp://{$targetHost}:{$targetPort}", $errno, $errstr, 3, STREAM_CLIENT_CONNECT);
    if (!$fp) return ['ok'=>false,'err'=>"Connection failed: {$errstr} ({$errno})"];
    stream_set_timeout($fp, 2);
    $banner = '';
    $banner .= @fgets($fp, 512);
    @fwrite($fp, "\n");
    $banner .= @fgets($fp, 512);
    fclose($fp);
    return ['ok'=>true,'data'=>trim((string)$banner)];
};

$res = $probe($host, $port);
if (!$res['ok'] && $port !== 23) {
    $fallback = $probe($host, 23);
    if ($fallback['ok']) { $res = $fallback; }
    else { $res['error_chain'] = [$res['err'], $fallback['err']]; }
}

if (!$res['ok']) {
    $extra = isset($res['error_chain']) ? " | Tried 23: {$res['error_chain'][1]}" : '';
    respond(false, $res['err'] . $extra);
}

$desc = 'Connected successfully.';
if (!empty($res['data'])) $desc .= ' Ok: ' . (strlen($res['data'])>120 ? substr($res['data'],0,120).'…' : $res['data']);
respond(true, $desc);

