<?php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $st = $pdo->prepare("DELETE FROM tickets WHERE id=?");
    $st->execute([$delId]);
    $_SESSION['flash_success'] = 'Ticket deleted.';
    header('Location: /public/tickets.php');
    exit;
}

$stmt = $pdo->query("SELECT t.*, c.name AS client_name 
                     FROM tickets t
                     LEFT JOIN clients c ON c.id = t.client_id
                     ORDER BY t.created_at DESC");
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../partials/partials_header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">All Tickets</h3>
        <a href="/public/ticket_add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Add Ticket</a>
    </div>
    <?php if(!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if(!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <table class="table table-bordered">
        <tr>
            <th>ID</th>
            <th>Client</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
        <?php foreach ($tickets as $t): ?>
        <tr>
            <td><?= $t['id'] ?></td>
            <td><?= htmlspecialchars($t['client_name']) ?></td>
            <td><?= htmlspecialchars($t['subject']) ?></td>
            <td><?= ucfirst($t['status']) ?></td>
            <td><?= $t['created_at'] ?></td>
            <td class="d-flex gap-2">
              <a href="ticket_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-info">View</a>
              <a href="ticket_add.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <form method="post" action="tickets.php" onsubmit="return confirm('Delete this ticket?');">
                <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
