<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['staff']);

$filters = [
    'ticket_number' => trim((string) ($_GET['ticket_number'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'category_id' => trim((string) ($_GET['category_id'] ?? '')),
    'location' => trim((string) ($_GET['location'] ?? '')),
];

$issues = issue_fetch_management_issues($filters);
$categories = issue_category_options();
$statuses = issue_status_options();
$stats = issue_fetch_status_counts();

$pageTitle = APP_NAME . ' | Issue Management';
$activePage = 'staff-issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5">
                <div class="section-header mb-0">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Staff Issue Console</p>
                        <h1 class="h3 mb-2">Manage submitted civic issues</h1>
                        <p class="mb-0 text-muted">Search by ticket number and filter issues by status, category, or location.</p>
                    </div>
                    <a href="<?= e(app_url('citizen/report-issue.php')) ?>" class="btn btn-primary">View Submission Form</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Total Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['total']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-yellow p-4 h-100">
                <div class="text-muted small text-uppercase">Open Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['open']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-red p-4 h-100">
                <div class="text-muted small text-uppercase">Pending Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['pending']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card bg-white metric-card metric-green p-4 h-100">
                <div class="text-muted small text-uppercase">Resolved Issues</div>
                <div class="display-6 fw-semibold mb-0"><?= e((string) $stats['resolved']) ?></div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white p-4 mb-4">
                <form method="get" action="" class="row g-3 align-items-end">
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
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= ($filters['category_id'] === (string) $category['id']) ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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

        <div class="col-12">
            <div class="app-card bg-white p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">All Issues</h2>
                        <p class="text-muted mb-0">Click a ticket number to open the full workflow detail page.</p>
                    </div>
                </div>

                <?php if (!$issues) : ?>
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
                                    <th>Location</th>
                                    <th>Assigned To</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issues as $issue) : ?>
                                    <tr>
                                        <td class="fw-semibold"><a href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>"><?= e($issue['ticket_number']) ?></a></td>
                                        <td><?= e($issue['reporter_name']) ?></td>
                                        <td><?= e($issue['category_name']) ?></td>
                                        <td><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></td>
                                        <td><?= e($issue['location']) ?></td>
                                        <td><?= e($issue['assigned_name'] ?? 'Unassigned') ?></td>
                                        <td><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>">Open</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>