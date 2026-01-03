<?php
// /public/billing.php
// Month-wise Billing Dashboard
// Views: Summary (zone-wise) + List (All/Paid/Due) + Search + Pagination + CSV
// Eye → client_ledger.php; Pay → payment_add.php (always enabled)
// UI English; Bangla comments only

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

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
  foreach (['name','pppoe_id','mobile','phone','cell','contact'] as $sc) if (col_exists($pdo,'clients',$sc)) $searchCols[] = "c.`$sc` LIKE ?";
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
  ? "(SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")"
  : ( $hasPayClientId && $payDateCol
      ? "(SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm').")"
      : "0"
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

/* sumPaid / sumDisc for rows */
if ($payFk) {
  $sumPaid = "(SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.`$payFk`=i.id".payments_active_where($pdo,'pm').")";
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
    ? "(SELECT COALESCE(SUM(pm.amount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm').")"
    : "0";
  if ($hasPayClientId && $hasPayDiscount && $payDateCol) {
    $sumDisc = "(SELECT COALESCE(SUM(pm.discount),0) FROM payments pm WHERE pm.client_id=i.client_id AND pm.`$payDateCol` BETWEEN ? AND ?".payments_active_where($pdo,'pm').")";
  } elseif ($invoiceDiscCol) {
    $sumDisc = "COALESCE(i.`$invoiceDiscCol`,0)";
  } else { $sumDisc = "0"; }
}

// (বাংলা) Invoice balance per client (match invoices.php logic)
$payNetExpr = $hasPayDiscount
  ? "COALESCE(SUM(pm.amount - COALESCE(pm.discount,0)),0)"
  : "COALESCE(SUM(pm.amount),0)";
$paidSubExpr = $payFk
  ? "(SELECT $payNetExpr FROM payments pm WHERE pm.`$payFk`=i2.id".payments_active_where($pdo,'pm').")"
  : "0";
