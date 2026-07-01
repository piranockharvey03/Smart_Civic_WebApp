<?php

declare(strict_types=1);

function session_cookie_options(): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function session_namespace_for_role(?string $role): string
{
    return match ($role) {
        'admin' => 'smart_civic_admin_session',
        'department_manager' => 'smart_civic_department_manager_session',
        'staff' => 'smart_civic_staff_session',
        'citizen' => 'smart_civic_citizen_session',
        default => 'smart_civic_shared_session',
    };
}

function session_namespace_for_request(): string
{
    $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $role = trim((string) ($_GET['role'] ?? ''));

    if (in_array($scriptName, ['password-reset.php', 'logout.php', 'logout-other.php'], true)) {
        if (in_array($role, ['admin', 'department_manager', 'staff', 'citizen'], true)) {
            return session_namespace_for_role($role);
        }

        return 'smart_civic_auth_session';
    }

    if (str_contains($scriptPath, '/admin/')) {
        return session_namespace_for_role('admin');
    }

    if (str_contains($scriptPath, '/department-manager/')) {
        return session_namespace_for_role('department_manager');
    }

    if (str_contains($scriptPath, '/staff/')) {
        return session_namespace_for_role('staff');
    }

    if (str_contains($scriptPath, '/citizen/')) {
        return session_namespace_for_role('citizen');
    }

    if ($scriptName === 'citizen-login.php') {
        return 'smart_civic_citizen_auth_session';
    }

    if ($scriptName === 'department-manager-login.php') {
        return 'smart_civic_department_manager_auth_session';
    }

    if ($scriptName === 'login.php') {
        return 'smart_civic_internal_auth_session';
    }

    if (in_array($role, ['admin', 'department_manager', 'staff', 'citizen'], true)) {
        return session_namespace_for_role($role);
    }

    return 'smart_civic_shared_session';
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(session_namespace_for_request());
    session_set_cookie_params(session_cookie_options());
    session_start();
}

function switch_secure_session_namespace(string $namespace): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    session_name($namespace);
    session_set_cookie_params(session_cookie_options());
    session_start();
}
