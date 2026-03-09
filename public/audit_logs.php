<?php
// Legacy compatibility shim for /public/audit_logs.php
// New implementation lives in React SPA + REST API (/new/reports/audit-logs, /node-api/audit-logs)

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo 'Method Not Allowed';
    exit;
}

$allowed = [
    'q', 'client_id', 'client_code', 'action', 'router', 'package', 'area',
    'df', 'dt', 'page', 'limit', 'sort', 'dir'
];

$params = [];
foreach ($allowed as $key) {
    if (!array_key_exists($key, $_GET)) {
        continue;
    }
    $value = trim((string)$_GET[$key]);
    if ($value === '') {
        continue;
    }
    $params[$key] = $value;
}

$export = strtolower(trim((string)($_GET['export'] ?? '')));
$target = '/new/reports/audit-logs';
if ($export === 'csv') {
    $target = '/node-api/audit-logs/export.csv';
} elseif ($export === 'xls') {
    $target = '/node-api/audit-logs/export.xls';
}

$query = http_build_query($params);
$location = $target . ($query !== '' ? ('?' . $query) : '');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . $location, true, 302);
exit;
