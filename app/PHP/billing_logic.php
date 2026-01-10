<?php
// /public/billing.php
// Month-wise Billing Dashboard
// Views: Summary (zone-wise) + List (All/Paid/Due) + Search + Pagination + CSV
// Eye → client_ledger.php; Pay → payment_add.php (always enabled)
// UI English; Bangla comments only

declare(strict_types=1);

require_once __DIR__ . '/../require_login.php';
require_once __DIR__ . '/../db.php';

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/* ---------- Small helpers ---------- */
// (বাংলা) টেবিল/কলাম/স্কেলার
function tbl_exists(PDO $pdo,string $t):bool{
  try{ $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $q->execute([$db,$t]); return (bool)$q->fetchColumn();
  }catch(Throwable){ return false; }
}
function col_exists(PDO $pdo,string $t,string $c):bool{
  try{ $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$db,$t,$c]); return (bool)$q->fetchColumn();
  }catch(Throwable){ return false; }
}
function pdo_scalar(PDO $pdo,string $sql,array $p=[]){
  $st=$pdo->prepare($sql); $st->execute($p); return $st->fetchColumn();
}
function find_invoice_vat_col(PDO $pdo): ?string {
  $candidates = ['vat','vat_amount','tax','tax_amount','vat_total','tax_total'];
  foreach ($candidates as $c) if (col_exists($pdo,'invoices',$c)) return $c;
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $q  = $pdo->prepare("
      SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=? AND TABLE_NAME='invoices' AND COLUMN_NAME LIKE '%vat%'
      LIMIT 1
    ");
    $q->execute([$db]);
    $col = $q->fetchColumn();
    return $col ? (string)$col : null;
  } catch (Throwable) { return null; }
}
function pick_col(PDO $pdo, string $table, array $cands): string {
  foreach ($cands as $c) { if (col_exists($pdo, $table, $c)) return $c; }
  return '';
}
function distinct_values(PDO $pdo, string $table, string $col): array {
  try {
    $st = $pdo->query("SELECT DISTINCT `$col` AS v FROM `$table` WHERE `$col` IS NOT NULL AND `$col`<>'' ORDER BY `$col` ASC");
    return array_values(array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN))));
  } catch (Throwable $e) {
    return [];
  }
}
// (বাংলা) Payments-এ active ফিল্টার (soft-delete/void বাদ)
function payments_active_where(PDO $pdo, string $alias='pm'): string {
  $c=[];
  if (col_exists($pdo,'payments','is_deleted')) $c[]="$alias.is_deleted=0";
  if (col_exists($pdo,'payments','deleted_at')) $c[]="$alias.deleted_at IS NULL";
  if (col_exists($pdo,'payments','void'))       $c[]="$alias.void=0";
  if (col_exists($pdo,'payments','status'))     $c[]="COALESCE($alias.status,'') NOT IN ('deleted','void','cancelled')";
  return $c ? (' AND '.implode(' AND ',$c)) : '';
}
// (বাংলা) invoices active filter (void/deleted বাদ)
function invoices_active_where(PDO $pdo, string $alias='i'): string {
  $c=[];
  if (col_exists($pdo,'invoices','is_void'))    $c[]="$alias.is_void=0";
  if (col_exists($pdo,'invoices','is_deleted'))$c[]="$alias.is_deleted=0";
  if (col_exists($pdo,'invoices','deleted_at'))$c[]="$alias.deleted_at IS NULL";
  if (col_exists($pdo,'invoices','status'))    $c[]="COALESCE($alias.status,'') NOT IN ('void','deleted','cancelled','canceled')";
  return $c ? (' AND '.implode(' AND ',$c)) : '';
}
// (বাংলা) invoices টেবিলে ডিসকাউন্ট কলাম অটো-ডিটেক্ট
function find_invoice_discount_col(PDO $pdo): ?string {
  $candidates = [
    'discount','bill_discount','discount_amount','disc','inv_discount',
    'pdiscount','p_discount','prev_discount','previous_discount',
    'package_discount','plan_discount','promo_discount'
  ];
  foreach ($candidates as $c) if (col_exists($pdo,'invoices',$c)) return $c;
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $q  = $pdo->prepare("
      SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=? AND TABLE_NAME='invoices' AND COLUMN_NAME LIKE '%discount%'
      LIMIT 1
    ");
    $q->execute([$db]);
    $col = $q->fetchColumn();
    return $col ? (string)$col : null;
  } catch (Throwable) { return null; }
}

/* ---------- DB ---------- */
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- Schema detection ---------- */
$hasInvMonth     = col_exists($pdo,'invoices','month');
$hasInvYear      = col_exists($pdo,'invoices','year');
$hasInvBillMonth = col_exists($pdo,'invoices','billing_month');

$payFk = col_exists($pdo,'payments','invoice_id') ? 'invoice_id'
       : (col_exists($pdo,'payments','bill_id') ? 'bill_id' : null);
$hasPayClientId  = col_exists($pdo,'payments','client_id');
$hasPayDiscount  = col_exists($pdo,'payments','discount');

$invAmountCol = col_exists($pdo,'invoices','payable') ? 'payable'
             : (col_exists($pdo,'invoices','net_amount') ? 'net_amount'
             : (col_exists($pdo,'invoices','amount') ? 'amount'
             : (col_exists($pdo,'invoices','total')  ? 'total'  : 'total')));
