USE smart_civic_app;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(80) NOT NULL PRIMARY KEY,
    `value` LONGTEXT NOT NULL,
    `group_name` VARCHAR(80) NOT NULL DEFAULT 'general',
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_settings_group_name (`group_name`),
    KEY idx_settings_updated_by (`updated_by`),
    CONSTRAINT fk_settings_updated_by
        FOREIGN KEY (`updated_by`) REFERENCES users (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO settings (`key`, `value`, `group_name`)
VALUES
    ('system_name', 'Smart Civic App', 'general'),
    ('organization_name', 'Kampala Capital City Authority', 'general'),
    ('organization_tagline', 'Citizen Services Tracking and Reporting', 'general'),
    ('logo_url', '', 'general'),
    ('default_statuses', '{"submitted":"Submitted","under_review":"Under Review","assigned":"Assigned","in_progress":"In Progress","pending":"Pending","resolved":"Resolved","closed":"Closed","reopened":"Reopened"}', 'workflow'),
    ('default_priorities', '{"low":"Low","medium":"Medium","high":"High","critical":"Critical"}', 'workflow'),
    ('upload_limit_mb', '5', 'security'),
    ('session_timeout_minutes', '30', 'security'),
    ('reports_retention_days', '365', 'reports'),
    ('enable_audit_logging', '1', 'security')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group_name` = VALUES(`group_name`);

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_permissions_module (module)
) ENGINE=InnoDB;

INSERT INTO permissions (`key`, module, `description`)
VALUES
    ('view_issues', 'issues', 'View issue records'),
    ('edit_issues', 'issues', 'Edit issue records'),
    ('delete_issues', 'issues', 'Delete issue records'),
    ('assign_issues', 'issues', 'Assign issues to staff'),
    ('generate_reports', 'reports', 'Generate administrative reports'),
    ('manage_users', 'users', 'Manage user accounts and roles'),
    ('manage_settings', 'settings', 'Manage system settings'),
    ('view_audit_trail', 'audit', 'View audit log entries'),
    ('view_analytics', 'analytics', 'View analytics dashboards')
ON DUPLICATE KEY UPDATE
    module = VALUES(module),
    `description` = VALUES(`description`);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id TINYINT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    granted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_role_permissions_permission_id (permission_id),
    KEY idx_role_permissions_granted_by (granted_by),
    CONSTRAINT fk_role_permissions_roles
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permissions
        FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_granted_by
        FOREIGN KEY (granted_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` IN ('view_issues', 'edit_issues', 'assign_issues', 'generate_reports', 'view_analytics', 'view_audit_trail')
WHERE r.name = 'staff';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` = 'view_issues'
WHERE r.name = 'citizen';

CREATE TABLE IF NOT EXISTS user_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_activity_logs_user_id (user_id),
    KEY idx_user_activity_logs_action (action),
    KEY idx_user_activity_logs_entity (entity_type, entity_id),
    KEY idx_user_activity_logs_created_at (created_at),
    CONSTRAINT fk_user_activity_logs_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    affected_table VARCHAR(80) NULL,
    affected_record VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action (action),
    KEY idx_audit_logs_affected_table (affected_table),
    KEY idx_audit_logs_created_at (created_at),
    CONSTRAINT fk_audit_logs_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backup_name VARCHAR(150) NOT NULL,
    backup_type VARCHAR(40) NOT NULL DEFAULT 'database',
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    file_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_backup_logs_status (status),
    KEY idx_backup_logs_created_at (created_at),
    CONSTRAINT fk_backup_logs_users
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO backup_logs (backup_name, backup_type, status, notes)
SELECT 'Initial backup log', 'database', 'ready', 'Backup management interface initialized'
WHERE NOT EXISTS (
    SELECT 1 FROM backup_logs WHERE backup_name = 'Initial backup log'
);

INSERT INTO user_activity_logs (user_id, action, entity_type, entity_id, metadata, ip_address)
SELECT u.id, 'platform_initialized', 'system', NULL, JSON_OBJECT('module', 'phase_four'), NULL
FROM users u
WHERE NOT EXISTS (
    SELECT 1 FROM user_activity_logs WHERE action = 'platform_initialized'
);

ALTER TABLE issues
    ADD COLUMN IF NOT EXISTS resolution_notes TEXT NULL,
    ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS reopened_at TIMESTAMP NULL;

ALTER TABLE issues
    ADD KEY IF NOT EXISTS idx_issues_created_at (created_at),
    ADD KEY IF NOT EXISTS idx_issues_updated_at (updated_at),
    ADD KEY IF NOT EXISTS idx_issues_resolved_at (resolved_at);

ALTER TABLE issue_comments
    ADD KEY IF NOT EXISTS idx_issue_comments_created_at (created_at);

ALTER TABLE issue_logs
    ADD KEY IF NOT EXISTS idx_issue_logs_created_at (created_at);