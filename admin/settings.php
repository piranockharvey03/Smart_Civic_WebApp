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

    // Validate JSON inputs for statuses/priorities and normalize
    $raw_statuses = trim((string) ($_POST['default_statuses'] ?? ''));
    $raw_priorities = trim((string) ($_POST['default_priorities'] ?? ''));

    // Helper validation: returns normalized JSON string or null on error
    $normalize_catalog = function (string $raw, array $fallback): ?string {
        if ($raw === '') {
            return json_encode($fallback, JSON_UNESCAPED_SLASHES);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $k => $v) {
            if (!is_string($k) || $k === '' || !is_string($v)) {
                return null;
            }
        }

        return json_encode($decoded, JSON_UNESCAPED_SLASHES);
    };

    $statuses_json = $normalize_catalog($raw_statuses, issue_status_catalog());
    $priorities_json = $normalize_catalog($raw_priorities, issue_priority_catalog());

    if ($statuses_json === null) {
        set_flash('error', 'Default statuses JSON is invalid. Provide an object mapping keys to labels.');
        flash_old(['default_statuses' => $raw_statuses, 'default_priorities' => $raw_priorities]);
        redirect(app_url('admin/settings.php'));
    }

    if ($priorities_json === null) {
        set_flash('error', 'Default priorities JSON is invalid. Provide an object mapping keys to labels.');
        flash_old(['default_statuses' => $raw_statuses, 'default_priorities' => $raw_priorities]);
        redirect(app_url('admin/settings.php'));
    }

    $payload = [
        'system_name' => trim((string) ($_POST['system_name'] ?? '')),
        'organization_name' => trim((string) ($_POST['organization_name'] ?? '')),
        'organization_tagline' => trim((string) ($_POST['organization_tagline'] ?? '')),
        'logo_url' => trim((string) ($_POST['logo_url'] ?? '')),
        'default_statuses' => $statuses_json,
        'default_priorities' => $priorities_json,
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
                    <?php
                    // Prepare decoded arrays for the key/value editor
                    $decoded_statuses = json_decode((string) ($settings['default_statuses'] ?? ''), true);
                    if (!is_array($decoded_statuses)) {
                        $decoded_statuses = issue_status_catalog();
                    }

                    $decoded_priorities = json_decode((string) ($settings['default_priorities'] ?? ''), true);
                    if (!is_array($decoded_priorities)) {
                        $decoded_priorities = issue_priority_catalog();
                    }
                    ?>

                    <div class="col-md-6">
                        <label class="form-label">Default Statuses</label>
                        <div id="statusesEditor" class="mb-2">
                            <?php foreach ($decoded_statuses as $k => $v) : ?>
                                <div class="input-group mb-2 status-row">
                                    <input type="text" class="form-control key-input" placeholder="key" value="<?= e($k) ?>">
                                    <input type="text" class="form-control value-input" placeholder="label" value="<?= e($v) ?>">
                                    <button type="button" class="btn btn-outline-danger btn-remove">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" id="addStatusBtn" class="btn btn-sm btn-outline-primary">Add status</button>
                        </div>
                        <input type="hidden" name="default_statuses" id="default_statuses">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Default Priorities</label>
                        <div id="prioritiesEditor" class="mb-2">
                            <?php foreach ($decoded_priorities as $k => $v) : ?>
                                <div class="input-group mb-2 priority-row">
                                    <input type="text" class="form-control key-input" placeholder="key" value="<?= e($k) ?>">
                                    <input type="text" class="form-control value-input" placeholder="label" value="<?= e($v) ?>">
                                    <button type="button" class="btn btn-outline-danger btn-remove">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" id="addPriorityBtn" class="btn btn-sm btn-outline-primary">Add priority</button>
                        </div>
                        <input type="hidden" name="default_priorities" id="default_priorities">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    function serializeEditor(containerSelector) {
        const container = document.querySelector(containerSelector);
        const rows = container ? Array.from(container.querySelectorAll('.input-group')) : [];
        const out = {};

        rows.forEach(function (row) {
            const keyEl = row.querySelector('.key-input');
            const valEl = row.querySelector('.value-input');
            if (!keyEl || !valEl) return;
            const key = keyEl.value.trim();
            const val = valEl.value.trim();
            if (key !== '' && val !== '') {
                out[key] = val;
            }
        });

        return JSON.stringify(out);
    }

    function wireEditor(containerId, addBtnId, rowClass) {
        const container = document.getElementById(containerId);
        const addBtn = document.getElementById(addBtnId);

        if (!container || !addBtn) return;

        addBtn.addEventListener('click', function () {
            const div = document.createElement('div');
            div.className = 'input-group mb-2 ' + rowClass;
            div.innerHTML = '<input type="text" class="form-control key-input" placeholder="key">' +
                            '<input type="text" class="form-control value-input" placeholder="label">' +
                            '<button type="button" class="btn btn-outline-danger btn-remove">Remove</button>';
            container.appendChild(div);
        });

        container.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('btn-remove')) {
                const row = e.target.closest('.' + rowClass);
                if (row) row.remove();
            }
        });
    }

    wireEditor('statusesEditor', 'addStatusBtn', 'status-row');
    wireEditor('prioritiesEditor', 'addPriorityBtn', 'priority-row');

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const statusesJson = serializeEditor('#statusesEditor');
            const prioritiesJson = serializeEditor('#prioritiesEditor');
            document.getElementById('default_statuses').value = statusesJson;
            document.getElementById('default_priorities').value = prioritiesJson;
        });
    }
});
</script>