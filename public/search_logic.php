<?php
// /public/search_logic.php
// Live suggestion backend for global search.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('respond_json')) {
  function respond_json(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

$q = trim((string)($_REQUEST['query'] ?? $_REQUEST['q'] ?? $_REQUEST['search'] ?? ''));
if ($q === '' || mb_strlen($q) < 1) {
  respond_json(['status' => 'success', 'results' => []]);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);

if (!function_exists('pick_col')) {
  function pick_col(array $cols, array $cands): string {
    foreach ($cands as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return '';
  }
}

$colId     = pick_col($cols, ['id','client_id']);
$colCode   = pick_col($cols, ['client_code','code','clientid']);
$colName   = pick_col($cols, ['full_name','name','client_name']);
$colMobile = pick_col($cols, ['mobile_number','mobile','phone']);
$colPppoe  = pick_col($cols, ['pppoe_username','pppoe_id','username']);
$colDelete = pick_col($cols, ['is_deleted']);

$where = [];
if ($colId !== '')     $where[] = "CAST(c.`{$colId}` AS CHAR) LIKE :q";
if ($colCode !== '' && $colCode !== $colId && $colCode !== $colPppoe) $where[] = "c.`{$colCode}` LIKE :q";
if ($colName !== '')   $where[] = "c.`{$colName}` LIKE :q";
if ($colMobile !== '') $where[] = "c.`{$colMobile}` LIKE :q";
if ($colPppoe !== '')  $where[] = "c.`{$colPppoe}` LIKE :q";

if (!$where) {
  respond_json(['status' => 'success', 'results' => []]);
}

$select = [
  $colId !== '' ? "c.`{$colId}` AS client_id" : "c.id AS client_id",
  $colCode !== '' ? "c.`{$colCode}` AS client_code" : "'' AS client_code",
  $colName !== '' ? "c.`{$colName}` AS full_name" : "'' AS full_name",
  $colMobile !== '' ? "c.`{$colMobile}` AS mobile_number" : "'' AS mobile_number",
  $colPppoe !== '' ? "c.`{$colPppoe}` AS pppoe_username" : "'' AS pppoe_username",
];

$sql = "SELECT " . implode(', ', $select) . "
        FROM clients c
        WHERE " . ($colDelete !== '' ? "COALESCE(c.`{$colDelete}`,0)=0 AND " : '') . "(" . implode(' OR ', $where) . ")
        ORDER BY " . ($colName !== '' ? "c.`{$colName}` ASC" : "client_id ASC") . "
        LIMIT 10";

$st = $pdo->prepare($sql);
$like = '%' . $q . '%';
$st->bindValue(':q', $like, PDO::PARAM_STR);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

respond_json(['status' => 'success', 'results' => $rows]);
