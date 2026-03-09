<?php
// /public/invoice_delete.php
// (বাংলা) ইনভয়েস ডিলিট: রিমার্ক + লক লগ করে তারপর হার্ড ডিলিট ও লেজার রিক্যাল্ক।

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
    // (বাংলা) রেফারার ব্লক থাকলেও যদি লগইন করা থাকে তাহলে এক্সেস দিই; না হলে ব্লক।
    if (empty($_SESSION['user_id'])) {
      $_SESSION['flash_error'] = 'Invalid CSRF token.';
      header('Location: /invoices.php');
      exit;
    }
  }
}

function col_exists(PDO $pdo, string $tbl, string $col): bool {
  try{
    $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetchColumn();
  }catch(Throwable $e){ return false; }
}
function add_col_if_missing(PDO $pdo, string $tbl, string $colDef): void {
  preg_match('/`([^`]+)`/',$colDef,$m);
  $col = $m[1] ?? null;
  if (!$col || col_exists($pdo,$tbl,$col)) return;
  try { $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN $colDef"); } catch(Throwable $e) {}
}
function ensure_audit_logs_schema(PDO $pdo): void {
  try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS `audit_logs`(
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `entity` VARCHAR(64) NULL,
        `entity_id` BIGINT NULL,
        `action` VARCHAR(64) NULL,
        `meta` LONGTEXT NULL,
        `new_json` LONGTEXT NULL,
        `user_id` BIGINT NULL,
        `ip` VARCHAR(45) NULL,
        `user_agent` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    add_col_if_missing($pdo,'audit_logs',"`entity` VARCHAR(64) NULL");
    add_col_if_missing($pdo,'audit_logs',"`entity_id` BIGINT NULL");
    add_col_if_missing($pdo,'audit_logs',"`action` VARCHAR(64) NULL");
    add_col_if_missing($pdo,'audit_logs',"`meta` LONGTEXT NULL");
    add_col_if_missing($pdo,'audit_logs',"`new_json` LONGTEXT NULL");
    add_col_if_missing($pdo,'audit_logs',"`user_id` BIGINT NULL");
    add_col_if_missing($pdo,'audit_logs',"`ip` VARCHAR(45) NULL");
    add_col_if_missing($pdo,'audit_logs',"`user_agent` VARCHAR(255) NULL");
    add_col_if_missing($pdo,'audit_logs',"`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
  } catch(Throwable $e) { /* ignore schema bootstrap error */ }
}
function current_user_id(): int {
  foreach ([
    $_SESSION['user']['id'] ?? null,
    $_SESSION['user_id'] ?? null,
    $_SESSION['SESS_USER_ID'] ?? null,
  ] as $v) {
    $id = (int)$v;
    if ($id > 0) return $id;
  }
  return 0;
}
function client_ip(): string {
  foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) {
      $ip = explode(',', $_SERVER[$k])[0];
      return trim($ip);
    }
  }
  return '';
}
function log_invoice_delete_lock(PDO $pdo, int $invoice_id, int $client_id, string $lock_code, string $remarks): void {
  $payload = [
    'lock' => $lock_code,
    'remarks' => $remarks,
    'invoice_id' => $invoice_id,
    'client_id' => $client_id,
    'user_id' => current_user_id(),
    'ip' => client_ip(),
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    'at' => date('c'),
  ];
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $cols = ['entity','entity_id','action'];
  $vals = ['invoice',$invoice_id,'invoice_delete_lock'];

  if (col_exists($pdo,'audit_logs','new_json')) {
    $cols[]='new_json'; $vals[]=$json;
  } elseif (col_exists($pdo,'audit_logs','meta')) {
    $cols[]='meta'; $vals[]=$json;
  } else {
    $cols[]='remarks'; $vals[]=$json;
  }
  if (col_exists($pdo,'audit_logs','user_id')) { $cols[]='user_id'; $vals[]=$payload['user_id'] ?: null; }
  if (col_exists($pdo,'audit_logs','ip')) { $cols[]='ip'; $vals[]=$payload['ip']; }
  if (col_exists($pdo,'audit_logs','user_agent')) { $cols[]='user_agent'; $vals[]=$payload['ua']; }
  if (col_exists($pdo,'audit_logs','created_at')) { $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s'); }

  $ph = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO audit_logs (".implode(',', $cols).") VALUES ($ph)";
  $st = $pdo->prepare($sql);
  $st->execute($vals);
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
  $invActive = invoices_active_where($pdo,'i');
  $st1=$pdo->prepare("SELECT COALESCE(SUM(i.`$invAmountCol`),0) FROM invoices i WHERE i.client_id=?".$invActive);
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
function invoices_active_where(PDO $pdo, string $alias='i'): string {
  $c=[];
  if (col_exists($pdo,'invoices','is_void'))    $c[]="$alias.is_void=0";
  if (col_exists($pdo,'invoices','is_deleted')) $c[]="$alias.is_deleted=0";
  if (col_exists($pdo,'invoices','deleted_at')) $c[]="$alias.deleted_at IS NULL";
  if (col_exists($pdo,'invoices','status'))     $c[]="COALESCE($alias.status,'') NOT IN ('void','deleted','cancelled','canceled')";
  return $c ? (' AND '.implode(' AND ',$c)) : '';
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
  $_SESSION['flash_error'] = 'Invalid invoice.';
  header('Location: /invoices.php');
  exit;
}
$remarks = trim((string)($_POST['remarks'] ?? ''));
$remarks = $remarks !== '' ? (function_exists('mb_substr') ? mb_substr($remarks, 0, 500) : substr($remarks, 0, 500)) : '';
if ($remarks === '') {
  $_SESSION['flash_error'] = 'Remarks প্রয়োজন।';
  header('Location: /invoices.php');
  exit;
}
$lock_code = 'INV-DEL-' . strtoupper(bin2hex(random_bytes(4)));

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ensure_audit_logs_schema($pdo);

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
  $pdo->beginTransaction();

  // (বাংলা) ডিলিটের আগে লক + রিমার্ক অডিটে লগ
  log_invoice_delete_lock($pdo, $invoice_id, $client_id, $lock_code, $remarks);

  // (বাংলা) হার্ড ডিলিট: চাইল্ড টেবিল আগে, পরে ইনভয়েস সারি
  if (col_exists($pdo, 'invoice_items', 'invoice_id')) {
    $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$invoice_id]);
  }
  if (col_exists($pdo, 'payments', 'invoice_id')) {
    $pdo->prepare("DELETE FROM payments WHERE invoice_id=?")->execute([$invoice_id]);
  }
  if (col_exists($pdo, 'payments', 'bill_id')) {
    $pdo->prepare("DELETE FROM payments WHERE bill_id=?")->execute([$invoice_id]);
  }
  $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$invoice_id]);

  // (বাংলা) ক্লায়েন্ট লেজার আপডেট
  $invAmountCol = col_exists($pdo,'invoices','total') ? 'total' : (col_exists($pdo,'invoices','payable') ? 'payable' : (col_exists($pdo,'invoices','amount') ? 'amount' : 'total'));
  $isNetInvAmount = in_array($invAmountCol, ['payable','net_amount','net_total'], true);
  $hasPayDiscount = col_exists($pdo,'payments','discount');
  $payFk = col_exists($pdo,'payments','bill_id') ? 'bill_id' : (col_exists($pdo,'payments','invoice_id') ? 'invoice_id' : null);
  $ledgerCols = ['ledger_balance','balance','wallet_balance','ledger'];
  $clientLedgerCol = null; foreach ($ledgerCols as $lc) if (col_exists($pdo,'clients',$lc)) { $clientLedgerCol = $lc; break; }
  if ($client_id && $clientLedgerCol) {
    recalc_client_ledger($pdo,$client_id,$invAmountCol,$isNetInvAmount,$hasPayDiscount,$payFk,$clientLedgerCol);
  }

  $pdo->commit();
  $_SESSION['flash'] = json_encode([
    'type' => 'success',
    'title'=> 'Deleted',
    'message' => 'Invoice removed successfully. Lock: '.$lock_code
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = json_encode([
    'type' => 'error',
    'title'=> 'Delete failed',
    'message' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

header('Location: /invoices.php');
exit;
