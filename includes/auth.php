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

function remember_me_cookie_name(): string
{
    return 'smart_civic_remember';
}

function remember_me_cookie_lifetime(): int
{
    return 60 * 60 * 24 * 30;
}

function remember_me_cookie_options(int $expires): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function set_remember_me_cookie(string $value, int $expires): void
{
    setcookie(remember_me_cookie_name(), $value, remember_me_cookie_options($expires));
}

function clear_remember_me_cookie(): void
{
    setcookie(remember_me_cookie_name(), '', remember_me_cookie_options(time() - 3600));
}

function remember_me_tokens_table_exists(): bool
{
    return issue_table_exists('user_remember_tokens');
}

function auth_fetch_login_user_by_id(int $userId, string $profileTable): ?array
{
    $hasResetColumn = db_column_exists('users', 'must_change_password');
    $resetSelect = $hasResetColumn ? ', u.must_change_password' : ', 0 AS must_change_password';
    $deletedFilter = db_column_exists('users', 'deleted_at') ? ' AND u.deleted_at IS NULL' : '';

    $profileJoin = '';
    if ($profileTable !== '' && db_column_exists($profileTable, 'user_id')) {
        $profileAlias = $profileTable === 'staff_profiles' ? 'sp' : 'cp';
        $profileJoin = ' LEFT JOIN ' . $profileTable . ' ' . $profileAlias . ' ON ' . $profileAlias . '.user_id = u.id';
        $profileSelect = ', ' . $profileAlias . '.phone AS phone, ' . $profileAlias . '.division AS division';
    } else {
        $profileSelect = ', NULL AS phone, NULL AS division';
    }

    $sql =
        'SELECT u.id, u.full_name, u.email, u.password, u.role_id' .
        $resetSelect .
        $profileSelect .
        ', r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id' .
        $profileJoin .
        ' WHERE u.id = :id AND u.is_active = 1' .
        $deletedFilter .
        ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $userId]);

    $user = $stmt->fetch();

    return is_array($user) ? $user : null;
}

function auth_fetch_login_user(string $email, array $allowedRoles, string $profileTable): ?array
{
    $email = trim($email);

    if ($email === '') {
        return null;
    }

    $allowedRoles = array_values(array_filter(array_map('strval', $allowedRoles), static fn (string $role): bool => $role !== ''));
    if (!$allowedRoles) {
        return null;
    }

    $hasResetColumn = db_column_exists('users', 'must_change_password');
    $resetSelect = $hasResetColumn ? ', u.must_change_password' : ', 0 AS must_change_password';
    $deletedFilter = db_column_exists('users', 'deleted_at') ? ' AND u.deleted_at IS NULL' : '';

    $profileJoin = '';
    if ($profileTable !== '' && db_column_exists($profileTable, 'user_id')) {
        $profileAlias = $profileTable === 'staff_profiles' ? 'sp' : 'cp';
        $profileJoin = ' LEFT JOIN ' . $profileTable . ' ' . $profileAlias . ' ON ' . $profileAlias . '.user_id = u.id';
        $profileSelect = ', ' . $profileAlias . '.phone AS phone, ' . $profileAlias . '.division AS division';
    } else {
        $profileSelect = ', NULL AS phone, NULL AS division';
    }

    $placeholders = implode(', ', array_fill(0, count($allowedRoles), '?'));

    $sql =
        'SELECT u.id, u.full_name, u.email, u.password, u.role_id' .
        $resetSelect .
        $profileSelect .
        ', r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id' .
        $profileJoin .
        ' WHERE u.email = ? AND u.is_active = 1' .
        $deletedFilter .
        ' AND r.name IN (' . $placeholders . ')
         LIMIT 1';

    $stmt = db()->prepare($sql);
    $params = array_merge([$email], $allowedRoles);
    $stmt->execute($params);

    $user = $stmt->fetch();

    return is_array($user) ? $user : null;
}

function remember_me_cookie_parts(?string $cookieValue): ?array
{
    $cookieValue = trim((string) $cookieValue);

    if ($cookieValue === '') {
        return null;
    }

    $parts = explode(':', $cookieValue, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }

    return [
        'selector' => $parts[0],
        'validator' => $parts[1],
    ];
}

function issue_remember_me_token(array $user): void
{
    if (!remember_me_tokens_table_exists()) {
        clear_remember_me_cookie();
        return;
    }

    try {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . remember_me_cookie_lifetime() . ' seconds');

        $stmt = db()->prepare(
            'INSERT INTO user_remember_tokens
                (user_id, selector, token_hash, expires_at, ip_address, user_agent)
             VALUES
                (:user_id, :selector, :token_hash, :expires_at, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'user_id' => (int) $user['id'],
            'selector' => $selector,
            'token_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null,
        ]);

        set_remember_me_cookie($selector . ':' . $validator, $expiresAt->getTimestamp());
    } catch (Throwable) {
        clear_remember_me_cookie();
    }
}

