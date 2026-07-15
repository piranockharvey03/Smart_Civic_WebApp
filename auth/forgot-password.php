<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$errors = [];
$email = '';
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if (!$errors) {
            if (!password_reset_tokens_table_exists()) {
                $errors[] = 'Password recovery is not available yet. Contact the KCCA support desk.';
            } else {
                $token = auth_request_password_reset($email);
                if (APP_ENV === 'local' && $token !== null) {
                    $resetLink = app_url('auth/reset-password.php?token=' . urlencode($token));
                }
            }

            if (!$errors) {
                set_flash('success', 'If an account exists for that email, password reset instructions have been prepared.');
                if ($resetLink !== null) {
                    set_flash('success', 'Development reset link: ' . $resetLink);
                }
                redirect(app_url('auth/forgot-password.php'));
            }
        }
    }

    flash_old(['email' => $email]);
}

$pageTitle = APP_NAME . ' | Forgot Password';
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
                <h2 class="h5 fw-semibold">Forgot Password</h2>
                <p class="mt-3 mb-0 text-muted">Recover access to your citizen account using the email you registered with.</p>
            </div>
            <div class="pt-4 small text-muted">Kampala Capital City Authority</div>
        </div>
        <div class="col-lg-7 p-5">
            <h2 class="h4 mb-2">Reset your password</h2>
            <p class="text-muted mb-4">On entering your email address, we will prepare a secure reset link.</p>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control form-control-lg" id="email" placeholder="Enter your email address" name="email" value="<?= old('email', $email) ?>" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <a href="<?= e(app_url('auth/citizen-login.php')) ?>">Back to citizen login</a>
                    <a href="<?= e(app_url('track-issue.php')) ?>">Track a ticket</a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Send Reset Link</button>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
