<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$pageTitle = APP_NAME . ' | Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$user = current_user();
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card hero-panel p-4 p-lg-5">
                <p class="text-uppercase small text-muted mb-2">Admin Dashboard</p>
                <h1 class="h2 mb-2">Welcome, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Manage users, security access, and platform oversight from the administrative console.</p>
                <span class="status-chip green">System Healthy</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Total Users</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-yellow p-4 h-100">
                <div class="text-muted small text-uppercase">Citizens</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-red p-4 h-100">
                <div class="text-muted small text-uppercase">Staff</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Admins</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">Phase One Admin Scope</h2>
                <p class="text-muted mb-0">The admin area is ready for future user management, role administration, and oversight workflows without adding advanced service features yet.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>