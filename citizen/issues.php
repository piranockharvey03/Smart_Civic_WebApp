<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$user = current_user();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$issuesPage = issue_fetch_citizen_issue_page((int) $user['id'], $page, $perPage);
$stats = issue_fetch_status_counts((int) $user['id']);
$recentResponses = issue_fetch_latest_staff_responses((int) $user['id'], 5);
$recentActivity = issue_fetch_recent_activity((int) $user['id'], 5);

$pageTitle = APP_NAME . ' | My Reports';
$activePage = 'citizen-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <p class="text-uppercase small text-muted mb-2">Citizen Reporting</p>
                    <h1 class="h3 mb-2">My submitted issues</h1>
                    <p class="mb-0 text-muted">Track your reports, follow staff responses, and review resolution progress.</p>
                </div>
                <a href="<?= e(app_url('citizen/report-issue.php')) ?>" class="btn btn-primary">Submit New Issue</a>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['total']) ?></div>
                    <div class="card-meta">All time</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Open Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['open']) ?></div>
                    <div class="card-meta">In progress</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Pending Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['pending']) ?></div>
                    <div class="card-meta">Waiting</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Resolved</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['resolved']) ?></div>
                    <div class="card-meta">Closed</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white compact-card">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Issue History</h2>
                        <p class="text-muted mb-0">Open any ticket to view the full timeline, staff replies, and workflow updates.</p>
                    </div>
                </div>

                <?php if (!$issuesPage['items']) : ?>
                    <div class="alert alert-info mb-0">No issues have been submitted yet. Use the submit button to create your first report.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Category</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Location</th>
                                    <th>Submitted</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issuesPage['items'] as $issue) : ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($issue['ticket_number']) ?></td>
                                        <td><?= e($issue['category_name']) ?></td>
                                        <td><?= e($issue['title']) ?></td>
                                        <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                        <td><span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span></td>
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

                    <?php if ($issuesPage['pages'] > 1) : ?>
                        <nav class="mt-4" aria-label="Issue pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($pageIndex = 1; $pageIndex <= $issuesPage['pages']; $pageIndex++) : ?>
                                    <li class="page-item <?= $pageIndex === $issuesPage['page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= e(app_url('citizen/issues.php?' . http_build_query(['page' => $pageIndex]))) ?>"><?= e((string) $pageIndex) ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Latest Staff Responses</h2>
                        <p class="text-muted mb-0">Newest replies from KCCA staff and admins.</p>
                    </div>
                </div>
                <?php if (!$recentResponses) : ?>
                    <p class="text-muted mb-0">No staff responses have been posted yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($recentResponses as $response) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="small text-muted mb-1"><?= e($response['ticket_number']) ?></div>
                                <div class="fw-semibold mb-1"><?= e($response['author_name']) ?> <span class="text-muted small">(<?= e($response['author_role']) ?>)</span></div>
                                <div class="mb-2"><?= nl2br(e($response['comment'])) ?></div>
                                <div class="small text-muted"><?= e(date('d M Y, H:i', strtotime((string) $response['created_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Activity</h2>
                        <p class="text-muted mb-0">Timeline entries linked to your reports.</p>
                    </div>
                </div>
                <?php if (!$recentActivity) : ?>
                    <p class="text-muted mb-0">No recent activity is available yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($recentActivity as $activity) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold mb-1"><?= e(issue_log_action_label((string) $activity['action'])) ?></div>
                                <div class="small text-muted mb-1"><?= e($activity['ticket_number']) ?> | <?= e($activity['title']) ?></div>
                                <div class="small"><?= nl2br(e((string) $activity['description'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>