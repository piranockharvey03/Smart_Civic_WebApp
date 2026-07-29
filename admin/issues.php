<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$user = current_user();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$filters = [
    'ticket_number' => trim((string) ($_GET['ticket_number'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'category_id' => trim((string) ($_GET['category_id'] ?? '')),
    'assigned_to' => trim((string) ($_GET['assigned_to'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
    'deleted' => trim((string) ($_GET['deleted'] ?? '')),
];

$issuesPage = issue_fetch_management_issue_page($filters, $page, $perPage);
$categories = issue_category_options();
$statuses = issue_status_options();
$priorities = issue_priority_catalog();
$staffMembers = issue_fetch_staff_members();
$stats = issue_fetch_status_counts();
$staffWorkload = issue_fetch_staff_workload();
$recentActivity = issue_fetch_recent_activity(null, 6);
$commonCategories = issue_fetch_common_categories(5);

$pageTitle = APP_NAME . ' | Issue Administration';
$activePage = 'admin-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card">
                <div class="section-header mb-0">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Administrative Oversight</p>
                        <h1 class="h3 mb-2">Issue oversight and governance</h1>
                        <p class="mb-0 text-muted">Filter tickets by status, priority, staff assignment, category, date range, and location.</p>
                    </div>
                    <a href="<?= e(app_url('admin/issues.php?status=submitted')) ?>" class="btn btn-primary">View Submitted Queue</a>
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
                    <div class="card-meta">Action needed</div>
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

        <div class="col-lg-15">
            <div class="app-card bg-white compact-card mb-4">
                <form method="get" action="" class="row g-2 align-items-end">
                    <div class="col-lg-12 col-md-6">
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
                        <label class="form-label" for="assigned_to">Assigned Staff</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="">All</option>
                            <?php foreach ($staffMembers as $staffMember) : ?>
                                <option value="<?= e((string) $staffMember['id']) ?>" <?= ($filters['assigned_to'] === (string) $staffMember['id']) ? 'selected' : '' ?>><?= e($staffMember['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?= e($filters['location']) ?>" placeholder="Division or area">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="deleted">Trash</label>
                        <select class="form-select" id="deleted" name="deleted">
                            <option value="">Active only</option>
                            <option value="1" <?= $filters['deleted'] === '1' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= $filters['deleted'] === 'all' ? 'selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>

            <div class="app-card bg-white p-1">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">All Issues</h2>
                        <p class="text-muted mb-0">Administrators can open any ticket to reassign work, change priority, and manage resolution notes.</p>
                    </div>
                </div>

                <?php if (!$issuesPage['items']) : ?>
                    <div class="alert alert-info mb-0">No issues match the selected filters.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Reporter</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Location</th>
                                    <th>Assigned To</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issuesPage['items'] as $issue) : ?>
                                    <tr>
                                        <td class="fw-semibold"><a href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>"><?= e($issue['ticket_number']) ?></a></td>
                                        <td><?= e($issue['reporter_name']) ?></td>
                                        <td><?= e($issue['category_name']) ?></td>
                                        <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                        <td><span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span></td>
                                        <td><?= e($issue['location']) ?></td>
                                        <td><?= e($issue['assigned_name'] ?? 'Unassigned') ?></td>
                                        <td><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></td>
                                        <td class="text-end">
                                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                                <a class="btn btn-sm btn-outline-primary" href="<?= e(issue_detail_url((int) $issue['id'], current_user_role())) ?>">Open</a>
                                                <form method="post" action="<?= e(app_url('admin/trash.php')) ?>" onsubmit="return confirm('Move this issue to trash?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="record_type" value="issue">
                                                    <input type="hidden" name="record_id" value="<?= e((string) $issue['id']) ?>">
                                                    <input type="hidden" name="action" value="trash">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Trash</button>
                                                </form>
                                            </div>
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
                                        <a class="page-link" href="<?= e(app_url('admin/issues.php?' . http_build_query(array_merge($filters, ['page' => $pageIndex])))) ?>"><?= e((string) $pageIndex) ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-15">
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
                        <p class="text-muted mb-0">Where citizens are reporting most often.</p>
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

                <div class="mt-4 d-flex justify-content-end">
                    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/trash.php')) ?>">Open Trash Center</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>