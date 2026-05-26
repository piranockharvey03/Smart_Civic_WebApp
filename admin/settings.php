<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$currentUser = current_user();
$settings = admin_fetch_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token.');
        redirect(app_url('admin/settings.php'));
    }

    $payload = [
        'system_name' => trim((string) ($_POST['system_name'] ?? '')),
        'organization_name' => trim((string) ($_POST['organization_name'] ?? '')),
        'organization_tagline' => trim((string) ($_POST['organization_tagline'] ?? '')),
        'logo_url' => trim((string) ($_POST['logo_url'] ?? '')),
        'default_statuses' => trim((string) ($_POST['default_statuses'] ?? '')),
        'default_priorities' => trim((string) ($_POST['default_priorities'] ?? '')),
        'upload_limit_mb' => (string) max(1, (int) ($_POST['upload_limit_mb'] ?? 5)),
        'session_timeout_minutes' => (string) max(5, (int) ($_POST['session_timeout_minutes'] ?? 30)),
        'reports_retention_days' => (string) max(30, (int) ($_POST['reports_retention_days'] ?? 365)),
        'enable_audit_logging' => isset($_POST['enable_audit_logging']) ? '1' : '0',
    ];

    admin_update_settings($payload, (int) $currentUser['id']);
    admin_record_audit_log((int) $currentUser['id'], 'settings_updated', 'settings', null, 'System settings updated');
    set_flash('success', 'System settings saved successfully.');
    redirect(app_url('admin/settings.php'));
}

$pageTitle = APP_NAME . ' | Settings';
$activePage = 'admin-settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">System Settings</p>
                <h1 class="h2 mb-2">Centralized admin configuration</h1>
                <p class="mb-0">Maintain system identity, workflow defaults, security controls, and reporting thresholds.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <form method="post" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label">System Name</label>
                        <input type="text" name="system_name" class="form-control" value="<?= e((string) $settings['system_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Organization Name</label>
                        <input type="text" name="organization_name" class="form-control" value="<?= e((string) $settings['organization_name']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Organization Tagline</label>
                        <input type="text" name="organization_tagline" class="form-control" value="<?= e((string) $settings['organization_tagline']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo URL or Path</label>
                        <input type="text" name="logo_url" class="form-control" value="<?= e((string) $settings['logo_url']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">File Upload Limit (MB)</label>
                        <input type="number" min="1" name="upload_limit_mb" class="form-control" value="<?= e((string) $settings['upload_limit_mb']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Session Timeout (Minutes)</label>
                        <input type="number" min="5" name="session_timeout_minutes" class="form-control" value="<?= e((string) $settings['session_timeout_minutes']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reports Retention (Days)</label>
                        <input type="number" min="30" name="reports_retention_days" class="form-control" value="<?= e((string) $settings['reports_retention_days']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Default Statuses JSON</label>
                        <textarea name="default_statuses" class="form-control" rows="5"><?= e((string) $settings['default_statuses']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Default Priorities JSON</label>
                        <textarea name="default_priorities" class="form-control" rows="5"><?= e((string) $settings['default_priorities']) ?></textarea>
                    </div>
                    <div class="col-12 form-check">
                        <input class="form-check-input" type="checkbox" name="enable_audit_logging" id="enableAudit" <?= ((string) $settings['enable_audit_logging'] === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enableAudit">Enable audit logging</label>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>