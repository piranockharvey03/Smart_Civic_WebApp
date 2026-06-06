<?php

declare(strict_types=1);

function system_logs_table_exists(): bool
{
    return issue_table_exists('system_logs');
}

function app_client_ip(): ?string
{
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function app_user_agent(): ?string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null;
}

function app_log_system_event(string $logType, string $severity, string $message, array $context = [], ?int $userId = null, ?string $source = null): void
{
    if (!system_logs_table_exists()) {
        error_log(sprintf('[%s][%s] %s', $severity, $logType, $message));
        return;
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO system_logs (user_id, log_type, severity, source, message, context_json, ip_address, user_agent)
             VALUES (:user_id, :log_type, :severity, :source, :message, :context_json, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'log_type' => $logType,
            'severity' => $severity,
            'source' => $source,
            // Store the full message in the DB (column will be altered to TEXT by migration)
            'message' => (string) $message,
            'context_json' => $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => app_client_ip(),
            'user_agent' => app_user_agent(),
        ]);
    } catch (Throwable $throwable) {
        error_log(sprintf('[%s][%s] %s | logging_failed: %s', $severity, $logType, $message, $throwable->getMessage()));
    }
}

function system_log_severity_badge_class(string $severity): string
{
    return match (strtolower($severity)) {
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'dark',
        default => 'secondary',
    };
}

function app_log_exception(Throwable $throwable, string $severity = 'error'): void
{
    $userId = $_SESSION['user']['id'] ?? null;

    app_log_system_event(
        'exception',
        $severity,
        $throwable->getMessage(),
        [
            'exception_class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ],
        is_int($userId) ? $userId : null,
        $throwable->getFile() . ':' . $throwable->getLine()
    );
}

function app_render_error_page(int $statusCode, string $title, string $message): never
{
    http_response_code($statusCode);
    $safeTitle = e($title);
    $safeMessage = e($message);

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $safeTitle . '</title>';
    echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f4f6f9;color:#1f2937;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px}.card{max-width:560px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;box-shadow:0 12px 30px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:28px}p{margin:0;line-height:1.6}.code{margin-top:18px;font-size:13px;color:#6b7280}</style></head><body><main class="card"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p><div class="code">If the problem continues, contact the system administrator and provide the time of the error.</div></main></body></html>';
    exit;
}

function app_handle_exception(Throwable $throwable): never
{
    app_log_exception($throwable);

    if (APP_ENV === 'local') {
        app_render_error_page(500, 'Application Error', $throwable->getMessage());
    }

    app_render_error_page(500, 'Something Went Wrong', 'The system encountered an unexpected error. Please try again later.');
}

function app_handle_error(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $exception = new ErrorException($message, 0, $severity, $file, $line);
    app_log_exception($exception, 'warning');

    if (APP_ENV === 'local') {
        app_render_error_page(500, 'Application Warning', $message . ' in ' . $file . ':' . $line);
    }

    return true;
}

function app_handle_shutdown(): void
{
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    app_log_system_event(
        'fatal',
        'critical',
        $error['message'],
        [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type'],
        ],
        isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null,
        $error['file'] . ':' . $error['line']
    );
}

function app_register_error_handlers(): void
{
    set_exception_handler('app_handle_exception');
    set_error_handler('app_handle_error');
    register_shutdown_function('app_handle_shutdown');
}