<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['department_manager']);
require_permission('view_department_dashboard');

$user = current_user();
$departmentId = department_current_user_department_id($user);
$department = $departmentId ? department_fetch_department_by_id($departmentId) : null;
$summary = $departmentId ? department_fetch_dashboard_summary($departmentId) : [
    'total_issues' => 0,
    'open_issues' => 0,
    'assigned_issues' => 0,
    'resolved_issues' => 0,
    'emergency_incidents' => 0,
    'staff_count' => 0,
    'avg_resolution_minutes' => null,
];
$recentIssues = $departmentId ? department_fetch_recent_issues($departmentId, 5) : [];
$staffWorkload = $departmentId ? department_fetch_staff_workload($departmentId) : [];

$pageTitle = APP_NAME . ' | Department Manager Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <!-- Welcome Header -->
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Department Manager Dashboard</p>
                <h1  class="h5 mb-2">Logged in as:  <?= e($user['full_name'] ?? '') ?></h1>
                <p class="mb-3">Monitor routed issues, staff workload, emergency incidents, and departmental performance.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(app_url('department-manager/issues.php')) ?>" class="btn btn-primary">Department Issues</a>
                    <a href="<?= e(app_url('department-manager/issues.php?status=submitted')) ?>" class="btn btn-outline-primary">Submitted Queue</a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $summary['total_issues']) ?></div>
                    <div class="card-meta">All tickets</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Open Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $summary['open_issues']) ?></div>
                    <div class="card-meta">Awaiting action</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Assigned Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $summary['assigned_issues']) ?></div>
                    <div class="card-meta">In queue</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Resolved Issues</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) $summary['resolved_issues']) ?></div>
                    <div class="card-meta">Completed</div>
                </div>
            </div>
        </div>

        <!-- Recent Issues -->
        <div class="col-lg-7">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Department Issues</h2>
                        <p class="text-muted mb-0">Latest issues routed to your department</p>
                    </div>
                    <a href="<?= e(app_url('department-manager/issues.php')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php if (!$recentIssues) : ?>
                    <p class="text-muted mb-0">No issues have been routed to your department yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach ($recentIssues as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a> - <?= e($issue['title']) ?></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['reporter_name']) ?></div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                                    <?php if (isset($issue['priority'])) : ?>
                                        <span class="issue-badge <?= e(issue_priority_badge_class((string) $issue['priority'])) ?>"><?= e(issue_priority_label((string) $issue['priority'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Submitted Queue</h2>
                        <p class="text-muted mb-0">Submitted tickets waiting for review or assignment.</p>
                    </div>
                    <a href="<?= e(app_url('department-manager/issues.php?status=submitted')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php 
                $submittedQueue = $departmentId ? department_fetch_recent_issues($departmentId, 5) : [];
                $submittedQueue = array_filter($submittedQueue, fn($i) => ($i['status'] ?? '') === 'submitted');
                ?>
                <?php if (!$submittedQueue) : ?>
                    <p class="text-muted mb-0">No submitted tickets are currently waiting in the queue.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach (array_slice($submittedQueue, 0, 5) as $issue) : ?>
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a></div>
                                    <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['reporter_name']) ?></div>
                                </div>
                                <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-5">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Quick Actions</h2>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="<?= e(app_url('department-manager/issues.php?status=submitted')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-inbox me-2"></i>Submitted Queue
                    </a>
                    <a href="<?= e(app_url('department-manager/issues.php?status=in_progress')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-clock me-2"></i>In Progress
                    </a>
                    <a href="<?= e(app_url('department-manager/issues.php?priority=critical')) ?>" class="btn btn-outline-danger text-start">
                        <i class="bi bi-exclamation-triangle me-2"></i>Critical Issues
                    </a>
                    <a href="<?= e(app_url('issues/map.php')) ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-map me-2"></i>Issue Map
                    </a>
                </div>
            </div>

            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Performance Metrics</h2>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-center border rounded-3 p-3">
                            <div class="small text-muted small">Emergencies</div>
                            <div class="h4 mb-0"><?= e((string) $summary['emergency_incidents']) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center border rounded-3 p-3">
                            <div class="small text-muted small">Staff</div>
                            <div class="h4 mb-0"><?= e((string) $summary['staff_count']) ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-center border rounded-3 p-3">
                            <div class="small text-muted small">Avg Resolution Time</div>
                            <div class="h4 mb-0"><?= e($summary['avg_resolution_minutes'] !== null ? number_format((float) $summary['avg_resolution_minutes'], 1) . ' min' : '0.0 min') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Staff Workload</h2>
                        <p class="text-muted mb-0">Quick view of who is carrying the queue.</p>
                    </div>
                    <a href="<?= e(app_url('department-manager/staff.php')) ?>" class="btn btn-sm btn-outline-primary">Manage Staff</a>
                </div>
                <?php if (!$staffWorkload) : ?>
                    <p class="text-muted mb-0">No staff workload data available yet.</p>
                <?php else : ?>
                    <div class="d-grid gap-2 compact-stack">
                        <?php foreach (array_slice($staffWorkload, 0, 5) as $memberWorkload) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="fw-semibold mb-1"><?= e($memberWorkload['full_name']) ?></div>
                                <div class="small text-muted mb-1"><?= e($memberWorkload['email']) ?></div>
                                <div class="small">Active: <?= e((string) ($memberWorkload['active_tasks'] ?? 0)) ?> | Resolved: <?= e((string) ($memberWorkload['resolved_tasks'] ?? 0)) ?> | Avg: <?= e($memberWorkload['avg_resolution_minutes'] !== null ? number_format((float) $memberWorkload['avg_resolution_minutes'], 1) . ' min' : '0.0 min') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>