<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();
$cleanupResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/maintenance.php'));
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'clear_read_notifications' && issue_table_exists('notifications')) {
        $stmt = db()->prepare('DELETE FROM notifications WHERE is_read = 1');
        $stmt->execute();
        $cleanupResult = 'Read notifications cleared.';
        app_log_system_event('maintenance', 'info', 'Read notifications cleared', [], isset($currentUser['id']) ? (int) $currentUser['id'] : null, __FUNCTION__);
    } elseif ($action === 'purge_old_logs' && system_logs_table_exists()) {
        $days = max(30, (int) (admin_get_setting('log_retention_days', 180) ?? 180));
        $stmt = db()->prepare('DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
        $stmt->execute();
        $cleanupResult = 'Old system logs purged.';
        app_log_system_event('maintenance', 'info', 'Old system logs purged', ['retention_days' => $days], isset($currentUser['id']) ? (int) $currentUser['id'] : null, __FUNCTION__);
    } else {
        $cleanupResult = 'No maintenance action was performed.';
    }

    set_flash('success', $cleanupResult);
    redirect(app_url('admin/maintenance.php'));
}

$totalUsers = 0;
$activeUsers = 0;
$systemLogCount = 0;
$readNotifications = 0;
$unreadNotifications = 0;

try {
    $totalUsers = (int) db()->query('SELECT COUNT(*) AS total FROM users')->fetch()['total'];
    $activeUsers = (int) db()->query('SELECT COUNT(*) AS total FROM users WHERE is_active = 1')->fetch()['total'];
    if (system_logs_table_exists()) {
        $systemLogCount = (int) db()->query('SELECT COUNT(*) AS total FROM system_logs')->fetch()['total'];
    }
    if (issue_table_exists('notifications')) {
        $notifStmt = db()->query('SELECT SUM(is_read = 1) AS read_total, SUM(is_read = 0) AS unread_total FROM notifications');
        $notifTotals = $notifStmt->fetch();
        $readNotifications = (int) ($notifTotals['read_total'] ?? 0);
        $unreadNotifications = (int) ($notifTotals['unread_total'] ?? 0);
    }
} catch (Throwable $throwable) {
    app_log_exception($throwable);
}

$pageTitle = APP_NAME . ' | Maintenance';
$activePage = 'admin-maintenance';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Maintenance Center</p>
                <h1 class="h2 mb-2">System health and soft cleanup tools</h1>
                <p class="mb-0">Review basic operational health and run controlled cleanup tasks for administrative upkeep.</p>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Users</div>
                <div class="card-value mt-2"><?= e((string) $totalUsers) ?></div>
                <div class="card-meta">All registered accounts</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Active Users</div>
                <div class="card-value mt-2"><?= e((string) $activeUsers) ?></div>
                <div class="card-meta">Enabled accounts</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">System Logs</div>
                <div class="card-value mt-2"><?= e((string) $systemLogCount) ?></div>
                <div class="card-meta">Stored events</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Notifications</div>
                <div class="card-value mt-2"><?= e((string) ($readNotifications + $unreadNotifications)) ?></div>
                <div class="card-meta"><?= e((string) $unreadNotifications) ?> unread</div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Cleanup Actions</h2>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <form method="post" class="border rounded-3 p-3 h-100">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear_read_notifications">
                            <h3 class="h6">Clear Read Notifications</h3>
                            <p class="text-muted small mb-3">Remove read notifications to keep the inbox clean without affecting unread alerts.</p>
                            <button type="submit" class="btn btn-outline-primary">Clear Read Notifications</button>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <form method="post" class="border rounded-3 p-3 h-100">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="purge_old_logs">
                            <h3 class="h6">Purge Old System Logs</h3>
                            <p class="text-muted small mb-3">Remove logs older than the configured retention period to reduce storage growth.</p>
                            <button type="submit" class="btn btn-outline-danger">Purge Old Logs</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Maintenance Guidance</h2>
                <ul class="mb-0">
                    <li>Review logs weekly and keep production retention aligned with policy.</li>
                    <li>Monitor upload storage size and archive exports after download.</li>
                    <li>Use the admin dashboard to spot stale queues and unusual activity spikes.</li>
                    <li>Run backups before any cleanup or schema change.</li>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>