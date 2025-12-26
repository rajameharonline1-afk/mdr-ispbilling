<?php
// /public/portal/bkash.php
// Redirect wrapper for backwards-compatible links.

declare(strict_types=1);

$qs = $_SERVER['QUERY_STRING'] ?? '';
$to = '/public/portal/payments.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $to, true, 302);
exit;

