<?php
// /app/audit.php — Audit logger with auto-migration
// Code English; comments Bangla.

declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- helpers: schema ---------- */
function audit_db(): PDO { return db(); }

function audit_table_exists(PDO $pdo, string $t): bool {
  try{
    $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $q->execute([$db,$t]); return (bool)$q->fetchColumn();
  }catch(Throwable $e){ return false; }
}
function audit_col_exists(PDO $pdo, string $t, string $c): bool {
  try{
    $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$db,$t,$c]); return (bool)$q->fetchColumn();
  }catch(Throwable $e){ return false; }
}
function audit_add_col_if_missing(PDO $pdo, string $t, string $colDef): void {
  // $colDef like: `entity` VARCHAR(64) NOT NULL
  preg_match('/`([^`]+)`/',$colDef,$m);
  $col = $m[1] ?? null; if(!$col) return;
  if (!audit_col_exists($pdo,$t,$col)) {
    try { $pdo->exec("ALTER TABLE `$t` ADD COLUMN $colDef"); } catch(Throwable $e) { /* ignore */ }
  }
}
function audit_add_index_if_missing(PDO $pdo, string $t, string $idxName, string $cols): void {
  try{
    $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?");
    $q->execute([$db,$t,$idxName]);
    if(!$q->fetchColumn()){
      $pdo->exec("ALTER TABLE `$t` ADD INDEX `$idxName` ($cols)");
    }
  }catch(Throwable $e){ /* ignore */ }
}

/* ---------- bootstrap: create or migrate ---------- */
function audit_bootstrap(): void {
  $pdo = audit_db();
  $table = 'audit_logs';

  // 1) যদি টেবিলই না থাকে, নতুন করে বানাই (JSON -> TEXT for wide compatibility)
  if (!audit_table_exists($pdo,$table)) {
    $pdo->exec("
      CREATE TABLE `$table`(
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `entity` VARCHAR(64) NOT NULL,
        `entity_id` BIGINT NULL,
        `action` VARCHAR(32) NOT NULL,
        `old_json` LONGTEXT NULL,
        `new_json` LONGTEXT NULL,
        `user_id` BIGINT NULL,
        `ip` VARCHAR(45) NULL,
        `user_agent` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }

  // 2) মাইগ্রেশন: পূর্বের ভিন্ন স্কিমা থাকলে রিনেম/অ্যাড
  //    - table -> entity
  if (!audit_col_exists($pdo,$table,'entity') && audit_col_exists($pdo,$table,'table')) {
    try { $pdo->exec("ALTER TABLE `$table` CHANGE `table` `entity` VARCHAR(64) NOT NULL"); } catch(Throwable $e){}
  }
  //    - row_id -> entity_id
  if (!audit_col_exists($pdo,$table,'entity_id') && audit_col_exists($pdo,$table,'row_id')) {
    try { $pdo->exec("ALTER TABLE `$table` CHANGE `row_id` `entity_id` BIGINT NULL"); } catch(Throwable $e){}
  }

  //    - essential columns add if missing
  audit_add_col_if_missing($pdo,$table,"`entity` VARCHAR(64) NOT NULL");
  audit_add_col_if_missing($pdo,$table,"`entity_id` BIGINT NULL");
  audit_add_col_if_missing($pdo,$table,"`action` VARCHAR(32) NOT NULL");
  audit_add_col_if_missing($pdo,$table,"`old_json` LONGTEXT NULL");
  audit_add_col_if_missing($pdo,$table,"`new_json` LONGTEXT NULL");
  audit_add_col_if_missing($pdo,$table,"`user_id` BIGINT NULL");
  audit_add_col_if_missing($pdo,$table,"`ip` VARCHAR(45) NULL");
  audit_add_col_if_missing($pdo,$table,"`user_agent` VARCHAR(255) NULL");
  audit_add_col_if_missing($pdo,$table,"`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");

  //    - helpful indexes
  audit_add_index_if_missing($pdo,$table,'idx_audit_entity','`entity`');
  audit_add_index_if_missing($pdo,$table,'idx_audit_entity_id','`entity_id`');
  audit_add_index_if_missing($pdo,$table,'idx_audit_action','`action`');
  audit_add_index_if_missing($pdo,$table,'idx_audit_created','`created_at`');
}

/* ---------- small helpers ---------- */
function audit_current_user_id(): ?int {
  foreach ([
    $_SESSION['user']['id'] ?? null,
    $_SESSION['user_id'] ?? null,
    $_SESSION['SESS_USER_ID'] ?? null,
  ] as $v) { $id = (int)$v; if ($id>0) return $id; }
  return null;
}
function audit_client_ip(): string {
  foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $ip = explode(',', $_SERVER[$k])[0]; return trim($ip); }
  }
  return '';
}

