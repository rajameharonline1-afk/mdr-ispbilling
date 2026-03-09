<?php
require_once __DIR__ . '/db.php';
session_start();

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// ডাটাবেজ থেকে ইউজার তথ্য আনা (MD5 পাসওয়ার্ড মিলানো)
$sql = "SELECT u.id, u.username, u.role_id, u.password,
               COALESCE(LOWER(r.name), LOWER(u.role), 'viewer') AS role_name
          FROM users u
          LEFT JOIN roles r ON u.role_id = r.id
         WHERE u.username = ?
         LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify password: prefer password_hash; fallback to legacy MD5
$ok = false;
if ($user && isset($user['password'])) {
    $stored = (string)$user['password'];
    if (strlen($stored) > 0 && strlen($stored) < 60) {
        // legacy MD5 hex (32 chars); re-hash on login success
        if (md5($password) === $stored) {
            $ok = true;
            // upgrade hash silently
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $u = db()->prepare("UPDATE users SET password=? WHERE id=?");
            $u->execute([$newHash, (int)$user['id']]);
            $user['password'] = $newHash;
        }
    }
    if (!$ok && password_verify($password, $stored)) {
        $ok = true;
    }
}

if ($ok && $user) {
    $roleName = strtolower(trim((string)($user['role_name'] ?? 'viewer')));
    if ($roleName === '') $roleName = 'viewer';
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $roleName;
    $_SESSION['role_id'] = $user['role_id'] ?? null;
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $roleName,
        'role_id' => $user['role_id'] ?? null,
    ];
    $_SESSION['acl_role_name'] = $roleName;
    $_SESSION['last_activity'] = time();

    header("Location: /public/index.php");
    exit;
} else {
    header("Location: /public/login.php?error=Invalid username or password");
    exit;
}

