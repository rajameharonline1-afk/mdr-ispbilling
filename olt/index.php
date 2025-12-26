<?php
// /olt/index.php
// (বাংলা) OLT ডিলিট বাটন পুনরুদ্ধার করা চূড়ান্ত সংস্করণ
session_start();
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

$pdo = db();
$olts = $pdo->query("SELECT * FROM olts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "OLT Management";
include __DIR__ . '/../partials/partials_header.php';
?>

<style>
@keyframes spin360 {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.sync-rotating {
    animation: spin360 0.9s linear infinite;
    display: inline-block;
}
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-pc-display-horizontal"></i> OLT Management</h4>
        <a href="olt_add.php" class="btn btn-primary"><i class="bi bi-plus-circle-fill"></i> Add New OLT</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Vendor</th>
                            <th>Host IP</th>
                            <th>Connection Test</th>
                            <th>Sync</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($olts)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No OLTs found.</td></tr>
                    <?php else: foreach ($olts as $olt): ?>
                        <tr>
                            <td><strong><?= h($olt['name']) ?></strong></td>
                            <td><?= h(strtoupper($olt['vendor'])) ?></td>
                            <td><code><?= h($olt['host']) ?></code></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info btn-test-connection" data-olt-id="<?= $olt['id'] ?>">
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    <i class="bi bi-patch-question-fill"></i>
                                    <span class="btn-text">Test</span>
                                </button>
                                <span class="test-result small ms-2"></span>
                            </td>
                            <td>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-sync-olt"
                                        title="Sync MAC table via Telnet"
                                        data-olt-id="<?= $olt['id'] ?>">
                                    <i class="bi bi-arrow-repeat sync-icon"></i>
                                </button>
                                <span class="sync-result small ms-2 text-muted"></span>
                            </td>
                            <td><span class="badge <?= $olt['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $olt['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="text-end">
                                <a href="/public/onu_monitor.php?olt_id=<?= $olt['id'] ?>" class="btn btn-info btn-sm" title="Monitor ONUs"><i class="bi bi-broadcast"></i> Monitor</a>
                                <a href="olt_edit.php?id=<?= $olt['id'] ?>" class="btn btn-warning btn-sm" title="Edit OLT"><i class="bi bi-pencil-square"></i></a>
                                <!-- **FIX: ডিলিট বাটনটি এখানে ফিরিয়ে আনা হয়েছে** -->
                                <a href="olt_delete.php?id=<?= $olt['id'] ?>" class="btn btn-danger btn-sm" title="Delete OLT" onclick="return confirm('Are you sure you want to delete this OLT? This cannot be undone.')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header"><strong class="me-auto" id="toast-title"></strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div>
    <div class="toast-body" id="toast-body"></div>
  </div>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Logic for showing toast messages from session (add/edit/delete)
    <?php if ($toast_message): ?>
    const toastEl = document.getElementById('liveToast');
    const toastBody = document.getElementById('toast-body');
    const toastTitle = document.getElementById('toast-title');
    const toastHeader = toastEl.querySelector('.toast-header');
    
    toastBody.innerHTML = '<?= addslashes($toast_message) ?>';
    toastHeader.classList.remove('bg-success', 'text-white', 'bg-warning', 'text-dark', 'bg-danger');
    
    if ('<?= $toast_type ?>' === 'success') {
        toastHeader.classList.add('bg-success', 'text-white');
        toastTitle.innerText = 'Success!';
    } else {
        toastHeader.classList.add('bg-danger', 'text-white');
        toastTitle.innerText = 'Error!';
    }
    new bootstrap.Toast(toastEl).show();
    <?php endif; ?>

    // Logic for "Test Connection" buttons
    document.querySelectorAll('.btn-test-connection').forEach(button => {
        button.addEventListener('click', function() {
            const oltId = this.dataset.oltId;
            const spinner = this.querySelector('.spinner-border');
            const icon = this.querySelector('.bi');
            const btnText = this.querySelector('.btn-text');
            const resultSpan = this.nextElementSibling;

            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btnText.textContent = 'Testing...';
            resultSpan.textContent = '';
            this.disabled = true;

            fetch('/api/olt_test_connection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ olt_id: oltId })
            })
            .then(res => res.json())
            .then(result => {
                if (result.ok) {
                    resultSpan.textContent = 'Success!';
                    resultSpan.className = 'test-result small ms-2 text-success fw-bold';
                    resultSpan.title = result.description || 'Successfully connected to OLT.';
                } else {
                    resultSpan.textContent = 'Failed!';
                    resultSpan.className = 'test-result small ms-2 text-danger fw-bold';
                    resultSpan.title = result.error;
                }
            })
            .catch(err => {
                resultSpan.textContent = 'Error!';
                resultSpan.className = 'test-result small ms-2 text-danger fw-bold';
                resultSpan.title = 'A network or server error occurred.';
            })
            .finally(() => {
                spinner.classList.add('d-none');
                icon.classList.remove('d-none');
                btnText.textContent = 'Test';
                this.disabled = false;
            });
        });
    });

    // Logic for per-OLT MAC sync buttons
    document.querySelectorAll('.btn-sync-olt').forEach(button => {
        button.addEventListener('click', function() {
            const oltId = this.dataset.oltId;
            if (!oltId) return;

            const icon = this.querySelector('.sync-icon');
            const resultSpan = this.closest('td').querySelector('.sync-result');
            const url = `/api/olt_mac_refresh_telnet.php?olt_id=${encodeURIComponent(oltId)}`;

            this.disabled = true;
            if (icon) icon.classList.add('sync-rotating');
            if (resultSpan) {
                resultSpan.textContent = 'Syncing...';
                resultSpan.className = 'sync-result small ms-2 text-muted';
                resultSpan.removeAttribute('title');
            }

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (!resultSpan) return;
                    if (data.ok) {
                        const perOlt = data.per_olt || {};
                        const count = perOlt[oltId] ?? perOlt[String(oltId)] ?? null;
                        resultSpan.textContent = count !== null ? `Synced ${count}` : 'Synced';
                        resultSpan.className = 'sync-result small ms-2 text-success fw-bold';
                        resultSpan.title = 'Latest MAC table pulled successfully.';
                    } else {
                        const message = data.error || (Array.isArray(data.errors) && data.errors.length ? data.errors[0] : 'Sync failed.');
                        resultSpan.textContent = 'Failed';
                        resultSpan.className = 'sync-result small ms-2 text-danger fw-bold';
                        resultSpan.title = message;
                    }
                })
                .catch(() => {
                    if (!resultSpan) return;
                    resultSpan.textContent = 'Error';
                    resultSpan.className = 'sync-result small ms-2 text-danger fw-bold';
                    resultSpan.title = 'A network or server error occurred.';
                })
                .finally(() => {
                    this.disabled = false;
                    if (icon) icon.classList.remove('sync-rotating');
                });
        });
    });
});
</script>