$invRemainExpr = "GREATEST(0, COALESCE(i2.`$invAmountCol`,0) - $paidSubExpr)";
$invActiveWhere = invoices_active_where($pdo,'i2');
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
  $tab_where = "WHERE x.inv_balance<=0.0001";
} elseif ($tab === 'due') {
  $tab_where = "WHERE x.inv_balance>0.0001";
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
$count_paid = (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM ( $innerRows ) x WHERE x.inv_balance<=0.0001", $params_count);
$count_due  = (int)pdo_scalar($pdo, "SELECT COUNT(*) FROM ( $innerRows ) x WHERE x.inv_balance>0.0001",  $params_count);

$pages=max(1,(int)ceil($total_clients/$limit));
$page=min(max(1,$page),$pages);
$offset=($page-1)*$limit;

/* Final rows */
$tab_where_rows = '';
if ($tab === 'paid') {
  $tab_where_rows = "WHERE r.inv_balance<=0.0001";
} elseif ($tab === 'due') {
  $tab_where_rows = "WHERE r.inv_balance>0.0001";
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
$sql_month_due = "SELECT COALESCE(SUM(r.inv_balance),0) FROM ( $innerRows ) r";
$stmd = $pdo->prepare($sql_month_due);
$stmd->execute($params_rows);
$month_due_total = (float)$stmd->fetchColumn();

/* (বাংলা) 2) Due (Filtered): বর্তমান search/tab ফিল্টার মিলিয়ে সকল ম্যাচিং ক্লায়েন্টের ledger-based due যোগফল */
$sql_filtered_due = "SELECT COALESCE(SUM(r.inv_balance),0) FROM ( $innerRows ) r ".
  ($tab==='paid' ? "WHERE r.inv_balance<=0.0001" : ($tab==='due' ? "WHERE r.inv_balance>0.0001" : ""));
$stfd = $pdo->prepare($sql_filtered_due);
$stfd->execute($params_rows);
$filtered_due_total = (float)$stfd->fetchColumn();

/* (বাংলা) Page Due */
$page_due_total=0.0;
foreach($rows as $r){
  $inv_balance = (float)($r['inv_balance']??0);
  if ($inv_balance > 0) $page_due_total += $inv_balance;
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
<?php include __DIR__ . '/../partials/partials_header.php'; ?>

<div class="main-content p-3 p-md-4">
  <div class="container-fluid">

    <h3 class="mb-3">Billing</h3>

    <?php
      $qs=$_GET; $qs['page']=1;
      $qs_sum=$qs;  $qs_sum['view']='summary'; unset($qs_sum['tab']); unset($qs_sum['search']);
      $qs_all=$qs;  $qs_all['view']='list'; $qs_all['tab']='all';
      $qs_paid=$qs; $qs_paid['view']='list'; $qs_paid['tab']='paid';
      $qs_due=$qs;  $qs_due['view']='list'; $qs_due['tab']='due';
    ?>
    <div class="seg btn-group mb-3" role="group">
      <a class="btn btn-outline-primary <?= $view==='summary'?'active':'' ?>" href="?<?= h(http_build_query($qs_sum)) ?>"><i class="bi bi-graph-up"></i> Summary</a>
      <a class="btn btn-outline-secondary <?= ($view==='list' && $tab==='all')?'active':'' ?>" href="?<?= h(http_build_query($qs_all)) ?>"><i class="bi bi-list-ul"></i> All</a>
            <a href="/public/collections.php?when=today" class="btn btn-outline-success"><i class="bi bi-calendar-day"></i> Today's Collection</a>
      <!-- <a class="btn btn-outline-success <?= ($view==='list' && $tab==='paid')?'active':'' ?>" href="?<?= h(http_build_query($qs_paid)) ?>"><i class="bi bi-check2-circle"></i> Paid</a>
      <a class="btn btn-outline-danger  <?= ($view==='list' && $tab==='due')?'active':'' ?>" href="?<?= h(http_build_query($qs_due )) ?>"><i class="bi bi-exclamation-octagon"></i> Due</a> -->
      <a href="/public/webhook_payments.php" class="btn btn-outline-danger btn-sm"> 📴 Webhook Payments </a>
      <?php if($view==='list'){ $qs_csv=$qs; $qs_csv['export']='csv'; ?>
        <a class="btn btn-outline-primary btn-sm" href="?<?= h(http_build_query($qs_csv)) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
      <?php } ?>
    </div>

    <!-- Filters -->
    <form class="filter-card card border-0 shadow-sm mb-3" method="GET">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-sm-3">
            <label class="form-label mb-1">Month</label>
            <input type="month" class="form-control form-control-sm" name="month" value="<?= h($monthParam) ?>">
          </div>
          <?php if($view==='list'): ?>
          <div class="col-12 col-sm-5">
            <label class="form-label mb-1">Search</label>
            <input type="text" class="form-control form-control-sm" name="search" placeholder="Name / PPPoE / Mobile" value="<?= h($search) ?>">
          </div>
          <div class="col-6 col-sm-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Apply</button>
          </div>
          <div class="col-6 col-sm-2 d-grid">
            <a class="btn btn-outline-secondary btn-sm" href="?month=<?= h(date('Y-m')) ?>&view=<?= h($view) ?>&tab=<?= h($tab) ?>"><i class="bi bi-x-circle"></i> Reset</a>
          </div>
          <?php else: ?>
          <div class="col-6 col-sm-2 d-grid">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Apply</button>
          </div>
          <div class="col-6 col-sm-2 d-grid">
            <a class="btn btn-outline-secondary btn-sm" href="?month=<?= h(date('Y-m')) ?>&view=summary"><i class="bi bi-x-circle"></i> Reset</a>
          </div>
          <?php endif; ?>
        </div>

        <?php if($view==='list'): ?>
        <div class="filter-grid mt-3">
          <div class="row g-2 g-md-3">
            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Server</label>
              <select name="router_id" class="form-select form-select-sm">
                <option value="0">Select</option>
                <?php foreach($routers as $rt): ?>
                  <option value="<?= (int)$rt['id'] ?>" <?= $router_id==(int)$rt['id']?'selected':'' ?>>
                    <?= h($rt['name'] ?? 'Router #'.(int)$rt['id']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Zone</label>
              <select name="zone" class="form-select form-select-sm" <?= !$hasArea ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($zones as $z): ?>
                  <option value="<?= h($z) ?>" <?= $zone===$z?'selected':'' ?>><?= h($z) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Sub Zone</label>
              <select name="sub_zone" class="form-select form-select-sm" <?= !$hasSubZone ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($sub_zones_list as $sz): ?>
                  <option value="<?= h($sz) ?>" <?= $sub_zone===$sz?'selected':'' ?>><?= h($sz) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Box</label>
              <select name="box" class="form-select form-select-sm" <?= !$hasBox ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($boxes as $b): ?>
                  <option value="<?= h($b) ?>" <?= $box===$b?'selected':'' ?>><?= h($b) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Package</label>
              <select name="package_id" class="form-select form-select-sm">
                <option value="0">Select</option>
                <?php foreach($packages as $pkg): ?>
                  <option value="<?= (int)$pkg['id'] ?>" <?= $package_id==(int)$pkg['id']?'selected':'' ?>>
                    <?= h($pkg['name'] ?? 'N/A') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Billing Status</label>
              <select name="b_status" class="form-select form-select-sm" <?= ($B_STATUS_COL==='' && !$invStatusCol) ? 'disabled' : '' ?>>
                <option value="">Select</option>
                <?php foreach($b_statuses as $bs): ?>
                  <option value="<?= h($bs) ?>" <?= $b_status===$bs?'selected':'' ?>><?= h(ucfirst($bs)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
              <label class="form-label mb-1 text-uppercase small fw-semibold">Custom Status</label>
              <select name="custom_status" class="form-select form-select-sm">
                <option value="">Select</option>
                <option value="active" <?= $custom_status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $custom_status==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>

          </div>
        </div>
        <?php endif; ?>
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <?php if($view==='list'): ?><input type="hidden" name="tab" value="<?= h($tab) ?>"><?php endif; ?>
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="limit" value="<?= (int)$limit ?>">
      </div>
    </form>

    <?php if($view==='summary'): ?>

      <div class="hero p-3 p-md-4 mb-3">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
          <div>
            <h4 class="mb-1">Bills vs Collections</h4>
            <div class="text-muted">Month: <?= h(date('F Y', strtotime($monthParam.'-01'))) ?></div>
          </div>
          <div class="summary d-inline-flex flex-wrap gap-4">
            <div><span class="text-muted">Invoices:</span> <span class="num total"><?= number_format($count_total) ?></span></div>
            <div><span class="text-muted">Paid:</span> <span class="num paid"><?= number_format($count_paid) ?></span></div>
            <div><span class="text-muted">Due:</span> <span class="num due"><?= number_format($count_due) ?></span></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-primary"><i class="bi bi-receipt"></i></div><div class="hint">Generated</div></div><div class="val text-primary"> <?= number_format($z_tot_gen,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-success"><i class="bi bi-coin"></i></div><div class="hint">Collection</div></div><div class="val text-success"> <?= number_format($z_tot_col,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-info"><i class="bi bi-percent"></i></div><div class="hint">Discount</div></div><div class="val text-info"> <?= number_format($z_tot_dis,2) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="kpi p-3"><div class="d-flex align-items-center gap-2 mb-2"><div class="icon text-danger"><i class="bi bi-exclamation-octagon"></i></div><div class="hint">Total Due</div></div><div class="val text-danger"> <?= number_format($z_tot_due,2) ?></div></div></div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">Zones / Areas</div>
        <div class="card-body p-0">
          <?php if($summaryError): ?>
            <div class="alert alert-danger m-3">Failed to load summary: <?= h($summaryError) ?></div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:56px">SL</th>
                  <th>Area</th>
                  <th>Status</th>
                  <th class="text-end">Generated</th>
                  <th class="text-end">Collection</th>
                  <th class="text-end">Discount</th>
                  <th class="text-end">Due</th>
                  <th class="text-end">Ratio</th>
                </tr>
              </thead>
              <tbody>
                <?php if($zonesData): $sl=1; foreach($zonesData as $z): ?>
                <tr>
                  <td><?= $sl++ ?></td>
                  <td>
                    <div class="fw-semibold"><?= h($z['zone_name']) ?></div>

                  </td>
                  <td>
                    <div class="small">
                      <span class="badge bg-success-subtle text-success border badge-pill">Active: <?= (int)$z['active_clients'] ?></span>
                      <span class="badge bg-danger-subtle text-danger border badge-pill ms-1">Inactive: <?= (int)$z['inactive_clients'] ?></span>
                      <span class="text-muted ms-1 small">Total: <?= (int)$z['total_clients'] ?></span>
                    </div>
                  </td>
                  <td class="text-end fw-semibold"> <?= number_format($z['generated'],2) ?></td>
                  <td class="text-end text-success fw-semibold"> <?= number_format($z['collection'],2) ?></td>
                  <td class="text-end text-primary"> <?= number_format($z['discount'],2) ?></td>
                  <td class="text-end text-danger fw-semibold"> <?= number_format($z['due'],2) ?></td>
                  <td class="text-end">
                    <div class="ratio-wrap">
                      <div class="d-flex justify-content-between small text-muted mb-1">
                        <span><?= number_format($z['ratio'],2) ?>%</span>
                        <span><?= $z['generated']>0? number_format(($z['collection']/$z['generated'])*100,0).'%' : '0%' ?></span>
                      </div>
                      <div class="progress"><div class="progress-bar" style="width: <?= max(0,min(100,$z['ratio'])) ?>%"></div></div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="8" class="text-center text-muted">No data.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?><!-- LIST VIEW -->

      <div class="text-left mb-2">
        <div class="summary d-inline-flex flex-wrap gap-4 fs-5 fs-md-4">
          <div><span class="text-muted">Total Bills :</span> <span class="num total"><?= number_format($count_total) ?></span></div>
          <div><span class="text-muted">Paid :</span> <span class="num paid"><?= number_format($count_paid) ?></span></div>
          <div><span class="text-muted">Due Bills :</span> <span class="num due"><?= number_format($count_due) ?></span></div>
        </div>
      </div>

      <!-- Totals row -->
      <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="fw-semibold">
          <i class="bi bi-exclamation-octagon text-danger"></i>
          Total Due (Month): <span class="text-danger"> <?= number_format($month_due_total,2) ?></span>
        </div>
        <div class="text-muted">
          <span class="me-3">Due (Filtered): <span class="fw-semibold">৳ <?= number_format($filtered_due_total,2) ?></span></span>
          <span>Due (This Page): <span class="fw-semibold"> <?= number_format($page_due_total,2) ?></span></span>
        </div>
      </div>

      <form class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2" method="GET">
        <div class="d-flex align-items-center gap-2">
          <label class="small text-muted mb-0" for="bill-limit">Show</label>
          <select id="bill-limit" name="limit" class="form-select form-select-sm" style="width: 90px;" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $opt): ?>
              <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <span class="small text-muted">entries</span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <label class="small text-muted mb-0" for="bill-search">Search:</label>
          <input id="bill-search" type="text" class="form-control form-control-sm" name="search" value="<?= h($search) ?>" placeholder="Name / PPPoE / Mobile">
          <button class="btn btn-primary btn-sm" type="submit">Go</button>
        </div>
        <input type="hidden" name="month" value="<?= h($monthParam) ?>">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <input type="hidden" name="page" value="1">
      </form>

      <div class="overflow-x">
        <table class="table table-striped table-hover table-sm align-middle">
          <thead>
            <tr>
              <th style="width:28px;"><input type="checkbox" aria-label="Select all"></th>
              <th>C.Code</th>
              <th>ID / IP</th>
              <th>Cus. Name</th>
              <th>MobileNumber</th>
              <th>Zone</th>
              <th>Package</th>
              <th>Ex.Date</th>
              <th class="text-end">M.Bill</th>
              <th class="text-end">Received</th>
              <th class="text-end">BalanceDue</th>
              <th class="text-end">Advance</th>
              <th>PaymentDate</th>
              <th>Billing Status</th>
              <th>Custom Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if($rows): foreach($rows as $r):
              $invAmount = (float)($r['inv_amount']??0);
              $paid      = (float)($r['paid_amount']??0);
              $disc      = (float)($r['discount']??0);
              $remain    = max(0.0, $invAmount - ($isNetInvAmount?0:$disc) - $paid);
              $ledger    = (float)($r['ledger_balance']??0);
              $inv_balance = (float)($r['inv_balance']??0);
              $due_balance = $inv_balance > 0 ? $inv_balance : 0.0;
              $pay_class = $due_balance>0.0001?'due':'zero';
              $advance   = (float)($r['advance_amount'] ?? 0);
              $vat       = (float)($r['vat_amount'] ?? 0);
              $m_bill    = (float)($r['monthly_bill'] ?? 0);
              if ($m_bill <= 0) { $m_bill = $invAmount; }
              $billing_status = strtolower(trim((string)($r['billing_status'] ?? '')));
              if ($billing_status === '') { $billing_status = strtolower(trim((string)($r['inv_status'] ?? ''))); }
              $client_status = strtolower(trim((string)($r['client_status'] ?? '')));
              $is_left = ((int)($r['is_left'] ?? 0) === 1) || in_array($client_status, ['left','terminated'], true);
              if ($is_left) {
                $bill_badge = 'dark';
                $bill_label = 'Left';
              } else {
                if ($billing_status === '') {
                  if ($remain <= 0.0001) {
                    $billing_status = 'paid';
                  } elseif ($paid > 0.0001) {
                    $billing_status = 'partial';
                  } else {
                    $billing_status = 'due';
                  }
                } elseif (in_array($billing_status, ['unpaid','clear','cleared'], true)) {
                  $billing_status = ($billing_status === 'unpaid') ? 'due' : 'paid';
                } elseif ($billing_status === 'unpaid') {
                  $billing_status = 'due';
                }
                $bill_badge = $billing_status==='paid'?'success':($billing_status==='partial'?'warning text-dark':($billing_status==='due'?'danger':'secondary'));
                $bill_label = ucfirst($billing_status);
              }

              $exp_date = trim((string)($r['expiry_date'] ?? ''));
              $pay_date = trim((string)($r['last_payment_date'] ?? ''));
              $pay_ts = $pay_date !== '' ? strtotime($pay_date) : false;
              $pay_date_fmt = $pay_ts ? date('M-d-Y', $pay_ts) : '-';
              $period_active = (int)($r['period_active'] ?? 0);
              $period_label = $period_active === 1 ? 'Active' : 'Inactive';
              $period_badge = $period_active === 1 ? 'success' : 'secondary';

              $return = $cur_url;
              $invoice_id = (int)($r['invoice_id'] ?? 0);
              if ($invoice_id > 0) {
                $pay_url = 'payment_add.php?invoice_id='.$invoice_id.'&return='.rawurlencode($return);
                $pay_title = 'Pay (Full/Partial)';
              } else {
                $pay_url = 'invoice_new.php?client_id='.(int)$r['client_id'];
                $pay_title = 'Create Invoice';
              }
              $ledger_url = 'client_ledger.php?client_id='.(int)$r['client_id'].'&return='.rawurlencode($cur_url);
            ?>
            <tr class="<?= $inv_balance<=0.0001 ? 'table-success' : '' ?>">
              <td><input type="checkbox" aria-label="Select"></td>
              <td><?= (int)$r['client_id'] ?></td>
              <td>
                <div class="fw-semibold"><?= h($r['pppoe_id'] ?? '') ?></div>
                <div class="text-muted small"><?= h($r['ip_address'] ?? '') ?></div>
              </td>
              <td>
                <div class="fw-semibold"><a class="text-decoration-none" href="client_view.php?id=<?= (int)$r['client_id'] ?>"><?= h($r['client_name']) ?></a></div>
                <div class="text-muted small"><?= h($r['sub_zone'] ?? '') ?><?= ($r['box_name'] ?? '') ? ' • '.h($r['box_name']) : '' ?></div>
              </td>
              <td><?= h($r['mobile'] ?? '-') ?></td>
              <td><?= h($r['zone_name'] ?? '-') ?></td>
              <td><?= h($r['package_name'] ?? '-') ?></td>
              <td><?= h($exp_date) ?></td>
              <td class="text-end"> <?= number_format($m_bill, 2) ?></td>
              <td class="text-end"> <?= number_format($paid, 2) ?></td>
              <td class="text-end payable <?= $pay_class ?>"> <?= number_format($due_balance, 2) ?></td>
              <td class="text-end"> <?= number_format($advance, 2) ?></td>
              <td><?= h($pay_date_fmt) ?></td>
              <td><span class="badge bg-<?= $bill_badge ?>"><?= h($bill_label) ?></span></td>
              <td><span class="badge bg-<?= $period_badge ?>"><?= h($period_label) ?></span></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <?php if ($pay_url !== ''): ?>
                    <a class="btn btn-outline-success" title="<?= h($pay_title) ?>" href="<?= h($pay_url) ?>"><i class="bi bi-cash-coin"></i> Pay</a>
                  <?php else: ?>
                    <button class="btn btn-outline-success" title="<?= h($pay_title) ?>" type="button" disabled><i class="bi bi-cash-coin"></i> Pay</button>
                  <?php endif; ?>
                  <?php if ($invoice_id > 0): ?>
                    <a class="btn btn-outline-secondary" title="Invoice" href="invoice_view.php?id=<?= $invoice_id ?>"><i class="bi bi-receipt"></i></a>
                  <?php else: ?>
                    <button class="btn btn-outline-secondary" title="Invoice" type="button" disabled><i class="bi bi-receipt"></i></button>
                  <?php endif; ?>
                  <!-- <a class="btn btn-outline-info" title="View Ledger" href="<?= h($ledger_url) ?>"><i class="bi bi-eye"></i></a> -->
                </div>
              </td>
            </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="17" class="text-center text-muted">No data found for this month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if($pages>1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
          <?php $qs=$_GET; $prev=max(1,$page-1); $next=min($pages,$page+1); ?>
          <li class="page-item <?= $page<=1?'disabled':'' ?>"><?php $qs['page']=$prev; ?><a class="page-link" href="?<?= h(http_build_query($qs)) ?>">Previous</a></li>
          <?php $start=max(1,$page-2); $end=min($pages,$start+4); $start=max(1,min($start,$end-4));
          for($p=$start;$p<=$end;$p++): $qs['page']=$p; ?>
            <li class="page-item <?= $p==$page?'active':'' ?>"><a class="page-link" href="?<?= h(http_build_query($qs)) ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><?php $qs['page']=$next; ?><a class="page-link" href="?<?= h(http_build_query($qs)) ?>">Next</a></li>
        </ul>
      </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<!-- Discount Manager Modal -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Manage Discounts</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="disc-info" class="mb-3 small text-muted">Loading…</div>
        <div class="mb-2">
          <div class="fw-semibold">Invoice-level Discount</div>
          <div id="inv-disc-row" class="d-flex align-items-center justify-content-between border rounded p-2">
            <span id="inv-disc-amt"> 0.00</span>
            <button id="btn-clear-inv" class="btn btn-outline-danger btn-sm" disabled>
              <i class="bi bi-x-circle"></i> Clear
            </button>
          </div>
        </div>
        <div class="mt-3">
          <div class="fw-semibold mb-1">Payment Discounts</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>#ID</th><th>Date</th><th>Method</th><th class="text-end">Discount</th><th class="text-end">Action</th></tr></thead>
              <tbody id="pay-disc-tbody">
                <tr><td colspan="5" class="text-center text-muted">No rows.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto">Tip: Clearing will just set discount = 0 (soft delete).</small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
/* (বাংলা) পেমেন্ট হয়ে ফিরে এলে রিসিট অটো-ওপেন; ছোট Toast */
(function(){
  const q = new URLSearchParams(location.search);
  if (q.get('ok')==='1' && q.get('pid')) {
    const pid = q.get('pid');
    const w   = q.get('w') || '58';
    const url = `/public/receipt_payment.php?payment_id=${encodeURIComponent(pid)}&w=${encodeURIComponent(w)}&autoprint=1`;
    window.open(url, '_blank', 'noopener');
    const t = document.createElement('div');
    t.style.position='fixed'; t.style.left='50%'; t.style.top='20px'; t.style.transform='translateX(-50%)';
    t.style.background='#198754'; t.style.color='#fff'; t.style.padding='10px 14px';
    t.style.borderRadius='10px'; t.style.boxShadow='0 10px 30px rgba(0,0,0,.2)'; t.style.zIndex='9999';
    t.textContent='Payment saved';
    document.body.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translate(-50%,-10px)'; }, 1800);
    setTimeout(()=> t.remove(), 2300);
  }
})();

/* (বাংলা) Discount Manager Modal লজিক */
(function(){
  const CSRF = <?= json_encode($__csrf ?? '') ?>;
  const modalEl = document.getElementById('discountModal');
  const discInfo = document.getElementById('disc-info');
  const invAmtEl = document.getElementById('inv-disc-amt');
  const btnClearInv = document.getElementById('btn-clear-inv');
  const tbody = document.getElementById('pay-disc-tbody');
  let currentInvoiceId = 0;

  async function apiCall(payload){
    const res = await fetch('/public/billing_discount_api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(payload)
    });
    return res.json();
  }

  async function loadDiscounts(invId){
    discInfo.textContent = 'Loading…';
    invAmtEl.textContent = ' 0.00';
    btnClearInv.disabled = true;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';

    const data = await apiCall({ action:'list', invoice_id: invId, csrf: CSRF });
    if (!data.ok) {
      discInfo.textContent = 'Failed: ' + (data.error || 'Unknown error');
      return;
    }
    discInfo.textContent = 'Invoice ID: ' + invId;

    const inv = Number(data.invoice_discount || 0);
    invAmtEl.textContent = ' ' + inv.toFixed(2);
    btnClearInv.disabled = !(inv > 0.0001);

    const pays = Array.isArray(data.payments) ? data.payments : [];
    if (!pays.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payment discounts.</td></tr>';
    } else {
      tbody.innerHTML = '';
      pays.forEach(row => {
        const tr = document.createElement('tr');
        const id = Number(row.id);
        const disc = Number(row.discount || 0);
        const dt = row.pdate || '';
        const method = row.method || '';
        tr.innerHTML = `
          <td>${id}</td>
          <td>${dt ? dt : '-'}</td>
          <td>${method ? method : '-'}</td>
          <td class="text-end">৳ ${disc.toFixed(2)}</td>
          <td class="text-end">
            <button class="btn btn-outline-danger btn-sm btn-del-pay" data-pid="${id}">
              <i class="bi bi-x-circle"></i> Clear
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  }

  // Open modal
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.manage-discount');
    if (!btn) return;
    ev.preventDefault();
    currentInvoiceId = Number(btn.dataset.invoice) || 0;
    if (!currentInvoiceId) return;

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    loadDiscounts(currentInvoiceId);
  });

  // Clear invoice-level discount
  btnClearInv?.addEventListener('click', async function(){
    if (this.disabled || !currentInvoiceId) return;
    if (!confirm('Clear invoice-level discount?')) return;
    const data = await apiCall({ action:'clear_invoice', invoice_id: currentInvoiceId, csrf: CSRF });
    if (!data.ok) { alert(data.error || 'Failed'); return; }
    await loadDiscounts(currentInvoiceId);
    location.reload();
  });

  // Clear payment-level discount (event delegation)
  tbody?.addEventListener('click', async function(ev){
    const b = ev.target.closest('.btn-del-pay');
    if (!b) return;
    const pid = Number(b.dataset.pid) || 0;
    if (!pid) return;
    if (!confirm('Clear this payment discount?')) return;
    const data = await apiCall({ action:'delete_payment', payment_id: pid, csrf: CSRF });
    if (!data.ok) { alert(data.error || 'Failed'); return; }
    await loadDiscounts(currentInvoiceId);
    location.reload();
  });
})();
</script>

<?php if (isset($_GET['debug'])): ?>
<pre style="background:#111;color:#9f9;padding:12px;border-radius:8px;margin:12px">
invAmountCol    = <?= h($invAmountCol) . ($isNetInvAmount?' (NET)':' (GROSS)') . "\n" ?>
invoiceDiscCol  = <?= h($invoiceDiscCol ?? 'NULL') . "\n" ?>
hasPayDiscount  = <?= h($hasPayDiscount ? '1':'0') . "\n" ?>
payFk           = <?= h($payFk ?? 'NULL') . "\n" ?>
month_due_total = <?= number_format($month_due_total,2) . "\n" ?>
filtered_due_total = <?= number_format($filtered_due_total,2) . "\n" ?>
pages = <?= (int)$pages ?>, page = <?= (int)$page ?>, limit = <?= (int)$limit ?>, tab = <?= h($tab) . "\n" ?>
</pre>
<?php endif; ?>

</body>
</html>
