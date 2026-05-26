<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();
admin_seed_role_permissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/permissions.php'));
    }

    $roleId = (int) ($_POST['role_id'] ?? 0);
    $permissionIds = array_map('intval', $_POST['permissions'] ?? []);
    $permissionIds = array_filter($permissionIds, static fn (int $value): bool => $value > 0);

    $delete = db()->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
    $delete->execute(['role_id' => $roleId]);

    if ($permissionIds) {
        $insert = db()->prepare('INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (:role_id, :permission_id, :granted_by)');
        foreach ($permissionIds as $permissionId) {
            $insert->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'granted_by' => (int) $currentUser['id'],
            ]);
        }
    }

    admin_record_audit_log((int) $currentUser['id'], 'permissions_updated', 'role_permissions', (string) $roleId, 'Role permissions updated');
    set_flash('success', 'Role permissions saved successfully.');
    redirect(app_url('admin/permissions.php'));
}

$roles = db()->query('SELECT id, name FROM roles ORDER BY id ASC')->fetchAll();
$permissions = db()->query('SELECT id, `key`, module, `description` FROM permissions ORDER BY module ASC, `key` ASC')->fetchAll();
$existing = db()->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();
$matrix = [];
foreach ($existing as $row) {
    $matrix[(int) $row['role_id']][(int) $row['permission_id']] = true;
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
                            <button type="submit" class="btn btn-primary">Save Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>