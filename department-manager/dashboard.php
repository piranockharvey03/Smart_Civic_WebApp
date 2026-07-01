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
$activePage = 'department-manager-dashboard';
$pageTitle = APP_NAME . ' | Department Manager Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Department Manager Dashboard</p>
                <h1 class="h2 mb-2"><?= e($department['department_name'] ?? 'Department') ?></h1>
                <p class="mb-0">Monitor routed issues, staff workload, emergency incidents, and departmental performance.</p>
            </div>
        </div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Total Department Issues</div><div class="card-value"><?= e((string) $summary['total_issues']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Open Issues</div><div class="card-value"><?= e((string) $summary['open_issues']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Assigned Issues</div><div class="card-value"><?= e((string) $summary['assigned_issues']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Resolved Issues</div><div class="card-value"><?= e((string) $summary['resolved_issues']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Emergency Incidents</div><div class="card-value"><?= e((string) $summary['emergency_incidents']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Staff Count</div><div class="card-value"><?= e((string) $summary['staff_count']) ?></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Avg Resolution Time</div><div class="card-value"><?= e($summary['avg_resolution_minutes'] !== null ? number_format((float) $summary['avg_resolution_minutes'], 1) : '0.0') ?></div></div></div>
        <div class="col-lg-7">
            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3"><div><h2 class="h5 mb-1">Recent Department Issues</h2></div></div>
                <div class="d-grid gap-2 compact-stack">
                    <?php foreach ($recentIssues as $issue) : ?>
                        <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a> - <?= e($issue['title']) ?></div>
                                <div class="small text-muted"><?= e($issue['category_name']) ?> | <?= e($issue['reporter_name']) ?></div>
                            </div>
                            <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="app-card bg-white compact-card mb-4">
                <div class="section-header mb-3"><div><h2 class="h5 mb-1">Staff Workload</h2></div></div>
                <div class="d-grid gap-2 compact-stack">
                    <?php foreach ($staffWorkload as $member) : ?>
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold"><?= e($member['full_name']) ?></div>
                            <div class="small text-muted mb-1"><?= e($member['email']) ?></div>
                            <div class="small">Active: <?= e((string) $member['active_tasks']) ?> | Resolved: <?= e((string) $member['resolved_tasks']) ?> | Avg mins: <?= e($member['avg_resolution_minutes'] !== null ? number_format((float) $member['avg_resolution_minutes'], 1) : '0.0') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3"><div><h2 class="h5 mb-1">Quick Links</h2></div></div>
                <a class="btn btn-outline-primary w-100 mb-2" href="<?= e(app_url('department-manager/issues.php')) ?>">Department Issues</a>
                <a class="btn btn-outline-primary w-100 mb-2" href="<?= e(app_url('department-manager/staff.php')) ?>">Manage Staff</a>
                <a class="btn btn-outline-primary w-100" href="<?= e(app_url('issues/map.php')) ?>">Department Map</a>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>