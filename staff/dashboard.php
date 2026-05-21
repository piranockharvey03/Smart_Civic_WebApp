<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['staff']);

$pageTitle = APP_NAME . ' | Staff Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$user = current_user();
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card hero-panel p-4 p-lg-5">
                <p class="text-uppercase small text-muted mb-2">Staff Dashboard</p>
                <h1 class="h2 mb-2">Welcome, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Monitor assigned civic issues and prepare updates for citizen service requests.</p>
                <span class="status-chip red">Needs Attention</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-red p-4 h-100">
                <div class="text-muted small text-uppercase">Assigned Issues</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-yellow p-4 h-100">
                <div class="text-muted small text-uppercase">Pending Response</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Closed This Week</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">Phase One Staff View</h2>
                <p class="text-muted mb-0">This dashboard is intentionally limited to authenticated staff workflows. Issue assignment, status updates, and response tools are added in later phases.</p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>