<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['department_manager']);
require_permission('view_department_dashboard');

$user = current_user();
$departmentId = department_current_user_department_id($user);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$filters = [
    'ticket_number' => trim((string) ($_GET['ticket_number'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'category_id' => trim((string) ($_GET['category_id'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'department_id' => $departmentId,
];

$issuesPage = issue_fetch_management_issue_page($filters, $page, $perPage, $user);
$categories = issue_category_options();
$statuses = issue_status_options();
$priorities = issue_priority_catalog();
$recentActivity = issue_fetch_recent_activity((int) $user['id'], 5);
$pageTitle = APP_NAME . ' | Department Issues';
$activePage = 'department-manager-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="section-header"><div><h1 class="h3 mb-1">Department Issues</h1><p class="text-muted mb-0">Issues routed to your department only.</p></div></div>
                <form method="get" action="" class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6"><input type="text" class="form-control" name="ticket_number" placeholder="Ticket number" value="<?= e($filters['ticket_number']) ?>"></div>
                    <div class="col-lg-2 col-md-6"><select class="form-select" name="status"><option value="">All Statuses</option><?php foreach ($statuses as $status) : ?><option value="<?= e($status['status_key']) ?>" <?= $filters['status'] === $status['status_key'] ? 'selected' : '' ?>><?= e($status['label']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2 col-md-6"><select class="form-select" name="priority"><option value="">All Priorities</option><?php foreach ($priorities as $priorityKey => $priorityLabel) : ?><option value="<?= e($priorityKey) ?>" <?= $filters['priority'] === $priorityKey ? 'selected' : '' ?>><?= e($priorityLabel) ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-3 col-md-6"><select class="form-select" name="category_id"><option value="">All Categories</option><?php foreach ($categories as $category) : ?><option value="<?= e((string) $category['id']) ?>" <?= $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-lg-2 d-grid"><button type="submit" class="btn btn-primary">Filter</button></div>
                </form>
            </div>
        </div>
        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Ticket</th><th>Title</th><th>Status</th><th>Priority</th><th>Assigned</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($issuesPage['items'] as $issue) : ?>
                            <tr>
                                <td><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a></td>
                                <td><?= e($issue['title']) ?></td>
                                <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                <td><span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span></td>
                                <td><?= e($issue['assigned_name'] ?? 'Unassigned') ?></td>
                                <td><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>