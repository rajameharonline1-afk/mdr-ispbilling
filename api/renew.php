<?php
// /api/renew.php
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/audit.php';            // আগেই বানানো হেল্পার
// invoice_calc.php থাকলে include (না থাকলে ইগনোর)
$calc_path = __DIR__ . '/../app/invoice_calc.php';
if (file_exists($calc_path)) require_once $calc_path;

header('Content-Type: application/json');

function json_out($arr){ echo json_encode($arr); exit; }

$input = $_POST;
if (empty($input)) {
  // JSON body সাপোর্ট
  $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$client_id   = (int)($input['client_id'] ?? 0);
$months      = max(1, (int)($input['months'] ?? 1));
$start_on    = trim($input['start_on'] ?? '');       // YYYY-MM-DD | empty = auto
$mark_paid   = (int)($input['mark_paid'] ?? 0);      // 1 হলে সাথে সাথে paid
$pay_method  = trim($input['pay_method'] ?? '');     // Cash/bKash/...
$notes       = trim($input['notes'] ?? '');

if (!$client_id) json_out(['status'=>'error','message'=>'Invalid client_id']);

try{
  // Helper: column exists?
  $pdo = db();
  $col_exists = function(string $tbl, string $col) use ($pdo): bool {
    try {
      $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
      $st->execute([$tbl, $col]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
  };

  // Load client
  $st = $pdo->prepare("SELECT c.*, p.price AS pkg_price
                       FROM clients c
                       LEFT JOIN packages p ON p.id = c.package_id
                       WHERE c.id=? LIMIT 1");
  $st->execute([$client_id]);
  $c = $st->fetch(PDO::FETCH_ASSOC);
  if (!$c) json_out(['status'=>'error','message'=>'Client not found']);

  $monthly_bill = (float)($c['monthly_bill'] ?? 0);
  if (!$monthly_bill) $monthly_bill = (float)($c['pkg_price'] ?? 0);

  if ($monthly_bill <= 0) json_out(['status'=>'error','message'=>'Monthly bill not set for this client/package']);

  // Period calc
  $today = new DateTimeImmutable('today');
  if ($start_on && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_on)) {
    $period_start = new DateTimeImmutable($start_on);
  } else {
    // Auto: যদি আগের expiry_date আজ/ভবিষ্যৎ, তাহলে পরদিন থেকে; নাহলে আজ
    $exp = !empty($c['expiry_date']) ? new DateTimeImmutable($c['expiry_date']) : null;
    if ($exp && $exp >= $today) {
      $period_start = $exp->modify('+1 day');
    } else {
      $period_start = $today;
    }
  }
  // end = start + months - 1 day
  $period_end = $period_start->modify("+{$months} months")->modify('-1 day');

  // Amount (simple): months * monthly_bill (প্রোরেশন লাগলে invoice_calc.php ব্যবহার করুন)
  $amount = round($months * $monthly_bill, 2);

  // Invoice no generate: INV-YYYYMM-XXXX
  $ym = (new DateTime())->format('Ym');
  $invCol = null;
  if ($col_exists('invoices','invoice_number')) $invCol = 'invoice_number';
  elseif ($col_exists('invoices','invoice_no')) $invCol = 'invoice_no';
  elseif ($col_exists('invoices','number')) $invCol = 'number';
  else json_out(['status'=>'error','message'=>'Invoices table missing invoice number column']);

  $st = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE `$invCol` LIKE ?");
  $st->execute(["INV-$ym-%"]);
  $seq = (int)$st->fetchColumn() + 1;
  $invoice_no = sprintf('INV-%s-%04d', $ym, $seq);

  // Build dynamic insert based on available columns
  $cols = ['client_id', $invCol, 'months', 'amount', 'status'];
  $vals = [':cid', ':inv', ':m', ':amt', "'unpaid'"];
  $params = [
    ':cid' => $client_id,
    ':inv' => $invoice_no,
    ':m'   => $months,
    ':amt' => $amount,
  ];

  $period_start_str = $period_start->format('Y-m-d');
  $period_end_str   = $period_end->format('Y-m-d');
  $billing_month    = $period_start->format('Y-m-01');

  $addCol = function(string $col, $val, string $ph = null) use (&$cols, &$vals, &$params, $col_exists) {
    if (!$col_exists('invoices', $col)) return;
    $cols[] = $col;
    if ($ph) { $vals[] = $ph; $params[$ph] = $val; }
    else { $vals[] = $val; }
  };

  $addCol('period_start', $period_start_str, ':ps');
  $addCol('period_end',   $period_end_str,   ':pe');
  $addCol('billing_month',$billing_month,    ':bm');
  $addCol('invoice_date', $period_start_str, ':idate');
  $addCol('due_date',     $period_end_str,   ':ddate');
  $addCol('subtotal',     $amount,           ':subtotal');
  $addCol('discount',     0,                 ':disc');
  $addCol('vat_percent',  0,                 ':vatp');
  $addCol('vat_amount',   0,                 ':vata');
  $addCol('total',        $amount,           ':total');
  $addCol('payable',      $amount,           ':payable');
  $addCol('total_amount', $amount,           ':tamt');
  $addCol('paid_amount',  0,                 ':paidamt');
  $addCol('notes',        $notes ?: null,    ':notes');
  $addCol('created_at',   'NOW()',           null); // use NOW() literal if column exists

  $cols_sql = implode(',', array_map(fn($c)=>"`$c`", $cols));
  $vals_sql = implode(',', array_map(function($v){ return $v === 'NOW()' ? 'NOW()' : $v; }, $vals));

  $sql = "INSERT INTO invoices ($cols_sql) VALUES ($vals_sql)";
  $ins = $pdo->prepare($sql);
  $ok  = $ins->execute($params);
  if (!$ok) json_out(['status'=>'error','message'=>'Invoice create failed']);

  $invoice_id = (int)$pdo->lastInsertId();

  // Mark paid (optional)
  if ($mark_paid) {
    // payments insert
    $p = $pdo->prepare("INSERT INTO payments (invoice_id, client_id, amount, method, ref_no, paid_at, notes)
                        VALUES (:iid, :cid, :amt, :m, :ref, NOW(), :n)");
    $p->execute([
      ':iid'=>$invoice_id, ':cid'=>$client_id, ':amt'=>$amount,
      ':m'=> ($pay_method ?: null), ':ref'=> null, ':n'=>$notes ?: null
    ]);
    // invoice status update
    db()->prepare("UPDATE invoices SET status='paid', paid_at=NOW() WHERE id=?")->execute([$invoice_id]);
  }

  // Update client expiry_date
  $pdo->prepare("UPDATE clients SET expiry_date=? WHERE id=?")
    ->execute([$period_end->format('Y-m-d'), $client_id]);

  // Audit log (app/audit.php signature: audit_log(entity, entity_id, action, old|null, new|null))
  try {
    audit_log('client', $client_id, 'renew', null, [
      'invoice_id'   => $invoice_id,
      'invoice_no'   => $invoice_no,
      'months'       => $months,
      'amount'       => $amount,
      'period_from'  => $period_start->format('Y-m-d'),
      'period_to'    => $period_end->format('Y-m-d'),
      'mark_paid'    => (bool)$mark_paid,
      'pay_method'   => $pay_method ?: null,
    ]);
  } catch (Throwable $e) {
    // বাংলা: অডিট ব্যর্থ হলেও রিনিউ থামাবে না
  }

  json_out([
    'status' => 'success',
    'message'=> 'Renew successful',
    'invoice'=> [
      'id'          => $invoice_id,
      'invoice_no'  => $invoice_no,
      'amount'      => $amount,
      'status'      => $mark_paid ? 'paid' : 'unpaid',
      'period_from' => $period_start->format('Y-m-d'),
      'period_to'   => $period_end->format('Y-m-d')
    ],
    'new_expiry' => $period_end->format('Y-m-d')
  ]);

}catch(Exception $e){
  json_out(['status'=>'error','message'=>$e->getMessage()]);
}
