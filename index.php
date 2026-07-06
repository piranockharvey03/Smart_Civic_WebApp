<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

// Fetch public statistics
$publicStats = issue_fetch_status_counts(null, null);
$recentIssues = [];
try {
    $recentIssues = issue_fetch_management_issues(['limit' => 6], null);
} catch (Throwable $e) {
    $recentIssues = [];
}

$commonCategories = issue_fetch_common_categories(5);

$pageTitle = APP_NAME . ' | Public Dashboard';
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
                                    <div class="display-6 fw-bold"><?= number_format((int) ($publicStats['total'] ?? 0)) ?></div>
                                    <div class="small text-muted">Total Issues</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold"><?= number_format((int) ($publicStats['resolved'] ?? 0)) ?></div>
                                    <div class="small text-muted">Resolved</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold"><?= number_format((int) ($publicStats['in_progress'] ?? 0)) ?></div>
                                    <div class="small text-muted">In Progress</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="app-card compact-card p-3 text-center">
                                    <div class="display-6 fw-bold"><?= number_format((int) ($publicStats['open'] ?? 0)) ?></div>
                                    <div class="small text-muted">Open</div>
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
        <div class="col-lg-10">
            <h2 class="h3 fw-bold mb-2">Recent Issues</h2>
            <p class="text-muted">Latest civic issues reported by citizens</p>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if ($recentIssues) : ?>
                <div class="row g-3">
                    <?php foreach (array_slice($recentIssues, 0, 6) as $issue) : ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="app-card compact-card p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                                    <span class="small text-muted"><?= e(date('d M', strtotime((string) $issue['created_at']))) ?></span>
                                </div>
                                <h6 class="fw-bold mb-1 text-truncate"><?= e($issue['title']) ?></h6>
                                <div class="small text-muted mb-2"><?= e($issue['category_name'] ?? 'Uncategorized') ?></div>
                                <div class="small text-muted text-truncate"><?= e($issue['location'] ?? 'No location') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info">No issues have been reported yet.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container py-3">
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <h2 class="h3 fw-bold mb-2">Common Categories</h2>
            <p class="text-muted">Most frequently reported issue types</p>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if ($commonCategories) : ?>
                <div class="row g-3">
                    <?php foreach ($commonCategories as $category) : ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="app-card compact-card p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-0"><?= e($category['name']) ?></h6>
                                </div>
                                <span class="issue-badge secondary"><?= number_format((int) $category['issue_count']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info">No category data available yet.</div>
            <?php endif; ?>
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