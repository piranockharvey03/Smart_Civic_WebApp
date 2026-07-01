<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['department_manager']);
require_permission('manage_department_staff');

$user = current_user();
$departmentId = department_current_user_department_id($user);
$staffId = (int) ($_GET['id'] ?? 0);
$errors = [];
$message = null;

$stmt = db()->prepare('SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.department_id, sp.job_title FROM users u LEFT JOIN staff_profiles sp ON sp.user_id = u.id WHERE u.id = :id LIMIT 1');
$stmt->execute(['id' => $staffId]);
$staff = $stmt->fetch();

if (!$staff || (int) ($staff['department_id'] ?? 0) !== (int) $departmentId) {
    http_response_code(404);
    exit('Staff member not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token.';
    } else {
        $result = department_update_staff($staffId, $_POST, (int) $user['id'], (int) $departmentId, $errors);
        if ($result) {
            $message = 'Staff account updated successfully.';
            $stmt->execute(['id' => $staffId]);
            $staff = $stmt->fetch();
        }
    }
}

$pageTitle = APP_NAME . ' | Edit Staff';
$activePage = 'department-manager-staff';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="app-card bg-white compact-card">
                <h1 class="h3 mb-3">Edit Staff Account</h1>
                <?php foreach ($errors as $error) : ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($message) : ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e((string) $staff['full_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string) $staff['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e((string) ($staff['phone'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Job Title</label><input class="form-control" name="job_title" value="<?= e((string) ($staff['job_title'] ?? 'Staff Member')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" placeholder="Leave blank to keep existing password"></div>
                    <div class="col-md-6 d-flex align-items-end">
                        <label class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int) $staff['is_active'] === 1 ? 'checked' : '' ?>>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= e(app_url('department-manager/staff.php')) ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>