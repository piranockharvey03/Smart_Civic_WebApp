<?php

declare(strict_types=1);

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function dashboard_url_for_role(?string $role): string
{
    return match ($role) {
        'admin' => app_url('admin/dashboard.php'),
        'staff' => app_url('staff/dashboard.php'),
        default => app_url('citizen/dashboard.php'),
    };
}

function login_url_for_roles(array $allowedRoles = []): string
{
    return (count($allowedRoles) === 1 && $allowedRoles[0] === 'citizen')
        ? app_url('auth/citizen-login.php')
        : app_url('auth/login.php');
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role_name'],
        'role_id' => (int) $user['role_id'],
        'division' => $user['division'] ?? null,
    ];
}

function persist_user_session(array $user): void
{
    $stmt = db()->prepare(
        'INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent)
         VALUES (:user_id, :session_id, :ip_address, :user_agent)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            last_activity = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        'user_id' => (int) $user['id'],
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null,
    ]);

    $audit = db()->prepare(
        'INSERT INTO auth_audit_logs (user_id, action, ip_address, user_agent, details)
         VALUES (:user_id, :action, :ip_address, :user_agent, :details)'
    );

    $audit->execute([
        'user_id' => (int) $user['id'],
        'action' => 'login',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null,
        'details' => 'Session created for role ' . ($user['role_name'] ?? 'unknown'),
    ]);

    $touch = db()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $touch->execute(['id' => (int) $user['id']]);
}

function logout_user(): void
{
    if (isset($_SESSION['user']['id'])) {
        $delete = db()->prepare('DELETE FROM user_sessions WHERE session_id = :session_id');
        $delete->execute(['session_id' => session_id()]);

        $audit = db()->prepare(
            'INSERT INTO auth_audit_logs (user_id, action, ip_address, user_agent, details)
             VALUES (:user_id, :action, :ip_address, :user_agent, :details)'
        );
        $audit->execute([
            'user_id' => (int) $_SESSION['user']['id'],
            'action' => 'logout',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null,
            'details' => 'Session destroyed by user',
        ]);
    }

    // Preserve flash messages across logout so the user sees the success message
    $flash = $_SESSION['flash'] ?? null;

    // Clear session and keep only flash if present
    $_SESSION = [];
    if ($flash !== null) {
        $_SESSION['flash'] = $flash;
    }

    // Regenerate session id to avoid fixation; user data has been removed
    session_regenerate_id(true);
}

function require_login(?string $loginPath = null): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect($loginPath ?? app_url('auth/login.php'));
    }
}

function require_role(array $allowedRoles): void
{
    require_login(login_url_for_roles($allowedRoles));

    $role = current_user_role();

    if ($role === null || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}
