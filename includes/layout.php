<?php
/**
 * Shared Layout Component — Krystal Attendance System
 * Phase 4C — Sidebar + Topbar + Responsive Layout
 *
 * Usage:
 *   renderLayoutStart($user, 'dashboard', 'Dashboard');
 *   // ... page content ...
 *   renderLayoutEnd();
 */

function renderLayoutStart($user, $pageId = '', $pageTitle = '') {
    $role = $user['role'];
    $isAdmin = ($role === 'admin');

    // Build role/shift label
    if ($isAdmin) {
        $roleLabel = 'Main Admin';
    } else {
        $roleLabel = getLocationLabel($user['location']) . ' — ' . getShiftLabel($user['shift']);
    }

    // Menu items — role-aware
    $menuItems = [];
    $menuItems[] = [
        'id'   => 'dashboard',
        'href' => 'dashboard.php',
        'icon' => 'fa-solid fa-gauge-high',
        'text' => 'Dashboard',
    ];
    $menuItems[] = [
        'id'   => 'add-attendance',
        'href' => $isAdmin ? 'select-shift.php' : 'add-attendance.php?new=1',
        'icon' => 'fa-solid fa-plus-circle',
        'text' => 'Add Attendance',
    ];
    if ($isAdmin) {
        $menuItems[] = [
            'id'   => 'employees',
            'href' => 'employees.php',
            'icon' => 'fa-solid fa-users-gear',
            'text' => 'Employees',
        ];
        $menuItems[] = [
            'id'   => 'deployment-locations',
            'href' => 'deployment-locations.php',
            'icon' => 'fa-solid fa-map-pin',
            'text' => 'Dep. Locations',
        ];
        $menuItems[] = [
            'id'   => 'reports',
            'href' => 'reports.php',
            'icon' => 'fa-solid fa-chart-line',
            'text' => 'Reports',
        ];
        $menuItems[] = [
            'id'   => 'audit-log',
            'href' => 'audit-log.php',
            'icon' => 'fa-solid fa-shield-halved',
            'text' => 'Audit Log',
        ];
    }
?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar print-hide" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="sidebar-logo">
            <span class="sidebar-brand-text">KRYSTAL</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo $item['href']; ?>"
           class="sidebar-link<?php echo ($pageId === $item['id']) ? ' active' : ''; ?>">
            <i class="<?php echo $item['icon']; ?>"></i>
            <span><?php echo esc($item['text']); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-icon">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-role"><?php echo esc($roleLabel); ?></span>
            </div>
        </div>
        <a href="logout.php" class="sidebar-link sidebar-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content Area -->
<div class="main-content">
    <!-- Topbar -->
    <header class="topbar print-hide">
        <div class="topbar-left">
            <button type="button" class="hamburger-btn" id="hamburger-btn" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <?php if ($pageTitle): ?>
            <h1 class="topbar-title"><?php echo esc($pageTitle); ?></h1>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <span class="topbar-badge"><?php echo esc($roleLabel); ?></span>
            <img src="assets/images/krystal-logo.png" alt="Krystal Logo" class="topbar-mobile-logo">
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
<?php
}

function renderLayoutEnd() {
?>
    </div><!-- /.page-content -->
</div><!-- /.main-content -->
<script src="js/sidebar.js"></script>
<?php
}
?>