function revoke_remember_me_token(?string $cookieValue = null): void
{
    if (!remember_me_tokens_table_exists()) {
        clear_remember_me_cookie();
        return;
    }

    $parts = remember_me_cookie_parts($cookieValue ?? ($_COOKIE[remember_me_cookie_name()] ?? null));
    if ($parts !== null) {
        try {
            $stmt = db()->prepare(
                'UPDATE user_remember_tokens
                 SET revoked_at = CURRENT_TIMESTAMP
                 WHERE selector = :selector AND revoked_at IS NULL'
            );
            $stmt->execute(['selector' => $parts['selector']]);
        } catch (Throwable) {
            // Ignore token cleanup failures during logout.
        }
    }

    clear_remember_me_cookie();
}

function revoke_other_devices(): array
{
    if (!is_logged_in() || empty($_SESSION['user']['id'])) {
        return ['sessions' => 0, 'tokens' => 0];
    }

    $userId = (int) $_SESSION['user']['id'];
    $currentSession = session_id();

    $sessionsRemoved = 0;
    $tokensRevoked = 0;

    try {
        $del = db()->prepare('DELETE FROM user_sessions WHERE user_id = :user_id AND session_id != :session_id');
        $del->execute(['user_id' => $userId, 'session_id' => $currentSession]);
        $sessionsRemoved = $del->rowCount();
    } catch (Throwable) {
        $sessionsRemoved = 0;
    }

    if (remember_me_tokens_table_exists()) {
        try {
            $upd = db()->prepare('UPDATE user_remember_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND revoked_at IS NULL');
            $upd->execute(['user_id' => $userId]);
            $tokensRevoked = $upd->rowCount();
        } catch (Throwable) {
            $tokensRevoked = 0;
        }
    }

    // Audit the action
    try {
        $audit = db()->prepare('INSERT INTO auth_audit_logs (user_id, action, ip_address, user_agent, details) VALUES (:user_id, :action, :ip_address, :user_agent, :details)');
        $audit->execute([
            'user_id' => $userId,
            'action' => 'revoke_other_devices',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null,
            'details' => json_encode(['sessions_removed' => $sessionsRemoved, 'tokens_revoked' => $tokensRevoked]),
        ]);
    } catch (Throwable) {
        // Do not block on audit failures
    }

    return ['sessions' => $sessionsRemoved, 'tokens' => $tokensRevoked];
}

function attempt_remember_me_login(): bool
{
    if (is_logged_in()) {
        return true;
    }

    $parts = remember_me_cookie_parts($_COOKIE[remember_me_cookie_name()] ?? null);
    if ($parts === null || !remember_me_tokens_table_exists()) {
        if ($parts !== null) {
            clear_remember_me_cookie();
        }

        return false;
    }

    $stmt = db()->prepare(
        'SELECT t.user_id, t.token_hash, t.expires_at, t.revoked_at, r.name AS role_name
         FROM user_remember_tokens t
         INNER JOIN users u ON u.id = t.user_id
         INNER JOIN roles r ON r.id = u.role_id
         WHERE t.selector = :selector
         LIMIT 1'
    );
    $stmt->execute(['selector' => $parts['selector']]);

    $token = $stmt->fetch();
    if (!is_array($token)) {
        clear_remember_me_cookie();
        return false;
    }

    $expiresAt = strtotime((string) ($token['expires_at'] ?? ''));
    $isExpired = $expiresAt !== false && $expiresAt < time();
    $isRevoked = !empty($token['revoked_at']);
    $isValid = !$isExpired && !$isRevoked && hash_equals((string) $token['token_hash'], hash('sha256', $parts['validator']));

    if (!$isValid) {
        revoke_remember_me_token($parts['selector'] . ':' . $parts['validator']);
        return false;
    }

    $profileTable = ($token['role_name'] ?? '') === 'citizen' ? 'citizen_profiles' : 'staff_profiles';
    $user = auth_fetch_login_user_by_id((int) $token['user_id'], $profileTable);

    if (!$user) {
        revoke_remember_me_token($parts['selector'] . ':' . $parts['validator']);
        return false;
    }

    $touch = db()->prepare(
        'UPDATE user_remember_tokens
         SET last_used_at = CURRENT_TIMESTAMP
         WHERE selector = :selector'
    );
    $touch->execute(['selector' => $parts['selector']]);

    login_user($user);
    persist_user_session($user);

    return true;
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
        'must_change_password' => !empty($user['must_change_password']) ? 1 : 0,
    ];
}

function current_user_must_change_password(): bool
{
    return !empty($_SESSION['user']['must_change_password']);
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
    revoke_remember_me_token();

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
        app_log_system_event(
            'security',
            'warning',
            'Unauthorized access attempt blocked',
            [
                'required_roles' => array_values($allowedRoles),
                'current_role' => $role,
                'path' => $_SERVER['REQUEST_URI'] ?? null,
            ],
            isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null,
            __FUNCTION__
        );

        http_response_code(403);
        app_render_error_page(403, '403 Forbidden', 'You do not have permission to access this page.');
    }
}
