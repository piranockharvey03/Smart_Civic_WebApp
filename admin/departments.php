<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);
require_permission('manage_departments');

$user = current_user();
$errors = [];
$message = null;
$departmentUsers = db()->query("SELECT u.id, u.full_name, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1" . sql_table_deleted_cond('users', 'u') . " AND r.name IN ('staff', 'department_manager', 'admin') ORDER BY u.full_name ASC")->fetchAll();
$staffDepartmentReview = db()->query(
    "SELECT u.id, u.full_name, u.email, r.name AS role_name,
            d.department_name AS current_department_name,
            COALESCE(d.department_name, sp.department, 'Unmapped') AS current_department,
            sp.job_title
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN departments d ON d.department_id = u.department_id
     LEFT JOIN staff_profiles sp ON sp.user_id = u.id
     WHERE u.is_active = 1" . sql_table_deleted_cond('users', 'u') . "
       AND r.name IN ('staff', 'department_manager')
     ORDER BY u.full_name ASC"
)->fetchAll();
$departmentCleanupSummary = [
    'staff_mapped' => 0,
    'staff_unmapped' => 0,
    'legacy_profile_matches' => 0,
    'departments_active' => 0,
];

try {
    $summaryStmt = db()->query(
        "SELECT
            SUM(CASE WHEN u.department_id IS NOT NULL AND r.name IN ('staff', 'department_manager') THEN 1 ELSE 0 END) AS staff_mapped,
            SUM(CASE WHEN u.department_id IS NULL AND r.name IN ('staff', 'department_manager') THEN 1 ELSE 0 END) AS staff_unmapped,
            SUM(CASE WHEN u.department_id IS NULL AND sp.department IS NOT NULL AND sp.department <> '' THEN 1 ELSE 0 END) AS legacy_profile_matches,
            SUM(CASE WHEN d.status = 1 THEN 1 ELSE 0 END) AS departments_active
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         LEFT JOIN staff_profiles sp ON sp.user_id = u.id
         LEFT JOIN departments d ON d.department_id = u.department_id
         WHERE 1 = 1" . sql_table_deleted_cond('users', 'u')
    );
    $row = $summaryStmt->fetch();
    if ($row) {
        $departmentCleanupSummary['staff_mapped'] = (int) ($row['staff_mapped'] ?? 0);
        $departmentCleanupSummary['staff_unmapped'] = (int) ($row['staff_unmapped'] ?? 0);
        $departmentCleanupSummary['legacy_profile_matches'] = (int) ($row['legacy_profile_matches'] ?? 0);
        $departmentCleanupSummary['departments_active'] = (int) ($row['departments_active'] ?? 0);
    }
} catch (Throwable) {
    // Leave the cleanup summary at zero if the schema is partially migrated.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $name = trim((string) ($_POST['department_name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($name === '') {
                $errors[] = 'Department name is required.';
            } else {
                $stmt = db()->prepare('INSERT INTO departments (department_name, description, status, created_at, updated_at) VALUES (:department_name, :description, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $stmt->execute(['department_name' => $name, 'description' => $description !== '' ? $description : null]);
                department_clear_cache((int) db()->lastInsertId());
                admin_record_audit_log((int) $user['id'], 'department_created', 'departments', $name, 'Department created');
                $message = 'Department created successfully.';
            }
        } elseif ($action === 'update') {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $name = trim((string) ($_POST['department_name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $managerId = (int) ($_POST['manager_id'] ?? 0);
            $status = !empty($_POST['status']) ? 1 : 0;

            if ($departmentId < 1 || $name === '') {
                $errors[] = 'Department name is required.';
            } else {
                $stmt = db()->prepare('UPDATE departments SET department_name = :department_name, description = :description, manager_id = :manager_id, status = :status, updated_at = CURRENT_TIMESTAMP WHERE department_id = :department_id');
                $stmt->execute([
                    'department_name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'manager_id' => $managerId > 0 ? $managerId : null,
                    'status' => $status,
                    'department_id' => $departmentId,
                ]);
                department_clear_cache($departmentId);
                admin_record_audit_log((int) $user['id'], 'department_updated', 'departments', (string) $departmentId, 'Department updated');
                $message = 'Department updated successfully.';
            }
        } elseif ($action === 'assign_staff_department') {
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $departmentId = (int) ($_POST['department_id'] ?? 0);

            if ($staffId < 1 || $departmentId < 1) {
                $errors[] = 'Select a staff member and department.';
            } else {
                $update = db()->prepare('UPDATE users SET department_id = :department_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $update->execute([
                    'department_id' => $departmentId,
                    'id' => $staffId,
                ]);

                if (issue_table_exists('staff_profiles')) {
                    $departmentNameStmt = db()->prepare('SELECT department_name FROM departments WHERE department_id = :department_id LIMIT 1');
                    $departmentNameStmt->execute(['department_id' => $departmentId]);
                    $departmentName = (string) ($departmentNameStmt->fetch()['department_name'] ?? '');

                    if ($departmentName !== '') {
                        $profileUpdate = db()->prepare('UPDATE staff_profiles SET department = :department WHERE user_id = :user_id');
                        $profileUpdate->execute([
                            'department' => $departmentName,
                            'user_id' => $staffId,
                        ]);
                    }
                }

                admin_record_audit_log((int) $user['id'], 'staff_department_assigned', 'users', (string) $staffId, 'Department assigned to staff');
                $message = 'Staff department assigned successfully.';
            }
        }
    }
}

