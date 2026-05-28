<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/trash.php'));
    }

    $recordType = (string) ($_POST['record_type'] ?? '');
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($recordType === 'issue' && $action === 'trash') {
            issue_soft_delete_issue($recordId, (int) $currentUser['id']);
            app_log_system_event('recovery', 'warning', 'Issue moved to trash', ['issue_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'Issue moved to trash.');
        } elseif ($recordType === 'issue' && $action === 'restore') {
            issue_restore_issue($recordId);
            app_log_system_event('recovery', 'info', 'Issue restored from trash', ['issue_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'Issue restored successfully.');
        } elseif ($recordType === 'user' && $action === 'trash') {
            $stmt = db()->prepare('UPDATE users SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by, is_active = 0 WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute([
                'deleted_by' => (int) $currentUser['id'],
                'id' => $recordId,
            ]);
            app_log_system_event('recovery', 'warning', 'User moved to trash', ['user_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'User moved to trash.');
        } elseif ($recordType === 'user' && $action === 'restore') {
            $stmt = db()->prepare('UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE id = :id AND deleted_at IS NOT NULL');
            $stmt->execute(['id' => $recordId]);
            app_log_system_event('recovery', 'info', 'User restored from trash', ['user_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'User restored successfully.');
        } elseif ($recordType === 'comment' && $action === 'trash') {
            issue_soft_delete_comment($recordId, (int) $currentUser['id']);
            app_log_system_event('recovery', 'warning', 'Comment moved to trash', ['comment_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'Comment moved to trash.');
        } elseif ($recordType === 'comment' && $action === 'restore') {
            $stmt = db()->prepare('UPDATE issue_comments SET deleted_at = NULL, deleted_by = NULL WHERE id = :id AND deleted_at IS NOT NULL');
            $stmt->execute(['id' => $recordId]);
            app_log_system_event('recovery', 'info', 'Comment restored from trash', ['comment_id' => $recordId], (int) $currentUser['id'], __FUNCTION__);
            set_flash('success', 'Comment restored successfully.');
        } else {
            set_flash('error', 'Unsupported trash action.');
        }
    } catch (Throwable $throwable) {
        app_log_exception($throwable);
        set_flash('error', 'Unable to complete the recovery action.');
    }

    redirect(app_url('admin/trash.php'));
}

$deletedIssues = [];
$deletedUsers = [];
$deletedComments = [];

try {
    $deletedIssues = db()->query(
        'SELECT i.id, i.ticket_number, i.title, i.deleted_at, c.name AS category_name, reporter.full_name AS reporter_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         LEFT JOIN users reporter ON reporter.id = i.user_id
         WHERE i.deleted_at IS NOT NULL
         ORDER BY i.deleted_at DESC, i.id DESC
         LIMIT 50'
    )->fetchAll();

    $deletedUsers = db()->query(
        'SELECT u.id, u.full_name, u.email, u.deleted_at, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.deleted_at IS NOT NULL
         ORDER BY u.deleted_at DESC, u.id DESC
         LIMIT 50'
    )->fetchAll();

    if (issue_table_exists('issue_comments')) {
        $deletedComments = db()->query(
            'SELECT ic.id, ic.comment, ic.issue_id, i.ticket_number, ic.deleted_at, commenter.full_name AS commenter_name
             FROM issue_comments ic
             INNER JOIN issues i ON i.id = ic.issue_id
             LEFT JOIN users commenter ON commenter.id = ic.user_id
             WHERE ic.deleted_at IS NOT NULL
             ORDER BY ic.deleted_at DESC, ic.id DESC
             LIMIT 50'
        )->fetchAll();
    }
} catch (Throwable $throwable) {
    app_log_exception($throwable);
}

$pageTitle = APP_NAME . ' | Trash Center';
$activePage = 'admin-trash';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Trash Center</p>
                <h1 class="h2 mb-2">Deleted records recovery</h1>
                <p class="mb-0">Restore deleted issues and user accounts that were moved to trash.</p>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Deleted Issues</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Deleted At</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$deletedIssues) : ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No deleted issues found.</td></tr>
                            <?php else : ?>
                                <?php foreach ($deletedIssues as $issue) : ?>
                                    <tr>
                                        <td><?= e((string) $issue['ticket_number']) ?></td>
                                        <td><?= e((string) $issue['title']) ?></td>
                                        <td><?= e((string) $issue['category_name']) ?></td>
                                        <td><?= e((string) $issue['deleted_at']) ?></td>
                                        <td>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="record_type" value="issue">
                                                <input type="hidden" name="record_id" value="<?= e((string) $issue['id']) ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Restore this issue?');">Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Deleted Users</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Deleted At</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$deletedUsers) : ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No deleted users found.</td></tr>
                            <?php else : ?>
                                <?php foreach ($deletedUsers as $deletedUser) : ?>
                                    <tr>
                                        <td><?= e((string) $deletedUser['full_name']) ?></td>
                                        <td><?= e((string) $deletedUser['email']) ?></td>
                                        <td><?= e((string) $deletedUser['role_name']) ?></td>
                                        <td><?= e((string) $deletedUser['deleted_at']) ?></td>
                                        <td>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="record_type" value="user">
                                                <input type="hidden" name="record_id" value="<?= e((string) $deletedUser['id']) ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Restore this user?');">Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Deleted Comments</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Issue</th>
                                <th>Comment</th>
                                <th>Author</th>
                                <th>Deleted At</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$deletedComments) : ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No deleted comments found.</td></tr>
                            <?php else : ?>
                                <?php foreach ($deletedComments as $dc) : ?>
                                    <tr>
                                        <td><?= e((string) $dc['ticket_number']) ?></td>
                                        <td><?= e(mb_strimwidth((string) $dc['comment'], 0, 140, '...')) ?></td>
                                        <td><?= e((string) $dc['commenter_name']) ?></td>
                                        <td><?= e((string) $dc['deleted_at']) ?></td>
                                        <td>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="record_type" value="comment">
                                                <input type="hidden" name="record_id" value="<?= e((string) $dc['id']) ?>">
                                                <input type="hidden" name="action" value="restore">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Restore this comment?');">Restore</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>