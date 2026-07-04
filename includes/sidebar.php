<?php

declare(strict_types=1);

$role = current_user_role();
$activePage = $activePage ?? '';
$roleQuery = $role ? '?role=' . urlencode($role) : '';
$dashboardLink = match ($role) {
    'admin' => app_url('admin/dashboard.php'),
    'department_manager' => app_url('department-manager/dashboard.php'),
    'staff' => app_url('staff/dashboard.php'),
    default => app_url('citizen/dashboard.php'),
};

$issueListLink = match ($role) {
    'admin' => app_url('admin/issues.php'),
    'department_manager' => app_url('department-manager/issues.php'),
    'staff' => app_url('staff/issues.php'),
    default => app_url('citizen/issues.php'),
};

$issueQueueLink = match ($role) {
    'admin' => app_url('admin/issues.php?status=submitted'),
    'department_manager' => app_url('department-manager/issues.php?status=submitted'),
    'staff' => app_url('staff/issues.php?status=submitted'),
    default => app_url('citizen/report-issue.php'),
};

$issueMapLink = match ($role) {
    'admin' => app_url('issues/map.php?role=admin'),
    'department_manager' => app_url('department-manager/map.php'),
    'staff' => app_url('staff/map.php'),
    default => null,
};

$sidebarLogoUrl = app_url('KCCA.png');
?>
<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="appSidebarLabel"><?= e(APP_NAME) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <aside class="sidebar h-100">
            <div class="sidebar-brand px-4 py-4 border-bottom">
                <div class="small text-uppercase text-muted fw-semibold">KCCA Civic Platform</div>
               
            </div>
            <div class="sidebar-logo px-4 py-3 border-bottom">
                <img class="sidebar-logo-image" src="<?= e($sidebarLogoUrl) ?>" alt="KCCA logo">
            </div>
            <nav class="nav flex-column px-4 py-4 gap-2">
                <a class="nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>" href="<?= e($dashboardLink) ?>">
                    Dashboard
                </a>
                <?php if ($role === 'citizen') : ?>
                    <a class="nav-link <?= ($activePage === 'citizen-issues') ? 'active' : '' ?>" href="<?= e($issueListLink) ?>">My Reports</a>
                    <a class="nav-link <?= ($activePage === 'citizen-report') ? 'active' : '' ?>" href="<?= e($issueQueueLink) ?>">Submit Report</a>
                    <a class="nav-link <?= ($activePage === 'citizen-notifications') ? 'active' : '' ?>" href="<?= e(app_url('citizen/notifications.php')) ?>">Notifications</a>
                    <a class="nav-link <?= ($activePage === 'citizen-profile') ? 'active' : '' ?>" href="<?= e(app_url('citizen/profile.php')) ?>">Profile</a>
                <?php elseif ($role === 'staff') : ?>
                    <a class="nav-link <?= ($activePage === 'staff-issues') ? 'active' : '' ?>" href="<?= e($issueListLink) ?>">Issue Management</a>
                    <a class="nav-link" href="<?= e($issueQueueLink) ?>">Submitted Queue</a>
                    <?php if ($issueMapLink) : ?>
                        <a class="nav-link <?= ($activePage === 'staff-map') ? 'active' : '' ?>" href="<?= e($issueMapLink) ?>">Issue Map</a>
                    <?php endif; ?>
                <?php elseif ($role === 'department_manager') : ?>
                    <a class="nav-link <?= ($activePage === 'department-manager-issues') ? 'active' : '' ?>" href="<?= e($issueListLink) ?>">Department Issues</a>
                    <a class="nav-link <?= ($activePage === 'department-manager-staff') ? 'active' : '' ?>" href="<?= e(app_url('department-manager/staff.php')) ?>">Staff Management</a>
                    <a class="nav-link" href="<?= e($issueQueueLink) ?>">Submitted Queue</a>
                    <?php if ($issueMapLink) : ?>
                        <a class="nav-link <?= ($activePage === 'department-manager-map') ? 'active' : '' ?>" href="<?= e($issueMapLink) ?>">Department Map</a>
                    <?php endif; ?>
                <?php elseif ($role === 'admin') : ?>
                    <a class="nav-link <?= ($activePage === 'admin-issues') ? 'active' : '' ?>" href="<?= e($issueListLink) ?>">Issue Management</a>
                    <a class="nav-link" href="<?= e($issueQueueLink) ?>">Submitted Queue</a>
                    <?php if ($issueMapLink) : ?>
                        <a class="nav-link <?= ($activePage === 'issue-map') ? 'active' : '' ?>" href="<?= e($issueMapLink) ?>">Issue Map</a>
                    <?php endif; ?>
                    <a class="nav-link <?= ($activePage === 'admin-departments') ? 'active' : '' ?>" href="<?= e(app_url('admin/departments.php')) ?>">Departments</a>
                    <a class="nav-link <?= ($activePage === 'admin-reports') ? 'active' : '' ?>" href="<?= e(app_url('admin/reports.php')) ?>">Reports</a>
                    <a class="nav-link <?= ($activePage === 'admin-analytics') ? 'active' : '' ?>" href="<?= e(app_url('admin/analytics.php')) ?>">Analytics</a>
                    <a class="nav-link <?= ($activePage === 'admin-users') ? 'active' : '' ?>" href="<?= e(app_url('admin/users.php')) ?>">Users</a>
                    <a class="nav-link <?= ($activePage === 'admin-settings') ? 'active' : '' ?>" href="<?= e(app_url('admin/settings.php')) ?>">Settings</a>
                    <a class="nav-link <?= ($activePage === 'admin-permissions') ? 'active' : '' ?>" href="<?= e(app_url('admin/permissions.php')) ?>">Permissions</a>
                    <a class="nav-link <?= ($activePage === 'admin-audit') ? 'active' : '' ?>" href="<?= e(app_url('admin/audit.php')) ?>">Audit Trail</a>
                    <a class="nav-link <?= ($activePage === 'admin-system-logs') ? 'active' : '' ?>" href="<?= e(app_url('admin/system-logs.php')) ?>">System Logs</a>
                    <a class="nav-link <?= ($activePage === 'admin-maintenance') ? 'active' : '' ?>" href="<?= e(app_url('admin/maintenance.php')) ?>">Maintenance</a>
                    <a class="nav-link <?= ($activePage === 'admin-trash') ? 'active' : '' ?>" href="<?= e(app_url('admin/trash.php')) ?>">Trash Center</a>
                    <a class="nav-link <?= ($activePage === 'admin-search') ? 'active' : '' ?>" href="<?= e(app_url('admin/search.php')) ?>">Global Search</a>
                    <a class="nav-link <?= ($activePage === 'admin-notifications') ? 'active' : '' ?>" href="<?= e(app_url('admin/notifications.php')) ?>">Notifications</a>
                    <a class="nav-link <?= ($activePage === 'admin-backup') ? 'active' : '' ?>" href="<?= e(app_url('admin/backup.php')) ?>">Backup Center</a>
                <?php endif; ?>
                <a class="nav-link text-danger" href="<?= e(app_url('auth/logout.php' . $roleQuery)) ?>">
                    Logout
                </a>
            </nav>
        </aside>
    </div>
</div>
<main class="flex-grow-1 app-content">
