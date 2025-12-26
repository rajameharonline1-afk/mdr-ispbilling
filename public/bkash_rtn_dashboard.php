<?php
// /public/bkash_rtn_dashboard.php
// bKash RTN Webhook Payment Monitoring & Management Dashboard
// Display: received events, processing status, applied payments, error tracking
declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = db();

// Filters
$fromDate = trim($_GET['from_date'] ?? date('Y-m-01'));
$toDate   = trim($_GET['to_date'] ?? date('Y-m-d'));
$status   = trim($_GET['status'] ?? '');
$processed = trim($_GET['processed'] ?? '');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) $toDate = date('Y-m-d');

// Build WHERE clause
$where = [];
$params = [];
$where[] = "e.received_at >= ?";
$params[] = $fromDate . ' 00:00:00';
$where[] = "e.received_at <= ?";
$params[] = $toDate . ' 23:59:59';

if ($status !== '') {
  $where[] = "e.status = ?";
  $params[] = $status;
}
if ($processed !== '') {
  $where[] = "e.processed = ?";
  $params[] = $processed === 'yes' ? 1 : 0;
}

$whereStr = implode(' AND ', $where);

// Summary stats
$sumSql = "
SELECT 
  COUNT(*) AS total_events,
  SUM(CASE WHEN e.processed=1 THEN 1 ELSE 0 END) AS processed_count,
  SUM(CASE WHEN e.processed=0 THEN 1 ELSE 0 END) AS pending_count,
  SUM(CASE WHEN e.processed=1 AND e.applied_client_id>0 THEN 1 ELSE 0 END) AS applied_count,
  SUM(CASE WHEN e.processed=0 AND e.last_error IS NOT NULL THEN 1 ELSE 0 END) AS failed_count,
  COALESCE(SUM(CASE WHEN e.processed=1 THEN e.amount ELSE 0 END), 0) AS total_applied,
  COALESCE(SUM(CASE WHEN e.processed=0 THEN e.amount ELSE 0 END), 0) AS total_pending
FROM bkash_rtn_events e
WHERE $whereStr
";
$sumStmt = $pdo->prepare($sumSql);
$sumStmt->execute($params);
$summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 50);
$offset = ($page - 1) * $limit;

// Detect available client columns for display
$clientDisplayCol = 'id';
$clientCols = [];
try {
  $colSt = $pdo->query("SHOW COLUMNS FROM clients");
  $clientCols = array_map(fn($r) => $r[0], $colSt->fetchAll(PDO::FETCH_NUM));
} catch (Throwable $e) {}

foreach (['username', 'name', 'client_code', 'code', 'pppoe_id'] as $col) {
  if (in_array($col, $clientCols, true)) {
    $clientDisplayCol = $col;
    break;
  }
}

// Events list
$listSql = "
SELECT 
  e.id, e.event_type, e.event_id, e.trx_id, e.status, e.amount, e.payer_msisdn,
  e.merchant_invoice_number, e.received_at, e.processed, e.processed_at,
  e.applied_client_id, e.applied_amount, e.last_error, e.remote_ip, e.process_attempts,
  COALESCE(c.`" . $clientDisplayCol . "`, c.id) AS client_name,
  COALESCE(p.method, 'N/A') AS payment_method
FROM bkash_rtn_events e
LEFT JOIN clients c ON e.applied_client_id COLLATE utf8mb4_unicode_ci = c.id COLLATE utf8mb4_unicode_ci
LEFT JOIN payments p ON p.txn_id COLLATE utf8mb4_unicode_ci = e.trx_id COLLATE utf8mb4_unicode_ci AND p.method='bkash'
WHERE $whereStr
ORDER BY e.received_at DESC
LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$events = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Count for pagination
$countSql = "SELECT COUNT(*) FROM bkash_rtn_events e WHERE $whereStr";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Unique statuses for filter dropdown
$statusesSql = "SELECT DISTINCT status FROM bkash_rtn_events WHERE status IS NOT NULL ORDER BY status";
$statuses = $pdo->query($statusesSql)->fetchAll(PDO::FETCH_COLUMN);

