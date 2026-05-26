<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(app_url('index.php'));
}

$errors = [];
$data = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'division' => '',
    'national_id' => '',
    'address' => '',
    'ward' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $data['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $data['email'] = trim((string) ($_POST['email'] ?? ''));
        $data['phone'] = trim((string) ($_POST['phone'] ?? ''));
        $data['division'] = trim((string) ($_POST['division'] ?? ''));
        $data['national_id'] = trim((string) ($_POST['national_id'] ?? ''));
        $data['address'] = trim((string) ($_POST['address'] ?? ''));
        $data['ward'] = trim((string) ($_POST['ward'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($data['full_name'] === '' || mb_strlen($data['full_name']) < 3) {
            $errors[] = 'Full name must be at least 3 characters long.';
        }

        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($data['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $data['phone'])) {
            $errors[] = 'Enter a valid phone number.';
        }

        if ($password === '' || mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $data['email']]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if (!$errors) {
            $roleStmt = db()->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $roleStmt->execute(['name' => 'citizen']);
            $role = $roleStmt->fetch();

            if (!$role) {
                $errors[] = 'Citizen role is not configured in the database.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = db()->prepare(
                    'INSERT INTO users (full_name, email, password, role_id)
                     VALUES (:full_name, :email, :password, :role_id)'
                );
                $insert->execute([
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'password' => $hash,
                    'role_id' => (int) $role['id'],
                ]);

                $userId = (int) db()->lastInsertId();
                $profileInsert = db()->prepare(
                    'INSERT INTO citizen_profiles (user_id, national_id, phone, division, address, ward)
                     VALUES (:user_id, :national_id, :phone, :division, :address, :ward)'
                );
                $profileInsert->execute([
                    'user_id' => $userId,
                    'national_id' => $data['national_id'] !== '' ? $data['national_id'] : null,
                    'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                    'division' => $data['division'] !== '' ? $data['division'] : null,
                    'address' => $data['address'] !== '' ? $data['address'] : null,
                    'ward' => $data['ward'] !== '' ? $data['ward'] : null,
                ]);

                clear_old();
                set_flash('success', 'Registration successful. Please log in.');
                redirect(app_url('auth/citizen-login.php'));
            }
        }
    }

    flash_old($data);
}

$pageTitle = APP_NAME . ' | Citizen Registration';
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
                <h2 class="h5 fw-semibold">Citizen Registration</h2>
                <p class="mt-3 mb-0 text-muted">Register to submit and track civic service reports.</p>
            </div>
            <div class="pt-4 small text-muted">Kampala Capital City Authority</div>
        </div>
        <div class="col-lg-7 p-5">
            <h2 class="h4 mb-2">Create an account</h2>
            <p class="text-muted mb-4">Provide accurate information to register.</p>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" action="">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= old('full_name', $data['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email', $data['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= old('phone', $data['phone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="division" class="form-label">Division / Area</label>
                        <input type="text" class="form-control" id="division" name="division" value="<?= old('division', $data['division']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="national_id" class="form-label">National ID <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="national_id" name="national_id" value="<?= old('national_id', $data['national_id']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="address" class="form-label">Address <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="address" name="address" value="<?= old('address', $data['address']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="ward" class="form-label">Ward <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="ward" name="ward" value="<?= old('ward', $data['ward']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <a href="<?= e(app_url('auth/citizen-login.php')) ?>">Already have an account?</a>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Create Account</button>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>