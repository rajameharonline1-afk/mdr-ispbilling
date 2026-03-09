<?php
// /tg/run_now.php — Run queue now + show last errors (admin only)
// UI: English; Comments: বাংলা

declare(strict_types=1);
$ROOT = __DIR__ . '/..';
require_once $ROOT.'/app/require_login.php';
require_once $ROOT.'/app/db.php';
require_once __DIR__.'/telegram.php';
$acl_file = $ROOT.'/app/acl.php'; if (is_file($acl_file)) require_once $acl_file;
if (function_exists('require_perm')) { require_perm('notify.run'); }

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

// Run batch first
$res = tg_run_batch($pdo);

// Fetch last 10 items
$rows = [];
if (tg_tbl_exists($pdo, 'telegram_queue')) {
  $q = $pdo->query("SELECT id, client_id, template_key, status, retries, last_error, sent_at, created_at
                    FROM telegram_queue ORDER BY id DESC LIMIT 10");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// HTML
$page_title = 'Telegram Queue Runner';
$_active = 'settings_tg';
require_once $ROOT . '/partials/partials_header.php';
?>

<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Telegram Queue Runner</h4>
    <a class="btn btn-sm btn-outline-secondary" href="/tg/run_now.php">Run Again</a>
  </div>

  <div class="alert alert-info mono">
    Processed=<strong><?= (int)$res['processed'] ?></strong>
    &nbsp;&nbsp;Sent=<strong><?= (int)$res['sent'] ?></strong>
    &nbsp;&nbsp;Failed=<strong><?= (int)$res['failed'] ?></strong>
  </div>

  <div class="card ads-ds-card">
    <div class="card-header bg-light"><strong>Recent Queue (last 10)</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 ads-ds-table">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Template</th>
              <th>Status</th>
              <th>Retries</th>
              <th>Subscriber</th>
              <th>Last Error</th>
              <th>Sent At</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No queue rows.</td></tr>
          <?php else:
            foreach ($rows as $r):
              $sub = tg_subscriber_get($pdo, (int)$r['client_id']);
              $subTxt = $sub ? ('chat_id='.$sub['chat_id']) : '— (none)';
          ?>
            <tr>
              <td class="mono"><?= (int)$r['id'] ?></td>
              <td class="mono">#<?= (int)$r['client_id'] ?></td>
              <td class="mono"><?= h($r['template_key']) ?></td>
              <td>
                <?php
                  $badge = 'secondary';
                  if ($r['status']==='queued') $badge='warning';
                  if ($r['status']==='sent')   $badge='success';
                  if ($r['status']==='failed') $badge='danger';
                ?>
                <span class="badge bg-<?= $badge ?>"><?= h($r['status']) ?></span>
              </td>
              <td class="mono"><?= (int)$r['retries'] ?></td>
              <td class="mono"><?= h($subTxt) ?></td>
              <td class="err small"><?= h((string)$r['last_error']) ?></td>
              <td class="mono small"><?= h((string)$r['sent_at'] ?: '-') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3 small text-muted">
    Tip: যদি <span class="badge bg-danger">failed</span> সাঝে <code>No active Telegram subscriber</code> দেখেন, তাহলে নিচের লিংকার ফাইল দিয়ে client-কে সাবস্ক্রাইব করান।
  </div>
</div>
<?php require_once $ROOT . '/partials/partials_footer.php'; ?>
