<?php
// /api/client_expiry_update.php
// Update a client's expiry_date (calendar extend from client view)

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf_compat.php';

header('Content-Type: application/json');

function jexit(array $payload): void {
  echo json_encode($payload);
  exit;
}

// Accept form or JSON body
$input = $_POST;
if (empty($input)) {
  $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$client_id   = (int)($input['client_id'] ?? 0);
$expiry_date = trim((string)($input['expiry_date'] ?? ''));
$token       = trim((string)($input['csrf_token'] ?? ''));

$csrf_ok = false;
if (function_exists('csrf_validate')) {
  $csrf_ok = csrf_validate($token);
} elseif (function_exists('csrf_verify')) {
  // fallback to request-token verifier if available
  $csrf_ok = csrf_verify();
} else {
  $stored = csrf_session_token();
  $csrf_ok = ($token !== '' && $stored && hash_equals((string)$stored, (string)$token));
}
if (!$csrf_ok) {
  jexit(['status' => 'error', 'message' => 'Invalid CSRF token']);
}
if ($client_id <= 0) {
  jexit(['status' => 'error', 'message' => 'Invalid client']);
}
if ($expiry_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date) || !strtotime($expiry_date)) {
  jexit(['status' => 'error', 'message' => 'Invalid expiry date']);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$col_exists = function(string $tbl, string $col) use ($pdo): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $st->execute([$tbl, $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
};

// Ensure column exists
if (!$col_exists('clients', 'expiry_date')) {
  jexit(['status' => 'error', 'message' => 'expiry_date column not found']);
}

// Fetch old expiry for info (optional)
$old = null;
$stOld = $pdo->prepare("SELECT expiry_date FROM clients WHERE id=? LIMIT 1");
$stOld->execute([$client_id]);
$old = $stOld->fetchColumn();

$sql = "UPDATE clients SET expiry_date=?".($col_exists('clients','updated_at') ? ", updated_at=NOW()" : "")." WHERE id=?";
$st = $pdo->prepare($sql);
$ok = $st->execute([$expiry_date, $client_id]);
if (!$ok) {
  jexit(['status' => 'error', 'message' => 'Update failed']);
}

jexit([
  'status' => 'success',
  'message'=> 'Expiry updated',
  'new_expiry' => $expiry_date,
  'old_expiry' => $old,
]);
