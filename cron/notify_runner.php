<?php
// /cron/notify_runner.php
// বাংলা: কিউড নোটিফিকেশন পাঠানো (batch runner)
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','0'); ini_set('log_errors','1');

$ROOT = dirname(__DIR__, 1);
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/notify.php';
@include_once $ROOT . '/app/audit.php';

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// client filter (CLI --client_id=123 or GET)
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if (PHP_SAPI === 'cli') {
  foreach (($argv ?? []) as $a) {
    if (preg_match('/^--client_id=(\d+)/', $a, $m)) { $client_id = (int)$m[1]; break; }
  }
}

$res = notify_run_batch($pdo, ['client_id'=>$client_id]);
echo "Processed={$res['processed']} Sent={$res['sent']} Failed={$res['failed']}".
     ($client_id>0 ? " client_id={$client_id}" : "")."\n";
