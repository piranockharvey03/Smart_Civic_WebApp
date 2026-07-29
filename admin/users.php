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
    'deleted' => trim((string) ($_GET['deleted'] ?? '')),
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
    'deleted' => $filters['deleted'],
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
    // Handle creation of new staff accounts before other user-targeted actions
    if ($action === 'create_staff') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $division = trim((string) ($_POST['division'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $forcePasswordReset = isset($_POST['force_password_reset']) ? 1 : 0;

        if ($fullName === '' || $email === '') {
            set_flash('error', 'Name and email are required to create a staff account.');
            redirect(admin_users_list_url($returnFilters));
        }

        // determine staff role id
        $staffRoleId = null;
        foreach ($roles as $r) {
            if ((string) $r['name'] === 'staff') {
                $staffRoleId = (int) $r['id'];
                break;
            }
        }

        if ($staffRoleId === null) {
            set_flash('error', 'Staff role is not configured in the system.');
            redirect(admin_users_list_url($returnFilters));
        }

        // check for existing email
        $existsStmt = db()->prepare('SELECT COUNT(*) AS total FROM users WHERE email = :email');
        $existsStmt->execute(['email' => $email]);
        if (((int) ($existsStmt->fetch()['total'] ?? 0)) > 0) {
            set_flash('error', 'An account with that email already exists.');
            redirect(admin_users_list_url($returnFilters));
        }

        if ($password === '') {
            $password = 'Kcca@' . bin2hex(random_bytes(4));
        }

        $hasResetColumn = db_column_exists('users', 'must_change_password');
        $insertColumns = ['full_name', 'email', 'password', 'role_id', 'division', 'is_active'];
        $placeholders = [':full_name', ':email', ':password', ':role_id', ':division', '1'];
        $params = [
            'full_name' => $fullName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $staffRoleId,
            'division' => $division ?: null,
        ];

        if ($hasResetColumn) {
            $insertColumns[] = 'must_change_password';
            $placeholders[] = ':must_change_password';
            $params['must_change_password'] = $forcePasswordReset;
        }

        $stmt = db()->prepare('INSERT INTO users (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $stmt->execute($params);

        $newUserId = (int) db()->lastInsertId();
        admin_record_audit_log((int) $currentUser['id'], 'user_created', 'users', (string) $newUserId, 'Staff account created');
        admin_record_user_activity((int) $currentUser['id'], 'user_created', 'users', $newUserId, ['email' => $email]);
        $_SESSION['temp_credential_notice'] = [
            'title' => 'Temporary staff password ready',
            'email' => $email,
            'password' => $password,
        ];
        set_flash('success', 'Staff account created successfully.');
        redirect(admin_users_list_url($returnFilters));
    }
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $returnFilters = [
        'role' => trim((string) ($_POST['return_role'] ?? '')),
        'is_active' => trim((string) ($_POST['return_is_active'] ?? '')),
        'deleted' => trim((string) ($_POST['return_deleted'] ?? '')),
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

            $stmt = db()->prepare('UPDATE users SET role_id = :role_id, is_active = :is_active WHERE id = :id AND deleted_at IS NULL');
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

            $hasResetColumn = db_column_exists('users', 'must_change_password');
            $resetColumnSql = $hasResetColumn ? ', must_change_password = 1' : '';
            $stmt = db()->prepare('UPDATE users SET password = :password' . $resetColumnSql . ' WHERE id = :id');
            $stmt->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $targetUserId,
            ]);

            admin_record_audit_log((int) $currentUser['id'], 'password_reset', 'users', (string) $targetUserId, 'Temporary password issued for user account');
            admin_record_user_activity((int) $currentUser['id'], 'password_reset', 'users', $targetUserId, ['target_user' => $targetUser['email']]);
            $_SESSION['temp_credential_notice'] = [
                'title' => 'Temporary user password ready',
                'email' => $targetUser['email'],
                'password' => $newPassword,
            ];
            set_flash('success', 'Password reset successfully.');
        } elseif ($action === 'trash_user') {
            if ($targetUserId === (int) $currentUser['id']) {
                set_flash('error', 'You cannot delete your own account.');
                redirect(admin_users_list_url($returnFilters));
            }

            $stmt = db()->prepare('UPDATE users SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by, is_active = 0 WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute([
                'deleted_by' => (int) $currentUser['id'],
                'id' => $targetUserId,
            ]);

            admin_record_audit_log((int) $currentUser['id'], 'user_trashed', 'users', (string) $targetUserId, 'User account moved to trash');
            admin_record_user_activity((int) $currentUser['id'], 'user_trashed', 'users', $targetUserId, ['target_user' => $targetUser['email']]);
            set_flash('success', 'User account moved to trash.');
        } elseif ($action === 'restore_user') {
            $stmt = db()->prepare('UPDATE users SET deleted_at = NULL, deleted_by = NULL WHERE id = :id AND deleted_at IS NOT NULL');
            $stmt->execute(['id' => $targetUserId]);

            admin_record_audit_log((int) $currentUser['id'], 'user_restored', 'users', (string) $targetUserId, 'User account restored from trash');
            admin_record_user_activity((int) $currentUser['id'], 'user_restored', 'users', $targetUserId, ['target_user' => $targetUser['email']]);
            set_flash('success', 'User account restored successfully.');
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

$tempCredentialNotice = $_SESSION['temp_credential_notice'] ?? null;
unset($_SESSION['temp_credential_notice']);
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
                <?php if ($tempCredentialNotice) : ?>
                    <div class="alert alert-success d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <div>
                            <div class="fw-semibold mb-1"><?= e((string) $tempCredentialNotice['title']) ?></div>
                            <div class="small mb-1">Email: <?= e((string) $tempCredentialNotice['email']) ?></div>
                            <div class="small">This password is shown once. The user will be forced to change it on first login.</div>
                        </div>
                        <div class="d-flex flex-column align-items-md-end gap-2">
                            <input type="password" class="form-control form-control-sm w-auto" id="tempCredentialPassword" value="<?= e((string) $tempCredentialNotice['password']) ?>" readonly>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="(function(){const input=document.getElementById('tempCredentialPassword'); const reveal=(input.type==='password'); input.type=reveal?'text':'password'; if(reveal){window.clearTimeout(window.__tempCredentialHideTimer); window.__tempCredentialHideTimer=window.setTimeout(function(){input.type='password';},15000);} })()">Reveal 15s</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('tempCredentialPassword').value)">Copy Password</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <h3 class="h6">Create Staff Account</h3>
                    <p class="text-muted small mb-2">A temporary password will force a reset on first login.</p>
                    <form method="post" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_staff">
                        <div class="col-md-2">
                            <label class="form-label">Full name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Staff member name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="staffexample@gmail.org" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Division</label>
                            <input type="text" name="division" class="form-control" placeholder="Roads">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Password (optional)</label>
                            <input type="password" name="password" class="form-control" placeholder="temporary password">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4 pt-1">
                                <input class="form-check-input" type="checkbox" name="force_password_reset" id="force_password_reset" checked>
                                <label class="form-check-label small" for="force_password_reset">Force first-login reset</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">Create</button>
                        </div>
                    </form>
                </div>
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="<?= e($filters['search']) ?>" class="form-control" placeholder="Name, email, division">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All roles</option>
                            <?php foreach ($roles as $role) : ?>
                                <option value="<?= e($role['name']) ?>" <?= $filters['role'] === $role['name'] ? 'selected' : '' ?>><?= e(ucfirst($role['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Account Status</label>
                        <select name="is_active" class="form-select">
                            <option value="">All accounts</option>
                            <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Trash</label>
                        <select name="deleted" class="form-select">
                            <option value="">Active only</option>
                            <option value="1" <?= $filters['deleted'] === '1' ? 'selected' : '' ?>>Deleted</option>
                            <option value="all" <?= $filters['deleted'] === 'all' ? 'selected' : '' ?>>All</option>
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
                    <table class="table table-hover align-middle  mb-0">
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
                                        <form method="post" class="d-flex flex-wrap gap-1 align-items-center">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <input type="hidden" name="return_role" value="<?= e($filters['role']) ?>">
                                            <input type="hidden" name="return_is_active" value="<?= e($filters['is_active']) ?>">
                                            <input type="hidden" name="return_deleted" value="<?= e($filters['deleted']) ?>">
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
                                        <form method="post" class="d-flex gap-1 mt-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <input type="hidden" name="return_role" value="<?= e($filters['role']) ?>">
                                            <input type="hidden" name="return_is_active" value="<?= e($filters['is_active']) ?>">
                                            <input type="hidden" name="return_deleted" value="<?= e($filters['deleted']) ?>">
                                            <input type="hidden" name="return_search" value="<?= e($filters['search']) ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="text" name="new_password" class="form-control form-control-sm" placeholder="Temporary password">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Reset Password</button>
                                        </form>
                                        <form method="post" class="d-flex gap-1 mt-1" onsubmit="return confirm('Move this user to trash?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                            <input type="hidden" name="return_role" value="<?= e($filters['role']) ?>">
                                            <input type="hidden" name="return_is_active" value="<?= e($filters['is_active']) ?>">
                                            <input type="hidden" name="return_deleted" value="<?= e($filters['deleted']) ?>">
                                            <input type="hidden" name="return_search" value="<?= e($filters['search']) ?>">
                                            <input type="hidden" name="action" value="trash_user">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Trash</button>
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

                <div class="mt-4 d-flex justify-content-end">
                    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/trash.php')) ?>">Open Trash Center</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>