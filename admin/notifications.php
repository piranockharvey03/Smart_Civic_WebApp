<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/notifications.php'));
    }

    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    issue_mark_notification_read($notificationId, (int) $currentUser['id']);
    set_flash('success', 'Notification marked as read.');
    redirect(app_url('admin/notifications.php'));
}

$notifications = issue_fetch_notifications((int) $currentUser['id'], 50);

$pageTitle = APP_NAME . ' | Notifications';
$activePage = 'admin-notifications';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Notification Center</p>
                <h1 class="h2 mb-2">System alerts and assignment updates</h1>
                <p class="mb-0">Review internal notifications and clear them once action has been taken.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification) : ?>
                                <tr>
                                    <td><?= e((string) $notification['created_at']) ?></td>
                                    <td><?= e((string) $notification['message']) ?></td>
                                    <td><span class="issue-badge <?= ((int) $notification['is_read'] === 1) ? 'secondary' : 'warning' ?>"><?= ((int) $notification['is_read'] === 1) ? 'Read' : 'Unread' ?></span></td>
                                    <td>
                                        <?php if ((int) $notification['is_read'] === 0) : ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="notification_id" value="<?= e((string) $notification['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark as read</button>
                                            </form>
                                        <?php else : ?>
                                            <span class="text-muted small">No action needed</span>
                                        <?php endif; ?>
                                    </td>
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