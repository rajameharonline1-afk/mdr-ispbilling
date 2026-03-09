<?php

declare(strict_types=1);

if (!function_exists('dashboard_tbl_exists')) {
    function dashboard_tbl_exists(PDO $pdo, string $table): bool
    {
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1');
            $st->execute([(string)$db, $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('dashboard_col_exists')) {
    function dashboard_col_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $st->execute([$column]);
            return (bool)$st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('dashboard_scalar')) {
    function dashboard_scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
    {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $v = $st->fetchColumn();
            return $v === false || $v === null ? $default : $v;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('dashboard_fetch_all')) {
    function dashboard_fetch_all(PDO $pdo, string $sql, array $params = []): array
    {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('dashboard_nf')) {
    function dashboard_nf($value, int $decimals = 0): string
    {
        return number_format((float)$value, $decimals, '.', ',');
    }
}

if (!function_exists('dashboard_money_expr')) {
    function dashboard_money_expr(PDO $pdo, string $table, array $candidates, string $fallback = '0'): string
    {
        foreach ($candidates as $col) {
            if (dashboard_col_exists($pdo, $table, $col)) {
                return "COALESCE($col,0)";
            }
        }
        return $fallback;
    }
}

if (!function_exists('dashboard_month_key_range')) {
    function dashboard_month_key_range(int $months): array
    {
        $keys = [];
        $labels = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime("first day of -$i month");
            $keys[] = date('Y-m', $ts);
            $labels[] = date('M', $ts);
        }
        return [$keys, $labels];
    }
}

if (!function_exists('dashboard_build_data')) {
    function dashboard_build_data(PDO $pdo): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');
        $monthStartDate = date('Y-m-01');
        $monthEndDate = date('Y-m-t');

        $hasClients = dashboard_tbl_exists($pdo, 'clients');
        $hasInvoices = dashboard_tbl_exists($pdo, 'invoices');
        $hasPayments = dashboard_tbl_exists($pdo, 'payments');
        $hasTickets = dashboard_tbl_exists($pdo, 'tickets');
        $hasUsers = dashboard_tbl_exists($pdo, 'users');
        $hasIncome = dashboard_tbl_exists($pdo, 'income');
        $hasExpenses = dashboard_tbl_exists($pdo, 'expenses');
        $hasWalletTransfers = dashboard_tbl_exists($pdo, 'wallet_transfers');
        $hasEmployeePayments = dashboard_tbl_exists($pdo, 'employee_payments');
        $hasRouters = dashboard_tbl_exists($pdo, 'routers');

        $taskTable = dashboard_tbl_exists($pdo, 'tasks') ? 'tasks' : (dashboard_tbl_exists($pdo, 'task_items') ? 'task_items' : '');

        $hasIsLeft = $hasClients && dashboard_col_exists($pdo, 'clients', 'is_left');
        $hasStatus = $hasClients && dashboard_col_exists($pdo, 'clients', 'status');
        $hasJoinDate = $hasClients && dashboard_col_exists($pdo, 'clients', 'join_date');
        $hasUpdatedAt = $hasClients && dashboard_col_exists($pdo, 'clients', 'updated_at');
        $hasIsOnline = $hasClients && dashboard_col_exists($pdo, 'clients', 'is_online');
        $hasBox = $hasClients && dashboard_col_exists($pdo, 'clients', 'box');
        $hasWhitelist = $hasClients && dashboard_col_exists($pdo, 'clients', 'is_whitelist');
        $hasSuspendByBilling = $hasClients && dashboard_col_exists($pdo, 'clients', 'suspend_by_billing');

        $activeFilter = $hasIsLeft ? 'COALESCE(is_left,0)=0' : '1=1';
        $leftFilter = $hasIsLeft ? 'COALESCE(is_left,0)=1' : ($hasStatus ? "LOWER(COALESCE(status,''))='left'" : '1=0');

        $totalClients = $hasClients ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter") : 0;
        $runningClients = ($hasClients && $hasStatus)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND LOWER(COALESCE(status,''))='active'")
            : $totalClients;
        $inactiveClients = ($hasClients && $hasStatus)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND LOWER(COALESCE(status,''))='inactive'")
            : 0;
        $waiverClients = ($hasClients && $hasWhitelist)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND COALESCE(is_whitelist,0)=1")
            : 0;
        $newClients = ($hasClients && $hasJoinDate)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND join_date BETWEEN ? AND ?", [$monthStartDate, $monthEndDate])
            : 0;

        $renewedClients = ($hasPayments && dashboard_col_exists($pdo, 'payments', 'client_id') && dashboard_col_exists($pdo, 'payments', 'paid_at'))
            ? (int)dashboard_scalar($pdo, 'SELECT COUNT(DISTINCT client_id) FROM payments WHERE paid_at BETWEEN ? AND ?', [$monthStart, $monthEnd])
            : 0;

        $deactivatedClients = ($hasClients && $hasStatus && $hasUpdatedAt)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND LOWER(COALESCE(status,''))='inactive' AND updated_at BETWEEN ? AND ?", [$monthStart, $monthEnd])
            : 0;

        $leftClients = $hasClients
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $leftFilter")
            : 0;

        $invoiceDateCol = 'created_at';
        if ($hasInvoices && !dashboard_col_exists($pdo, 'invoices', 'created_at') && dashboard_col_exists($pdo, 'invoices', 'invoice_date')) {
            $invoiceDateCol = 'invoice_date';
        }

        $invoiceAmountExpr = $hasInvoices ? dashboard_money_expr($pdo, 'invoices', ['payable', 'total', 'total_amount', 'amount']) : '0';
        $invoicePaidExpr = $hasInvoices ? dashboard_money_expr($pdo, 'invoices', ['paid_amount']) : '0';

        $billingClients = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'client_id'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(DISTINCT client_id) FROM invoices WHERE $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd])
            : 0;

        $paidClients = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(DISTINCT client_id) FROM invoices WHERE LOWER(status)='paid' AND $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd])
            : 0;

        $partiallyPaid = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(DISTINCT client_id) FROM invoices WHERE LOWER(status)='partial' AND $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd])
            : 0;

        $unpaidClients = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(DISTINCT client_id) FROM invoices WHERE LOWER(status)='unpaid' AND $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd])
            : 0;

        $onlineClients = ($hasClients && $hasIsOnline)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND COALESCE(is_online,0)=1")
            : 0;

        $blockedClients = ($hasClients && $hasSuspendByBilling)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND COALESCE(suspend_by_billing,0)=1")
            : (($hasClients && $hasStatus)
                ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND LOWER(COALESCE(status,''))='disabled'")
                : 0);

        $billDateExpire = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'due_date') && dashboard_col_exists($pdo, 'invoices', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE due_date < CURDATE() AND LOWER(status) IN ('unpaid','partial')")
            : 0;

        $unpaidExtension = 0;

        $totalPop = ($hasClients && $hasBox)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(DISTINCT box) FROM clients WHERE box IS NOT NULL AND box<>''")
            : ($hasRouters ? (int)dashboard_scalar($pdo, 'SELECT COUNT(*) FROM routers') : 0);

        $totalPopClients = ($hasClients && $hasBox)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND box IS NOT NULL AND box<>''")
            : 0;

        $enabledPopClients = ($hasClients && $hasBox && $hasStatus)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND box IS NOT NULL AND box<>'' AND LOWER(COALESCE(status,''))='active'")
            : 0;

        $disabledPopClients = ($hasClients && $hasBox && $hasStatus)
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM clients WHERE $activeFilter AND box IS NOT NULL AND box<>'' AND LOWER(COALESCE(status,'')) IN ('inactive','disabled')")
            : 0;

        $pendingTickets = ($hasTickets && dashboard_col_exists($pdo, 'tickets', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM tickets WHERE LOWER(status)='pending'")
            : 0;

        $processingTickets = ($hasTickets && dashboard_col_exists($pdo, 'tickets', 'status'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM tickets WHERE LOWER(status)='open'")
            : 0;

        $pendingTasks = 0;
        $processingTasks = 0;
        if ($taskTable !== '' && dashboard_col_exists($pdo, $taskTable, 'status')) {
            $pendingTasks = (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM $taskTable WHERE LOWER(status)='pending'");
            $processingTasks = (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM $taskTable WHERE LOWER(status)='processing'");
        }

        $monthlyBill = ($hasInvoices && $invoiceDateCol !== '')
            ? (float)dashboard_scalar($pdo, "SELECT SUM($invoiceAmountExpr) FROM invoices WHERE $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd], 0)
            : 0.0;

        $collectedBill = ($hasPayments && dashboard_col_exists($pdo, 'payments', 'amount') && dashboard_col_exists($pdo, 'payments', 'paid_at'))
            ? (float)dashboard_scalar($pdo, 'SELECT SUM(amount) FROM payments WHERE paid_at BETWEEN ? AND ?', [$monthStart, $monthEnd], 0)
            : 0.0;

        $discountAmount = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'discount'))
            ? (float)dashboard_scalar($pdo, "SELECT SUM(COALESCE(discount,0)) FROM invoices WHERE $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd], 0)
            : 0.0;

        $totalDue = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'status'))
            ? (float)dashboard_scalar($pdo, "SELECT SUM(GREATEST(($invoiceAmountExpr - $invoicePaidExpr), 0)) FROM invoices WHERE LOWER(status) IN ('unpaid','partial')", [], 0)
            : 0.0;

        $serviceSalesInvoice = ($hasInvoices && dashboard_col_exists($pdo, 'invoices', 'id'))
            ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM invoices WHERE $invoiceDateCol BETWEEN ? AND ?", [$monthStart, $monthEnd], 0)
            : 0;

        $productSalesInvoice = ($hasIncome && dashboard_col_exists($pdo, 'income', 'amount'))
            ? (float)dashboard_scalar($pdo, 'SELECT SUM(amount) FROM income WHERE DATE(created_at) BETWEEN ? AND ?', [$monthStartDate, $monthEndDate], 0)
            : 0.0;

        $incomeAmount = ($hasIncome && dashboard_col_exists($pdo, 'income', 'amount'))
            ? (float)dashboard_scalar($pdo, 'SELECT SUM(amount) FROM income WHERE DATE(created_at) BETWEEN ? AND ?', [$monthStartDate, $monthEndDate], 0)
            : 0.0;

        $expenseAmount = ($hasExpenses && dashboard_col_exists($pdo, 'expenses', 'amount'))
            ? (float)dashboard_scalar($pdo, 'SELECT SUM(amount) FROM expenses WHERE DATE(created_at) BETWEEN ? AND ?', [$monthStartDate, $monthEndDate], 0)
            : 0.0;

        $creditedAmount = $collectedBill;

        $popFund = ($hasWalletTransfers && dashboard_col_exists($pdo, 'wallet_transfers', 'amount') && dashboard_col_exists($pdo, 'wallet_transfers', 'status'))
            ? (float)dashboard_scalar($pdo, "SELECT SUM(amount) FROM wallet_transfers WHERE status='approved' AND DATE(created_at) BETWEEN ? AND ?", [$monthStartDate, $monthEndDate], 0)
            : 0.0;

        $popBill = $popFund;

        $receivableAmount = $totalDue;

        $bwidthProviderBill = 0.0;
        $bwidthProviderDue = 0.0;
        $bwidthPopBill = 0.0;

        $paidSalary = ($hasEmployeePayments && dashboard_col_exists($pdo, 'employee_payments', 'amount'))
            ? (float)dashboard_scalar($pdo, 'SELECT SUM(amount) FROM employee_payments WHERE DATE(payment_date) BETWEEN ? AND ?', [$monthStartDate, $monthEndDate], 0)
            : 0.0;

        $cashOnHand = $collectedBill + $incomeAmount - $expenseAmount - $paidSalary;

        $zoneProblems = [];
        if ($hasTickets && dashboard_col_exists($pdo, 'tickets', 'zone') && dashboard_col_exists($pdo, 'tickets', 'created_at')) {
            $zoneProblems = dashboard_fetch_all(
                $pdo,
                "SELECT COALESCE(NULLIF(zone,''),'Unknown') AS label, COUNT(*) AS total
                 FROM tickets
                 WHERE created_at BETWEEN ? AND ?
                 GROUP BY label
                 ORDER BY total DESC
                 LIMIT 8",
                [$monthStart, $monthEnd]
            );
        }

        $subZoneProblems = [];
        if ($hasTickets && $hasClients && dashboard_col_exists($pdo, 'tickets', 'client_id') && dashboard_col_exists($pdo, 'tickets', 'created_at') && dashboard_col_exists($pdo, 'clients', 'sub_zone')) {
            $subZoneProblems = dashboard_fetch_all(
                $pdo,
                "SELECT COALESCE(NULLIF(c.sub_zone,''),'Unknown') AS label, COUNT(*) AS total
                 FROM tickets t
                 LEFT JOIN clients c ON c.id = t.client_id
                 WHERE t.created_at BETWEEN ? AND ?
                 GROUP BY label
                 ORDER BY total DESC
                 LIMIT 8",
                [$monthStart, $monthEnd]
            );
        }

        [$pKeys, $pLabels] = dashboard_month_key_range(6);
        $monthlyProblemMap = [];
        if ($hasTickets && dashboard_col_exists($pdo, 'tickets', 'created_at')) {
            $rows = dashboard_fetch_all(
                $pdo,
                "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS total
                 FROM tickets
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                 GROUP BY ym"
            );
            foreach ($rows as $r) {
                $monthlyProblemMap[(string)($r['ym'] ?? '')] = (int)($r['total'] ?? 0);
            }
        }
        $monthlyProblemValues = [];
        foreach ($pKeys as $k) {
            $monthlyProblemValues[] = $monthlyProblemMap[$k] ?? 0;
        }

        $mostProblemSolverRows = [];
        if ($hasTickets && $hasUsers && dashboard_col_exists($pdo, 'tickets', 'assigned_to')) {
            $mostProblemSolverRows = dashboard_fetch_all(
                $pdo,
                "SELECT COALESCE(u.full_name, CONCAT('User #', t.assigned_to)) AS label, COUNT(*) AS total
                 FROM tickets t
                 LEFT JOIN users u ON u.id = t.assigned_to
                 WHERE t.assigned_to IS NOT NULL AND t.assigned_to > 0
                 GROUP BY t.assigned_to, u.full_name
                 ORDER BY total DESC
                 LIMIT 8"
            );
        }

        [$nKeys, $nLabels] = dashboard_month_key_range(3);
        $monthlyNewMap = [];
        if ($hasClients && $hasJoinDate) {
            $rows = dashboard_fetch_all(
                $pdo,
                "SELECT DATE_FORMAT(join_date,'%Y-%m') AS ym, COUNT(*) AS total
                 FROM clients
                 WHERE join_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
                 GROUP BY ym"
            );
            foreach ($rows as $r) {
                $monthlyNewMap[(string)($r['ym'] ?? '')] = (int)($r['total'] ?? 0);
            }
        }
        $monthlyNewValues = [];
        foreach ($nKeys as $k) {
            $monthlyNewValues[] = $monthlyNewMap[$k] ?? 0;
        }

        [$cpKeys, $cpLabels] = dashboard_month_key_range(12);
        $companyPerformanceValues = [];
        if ($hasClients && $hasJoinDate) {
            foreach ($cpKeys as $ym) {
                $monthLastDate = date('Y-m-t', strtotime($ym . '-01'));
                if ($hasIsLeft && dashboard_col_exists($pdo, 'clients', 'left_at')) {
                    $companyPerformanceValues[] = (int)dashboard_scalar(
                        $pdo,
                        "SELECT COUNT(*) FROM clients
                         WHERE join_date <= ?
                           AND (COALESCE(is_left,0)=0 OR left_at IS NULL OR DATE(left_at) > ?)",
                        [$monthLastDate, $monthLastDate],
                        0
                    );
                } elseif ($hasStatus) {
                    $companyPerformanceValues[] = (int)dashboard_scalar(
                        $pdo,
                        "SELECT COUNT(*) FROM clients
                         WHERE join_date <= ? AND LOWER(COALESCE(status,''))='active'",
                        [$monthLastDate],
                        0
                    );
                } else {
                    $companyPerformanceValues[] = (int)dashboard_scalar(
                        $pdo,
                        'SELECT COUNT(*) FROM clients WHERE join_date <= ?',
                        [$monthLastDate],
                        0
                    );
                }
            }
        } else {
            $companyPerformanceValues = array_fill(0, count($cpLabels), 0);
        }

        $topUnpaidClients = [];
        if ($hasInvoices && $hasClients && dashboard_col_exists($pdo, 'invoices', 'client_id')) {
            $topUnpaidClients = dashboard_fetch_all(
                $pdo,
                "SELECT
                    c.id,
                    COALESCE(NULLIF(c.client_code,''), CONCAT('C', c.id)) AS user_name,
                    COALESCE(c.mobile,'') AS mobile,
                    ROUND(MAX($invoiceAmountExpr), 2) AS bill_amount,
                    ROUND(SUM(GREATEST(($invoiceAmountExpr - $invoicePaidExpr),0)), 2) AS due_amount
                 FROM invoices i
                 INNER JOIN clients c ON c.id = i.client_id
                 WHERE LOWER(COALESCE(i.status,'')) IN ('unpaid','partial')
                 GROUP BY c.id, c.client_code, c.mobile
                 HAVING due_amount > 0
                 ORDER BY due_amount DESC
                 LIMIT 20"
            );
        }

        $cards = [
            [
                'key' => 'total_client', 'title' => 'Total Client', 'value' => dashboard_nf($totalClients),
                'note' => 'All over the number of clients at present.', 'variant' => 'blue', 'icon' => 'bi-people-fill', 'href' => '/public/clients.php'
            ],
            [
                'key' => 'running_client', 'title' => 'Running Clients', 'value' => dashboard_nf($runningClients),
                'note' => 'Number of clients without LeftOut status.', 'variant' => 'green', 'icon' => 'bi-person-check-fill', 'href' => '/public/client_list_by_status.php?status=active'
            ],
            [
                'key' => 'inactive_client', 'title' => 'Inactive Clients', 'value' => dashboard_nf($inactiveClients),
                'note' => 'Number of clients whom status are inactive.', 'variant' => 'purple', 'icon' => 'bi-person-x-fill', 'href' => '/public/client_list_by_status.php?status=inactive'
            ],
            [
                'key' => 'waiver_client', 'title' => 'Waiver Clients', 'value' => dashboard_nf($waiverClients),
                'note' => 'Number of clients those are free/personal.', 'variant' => 'dark', 'icon' => 'bi-person-fill-dash', 'href' => '/public/clients.php'
            ],
            [
                'key' => 'new_client', 'title' => 'New Client', 'value' => dashboard_nf($newClients),
                'note' => 'Monthly number of clients those are new.', 'variant' => 'blue', 'icon' => 'bi-person-plus-fill', 'href' => '/public/clients.php?join_from=' . urlencode($monthStartDate) . '&join_to=' . urlencode($monthEndDate)
            ],
            [
                'key' => 'renewed_client', 'title' => 'Renewed Clients', 'value' => dashboard_nf($renewedClients),
                'note' => 'Monthly number of newly renewed clients.', 'variant' => 'green', 'icon' => 'bi-people-fill', 'href' => '/public/report_payments.php'
            ],
            [
                'key' => 'deactivated_client', 'title' => 'Deactivated Clients', 'value' => dashboard_nf($deactivatedClients),
                'note' => 'Monthly number of newly deactivated clients.', 'variant' => 'purple', 'icon' => 'bi-shield-x', 'href' => '/public/client_list_by_status.php?status=inactive'
            ],
            [
                'key' => 'left_client', 'title' => 'Left Clients', 'value' => dashboard_nf($leftClients),
                'note' => 'Number of clients those are not exist.', 'variant' => 'dark', 'icon' => 'bi-person-dash-fill', 'href' => '/public/client_list_by_status.php?status=left'
            ],
            [
                'key' => 'billing_client', 'title' => 'Billing Clients', 'value' => dashboard_nf($billingClients),
                'note' => 'Number of clients whom bill generated.', 'variant' => 'blue', 'icon' => 'bi-person-check-fill', 'href' => '/public/billing.php'
            ],
            [
                'key' => 'paid_client', 'title' => 'Paid Clients', 'value' => dashboard_nf($paidClients),
                'note' => 'Number of clients those are fully paid.', 'variant' => 'green', 'icon' => 'bi-receipt-cutoff', 'href' => '/public/invoices.php?status=paid'
            ],
            [
                'key' => 'partially_paid_client', 'title' => 'Partially Paid', 'value' => dashboard_nf($partiallyPaid),
                'note' => 'Number of clients those are partially paid.', 'variant' => 'purple', 'icon' => 'bi-cash-coin', 'href' => '/public/invoices.php?status=unpaid'
            ],
            [
                'key' => 'unpaid_client', 'title' => 'Unpaid Clients', 'value' => dashboard_nf($unpaidClients),
                'note' => 'Number of clients those are fully unpaid.', 'variant' => 'dark', 'icon' => 'bi-search-dollar', 'href' => '/public/due_report_pro.php'
            ],
            [
                'key' => 'online_client', 'title' => 'Online Clients', 'value' => dashboard_nf($onlineClients),
                'note' => 'Number of clients those are connected.', 'variant' => 'blue', 'icon' => 'bi-bar-chart-steps', 'href' => '/public/clients_online.php'
            ],
            [
                'key' => 'blocked_client', 'title' => 'Blocked Clients', 'value' => dashboard_nf($blockedClients),
                'note' => 'Number of clients those are blocked/disabled.', 'variant' => 'green', 'icon' => 'bi-person-slash', 'href' => '/public/suspended_clients.php'
            ],
            [
                'key' => 'bill_date_expire', 'title' => 'Bill Date Expire', 'value' => dashboard_nf($billDateExpire),
                'note' => 'Number of clients whom billing date expired.', 'variant' => 'purple', 'icon' => 'bi-person-clock', 'href' => '/public/due_report_pro.php'
            ],
            [
                'key' => 'unpaid_extension', 'title' => 'Unpaid Extension', 'value' => dashboard_nf($unpaidExtension),
                'note' => 'Clients those are expired but extended.', 'variant' => 'dark', 'icon' => 'bi-clock-history', 'href' => '/public/invoices.php'
            ],
            [
                'key' => 'total_pop', 'title' => 'Total Pop', 'value' => dashboard_nf($totalPop),
                'note' => 'Total number of POPs you have.', 'variant' => 'blue', 'icon' => 'bi-person-vcard-fill', 'href' => '/public/settings.php?type=box'
            ],
            [
                'key' => 'total_pop_clients', 'title' => 'Total Pop Clients', 'value' => dashboard_nf($totalPopClients),
                'note' => 'Number of exported and unexported POP clients.', 'variant' => 'green', 'icon' => 'bi-people-fill', 'href' => '/public/clients.php'
            ],
            [
                'key' => 'enabled_pop_client', 'title' => 'Enabled Pop Clients', 'value' => dashboard_nf($enabledPopClients),
                'note' => 'Number of exported POP clients those are enabled.', 'variant' => 'purple', 'icon' => 'bi-person-check-fill', 'href' => '/public/client_list_by_status.php?status=active'
            ],
            [
                'key' => 'disabled_pop_client', 'title' => 'Disabled Pop Clients', 'value' => dashboard_nf($disabledPopClients),
                'note' => 'Number of exported POP clients those are disabled.', 'variant' => 'dark', 'icon' => 'bi-person-x-fill', 'href' => '/public/client_list_by_status.php?status=inactive'
            ],
        ];

        $financeCards = [
            ['title' => 'Monthly Bill', 'value' => dashboard_nf($monthlyBill), 'note' => 'Current month total customer monthly bill.', 'variant' => 'blue', 'icon' => 'bi-calendar3'],
            ['title' => 'Collected Bill', 'value' => dashboard_nf($collectedBill), 'note' => 'Current month total received amount.', 'variant' => 'green', 'icon' => 'bi-calendar-check'],
            ['title' => 'Discount', 'value' => dashboard_nf($discountAmount), 'note' => 'Current month total discount amount.', 'variant' => 'purple', 'icon' => 'bi-currency-dollar'],
            ['title' => 'Total Due', 'value' => dashboard_nf($totalDue), 'note' => 'Total due bill of client.', 'variant' => 'dark', 'icon' => 'bi-file-earmark-ruled'],
            ['title' => 'Service Sales Invoice', 'value' => dashboard_nf($serviceSalesInvoice), 'note' => 'Monthly service sales invoice count.', 'variant' => 'blue', 'icon' => 'bi-cash-coin'],
            ['title' => 'Product Sales Invoice', 'value' => dashboard_nf($productSalesInvoice), 'note' => 'Current month total product sales.', 'variant' => 'green', 'icon' => 'bi-graph-up-arrow'],
            ['title' => 'Income', 'value' => dashboard_nf($incomeAmount), 'note' => 'Current month total income amount.', 'variant' => 'purple', 'icon' => 'bi-graph-up'],
            ['title' => 'Expense', 'value' => dashboard_nf($expenseAmount), 'note' => 'Current month total expense amount.', 'variant' => 'dark', 'icon' => 'bi-graph-down'],
            ['title' => 'Credited Amount', 'value' => dashboard_nf($creditedAmount, 2), 'note' => 'Monthly credited amount.', 'variant' => 'blue', 'icon' => 'bi-currency-exchange'],
            ['title' => 'POP Fund', 'value' => dashboard_nf($popFund), 'note' => 'Monthly given fund amount to POPs.', 'variant' => 'green', 'icon' => 'bi-credit-card-2-front'],
            ['title' => 'POP Bill', 'value' => dashboard_nf($popBill), 'note' => 'Monthly received amount from POPs.', 'variant' => 'purple', 'icon' => 'bi-cash-stack'],
            ['title' => 'Receivable Amount', 'value' => dashboard_nf($receivableAmount, 2), 'note' => 'Monthly receivable amount from POPs.', 'variant' => 'dark', 'icon' => 'bi-funnel-fill'],
            ['title' => 'B.Width Provider Bill', 'value' => dashboard_nf($bwidthProviderBill), 'note' => 'Monthly paid amount to bandwidth providers.', 'variant' => 'blue', 'icon' => 'bi-collection'],
            ['title' => 'B.Width Provider Due', 'value' => dashboard_nf($bwidthProviderDue, 2), 'note' => 'All over bandwidth providers payable due.', 'variant' => 'green', 'icon' => 'bi-credit-card'],
            ['title' => 'B.Width POP Bill', 'value' => dashboard_nf($bwidthPopBill), 'note' => 'Monthly received amount from bandwidth POPs.', 'variant' => 'purple', 'icon' => 'bi-credit-card-2-front-fill'],
            ['title' => 'Paid Salary', 'value' => dashboard_nf($paidSalary), 'note' => 'Current month total paid salary amount.', 'variant' => 'dark', 'icon' => 'bi-cash'],
            ['title' => 'SMS Balance', 'value' => dashboard_nf(0, 2), 'note' => 'This is your total SMS balance amount.', 'variant' => 'blue', 'icon' => 'bi-envelope'],
            ['title' => 'Purchase Payable Due', 'value' => dashboard_nf(0), 'note' => 'All over inventory purchase payable due.', 'variant' => 'green', 'icon' => 'bi-credit-card-fill'],
            ['title' => 'Purchase Paid Amount', 'value' => dashboard_nf(0), 'note' => 'Current month total inventory purchase paid amount.', 'variant' => 'purple', 'icon' => 'bi-credit-card-2-front'],
            ['title' => 'Cash On Hand', 'value' => dashboard_nf($cashOnHand), 'note' => 'Current month total cash on hand amount.', 'variant' => 'dark', 'icon' => 'bi-cash-stack'],
        ];

        return [
            'cards' => $cards,
            'finance_cards' => $financeCards,
            'support_cards' => [
                ['title' => 'Pending Tickets', 'value' => dashboard_nf($pendingTickets), 'note' => "Number of support tickets that's are pending.", 'variant' => 'danger', 'icon' => 'bi-ticket-detailed'],
                ['title' => 'Processing Tickets', 'value' => dashboard_nf($processingTickets), 'note' => "Number of support tickets that's are processing.", 'variant' => 'warning', 'icon' => 'bi-arrow-repeat'],
                ['title' => 'Pending Task', 'value' => dashboard_nf($pendingTasks), 'note' => "Number of created task that's are pending.", 'variant' => 'danger', 'icon' => 'bi-list-task'],
                ['title' => 'Processing Task', 'value' => dashboard_nf($processingTasks), 'note' => "Number of task that's are processing.", 'variant' => 'warning', 'icon' => 'bi-arrow-repeat'],
            ],
            'charts' => [
                'zone_problem' => [
                    'labels' => array_values(array_map(static fn($r) => (string)($r['label'] ?? 'Unknown'), $zoneProblems)),
                    'values' => array_values(array_map(static fn($r) => (int)($r['total'] ?? 0), $zoneProblems)),
                ],
                'sub_zone_problem' => [
                    'labels' => array_values(array_map(static fn($r) => (string)($r['label'] ?? 'Unknown'), $subZoneProblems)),
                    'values' => array_values(array_map(static fn($r) => (int)($r['total'] ?? 0), $subZoneProblems)),
                ],
                'monthly_problem' => [
                    'labels' => $pLabels,
                    'values' => $monthlyProblemValues,
                ],
                'most_problem_solver' => [
                    'labels' => array_values(array_map(static fn($r) => (string)($r['label'] ?? 'Unknown'), $mostProblemSolverRows)),
                    'values' => array_values(array_map(static fn($r) => (int)($r['total'] ?? 0), $mostProblemSolverRows)),
                ],
                'monthly_new_client' => [
                    'labels' => $nLabels,
                    'values' => $monthlyNewValues,
                ],
                'company_performance' => [
                    'labels' => $cpLabels,
                    'values' => $companyPerformanceValues,
                ],
            ],
            'top_unpaid_clients' => $topUnpaidClients,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'source' => 'dashboard_service',
                'today' => $today,
            ],
        ];
    }
}