$invStatusCol = col_exists($pdo,'invoices','status') ? 'status' : null;
// (বাংলা) যদি invAmountCol net হয়, তবে ডিসকাউন্ট already applied ধরা হবে
$isNetInvAmount = in_array($invAmountCol, ['payable','net_amount','net_total'], true);

$invoiceDiscCol   = find_invoice_discount_col($pdo);
$invoiceVatCol    = find_invoice_vat_col($pdo);
$showDiscountCol  = $hasPayDiscount || (bool)$invoiceDiscCol;

$ledgerCols = ['ledger_balance','balance','wallet_balance','ledger'];
$clientLedgerExpr = '0';
foreach ($ledgerCols as $lc) if (col_exists($pdo,'clients',$lc)) { $clientLedgerExpr = "c.`$lc`"; break; }

$clientMobileCol = null; foreach (['mobile','phone','cell','contact'] as $mc) if (col_exists($pdo,'clients',$mc)) { $clientMobileCol = $mc; break; }
$hasPackages = tbl_exists($pdo,'packages') && col_exists($pdo,'clients','package_id') && col_exists($pdo,'packages','id');
$hasPkgName  = $hasPackages && col_exists($pdo,'packages','name');
$payDateCol  = col_exists($pdo,'payments','payment_date') ? 'payment_date'
            : (col_exists($pdo,'payments','paid_at') ? 'paid_at'
            : (col_exists($pdo,'payments','created_at') ? 'created_at' : null));
$invoicePaidCol = col_exists($pdo,'invoices','paid_amount') ? 'paid_amount' : null;
$clientLastPayCol = col_exists($pdo,'clients','last_payment_date') ? 'last_payment_date' : null;

$routerNameParts = [];
if (tbl_exists($pdo,'routers')) {
  foreach (['name','identity','ip','host'] as $c) if (col_exists($pdo,'routers',$c)) $routerNameParts[] = "r.`$c`";
}
$ROUTER_NAME_EXPR = $routerNameParts ? ('COALESCE('.implode(',', $routerNameParts).')') : 'NULL';

/* ---------- Filter columns (clients/packages) ---------- */
$AREA_COL        = pick_col($pdo, 'clients', ['area','zone','location']);
$SUB_ZONE_COL    = pick_col($pdo, 'clients', ['sub_zone','subzone','sub_area']);
$BOX_COL         = pick_col($pdo, 'clients', ['box','distribution_box','box_name']);
$PROFILE_COL     = pick_col($pdo, 'clients', ['profile','pppoe_profile','profile_name','mt_profile']);
$B_STATUS_COL    = pick_col($pdo, 'clients', ['billing_status','payment_status']);
$hasArea    = ($AREA_COL !== '');
$hasSubZone = ($SUB_ZONE_COL !== '');
$hasBox     = ($BOX_COL !== '');
$hasLeftCol = col_exists($pdo, 'clients', 'is_left');
$hasStatusCol = col_exists($pdo, 'clients', 'status');

/* ---------- Inputs ---------- */
$month    = trim($_GET['month'] ?? date('Y-m'));
$search   = trim($_GET['search'] ?? '');
$tab      = strtolower(trim($_GET['tab'] ?? 'all'));        // all|paid|due
$view     = strtolower(trim($_GET['view'] ?? 'summary'));   // summary|list
$page     = max(1,(int)($_GET['page']??1));
$limit    = max(1, (int)($_GET['limit'] ?? 20));
$export   = isset($_GET['export']) && $_GET['export']==='csv';

$EXPIRY_COL = pick_col($pdo, 'clients', ['expiry_date','expire_date']);
$MONTHLY_BILL_COL = pick_col($pdo, 'clients', ['monthly_bill','monthly_bill_amount','bill_amount','monthly_bill_tk']);
$ADVANCE_COL = pick_col($pdo, 'clients', ['advance','advance_balance','advance_amount','prepaid','wallet_advance']);

/* ---- Advanced filters (list view) ---- */
$package_id = (int)($_GET['package_id'] ?? 0);
$router_id  = (int)($_GET['router_id']  ?? 0);
$zone       = trim($_GET['zone'] ?? '');
$area       = trim($_GET['area'] ?? '');
if ($zone === '' && $area !== '') $zone = $area;
$sub_zone   = trim($_GET['sub_zone'] ?? '');
$box        = trim($_GET['box'] ?? '');
$b_status   = strtolower(trim($_GET['b_status'] ?? ''));
$custom_status = strtolower(trim($_GET['custom_status'] ?? ''));
if (!in_array($custom_status, ['active','inactive'], true)) {
  $custom_status = '';
}

/* Normalize month (সবসময় শুরু/শেষ তারিখ) */
$monthParam = preg_match('/^\d{4}-\d{2}$/',$month)?$month:date('Y-m');
[$yr,$mo] = array_map('intval', explode('-', $monthParam));
$date_start = $monthParam.'-01';
$date_end   = date('Y-m-t', strtotime($date_start));

