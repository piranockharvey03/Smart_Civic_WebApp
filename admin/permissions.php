<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();
$permissionsReady = admin_permissions_table_exists() && issue_table_exists('role_permissions');
$roles = db()->query('SELECT id, name FROM roles ORDER BY id ASC')->fetchAll();
$permissions = [];
$existing = [];
$matrix = [];

if ($permissionsReady) {
    admin_seed_role_permissions();
    $permissions = db()->query('SELECT id, `key`, module, `description` FROM permissions ORDER BY module ASC, `key` ASC')->fetchAll();
    $existing = db()->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();

    foreach ($existing as $row) {
        $matrix[(int) $row['role_id']][(int) $row['permission_id']] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/permissions.php'));
    }

    if (!$permissionsReady) {
        set_flash('error', 'Permission tables are not installed. Run the phase four admin reporting migration first.');
        redirect(app_url('admin/permissions.php'));
    }

    $roleId = (int) ($_POST['role_id'] ?? 0);
    $roleIds = array_flip(array_map(static fn (array $role): int => (int) $role['id'], $roles));

    if (!isset($roleIds[$roleId])) {
        set_flash('error', 'Select a valid role before saving permissions.');
        redirect(app_url('admin/permissions.php'));
    }

    $validPermissionIds = array_flip(array_map(static fn (array $permission): int => (int) $permission['id'], $permissions));
    $permissionIds = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['permissions'] ?? [])),
        static fn (int $value): bool => $value > 0
    )));

    foreach ($permissionIds as $permissionId) {
        if (!isset($validPermissionIds[$permissionId])) {
            set_flash('error', 'One or more selected permissions are no longer available.');
            redirect(app_url('admin/permissions.php'));
        }
    }

    db()->beginTransaction();

    try {
        $delete = db()->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
        $delete->execute(['role_id' => $roleId]);

        if ($permissionIds) {
            $hasGrantedByColumn = db_column_exists('role_permissions', 'granted_by');
            $insertSql = $hasGrantedByColumn
                ? 'INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (:role_id, :permission_id, :granted_by)'
                : 'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)';
            $insert = db()->prepare($insertSql);

            foreach ($permissionIds as $permissionId) {
                $params = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ];

                if ($hasGrantedByColumn) {
                    $params['granted_by'] = (int) $currentUser['id'];
                }

                $insert->execute($params);
            }
        }

        db()->commit();

        admin_record_audit_log((int) $currentUser['id'], 'permissions_updated', 'role_permissions', (string) $roleId, 'Role permissions updated');
        set_flash('success', 'Role permissions saved successfully.');
    } catch (Throwable $throwable) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        app_log_exception($throwable);
        set_flash('error', 'Unable to save role permissions right now.');
    }

    redirect(app_url('admin/permissions.php'));
}

$pageTitle = APP_NAME . ' | Permissions';
$activePage = 'admin-permissions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Role & Permission Management</p>
                <h1 class="h2 mb-2">Granular access control</h1>
                <p class="mb-0">Manage permissions for issues, reporting, analytics, users, settings, and audit access.</p>
            </div>
        </div>

        <?php if (!$permissionsReady) : ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    Permission tables are not installed. Run <code>database/migrations/2026_05_26_phase_four_admin_reporting.sql</code> before managing role permissions.
                </div>
            </div>
        <?php elseif (!$permissions) : ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No permissions are configured yet. Refresh this page after the permissions catalog has been seeded.
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($roles as $role) : ?>
            <div class="col-12">
                <div class="app-card bg-white compact-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?= e(ucfirst($role['name'])) ?> Role</h2>
                            <p class="text-muted mb-0">Select the capabilities available to this role.</p>
                        </div>
                        <span class="issue-badge secondary">Role ID <?= e((string) $role['id']) ?></span>
                    </div>
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role_id" value="<?= e((string) $role['id']) ?>">
                        <?php foreach ($permissions as $permission) : ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e((string) $permission['id']) ?>" id="perm-<?= e((string) $role['id']) ?>-<?= e((string) $permission['id']) ?>" <?= isset($matrix[(int) $role['id']][(int) $permission['id']]) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="perm-<?= e((string) $role['id']) ?>-<?= e((string) $permission['id']) ?>">
                                            <?= e((string) $permission['key']) ?>
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1"><?= e((string) $permission['module']) ?> | <?= e((string) $permission['description']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" <?= (!$permissionsReady || !$permissions) ? 'disabled' : '' ?>>Save Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
