<?php
// /api/package_upsert.php
// (বাংলা) Create/Update package (name unique, price>=0)
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json; charset=utf-8');
function out($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function hascol(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $st->execute([$column]);
  return (bool)$st->fetchColumn();
}

try{
  $in = json_decode(file_get_contents('php://input'), true) ?? [];
  $id    = isset($in['id']) && $in['id'] !== '' ? (int)$in['id'] : null;
  $name  = trim((string)($in['name'] ?? ''));
  $price = (float)($in['price'] ?? 0);
  $speed = trim((string)($in['speed'] ?? ''));
  $desc  = trim((string)($in['description'] ?? ''));
  $desc  = ($desc === '') ? null : $desc;

  if ($name === '') out(['ok'=>false,'error'=>'Package name is required']);
  if ($price < 0)   out(['ok'=>false,'error'=>'Price must be >= 0']);

  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $hasCreated = hascol($pdo,'packages','created_at');
  $hasUpdated = hascol($pdo,'packages','updated_at');
  $hasSpeed   = hascol($pdo,'packages','speed');
  $hasValidity= hascol($pdo,'packages','validity');
  $hasDesc    = hascol($pdo,'packages','description');

  if ($hasSpeed && $speed === '') out(['ok'=>false,'error'=>'Speed is required']);

  // (বাংলা) name unique (case-insensitive)
  $sqlDup = "SELECT id FROM packages WHERE LOWER(name)=LOWER(?)";
  $params = [$name];
  if ($id) { $sqlDup .= " AND id<>?"; $params[] = $id; }
  $st = $pdo->prepare($sqlDup); $st->execute($params);
  if ($st->fetch()) out(['ok'=>false,'error'=>'A package with the same name already exists']);

  if ($id) {
    $sql = "UPDATE packages SET name=?, price=?";
    $params = [$name,$price];
    if ($hasDesc) {
      $sql .= ", description=?";
      $params[] = $desc;
    }
    if ($hasSpeed) {
      $sql .= ", speed=?";
      $params[] = $speed;
    }
    if ($hasUpdated) {
      $sql .= ", updated_at=NOW()";
    }
    $sql .= " WHERE id=?";
    $params[] = $id;
    $u = $pdo->prepare($sql);
    $u->execute($params);
    out(['ok'=>true,'message'=>'Package updated','id'=>$id]);
  } else {
    $cols = ['name','price'];
    $vals = ['?','?'];
    $params = [$name,$price];
    if ($hasDesc) {
      $cols[] = 'description';
      $vals[] = '?';
      $params[] = $desc;
    }
    if ($hasSpeed) {
      $cols[] = 'speed';
      $vals[] = '?';
      $params[] = $speed;
    }
    if ($hasValidity) {
      $cols[] = 'validity';
      $vals[] = '?';
      $params[] = 30;
    }
    if ($hasCreated) {
      $cols[] = 'created_at';
      $vals[] = 'NOW()';
    }
    if ($hasUpdated) {
      $cols[] = 'updated_at';
      $vals[] = 'NOW()';
    }
    $sql = "INSERT INTO packages (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $i = $pdo->prepare($sql);
    $i->execute($params);
    out(['ok'=>true,'message'=>'Package created','id'=>(int)$pdo->lastInsertId()]);
  }
}catch(Throwable $e){
  out(['ok'=>false,'error'=>$e->getMessage()]);
}