/* invoices-এর জন্য month WHERE */
$hasInvDate = col_exists($pdo,'invoices','invoice_date');
$useInvoiceDate = false;
if ($hasInvBillMonth && $hasInvDate) {
  $cntMonth = (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE billing_month BETWEEN ? AND ?", [$date_start, $date_end]);
  if ($cntMonth === 0) $useInvoiceDate = true;
}
if ($useInvoiceDate) {
  $params_base = [$date_start,$date_end];
  $whereMonth = "invoice_date BETWEEN ? AND ?";
} elseif ($hasInvBillMonth) {
  $params_base = [$date_start,$date_end];
  $whereMonth = "billing_month BETWEEN ? AND ?";
} elseif ($hasInvMonth && $hasInvYear) {
  $params_base = [$mo,$yr];
  $whereMonth = "month=? AND year=?";
} else {
  $params_base = [];
  $whereMonth = "1=1";
}

/* Billing period expression for active/inactive (best-effort) */
$period_start_sql = $pdo->quote($date_start);
$period_end_sql   = $pdo->quote($date_end);
if ($hasInvDate) {
  $period_expr = "i.invoice_date BETWEEN $period_start_sql AND $period_end_sql";
} elseif ($hasInvBillMonth) {
  $period_expr = "i.billing_month BETWEEN $period_start_sql AND $period_end_sql";
} elseif ($hasInvMonth && $hasInvYear) {
  $period_expr = "i.month = $mo AND i.year = $yr";
} else {
  $period_expr = "i.id IS NOT NULL";
}

/* ===================== Helper: Max invoice up-to-month-end ===================== */
/* (বাংলা) carryover due ধরতে “<= month-end” পর্যন্ত সর্বশেষ ইনভয়েস নেব */
if ($useInvoiceDate) {
  $rangeUpTo = "WHERE invoice_date <= ?";
  $params_upto = [$date_end];
} elseif ($hasInvBillMonth) {
  $rangeUpTo = "WHERE billing_month <= ?";
  $params_upto = [$date_end];
} elseif ($hasInvMonth && $hasInvYear) {
  // (year < yr) OR (year = yr AND month <= mo)
  $rangeUpTo = "WHERE (year < ?) OR (year = ? AND month <= ?)";
  $params_upto = [$yr, $yr, $mo];
} else {
  $rangeUpTo = "";
  $params_upto = [];
}

/* =========================================================
   SUMMARY (ZONE/AREA-WISE)
   ========================================================= */
$zonesData = [];
$summaryError = null;
$z_tot_gen=$z_tot_col=$z_tot_dis=$z_tot_due=0.0;

if ($view === 'summary') {
  $zoneMeta = ['type'=>'single','key_col'=>null,'name_col'=>null,'table'=>null];
  if (col_exists($pdo,'clients','zone_id') && tbl_exists($pdo,'zones') && col_exists($pdo,'zones','id') && col_exists($pdo,'zones','name')) {
    $zoneMeta = ['type'=>'fk','key_col'=>'zone_id','name_col'=>'name','table'=>'zones'];
  } elseif (col_exists($pdo,'clients','area_id') && tbl_exists($pdo,'areas') && col_exists($pdo,'areas','id') && col_exists($pdo,'areas','name')) {
    $zoneMeta = ['type'=>'fk','key_col'=>'area_id','name_col'=>'name','table'=>'areas'];
  } elseif (col_exists($pdo,'clients','zone')) { $zoneMeta = ['type'=>'text','key_col'=>'zone','name_col'=>'zone','table'=>null];
  } elseif (col_exists($pdo,'clients','area')) { $zoneMeta = ['type'=>'text','key_col'=>'area','name_col'=>'area','table'=>null]; }

  $activeParts=[];
  if (col_exists($pdo,'clients','is_active')) $activeParts[]="COALESCE(c.is_active,0)=1";
  if (col_exists($pdo,'clients','is_left'))   $activeParts[]="COALESCE(c.is_left,0)=0";
  if (col_exists($pdo,'clients','status'))    $activeParts[]="COALESCE(c.status,'') NOT IN ('inactive','left','terminated','suspended')";
  $activeExpr = $activeParts ? implode(' AND ', $activeParts) : '1=1';

  try {
    if ($zoneMeta['type']==='fk') {
      $sql = "
        SELECT c.`{$zoneMeta['key_col']}` AS zone_key, z.`{$zoneMeta['name_col']}` AS zone_name,
               COUNT(*) total_clients,
               SUM(CASE WHEN ($activeExpr) THEN 1 ELSE 0 END) active_clients,
               SUM(CASE WHEN NOT ($activeExpr) THEN 1 ELSE 0 END) inactive_clients
        FROM clients c LEFT JOIN `{$zoneMeta['table']}` z ON z.id=c.`{$zoneMeta['key_col']}`
        GROUP BY c.`{$zoneMeta['key_col']}`, z.`{$zoneMeta['name_col']}` ORDER BY z.`{$zoneMeta['name_col']}` ASC";
      $rowsZ = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($zoneMeta['type']==='text') {
      $col = $zoneMeta['key_col'];
      $sql = "
        SELECT COALESCE(c.`$col`,'Unassigned') zone_key, COALESCE(c.`$col`,'Unassigned') zone_name,
               COUNT(*) total_clients,
               SUM(CASE WHEN ($activeExpr) THEN 1 ELSE 0 END) active_clients,
               SUM(CASE WHEN NOT ($activeExpr) THEN 1 ELSE 0 END) inactive_clients
        FROM clients c GROUP BY COALESCE(c.`$col`,'Unassigned') ORDER BY zone_name ASC";
      $rowsZ = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
      $sql = "SELECT NULL zone_key,'All Clients' zone_name, COUNT(*) total_clients,
              SUM(CASE WHEN ($activeExpr) THEN 1 ELSE 0 END) active_clients,
              SUM(CASE WHEN NOT ($activeExpr) THEN 1 ELSE 0 END) inactive_clients
              FROM clients c";
      $rowsZ = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    $payActive = payments_active_where($pdo,'pm');

    /* payments join plan */
    $payJoin = '';
    if ($payFk)              $payJoin = "JOIN invoices i ON pm.`$payFk`=i.id JOIN clients c ON c.id=i.client_id";
    elseif ($hasPayClientId) $payJoin = "JOIN clients c ON c.id=pm.client_id";
    else                     $payJoin = null;

    /* Generated (only this month) */
    $sqlGen = "SELECT COALESCE(SUM(i.`$invAmountCol`),0)
               FROM invoices i JOIN clients c ON c.id=i.client_id
               WHERE i.$whereMonth AND %ZONECOND%";

    /* Discount aggregate: payments + invoices (দুটোই) */
    $sqlDisPay = null; $sqlDisInv = null;
    if ($payJoin && $hasPayDiscount) {
      $sqlDisPay = "SELECT COALESCE(SUM(pm.discount),0)
                    FROM payments pm $payJoin
                    WHERE ".($payDateCol ? "pm.`$payDateCol` BETWEEN ? AND ?" : "1=1")." $payActive AND %ZONECOND%";
    }
    if ($invoiceDiscCol) {
      $sqlDisInv = "SELECT COALESCE(SUM(i.`$invoiceDiscCol`),0)
                    FROM invoices i JOIN clients c ON c.id=i.client_id
                    WHERE i.$whereMonth AND %ZONECOND%";
    }

    /* Collection (payments, this month) */
    $sqlCol = null;
    if ($payJoin) {
      $sqlCol = "SELECT COALESCE(SUM(pm.amount),0)
                 FROM payments pm $payJoin
                 WHERE ".($payDateCol ? "pm.`$payDateCol` BETWEEN ? AND ?" : "1=1")." $payActive AND %ZONECOND%";
    }

    /* Due of this month (all-time payments against invoice) */
    $discForDue = "0";
    if (!$isNetInvAmount) {
      if ($hasPayDiscount) {
        $discForDue = "(SELECT COALESCE(SUM(pm2.discount),0) FROM payments pm2 WHERE ".($payFk?"pm2.`$payFk`=i.id":"pm2.client_id=i.client_id")." ".payments_active_where($pdo,'pm2').")";
      } elseif ($invoiceDiscCol) {
        $discForDue = "COALESCE(i.`$invoiceDiscCol`,0)";
      }
    }
    $sqlDue = "SELECT COALESCE(SUM(GREATEST(0,
                 COALESCE(i.`$invAmountCol`,0)
                 - $discForDue
                 - (SELECT COALESCE(SUM(pm1.amount),0) FROM payments pm1 WHERE ".($payFk?"pm1.`$payFk`=i.id":"pm1.client_id=i.client_id")." ".payments_active_where($pdo,'pm1').")
               )),0)
               FROM invoices i JOIN clients c ON c.id=i.client_id
               WHERE i.$whereMonth AND %ZONECOND%";

    foreach ($rowsZ as $r) {
      if     ($zoneMeta['type']==='fk')   { $zCond="c.`{$zoneMeta['key_col']}`=?"; $zParam=[$r['zone_key']]; }
      elseif ($zoneMeta['type']==='text') { $col=$zoneMeta['key_col']; $zCond="COALESCE(c.`$col`,'Unassigned')=?"; $zParam=[$r['zone_key']]; }
      else                                { $zCond="1=1"; $zParam=[]; }

      // Generated
      $stG=$pdo->prepare(str_replace('%ZONECOND%',$zCond,$sqlGen));
      $stG->execute(array_merge($params_base,$zParam)); $gen=(float)$stG->fetchColumn();

      // Collection
      $col=0.0; if ($sqlCol){
        $stC=$pdo->prepare(str_replace('%ZONECOND%',$zCond,$sqlCol));
        $stC->execute($payDateCol? array_merge([$date_start,$date_end],$zParam):$zParam);
        $col=(float)$stC->fetchColumn();
      }

      // Discount = payments + invoices
      $disPay=0.0; $disInv=0.0;
      if ($sqlDisPay){
        $stDP=$pdo->prepare(str_replace('%ZONECOND%',$zCond,$sqlDisPay));
        $stDP->execute($payDateCol? array_merge([$date_start,$date_end],$zParam):$zParam);
        $disPay=(float)$stDP->fetchColumn();
      }
      if ($sqlDisInv){
        $stDI=$pdo->prepare(str_replace('%ZONECOND%',$zCond,$sqlDisInv));
        $stDI->execute(array_merge($params_base,$zParam));
        $disInv=(float)$stDI->fetchColumn();
      }
      $dis = $disPay + $disInv;

      // Due
      $stU=$pdo->prepare(str_replace('%ZONECOND%',$zCond,$sqlDue));
      $stU->execute(array_merge($params_base,$zParam)); $due=(float)$stU->fetchColumn();

      $ratio = ($gen>0.0001)? (($col/$gen)*100.0) : 0.0;

      $zonesData[] = [
        'zone_key'=>$r['zone_key'],'zone_name'=>(string)$r['zone_name'],
        'total_clients'=>(int)$r['total_clients'],
        'active_clients'=>(int)$r['active_clients'],
        'inactive_clients'=>(int)$r['inactive_clients'],
        'generated'=>$gen,'collection'=>$col,'discount'=>$dis,'due'=>$due,'ratio'=>$ratio,
      ];
      $z_tot_gen += $gen; $z_tot_col += $col; $z_tot_dis += $dis; $z_tot_due += $due;
    }
  } catch (Throwable $e) { $summaryError = $e->getMessage(); }
}

