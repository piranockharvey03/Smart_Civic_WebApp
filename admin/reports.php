<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$user = current_user();
$filters = admin_normalize_filters($_GET);
$summary = admin_fetch_report_summary($filters);
$reportRows = array_slice(admin_fetch_report_rows($filters), 0, 25);
$categories = issue_category_options();
$statusOptions = issue_status_options();
$priorityOptions = issue_priority_catalog();
$staffMembers = issue_fetch_staff_members();
$exportBase = array_filter($filters, static fn ($value) => $value !== '' && $value !== 0 && $value !== null);
$exportCsv = app_url('admin/export.php?' . http_build_query($exportBase + ['format' => 'csv', 'type' => 'issues']));
$exportPdf = app_url('admin/export.php?' . http_build_query($exportBase + ['format' => 'pdf', 'type' => 'issues']));

$pageTitle = APP_NAME . ' | Reports Center';
$activePage = 'admin-reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Reports Center</p>
                <h1 class="h2 mb-2">Administrative reporting and analytics</h1>
                <p class="mb-3">Generate civic issue summaries, filters, and exports for oversight and accountability.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="<?= e($exportCsv) ?>">Export CSV</a>
                    <a class="btn btn-outline-primary" href="<?= e($exportPdf) ?>">Export PDF</a>
                    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/analytics.php?' . http_build_query($exportBase))) ?>">View Analytics</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['total_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Open Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['open_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Closed Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['closed_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Avg Resolution</div>
                <div class="card-value mt-2"><?= $summary['avg_resolution_minutes'] !== null ? e(number_format((float) $summary['avg_resolution_minutes'], 1)) . ' min' : 'N/A' ?></div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Ticket Number</label>
                        <input type="text" name="ticket_number" value="<?= e($filters['ticket_number']) ?>" class="form-control" placeholder="KCCA-2026-0001">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            <?php foreach ($statusOptions as $option) : ?>
                                <?php $statusValue = (string) ($option['status_key'] ?? ''); ?>
                                <option value="<?= e($statusValue) ?>" <?= $filters['status'] === $statusValue ? 'selected' : '' ?>><?= e((string) ($option['label'] ?? $statusValue)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="">All priorities</option>
                            <?php foreach ($priorityOptions as $priorityKey => $priorityLabel) : ?>
                                <option value="<?= e((string) $priorityKey) ?>" <?= $filters['priority'] === (string) $priorityKey ? 'selected' : '' ?>><?= e((string) $priorityLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="0">All categories</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Assigned Staff</label>
                        <select name="assigned_to" class="form-select">
                            <option value="0">All staff</option>
                            <?php foreach ($staffMembers as $staff) : ?>
                                <option value="<?= e((string) $staff['id']) ?>" <?= (int) $filters['assigned_to'] === (int) $staff['id'] ? 'selected' : '' ?>><?= e($staff['full_name']) ?> (<?= e($staff['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" value="<?= e($filters['location']) ?>" class="form-control" placeholder="Division or site">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-control">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="<?= e(app_url('admin/reports.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-15">
            <div class="app-card bg-white compact-card">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Issue Report Rows</h2>
                        <p class="text-muted mb-0">Filtered issue records for management review.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportRows as $issue) : ?>
                                <tr>
                                    <td><?= e($issue['ticket_number']) ?></td>
                                    <td><?= e($issue['title']) ?></td>
                                    <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                    <td><span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span></td>
                                    <td><?= e($issue['location']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-15">
            <div class="app-card bg-white compact-card mb-4">
                <h2 class="h5 mb-3">Top Categories</h2>
                <div class="d-grid gap-2 compact-stack">
                    <?php foreach ($summary['category_breakdown'] as $category) : ?>
                        <div class="border rounded-3 p-3 d-flex justify-content-between gap-3">
                            <span><?= e($category['name']) ?></span>
                            <span class="issue-badge secondary"><?= e((string) $category['issue_count']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Most Affected Locations</h2>
                <div class="d-grid gap-2 compact-stack">
                    <?php foreach ($summary['location_breakdown'] as $location) : ?>
                        <div class="border rounded-3 p-3 d-flex justify-content-between gap-3">
                            <span><?= e($location['location']) ?></span>
                            <span class="issue-badge secondary"><?= e((string) $location['issue_count']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>