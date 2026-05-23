<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$user = current_user();
$issues = issue_fetch_citizen_issues((int) $user['id']);
$stats = issue_fetch_status_counts((int) $user['id']);

$pageTitle = APP_NAME . ' | My Reports';
$activePage = 'citizen-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <p class="text-uppercase small text-muted mb-2">Citizen Reporting</p>
                    <h1 class="h3 mb-2">My submitted issues</h1>
                    <p class="mb-0 text-muted">Track the status of the issues you submitted to KCCA.</p>
                </div>
                <a href="<?= e(app_url('citizen/report-issue.php')) ?>" class="btn btn-primary btn-lg">Submit New Issue</a>
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
                <div class="text-muted small text-uppercase">Resolved Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['resolved']) ?></div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Issue History</h2>
                        <p class="text-muted mb-0">Open any row to view the full ticket details and timeline.</p>
                    </div>
                </div>

                <?php if (!$issues) : ?>
                    <div class="alert alert-info mb-0">No issues have been submitted yet. Use the submit button to create your first report.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Category</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Submitted</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issues as $issue) : ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($issue['ticket_number']) ?></td>
                                        <td><?= e($issue['category_name']) ?></td>
                                        <td><?= e($issue['title']) ?></td>
                                        <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                        <td><?= e($issue['location']) ?></td>
                                        <td><?= e(date('d M Y, H:i', strtotime((string) $issue['created_at']))) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>