$departments = department_fetch_departments(false);
$pageTitle = APP_NAME . ' | Departments';
$activePage = 'admin-departments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Departments Active</div><div class="card-value mt-2"><?= e((string) $departmentCleanupSummary['departments_active']) ?></div></div></div>
                <div class="col-md-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Staff Mapped</div><div class="card-value mt-2"><?= e((string) $departmentCleanupSummary['staff_mapped']) ?></div></div></div>
                <div class="col-md-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Staff Unmapped</div><div class="card-value mt-2"><?= e((string) $departmentCleanupSummary['staff_unmapped']) ?></div></div></div>
                <div class="col-md-3"><div class="app-card bg-white compact-card h-100"><div class="card-kicker">Legacy Profile Matches</div><div class="card-value mt-2"><?= e((string) $departmentCleanupSummary['legacy_profile_matches']) ?></div></div></div>
            </div>
        </div>

        <div class="col-lg-15">
            <div class="app-card bg-white compact-card">
                <h1 class="h4 mb-3">Departments</h1>
                <?php foreach ($errors as $error) : ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label">Department Name</label><input class="form-control" name="department_name" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
                    <button type="submit" class="btn btn-primary w-100">Create Department</button>
                </form>
            </div>
        </div>
        <div class="col-lg-15">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Name</th><th>Manager</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($departments as $department) : ?>
                            <tr>
                                <td>
                                    <form method="post" class="vstack gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="department_id" value="<?= e((string) $department['department_id']) ?>">
                                        <input type="text" class="form-control form-control-sm" name="department_name" value="<?= e((string) $department['department_name']) ?>" required>
                                        <textarea class="form-control form-control-sm" name="description" rows="2"><?= e((string) ($department['description'] ?? '')) ?></textarea>
                                </td>
                                <td>
                                        <select class="form-select form-select-sm" name="manager_id">
                                            <option value="0">Unassigned</option>
                                            <?php foreach ($departmentUsers as $departmentUser) : ?>
                                                <option value="<?= e((string) $departmentUser['id']) ?>" <?= (int) ($department['manager_id'] ?? 0) === (int) $departmentUser['id'] ? 'selected' : '' ?>><?= e($departmentUser['full_name']) ?> (<?= e($departmentUser['role_name']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="status" value="1" <?= (int) ($department['status'] ?? 0) === 1 ? 'checked' : '' ?>>
                                            <span class="form-check-label">Active</span>
                                        </label>
                                </td>
                                <td><?= e(date('d M Y', strtotime((string) $department['created_at']))) ?></td>
                                <td>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="app-card bg-white compact-card mt-4">
                <h2 class="h5 mb-3">Staff Department Review</h2>
                <?php if (!$staffDepartmentReview) : ?>
                    <p class="text-muted mb-0">No staff accounts were found.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Name</th><th>Current Dept</th><th>Role</th><th>Move To Department</th></tr></thead>
                            <tbody>
                            <?php foreach ($staffDepartmentReview as $staff) : ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($staff['full_name']) ?></div>
                                        <div class="small text-muted"><?= e($staff['email']) ?></div>
                                    </td>
                                    <td><?= e((string) ($staff['current_department'] ?? 'Unmapped')) ?></td>
                                    <td><?= e($staff['role_name']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="assign_staff_department">
                                            <input type="hidden" name="staff_id" value="<?= e((string) $staff['id']) ?>">
                                            <select class="form-select form-select-sm" name="department_id" required>
                                                <option value="">Select department</option>
                                                <?php foreach ($departments as $department) : ?>
                                                    <option value="<?= e((string) $department['department_id']) ?>" <?= ((int) ($staff['current_department_name'] ?? 0) === (int) $department['department_id']) ? 'selected' : '' ?>><?= e((string) $department['department_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>