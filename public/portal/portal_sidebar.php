<?php
// (বাংলা) Active link হাইলাইট করার ছোট হেল্পার
$uri = $_SERVER['REQUEST_URI'] ?? '';
$active = function(string $needle) use ($uri) {
  return (strpos($uri, $needle) !== false) ? 'active' : '';
};

// (বাংলা) অবতার URL (না থাকলে ডিফল্ট)
// যদি আপনার পেজে আগে $avatar_url সেট করা থাকে, সেটাই ব্যবহার হবে
$avatar_url = $avatar_url ?? '/assets/images/default-avatar.png';
?>

<!-- Navbar (mobile only) -->
<nav class="navbar navbar-dark fixed-top d-lg-none custom-navbar">
  <div class="container-fluid">
    <button class="navbar-toggler p-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Toggle sidebar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <span class="navbar-brand ms-2 small"><?= htmlspecialchars(portal_username()) ?></span>
  </div>
</nav>

<!-- Sidebar -->
<div class="offcanvas-lg offcanvas-start text-white custom-sidebar" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
  <div class="offcanvas-header py-2">
    <h6 class="offcanvas-title d-flex align-items-center gap-2" id="sidebarMenuLabel">
      <i class="bi bi-ui-checks-grid"></i> Customer Menu
    </h6>
    <button type="button" class="btn-close btn-close-white btn-sm d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>

  <div class="offcanvas-body p-3 d-flex flex-column gap-3">
    <!-- User mini card -->
    <div class="sidebar-user">
      <div class="sidebar-avatar-ring">
        <div class="sidebar-avatar">
          <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
        </div>
      </div>
      <div class="flex-grow-1">
        <div class="sidebar-username"><?= htmlspecialchars(portal_username()) ?></div>
        <div class="sidebar-pppoe">Signed in</div>
      </div>
    </div>

    <!-- Nav group -->
    <div>
      <div class="sidebar-section-title">Navigation</div>
      <ul class="nav flex-column sidebar-nav gap-2">
        <li class="nav-item">
          <a class="sidebar-link <?= $active('/public/portal/index.php') ?>" href="/public/portal/index.php">
            <i class="bi bi-house"></i> <span>Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="sidebar-link <?= $active('/public/portal/invoices.php') ?>" href="/public/portal/invoices.php">
            <i class="bi bi-file-text"></i> <span> Invoices</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="sidebar-link <?= $active('/public/portal/ticket_new.php') ?>" href="/public/portal/ticket_new.php">
            <i class="bi bi-life-preserver"></i> <span>Support Tickets</span>
          </a>
        </li>
      </ul>
    </div>

    <!-- Danger zone / Logout -->
    <div class="mt-auto">
      <div class="sidebar-section-title">Account</div>
      <a class="sidebar-link logout" href="/public/portal/logout.php">
        <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
      </a>
    </div>
  </div>
</div>
