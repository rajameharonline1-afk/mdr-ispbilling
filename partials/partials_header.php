<?php // /partials/partials_header.php
/* (বাংলা) সেশন + পেজ টাইটেল */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$page_title = trim($page_title ?? '') ?: '';
/* Active slug from page (optional) */
$__active = trim($GLOBALS['_active'] ?? ''); // e.g., 'dashboard', 'clients', 'billing' ...
?>

<?php
/* (বাংলা) সেশন/হেল্পার সেফগার্ড */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!function_exists('h')) {
  function h($s)
  {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

/* (বাংলা) লগিন ইউজারের ডিসপ্লে নেম/রোল */
$__u   = $_SESSION['user'] ?? [];
$__nm  = $__u['full_name'] ?? $__u['name'] ?? $__u['username'] ?? 'User';
$__rl  = $__u['role'] ?? 'user';
require_once __DIR__ . '/../app/settings_store.php';
$__brand_name = trim((string)settings_get('company_name', '')) ?: 'Rajamehar Online';
$__brand_logo = trim((string)settings_get('company_logo', '')) ?: '/assets/img/logo.png';
$__brand_logo_ok = $__brand_logo !== '' && is_file($_SERVER['DOCUMENT_ROOT'] . $__brand_logo);

$__req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__req_query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '';
$__req_qs = [];
if ($__req_query !== '') {
  parse_str($__req_query, $__req_qs);
}

if (!function_exists('resolve_active_slug')) {
  function resolve_active_slug(string $path, array $qs): string
  {
    $path = rtrim($path, '/');
    if ($path === '') $path = '/';
    if ($path === '/public/client_list_by_status.php') {
      $status = strtolower((string)($qs['status'] ?? ''));
      return match ($status) {
        'active' => 'clients_active',
        'inactive' => 'clients_inactive',
        'expired' => 'clients_expired',
        'left' => 'clients_left',
        default => 'clients',
      };
    }
    if ($path === '/public/invoices.php') {
      $status = strtolower((string)($qs['status'] ?? ''));
      return $status === 'paid' ? 'invoices_paid' : 'invoices';
    }
    if ($path === '/public/collections.php') {
      $when = strtolower((string)($qs['when'] ?? ''));
      return $when === 'today' ? 'collections_today' : 'collections';
    }
    if ($path === '/public/settings.php') {
      $type = strtolower((string)($qs['type'] ?? ''));
      return match ($type) {
        'area' => 'settings_location_area',
        'sub_zone' => 'settings_location_sub_zone',
        'box' => 'settings_location_box',
        default => 'settings',
      };
    }
    $map = [
      '/public/index.php' => 'dashboard',
      '/public/client_add.php' => 'client_add',
      '/public/clients.php' => 'clients',
      '/public/all_clientt_info.php' => 'clients_info',
      '/public/clients_online.php' => 'clients_online',
      '/public/clients_offline.php' => 'clients_offline',
      '/public/suspended_clients.php' => 'clients_auto_inactive',
      '/public/client_ledger.php' => 'clients_ledger',
      '/public/billing.php' => 'billing',
      '/public/due_report_pro.php' => 'due_report_pro',
      '/public/invoice_new.php' => 'invoice_new',
      '/public/wallets.php' => 'wallets',
      '/public/wallets_dashboard.php' => 'wallets_dashboard',
      '/public/wallet_approvals.php' => 'wallet_approvals',
      '/public/wallet_settlement.php' => 'wallet_settlement',
      '/public/report_payments.php' => 'payment_report',
      '/public/payment_report.php' => 'payment_report_invoice',
      '/public/income_expense.php' => 'income_expense',
      '/public/expenses.php' => 'expenses',
      '/public/expense_add.php' => 'expense_add',
      '/public/router_add.php' => 'router_add',
      '/public/routers.php' => 'routers',
      '/public/packages.php' => 'packages',
      '/public/import_clients.php' => 'import_clients_csv',
      '/public/import_mikrotik_client.php' => 'import_mikrotik_client',
      '/olt/index.php' => 'olts',
      '/public/olt_mac_table.php' => 'olt_mac',
      '/public/admin_tools.php' => 'admin_tools',
      '/public/onu_monitor.php' => 'onu_monitor',
      '/public/users_permission.php' => 'users_permission',
      '/public/users.php' => 'users',
      '/public/hr/employees.php' => 'hr_employees',
      '/public/hr/employee_toggle.php' => 'hr_employee_toggle',
      '/public/tickets.php' => 'tickets',
      '/public/due_report.php' => 'due_report',
      '/public/report_package_wise.php' => 'report_package_wise',
      '/public/notifications.php' => 'sms',
      '/public/sms_individual.php' => 'sms_individual',
      '/public/sms_templates.php' => 'sms_templates',
      '/public/sms_groups.php' => 'sms_groups',
      '/public/sms_send.php' => 'sms_send',
      '/public/sms_gateway.php' => 'sms_gateway',
      '/tg/settings.php' => 'settings_tg',
      '/public/settings_bkash.php' => 'settings_bkash',
      '/public/webhook_payments.php' => 'bkash_webhook',
      '/public/process_manual.php' => 'bkash_manual',
      '/public/settings_company.php' => 'settings_company',
    ];
    return $map[$path] ?? '';
  }
}

$__resolved_active = resolve_active_slug($__req_path, $__req_qs);
if ($__resolved_active !== '') {
  $__active = $__resolved_active;
}
?>

<!doctype html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- Site CSS (keep your global overrides) -->
  <?php
  $css_main_ver = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
  $css_mod_ver  = @filemtime(__DIR__ . '/../assets/css/custom_modern.css') ?: time();
  ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo $css_main_ver; ?>">
  <link rel="stylesheet" href="/assets/css/custom_modern.css?v=<?php echo $css_mod_ver; ?>">
</head>

<body class="app-shell">

  <!-- =============== Topbar =============== -->
  <nav class="navbar topbar sticky-top">
    <div class="container-fluid gap-2">
      <div class="d-flex align-items-center gap-2 flex-grow-1">
        <!-- Mobile: open sidebar -->
        <button class="btn btn-icon d-md-none" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open sidebar">
          <i class="bi bi-list"></i>
        </button>

        <!-- Desktop: collapse/expand -->
        <button id="btnSidebarToggle" class="btn btn-icon d-none d-md-inline-flex" type="button" title="Toggle sidebar">
          <i class="bi bi-layout-sidebar"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="/public/index.php">
          <?php if ($__brand_logo_ok): ?>
            <img src="<?php echo h($__brand_logo); ?>" alt="<?php echo h($__brand_name); ?>">
          <?php else: ?>
            <span class="fw-semibold text-white"><?php echo h($__brand_name); ?></span>
          <?php endif; ?>
        </a>

        <!-- Quick Search (desktop) -->
        <form class="topbar-search d-none d-md-flex align-items-center position-relative ms-4"
          id="nav-search-form" action="/public/clients.php" method="get" autocomplete="off" role="search">
          <div class="input-group input-group-sm" id="nav-search-group">
            <!-- <span class="input-group-text py-1"><i class="bi bi-search"></i></span> -->
            <input type="text" class="form-control form-control-sm" id="nav-search-input"
              name="search" placeholder="Search Customer">
            <button class="btn btn-sm" type="submit"><i class="bi bi-search"></i></button>
          </div>
          <div id="nav-suggest" class="suggest-box d-none"></div>
        </form>
      </div>

      <div class="d-flex align-items-center gap-2 ms-auto">
        <a href="/public/tickets.php" class="btn btn-outline-light btn-sm d-none d-sm-inline-flex">
          <i class="bi bi-life-preserver"></i> Tickets
        </a>

        <div class="dropdown">
          <a class="d-flex align-items-center gap-2 text-decoration-none text-white dropdown-toggle"
            href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="btn btn-icon d-none d-lg-inline-flex"><i class="bi bi-person-circle"></i></span>
            <span class="d-none d-md-flex flex-column lh-sm">
              <span class="fw-semibold"><?php echo h($__nm); ?></span>
              <span class="small opacity-75"><?php echo h($__rl); ?></span>
            </span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="/public/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li><a class="dropdown-item text-danger" href="/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <!-- =============== Mobile Quick Search =============== -->
  <div class="mobile-search p-2 d-flex d-md-none">
    <form class="w-100 position-relative" id="nav-search-form-m" action="/public/clients.php" method="get" autocomplete="off" role="search">
      <div class="input-group input-group-sm" id="nav-search-group-m">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" id="nav-search-input-m" name="search" placeholder="Search...">
        <button class="btn" type="submit">Go</button>
      </div>
      <div id="nav-suggest-m" class="suggest-box d-none"></div>
    </form>
  </div>

  <script>
  (function(){
    const endpoint = '/public/search_logic.php';
    const minChars = 2;

    function esc(s){
      return (s || '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
      }[m]));
    }

    function initLiveSuggest(inputId, suggestId){
      const input = document.getElementById(inputId);
      const suggest = document.getElementById(suggestId);
      if (!input || !suggest) return;

      let timer = null;
      let active = -1;

      function hide(){ suggest.classList.add('d-none'); active = -1; }
      function show(){ suggest.classList.remove('d-none'); }
      function clear(){ suggest.innerHTML = ''; active = -1; }

      function highlight(text, q){
        const hay = String(text || '');
        const needle = String(q || '').trim();
        if (!needle) return esc(hay);
        const hayLower = hay.toLowerCase();
        const needleLower = needle.toLowerCase();
        const idx = hayLower.indexOf(needleLower);
        if (idx === -1) return esc(hay);
        const before = hay.slice(0, idx);
        const match = hay.slice(idx, idx + needle.length);
        const after = hay.slice(idx + needle.length);
        return `${esc(before)}<span class="suggest-match">${esc(match)}</span>${esc(after)}`;
      }

      function render(items, q){
        clear();
        if (!items || !items.length){ hide(); return; }
        suggest.classList.add('list-group');
        items.forEach((row) => {
          const id = row.client_id || '';
          const code = row.client_code || row.pppoe_username || row.client_id || '';
          const label = code && id && String(code) !== String(id) ? `${code}(${id})` : (code || id || 'Unknown');
          const item = document.createElement('button');
          item.type = 'button';
          item.className = 'list-group-item list-group-item-action suggest-item';
          item.innerHTML = highlight(label, q);
          item.addEventListener('click', () => {
            if (!id) return;
            window.location.href = '/public/client_view.php?id=' + encodeURIComponent(id);
          });
          suggest.appendChild(item);
        });
        show();
      }

      async function fetchData(q){
        const res = await fetch(`${endpoint}?query=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }

      function onInput(){
        const q = input.value.trim();
        if (q.length < minChars){ clear(); hide(); return; }
        clearTimeout(timer);
        timer = setTimeout(async () => {
          try {
            const data = await fetchData(q);
            if (input.value.trim() !== q) return;
            render(data.results || [], q);
          } catch (e) {
            clear(); hide();
          }
        }, 200);
      }

      function onKey(e){
        if (suggest.classList.contains('d-none')) return;
        const items = Array.from(suggest.querySelectorAll('.suggest-item'));
        if (!items.length) return;
        if (e.key === 'ArrowDown'){ e.preventDefault(); active = (active + 1) % items.length; }
        else if (e.key === 'ArrowUp'){ e.preventDefault(); active = (active - 1 + items.length) % items.length; }
        else if (e.key === 'Enter' && active >= 0){ e.preventDefault(); items[active].click(); return; }
        else if (e.key === 'Escape'){ hide(); return; }
        items.forEach(el => el.classList.remove('active'));
        if (active >= 0) items[active].classList.add('active');
      }

      document.addEventListener('click', (e) => { if (!suggest.contains(e.target) && e.target !== input) hide(); });
      input.addEventListener('input', onInput);
      input.addEventListener('focus', onInput);
      input.addEventListener('keydown', onKey);
    }

    document.addEventListener('DOMContentLoaded', () => {
      initLiveSuggest('nav-search-input', 'nav-suggest');
      initLiveSuggest('nav-search-input-m', 'nav-suggest-m');
    });
  })();
  </script>

  <?php
  /* (বাংলা) ছোট হেল্পার: approval badge কাউন্ট */
  @require_once __DIR__ . '/../app/db.php';
  if (!function_exists('can_approve')) {
    function can_approve(): bool
    {
      $is_admin = (int)($_SESSION['user']['is_admin'] ?? 0);
      $role = strtolower((string)($_SESSION['user']['role'] ?? ''));
      return $is_admin === 1 || in_array($role, ['admin', 'superadmin', 'manager', 'accounts', 'accountant', 'billing'], true);
    }
  }
  $__pending_approvals = 0;
  try {
    $pdo = db();
    $__pending_approvals = (int)$pdo->query("SELECT COUNT(*) FROM wallet_transfers WHERE status='pending'")->fetchColumn();
  } catch (Throwable $e) {
    $__pending_approvals = 0;
  }

  /* (বাংলা) active helper */
  function is_active($slug)
  {
    global $__active;
    return ($__active === $slug) ? ' active' : '';
  }

  /* collapse states by section */
  $openClients  = in_array($__active, ['clients', 'clients_info', 'clients_online', 'clients_offline', 'clients_active', 'clients_inactive', 'clients_expired', 'clients_left', 'clients_auto_inactive', 'clients_ledger', 'client_add'], true);
  $openBilling  = in_array($__active, ['billing', 'due_report', 'due_report_pro', 'invoices', 'invoices_paid', 'invoice_new', 'collections', 'bkash', 'bkash_webhook', 'bkash_manual', 'collections_today'], true);
  $openRouters  = in_array($__active, ['routers', 'router_add', 'packages', 'import_clients_csv', 'import_mikrotik_client'], true);
  $openOLT      = in_array($__active, ['olts', 'olt_tools', 'onu_tools', 'olt_mac', 'olt_sfp', 'olt_pon', 'onu_monitor', 'admin_tools'], true);
  $openAccounts = in_array($__active, ['wallets', 'wallets_dashboard', 'wallet_approvals', 'wallet_settlement', 'payment_report', 'payment_report_invoice', 'income_expense', 'expenses', 'expense_add'], true);
  $openSMS      = in_array($__active, ['sms', 'sms_individual', 'sms_templates', 'sms_groups', 'sms_send', 'sms_gateway'], true);
  $openReports  = in_array($__active, ['reports', 'tickets', 'due_report', 'due_report_pro', 'report_package_wise'], true);
  /* FIX: HR open state was missing */
  $openHR       = in_array($__active, ['users_permission', 'users', 'hr_employees', 'hr_employee_toggle'], true);
  $openSettings = in_array($__active, ['settings', 'settings_tg', 'settings_bkash', 'settings_company', 'settings_location_area', 'settings_location_sub_zone', 'settings_location_box'], true);
  $openLocation = in_array($__active, ['settings_location_area', 'settings_location_sub_zone', 'settings_location_box'], true);

  function hasPermission($permissionKey)
  {
    global $pdo; // PDO database connection
    if (($_SESSION['role_id'] ?? null) == 1) { // super admin short-circuit
      return true;
    }
    if (!isset($_SESSION['role_id'])) {
      return false;
    }
    $roleId = (int)$_SESSION['role_id'];
    $sql = "
    SELECT p.perm_key
    FROM permissions p
    INNER JOIN role_permissions rp ON rp.permission_id = p.id
    WHERE rp.role_id = :role_id AND p.perm_key = :perm_key
    LIMIT 1
  ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':role_id' => $roleId, ':perm_key' => $permissionKey]);
    return $stmt->rowCount() > 0;
  }
  ?>

  <!-- =============== Layout =============== -->
  <div class="layout">

    <!-- =============== Sidebar (offcanvas-md) =============== -->
    <aside class="offcanvas offcanvas-start offcanvas-md sidebar" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarLabel">
      <div class="offcanvas-header border-bottom border-secondary d-md-none">
        <h6 class="offcanvas-title m-0" id="sidebarLabel">
          <i class="bi bi-router-fill me-1"></i> <?php echo h($__brand_name); ?>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
      </div>

      <div class="offcanvas-body p-0">
        <nav class="sidebar-scroll" id="sidebarAccordion">
          <ul class="list-unstyled m-0">

            <!-- Dashboard -->
            <?php if (hasPermission('view.dashboard')) { ?>
              <li>
                <a href="/public/index.php" class="btn btn-menu<?php echo is_active('dashboard'); ?>">
                  <i class="bi bi-speedometer2"></i> <span class="menu-label">Dashboard</span>
                </a>
              </li>
            <?php } ?>

            <!-- Clients -->
            <?php if (hasPermission('client.view')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openClients ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#clientsMenu" aria-expanded="<?php echo $openClients ? 'true' : 'false'; ?>">
                  <i class="bi bi-people-fill"></i> <span class="menu-label">Clients</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>

                <ul class="collapse submenu list-unstyled <?php echo $openClients ? 'show' : ''; ?>" id="clientsMenu">
                  <li><a href="/public/client_add.php" class="btn btn-menu<?php echo is_active('client_add'); ?>"><i class="bi bi-plus-circle"></i> Add Client</a></li>
                  <li><a href="/public/clients.php" class="btn btn-menu<?php echo is_active('clients'); ?>"><i class="bi bi-list-ul"></i> All Clients</a></li>
                  <li><a href="/public/all_clientt_info.php" class="btn btn-menu<?php echo is_active('clients_info'); ?>"><i class="bi bi-list-ul"></i> All Client Info</a></li>


                  <!-- <li><a href="/public/clients_online.php" class="btn btn-menu<?php echo is_active('clients_online'); ?>"><i class="bi bi-wifi"></i> Online Clients</a></li>
              <li><a href="/public/clients_offline.php" class="btn btn-menu<?php echo is_active('clients_offline'); ?>"><i class="bi bi-wifi-off"></i> Offline Clients</a></li>
              <li><a href="/public/client_list_by_status.php?status=active" class="btn btn-menu<?php echo is_active('clients_active'); ?>"><i class="bi bi-check-circle"></i> Active Clients</a></li> -->
                  <!-- <li><a href="/public/client_list_by_status.php?status=inactive" class="btn btn-menu<?php echo is_active('clients_inactive'); ?>"><i class="bi bi-slash-circle"></i> Inactive Clients</a></li> -->
                  <!-- <li><a href="/public/client_list_by_status.php?status=expired" class="btn btn-menu<?php echo is_active('clients_expired'); ?>"><i class="bi bi-hourglass"></i> Expired Clients</a></li> -->
                  <!-- <li><a href="/public/suspended_clients.php" class="btn btn-menu<?php echo is_active('clients_auto_inactive'); ?>"><i class="bi bi-hourglass"></i>Auto Inactive Client</a></li> -->
                  <li><a href="/public/client_list_by_status.php?status=left" class="btn btn-menu<?php echo is_active('clients_left'); ?>"><i class="bi bi-box-arrow-left"></i> Left Clients</a></li>
                  <!-- <li><a href="/public/client_ledger.php" class="btn btn-menu<?php echo is_active('clients_ledger'); ?>"><i class="bi bi-file-earmark-ruled"></i>Clients Ledger</a></li> -->
                </ul>
              </li>
            <?php } ?>

            <!-- Billing -->
            <?php if (hasPermission('view.billing')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openBilling ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#billingMenu" aria-expanded="<?php echo $openBilling ? 'true' : 'false'; ?>">
                  <i class="bi bi-receipt"></i> <span class="menu-label">Billing</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openBilling ? 'show' : ''; ?>" id="billingMenu">
                  <li><a href="/public/billing.php" class="btn btn-menu<?php echo is_active('billing'); ?>"><i class="bi bi-list-check"></i> All Bills</a></li>
                  <!-- <li><a href="/public/due_report_pro.php" class="btn btn-menu<?php echo is_active('due_report_pro'); ?>"><i class="bi bi-exclamation-triangle"></i> Due Bills</a></li>
                  <li><a href="/public/invoices.php?status=paid" class="btn btn-menu<?php echo is_active('invoices_paid'); ?>"><i class="bi bi-check2-circle"></i> Paid Bills</a></li>
                  <li><a href="/public/invoices.php" class="btn btn-menu<?php echo is_active('invoices'); ?>"><i class="bi bi-file-text"></i> Invoices</a></li>
                  <li><a href="/public/invoice_new.php" class="btn btn-menu<?php echo is_active('invoice_new'); ?>"><i class="bi bi-file-earmark-plus"></i> New Invoice</a></li>
                  <li><a href="/public/collections.php?when=today" class="btn btn-menu<?php echo is_active('collections_today'); ?>"><i class="bi bi-calendar-day"></i> Today's Collection</a></li>
                  <li><a href="/public/collections.php" class="btn btn-menu<?php echo is_active('collections'); ?>"><i class="bi bi-calendar2-week"></i> All Collection</a></li>
                  <li><a href="/public/webhook_payments.php" class="btn btn-menu<?php echo is_active('bkash_webhook'); ?>"><i class="bi bi-broadcast-pin"></i> bKash Webhook</a></li> -->
                  <!-- <li><a href="/public/process_manual.php" class="btn btn-menu<?php echo is_active('bkash_manual'); ?>"><i class="bi bi-wrench"></i> bKash Manual Map</a></li> -->
                </ul>
              </li>
            <?php } ?>

            <!-- Accounts -->
            <?php
            $accountMenu = hasPermission('view.wallets') || hasPermission('wallet.approval') || hasPermission('wallet.settlement');
            if ($accountMenu) { ?>
              <li class="nav-item">
                <button type="button" class="btn btn-menu w-100<?php echo $openAccounts ? ' active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#accountsMenu" aria-expanded="<?php echo $openAccounts ? 'true' : 'false'; ?>">
                  <i class="bi bi-cash-stack"></i> <span class="menu-label">Accounts</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openAccounts ? 'show' : ''; ?>" id="accountsMenu">
                  <?php if (hasPermission('view.wallets')) { ?>
                    <li><a href="/public/wallets.php" class="btn btn-menu<?php echo is_active('wallets'); ?>"><i class="bi bi-wallet2"></i> Wallets</a></li>
                    <li><a href="/public/wallets_dashboard.php" class="btn btn-menu<?php echo is_active('wallets_dashboard'); ?>"> <i class="bi bi-wallet-fill"></i> Wallet Dashboard</a></li>
                  <?php } ?>

                  <?php if (can_approve()): ?>
                    <li>
                      <a href="/public/wallet_approvals.php" class="btn btn-menu<?php echo is_active('wallet_approvals'); ?>">
                        <i class="bi bi-check2-square"></i> Approvals
                        <?php if ($__pending_approvals): ?>
                          <span class="badge bg-success ms-auto menu-badge"><?= $__pending_approvals ?></span>
                        <?php endif; ?>
                      </a>
                    </li>
                  <?php endif; ?>

                  <li><a href="/public/wallet_settlement.php" class="btn btn-menu<?php echo is_active('wallet_settlement'); ?>"><i class="bi bi-arrow-left-right"></i> Settlement</a></li>
                  <li><a href="/public/report_payments.php" class="btn btn-menu<?php echo is_active('payment_report'); ?>"><i class="bi bi-receipt"></i> <span class="menu-label">Payment Report</span></a></li>
                  <li><a href="/public/payment_report.php" class="btn btn-menu<?php echo is_active('payment_report_invoice'); ?>"><i class="bi bi-receipt"></i> <span class="menu-label">Payment Report Invoice</span></a></li>
                  <li><a href="/public/income_expense.php" class="btn btn-menu<?php echo is_active('income_expense'); ?>"><i class="bi bi-graph-up-arrow"></i> <span class="menu-label">Income vs Expense</span></a></li>
                  <li><a href="/public/expenses.php" class="btn btn-menu<?php echo is_active('expenses'); ?>"><i class="bi bi-currency-exchange"></i> <span class="menu-label">Expenses</span></a></li>
                  <li><a href="/public/expense_add.php" class="btn btn-menu<?php echo is_active('expense_add'); ?>"><i class="bi bi-plus-square"></i> <span class="menu-label">Add Expense</span></a></li>
                </ul>
              </li>
            <?php } ?>



            <?php if (hasPermission('routers')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openRouters ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#routersMenu" aria-expanded="<?php echo $openRouters ? 'true' : 'false'; ?>">
                  <i class="bi bi-hdd-network"></i> <span class="menu-label">Network</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openRouters ? 'show' : ''; ?>" id="routersMenu">
                  <li><a href="/public/router_add.php" class="btn btn-menu<?php echo is_active('router_add'); ?>"><i class="bi bi-plus-circle"></i> Add Router</a></li>
                  <li><a href="/public/routers.php" class="btn btn-menu<?php echo is_active('routers'); ?>"><i class="bi bi-list-ul"></i> Routers List</a></li>
                  <li><a href="/public/packages.php" class="btn btn-menu<?php echo is_active('packages'); ?>"><i class="bi bi-box2-fill"></i> <span class="menu-label">Packages</a></li>
                  <li><a href="/public/import_clients.php" class="btn btn-menu<?php echo is_active('import_clients_csv'); ?>"><i class="bi bi-upload"></i> <span class="menu-label">import client Csv</a></li>
                  <li><a href="/public/import_mikrotik_client.php" class="btn btn-menu<?php echo is_active('import_mikrotik_client'); ?>"><i class="bi bi-capslock-fill"></i> <span class="menu-label">import From Mikrotik</a></li>
                </ul>
              </li>
            <?php } ?>


            <?php if (hasPermission('olt.view')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openOLT ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#oltMenu" aria-expanded="<?php echo $openOLT ? 'true' : 'false'; ?>">
                  <i class="bi bi-diagram-3"></i> <span class="menu-label">OLT & Tools</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openOLT ? 'show' : ''; ?>" id="oltMenu">
                  <li><a href="/olt/index.php" class="btn btn-menu<?php echo is_active('olts'); ?>"><i class="bi bi-pc-display"></i> OLTs</a></li>
                  <li><a href="/public/olt_mac_table.php" class="btn btn-menu<?php echo is_active('olt_mac'); ?>"><i class="bi bi-table"></i> ONU Info Table</a></li>
                  <!-- <li><a href="/public/onu_tools.php" class="btn btn-menu<?php echo is_active('onu_tools'); ?>"><i class="bi bi-magic"></i> ONU Tools</a></li> -->
                  <!-- <li><a href="/public/admin_tools.php" class="btn btn-menu<?php echo is_active('admin_tools'); ?>"><i class="bi bi-hammer"></i> Tools</a></li> -->
                  <!-- <li><a href="/public/olt_sfp.php" class="btn btn-menu<?php echo is_active('olt_sfp'); ?>"><i class="bi bi-lightning"></i> ALL SFP</a></li> -->
                  <!-- <li><a href="/public/olt_pon.php" class="btn btn-menu<?php echo is_active('olt_pon'); ?>"><i class="bi bi-diagram-2"></i> ALL PON</a></li> -->
                  <!-- <li><a href="/public/onu_monitor.php" class="btn btn-menu<?php echo is_active('onu_monitor'); ?>"><i class="bi bi-broadcast"></i> ALL ONU</a></li> -->
                </ul>
              </li>
            <?php } ?>





            <!-- HR -->
            <?php if (hasPermission('hrm.view')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openHR ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#hrMenu" aria-expanded="<?php echo $openHR ? 'true' : 'false'; ?>">
                  <i class="bi bi-person-workspace"></i> <span class="menu-label">HRM</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openHR ? 'show' : ''; ?>" id="hrMenu">
                  <li><a href="/public/users_permission.php" class="btn btn-menu<?php echo is_active('users_permission'); ?>"><i class="bi bi-box2-fill"></i> <span class="menu-label">User Permission</span></a></li>
                  <li><a href="/public/users.php" class="btn btn-menu<?php echo is_active('users'); ?>"><i class="bi bi-people"></i> <span class="menu-label">Users</span></a></li>
                  <li><a href="/public/hr/employees.php" class="btn btn-menu<?php echo is_active('hr_employees'); ?>"><i class="bi bi-people"></i> Employees (All)</a></li>
                  <li><a href="/public/hr/employee_toggle.php" class="btn btn-menu<?php echo is_active('hr_employee_toggle'); ?>"><i class="bi bi-toggle-on"></i> Employee Toggle</a></li>
                </ul>
              </li>
            <?php } ?>



            <!-- SMS -->
            <?php
            $smsMenu = hasPermission('send.sms') || hasPermission('send.sms.bulk') || hasPermission('delivered.sms') || hasPermission('pending.sms');
            if ($smsMenu) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openSMS ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#smsMenu" aria-expanded="<?php echo $openSMS ? 'true' : 'false'; ?>">
                  <i class="bi bi-chat-left-dots"></i> <span class="menu-label">SMS</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openSMS ? 'show' : ''; ?>" id="smsMenu">
                  <li><a href="/public/sms_individual.php" class="btn btn-menu<?php echo is_active('sms_individual'); ?>"><i class="bi bi-chat-dots"></i> Individual SMS</a></li>
                  <li><a href="/public/sms_templates.php" class="btn btn-menu<?php echo is_active('sms_templates'); ?>"><i class="bi bi-chat-dots"></i> SMS Template</a></li>
                  <li><a href="/public/sms_groups.php" class="btn btn-menu<?php echo is_active('sms_groups'); ?>"><i class="bi bi-envelope-check"></i> SMS Groups</a></li>
                  <li><a href="/public/sms_send.php" class="btn btn-menu<?php echo is_active('sms_send'); ?>"><i class="bi bi-hourglass-split"></i> Send SMS</a></li>
                  <li><a href="/public/sms_gateway.php" class="btn btn-menu<?php echo is_active('sms_gateway'); ?>"><i class="bi bi-hourglass-split"></i> SmsGateway</a></li>
                </ul>
              </li>
            <?php } ?>



            <!-- Reports -->
            <?php if (hasPermission('report.view')) { ?>
              <li>
                <button class="btn btn-menu w-100<?php echo $openReports ? ' active' : ''; ?>" data-bs-toggle="collapse"
                  data-bs-target="#reportsMenu" aria-expanded="<?php echo $openReports ? 'true' : 'false'; ?>">
                  <i class="bi bi-bar-chart-line-fill"></i> <span class="menu-label">Reports</span>
                  <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                </button>
                <ul class="collapse submenu list-unstyled <?php echo $openReports ? 'show' : ''; ?>" id="reportsMenu">

                  <li><a href="/public/tickets.php" class="btn btn-menu<?php echo is_active('tickets'); ?>"><i class="bi bi-life-preserver"></i> Tickets</a></li>
                  <li><a href="/public/due_report.php" class="btn btn-menu<?php echo is_active('due_report'); ?>"><i class="bi bi-exclamation-triangle"></i> Due Report</a></li>
                  <li><a href="/public/due_report_pro.php" class="btn btn-menu<?php echo is_active('due_report_pro'); ?>"><i class="bi bi-exclamation-triangle"></i> Due Report Pro</a></li>
                  <li><a href="#" class="btn btn-menu<?php echo is_active('bill_report'); ?>"><i class="bi bi-graph-up"></i> Bill Report</a></li>
                  <li><a href="#" class="btn btn-menu<?php echo is_active('payment_reports'); ?>"><i class="bi bi-cash-coin"></i> Payment Reports</a></li>
                  <li><a href="#" class="btn btn-menu<?php echo is_active('export_clients'); ?>"><i class="bi bi-upload"></i> Export Clients</a></li>
                  <li><a href="#" class="btn btn-menu<?php echo is_active('print_reports'); ?>"><i class="bi bi-printer"></i> Print Reports</a></li>
                  <li><a href="/public/report_package_wise.php" class="btn btn-menu<?php echo is_active('report_package_wise'); ?>"><i class="bi bi-diagram-3"></i> Package-wise Report</a></li>
                </ul>
              </li>
            <?php } ?>

            <!-- Settings -->
            <li>
              <button class="btn btn-menu w-100<?php echo $openSettings ? ' active' : ''; ?>" data-bs-toggle="collapse"
                data-bs-target="#settingsMenu" aria-expanded="<?php echo $openSettings ? 'true' : 'false'; ?>">
                <i class="bi bi-gear"></i> <span class="menu-label">Settings</span>
                <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
              </button>
              <ul class="collapse submenu list-unstyled <?php echo $openSettings ? 'show' : ''; ?>" id="settingsMenu">
                <li><a href="/tg/settings.php" class="btn btn-menu<?php echo is_active('settings_tg'); ?>"><i class="bi bi-sliders"></i> TG Settings</a></li>
                <li><a href="/public/settings_bkash.php" class="btn btn-menu<?php echo is_active('settings_bkash'); ?>"><i class="bi bi-credit-card"></i> bKash PGW</a></li>
                <li><a href="/public/settings_company.php" class="btn btn-menu<?php echo is_active('settings_company'); ?>"><i class="bi bi-buildings"></i> Company Setup</a></li>
                <li>
                  <button class="btn btn-menu<?php echo $openLocation ? ' active' : ''; ?>" data-bs-toggle="collapse"
                    data-bs-target="#locationMenu" aria-expanded="<?php echo $openLocation ? 'true' : 'false'; ?>">
                    <i class="bi bi-geo"></i> <span class="menu-label">Location</span>
                    <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                  </button>
                  <ul class="collapse submenu list-unstyled <?php echo $openLocation ? 'show' : ''; ?>" id="locationMenu">
                    <li><a href="/public/settings.php?type=area" class="btn btn-menu<?php echo is_active('settings_location_area'); ?>"><i class="bi bi-geo-alt"></i> Area</a></li>
                    <li><a href="/public/settings.php?type=sub_zone" class="btn btn-menu<?php echo is_active('settings_location_sub_zone'); ?>"><i class="bi bi-diagram-3"></i> Sub Zone</a></li>
                    <li><a href="/public/settings.php?type=box" class="btn btn-menu<?php echo is_active('settings_location_box'); ?>"><i class="bi bi-hdd-stack"></i> Box</a></li>
                  </ul>
                </li>
                <?php
                $toolsAllowed = (($_SESSION['role_id'] ?? null) == 1) || (strtolower((string)($_SESSION['user']['role'] ?? '')) === 'admin');
                if ($toolsAllowed) { ?>
                  <li>
                    <button class="btn btn-menu" data-bs-toggle="collapse" data-bs-target="#toolsMenu" aria-expanded="false">
                      <i class="bi bi-wrench-adjustable-circle"></i> <span class="menu-label">Tools</span>
                      <i class="bi bi-caret-down-fill small ms-auto menu-caret"></i>
                    </button>
                    <ul class="collapse submenu list-unstyled" id="toolsMenu">
                      <li><a href="/tools/log_watch.sh" class="btn btn-menu"><i class="bi bi-eye"></i> Log Watch (bash)</a></li>
                      <li><a href="/tools/logrotate_isp_billing.conf" class="btn btn-menu"><i class="bi bi-arrow-repeat"></i> Logrotate Conf</a></li>
                      <li><a href="/tools/install_logrotate.sh" class="btn btn-menu"><i class="bi bi-gear-wide-connected"></i> Install Logrotate</a></li>
                      <li><a href="/tools/phpunit.sh" class="btn btn-menu"><i class="bi bi-filetype-php"></i> PHPUnit Runner</a></li>
                      <li><a href="/tools/test_mikrotik.php" class="btn btn-menu"><i class="bi bi-router"></i> Test MikroTik API</a></li>
                      <li><a href="/tools/sync_clients_router_mac.php" class="btn btn-menu"><i class="bi bi-plug"></i> Sync Clients Router MAC</a></li>
                      <li><a href="/tools/seed_admin.php" class="btn btn-menu"><i class="bi bi-person-badge"></i> Seed Admin User</a></li>
                      <li><a href="/tools/seed_portal_user.php" class="btn btn-menu"><i class="bi bi-people"></i> Seed Portal User</a></li>
                    </ul>
                  </li>
                <?php } ?>
              </ul>
            </li>

            <!-- Logout -->
            <!-- <li>
            <a href="/public/logout.php" class="btn btn-menu text-danger">
              <i class="bi bi-box-arrow-right"></i> <span class="menu-label">Logout</span>
            </a>
          </li> -->

          </ul>
        </nav>
      </div>
    </aside>

    <!-- =============== Main Content Starts =============== -->
    <main class="content-area">
      <!-- page content goes here -->
