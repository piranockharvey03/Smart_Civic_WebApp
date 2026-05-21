<?php

declare(strict_types=1);

$role = current_user_role();
$dashboardLink = match ($role) {
    'admin' => app_url('admin/dashboard.php'),
    'staff' => app_url('staff/dashboard.php'),
    default => app_url('citizen/dashboard.php'),
};
?>
<div class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="appSidebarLabel"><?= e(APP_NAME) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <aside class="sidebar h-100">
            <div class="sidebar-brand px-4 py-4 border-bottom">
                <div class="small text-uppercase text-muted fw-semibold">KCCA Civic Platform</div>
                <div class="h5 mb-0 text-success"><?= e(APP_NAME) ?></div>
            </div>
            <nav class="nav flex-column px-3 py-4 gap-2">
                <a class="nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>" href="<?= e($dashboardLink) ?>">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
                <?php if ($role === 'citizen') : ?>
                    <a class="nav-link" href="#"><i class="bi bi-chat-square-text me-2"></i>My Reports</a>
                    <a class="nav-link" href="#"><i class="bi bi-plus-circle me-2"></i>Submit Report</a>
                <?php elseif ($role === 'staff') : ?>
                    <a class="nav-link" href="#"><i class="bi bi-inbox me-2"></i>Assigned Issues</a>
                    <a class="nav-link" href="#"><i class="bi bi-arrow-repeat me-2"></i>Status Updates</a>
                <?php elseif ($role === 'admin') : ?>
                    <a class="nav-link" href="#"><i class="bi bi-people me-2"></i>User Management</a>
                    <a class="nav-link" href="#"><i class="bi bi-shield-lock me-2"></i>Role Access</a>
                <?php endif; ?>
                <a class="nav-link text-danger" href="<?= e(app_url('auth/logout.php')) ?>">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </nav>
        </aside>
    </div>
</div>
<main class="flex-grow-1 app-content">