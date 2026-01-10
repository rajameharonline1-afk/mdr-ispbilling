<?php
// /api/bkash_rtn/get_event_detail.php
// API: Get detailed JSON for RTN event modal
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

header('Content-Type: application/json; charset=utf-8');

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid event ID']);
  exit;
}

$pdo = db();
$st = $pdo->prepare("
  SELECT 
    e.*,
    COALESCE(c.username, c.name) AS client_name,
    COALESCE(c.id) AS client_id,
    COALESCE(p.id) AS payment_id
  FROM bkash_rtn_events e
  LEFT JOIN clients c ON e.applied_client_id = c.id
  LEFT JOIN payments p ON p.txn_id = e.trx_id AND p.method='bkash'
  WHERE e.id = ?
");
$st->execute([$eventId]);
$event = $st->fetch(PDO::FETCH_ASSOC);

if (!$event) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'message' => 'Event not found']);
  exit;
}

// Build HTML detail
$html = '<div class="row g-3">';

// Event Header
$html .= '<div class="col-12"><h6 class="border-bottom pb-2">ইভেন্ট তথ্য</h6></div>';
$html .= '<div class="col-md-6"><strong>ইভেন্ট ID:</strong> #' . h((string)$event['id']) . '</div>';
$html .= '<div class="col-md-6"><strong>ধরন:</strong> ' . h((string)($event['event_type'] ?? 'N/A')) . '</div>';
$html .= '<div class="col-md-6"><strong>Event ID:</strong> <code>' . h((string)($event['event_id'] ?? 'N/A')) . '</code></div>';
$html .= '<div class="col-md-6"><strong>লেনদেন ID:</strong> <code>' . h((string)($event['trx_id'] ?? 'N/A')) . '</code></div>';
$html .= '<div class="col-md-6"><strong>লেনদেন স্ট্যাটাস:</strong> <span class="badge bg-info">' . h((string)($event['status'] ?? 'N/A')) . '</span></div>';
$html .= '<div class="col-md-6"><strong>পরিমাণ:</strong> <strong class="text-success">৳' . number_format((float)($event['amount'] ?? 0), 2) . '</strong></div>';

// Payer Info
$html .= '<div class="col-12 mt-3"><h6 class="border-bottom pb-2">পেয়ার তথ্য</h6></div>';
$html .= '<div class="col-md-6"><strong>মসিসদান:</strong> ' . h((string)($event['payer_msisdn'] ?? 'N/A')) . '</div>';
$html .= '<div class="col-md-6"><strong>রেফারেন্স:</strong> ' . h((string)($event['merchant_invoice_number'] ?? 'N/A')) . '</div>';

// Processing Info
$html .= '<div class="col-12 mt-3"><h6 class="border-bottom pb-2">প্রসেসিং তথ্য</h6></div>';
$html .= '<div class="col-md-6"><strong>গৃহীত:</strong> ' . h((string)$event['received_at']) . '</div>';
$html .= '<div class="col-md-6"><strong>প্রসেস স্ট্যাটাস:</strong> ';
if ($event['processed']) {
  $html .= '<span class="badge bg-success">✓ প্রসেস হয়েছে</span> ' . h((string)($event['processed_at'] ?? ''));
} else {
  $html .= '<span class="badge bg-warning text-dark">পেন্ডিং</span>';
}
$html .= '</div>';
$html .= '<div class="col-md-6"><strong>প্রচেষ্টা:</strong> ' . h((string)($event['process_attempts'] ?? 0)) . '</div>';

// Application Info
if ($event['applied_client_id']) {
  $html .= '<div class="col-12 mt-3"><h6 class="border-bottom pb-2">প্রয়োগ তথ্য</h6></div>';
  $html .= '<div class="col-md-6"><strong>গ্রাহক:</strong> <a href="/client_detail.php?id=' . (int)$event['client_id'] . '">' . h((string)($event['client_name'] ?? 'N/A')) . '</a></div>';
  $html .= '<div class="col-md-6"><strong>প্রয়োগ পরিমাণ:</strong> <strong>৳' . number_format((float)($event['applied_amount'] ?? 0), 2) . '</strong></div>';
  if ($event['payment_id']) {
    $html .= '<div class="col-md-6"><strong>পেমেন্ট ID:</strong> <a href="/payment_detail.php?id=' . h((string)$event['payment_id']) . '">#' . h((string)$event['payment_id']) . '</a></div>';
  }
}

// Error Info
if ($event['last_error']) {
  $html .= '<div class="col-12 mt-3"><h6 class="border-bottom pb-2 text-danger">ত্রুটি</h6></div>';
  $html .= '<div class="col-12"><div class="alert alert-danger mb-0"><small>' . h((string)$event['last_error']) . '</small></div></div>';
}

// Raw Payload (if available)
if (!empty($event['raw_body'])) {
  $payload = json_decode((string)$event['raw_body'], true);
  if ($payload) {
    $html .= '<div class="col-12 mt-3"><h6 class="border-bottom pb-2">Raw Payload</h6></div>';
    $html .= '<div class="col-12"><pre class="small mb-0" style="max-height: 200px; overflow-y: auto;">' . h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div>';
  }
}

$html .= '</div>';

echo json_encode(['ok' => true, 'html' => $html]);
