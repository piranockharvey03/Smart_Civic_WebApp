<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);
require_permission('manage_users');

$currentUser = current_user();
$staffId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$returnFilters = [
    'role' => trim((string) ($_GET['return_role'] ?? $_POST['return_role'] ?? '')),
    'is_active' => trim((string) ($_GET['return_is_active'] ?? $_POST['return_is_active'] ?? '')),
    'deleted' => trim((string) ($_GET['return_deleted'] ?? $_POST['return_deleted'] ?? '')),
    'search' => trim((string) ($_GET['return_search'] ?? $_POST['return_search'] ?? '')),
];
$returnUrl = app_url('admin/users.php' . ($returnFilters ? '?' . http_build_query($returnFilters) : ''));
$errors = [];
$message = null;

$departments = department_fetch_departments(true);
$departmentLookup = [];
foreach ($departments as $department) {
    $departmentLookup[(int) $department['department_id']] = (string) $department['department_name'];
}

$deletedFilter = db_column_exists('users', 'deleted_at') ? ' AND u.deleted_at IS NULL' : '';
$staffStmt = db()->prepare(
    'SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.department_id,
            r.name AS role_name, sp.job_title
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN staff_profiles sp ON sp.user_id = u.id
     WHERE u.id = :id' . $deletedFilter . ' LIMIT 1'
);
$staffStmt->execute(['id' => $staffId]);
$staff = $staffStmt->fetch();

if (!$staff || !in_array((string) $staff['role_name'], ['staff', 'department_manager'], true)) {
    http_response_code(404);
    exit('Staff member not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token.';
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $newPassword = (string) ($_POST['password'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($phone !== '' && mb_strlen($phone) > 20) {
        $errors[] = 'Phone number must be 20 characters or fewer.';
    }

    if ($departmentId < 1 || !isset($departmentLookup[$departmentId])) {
        $errors[] = 'Select a valid active department.';
    }

    $emailStmt = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $emailStmt->execute(['email' => $email, 'id' => $staffId]);
    if ($emailStmt->fetch()) {
        $errors[] = 'Another user already uses this email.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();

            $userFields = 'full_name = :full_name, email = :email, phone = :phone, department_id = :department_id, is_active = :is_active, updated_at = CURRENT_TIMESTAMP';
            $userParams = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'department_id' => $departmentId,
                'is_active' => $isActive,
                'id' => $staffId,
            ];

            if ($newPassword !== '') {
                if (mb_strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                    throw new InvalidArgumentException('Password must be at least 8 characters long and include upper-case letters, lower-case letters, and numbers.');
                }

                $userFields .= ', password = :password';
                $userParams['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                if (db_column_exists('users', 'must_change_password')) {
                    $userFields .= ', must_change_password = 1';
                }
            }

            $updateStmt = db()->prepare('UPDATE users SET ' . $userFields . ' WHERE id = :id');
            $updateStmt->execute($userParams);

            if (issue_table_exists('staff_profiles')) {
                $profileStmt = db()->prepare(
                    'INSERT INTO staff_profiles (user_id, employee_number, department, job_title, office_location, phone, division)
                     VALUES (:user_id, :employee_number, :department, :job_title, :office_location, :phone, :division)
                     ON DUPLICATE KEY UPDATE
                        department = VALUES(department),
                        job_title = VALUES(job_title),
                        phone = VALUES(phone)'
                );
                $profileStmt->execute([
                    'user_id' => $staffId,
                    'employee_number' => 'STAFF-' . str_pad((string) $staffId, 5, '0', STR_PAD_LEFT),
                    'department' => $departmentLookup[$departmentId],
                    'job_title' => $jobTitle !== '' ? $jobTitle : 'Staff Member',
                    'office_location' => null,
                    'phone' => $phone !== '' ? $phone : null,
                    'division' => null,
                ]);
            }

            admin_record_audit_log((int) $currentUser['id'], 'staff_details_updated', 'users', (string) $staffId, 'Staff details updated by administrator');
            admin_record_user_activity((int) $currentUser['id'], 'staff_details_updated', 'users', $staffId, ['target_user' => $email]);
            db()->commit();
            set_flash('success', 'Staff details updated successfully.');
            redirect($returnUrl);
        } catch (InvalidArgumentException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (Throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'Unable to update the staff account at this time.';
        }
    }

    $staff['full_name'] = $fullName;
    $staff['email'] = $email;
    $staff['phone'] = $phone;
    $staff['job_title'] = $jobTitle;
    $staff['department_id'] = $departmentId;
    $staff['is_active'] = $isActive;
}

$pageTitle = APP_NAME . ' | Edit Staff Details';
$activePage = 'admin-users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="app-card bg-white compact-card">
                <div class="section-header mb-4">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Administrative user management</p>
                        <h1 class="h3 mb-1">Edit Staff Details</h1>
                        <p class="text-muted mb-0">Update this staff account and its department assignment.</p>
                    </div>
                    <span class="issue-badge secondary"><?= e(ucfirst(str_replace('_', ' ', (string) $staff['role_name']))) ?></span>
                </div>
                <?php foreach ($errors as $error) : ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= e((string) $staff['id']) ?>">
                    <?php foreach ($returnFilters as $filterName => $filterValue) : ?>
                        <input type="hidden" name="return_<?= e($filterName) ?>" value="<?= e($filterValue) ?>">
                    <?php endforeach; ?>
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full name</label>
                        <input class="form-control" id="full_name" name="full_name" value="<?= e((string) $staff['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" type="email" name="email" value="<?= e((string) $staff['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e((string) ($staff['phone'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="job_title">Job title</label>
                        <input class="form-control" id="job_title" name="job_title" value="<?= e((string) ($staff['job_title'] ?? 'Staff Member')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="department_id">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select department</option>
                            <?php foreach ($departmentLookup as $departmentId => $departmentName) : ?>
                                <option value="<?= e((string) $departmentId) ?>" <?= (int) $staff['department_id'] === $departmentId ? 'selected' : '' ?>><?= e($departmentName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control" id="password" type="password" name="password" placeholder="Leave blank to keep existing password">
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int) $staff['is_active'] === 1 ? 'checked' : '' ?>>
                            <span class="form-check-label">Active account</span>
                        </label>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= e($returnUrl) ?>" class="btn btn-outline-secondary">Back to Users</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
