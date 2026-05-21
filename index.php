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
                <p class="lead mb-4">A citizen services reporting and tracking platform for public service issues in Kampala.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a class="btn btn-light btn-lg" href="<?= e(app_url('auth/login.php')) ?>">Staff/Admin Login</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(app_url('auth/citizen-login.php')) ?>">Citizen Login</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(app_url('auth/register.php')) ?>">Citizen Registration</a>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="app-card bg-white p-4">
                <h2 class="h4 mb-3">Phase One Scope</h2>
                <ul class="mb-0 text-muted">
                    <li>Project setup and structure</li>
                    <li>MySQL database design</li>
                    <li>Authentication and session handling</li>
                    <li>Role-based access control</li>
                    <li>Basic dashboards for each role</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>