<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

if (!system_logs_table_exists()) {
    $pageTitle = APP_NAME . ' | System Logs';
    $activePage = 'admin-system-logs';
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <section class="container-fluid">
        <div class="row g-4">
            <div class="col-12">
                <div class="app-card issue-panel compact-card p-4 p-lg-4">
                    <p class="text-uppercase small text-muted mb-2">System Logs</p>
                    <h1 class="h2 mb-2">Logging storage is not available yet</h1>
                    <p class="mb-0">The application is running, but the `system_logs` table has not been created in the database.</p>
                </div>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$filters = [
    'log_type' => trim((string) ($_GET['log_type'] ?? '')),
    'severity' => trim((string) ($_GET['severity'] ?? '')),
    'user_id' => (int) ($_GET['user_id'] ?? 0),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$conditions = [];
$params = [];

if ($filters['log_type'] !== '') {
    $conditions[] = 'sl.log_type = :log_type';
    $params['log_type'] = $filters['log_type'];
}

if ($filters['severity'] !== '') {
    $conditions[] = 'sl.severity = :severity';
    $params['severity'] = $filters['severity'];
}

if ($filters['user_id'] > 0) {
    $conditions[] = 'sl.user_id = :user_id';
    $params['user_id'] = $filters['user_id'];
}

$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = db()->prepare('SELECT COUNT(*) AS total FROM system_logs sl' . $whereSql);
$countStmt->execute($params);
$total = (int) ($countStmt->fetch()['total'] ?? 0);

$stmt = db()->prepare(
    'SELECT sl.*, u.full_name AS user_name, u.email AS user_email
     FROM system_logs sl
     LEFT JOIN users u ON u.id = sl.user_id' .
     $whereSql .
    ' ORDER BY sl.created_at DESC, sl.id DESC
     LIMIT :limit OFFSET :offset'
);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = APP_NAME . ' | System Logs';
$activePage = 'admin-system-logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">System Logs</p>
                <h1 class="h2 mb-2">Application and security event history</h1>
                <p class="mb-0">Review errors, exceptions, unauthorized access attempts, and maintenance events.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <form class="row g-3 align-items-end" method="get">
                    <div class="col-md-4">
                        <label class="form-label" for="log_type">Log type</label>
                        <input type="text" class="form-control" id="log_type" name="log_type" value="<?= e($filters['log_type']) ?>" placeholder="exception, security, fatal">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="severity">Severity</label>
                        <input type="text" class="form-control" id="severity" name="severity" value="<?= e($filters['severity']) ?>" placeholder="warning, error, critical">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="user_id">User ID</label>
                        <input type="number" class="form-control" id="user_id" name="user_id" value="<?= e((string) $filters['user_id']) ?>" min="0" placeholder="Optional">
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Filter Logs</button>
                        <a href="<?= e(app_url('admin/system-logs.php')) ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>User</th>
                                <th>Message</th>
                                <th>Source</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$logs) : ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No system logs found for the selected filters.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($logs as $log) : ?>
                                    <tr>
                                        <td><?= e((string) $log['created_at']) ?></td>
                                        <td><span class="issue-badge secondary"><?= e((string) $log['log_type']) ?></span></td>
                                        <td><span class="issue-badge <?= e(system_log_severity_badge_class((string) $log['severity'])) ?>"><?= e((string) $log['severity']) ?></span></td>
                                        <td>
                                            <?= e((string) ($log['user_name'] ?? 'System')) ?><br>
                                            <span class="text-muted small"><?= e((string) ($log['user_email'] ?? '')) ?></span>
                                        </td>
                                        <td><?= e((string) $log['message']) ?></td>
                                        <td><?= e((string) ($log['source'] ?? '')) ?></td>
                                        <td><?= e((string) ($log['ip_address'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="text-muted">Showing <?= e((string) ($total > 0 ? min($total, $offset + 1) : 0)) ?> to <?= e((string) ($total > 0 ? min($total, $offset + $perPage) : 0)) ?> of <?= e((string) $total) ?> entries</div>
                <div class="d-flex gap-2">
                    <?php if ($page > 1) : ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('admin/system-logs.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1])))) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if (($offset + $perPage) < $total) : ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(app_url('admin/system-logs.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1])))) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>