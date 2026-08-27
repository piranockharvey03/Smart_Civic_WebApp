<?php

declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}

$activePage = $activePage ?? '';
$user = current_user();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= e(app_url('KCCA.png')) ?>" type="image/png">
    <script>
        (function() {
            const storageKey = 'smart-civic-theme';
            const root = document.documentElement;

            const applyTheme = (theme) => {
                const nextTheme = theme === 'dark' ? 'dark' : 'light';

                root.setAttribute('data-bs-theme', nextTheme);
                root.style.colorScheme = nextTheme;
            };

            try {
                const savedTheme = localStorage.getItem(storageKey);
                const theme = savedTheme || 'light';

                applyTheme(theme);
            } catch (error) {
                applyTheme('light');
            }
        })();
    </script>
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(app_url('assets/css/style.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/style.css')) ?>" rel="stylesheet">
    <?php foreach (($pageStyles ?? []) as $pageStyle) : ?>
        <link href="<?= e($pageStyle) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>

<body class="app-shell">
    <!-- Toast container for flash pop-ups -->
    <div id="flashToasts" aria-live="polite" aria-atomic="true" class="position-fixed top-0 end-0 p-3" style="z-index:1080;">
        <?php if ($msg = get_flash('success')) : ?>
            <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body"><?= e($msg) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($msg = get_flash('error')) : ?>
            <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body"><?= e($msg) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if (is_logged_in()) : ?>
        <?php $currentRole = current_user_role(); ?>
        <?php $userDepartment = null; ?>
        <?php if (in_array($currentRole, ['staff', 'department_manager'], true)) : ?>
            <?php $userDepartmentId = department_current_user_department_id($user); ?>
            <?php $userDepartment = $userDepartmentId ? department_fetch_department_by_id($userDepartmentId) : null; ?>
        <?php endif; ?>
        <nav class="navbar navbar-expand-lg navbar-light app-topbar">
            <div class="container-fluid">
                <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand fw-semibold text-success me-auto d-flex align-items-center gap-2" href="<?= e(app_url('index.php')) ?>">
                    <img src="<?= e(app_url('KCCA.png')) ?>" alt="KCCA Logo" width="40" height="40">
                    <?= e(APP_NAME) ?>
                </a>
                <div class="d-flex align-items-center gap-3 text-muted small">
                    <button type="button" class="btn btn-sm btn-outline-secondary theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Toggle dark mode">
                        <i class="bi bi-moon-stars" data-theme-icon></i>
                        <span class="d-none d-sm-inline" data-theme-label>Dark mode</span>
                    </button>
                    <div class="d-flex align-items-center gap-2">
                        <span><?= e($user['full_name'] ?? '') ?></span>
                        <span class="badge text-bg-light text-uppercase"><?= e($user['role'] ?? '') ?></span>
                        <?php if (in_array($currentRole, ['staff', 'department_manager'], true)) : ?>
                            <span class="badge text-bg-light">Department: <?= e((string) ($userDepartment['department_name'] ?? 'Unassigned')) ?></span>
                        <?php endif; ?>
                            <form action="<?= e(app_url('auth/logout-other.php' . ($currentRole ? '?role=' . urlencode($currentRole) : ''))) ?>" method="POST" class="d-inline ms-2" onsubmit="return confirm('Log out other devices? This will end sessions on other devices. Continue?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Log out other devices">Log out other devices</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    <?php else : ?>
        <nav class="navbar navbar-expand-lg navbar-light app-topbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-semibold text-success me-auto d-flex align-items-center gap-2" href="<?= e(app_url('index.php')) ?>">
                    <img src="<?= e(app_url('KCCA.png')) ?>" alt="KCCA Logo" width="40" height="40">
                    <?= e(APP_NAME) ?>
                </a>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-success" href="<?= e(app_url('auth/register.php')) ?>">Report an Issue</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(app_url('track-issue.php')) ?>">Track Ticket</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(app_url('auth/citizen-login.php')) ?>">Citizen Login</a>
                </div>
            </div>
        </nav>
    <?php endif; ?>
    <div class="app-page<?= is_logged_in() ? ' d-flex' : '' ?>">