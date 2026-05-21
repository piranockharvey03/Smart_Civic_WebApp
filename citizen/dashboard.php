<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$pageTitle = APP_NAME . ' | Citizen Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$user = current_user();
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card hero-panel p-4 p-lg-5">
                <p class="text-uppercase small text-muted mb-2">Citizen Dashboard</p>
                <h1 class="h2 mb-2">Welcome, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Use this space to submit and track civic service concerns in your area.</p>
                <span class="status-chip yellow">In Review</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-yellow p-4 h-100">
                <div class="text-muted small text-uppercase">Open Reports</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-red p-4 h-100">
                <div class="text-muted small text-uppercase">In Review</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Resolved</div>
                <div class="display-6 fw-semibold mb-0">0</div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">What citizens can do in Phase One</h2>
                <ul class="mb-0 text-muted">
                    <li>Register and log in securely</li>
                    <li>Access a citizen-only dashboard</li>
                    <li>Prepare to submit civic issue reports in later phases</li>
                    <li>Track future response updates from KCCA staff</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card bg-white p-4 h-100">
                <h2 class="h5 mb-3">Profile Summary</h2>
                <p class="mb-2"><strong>Email:</strong> <?= e($user['email'] ?? '') ?></p>
                <p class="mb-0"><strong>Division:</strong> <?= e($user['division'] ?? 'Not set') ?></p>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>