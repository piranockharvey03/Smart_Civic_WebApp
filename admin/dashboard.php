<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$user = current_user();
$stats = issue_fetch_status_counts();
$latestIssues = array_slice(issue_fetch_management_issues(), 0, 3);
$recentActivity = issue_fetch_recent_activity(null, 6);
$staffWorkload = issue_fetch_staff_workload();
$commonCategories = issue_fetch_common_categories(5);
$pendingQueue = issue_fetch_management_issue_page(['status' => 'submitted'], 1, 5);

$pageTitle = APP_NAME . ' | Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Admin Dashboard</p>
                <h1 class="h2 mb-2">Welcome, <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Oversee civic issue resolution, monitor service status, and keep the platform accountable.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(app_url('admin/issues.php')) ?>" class="btn btn-primary">Open Oversight Console</a>
                    <a href="<?= e(app_url('admin/issues.php?status=submitted')) ?>" class="btn btn-outline-primary">Submitted Queue</a>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Admin Control Center</h2>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/reports.php')) ?>">Reports Center</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/analytics.php')) ?>">Analytics Dashboard</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('issues/map.php')) ?>">Issue Map Dashboard</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/users.php')) ?>">User Management</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/settings.php')) ?>">System Settings</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/audit.php')) ?>">Audit Trail</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/system-logs.php')) ?>">System Logs</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/permissions.php')) ?>">Permissions</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/search.php')) ?>">Global Search</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/notifications.php')) ?>">Notifications</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/trash.php')) ?>">Trash Center</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/backup.php')) ?>">Backup Center</a></div>
                    <div class="col-md-6 col-xl-4"><a class="btn btn-outline-primary w-100" href="<?= e(app_url('admin/maintenance.php')) ?>">Maintenance</a></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['total']) ?></div>
                    <div class="card-meta">All tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Open Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['open']) ?></div>
                    <div class="card-meta">Awaiting action</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Pending Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['pending']) ?></div>
                    <div class="card-meta">Queue pressure</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Resolved Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['resolved']) ?></div>
                    <div class="card-meta">Completed</div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Latest Issues</h2>
                        <p class="text-muted mb-0">Newest civic reports across the platform.</p>
                    </div>
                </div>
                <?php if (!$latestIssues) : ?>
                    <p class="text-muted mb-0">No issues have been recorded yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($latestIssues as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><a href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>"><?= e($issue['ticket_number']) ?></a> - <?= e($issue['title']) ?></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['location']) ?> | <?= e($issue['reporter_name']) ?></div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                                    <span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Pending Queue</h2>
                        <p class="text-muted mb-0">Submitted tickets waiting for review or assignment.</p>
                    </div>
                </div>
                <?php if (!$pendingQueue['items']) : ?>
                    <p class="text-muted mb-0">No submitted tickets are currently waiting in the queue.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($pendingQueue['items'] as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><a href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>"><?= e($issue['ticket_number']) ?></a></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['reporter_name']) ?></div>
                                </div>
                                <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Staff Workload</h2>
                        <p class="text-muted mb-0">Quick view of who is carrying the queue.</p>
                    </div>
                </div>
                <?php if (!$staffWorkload) : ?>
                    <p class="text-muted mb-0">No staff workload data is available yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach (array_slice($staffWorkload, 0, 5) as $memberWorkload) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold mb-1"><?= e($memberWorkload['full_name']) ?></div>
                                <div class="small text-muted mb-1"><?= e($memberWorkload['email']) ?></div>
                                <div class="small">Assigned: <?= e((string) $memberWorkload['total_assigned']) ?> | Active: <?= e((string) $memberWorkload['active_tasks']) ?> | Pending: <?= e((string) $memberWorkload['pending_tasks']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Most Common Categories</h2>
                        <p class="text-muted mb-0">Where the public is reporting most often.</p>
                    </div>
                </div>
                <?php if (!$commonCategories) : ?>
                    <p class="text-muted mb-0">No category data available yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($commonCategories as $category) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center gap-3">
                                <div class="fw-semibold"><?= e($category['name']) ?></div>
                                <span class="issue-badge secondary"><?= e((string) $category['issue_count']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Activity</h2>
                        <p class="text-muted mb-0">Latest workflow events across the system.</p>
                    </div>
                </div>
                <?php if (!$recentActivity) : ?>
                    <p class="text-muted mb-0">No recent activity has been recorded yet.</p>
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