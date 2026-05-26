<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$settings = admin_fetch_settings();
$backupLogs = admin_fetch_backup_logs();

$pageTitle = APP_NAME . ' | Backup Center';
$activePage = 'admin-backup';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Backup & Recovery</p>
                <h1 class="h2 mb-2">Backup planning and recovery readiness</h1>
                <p class="mb-0">Configure retention and maintain a backup register. Automated backup execution is intentionally not enabled yet.</p>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="app-card bg-white compact-card mb-4">
                <h2 class="h5 mb-3">Backup Settings</h2>
                <div class="small text-muted mb-2">Retention: <?= e((string) $settings['reports_retention_days']) ?> days</div>
                <div class="small text-muted mb-2">Audit logging: <?= ((string) $settings['enable_audit_logging'] === '1') ? 'Enabled' : 'Disabled' ?></div>
                <div class="small text-muted">Session timeout: <?= e((string) $settings['session_timeout_minutes']) ?> minutes</div>
            </div>
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Recovery Notes</h2>
                <p class="text-muted mb-0">Keep manual database snapshots, verify restore procedures, and record every recovery test in this center.</p>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Backup Log Register</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupLogs as $log) : ?>
                                <tr>
                                    <td><?= e((string) $log['created_at']) ?></td>
                                    <td><?= e((string) $log['backup_name']) ?></td>
                                    <td><?= e((string) $log['backup_type']) ?></td>
                                    <td><span class="issue-badge secondary"><?= e((string) $log['status']) ?></span></td>
                                    <td><?= e((string) ($log['notes'] ?? '')) ?></td>
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