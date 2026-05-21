<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (!$errors) {
            $stmt = db()->prepare(
                'SELECT u.id, u.full_name, u.email, u.password, u.role_id,
                    sp.phone AS phone,
                    sp.division AS division,
                        r.name AS role_name
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 LEFT JOIN staff_profiles sp ON sp.user_id = u.id
                 WHERE u.email = :email AND u.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && in_array($user['role_name'], ['admin', 'staff'], true) && password_verify($password, $user['password'])) {
                login_user($user);
                persist_user_session($user);
                clear_old();
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                redirect(dashboard_url_for_role($user['role_name']));
            }

            $errors[] = 'Invalid email or password, or this account must use the citizen login page.';
        }
    }

    flash_old(['email' => $email]);
}

$pageTitle = APP_NAME . ' | Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper container">
    <div class="row auth-card bg-white g-0">
        <div class="col-lg-5 auth-aside d-flex flex-column justify-content-between">
            <div>
                <div class="portal-brand">
                    <span class="emblem" aria-hidden="true"></span>
                    <div class="title">KCCA Smart Civic App</div>
                </div>
                <h2 class="h5 fw-semibold">Staff & Admin Login</h2>
                <p class="mt-3 mb-0 text-muted">Secure access for staff and administrators only.</p>
            </div>
            <div class="pt-4 small text-muted">Kampala Capital City Authority</div>
        </div>
        <div class="col-lg-7 p-5">
            <h2 class="h4 mb-2">Sign in to your account</h2>
            <p class="text-muted mb-4">Use your staff or administrator credentials to access services.</p>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <!-- Flash toasts are shown in the header as pop-ups -->

            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= old('email', $email) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="<?= e(app_url('auth/citizen-login.php')) ?>">Citizen login</a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>