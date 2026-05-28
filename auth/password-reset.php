<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login(app_url('auth/login.php'));

if (!current_user_must_change_password()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$errors = [];
$password = '';
$passwordConfirm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $hasResetColumn = db_column_exists('users', 'must_change_password');
            $sql = 'UPDATE users SET password = :password' . ($hasResetColumn ? ', must_change_password = 0' : '') . ' WHERE id = :id';
            $stmt = db()->prepare($sql);
            $stmt->execute([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'id' => (int) current_user()['id'],
            ]);

            $_SESSION['user']['must_change_password'] = 0;
            set_flash('success', 'Your password has been updated successfully.');
            redirect(dashboard_url_for_role(current_user_role()));
        }
    }
}

$pageTitle = APP_NAME . ' | Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper container">
    <div class="row auth-card bg-white g-0">
        <div class="col-lg-5 auth-aside d-flex flex-column justify-content-between">
            <div>
                <div class="portal-brand">
                    <img class="emblem" src="<?= e(app_url('KCCA.png')) ?>" alt="KCCA logo">
                    <div class="title">KCCA Smart Civic App</div>
                </div>
                <h2 class="h5 fw-semibold">Reset Your Password</h2>
                <p class="mt-3 mb-0 text-muted">You need to create a new password before continuing.</p>
            </div>
            <div class="pt-4 small text-muted">Kampala Capital City Authority</div>
        </div>
        <div class="col-lg-7 p-5">
            <h2 class="h4 mb-2">Set a new password</h2>
            <p class="text-muted mb-4">Use a password you have not used before.</p>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="password" class="form-label">New password</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required minlength="8">
                </div>
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Confirm password</label>
                    <input type="password" class="form-control form-control-lg" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>