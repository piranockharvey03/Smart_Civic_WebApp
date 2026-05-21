<?php

declare(strict_types=1);

function env(string $key, $default = null)
{
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }
    return $default;
}

define('APP_NAME', env('APP_NAME') ?: 'Smart Civic App');
define('APP_ENV', env('APP_ENV') ?: 'local');
define('BASE_PATH', __DIR__ . '/..');
define('BASE_URL', env('BASE_URL') ?: 'http://localhost/app');

define('DB_HOST', env('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', env('DB_NAME') ?: 'smart_civic_app');
define('DB_USER', env('DB_USER') ?: 'root');
define('DB_PASS', env('DB_PASS') ?: '');
define('DB_CHARSET', env('DB_CHARSET') ?: 'utf8mb4');