/* =========================================================
   LIST VIEW (All/Paid/Due) – carryover-aware
   ========================================================= */
$params=[]; $filter="";
if($search!==''){
  $searchCols = [];
  foreach (['name','pppoe_id','client_code','mobile','phone','cell','contact'] as $sc) {
    if (col_exists($pdo,'clients',$sc)) $searchCols[] = "c.`$sc` LIKE ?";
  }
  if ($searchCols) { $filter .= " AND (".implode(' OR ',$searchCols).") "; foreach ($searchCols as $_) $params[] = "%$search%"; }
}
if ($view === 'list') {
  if ($package_id > 0) { $filter .= " AND c.package_id = ?";  $params[] = $package_id; }
  if ($router_id  > 0) { $filter .= " AND c.router_id  = ?";  $params[] = $router_id; }
  if ($hasArea && $zone !== '') { $filter .= " AND TRIM(LOWER(c.`{$AREA_COL}`)) = TRIM(LOWER(?))"; $params[] = $zone; }
  if ($hasSubZone && $sub_zone !== '') { $filter .= " AND TRIM(LOWER(c.`{$SUB_ZONE_COL}`)) = TRIM(LOWER(?))"; $params[] = $sub_zone; }
  if ($hasBox && $box !== '') { $filter .= " AND TRIM(LOWER(c.`{$BOX_COL}`)) = TRIM(LOWER(?))"; $params[] = $box; }
  if ($b_status !== '') {
    if ($b_status === 'left') {
      $leftParts = [];
      if ($hasLeftCol) { $leftParts[] = "COALESCE(c.is_left,0)=1"; }
      if ($hasStatusCol) { $leftParts[] = "LOWER(COALESCE(c.status,'')) IN ('left','terminated')"; }
      $filter .= $leftParts ? (" AND (" . implode(' OR ', $leftParts) . ")") : " AND 1=0";
    } elseif ($B_STATUS_COL !== '') {
      $status_val = ($b_status === 'due') ? 'unpaid' : $b_status;
      $filter .= " AND TRIM(LOWER(c.`{$B_STATUS_COL}`)) = TRIM(LOWER(?))";
      $params[] = $status_val;
    } elseif ($invStatusCol) {
      $status_val = ($b_status === 'due') ? 'unpaid' : $b_status;
      $filter .= " AND TRIM(LOWER(i.`{$invStatusCol}`)) = TRIM(LOWER(?))";
      $params[] = $status_val;
    }
  }
  if ($custom_status !== '') {
    if ($custom_status === 'active') {
      $filter .= " AND ($period_expr)";
    } else {
      $filter .= " AND (i.id IS NULL OR NOT ($period_expr))";
    }
  }
}

