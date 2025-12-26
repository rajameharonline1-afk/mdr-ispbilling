<?php
declare(strict_types=1);
// /api/client_left_bulk.php (সংশ্লিষ্ট অংশ)

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audit.php';

header('Content-Type: application/json; charset=utf-8');
function jexit($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$ids = $_POST['ids'] ?? [];
$target = $_POST['target'] ?? ''; // 'left' or 'undo'
if (!is_array($ids) || !$ids) jexit(['ok'=>false,'msg'=>'No IDs']);
if (!in_array($target, ['left','undo'], true)) jexit(['ok'=>false,'msg'=>'Invalid target']);

$want_left = $target === 'left' ? 1 : 0;
$left_at_sql = $want_left ? "NOW()" : "NULL";

$ok = 0; $fail = 0;

$stmt = db()->prepare("SELECT id, name, pppoe_id, is_left FROM clients WHERE id=?");
$up   = db()->prepare("UPDATE clients SET is_left=?, left_at={$left_at_sql} WHERE id=?");

foreach ($ids as $rawId) {
    $id = (int)$rawId;
    if (!$id) { $fail++; continue; }

    $stmt->execute([$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) { $fail++; continue; }

    try {
        $up->execute([$want_left, $id]);
        $ok++;

        // ✅ AUDIT LOG
        $action = $want_left ? 'client_left' : 'client_undo_left';
        audit($action, 'client', $id, [
            'pppoe_id' => $c['pppoe_id'],
            'name'     => $c['name'],
            'from'     => (int)$c['is_left'],
            'to'       => (int)$want_left,
        ]);
    } catch (Throwable $e) {
        $fail++;
    }
}

jexit(['ok'=>true,'updated'=>$ok,'failed'=>$fail]);
