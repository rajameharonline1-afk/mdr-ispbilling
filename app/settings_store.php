<?php
// /app/settings_store.php
// Purpose: Simple key/value settings store backed by `settings` table.
// Supports both schemas: (setting_key, setting_value) and legacy (key, value).

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function settings_schema(PDO $pdo): array {
  static $cache = null;
  if (is_array($cache)) return $cache;

  $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  $t  = 'settings';

  $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
  $st->execute([$db, $t]);
  $cols = array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
  $cols = array_fill_keys($cols, true);

  if (isset($cols['setting_key']) && isset($cols['setting_value'])) {
    $cache = ['table' => $t, 'k' => 'setting_key', 'v' => 'setting_value', 'id' => 'id'];
    return $cache;
  }
  if (isset($cols['key']) && isset($cols['value'])) {
    $cache = ['table' => $t, 'k' => 'key', 'v' => 'value', 'id' => 'id'];
    return $cache;
  }
  // Fallback (won't work well but avoids fatals)
  $cache = ['table' => $t, 'k' => 'setting_key', 'v' => 'setting_value', 'id' => 'id'];
  return $cache;
}

function settings_get(string $key, $default = null) {
  static $memo = [];
  if (array_key_exists($key, $memo)) return $memo[$key];

  $pdo = db();
  $s = settings_schema($pdo);
  $kcol = $s['k']; $vcol = $s['v']; $table = $s['table'];

  try {
    $st = $pdo->prepare("SELECT `$vcol` FROM `$table` WHERE `$kcol`=? LIMIT 1");
    $st->execute([$key]);
    $val = $st->fetchColumn();
    if ($val === false || $val === null) {
      $memo[$key] = $default;
      return $default;
    }
    $memo[$key] = (string)$val;
    return $memo[$key];
  } catch (Throwable $e) {
    $memo[$key] = $default;
    return $default;
  }
}

function settings_get_many(array $keys): array {
  $out = [];
  foreach ($keys as $k) $out[(string)$k] = settings_get((string)$k, null);
  return $out;
}

function settings_set(string $key, ?string $value): bool {
  static $memo = [];
  $pdo = db();
  $s = settings_schema($pdo);
  $kcol = $s['k']; $vcol = $s['v']; $idcol = $s['id']; $table = $s['table'];

  try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT `$idcol` FROM `$table` WHERE `$kcol`=? LIMIT 1");
    $st->execute([$key]);
    $id = (int)($st->fetchColumn() ?: 0);

    if ($id > 0) {
      $up = $pdo->prepare("UPDATE `$table` SET `$vcol`=? WHERE `$idcol`=?");
      $up->execute([(string)($value ?? ''), $id]);
    } else {
      $ins = $pdo->prepare("INSERT INTO `$table` (`$kcol`, `$vcol`) VALUES (?, ?)");
      $ins->execute([$key, (string)($value ?? '')]);
    }
    $pdo->commit();
    $memo[$key] = $value ?? '';
    // reset read cache
    if (function_exists('settings_cache_clear')) settings_cache_clear();
    return true;
  } catch (Throwable $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}
    return false;
  }
}

function settings_set_many(array $pairs): bool {
  $ok = true;
  foreach ($pairs as $k => $v) {
    $ok = settings_set((string)$k, $v === null ? null : (string)$v) && $ok;
  }
  return $ok;
}

function settings_cache_clear(): void {
  // This function exists to allow settings_get() memo reset across requests if needed.
  // No-op in PHP request lifecycle; placeholder for future.
}

