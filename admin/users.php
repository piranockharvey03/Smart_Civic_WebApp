<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();
$roles = db()->query('SELECT id, name FROM roles ORDER BY id ASC')->fetchAll();
$roleLookup = [];
foreach ($roles as $role) {
    $roleLookup[(int) $role['id']] = (string) $role['name'];
}

$filters = [
    'role' => trim((string) ($_GET['role'] ?? '')),
    'is_active' => isset($_GET['is_active']) ? trim((string) $_GET['is_active']) : '',
    'search' => trim((string) ($_GET['search'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

function admin_users_list_url(array $filters = []): string
{
    $query = array_filter(
        $filters,
        static fn ($value): bool => $value !== '' && $value !== null
    );

    return app_url('admin/users.php') . ($query ? '?' . http_build_query($query) : '');
}

function admin_users_page_url(array $filters, int $page): string
{
    return admin_users_list_url($filters + ['page' => $page]);
}

$returnFilters = [
    'role' => $filters['role'],
    'is_active' => $filters['is_active'],
    'search' => $filters['search'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(admin_users_list_url([
            'role' => trim((string) ($_POST['return_role'] ?? '')),
            'is_active' => trim((string) ($_POST['return_is_active'] ?? '')),
            'search' => trim((string) ($_POST['return_search'] ?? '')),
        ]));
    }

    $action = (string) ($_POST['action'] ?? '');
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $returnFilters = [
        'role' => trim((string) ($_POST['return_role'] ?? '')),
        'is_active' => trim((string) ($_POST['return_is_active'] ?? '')),
        'search' => trim((string) ($_POST['return_search'] ?? '')),
    ];

    $targetUserStmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
    $targetUserStmt->execute(['id' => $targetUserId]);
    $targetUser = $targetUserStmt->fetch();

    if (!$targetUser) {
        set_flash('error', 'User record was not found.');
        redirect(admin_users_list_url($returnFilters));
    }

    try {
        if ($action === 'update_user') {
            $roleId = (int) ($_POST['role_id'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!isset($roleLookup[$roleId])) {
                set_flash('error', 'Please select a valid role.');
                redirect(admin_users_list_url($returnFilters));
            }

            if ($targetUserId === (int) $currentUser['id'] && $isActive === 0) {
                set_flash('error', 'You cannot deactivate your own account.');
                redirect(admin_users_list_url($returnFilters));
            }

            if ($targetUserId === (int) $currentUser['id'] && $roleId !== (int) $currentUser['role_id']) {
                set_flash('error', 'You cannot change your own role from this page.');
                redirect(admin_users_list_url($returnFilters));
            }

            $stmt = db()->prepare('UPDATE users SET role_id = :role_id, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'role_id' => $roleId,
                'is_active' => $isActive,
                'id' => $targetUserId,
            ]);

            admin_record_audit_log((int) $currentUser['id'], 'user_updated', 'users', (string) $targetUserId, 'User role or account status changed');
            admin_record_user_activity((int) $currentUser['id'], 'user_updated', 'users', $targetUserId, ['target_user' => $targetUser['email']]);
            set_flash('success', 'User account updated successfully.');
        } elseif ($action === 'reset_password') {
            $newPassword = trim((string) ($_POST['new_password'] ?? ''));
            if ($newPassword === '') {
                $newPassword = 'Kcca@' . bin2hex(random_bytes(4));
            }

            $stmt = db()->prepare('UPDATE users SET password = :password WHERE id = :id');
            $stmt->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $targetUserId,
            ]);

            admin_record_audit_log((int) $currentUser['id'], 'password_reset', 'users', (string) $targetUserId, 'Temporary password issued for user account');
            admin_record_user_activity((int) $currentUser['id'], 'password_reset', 'users', $targetUserId, ['target_user' => $targetUser['email']]);
            set_flash('success', 'Password reset successfully. Temporary password: ' . $newPassword);
        }
    } catch (Throwable $throwable) {
        set_flash('error', 'Unable to update the account at this time.');
    }

    redirect(admin_users_list_url($returnFilters));
}

$usersPage = admin_fetch_users($filters, $page, $perPage);
$users = $usersPage['items'];
$totalUsers = (int) $usersPage['total'];
$totalPages = (int) $usersPage['pages'];
$currentPage = (int) $usersPage['page'];

$pageTitle = APP_NAME . ' | User Management';
$activePage = 'admin-users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">User Management</p>
                <h1 class="h2 mb-2">Manage accounts, roles, and activation status</h1>
                <p class="mb-0">Administrative control for citizens, staff, and system operators.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="<?= e($filters['search']) ?>" class="form-control" placeholder="Name, email, division">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All roles</option>
                            <?php foreach ($roles as $role) : ?>
                                <option value="<?= e($role['name']) ?>" <?= $filters['role'] === $role['name'] ? 'selected' : '' ?>><?= e(ucfirst($role['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Account Status</label>
                        <select name="is_active" class="form-select">
                            <option value="">All accounts</option>
                            <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
                <div class="small text-muted mt-3">Showing <?= e((string) count($users)) ?> of <?= e((string) $totalUsers) ?> users</div>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Division</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user) : ?>
                                <tr>
                                    <td><?= e($user['full_name']) ?></td>
                                    <td><?= e($user['email']) ?></td>
                                    <td><span class="issue-badge secondary"><?= e(ucfirst($user['role_name'])) ?></span></td>
                                    <td><?= e((string) ($user['division'] ?? '')) ?></td>
                                    <td><span class="issue-badge <?= ((int) $user['is_active'] === 1) ? 'success' : 'dark' ?>"><?= ((int) $user['is_active'] === 1) ? 'Active' : 'Inactive' ?></span></td>
                                    <td><?= e((string) ($user['last_login_at'] ?? 'Never')) ?></td>
                                    <td>
                                        <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <input type="hidden" name="return_role" value="<?= e($filters['role']) ?>">
                                            <input type="hidden" name="return_is_active" value="<?= e($filters['is_active']) ?>">
                                            <input type="hidden" name="return_search" value="<?= e($filters['search']) ?>">
                                            <select name="role_id" class="form-select form-select-sm w-auto">
                                                <?php foreach ($roles as $role) : ?>
                                                    <option value="<?= e((string) $role['id']) ?>" <?= (int) $user['role_id'] === (int) $role['id'] ? 'selected' : '' ?>><?= e(ucfirst($role['name'])) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="active-<?= e((string) $user['id']) ?>" <?= ((int) $user['is_active'] === 1) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="active-<?= e((string) $user['id']) ?>">Active</label>
                                            </div>
                                            <input type="hidden" name="action" value="update_user">
                                            <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                        </form>
                                        <form method="post" class="d-flex gap-2 mt-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <input type="hidden" name="return_role" value="<?= e($filters['role']) ?>">
                                            <input type="hidden" name="return_is_active" value="<?= e($filters['is_active']) ?>">
                                            <input type="hidden" name="return_search" value="<?= e($filters['search']) ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="text" name="new_password" class="form-control form-control-sm" placeholder="Temporary password">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Reset Password</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$users) : ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No users matched the current filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1) : ?>
                    <nav class="mt-4" aria-label="User pagination">
                        <ul class="pagination justify-content-center mb-0 flex-wrap gap-1">
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= e(admin_users_page_url($returnFilters, max(1, $currentPage - 1))) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= e(admin_users_page_url($returnFilters, $i)) ?>"><?= e((string) $i) ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= e(admin_users_page_url($returnFilters, min($totalPages, $currentPage + 1))) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>