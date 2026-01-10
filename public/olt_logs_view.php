<?php
// /olt/olt_logs_view.php
// Read-only viewer for OLT logs (DB + file)

session_start();
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// fetch recent DB logs
$dbLogs = [];
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS olt_logs (
          id BIGINT AUTO_INCREMENT PRIMARY KEY,
          action VARCHAR(64) NOT NULL,
          olt_id BIGINT NULL,
          user_id BIGINT NULL,
          meta JSON NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $st = $pdo->query("SELECT * FROM olt_logs ORDER BY id DESC LIMIT 200");
    $dbLogs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// read recent file log lines
$fileLines = [];
$logFile = realpath(__DIR__ . '/../storage/logs/olt_actions.log');
if ($logFile && is_readable($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $lines = array_slice($lines, -200);
        foreach (array_reverse($lines) as $ln) {
            $decoded = json_decode($ln, true);
            $fileLines[] = $decoded ?: ['raw' => $ln];
        }
    }
}

include __DIR__ . '/../partials/partials_header.php';
?>
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-clipboard-data"></i> OLT Logs</h4>
      <div class="text-muted small">Latest 200 rows from DB and file logs</div>
    </div>
    <div class="d-flex gap-2">
      <a href="/olt/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
      <a href="/olt_logs_view.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span>Database Log (latest 200)</span>
          <span class="badge bg-light text-muted">Table: olt_logs</span>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($dbError)): ?>
            <div class="alert alert-danger m-3">DB error: <?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php elseif (empty($dbLogs)): ?>
            <div class="p-3 text-muted">No DB logs found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th class="text-nowrap">ID</th>
                    <th>Action</th>
                    <th>OLT</th>
                    <th>User</th>
                    <th>Meta</th>
                    <th class="text-nowrap">Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dbLogs as $row): ?>
                    <tr>
                      <td class="mono text-muted small"><?= (int)$row['id'] ?></td>
                      <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                      <td class="mono"><?= htmlspecialchars((string)($row['olt_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="mono"><?= htmlspecialchars((string)($row['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><small class="text-wrap d-inline-block" style="max-width:280px;"><?= htmlspecialchars($row['meta'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></td>
                      <td class="text-nowrap"><small class="text-muted mono"><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></small></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
          <span>File Log (latest 200)</span>
          <span class="badge bg-light text-muted">storage/logs/olt_actions.log</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($fileLines)): ?>
            <div class="p-3 text-muted">No file log entries found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th class="text-nowrap">Time</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Meta</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($fileLines as $row): ?>
                    <tr>
                      <td class="text-nowrap"><small class="text-muted mono"><?= htmlspecialchars($row['ts'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></td>
                      <td><span class="badge bg-secondary"><?= htmlspecialchars($row['action'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                      <td class="mono"><?= htmlspecialchars((string)($row['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><small class="text-wrap d-inline-block" style="max-width:320px;"><?= htmlspecialchars(json_encode($row['meta'] ?? $row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></small></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
