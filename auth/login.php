<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/auth-page.php';

if (is_logged_in()) {
    redirect(dashboard_url_for_role(current_user_role()));
}

$errors = [];
$email = '';
$rememberMe = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rememberMe = !empty($_POST['remember_me']);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (!$errors) {
            $user = auth_fetch_login_user($email, ['admin', 'staff'], 'staff_profiles');

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                persist_user_session($user);
                clear_old();
                if (!empty($user['must_change_password'])) {
                    clear_remember_me_cookie();
                    set_flash('warning', 'Please set a new password before continuing.');
                    redirect(app_url('auth/password-reset.php'));
                }

                if ($rememberMe) {
                    issue_remember_me_token($user);
                } else {
                    clear_remember_me_cookie();
                }

                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect(dashboard_url_for_role($user['role_name']));
            }

            $errors[] = 'Invalid email or password, or this account must use the citizen login page.';
        }
    }

    flash_old(['email' => $email, 'remember_me' => $rememberMe ? '1' : '0']);
}

render_auth_page([
    'pageTitle' => APP_NAME . ' | Login',
    'sidebarTitle' => 'KCCA Smart Civic App',
    'sidebarHeading' => 'Staff & Admin Login',
    'sidebarDescription' => 'Secure access for staff and administrators only.',
    'mainHeading' => 'Sign in to your account',
    'mainDescription' => 'Use your staff or administrator credentials to access services.',
    'supportText' => 'Kampala Capital City Authority',
    'formAction' => '',
    'emailValue' => $email,
    'errors' => $errors,
    'submitLabel' => 'Login',
    'linkGapClass' => 'd-flex justify-content-between align-items-center mb-4',
    'rememberMeChecked' => $rememberMe,
    'links' => [
        ['href' => app_url('auth/citizen-login.php'), 'label' => 'Citizen login'],
    ],
    'footerNote' => 'Need help accessing your account? Contact your system administrator.',
    'footerLinks' => [
        ['href' => app_url('auth/password-reset.php'), 'label' => 'Reset password'],
    ],
]);