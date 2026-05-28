<?php

declare(strict_types=1);

// Load Composer autoloader and environment variables (optional)
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;

    // Load .env if phpdotenv is installed; safeLoad avoids exceptions if no .env file exists
    if (class_exists(\Dotenv\Dotenv::class)) {
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    }
}

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logging.php';
require_once __DIR__ . '/../includes/issues.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
app_register_error_handlers();

if (is_logged_in() && current_user_must_change_password()) {
    $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($scriptName, ['password-reset.php', 'logout.php'], true)) {
        redirect(app_url('auth/password-reset.php'));
    }
}
