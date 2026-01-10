<?php
// /olt/olt_edit.php
// (বাংলা) ডিক্রিপ্ট করা পাসওয়ার্ড এবং দেখা/লুকানোর সুবিধা সহ চূড়ান্ত সংস্করণ
session_start();
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/security_helpers.php'; // এনক্রিপশনের জন্য
require_once __DIR__ . '/olt_logger.php'; // OLT action log/audit

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$pdo = db();
$colList = $pdo->query("SHOW COLUMNS FROM olts")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$hasSnmp = in_array('snmp_community', $colList, true);

$stmt = $pdo->prepare("SELECT * FROM olts WHERE id = ?");
$stmt->execute([$id]);
$olt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$olt) {
    $_SESSION['toast_message'] = 'OLT not found.';
    $_SESSION['toast_type'] = 'danger';
    header('Location: index.php');
    exit;
}

// **Attempt to decrypt stored SSH password and enable password**
$decrypted_ssh_password = '';
$decrypted_enable_password = '';
$decrypt_failed = false;

if (!empty($olt['password'])) {
    $try = decrypt_password($olt['password'], ENCRYPTION_KEY);
    if ($try === false) {
        $decrypt_failed = true;
        $decrypted_ssh_password = ''; // leave blank in input, but show message below
    } else {
        $decrypted_ssh_password = $try;
    }
}
if (!empty($olt['enable_password'])) {
    $try2 = decrypt_password($olt['enable_password'], ENCRYPTION_KEY);
    if ($try2 === false) {
        // don't treat as fatal; only mark
        $decrypted_enable_password = '';
    } else {
        $decrypted_enable_password = $try2;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $snmp_community = trim($_POST['snmp_community'] ?? '');
    $ssh_port = (int)($_POST['ssh_port'] ?? 22);
    $username = trim($_POST['username'] ?? '');
    $password_plain = trim($_POST['password'] ?? '');
    $enable_password_plain = trim($_POST['enable_password'] ?? '');
    $prompt_regex = trim($_POST['prompt_regex'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || empty($vendor) || empty($host) || ($hasSnmp && $snmp_community === '')) {
        $errs = ["OLT Name", "Vendor", "Host IP"];
        if ($hasSnmp) { $errs[] = "SNMP Community"; }
        $errors[] = implode(', ', $errs) . " are required fields.";
    }

    if (empty($errors)) {
        try {
            // If user left password blank, we keep the existing encrypted password unchanged.
            if ($password_plain !== '') {
                $encrypted_password = encrypt_password($password_plain, ENCRYPTION_KEY);
            } else {
                $encrypted_password = $olt['password']; // keep old
            }

            if ($enable_password_plain !== '') {
                $encrypted_enable = encrypt_password($enable_password_plain, ENCRYPTION_KEY);
            } else {
                $encrypted_enable = $olt['enable_password'];
            }

            $params = [
                ':id' => $id,
                ':name' => $name,
                ':vendor' => $vendor,
                ':host' => $host,
                ':ssh_port' => $ssh_port,
                ':username' => $username,
                ':password' => $encrypted_password,
                ':enable_password' => $encrypted_enable,
                ':prompt_regex' => $prompt_regex,
                ':is_active' => $is_active
            ];

            $set = [
                "name=:name",
                "vendor=:vendor",
                "host=:host",
                "ssh_port=:ssh_port",
                "username=:username",
                "password=:password",
                "enable_password=:enable_password",
                "prompt_regex=:prompt_regex",
                "is_active=:is_active"
            ];
            if ($hasSnmp) {
                $params[':snmp_community'] = $snmp_community;
                $set[] = "snmp_community=:snmp_community";
            }

            $sql = "UPDATE olts SET " . implode(', ', $set) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            olt_log_action('update', [
                'id'        => $id,
                'name'      => $name,
                'vendor'    => $vendor,
                'host'      => $host,
                'active'    => $is_active,
                'prev_host' => $olt['host'] ?? null,
            ]);
            olt_audit_action('update', $id, [
                'name'      => $name,
                'vendor'    => $vendor,
                'host'      => $host,
                'active'    => $is_active,
                'prev_host' => $olt['host'] ?? null,
            ]);

            $_SESSION['toast_message'] = "OLT '<strong>" . h($name) . "</strong>' was updated successfully!";
            $_SESSION['toast_type'] = 'success';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $errors[] = "An OLT with this Host IP ('" . h($host) . "') already exists.";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// AJAX: Detect Prompt action (inline) - same behavior as add page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'detect_prompt') {
    header('Content-Type: application/json; charset=utf-8');
    $host = trim($_POST['host'] ?? '');
    $port = (int)($_POST['ssh_port'] ?? 22);
    if ($host === '' || $port <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Host and port are required for detection.']);
        exit;
    }
    // Helper: connect + probe banner
    $probe = function(string $targetHost, int $targetPort) {
        $timeout = 3; // seconds
        $context = stream_context_create([]);
        $errno = $errstr = null;
        $fp = @stream_socket_client("tcp://{$targetHost}:{$targetPort}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) return ['ok'=>false,'err'=>"Connection failed: {$errstr} ({$errno})"];
        stream_set_timeout($fp, 2);
        $data = '';
        $data .= @fgets($fp, 1024);
        @fwrite($fp, "\n");
        usleep(200000);
        $data .= @fgets($fp, 1024);
        fclose($fp);
        return ['ok'=>true,'data'=>trim((string)$data)];
    };

    $res = $probe($host, $port);
    if (!$res['ok'] && $port !== 23) {
        $fallback = $probe($host, 23);
        if ($fallback['ok']) { $res = $fallback; }
        else { $res['error_chain'] = [$res['err'], $fallback['err']]; }
    }

    if (!$res['ok']) {
        $extra = isset($res['error_chain']) ? " | Tried 23: {$res['error_chain'][1]}" : '';
        echo json_encode(['ok' => false, 'error' => $res['err'] . $extra]);
        exit;
    }
    $data = $res['data'];

    $data = trim($data);
    if ($data === '') {
        echo json_encode(['ok' => false, 'error' => 'No banner/prompt received (device may require authentication or not speak on this port).']);
        exit;
    }

    if (preg_match('/^SSH-/', $data)) {
        $detected = 'SSH banner: ' . strtok($data, "\n");
    } elseif (preg_match('/password[: ]*$/i', $data) || stripos($data, 'password') !== false) {
        $detected = 'Password prompt detected';
    } elseif (preg_match('/login[: ]*$/i', $data) || stripos($data, 'login') !== false) {
        $detected = 'Login prompt detected';
    } else {
        $detected = 'Banner: ' . (strlen($data) > 120 ? substr($data,0,120).'...' : $data);
    }

    echo json_encode(['ok' => true, 'detected' => $detected]);
    exit;
}

$page_title = "Edit OLT: " . h($olt['name']);
include __DIR__ . '/../partials/partials_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-3"><i class="bi bi-pencil-square"></i> Edit OLT: <?= h($olt['name']) ?></h4>
        <a href="index.php" class="btn btn-secondary">Back to OLT List</a>
    </div>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger"><strong>Error!</strong><br><?= implode('<br>', array_map('h', $errors)) ?></div>
    <?php endif; ?>

    <form id="oltEditForm" method="POST" class="card p-3 bg-light">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">OLT Name *</label><input type="text" name="name" class="form-control" value="<?= h($olt['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Vendor *</label>
                <select name="vendor" class="form-select" required>
                    <option value="vsol" <?= ($olt['vendor'] == 'vsol') ? 'selected' : '' ?>>V-SOL</option>
                    <option value="huawei" <?= ($olt['vendor'] == 'huawei') ? 'selected' : '' ?>>Huawei</option>
                    <option value="zte" <?= ($olt['vendor'] == 'zte') ? 'selected' : '' ?>>ZTE</option>
                    <option value="bdcom" <?= ($olt['vendor'] == 'bdcom') ? 'selected' : '' ?>>BDCOM</option>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Host IP / Hostname *</label><input type="text" name="host" id="host" class="form-control" value="<?= h($olt['host']) ?>" required></div>
            <div class="col-md-6">
                <label class="form-label">SNMP Community<?= $hasSnmp ? ' *' : '' ?></label>
                <input type="text" name="snmp_community" class="form-control" value="<?= h($olt['snmp_community'] ?? 'public') ?>" <?= $hasSnmp ? 'required' : 'disabled' ?> <?= $hasSnmp ? '' : 'placeholder="Column missing; not stored"' ?>>
            </div>

            <hr class="my-3 w-100">
            <h5 class="mb-0">SSH / Telnet Access (Optional)</h5>
            <small class="text-muted mt-0">Fill this to enable advanced features in the future.</small>

            <div class="col-md-2"><label class="form-label">Port</label><input type="number" name="ssh_port" id="ssh_port" class="form-control" value="<?= h($olt['ssh_port']) ?>"></div>
            <div class="col-md-3"><label class="form-label">Username</label><input type="text" name="username" id="username" class="form-control" value="<?= h($olt['username']) ?>"></div>
            
            <div class="col-md-3">
                <label class="form-label">SSH / Telnet Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="sshPassword" class="form-control" 
                           value="<?= h($decrypted_ssh_password) ?>" autocomplete="new-password" />
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn"><i class="bi bi-eye-slash"></i></button>
                </div>
                <?php if ($decrypt_failed): ?>
                    <div class="form-text text-warning">Stored password could not be decrypted safely — the field is left blank. Enter a new password to replace the stored one.</div>
                <?php else: ?>
                    <div class="form-text">Leave blank to keep existing password unchanged.</div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label">Enable / Privileged Password</label>
                <input type="password" name="enable_password" class="form-control" value="<?= h($decrypted_enable_password) ?>">
                <div class="form-text">Leave blank to keep existing enable password unchanged.</div>
            </div>

            <div class="col-md-8">
                <label class="form-label">Prompt Regex (optional)</label>
                <input type="text" name="prompt_regex" id="prompt_regex" class="form-control" value="<?= h($olt['prompt_regex'] ?? '') ?>" placeholder="e.g., /[#\>\:] $/">
                <div id="promptDetectResult" class="form-text mt-1"></div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" id="detectPromptBtn" class="btn btn-outline-secondary w-100">Detect Prompt</button>
            </div>

            <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" <?= $olt['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">OLT is Active</label></div></div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('sshPassword');
    const eyeIcon = togglePasswordBtn.querySelector('i');

    togglePasswordBtn.addEventListener('click', function() {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
        }
    });

    // Detect Prompt inline (shared logic)
    const detectBtn = document.getElementById('detectPromptBtn');
    const hostEl = document.getElementById('host');
    const portEl = document.getElementById('ssh_port');
    const result = document.getElementById('promptDetectResult');

    detectBtn.addEventListener('click', async function() {
        result.textContent = 'Detecting...';
        detectBtn.disabled = true;
        const host = hostEl.value.trim();
        const port = portEl.value.trim() || '22';
        if (!host) {
            result.textContent = 'Host is required for detection.';
            detectBtn.disabled = false;
            return;
        }
        const fd = new FormData();
        fd.append('action', 'detect_prompt');
        fd.append('host', host);
        fd.append('ssh_port', port);

        try {
            const resp = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await resp.json();
            if (json.ok) {
                result.innerHTML = '<span class="text-success">✅ Prompt detected successfully!</span><br><small class="text-muted">' + (json.detected ? json.detected : '') + '</small>';
            } else {
                result.innerHTML = '<span class="text-danger">✖ Detection failed: ' + (json.error || 'Unknown') + '</span>';
            }
        } catch (e) {
            result.innerHTML = '<span class="text-danger">✖ Detection error: ' + e.message + '</span>';
        } finally {
            detectBtn.disabled = false;
        }
    });
});
</script>
