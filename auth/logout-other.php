<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (!is_logged_in()) {
    set_flash('error', 'You must be signed in to perform this action.');
    redirect(app_url('auth/login.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method.');
    redirect($_SERVER['HTTP_REFERER'] ?? app_url('index.php'));
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid security token.');
    redirect($_SERVER['HTTP_REFERER'] ?? app_url('index.php'));
}

$result = revoke_other_devices();

set_flash('success', 'Logged out other devices (' . (int) $result['sessions'] . ' sessions, ' . (int) $result['tokens'] . ' tokens revoked).');

redirect($_SERVER['HTTP_REFERER'] ?? app_url('index.php'));