?>
<?php require_once __DIR__ . '/../partials/partials_header.php'; ?>
<div class="container-fluid my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1">bKash RTN Webhook Monitor</h3>
      <small class="text-muted">রিয়েল-টাইম পেমেন্ট নোটিফিকেশন (RTN) ট্র্যাকিং ও ম্যানেজমেন্ট</small>
      <br>
      <span class="badge bg-info mt-2"><i class="bi bi-cloud"></i> Webhook System</span>
      <span class="badge bg-secondary mt-2"><i class="bi bi-shuffle"></i> Real-Time Processing</span>
    </div>
  </div>
  
  <!-- Inline Detail Cards (identical to modal content) -->
  <div class="row mb-4" id="inlineDetailCards">
    <div class="col-md-6 mb-2">
      <div class="card shadow-sm" id="detailCardLeft">
        <div class="card-header bg-light">
          <strong>ইভেন্ট বিস্তারিত (কার্ড ১)</strong>
        </div>
        <div class="card-body" id="detailCardLeftBody">
          <div class="text-muted">একটি ইভেন্ট নির্বাচন করুন বা বিস্তারিত দেখুন বোতামে ক্লিক করুন।</div>
        </div>
      </div>
    </div>
    <div class="col-md-6 mb-2">
      <div class="card shadow-sm" id="detailCardRight">
        <div class="card-header bg-light">
          <strong>ইভেন্ট কাঁচা ডেটা ও লোগিং (কার্ড ২)</strong>
        </div>
        <div class="card-body" id="detailCardRightBody">
          <div class="text-muted">র কাঁচা পে-লোড এবং প্রসেসিং স্টেটাস এখানে দেখানো হবে।</div>
        </div>
      </div>
    </div>
  </div>
  <hr class="my-3">

  <!-- Summary Cards -->
  <div class="mb-3">
    <h5 class="mb-3">📊 সংক্ষিপ্ত পরিসংখ্যান</h5>
  </div>
  <div class="row mb-4">
    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
      <div class="card bg-primary text-white h-100 border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-white-50 d-block mb-2"><i class="bi bi-diagram-3"></i> মোট ইভেন্ট</small>
              <h4 class="mb-0 fw-bold"><?= h((string)($summary['total_events'] ?? 0)) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
      <div class="card bg-success text-white h-100 border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-white-50 d-block mb-2"><i class="bi bi-check-circle"></i> প্রসেস হয়েছে</small>
              <h4 class="mb-1 fw-bold"><?= h((string)($summary['processed_count'] ?? 0)) ?></h4>
              <small class="text-white-50">৳<?= number_format((float)($summary['total_applied'] ?? 0), 2) ?></small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
      <div class="card bg-warning text-dark h-100 border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-dark-50 d-block mb-2"><i class="bi bi-hourglass-split"></i> পেন্ডিং</small>
              <h4 class="mb-1 fw-bold"><?= h((string)($summary['pending_count'] ?? 0)) ?></h4>
              <small class="text-dark-50">৳<?= number_format((float)($summary['total_pending'] ?? 0), 2) ?></small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
      <div class="card bg-info text-white h-100 border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-white-50 d-block mb-2"><i class="bi bi-check2-square"></i> প্রয়োগ করা</small>
              <h4 class="mb-0 fw-bold"><?= h((string)($summary['applied_count'] ?? 0)) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
      <div class="card bg-danger text-white h-100 border-0 shadow-sm">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-white-50 d-block mb-2"><i class="bi bi-exclamation-circle"></i> ত্রুটি</small>
              <h4 class="mb-0 fw-bold"><?= h((string)($summary['failed_count'] ?? 0)) ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card shadow-sm mb-4 border-top border-info border-3">
    <div class="card-header bg-light d-flex align-items-center gap-2">
      <i class="bi bi-funnel text-info"></i>
      <h6 class="mb-0">🔍 ফিল্টার এবং সার্চ</h6>
    </div>
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-2">
          <label class="form-label">থেকে তারিখ</label>
          <input type="date" name="from_date" class="form-control" value="<?= h($fromDate) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">পর্যন্ত তারিখ</label>
          <input type="date" name="to_date" class="form-control" value="<?= h($toDate) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">লেনদেন স্ট্যাটাস</label>
          <select name="status" class="form-select">
            <option value="">সব</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= h($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">প্রসেস স্ট্যাটাস</label>
          <select name="processed" class="form-select">
            <option value="">সব</option>
            <option value="yes" <?= $processed === 'yes' ? 'selected' : '' ?>>প্রসেস হয়েছে</option>
            <option value="no" <?= $processed === 'no' ? 'selected' : '' ?>>পেন্ডিং</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">প্রতি পৃষ্ঠা</label>
          <select name="limit" class="form-select">
            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-search"></i> ফিল্টার
          </button>
          <a href="?reset=1" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Events Table -->
  <div class="card shadow-sm border-top border-warning border-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-2"><i class="bi bi-cloud-arrow-down text-info"></i> RTN Webhook ইভেন্ট ডেটা</h6>
        <small class="text-muted">মোট: <strong><?= h((string)$totalRows) ?> টি</strong> | পৃষ্ঠা <strong><?= h((string)$page) ?></strong> / <strong><?= h((string)$totalPages) ?></strong></small>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size: 0.92rem;">
          <thead class="table-light sticky-top">
            <tr style="background-color: #f8f9fa;">
              <th style="width: 60px;" class="text-center"><strong>ID</strong></th>
              <th style="width: 135px;"><strong>পেমেন্ট তারিখ</strong></th>
              <th style="width: 140px;"><strong>লেনদেন ID</strong></th>
              <th style="width: 90px;" class="text-end"><strong>পরিমাণ</strong></th>
              <th style="width: 100px;"><strong>লেনদেন অবস্থা</strong></th>
              <th style="width: 120px;"><strong>গ্রাহক</strong></th>
              <th style="width: 90px;" class="text-center"><strong>প্রসেসিং</strong></th>
              <th style="width: 110px;"><strong>প্রয়োগ অবস্থা</strong></th>
              <th style="width: 110px;" class="text-center"><strong>আপডেট সময়</strong></th>
              <th style="width: 50px;" class="text-center"><strong>বিস্তারিত</strong></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$events): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-5">
                  <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.3;"></i><br>
                  <span>কোনো ওয়েবহুক ইভেন্ট পাওয়া যায়নি</span>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($events as $e): ?>
                <tr style="border-left: 4px solid <?= (string)($e['status'] ?? 'N/A') === 'COMPLETED' ? '#198754' : '#ffc107' ?>; vertical-align: middle;">
                  <td class="text-center"><strong class="text-primary">#<?= h((string)$e['id']) ?></strong></td>
                  <td>
                    <div class="mb-1"><?= h(date('d-M-y', strtotime((string)$e['received_at']))) ?></div>
                    <small class="text-muted"><?= h(date('H:i:s', strtotime((string)$e['received_at']))) ?></small>
                  </td>
                  <td>
                    <code class="bg-light p-1 rounded" style="font-size: 0.85rem;"><?= h((string)$e['trx_id']) ?></code>
                  </td>
                  <td class="text-end">
                    <strong class="text-success">৳<?= number_format((float)($e['amount'] ?? 0), 2) ?></strong>
                  </td>
                  <td>
                    <?php 
                      $statusClass = match((string)($e['status'] ?? '')) {
                        'COMPLETED', 'SUCCESS', 'CAPTURED' => 'success',
                        'PENDING', 'Initiated' => 'warning',
                        default => 'secondary'
                      };
                      $statusIcon = match((string)($e['status'] ?? '')) {
                        'COMPLETED', 'SUCCESS', 'CAPTURED' => '✓',
                        'PENDING', 'Initiated' => '⏳',
                        default => '○'
                      };
                    ?>
                    <span class="badge bg-<?= $statusClass ?>"><?= $statusIcon ?> <?= h((string)($e['status'] ?? 'N/A')) ?></span>
                  </td>
                  <td>
                    <?php if ($e['applied_client_id']): ?>
                      <a href="/client_detail.php?id=<?= h((string)$e['applied_client_id']) ?>" class="text-decoration-none text-dark fw-500" title="গ্রাহক প্রোফাইল দেখুন">
                        <strong><?= h((string)($e['client_name'] ?? 'N/A')) ?></strong>
                      </a>
                    <?php else: ?>
                      <span class="text-muted d-inline-block">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if ($e['processed']): ?>
                      <span class="badge bg-success">✓ সম্পন্ন</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">⏳ অপেক্ষা</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($e['applied_client_id'] && $e['applied_amount']): ?>
                      <div class="mb-1"><span class="badge bg-success">✓ প্রয়োগ হয়েছে</span></div>
                      <small class="text-muted">৳<?= number_format((float)$e['applied_amount'], 2) ?></small>
                    <?php elseif ($e['last_error']): ?>
                      <div class="mb-1"><span class="badge bg-danger">✗ ত্রুটি</span></div>
                      <small class="text-muted d-block" title="<?= h((string)$e['last_error']) ?>"><?= h(mb_substr((string)$e['last_error'], 0, 25)) ?></small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <small class="text-muted d-block"><?= h(date('d-M', strtotime((string)($e['processed_at'] ?? $e['received_at'])))) ?></small>
                    <small class="text-muted d-block"><?= h(date('H:i', strtotime((string)($e['processed_at'] ?? $e['received_at'])))) ?></small>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-info" title="বিস্তারিত দেখুন" data-bs-toggle="modal" data-bs-target="#detailModal" data-event-id="<?= h((string)$e['id']) ?>" onclick="loadEventDetail(<?= (int)$e['id'] ?>)">
                      <i class="bi bi-eye"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
      <ul class="pagination">
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?from_date=<?= h($fromDate) ?>&to_date=<?= h($toDate) ?>&status=<?= h($status) ?>&processed=<?= h($processed) ?>&page=1">প্রথম</a>
          </li>
          <li class="page-item">
            <a class="page-link" href="?from_date=<?= h($fromDate) ?>&to_date=<?= h($toDate) ?>&status=<?= h($status) ?>&processed=<?= h($processed) ?>&page=<?= $page-1 ?>">আগের</a>
          </li>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?from_date=<?= h($fromDate) ?>&to_date=<?= h($toDate) ?>&status=<?= h($status) ?>&processed=<?= h($processed) ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="?from_date=<?= h($fromDate) ?>&to_date=<?= h($toDate) ?>&status=<?= h($status) ?>&processed=<?= h($processed) ?>&page=<?= $page+1 ?>">পরের</a>
          </li>
          <li class="page-item">
            <a class="page-link" href="?from_date=<?= h($fromDate) ?>&to_date=<?= h($toDate) ?>&status=<?= h($status) ?>&processed=<?= h($processed) ?>&page=<?= $totalPages ?>">শেষ</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ইভেন্ট বিস্তারিত</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent">
        <div class="spinner-border" role="status"><span class="visually-hidden">লোড হচ্ছে...</span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
      </div>
    </div>
  </div>
</div>

<script>
function loadEventDetail(eventId) {
  const url = '/api/bkash_rtn/get_event_detail.php?id=' + eventId;
  fetch(url)
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        document.getElementById('detailContent').innerHTML = d.html;
        // Also populate the inline cards (if present) with identical content
        const left = document.getElementById('detailCardLeftBody');
        const right = document.getElementById('detailCardRightBody');
        if (left) left.innerHTML = d.html;
        if (right) right.innerHTML = d.html;
      } else {
        document.getElementById('detailContent').innerHTML = '<div class="alert alert-danger">বিস্তারিত লোড ব্যর্থ।</div>';
      }
    })
    .catch(() => {
      document.getElementById('detailContent').innerHTML = '<div class="alert alert-danger">ত্রুটি।</div>';
    });
}
</script>
<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>
