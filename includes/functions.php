<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function app_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function old_checked(string $key): bool
{
    return !empty($_SESSION['old'][$key]);
}

function flash_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function app_cache_table_exists(): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute(['table_name' => 'app_cache']);
        $cache = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    } catch (Throwable) {
        $cache = false;
    }

    return $cache;
}

function app_cache_key(string $key): string
{
    return substr(preg_replace('/[^a-zA-Z0-9:_-]/', '_', $key) ?: $key, 0, 190);
}

function app_cache_get(string $key): mixed
{
    if (!app_cache_table_exists()) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT cache_value, expires_at FROM app_cache WHERE cache_key = :cache_key LIMIT 1');
        $stmt->execute(['cache_key' => app_cache_key($key)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            app_cache_forget($key);
            return null;
        }

        return json_decode((string) $row['cache_value'], true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return null;
    }
}

function app_cache_set(string $key, mixed $value, int $ttlSeconds): void
{
    if (!app_cache_table_exists()) {
        return;
    }

    $ttlSeconds = max(1, $ttlSeconds);
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $ttlSeconds . ' seconds');

    try {
        $stmt = db()->prepare(
            'INSERT INTO app_cache (cache_key, cache_value, expires_at, updated_at)
             VALUES (:cache_key, :cache_value, :expires_at, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'cache_key' => app_cache_key($key),
            'cache_value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    } catch (Throwable) {
        // Cache writes are best-effort.
    }
}

function app_cache_forget(string|array $keys): void
{
    if (!app_cache_table_exists()) {
        return;
    }

    $keys = array_values(array_filter((array) $keys, static fn ($key): bool => is_string($key) && $key !== ''));
    if ($keys === []) {
        return;
    }

    try {
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = db()->prepare('DELETE FROM app_cache WHERE cache_key IN (' . $placeholders . ')');
        $stmt->execute(array_map('app_cache_key', $keys));
    } catch (Throwable) {
        // Cache deletions are best-effort.
    }
}

function app_cache_remember(string $key, int $ttlSeconds, callable $resolver): mixed
{
    $cached = app_cache_get($key);
    if ($cached !== null) {
        return $cached;
    }

    $value = $resolver();
    app_cache_set($key, $value, $ttlSeconds);

    return $value;
}

function app_rate_limit_key(string $scope, array $dimensions): string
{
    ksort($dimensions);

    return 'rate:' . $scope . ':' . hash('sha256', json_encode($dimensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function app_rate_limit_state(string $scope, array $dimensions, int $limit, int $windowSeconds): array
{
    $now = time();
    $key = app_rate_limit_key($scope, $dimensions);
    $state = app_cache_get($key);

    $count = 0;
    $expiresAt = $now + max(1, $windowSeconds);

    if (is_array($state)) {
        $count = max(0, (int) ($state['count'] ?? 0));
        $storedExpiresAt = max(0, (int) ($state['expires_at'] ?? 0));

        if ($storedExpiresAt > $now) {
            $expiresAt = $storedExpiresAt;
        }
    }

    $blocked = $count >= $limit;

    return [
        'key' => $key,
        'count' => $count,
        'limit' => $limit,
        'window_seconds' => $windowSeconds,
        'expires_at' => $expiresAt,
        'remaining' => max(0, $limit - $count),
        'blocked' => $blocked,
        'retry_after' => $blocked ? max(1, $expiresAt - $now) : 0,
    ];
}

function app_rate_limit_is_blocked(string $scope, array $dimensions, int $limit, int $windowSeconds): bool
{
    return app_rate_limit_state($scope, $dimensions, $limit, $windowSeconds)['blocked'];
}

function app_rate_limit_record_failure(string $scope, array $dimensions, int $limit, int $windowSeconds): array
{
    $state = app_rate_limit_state($scope, $dimensions, $limit, $windowSeconds);

    if ($state['blocked']) {
        return $state;
    }

    $state['count']++;
    $state['remaining'] = max(0, $limit - $state['count']);
    $state['blocked'] = $state['count'] >= $limit;
    $state['retry_after'] = $state['blocked'] ? max(1, $state['expires_at'] - time()) : 0;

    app_cache_set($state['key'], [
        'count' => $state['count'],
        'expires_at' => $state['expires_at'],
    ], max(1, $state['expires_at'] - time()));

    return $state;
}

function app_rate_limit_clear(string $scope, array $dimensions): void
{
    app_cache_forget(app_rate_limit_key($scope, $dimensions));
}

function issue_detail_url(int $issueId, ?string $role = null): string
{
    $query = ['id' => $issueId];

    if ($role !== null && $role !== '') {
        $query['role'] = $role;
    }

    return app_url('issues/view.php?' . http_build_query($query));
}

function db_column_exists(string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        $cache[$key] = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    } catch (Throwable) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function sql_table_deleted_cond(string $table, string $alias): string
{
    return db_column_exists($table, 'deleted_at') ? ' AND ' . $alias . '.deleted_at IS NULL' : '';
}
