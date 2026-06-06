<?php

declare(strict_types=1);

function render_auth_page(array $config): void
{
    $pageTitle = (string) ($config['pageTitle'] ?? (APP_NAME . ' | Login'));
    $pageEyebrow = (string) ($config['pageEyebrow'] ?? 'Portal access');
    $sidebarTitle = (string) ($config['sidebarTitle'] ?? 'KCCA Smart Civic App');
    $sidebarHeading = (string) ($config['sidebarHeading'] ?? 'Login');
    $sidebarDescription = (string) ($config['sidebarDescription'] ?? 'Secure access for registered users.');
    $mainHeading = (string) ($config['mainHeading'] ?? 'Sign in to your account');
    $mainDescription = (string) ($config['mainDescription'] ?? 'Use your credentials to continue.');
    $supportText = (string) ($config['supportText'] ?? 'Kampala Capital City Authority');
    $supportNote = (string) ($config['supportNote'] ?? 'If you need access help, contact your administrator.');
    $formAction = (string) ($config['formAction'] ?? '');
    $emailValue = (string) ($config['emailValue'] ?? '');
    $errors = $config['errors'] ?? [];
    $links = is_array($config['links'] ?? null) ? $config['links'] : [];
    $footerLinks = is_array($config['footerLinks'] ?? null) ? $config['footerLinks'] : [];
    $footerNote = (string) ($config['footerNote'] ?? 'Need access help? Contact your administrator or service desk.');
    $rememberMeEnabled = (bool) ($config['rememberMeEnabled'] ?? true);
    $rememberMeChecked = (bool) ($config['rememberMeChecked'] ?? false);
    $rememberMeLabel = (string) ($config['rememberMeLabel'] ?? 'Remember me on this device');
    $submitLabel = (string) ($config['submitLabel'] ?? 'Login');
    $linkGapClass = (string) ($config['linkGapClass'] ?? 'd-flex justify-content-between align-items-center mb-4 flex-wrap gap-2');

    require_once __DIR__ . '/header.php';
    ?>
    <div class="auth-wrapper container">
        <div class="row auth-card bg-white g-0">
            <div class="col-lg-5 auth-aside d-flex flex-column justify-content-between">
                <div>
                    <div class="auth-badge mb-3"><?= e($pageEyebrow) ?></div>
                    <div class="portal-brand">
                        <img class="emblem" src="<?= e(app_url('KCCA.png')) ?>" alt="KCCA logo">
                        <div class="title"><?= e($sidebarTitle) ?></div>
                    </div>
                    <div class="auth-ornament mb-4" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <h2 class="h5 fw-semibold"><?= e($sidebarHeading) ?></h2>
                    <p class="mt-3 mb-0 text-muted"><?= e($sidebarDescription) ?></p>
                </div>
                <div class="auth-support pt-4">
                    <div class="small text-uppercase fw-semibold auth-support-label"><?= e($supportText) ?></div>
                    <div class="small text-muted mt-1"><?= e($supportNote) ?></div>
                </div>
            </div>
            <div class="col-lg-7 p-5">
                <h2 class="h4 mb-2"><?= e($mainHeading) ?></h2>
                <p class="text-muted mb-4"><?= e($mainDescription) ?></p>

                <?php foreach ((array) $errors as $error) : ?>
                    <div class="alert alert-danger"><?= e((string) $error) ?></div>
                <?php endforeach; ?>

                <form method="post" action="<?= e($formAction) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= old('email', $emailValue) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    <?php if ($rememberMeEnabled) : ?>
                        <div class="form-check auth-remember mb-3">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1" <?= (old_checked('remember_me') || $rememberMeChecked) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remember_me"><?= e($rememberMeLabel) ?></label>
                        </div>
                    <?php endif; ?>
                    <div class="<?= e($linkGapClass) ?>">
                        <?php foreach ($links as $link) : ?>
                            <?php if (!is_array($link)) { continue; } ?>
                            <a href="<?= e((string) ($link['href'] ?? '#')) ?>"><?= e((string) ($link['label'] ?? '')) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="auth-form-footer small text-muted mb-3">
                        <div class="mb-2"><?= e($footerNote) ?></div>
                        <?php if ($footerLinks !== []) : ?>
                            <div class="d-flex flex-wrap gap-3 auth-footer-link">
                                <?php foreach ($footerLinks as $link) : ?>
                                    <?php if (!is_array($link)) { continue; } ?>
                                    <a href="<?= e((string) ($link['href'] ?? '#')) ?>"><?= e((string) ($link['label'] ?? '')) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100"><?= e($submitLabel) ?></button>
                </form>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/footer.php';
}