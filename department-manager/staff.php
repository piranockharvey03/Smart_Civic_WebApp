<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['department_manager']);
require_permission('manage_department_staff');

$user = current_user();
$departmentId = department_current_user_department_id($user);
$errors = [];
$message = null;

// Validate department before processing
if ($departmentId === null) {
    $errors[] = 'You are not assigned to a department. Please contact an administrator to assign you to a department before managing staff.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $created = department_create_staff($_POST, (int) $user['id'], (int) $departmentId, $errors);
            if ($created) {
                $message = 'Staff account created successfully.';
            }
        } elseif ($action === 'toggle') {
            department_toggle_staff_status((int) ($_POST['staff_id'] ?? 0), (int) $user['id'], (int) $departmentId, !empty($_POST['is_active']));
            $message = 'Staff status updated.';
        }
    }
}

$staffList = [];
if ($departmentId !== null) {
    $stmt = db()->prepare('SELECT u.id, u.full_name, u.email, u.is_active, u.created_at, u.created_by, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.department_id = :department_id ORDER BY u.full_name ASC');
    $stmt->execute(['department_id' => $departmentId]);
    $staffList = $stmt->fetchAll();
}

$pageTitle = APP_NAME . ' | Department Staff';
$activePage = 'department-manager-staff';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12"><div class="app-card bg-white compact-card"><div class="section-header"><div><h1 class="h3 mb-1">Staff Management</h1><p class="text-muted mb-0">Create and manage staff within your department.</p></div></div></div></div>
        <div class="col-lg-5">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Add Staff</h2>
                <?php foreach ($errors as $error) : ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
                    <div class="mb-3"><label class="form-label">Job Title</label><input class="form-control" name="job_title" value="Staff Member"></div>
                    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
                    <button class="btn btn-primary w-100" type="submit">Create Staff Account</button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Staff List</h2>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($staffList as $staff) : ?>
                            <tr>
                                <td><?= e($staff['full_name']) ?></td>
                                <td><?= e($staff['email']) ?></td>
                                <td><?= (int) $staff['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                <td><?= e(date('d M Y', strtotime((string) $staff['created_at']))) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary me-1" href="<?= e(app_url('department-manager/staff-edit.php?id=' . (int) $staff['id'])) ?>">Edit</a>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="staff_id" value="<?= e((string) $staff['id']) ?>">
                                        <input type="hidden" name="is_active" value="<?= (int) $staff['is_active'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= (int) $staff['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
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