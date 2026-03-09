<?php
// /api/update_expiry.php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audit.php';

header('Content-Type: application/json');

function json_out($arr) { echo json_encode($arr); exit; }

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$input = $_POST;
if (empty($input)) {
  $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$client_id   = (int)($input['client_id'] ?? 0);
$expiry_date = trim((string)($input['expiry_date'] ?? ''));
$remarks     = trim((string)($input['remarks'] ?? ''));
$csrf_token  = trim((string)($input['csrf_token'] ?? ''));

if (empty($_SESSION['csrf_token']) || $csrf_token === '' || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
  json_out(['status' => 'error', 'message' => 'Invalid CSRF token']);
}
if ($client_id <= 0) {
  json_out(['status' => 'error', 'message' => 'Invalid client_id']);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) {
  json_out(['status' => 'error', 'message' => 'Invalid expiry_date (YYYY-MM-DD)']);
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // পুরনো এক্সপায়ারি ধরে রাখি যাতে লগে যায়
  $oldExpiry = null;
  try {
    $stOld = $pdo->prepare("SELECT expiry_date FROM clients WHERE id=? LIMIT 1");
    $stOld->execute([$client_id]);
    $oldExpiry = $stOld->fetchColumn() ?: null;
  } catch (Throwable $e) {
    $oldExpiry = null;
  }

  // বাংলা: মান একই হলে কোনো আপডেট/লগ করা হবে না—ডুপ্লিকেট লগ ঠেকাতে
  if ($oldExpiry && $oldExpiry === $expiry_date) {
    json_out([
      'status' => 'success',
      'new_expiry' => $expiry_date,
      'message' => 'Expiry unchanged; no log written',
      'remarks' => $remarks,
      'skipped' => true,
    ]);
  }

  // বাংলা: কলাম থাকলে updated_at আপডেট হবে, না থাকলে শুধু expiry_date সেট হবে
  $hasUpdatedAt = false;
  try {
    $stc = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'updated_at' LIMIT 1");
    $stc->execute();
    $hasUpdatedAt = (bool)$stc->fetchColumn();
  } catch (Throwable $e) {
    $hasUpdatedAt = false;
  }

  // বাংলা: লগ টেবিল (client_expiry_logs) না থাকলে বানাই, তারপর ইনসার্ট করি
  $createSql = "
    CREATE TABLE IF NOT EXISTS `client_expiry_logs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `client_id` BIGINT NOT NULL,
      `old_expiry` DATE NULL,
      `new_expiry` DATE NOT NULL,
      `remarks` TEXT NULL,
      `user_id` BIGINT NULL,
      `ip` VARCHAR(45) NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_client (`client_id`),
      INDEX idx_created (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  $pdo->exec($createSql);

  $pdo->beginTransaction();

  $updateSql = $hasUpdatedAt
    ? "UPDATE clients SET expiry_date = ?, updated_at = NOW() WHERE id = ?"
    : "UPDATE clients SET expiry_date = ? WHERE id = ?";
  $st = $pdo->prepare($updateSql);
  $ok = $st->execute([$expiry_date, $client_id]);
  if (!$ok) {
    $pdo->rollBack();
    json_out(['status' => 'error', 'message' => 'Update failed']);
  }

  $userId = null;
  foreach ([
    $_SESSION['user']['id'] ?? null,
    $_SESSION['user_id'] ?? null,
    $_SESSION['SESS_USER_ID'] ?? null,
  ] as $v) {
    if ((int)$v > 0) { $userId = (int)$v; break; }
  }
  $ip = '';
  foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $ip = explode(',', $_SERVER[$k])[0]; $ip = trim($ip); break; }
  }

  $insLog = $pdo->prepare("
    INSERT INTO client_expiry_logs (client_id, old_expiry, new_expiry, remarks, user_id, ip)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $insLog->execute([$client_id, $oldExpiry, $expiry_date, ($remarks !== '' ? $remarks : null), $userId, $ip ?: null]);

  try {
    audit_log('client', $client_id, 'expiry_update',
      ['expiry_date' => $oldExpiry],
      [
        'expiry_date' => $expiry_date,
        'remarks'     => $remarks !== '' ? $remarks : null,
      ]
    );
  } catch (Throwable $e) {
    // বাংলা: অডিট ব্যর্থ হলেও আপডেট সম্পন্ন হবে
  }

  $pdo->commit();
  json_out([
    'status' => 'success',
    'new_expiry' => $expiry_date,
    'message' => 'Expiry date updated',
    'remarks' => $remarks,
  ]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_out(['status' => 'error', 'message' => $e->getMessage()]);
}