/* Header counters */
// total invoices created this month (as-is)
$count_total=(int)pdo_scalar($pdo,"SELECT COUNT(*) FROM invoices WHERE $whereMonth",$params_base);

/* innerCnt (latest invoice up-to-month-end per client) */
$sumPaidForCnt = $payFk
  ? "COALESCE((SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm')."), ".($invoicePaidCol ? "COALESCE(i.`$invoicePaidCol`,0)" : "0").")"
  : ( $hasPayClientId && $payDateCol
      ? "COALESCE((SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm')."), ".($invoicePaidCol ? "COALESCE(i.`$invoicePaidCol`,0)" : "0").")"
      : ($invoicePaidCol ? "COALESCE(i.`$invoicePaidCol`,0)" : "0")
    );

if ($payFk && $hasPayDiscount && $invoiceDiscCol) {
  $sumDiscForCnt = "(COALESCE(i.`$invoiceDiscCol`,0) + (SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm')."))";
} elseif ($payFk && $hasPayDiscount) {
  $sumDiscForCnt = "(SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")";
} elseif (!$payFk && $hasPayClientId && $hasPayDiscount && $payDateCol) {
  // fallback: client-month discount
  $sumDiscForCnt = "(SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm').")";
} elseif ($invoiceDiscCol) {
  $sumDiscForCnt = "COALESCE(i.`$invoiceDiscCol`,0)";
} else { $sumDiscForCnt = "0"; }

// latest invoice up to month-end
$innerCnt = "
  SELECT 
    i.client_id,
    GREATEST(0, COALESCE(i.`$invAmountCol`,0) - ".($isNetInvAmount?'0':"($sumDiscForCnt)")." - ($sumPaidForCnt)) AS remain
  FROM invoices i
  JOIN(
    SELECT client_id, MAX(id) AS max_id
    FROM invoices $rangeUpTo GROUP BY client_id
  ) t ON t.max_id=i.id
";

// bind params for innerCnt
$params_cnt = $params_upto;
if (!$payFk && $hasPayClientId && $payDateCol) {
  // order must match occurrences: first for paid, then for discount (if present)
  $params_cnt = array_merge($params_cnt, [$date_start,$date_end]);
  if ($hasPayDiscount) $params_cnt = array_merge($params_cnt, [$date_start,$date_end]);
}