/* ---------- main logger (flexible signatures) ---------- */
function _audit_duplicate_exists(PDO $pdo, string $entity, ?int $entity_id, string $action, ?string $oldJson, ?string $newJson, string $ua, int $windowSeconds=300): bool {
  if ($windowSeconds <= 0) return false;
  $since = date('Y-m-d H:i:s', time() - $windowSeconds);
  try {
    $sql = "SELECT 1 FROM audit_logs WHERE entity <=> ? AND entity_id <=> ? AND action = ? AND user_agent <=> ? AND created_at >= ? AND old_json <=> ? AND new_json <=> ? ORDER BY id DESC LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([$entity, $entity_id, $action, $ua, $since, $oldJson, $newJson]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function _audit_write(string $entity, ?int $entity_id, string $action, $old=null, $new=null): void {
  $pdo = audit_db(); audit_bootstrap();

  $uid = audit_current_user_id();
  if ($uid === null || $uid <= 0) $uid = 0;
  $ip  = audit_client_ip();
  $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
  $oldJson = $old!==null ? json_encode($old,$flags) : null;
  $newJson = $new!==null ? json_encode($new,$flags) : null;

  $shouldDedup = ($uid === 0) && (($ua === 'cron_runner') || PHP_SAPI === 'cli');
  if ($shouldDedup && _audit_duplicate_exists($pdo, $entity, $entity_id, $action, $oldJson, $newJson, $ua)) {
    return;
  }

  $ins = $pdo->prepare("
    INSERT INTO audit_logs(entity, entity_id, action, old_json, new_json, user_id, ip, user_agent)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  $ins->execute([
    $entity,
    $entity_id,
    $action,
    $oldJson,
    $newJson,
    $uid,
    $ip,
    $ua
  ]);
}

function _audit_int($v): ?int {
  if ($v === null || $v === '') return null;
  return is_numeric($v) ? (int)$v : null;
}

function _audit_guess_entity(string $action): string {
  $a = strtolower($action);
  foreach ([
    'client'   => ['client','pppoe','ppp'],
    'payment'  => ['payment','pay'],
    'invoice'  => ['invoice','bill'],
    'expense'  => ['expense'],
    'employee' => ['employee','hr'],
    'user'     => ['user','role','permission'],
    'router'   => ['router'],
  ] as $entity => $keys) {
    foreach ($keys as $k) if (str_contains($a, $k)) return $entity;
  }
  return 'system';
}

function audit_log(...$args): void {
  $entity = 'system';
  $entity_id = null;
  $action = '';
  $old = null;
  $new = null;

  $argc = count($args);
  if ($argc >= 3) {
    // (entity, entity_id, action, old?, new?)
    if (is_string($args[0]) && (is_numeric($args[1]) || $args[1] === null) && is_string($args[2])) {
      $entity = $args[0];
      $entity_id = _audit_int($args[1]);
      $action = $args[2];
      $old = $args[3] ?? null;
      $new = $args[4] ?? null;
    // (action, entity, entity_id, meta)
    } elseif (is_string($args[0]) && is_string($args[1]) && (is_numeric($args[2]) || $args[2] === null)) {
      $action = $args[0];
      $entity = $args[1];
      $entity_id = _audit_int($args[2]);
      $new = $args[3] ?? null;
    // (action, entity_id, meta)
    } elseif (is_string($args[0]) && (is_numeric($args[1]) || $args[1] === null) && is_array($args[2])) {
      $action = $args[0];
      $entity_id = _audit_int($args[1]);
      $entity = _audit_guess_entity($action);
      $new = $args[2];
    // (user_id, entity_id, action, meta)
    } elseif (is_numeric($args[0]) && (is_numeric($args[1]) || $args[1] === null) && is_string($args[2])) {
      $entity_id = _audit_int($args[1]);
      $action = $args[2];
      $entity = _audit_guess_entity($action);
      $new = $args[3] ?? null;
    } else {
      $action = is_string($args[0]) ? $args[0] : 'event';
      $new = $args[1] ?? null;
    }
  } elseif ($argc === 2 && is_string($args[0]) && is_array($args[1])) {
    // (action, meta)
    $action = $args[0];
    $new = $args[1];
  }

  if ($action === '') return;
  _audit_write($entity, $entity_id, $action, $old, $new);
}

// Simple wrapper used by older code: audit(action, entity, entity_id, meta)
function audit(string $action, string $entity='system', ?int $entity_id=null, array $meta=[]): void {
  _audit_write($entity, $entity_id, $action, null, $meta);
}
