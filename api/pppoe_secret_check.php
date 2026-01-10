<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/roles.php';
require_role(['billing']); // admin auto allowed

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

function respond(array $arr): void { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$rid = (int)($_GET['router_id'] ?? 0);
$name = trim((string)($_GET['pppoe_id'] ?? ''));
if ($rid <= 0 || $name === '') respond(['status'=>'error','message'=>'router_id or pppoe_id missing']);

$st = db()->prepare("SELECT * FROM routers WHERE id=? LIMIT 1");
$st->execute([$rid]);
$router = $st->fetch(PDO::FETCH_ASSOC);
if (!$router) respond(['status'=>'error','message'=>'router not found']);

$ip   = $router['ip'] ?? ($router['ip_address'] ?? ($router['host'] ?? ($router['address'] ?? '')));
$user = $router['username'] ?? ($router['user'] ?? '');
$pass = $router['password'] ?? ($router['pass'] ?? '');
$port = isset($router['api_port']) && $router['api_port'] ? (int)$router['api_port'] : (int)($router['port'] ?? 8728);
if ($ip === '' || $user === '' || $pass === '') respond(['status'=>'error','message'=>'router credentials missing']);

$API = new RouterosAPI();
$API->debug = false;
if (!$API->connect($ip, $user, $pass, $port)) {
  respond(['status'=>'error','message'=>'router connect failed']);
}

$rows = $API->comm('/ppp/secret/print', ['?name'=>$name, '.proplist'=>'name']);
$API->disconnect();

$exists = is_array($rows) && isset($rows[0]['name']);
respond(['status'=>'success','exists'=>$exists]);
