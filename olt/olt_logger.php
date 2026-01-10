<?php
// /olt/olt_logger.php
// Lightweight file logger + audit wrapper for OLT actions (file + DB table)

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
@include_once __DIR__ . '/../app/audit.php';

/* ------------------ helpers ------------------ */
function olt_log_entry_base(string $action, array $meta): array {
    return [
        'ts'      => date('c'),
        'action'  => $action,
        'user_id' => $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null),
        'meta'    => $meta,
    ];
}

/* File log */
function olt_log_action(string $action, array $meta = []): void {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $entry = olt_log_entry_base($action, $meta);
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line) { @file_put_contents($logDir . '/olt_actions.log', $line . PHP_EOL, FILE_APPEND); }
    olt_log_db($action, $meta);
}

/* DB table log */
function olt_log_db(string $action, array $meta = []): void {
    static $ensured = false;
    try {
        $pdo = db();
        if (!$ensured) {
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
            $ensured = true;
        }
        $entry = olt_log_entry_base($action, $meta);
        $oltId = $meta['olt_id'] ?? $meta['id'] ?? null;
        $uid   = is_numeric($entry['user_id'] ?? null) ? (int)$entry['user_id'] : null;
        $ins = $pdo->prepare("INSERT INTO olt_logs(action, olt_id, user_id, meta) VALUES (?,?,?,?)");
        $ins->execute([$action, $oltId, $uid, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    } catch (Throwable $e) {
        // ignore db issues to keep process non-fatal
    }
}

function olt_audit_action(string $action, ?int $olt_id = null, array $meta = []): void {
    $meta['olt_id'] = $olt_id;
    if (!function_exists('audit_log')) return;
    try { @call_user_func_array('audit_log', ['olt', $olt_id, $action, null, $meta]); return; } catch (Throwable $e) {}
    try { @call_user_func_array('audit_log', [$action, 'olt', $olt_id, $meta]); return; } catch (Throwable $e) {}
    try { @call_user_func_array('audit_log', [$action, $olt_id, $meta]); return; } catch (Throwable $e) {}
}
