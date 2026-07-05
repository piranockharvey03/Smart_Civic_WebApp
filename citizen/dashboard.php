<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$user = current_user();
$stats = issue_fetch_status_counts((int) $user['id']);
$recentIssues = array_slice(issue_fetch_citizen_issues((int) $user['id']), 0, 3);
$recentResponses = issue_fetch_latest_staff_responses((int) $user['id'], 3);
$recentActivity = issue_fetch_recent_activity((int) $user['id'], 5);
$notifications = issue_fetch_notifications((int) $user['id'], 5);

$pageTitle = APP_NAME . ' | Citizen Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Citizen Dashboard</p>
                <h1  class="h5 mb-2">Logged in as:  <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Submit civic issues, track ticket progress, and review KCCA responses in one place.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(app_url('citizen/report-issue.php')) ?>" class="btn btn-primary">Submit New Issue</a>
                    <a href="<?= e(app_url('citizen/issues.php')) ?>" class="btn btn-outline-primary">View My Reports</a>
                    <a href="<?= e(app_url('track-issue.php')) ?>" class="btn btn-outline-secondary">Track by Ticket</a>
                </div>
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
                    <div class="card-meta">Needs attention</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Pending Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['pending']) ?></div>
                    <div class="card-meta">Waiting on KCCA</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Resolved Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $stats['resolved']) ?></div>
                    <div class="card-meta">Closed issues</div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Reports</h2>
                        <p class="text-muted mb-0">Latest citizen submissions and their current state.</p>
                    </div>
                </div>
                <?php if (!$recentIssues) : ?>
                    <p class="text-muted mb-0">No reports have been submitted yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($recentIssues as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><?= e($issue['ticket_number']) ?> - <?= e($issue['title']) ?></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['location']) ?></div>
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
                        <p class="text-muted mb-0">Timeline entries linked to your reports.</p>
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

        <div class="col-lg-5">
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

            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Notifications</h2>
                        <p class="text-muted mb-0">Updates about your submitted issues.</p>
                    </div>
                    <a href="<?= e(app_url('citizen/notifications.php')) ?>" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <?php if (!$notifications) : ?>
                    <p class="text-muted mb-0">No notifications have been stored yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($notifications as $notification) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="small text-muted mb-1"><?= e(date('d M Y, H:i', strtotime((string) $notification['created_at']))) ?></div>
                                <div><?= e($notification['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Profile Summary</h2>
                    </div>
                    <a href="<?= e(app_url('citizen/profile.php')) ?>" class="btn btn-sm btn-outline-primary">Edit profile</a>
                </div>
                <div class="d-grid gap-2 compact-stack">
                    <div class="border rounded-3 p-3">
                        <div class="card-kicker mb-1">Email</div>
                        <div class="fw-semibold text-break"><?= e($user['email'] ?? '') ?></div>
                    </div>
                    <div class="border rounded-3 p-3">
                        <div class="card-kicker mb-1">Division</div>
                        <div class="fw-semibold"><?= e($user['division'] ?? 'Not set') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>