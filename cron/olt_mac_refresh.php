<?php
// /cron/olt_mac_refresh.php
// Cron wrapper to run api/olt_mac_refresh_telnet.php in CLI (fast mode by default)

declare(strict_types=1);

chdir(__DIR__ . '/..');
$php = PHP_BINARY ?: 'php';
$script = __DIR__ . '/../api/olt_mac_refresh_telnet.php';

$mode = 'fast';
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--full' || $arg === '-F') { $mode = 'full'; continue; }
    if ($arg === '--rx' || $arg === '--diag') { $mode = 'rx'; continue; }
    if ($arg === '--fast' || $arg === '-f') { $mode = 'fast'; continue; }
}

$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' mode=' . escapeshellarg($mode);
passthru($cmd);
