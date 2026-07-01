<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$user = current_user();
$userId = (int) $user['id'];
$errors = [];
$profile = auth_fetch_citizen_profile($userId) ?? [
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => '',
    'division' => $user['division'] ?? '',
    'address' => '',
    'ward' => '',
    'national_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $profile['phone'] = trim((string) ($_POST['phone'] ?? ''));
        $profile['division'] = trim((string) ($_POST['division'] ?? ''));
        $profile['address'] = trim((string) ($_POST['address'] ?? ''));
        $profile['ward'] = trim((string) ($_POST['ward'] ?? ''));
        $profile['national_id'] = trim((string) ($_POST['national_id'] ?? ''));

        if (auth_update_citizen_profile($userId, $profile, $errors)) {
            set_flash('success', 'Your profile has been updated.');
            redirect(app_url('citizen/profile.php'));
        }
    }
}

$pageTitle = APP_NAME . ' | Profile';
$activePage = 'citizen-profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4">
                <p class="text-uppercase small text-muted mb-2">Citizen Account</p>
                <h1 class="h3 mb-2">Profile settings</h1>
                <p class="mb-0 text-muted">Keep your contact details current so KCCA staff can follow up on your reports.</p>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white p-4">
                <?php foreach ($errors as $error) : ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" action="">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input type="text" class="form-control" value="<?= e((string) $profile['full_name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email address</label>
                            <input type="email" class="form-control" value="<?= e((string) $profile['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e((string) $profile['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="division" class="form-label">Division / area</label>
                            <input type="text" class="form-control" id="division" name="division" value="<?= e((string) $profile['division']) ?>">
                        </div>
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= e((string) $profile['address']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="ward" class="form-label">Ward</label>
                            <input type="text" class="form-control" id="ward" name="ward" value="<?= e((string) $profile['ward']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="national_id" class="form-label">National ID</label>
                            <input type="text" class="form-control" id="national_id" name="national_id" value="<?= e((string) $profile['national_id']) ?>">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">Account help</h2>
                <p class="text-muted">Need to change your password?</p>
                <a class="btn btn-outline-secondary w-100 mb-3" href="<?= e(app_url('auth/forgot-password.php')) ?>">Reset password</a>
                <p class="text-muted mb-2">You can also track a ticket publicly without signing in.</p>
                <a class="btn btn-outline-primary w-100" href="<?= e(app_url('track-issue.php')) ?>">Track a ticket</a>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
