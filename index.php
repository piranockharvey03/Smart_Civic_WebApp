<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    $role = current_user_role();

    if ($role === 'admin') {
        redirect(app_url('admin/dashboard.php'));
    }

    if ($role === 'staff') {
        redirect(app_url('staff/dashboard.php'));
    }

    redirect(app_url('citizen/dashboard.php'));
}

$pageTitle = APP_NAME . ' | Welcome';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-lg-7">
            <div class="p-5 rounded-4 hero-panel app-card">
                <p class="text-uppercase small mb-2 text-muted">Kampala Capital City Authority</p>
                <h1 class="display-5 fw-bold mb-3">Smart Civic App</h1>
                <p class="lead mb-4">A citizen services reporting and tracking platform for public service issues in Kampala, now with the phase two issue reporting core.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a class="btn btn-light btn-lg" href="<?= e(app_url('auth/login.php')) ?>">Staff/Admin Login</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(app_url('auth/citizen-login.php')) ?>">Citizen Login</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(app_url('auth/register.php')) ?>">Citizen Registration</a>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="app-card bg-white p-4">
                <h2 class="h4 mb-3">Phase Two Core</h2>
                <ul class="mb-0 text-muted">
                    <li>Issue submission with secure image uploads</li>
                    <li>Unique KCCA ticket numbers</li>
                    <li>Citizen issue history and issue detail views</li>
                    <li>Staff and admin issue management with filters</li>
                    <li>Status, assignment, and comments workflow</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>