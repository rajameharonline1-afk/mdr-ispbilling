<?php
// /olt/olt_add.php
// (বাংলা) SNMP এবং SSH উভয় তথ্যসহ নতুন OLT যোগ করার জন্য চূড়ান্ত সংস্করণ
session_start();
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/olt_schema.php'; // ensure telnet-related columns
require_once __DIR__ . '/../app/security_helpers.php'; // এনক্রিপশনের জন্য

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$success = false;
$pdo = db();
// নতুন ফিচারগুলোর জন্য দরকারি কলাম (mgmt_proto, telnet_port, prompt_regex) নিশ্চিত করি
ensure_olt_telnet_columns($pdo);
$colList      = $pdo->query("SHOW COLUMNS FROM olts")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$hasSnmp      = in_array('snmp_community', $colList, true);
$hasMgmtProto = in_array('mgmt_proto', $colList, true);

// AJAX: Detect Prompt action (inline)
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

    // Try primary port, then common telnet fallback (23) if refused/closed
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

    // Analyze banner for common patterns
    $detected = null;
    if (preg_match('/^SSH-/', $data)) {
        $detected = 'SSH banner: ' . strtok($data, "\n");
    } elseif (preg_match('/password[: ]*$/i', $data) || stripos($data, 'password') !== false) {
        $detected = 'Password prompt detected';
    } elseif (preg_match('/login[: ]*$/i', $data) || stripos($data, 'login') !== false) {
        $detected = 'Login prompt detected';
    } else {
        // fallback: return first 120 chars as sample
        $detected = 'Banner: ' . (strlen($data) > 120 ? substr($data,0,120).'...' : $data);
    }

    echo json_encode(['ok' => true, 'detected' => $detected]);
    exit;
}

