<?php
// /api/mt_list_secrets.php
// Plain & robust: Load PPP secrets (optionally filtered by profile) from RouterOS v7
// Output: { ok, rows:[{pppoe_id, password|null, client_name, mobile, profile, package_* , status, is_existing, action}], has_pwd_col, debug? }

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/routeros_api.class.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function jexit(array $a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// (বাংলা) রাউটার রেকর্ড নিন
function get_router(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("SELECT * FROM routers WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

// (বাংলা) BD মোবাইল detect/normalize → 01XXXXXXXXX
function detect_mobile(?string $s): ?string {
  if(!$s) return null;
  $raw = preg_replace('/[^0-9+]/', '', $s);
  if(preg_match('/^\+?8801[3-9]\d{8}$/', $raw)) return preg_replace('/^\+?88/', '', $raw);
  if(preg_match('/^01[3-9]\d{8}$/', $raw)) return $raw;
  return null;
}

// (বাংলা) টেবিল/কলাম চেক
function col_exists(PDO $pdo, string $tbl, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
}

// (বাংলা) যেকোনোভাবে PPPoE ID (username) খোঁজা
function find_pppoe_id(array $row): ?string {
  foreach (['name','user','username','=.name','=.user','.name','.user'] as $k) {
    if(isset($row[$k]) && is_string($row[$k]) && $row[$k] !== '') return $row[$k];
  }
  foreach ($row as $k=>$v) {
    if(is_string($v) && $v!==''){
      $lk = strtolower((string)$k);
      if(strpos($lk,'name')!==false || strpos($lk,'user')!==false) return $v;
    } elseif (is_array($v)) {
      foreach (['name','user','username','value'] as $kk) {
        if(isset($v[$kk]) && is_string($v[$kk]) && $v[$kk] !== '') return $v[$kk];
      }
    }
  }
  return null;
}

// (বাংলা) row থেকে একটি স্ট্রিং ভ্যালু তোলা
function pick_str(array $row, array $keys): ?string {
  foreach($keys as $k){
    if(isset($row[$k]) && is_string($row[$k]) && $row[$k] !== '') return $row[$k];
  }
  return null;
}

// (বাংলা) Mikrotik comment থেকে নাম/মোবাইল বের করা
function parse_comment_fields(?string $comment): array {
  $out = ['client_name'=>null, 'mobile'=>null, 'zone'=>null];
  if(!$comment) return $out;
  $comment = trim((string)$comment);
  $lines = preg_split('/\R/', $comment);
  foreach($lines as $line){
    $line = trim((string)$line);
    if($line === '') continue;
    // Client Name variations: "Client Name:", "Client name :", "Name:", also key-value pairs separated by |
    if(preg_match('/client\s*name\s*:?\s*([^|\r\n]+)/i', $line, $m) || preg_match('/^name\s*:?\s*([^|\r\n]+)/i', $line, $m)){
      $out['client_name'] = trim($m[1]);
    }
    if(preg_match('/contact\s*number\s*:?\s*([^|\r\n]+)/i', $line, $m)){
      $mob = trim($m[1]);
      $out['mobile'] = detect_mobile($mob) ?: $mob;
    }
    if(preg_match('/zone\s*name\s*:?\s*([^|\r\n]+)/i', $line, $m)){
      $out['zone'] = trim($m[1]);
    }
  }
  // Fallback: পুরো কমেন্টে কোনো মোবাইল আছে কি না দেখো
  if(!$out['mobile']){
    if(preg_match('/(01[3-9][0-9]{8})/', $comment, $m)){
      $out['mobile'] = detect_mobile($m[1]) ?: $m[1];
    }
  }
  // Fallback: যদি নাম না পাওয়া যায়, কমেন্টের প্রথম লাইনের প্রথম অংশ নাও
  if(!$out['client_name'] && !empty($lines[0])){
    $out['client_name'] = trim($lines[0]);
  }
  return $out;
}

try{
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $router_id = intval($_GET['router_id'] ?? 0);
  $profile   = trim((string)($_GET['profile'] ?? '')); // optional

  if($router_id <= 0) jexit(['ok'=>false,'msg'=>'router_id is required']);

  $router = get_router($pdo, $router_id);
  if(!$router) jexit(['ok'=>false,'msg'=>'Router not found']);

  $ip   = $router['ip'];                      // address কলাম ব্যবহার করছি না
  $user = $router['username'] ?? '';
  $pass = $router['password'] ?? '';
  $port = intval($router['api_port'] ?? 8728);

  // (বাংলা) RouterOS API কানেক্ট
  $API = new RouterosAPI();
  $API->debug = false;
  if(!$API->connect($ip, $user, $pass, $port)){
    jexit(['ok'=>false,'msg'=>'RouterOS API connect failed']);
  }

  // (বাংলা) শুধু plain print; optional profile filter
  $args = [];
  if($profile !== '') $args['?profile'] = $profile;

  $attempts = [];
  $secrets = null;

  try{
    $secrets = $API->comm('/ppp/secret/print', $args);
    $attempts[] = ['type'=>'comm.plain', 'count'=>is_array($secrets)?count($secrets):0];
  }catch(Throwable $e){
    $attempts[] = ['type'=>'comm.plain.err', 'err'=>$e->getMessage()];
  }

  $API->disconnect();

  if(!$secrets || !is_array($secrets) || !count($secrets)){
    jexit(['ok'=>false,'msg'=>'No secrets returned from router.','debug'=>['attempts'=>$attempts]]);
  }

  // (বাংলা) প্যাকেজ ম্যাপ: name → id, price
  $pkgRows = $pdo->query("SELECT id, name, price FROM packages")->fetchAll(PDO::FETCH_ASSOC);
  $pkgMap = [];
  foreach($pkgRows as $p){ $pkgMap[$p['name']] = ['id'=>(int)$p['id'],'price'=>(float)$p['price']]; }

  // (বাংলা) এক্সিস্টিং ক্লায়েন্ট ম্যাপ
  $cliRows = $pdo->query("SELECT id, pppoe_id, name, sub_zone, box, area FROM clients")->fetchAll(PDO::FETCH_ASSOC);
  $cliMap = [];
  $normId = function(?string $s): string {
    return strtolower(trim((string)$s));
  };
  foreach($cliRows as $c){
    $key = $normId($c['pppoe_id'] ?? '');
    if ($key === '') continue;
    $cliMap[$key] = [
      'id'       => (int)$c['id'],
      'name'     => $c['name'] ?? '',
      'sub_zone' => $c['sub_zone'] ?? '',
      'box'      => $c['box'] ?? '',
      'area'     => $c['area'] ?? '',
    ];
  }

  $has_pwd_col = col_exists($pdo, 'clients', 'pppoe_password');

  $rows = [];
  $firstRaw = null;

  foreach($secrets as $s){
    if($firstRaw === null) $firstRaw = $s;

    // PPPoE ID (username) বের করি
    $pppoe_id = find_pppoe_id($s);
    if(!$pppoe_id) continue;

    // profile, disabled, password (password null থাকলে সমস্যা নেই)
    $prof     = pick_str($s, ['profile','=.profile','.profile']) ?? ($profile!=='' ? $profile : '');
    $disabled = strtolower((string)(pick_str($s, ['disabled','=.disabled','.disabled']) ?? 'no'));
    $status   = ($disabled === 'yes' || $disabled === 'true' || $disabled === 'on') ? 'inactive' : 'active';
    $password = pick_str($s, ['password','=.password','.password']); // plain কল—সাধারণত ফাঁকা থাকবে

    $comment = pick_str($s, ['comment','=.comment','.comment']) ?? '';
    $cmeta = parse_comment_fields($comment);
    $keyNorm  = $normId($pppoe_id);
    $existing = $cliMap[$keyNorm] ?? null;
    $client_name = ($existing['name'] ?? '') ?: $cmeta['client_name'] ?: $pppoe_id; // ডিফল্ট নাম = PPPoE ID
    // phone/caller-id ফিল্ড থেকেও মোবাইল ধরার চেষ্টা
    $rawPhone = pick_str($s, ['phone','=.phone','.phone','caller-id','=.caller-id','.caller-id']);
    $mobile = $cmeta['mobile'] ?: detect_mobile($rawPhone) ?: detect_mobile($pppoe_id);

    // Location info (sub_zone, box) if existing client
    $locParts = [];
    if ($existing) {
      if (!empty($existing['sub_zone'])) $locParts[] = $existing['sub_zone'];
      if (!empty($existing['box']))      $locParts[] = $existing['box'];
    }
    $location = $locParts ? implode(' • ', $locParts) : null;
    $zone = $cmeta['zone'] ?? ($existing['area'] ?? null);
    if (!$zone && $location) {
      $zone = strtok($location, '•');
      $zone = $zone ? trim($zone) : null;
    }

    $pkg = ($prof !== '' && isset($pkgMap[$prof])) ? $pkgMap[$prof] : null;

    $rows[] = [
      'pppoe_id'        => $pppoe_id,
      'password'        => $password ?: null,
      'client_name'     => $client_name,
      'mobile'          => $mobile,
      'profile'         => $prof,
      'package_matched' => (bool)$pkg,
      'package_id'      => $pkg['id'] ?? null,
      'package_name'    => $pkg ? $prof : null,
      'package_price'   => $pkg['price'] ?? null,
      'status'          => $status,
      'is_existing'     => isset($cliMap[$keyNorm]),
      'action'          => $pkg ? ($existing ? 'update' : 'new') : 'skip'
      ,
      'location'        => $location,
      'zone'            => $zone,
    ];
  }

  if(!count($rows)){
    jexit([
      'ok'=>false,
      'msg'=>'Secrets fetched but none had a usable name for this profile.',
      // (বাংলা) ডিবাগ তথ্য — Network → Response থেকে দেখুন
      'debug'=>[
        'attempts'=>$attempts,
        'sample'=>$firstRaw ?? '(empty)',
        'keys'=> is_array($firstRaw) ? array_keys($firstRaw) : []
      ]
    ]);
  }

  jexit([
    'ok'=>true,
    'rows'=>$rows,
    'has_pwd_col'=>$has_pwd_col,
    'debug'=>['attempts'=>$attempts, 'count'=>count($rows)]
  ]);

}catch(Throwable $e){
  jexit(['ok'=>false,'msg'=>$e->getMessage()]);
}
