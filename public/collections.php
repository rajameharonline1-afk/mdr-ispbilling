<?php
// Legacy route: redirect collections to payment report with date filters.
require_once __DIR__ . '/../app/require_login.php';

$params = $_GET;
$when = strtolower(trim((string)($params['when'] ?? '')));
unset($params['when']);

$today = date('Y-m-d');
$from = (string)($params['from'] ?? '');
$to   = (string)($params['to'] ?? '');

if ($from === '' || $to === '') {
  switch ($when) {
    case 'today':
      $from = $today; $to = $today;
      break;
    case 'yesterday':
      $d = date('Y-m-d', strtotime('-1 day'));
      $from = $d; $to = $d;
      break;
    case 'month':
    case 'this_month':
    case 'thismonth':
      $from = date('Y-m-01'); $to = $today;
      break;
    default:
      if ($from === '') { $from = date('Y-m-01'); }
      if ($to === '')   { $to = $today; }
      break;
  }
}

$params['from'] = $from;
$params['to'] = $to;

$qs = http_build_query($params);
header('Location: /public/payment_report.php'.($qs ? '?'.$qs : ''));
exit;
