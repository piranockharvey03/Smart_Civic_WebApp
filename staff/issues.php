<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['staff']);

$user = current_user();
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
    'assigned_to' => (int) $user['id'],
];

$issuesPage = issue_fetch_management_issue_page($filters, $page, $perPage);
$categories = issue_category_options();
$statuses = issue_status_options();
$priorities = issue_priority_catalog();
$workload = issue_fetch_staff_workload();
$myWorkload = null;
foreach ($workload as $memberWorkload) {
    if ((int) $memberWorkload['id'] === (int) $user['id']) {
        $myWorkload = $memberWorkload;
        break;
    }
}
$recentActivity = issue_fetch_recent_activity((int) $user['id'], 5);

$pageTitle = APP_NAME . ' | Staff Issue Console';
$activePage = 'staff-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card">
                <div class="section-header mb-0">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Staff Issue Console</p>
                        <h1 class="h3 mb-2">My assigned civic issues</h1>
                        <p class="mb-0 text-muted">Track your assigned tickets, update their progress, and add response notes.</p>
                    </div>
                    <a href="<?= e(app_url('issues/view.php?id=' . (int) ($issuesPage['items'][0]['id'] ?? 0))) ?>" class="btn btn-primary">Open Latest Assignment</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Assigned Tickets</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) ($myWorkload['total_assigned'] ?? 0)) ?></div>
                    <div class="card-meta">Your queue</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Active Tasks</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) ($myWorkload['active_tasks'] ?? 0)) ?></div>
                    <div class="card-meta">In progress</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Pending Tasks</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) ($myWorkload['pending_tasks'] ?? 0)) ?></div>
                    <div class="card-meta">Needs follow-up</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Resolved Tasks</div>
                <div class="d-flex justify-content-between align-items-end mt-2">
                    <div class="card-value"><?= e((string) ($myWorkload['resolved_tasks'] ?? 0)) ?></div>
                    <div class="card-meta">Completed</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card mb-4">
                <form method="get" action="" class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="ticket_number">Ticket Number</label>
                        <input type="text" class="form-control" id="ticket_number" name="ticket_number" value="<?= e($filters['ticket_number']) ?>" placeholder="KCCA-2026-0001">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach ($statuses as $status) : ?>
                                <option value="<?= e($status['status_key']) ?>" <?= ($filters['status'] === $status['status_key']) ? 'selected' : '' ?>><?= e($status['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All</option>
                            <?php foreach ($priorities as $priorityKey => $priorityLabel) : ?>
                                <option value="<?= e($priorityKey) ?>" <?= ($filters['priority'] === $priorityKey) ? 'selected' : '' ?>><?= e($priorityLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= ($filters['category_id'] === (string) $category['id']) ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
                    </div>
                    <div class="col-lg-1 col-md-6">
                        <label class="form-label" for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?= e($filters['location']) ?>" placeholder="Division or area">
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white compact-card">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Assigned Issues</h2>
                        <p class="text-muted mb-0">Open any ticket to update its status, add a priority, or post a progress comment.</p>
                    </div>
                </div>

                <?php if (!$issuesPage['items']) : ?>
                    <div class="alert alert-info mb-0">No assigned issues match the selected filters.</div>
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
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issuesPage['items'] as $issue) : ?>
                                    <tr>
                                        <td class="fw-semibold"><a href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>"><?= e($issue['ticket_number']) ?></a></td>
                                        <td><?= e($issue['category_name']) ?></td>
                                        <td><?= e($issue['title']) ?></td>
                                        <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                        <td><span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span></td>
                                        <td><?= e($issue['location']) ?></td>
                                        <td><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>">Open</a></td>
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
                                        <a class="page-link" href="<?= e(app_url('staff/issues.php?' . http_build_query(array_merge($filters, ['page' => $pageIndex])))) ?>"><?= e((string) $pageIndex) ?></a>
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

            <div class="app-card bg-white compact-card">
                <div class="section-header mb-3">
                    <div>
                        <h2 class="h5 mb-1">Quick Actions</h2>
                    </div>
                </div>
                <p class="mb-2 text-muted">Use the ticket detail page to update status, add comments, and review resolution notes.</p>
                <a href="<?= e(app_url('staff/dashboard.php')) ?>" class="btn btn-outline-primary w-100">Back to Dashboard</a>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>