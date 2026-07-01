<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$reset = $token !== '' ? auth_validate_password_reset_token($token) : null;
$errors = [];
$password = '';
$passwordConfirm = '';

if ($token === '' || $reset === null) {
    $errors[] = 'This password reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset !== null) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors && !auth_complete_password_reset($token, $password)) {
            $errors[] = 'The reset link could not be used. Request a new one and try again.';
        }

        if (!$errors) {
            set_flash('success', 'Your password has been reset. Please sign in with your new password.');
            redirect(app_url('auth/citizen-login.php'));
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
                <h2 class="h5 fw-semibold">Choose a New Password</h2>
                <p class="mt-3 mb-0 text-muted">Create a strong password to secure your citizen account.</p>
            </div>
            <div class="pt-4 small text-muted">Kampala Capital City Authority</div>
        </div>
        <div class="col-lg-7 p-5">
            <h2 class="h4 mb-2">Set a new password</h2>
            <p class="text-muted mb-4">Use at least 8 characters.</p>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <?php if ($reset !== null) : ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
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
            <?php else : ?>
                <a class="btn btn-outline-primary" href="<?= e(app_url('auth/forgot-password.php')) ?>">Request a new reset link</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
