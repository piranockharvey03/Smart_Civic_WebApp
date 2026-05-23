<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$user = current_user();
$stats = issue_fetch_status_counts((int) $user['id']);
$recentIssues = array_slice(issue_fetch_citizen_issues((int) $user['id']), 0, 3);

$pageTitle = APP_NAME . ' | Citizen Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5">
                <p class="text-uppercase small text-muted mb-2">Citizen Dashboard</p>
                <h1 class="h2 mb-2">Welcome, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Submit civic issues, track ticket progress, and review KCCA responses in one place.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(app_url('citizen/report-issue.php')) ?>" class="btn btn-primary">Submit New Issue</a>
                    <a href="<?= e(app_url('citizen/issues.php')) ?>" class="btn btn-outline-primary">View My Reports</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Total Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['total']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-yellow p-4 h-100">
                <div class="text-muted small text-uppercase">Open Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['open']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-red p-4 h-100">
                <div class="text-muted small text-uppercase">Pending Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['pending']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Resolved</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['resolved']) ?></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">Recent Reports</h2>
                <?php if (!$recentIssues) : ?>
                    <p class="text-muted mb-0">No reports have been submitted yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($recentIssues as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><?= e($issue['ticket_number']) ?> - <?= e($issue['title']) ?></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['location']) ?></div>
                                </div>
                                <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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