$count_paid = 0;
$count_due  = 0;

/* Row builder (latest up-to-month-end) */
$selectMobile = $clientMobileCol ? ", c.`$clientMobileCol` AS mobile" : ", NULL AS mobile";
$selectPkg    = $hasPkgName ? ", p.name AS package_name" : ", NULL AS package_name";
$selectPkgSpeed = ($hasPackages && col_exists($pdo,'packages','speed')) ? ", p.speed AS package_speed" : ", NULL AS package_speed";
$selectClientCode = col_exists($pdo,'clients','client_code') ? ", c.client_code AS client_code" : ", NULL AS client_code";
$selectIp = col_exists($pdo,'clients','ip_address') ? ", c.ip_address AS ip_address" : ", NULL AS ip_address";
$selectZone = $hasArea ? ", c.`$AREA_COL` AS zone_name" : ", NULL AS zone_name";
$selectSubZone = $hasSubZone ? ", c.`$SUB_ZONE_COL` AS sub_zone" : ", NULL AS sub_zone";
$selectBox = $hasBox ? ", c.`$BOX_COL` AS box_name" : ", NULL AS box_name";
$selectIsLeft = $hasLeftCol ? ", c.is_left AS is_left" : ", NULL AS is_left";
$selectClientStatus = $hasStatusCol ? ", c.status AS client_status" : ", NULL AS client_status";
$selectPeriodActive = ", (CASE WHEN $period_expr THEN 1 ELSE 0 END) AS period_active";
$selectProfile = $PROFILE_COL !== '' ? ", c.`$PROFILE_COL` AS profile_name" : ", NULL AS profile_name";
$selectExpiry = $EXPIRY_COL !== '' ? ", c.`$EXPIRY_COL` AS expiry_date" : ", NULL AS expiry_date";
$selectMonthlyBill = $MONTHLY_BILL_COL !== '' ? ", NULLIF(c.`$MONTHLY_BILL_COL`,'') AS monthly_bill" : ", NULL AS monthly_bill";
$selectBillingStatus = $B_STATUS_COL !== '' ? ", c.`$B_STATUS_COL` AS billing_status" : ($invStatusCol ? ", i.`$invStatusCol` AS billing_status" : ", NULL AS billing_status");
$selectRouter = $ROUTER_NAME_EXPR !== 'NULL' ? ", $ROUTER_NAME_EXPR AS router_name" : ", NULL AS router_name";
$selectVat = $invoiceVatCol ? ", COALESCE(i.`$invoiceVatCol`,0) AS vat_amount" : ", 0 AS vat_amount";
$selectAdvance = $ADVANCE_COL !== '' ? ", COALESCE(c.`$ADVANCE_COL`,0) AS advance_amount" : ", GREATEST(0, COALESCE($clientLedgerExpr,0)) AS advance_amount";

$lastPayDateExpr = "NULL";
if ($payDateCol) {
  if ($payFk) {
    $lastPayDateExpr = "(SELECT MAX(pm.`$payDateCol`) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")";
  } elseif ($hasPayClientId) {
    $lastPayDateExpr = "(SELECT MAX(pm.`$payDateCol`) FROM payments pm WHERE pm.client_id=i.client_id".payments_active_where($pdo,'pm').")";
  }
}
if ($clientLastPayCol) {
  $lastPayDateExpr = "COALESCE($lastPayDateExpr, c.`$clientLastPayCol`)";
}
if (!$payDateCol && $clientLastPayCol) {
  $lastPayDateExpr = "c.`$clientLastPayCol`";
}

/* sumPaid / sumDisc for rows */
if ($payFk) {
  $sumPaidBase = "(SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")";
  $sumPaid = $invoicePaidCol
    ? "COALESCE($sumPaidBase, COALESCE(i.`$invoicePaidCol`,0))"
    : $sumPaidBase;
  if ($hasPayDiscount && $invoiceDiscCol) {
    $sumDisc = "(COALESCE(i.`$invoiceDiscCol`,0) + (SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm')."))";
  } elseif ($hasPayDiscount) {
    $sumDisc = "(SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")";
  } elseif ($invoiceDiscCol) {
    $sumDisc = "COALESCE(i.`$invoiceDiscCol`,0)";
  } else { $sumDisc = "0"; }
} else {
  // Fallback: only client_id exists in payments
  $sumPaid = ($hasPayClientId && $payDateCol)
    ? "COALESCE((SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm')."), ".($invoicePaidCol ? "COALESCE(i.`$invoicePaidCol`,0)" : "0").")"
    : ($invoicePaidCol ? "COALESCE(i.`$invoicePaidCol`,0)" : "0");
  if ($hasPayClientId && $hasPayDiscount && $payDateCol) {
    $sumDisc = "(SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm').")";
  } elseif ($invoiceDiscCol) {
    $sumDisc = "COALESCE(i.`$invoiceDiscCol`,0)";
  } else { $sumDisc = "0"; }
}

// (বাংলা) Invoice balance per client (match invoices.php logic)
$payNetExpr = $hasPayDiscount
  ? "SUM(pm.amount - COALESCE(pm.discount,0))"
  : "SUM(pm.amount)";
$paidSubExpr = $payFk
  ? "COALESCE((SELECT COALESCE($payNetExpr,0) FROM payments pm WHERE pm.`$payFk`=i2.id".payments_active_where($pdo,'pm')."), ".($invoicePaidCol ? "COALESCE(i2.`$invoicePaidCol`,0)" : "0").")"
  : ($invoicePaidCol ? "COALESCE(i2.`$invoicePaidCol`,0)" : "0");

