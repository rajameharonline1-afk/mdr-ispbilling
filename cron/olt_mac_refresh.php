<?php
// /cron/olt_mac_refresh.php
// Cron wrapper to run api/olt_mac_refresh_telnet.php in CLI (fast mode by default)

declare(strict_types=1);

chdir(__DIR__ . '/..');
$php = PHP_BINARY ?: 'php';
$script = __DIR__ . '/../api/olt_mac_refresh_telnet.php';

$mode = 'fast';
if (in_array('--full', $argv ?? [], true)) {
    $mode = 'full';
}

$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' mode=' . escapeshellarg($mode);
passthru($cmd);
