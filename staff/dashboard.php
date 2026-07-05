<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['staff']);

$user = current_user();
$stats = issue_fetch_status_counts();
$assignedIssues = array_slice(issue_fetch_management_issues(['assigned_to' => $user['id']]), 0, 5);
$recentIssues = array_slice(issue_fetch_management_issues(['assigned_to' => $user['id']]), 0, 3);
$recentActivity = issue_fetch_recent_activity((int) $user['id'], 5);
$workload = issue_fetch_staff_workload();
$myWorkload = null;
foreach ($workload as $memberWorkload) {
    if ((int) $memberWorkload['id'] === (int) $user['id']) {
        $myWorkload = $memberWorkload;
        break;
    }
}

$pageTitle = APP_NAME . ' | Staff Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Staff Dashboard</p>
                <h1  class="h5 mb-2">Logged in as: <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Monitor incoming civic issues, update progress, and keep citizens informed.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(app_url('staff/issues.php')) ?>" class="btn btn-primary">Open Issue Console</a>
                    <a href="<?= e(app_url('staff/issues.php?status=submitted')) ?>" class="btn btn-outline-primary">Submitted Queue</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['total']) ?></div>
                    <div class="card-meta">Portfolio</div>
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
                        <h2 class="h5 mb-1">Assigned Issues</h2>
                        <p class="text-muted mb-0">Active tickets in your queue with current status and priority.</p>
                    </div>
                </div>
                <?php if (!$assignedIssues) : ?>
                    <p class="text-muted mb-0">No issues are assigned to you yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($assignedIssues as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a> - <?= e($issue['title']) ?></div>
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
                        <h2 class="h5 mb-1">Recent Activity</h2>
                        <p class="text-muted mb-0">Latest changes across your assigned issues.</p>
                    </div>
                </div>
                <?php if (!$recentActivity) : ?>
                    <p class="text-muted mb-0">No recent activity has been logged on your assignments yet.</p>
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

        <div class="col-lg-5">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Workload Snapshot</h2>
                        <p class="text-muted mb-0">Your current queue, split by activity state.</p>
                    </div>
                </div>
                <p class="mb-2"><strong>Assigned Tickets:</strong> <?= e((string) ($myWorkload['total_assigned'] ?? 0)) ?></p>
                <p class="mb-2"><strong>Active Tasks:</strong> <?= e((string) ($myWorkload['active_tasks'] ?? 0)) ?></p>
                <p class="mb-2"><strong>Pending Tasks:</strong> <?= e((string) ($myWorkload['pending_tasks'] ?? 0)) ?></p>
                <p class="mb-0"><strong>Resolved Tasks:</strong> <?= e((string) ($myWorkload['resolved_tasks'] ?? 0)) ?></p>
            </div>

            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recently Updated Issues</h2>
                        <p class="text-muted mb-0">Assigned tickets with the latest timestamp.</p>
                    </div>
                </div>
                <?php if (!$recentIssues) : ?>
                    <p class="text-muted mb-0">No assigned issues were updated recently.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($recentIssues as $issue) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a></div>
                                <div class="small text-muted mb-1"><?= e($issue['title']) ?></div>
                                <div class="small text-muted"><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card h-100">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Quick Actions</h2>
                    </div>
                </div>
                <p class="mb-2 text-muted">Use the issue console to filter by assignment or open a ticket directly from the queue.</p>
                <a href="<?= e(app_url('staff/issues.php')) ?>" class="btn btn-outline-primary w-100">Open Issue Console</a>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>