$discBalExpr = "0";
if ($invoiceDiscCol && $hasPayDiscount && $payFk) {
  $discBalExpr = "(COALESCE(i2.`$invoiceDiscCol`,0) + COALESCE((SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i2.id".payments_active_where($pdo,'pm')."),0))";
} elseif ($hasPayDiscount && $payFk) {
  $discBalExpr = "COALESCE((SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.`$payFk`=i2.id".payments_active_where($pdo,'pm')."),0)";
} elseif ($invoiceDiscCol) {
  $discBalExpr = "COALESCE(i2.`$invoiceDiscCol`,0)";
}

$invRemainExpr = "GREATEST(0, COALESCE(i2.`$invAmountCol`,0) - ($discBalExpr) - ($paidSubExpr))";
$invActiveWhere = invoices_active_where($pdo,'i2');
$ledgerDueExpr = "GREATEST(0, -1*COALESCE($clientLedgerExpr,0))";
$invoiceBalanceJoin = "
  LEFT JOIN (
    SELECT i2.client_id, COALESCE(SUM($invRemainExpr),0) AS inv_balance
    FROM invoices i2
    WHERE 1=1 $invActiveWhere
    GROUP BY i2.client_id
  ) invbal ON invbal.client_id=c.id
";

$innerRows = "
  SELECT 
    c.id AS client_id, c.name AS client_name, c.pppoe_id
    $selectMobile
    $selectPkg
    $selectPkgSpeed
    $selectClientCode
    $selectIp
    $selectZone
    $selectSubZone
    $selectBox
    $selectIsLeft
    $selectClientStatus
    $selectPeriodActive
    $selectProfile
    $selectExpiry
    $selectMonthlyBill
    $selectBillingStatus
    $selectRouter
    $selectVat
    $selectAdvance
    , $lastPayDateExpr AS last_payment_date
    , i.id AS invoice_id,
    i.`$invAmountCol` AS inv_amount,
    ".($invStatusCol ? "i.`$invStatusCol` AS inv_status" : "NULL AS inv_status").",
    $sumDisc AS discount,
    $sumPaid AS paid_amount,
    $clientLedgerExpr AS ledger_balance,
    COALESCE(invbal.inv_balance,0) AS inv_balance,
    GREATEST(COALESCE(invbal.inv_balance,0), $ledgerDueExpr) AS effective_due,
    GREATEST(0, COALESCE(i.`$invAmountCol`,0) - ".($isNetInvAmount?'0':"($sumDisc)")." - ($sumPaid)) AS remain
  FROM clients c
  LEFT JOIN(
    SELECT i1.* FROM invoices i1
    JOIN(SELECT client_id, MAX(id) AS max_id FROM invoices $rangeUpTo GROUP BY client_id) t
      ON t.max_id=i1.id
  ) i ON i.client_id=c.id
  $invoiceBalanceJoin
  ".($hasPackages ? "LEFT JOIN packages p ON p.id=c.package_id" : "")."
  ".(tbl_exists($pdo,'routers') ? "LEFT JOIN routers r ON r.id=c.router_id" : "")."
  WHERE 1=1 $filter
";

$tab_where = '';
if ($tab === 'paid') {
  $tab_where = "WHERE x.effective_due<=0.0001";
} elseif ($tab === 'due') {
  $tab_where = "WHERE x.effective_due>0.0001";
}
$sql_count_clients = "SELECT COUNT(*) FROM ( $innerRows ) x ".$tab_where;
$params_rows_base = $params_upto;
if (!$payFk && $hasPayClientId && $payDateCol) {
  $params_rows_base = array_merge($params_rows_base, [$date_start,$date_end]); // paid
  if ($hasPayDiscount && (!$payFk)) $params_rows_base = array_merge($params_rows_base, [$date_start,$date_end]); // disc
}
$params_count = array_merge($params_rows_base, $params);
$stc=$pdo->prepare($sql_count_clients);
$stc->execute($params_count);
$total_clients=(int)$stc->fetchColumn();

