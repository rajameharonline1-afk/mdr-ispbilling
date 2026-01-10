<?php
// /public/invoice_amount_update.php
// Update invoice amount and recalc invoice status + client ledger.

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf_compat.php';

// (বাংলা) সেশন টোকেন না থাকলে পোস্টেড টোকেন সিঙ্ক করে নাও
$given = csrf_request_token();
$stored = csrf_session_token();
if (!$stored && $given) {
  $_SESSION['csrf'] = $given;
  $_SESSION['csrf_token'] = $given;
}
if (!csrf_verify()) {
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $refHost = $ref ? (parse_url($ref, PHP_URL_HOST) ?: '') : '';
  if (!($refHost && $host && strcasecmp($refHost, $host) === 0 && !empty($_SESSION['user_id']))) {
    $_SESSION['flash_error'] = 'Invalid CSRF token.';
    header('Location: /invoices.php');
    exit;
  }
}

function col_exists(PDO $pdo, string $tbl, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
}
function payments_active_where(PDO $pdo, string $alias='pm'): string {
  $conds=[];
  if (col_exists($pdo,'payments','is_deleted')) $conds[]="$alias.is_deleted=0";
  if (col_exists($pdo,'payments','deleted_at')) $conds[]="$alias.deleted_at IS NULL";
  if (col_exists($pdo,'payments','is_active'))  $conds[]="$alias.is_active=1";
  if (col_exists($pdo,'payments','void'))       $conds[]="$alias.void=0";
  if (col_exists($pdo,'payments','status'))     $conds[]="COALESCE($alias.status,'') NOT IN ('deleted','void','cancelled')";
  return $conds ? (' AND '.implode(' AND ',$conds)) : '';
}
function recalc_invoice_status(PDO $pdo, int $invoice_id, string $invAmountCol, bool $isNetInvAmount, bool $hasPayDiscount, ?string $payFk): void {
  if (!$payFk) return;
  $active = payments_active_where($pdo,'pm');
  $sql = "
    SELECT i.id, COALESCE(i.`$invAmountCol`,0) AS inv_amount,
           (SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payFk`=i.id $active) AS paid_sum
           ".($hasPayDiscount? ", (SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i.id $active) AS disc_sum" : ", 0 AS disc_sum")."
    FROM invoices i WHERE i.id=? LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([$invoice_id]); $r=$st->fetch(PDO::FETCH_ASSOC); if(!$r) return;
  $inv=(float)$r['inv_amount']; $paid=(float)$r['paid_sum']; $disc=(float)$r['disc_sum'];
  $discUsed = $isNetInvAmount ? 0.0 : $disc;
  $remain = max(0.0, $inv - $discUsed - $paid);
  $status = ($remain<=0.0001)?'paid':(($paid>0)?'partial':'unpaid');
  $hasUpdatedAt = col_exists($pdo,'invoices','updated_at');
  $updatedSql = $hasUpdatedAt ? ", updated_at=NOW()" : "";
  $u=$pdo->prepare("UPDATE invoices SET status=?".$updatedSql." WHERE id=?");
  $u->execute([$status,$invoice_id]);
}
function recalc_client_ledger(PDO $pdo, int $client_id, string $invAmountCol, bool $isNetInvAmount, bool $hasPayDiscount, ?string $payFk, ?string $clientLedgerCol): void {
  if (!$clientLedgerCol) return;
  $st1=$pdo->prepare("SELECT COALESCE(SUM(i.`$invAmountCol`),0) FROM invoices i WHERE i.client_id=?");
  $st1->execute([$client_id]); $sumInv=(float)$st1->fetchColumn();
  $active = payments_active_where($pdo,'pm');
  if ($payFk) {
    $st2=$pdo->prepare("SELECT COALESCE(SUM(pm.amount),0), ".($hasPayDiscount?"COALESCE(SUM(pm.discount),0)":"0")."
                        FROM payments pm JOIN invoices i ON pm.`$payFk`=i.id
                        WHERE i.client_id=? $active");
    $st2->execute([$client_id]); [$sumPaid,$sumDisc]=array_map('floatval',$st2->fetch(PDO::FETCH_NUM) ?: [0,0]);
  } else {
    if (col_exists($pdo,'payments','client_id')) {
      $st2=$pdo->prepare("SELECT COALESCE(SUM(pm.amount),0), ".($hasPayDiscount?"COALESCE(SUM(pm.discount),0)":"0")."
                          FROM payments pm WHERE pm.client_id=? $active");
      $st2->execute([$client_id]); [$sumPaid,$sumDisc]=array_map('floatval',$st2->fetch(PDO::FETCH_NUM) ?: [0,0]);
    } else { $sumPaid=0.0; $sumDisc=0.0; }
  }
  $discUsed=$isNetInvAmount?0.0:$sumDisc;
  $ledger = -1 * ($sumInv - $discUsed - $sumPaid);
  $hasUpdatedAt = col_exists($pdo,'clients','updated_at');
  $updatedSql = $hasUpdatedAt ? ", updated_at=NOW()" : "";
  $u=$pdo->prepare("UPDATE clients SET `$clientLedgerCol`=?".$updatedSql." WHERE id=?");
  $u->execute([$ledger,$client_id]);
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
if ($invoice_id <= 0 || $amount <= 0) {
  $_SESSION['flash_error'] = 'Invalid invoice or amount.';
  header('Location: /invoices.php');
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find invoice + client
$st = $pdo->prepare("SELECT id, client_id FROM invoices WHERE id=? LIMIT 1");
$st->execute([$invoice_id]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
  $_SESSION['flash_error'] = 'Invoice not found.';
  header('Location: /public/invoices.php');
  exit;
}
$client_id = (int)$inv['client_id'];

// Amount columns to update
$amountCols = [];
foreach (['total','payable','amount','total_amount','subtotal','grand_total','net_total'] as $c) {
  if (col_exists($pdo,'invoices',$c)) $amountCols[] = $c;
}
if (!$amountCols) {
  $_SESSION['flash_error'] = 'No amount column found in invoices.';
  header('Location: /public/invoices.php');
  exit;
}

$sets=[]; $vals=[];
foreach ($amountCols as $c) { $sets[]="`$c`=?"; $vals[]=$amount; }
if (col_exists($pdo,'invoices','updated_at')) { $sets[]="updated_at=NOW()"; }
$vals[]=$invoice_id;

try {
  $pdo->prepare("UPDATE invoices SET ".implode(',', $sets)." WHERE id=?")->execute($vals);

  // Recalc status + ledger
  $invAmountCol = in_array('total',$amountCols,true) ? 'total' : (in_array('payable',$amountCols,true) ? 'payable' : $amountCols[0]);
  $isNetInvAmount = in_array($invAmountCol, ['payable','net_amount','net_total'], true);
  $hasPayDiscount = col_exists($pdo,'payments','discount');
  $payFk = col_exists($pdo,'payments','bill_id') ? 'bill_id' : (col_exists($pdo,'payments','invoice_id') ? 'invoice_id' : null);

  recalc_invoice_status($pdo, $invoice_id, $invAmountCol, $isNetInvAmount, $hasPayDiscount, $payFk);
  $ledgerCols = ['ledger_balance','balance','wallet_balance','ledger'];
  $clientLedgerCol = null; foreach ($ledgerCols as $lc) if (col_exists($pdo,'clients',$lc)) { $clientLedgerCol = $lc; break; }
  if ($client_id && $clientLedgerCol) {
    recalc_client_ledger($pdo,$client_id,$invAmountCol,$isNetInvAmount,$hasPayDiscount,$payFk,$clientLedgerCol);
  }

  $_SESSION['flash'] = 'Invoice amount updated.';
} catch (Throwable $e) {
  $_SESSION['flash_error'] = 'Update failed: '.$e->getMessage();
}

header('Location: /invoices.php');
exit;
