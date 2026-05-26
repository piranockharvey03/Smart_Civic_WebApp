<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$page = max(1, (int) ($_GET['page'] ?? 1));
$auditPage = admin_fetch_audit_page($page, 25);

$pageTitle = APP_NAME . ' | Audit Trail';
$activePage = 'admin-audit';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Audit Trail</p>
                <h1 class="h2 mb-2">Operational and security monitoring</h1>
                <p class="mb-0">Track administrative actions, authentication events, and system changes.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditPage['items'] as $entry) : ?>
                                <tr>
                                    <td><?= e((string) ($entry['created_at'] ?? '')) ?></td>
                                    <td><?= e((string) ($entry['user_id'] ?? 'System')) ?></td>
                                    <td><?= e((string) ($entry['action'] ?? '')) ?></td>
                                    <td><?= e((string) ($entry['affected_table'] ?? '')) ?></td>
                                    <td><?= e((string) ($entry['affected_record'] ?? '')) ?></td>
                                    <td><?= e((string) ($entry['ip_address'] ?? '')) ?></td>
                                    <td><?= e((string) ($entry['details'] ?? '')) ?></td>
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