$count_total = $total_clients;
$count_paid = col_exists($pdo,'invoices','status')
  ? (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE $whereMonth AND status='paid'", $params_base)
  : (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM ( $innerRows ) x WHERE x.effective_due<=0.0001", $params_count);
$count_due  = (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM ( $innerRows ) x WHERE x.effective_due>0.0001",  $params_count);

$pages=max(1,(int)ceil($total_clients/$limit));
$page=min(max(1,$page),$pages);
$offset=($page-1)*$limit;

/* Final rows */
$tab_where_rows = '';
if ($tab === 'paid') {
  $tab_where_rows = "WHERE r.effective_due<=0.0001";
} elseif ($tab === 'due') {
  $tab_where_rows = "WHERE r.effective_due>0.0001";
}
$sql_rows = "SELECT * FROM ( $innerRows ) r " .
  $tab_where_rows .
  " ORDER BY r.client_name ASC, r.invoice_id DESC LIMIT $limit OFFSET $offset";
$std=$pdo->prepare($sql_rows);
$params_rows = array_merge($params_rows_base, $params);
$std->execute($params_rows);
$rows=$std->fetchAll(PDO::FETCH_ASSOC);

/* Dropdown data */
$packages = $hasPackages
  ? $pdo->query("SELECT id, name FROM packages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC)
  : [];
$routers  = tbl_exists($pdo,'routers') && col_exists($pdo,'routers','id')
  ? $pdo->query("SELECT id, ".(col_exists($pdo,'routers','name') ? 'name' : 'id')." AS name FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC)
  : [];
$zones = $hasArea ? distinct_values($pdo, 'clients', $AREA_COL) : [];
$sub_zones_list = $hasSubZone ? distinct_values($pdo, 'clients', $SUB_ZONE_COL) : [];
$boxes = $hasBox ? distinct_values($pdo, 'clients', $BOX_COL) : [];
$b_statuses = ['paid','due','partial','left'];

/* ========================== NEW TOTALS ========================== */
/* (বাংলা) invoice-based due: sum of remaining across invoices */
$sql_month_due = "SELECT COALESCE(SUM(r.effective_due),0) FROM ( $innerRows ) r";
$stmd = $pdo->prepare($sql_month_due);
$stmd->execute($params_rows);
$month_due_total = (float)$stmd->fetchColumn();

/* (বাংলা) payments থেকে মাসের মোট সংগ্রহ */
$month_paid_total = 0.0;
if ($payDateCol) {
  $paySql = "SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm');
  $stmp = $pdo->prepare($paySql);
  $stmp->execute([$date_start, $date_end]);
  $month_paid_total = (float)$stmp->fetchColumn();
}

/* (বাংলা) 2) Due (Filtered): বর্তমান search/tab ফিল্টার মিলিয়ে সকল ম্যাচিং ক্লায়েন্টের ledger-based due যোগফল */
$sql_filtered_due = "SELECT COALESCE(SUM(r.effective_due),0) FROM ( $innerRows ) r ".
  ($tab==='paid' ? "WHERE r.inv_balance<=0.0001" : ($tab==='due' ? "WHERE r.inv_balance>0.0001" : ""));
$stfd = $pdo->prepare($sql_filtered_due);
$stfd->execute($params_rows);
$filtered_due_total = (float)$stfd->fetchColumn();

/* (বাংলা) Page Due */
$page_due_total=0.0;
foreach($rows as $r){
  $eff_due = (float)($r['effective_due']??0);
  if ($eff_due > 0) $page_due_total += $eff_due;
}

/* CSV (List) */
if ($export && $view!=='summary') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="billing-'.$monthParam.'-'.$tab.'.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ClientID','Name','PPPoE','Mobile','Package','Status','InvoiceAmount','Discount','Paid','Payable','Ledger']);
  foreach ($rows as $r) {
    $invAmount=(float)($r['inv_amount']??0);
    $paid     =(float)($r['paid_amount']??0);
    $disc     =(float)($r['discount']??0);
    $remain   = max(0.0, $invAmount - ($isNetInvAmount?0:$disc) - $paid);
    $ledger   = (float)($r['ledger_balance']??0);
    fputcsv($out, [
      (int)$r['client_id'], (string)($r['client_name'] ?? ''), (string)($r['pppoe_id'] ?? ''),
      (string)($r['mobile'] ?? ''), (string)($r['package_name'] ?? ''), (string)($r['status'] ?? ''),
      number_format($invAmount,2,'.',''), number_format($disc,2,'.',''),
      number_format($paid,2,'.',''), number_format($remain,2,'.',''), number_format($ledger,2,'.',''),
    ]);
  }
  fclose($out); return;
}

/* current URL for return */
$cur_url = $_SERVER['REQUEST_URI'] ?? '/public/billing.php';

/* CSRF (বাংলা) modal actions এর জন্য নিশ্চিত করি */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$__csrf = $_SESSION['csrf_token'] ?? '';
if (!$__csrf) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); $__csrf = $_SESSION['csrf_token']; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Billing</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  :root{ --radius:14px; }
  .summary .num{font-weight:700}.summary .total{color:#0d6efd}.summary .paid{color:#198754}.summary .due{color:#dc3545}
  .seg a{text-decoration:none}.seg .btn{border-radius:999px;padding:.44rem .9rem}.seg .btn.active{pointer-events:none}
  .hero{background:linear-gradient(135deg,#eef4ff 0%,#f9fbff 100%);border:1px solid #e9eefc;border-radius:var(--radius)}
  .kpi{border:1px solid #eef2f7;border-radius:var(--radius);background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.04)}
  .kpi .icon{width:42px;height:42px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#f1f5ff;font-size:18px}
  .kpi .val{font-size:1.15rem;font-weight:800;letter-spacing:.3px}
  .kpi .hint{font-size:.775rem;color:#6c757d}
  .table-sm td,.table-sm th{padding:.6rem .7rem;line-height:1.2;vertical-align:middle;font-size:.92rem}
  .table thead{background:#f6f9ff}.table thead th{border-bottom:1px solid #e6eaf5}
  .table tbody tr:hover{background:#fafcff}
  .badge-pill{border-radius:999px;padding:.35rem .6rem;font-weight:600}
  .payable{font-weight:700}.payable.due{color:#dc3545}.payable.zero{color:#198754}
  .ratio-wrap{min-width:130px}.progress{height:8px;background:#eef2f7}.progress-bar{background:#20c997}
  @media(max-width:767.98px){.overflow-x{overflow-x:auto}}
</style>
</head>
<body>
<?php 
