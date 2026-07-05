<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$pageTitle = APP_NAME . ' | Welcome';
require_once __DIR__ . '/includes/header.php';
?>

<section class="container py-3">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="app-card issue-panel p-5">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <span class="badge text-bg-light mb-3">Kampala Capital City Authority</span>
                        <h1 class="display-5 fw-bold mb-3">Smart Civic Platform</h1>
                        <p class="lead text-muted mb-4">Report civic service issues, receive a KCCA ticket number, and track progress from submission through resolution.</p>
                        <div class="d-flex gap-3 flex-wrap">
                            <a class="btn btn-primary btn-lg" href="<?= e(app_url('auth/register.php')) ?>">
                                <i class="bi bi-plus-circle me-2"></i>Report an Issue
                            </a>
                            <a class="btn btn-outline-light btn-lg" href="<?= e(app_url('track-issue.php')) ?>">
                                <i class="bi bi-search me-2"></i>Track a Ticket
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-5 d-none d-lg-block">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold">24/7</div>
                                    <div class="small text-muted">Reporting</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold">Fast</div>
                                    <div class="small text-muted">Response</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold">Track</div>
                                    <div class="small text-muted">Status</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold">Secure</div>
                                    <div class="small text-muted">Protected</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-3">
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10 text-center">
            <h2 class="h3 fw-bold mb-2">Quick Access</h2>
            <p class="text-muted">Sign in or register to report and track civic issues</p>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="app-card compact-card p-4 text-center">
                <i class="bi bi-person fs-1 text-primary mb-3"></i>
                <h4 class="h5 fw-bold mb-2">Citizens</h4>
                <p class="text-muted small mb-3">Report issues and track tickets</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('auth/citizen-login.php')) ?>">Login</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(app_url('auth/register.php')) ?>">Register</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-3">
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10 text-center">
            <h2 class="h3 fw-bold mb-2">Platform Features</h2>
            <p class="text-muted">Comprehensive tools for civic issue management</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-6 col-lg-3">
            <div class="app-card compact-card p-4">
                <h5 class="fw-bold mb-1">Photo Evidence</h5>
                <p class="text-muted small mb-0">Upload images with GPS coordinates</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card compact-card p-4">
                <h5 class="fw-bold mb-1">Location Tracking</h5>
                <p class="text-muted small mb-0">Interactive maps for precise locations</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card compact-card p-4">
                <h5 class="fw-bold mb-1">Ticket System</h5>
                <p class="text-muted small mb-0">Unique KCCA ticket numbers</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card compact-card p-4">
                <h5 class="fw-bold mb-1">Status Updates</h5>
                <p class="text-muted small mb-0">Real-time tracking from submission</p>
            </div>
        </div>
    </div>
</section>

<section class="container py-3 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="app-card p-5 text-center">
                <h2 class="h3 fw-bold mb-3">Ready to Make a Difference?</h2>
                <p class="lead text-muted mb-4">Join thousands of citizens working together to improve Kampala's civic services.</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a class="btn btn-primary btn-lg" href="<?= e(app_url('auth/register.php')) ?>">
                        <i class="bi bi-person-plus me-2"></i>Get Started
                    </a>
                    <a class="btn btn-outline-primary btn-lg" href="<?= e(app_url('track-issue.php')) ?>">
                        <i class="bi bi-search me-2"></i>Track Issue
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>