// Normal form submission: Add OLT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // ফর্ম থেকে সব তথ্য গ্রহণ করুন
    $name = trim($_POST['name'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $snmp_community = trim($_POST['snmp_community'] ?? 'public');
    // Telnet default port 23; ফর্মে আলাদা ইনপুট রাখছি না, তাই কোডে ডিফল্ট সেট করি
    $ssh_port = (int)($_POST['ssh_port'] ?? 23);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $enable_password = trim($_POST['enable_password'] ?? '');
    $prompt_regex = trim($_POST['prompt_regex'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // বেসিক ভ্যালিডেশন
    if (empty($name) || empty($vendor) || empty($host) || ($hasSnmp && $snmp_community === '')) {
        $req = ["OLT Name","Vendor","Host IP"];
        if ($hasSnmp) $req[] = "SNMP Community";
        $errors[] = implode(', ', $req) . " are required fields.";
    }

    // enforce CLI credentials because downstream automation needs them
    $cliMissing = [];
    if ($ssh_port <= 0) {
        $cliMissing[] = 'Valid Port';
    }
    if ($username === '') {
        $cliMissing[] = 'Username';
    }
    if ($password === '') {
        $cliMissing[] = 'Password';
    }
    if ($enable_password === '') {
        $cliMissing[] = 'Enable Password';
    }
    if ($prompt_regex === '') {
        $cliMissing[] = 'Prompt Regex';
    }
    if (!empty($cliMissing)) {
        $errors[] = implode(', ', $cliMissing) . ' must be provided for CLI access.';
    }

    if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var('http://' . $host, FILTER_VALIDATE_URL)) {
        // allow hostname too; so only basic length check
        if (strlen($host) < 3) $errors[] = "Invalid Host value.";
    }

    if (empty($errors)) {
        try {
            // Encrypt passwords if provided
            $encrypted_password = null;
            if ($password !== '') {
                $encrypted_password = encrypt_password($password, ENCRYPTION_KEY);
            }
            $encrypted_enable = null;
            if ($enable_password !== '') {
                $encrypted_enable = encrypt_password($enable_password, ENCRYPTION_KEY);
            }

            // ডাটাবেসে OLT-এর তথ্য সেভ করুন
            $cols = ['name','vendor','host','ssh_port','username','password','enable_password','prompt_regex','is_active'];
            $params = [
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
            if ($hasSnmp) {
                $cols[] = 'snmp_community';
                $params[':snmp_community'] = $snmp_community;
            }
            if ($hasMgmtProto) {
                // সমগ্র প্রজেক্টে আমরা ডিফল্টভাবে telnet ব্যবহার করছি
                $cols[] = 'mgmt_proto';
                $params[':mgmt_proto'] = 'telnet';
            }
            $colSql = implode(', ', $cols);
            $valSql = ':' . implode(', :', $cols);

            $stmt = $pdo->prepare("INSERT INTO olts ({$colSql}) VALUES ({$valSql})");
            $stmt->execute($params);

            $success = true;
            $_SESSION['toast_message'] = "OLT '<strong>" . h($name) . "</strong>' added successfully!";
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

$page_title = "Add New OLT";
include __DIR__ . '/../partials/partials_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-3"><i class="bi bi-plus-circle-fill"></i> Add New OLT</h4>
        <a href="index.php" class="btn btn-secondary">Back to OLT List</a>
    </div>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Error!</strong><br>
            <?= implode('<br>', array_map('h', $errors)) ?>
        </div>
    <?php endif; ?>

    <form id="oltAddForm" method="POST" class="card p-3 bg-light">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">OLT Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vendor <span class="text-danger">*</span></label>
                <select name="vendor" class="form-select" required>
                    <option value="vsol" <?= ($_POST['vendor'] ?? '') == 'vsol' ? 'selected' : '' ?>>V-SOL</option>
                    <option value="huawei" <?= ($_POST['vendor'] ?? '') == 'huawei' ? 'selected' : '' ?>>Huawei</option>
                    <option value="zte" <?= ($_POST['vendor'] ?? '') == 'zte' ? 'selected' : '' ?>>ZTE</option>
                    <option value="bdcom" <?= ($_POST['vendor'] ?? '') == 'bdcom' ? 'selected' : '' ?>>BDCOM</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">OLT IP <span class="text-danger">*</span></label>
                <input type="text" name="host" id="host" class="form-control" value="<?= h($_POST['host'] ?? '') ?>" placeholder="e.g., 192.168.130.2" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">SNMP Community <span class="text-danger">*</span></label>
                <input type="text" name="snmp_community" class="form-control" value="<?= h($_POST['snmp_community'] ?? 'public') ?>" required>
                <!-- <small class="form-text text-muted">এখানে আপনি ইচ্ছামত SNMP community সেট করতে পারবেন (যেমন <code>public</code>, <code>private</code> বা অন্য কিছু)।</small> -->
            </div>

            <hr class="my-3 w-100">
            <h5 class="mb-0">SSH / Telnet Access</h5>
            <small class="text-muted mt-0">These credentials are required for CLI automation in this deployment.</small>

            <div class="col-md-2">
                <label class="form-label">Port <span class="text-danger">*</span></label>
                <input type="number" name="ssh_port" id="ssh_port" class="form-control" value="<?= h($_POST['ssh_port'] ?? 23) ?>" placeholder="23" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" id="username" class="form-control" value="<?= h($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="password" class="form-control" value="" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Enable Password <span class="text-danger">*</span></label>
                <input type="password" name="enable_password" id="enable_password" class="form-control" value="<?= h($_POST['enable_password'] ?? '') ?>" required>
            </div>

            <div class="col-md-8">
                <label class="form-label">Prompt Regex <span class="text-danger">*</span></label>
                <input type="text" name="prompt_regex" id="prompt_regex" class="form-control" value="<?= h($_POST['prompt_regex'] ?? '') ?>" placeholder="e.g., /[#\>\:] $/" required>
                <div id="promptDetectResult" class="form-text mt-1"></div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" id="detectPromptBtn" class="btn btn-outline-secondary w-100">Detect Prompt</button>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">OLT is Active</label>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save OLT</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detectBtn = document.getElementById('detectPromptBtn');
    const hostEl = document.getElementById('host');
    const portEl = document.getElementById('ssh_port');
    const result = document.getElementById('promptDetectResult');
    if (!detectBtn) return;

    detectBtn.addEventListener('click', async function() {
        result.textContent = 'Detecting...';
        detectBtn.disabled = true;
        const host = hostEl.value.trim();
        const port = portEl.value.trim() || '23';
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
