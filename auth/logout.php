<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

// set a flash message; `logout_user()` preserves flash
set_flash('success', 'You have been logged out.');
$role = current_user_role();
logout_user();
redirect($role === 'citizen' ? app_url('auth/citizen-login.php') : app_url('auth/login.php'));
