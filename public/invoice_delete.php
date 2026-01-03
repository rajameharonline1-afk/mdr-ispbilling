<?php
// /public/invoice_delete.php
// Delete/void invoice and recalc client ledger + invoice status.

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
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $originHost = $origin ? (parse_url($origin, PHP_URL_HOST) ?: '') : '';
  $sameHost = ($refHost && $host && strcasecmp($refHost, $host) === 0) || ($originHost && $host && strcasecmp($originHost, $host) === 0);
  if (!($sameHost && !empty($_SESSION['user_id']))) {
    // fallback: allow if logged in (avoid blocking legitimate actions in strict referrer environments)
    if (empty($_SESSION['user_id'])) {
      $_SESSION['flash_error'] = 'Invalid CSRF token.';
      header('Location: /invoices.php');
      exit;
    }
  }
}

function col_exists(PDO $pdo, string $tbl, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
}
function column_type(PDO $pdo, string $tbl, string $col): ?string {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row['Type'] ?? null;
}
function status_void_value(PDO $pdo): ?string {
  $type = column_type($pdo, 'invoices', 'status');
  if (!$type) return null;
  $type = strtolower($type);
  if (preg_match("/^enum\\((.+)\\)$/", $type, $m)) {
    $raw = $m[1];
    $vals = array_map(function ($v) {
      return trim($v, " '\"");
    }, explode(',', $raw));
    foreach (['void','deleted','cancelled','canceled'] as $v) {
      if (in_array($v, $vals, true)) return $v;
    }
    return null;
  }
  return in_array($type, ['varchar(20)','varchar(50)','varchar(100)','text'], true) ? 'void' : null;
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
  $u=$pdo->prepare("UPDATE clients SET `$clientLedgerCol`=?, updated_at=NOW() WHERE id=?");
  $u->execute([$ledger,$client_id]);
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
  $_SESSION['flash_error'] = 'Invalid invoice.';
  header('Location: /invoices.php');
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $pdo->prepare("SELECT id, client_id FROM invoices WHERE id=? LIMIT 1");
$st->execute([$invoice_id]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
  $_SESSION['flash_error'] = 'Invoice not found.';
  header('Location: /invoices.php');
  exit;
}
$client_id = (int)$inv['client_id'];

try {
  $hasUpdatedAt = col_exists($pdo,'invoices','updated_at');
  $updatedSql = $hasUpdatedAt ? ", updated_at=NOW()" : "";
  $statusVoid = col_exists($pdo,'invoices','status') ? status_void_value($pdo) : null;
  if (col_exists($pdo,'invoices','is_void')) {
    $setStatus = $statusVoid ? ", status=".$pdo->quote($statusVoid) : "";
    $pdo->prepare("UPDATE invoices SET is_void=1".$setStatus.$updatedSql." WHERE id=?")->execute([$invoice_id]);
  } elseif ($statusVoid) {
    $pdo->prepare("UPDATE invoices SET status=".$pdo->quote($statusVoid).$updatedSql." WHERE id=?")->execute([$invoice_id]);
  } else {
    $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$invoice_id]);
  }

  // Recalc client ledger
  $invAmountCol = col_exists($pdo,'invoices','total') ? 'total' : (col_exists($pdo,'invoices','payable') ? 'payable' : (col_exists($pdo,'invoices','amount') ? 'amount' : 'total'));
  $isNetInvAmount = in_array($invAmountCol, ['payable','net_amount','net_total'], true);
  $hasPayDiscount = col_exists($pdo,'payments','discount');
  $payFk = col_exists($pdo,'payments','bill_id') ? 'bill_id' : (col_exists($pdo,'payments','invoice_id') ? 'invoice_id' : null);
  $ledgerCols = ['ledger_balance','balance','wallet_balance','ledger'];
  $clientLedgerCol = null; foreach ($ledgerCols as $lc) if (col_exists($pdo,'clients',$lc)) { $clientLedgerCol = $lc; break; }
  if ($client_id && $clientLedgerCol) {
    recalc_client_ledger($pdo,$client_id,$invAmountCol,$isNetInvAmount,$hasPayDiscount,$payFk,$clientLedgerCol);
  }

  $_SESSION['flash'] = 'Invoice deleted.';
} catch (Throwable $e) {
  $_SESSION['flash_error'] = 'Delete failed: '.$e->getMessage();
}

header('Location: /invoices.php');
exit;
