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
        $rateLimitScope = 'department-manager-login';
        $rateLimitDimensions = [
            'email' => mb_strtolower($email),
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (!$errors) {
            if (app_rate_limit_is_blocked($rateLimitScope, $rateLimitDimensions, 5, 900)) {
                $errors[] = 'Too many login attempts. Please wait a few minutes and try again.';
            }

            if (!$errors) {
            $user = auth_fetch_login_user($email, ['department_manager'], 'staff_profiles');

            if ($user && password_verify($password, $user['password'])) {
                switch_secure_session_namespace(session_namespace_for_role($user['role_name']));
                login_user($user);
                persist_user_session($user);
                clear_old();

                if (!empty($user['must_change_password'])) {
                    clear_remember_me_cookie();
                    set_flash('warning', 'Please set a new password before continuing.');
                    redirect(app_url('auth/password-reset.php?role=' . urlencode($user['role_name'])));
                }

                if ($rememberMe) {
                    issue_remember_me_token($user);
                } else {
                    clear_remember_me_cookie();
                }

                app_rate_limit_clear($rateLimitScope, $rateLimitDimensions);
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect(dashboard_url_for_role($user['role_name']));
            }

            app_rate_limit_record_failure($rateLimitScope, $rateLimitDimensions, 5, 900);
            $errors[] = 'Invalid email or password, or this account must use a different login page.';
            }
        }
    }

    flash_old(['email' => $email, 'remember_me' => $rememberMe ? '1' : '0']);
}

render_auth_page([
    'pageTitle' => APP_NAME . ' | Department Manager Login',
    'sidebarTitle' => 'KCCA Smart Civic App',
    'sidebarHeading' => 'Department Manager Login',
    'sidebarDescription' => 'Secure access for department managers overseeing departmental operations.',
    'mainHeading' => 'Sign in to your department portal',
    'mainDescription' => 'Use your department manager credentials to access department issues, staff, and dashboards.',
    'supportText' => 'Kampala Capital City Authority',
    'formAction' => '',
    'emailValue' => $email,
    'errors' => $errors,
    'submitLabel' => 'Login',
    'linkGapClass' => 'd-flex justify-content-between align-items-center mb-4 flex-wrap gap-2',
    'rememberMeChecked' => $rememberMe,
    'links' => [
        ['href' => app_url('auth/login.php'), 'label' => 'Staff/admin login'],
        ['href' => app_url('auth/citizen-login.php'), 'label' => 'Citizen login'],
    ],
    'footerNote' => 'Department managers use the same credentials as other internal users, but land in the department portal after login.',
    'footerLinks' => [
        ['href' => app_url('auth/forgot-password.php'), 'label' => 'Forgot password'],
    ],
]);