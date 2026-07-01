USE smart_civic_app;

CREATE TABLE IF NOT EXISTS departments (
    department_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    manager_id BIGINT UNSIGNED NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_departments_department_name (department_name),
    KEY idx_departments_manager_id (manager_id),
    KEY idx_departments_status (status),
    CONSTRAINT fk_departments_manager_id
        FOREIGN KEY (manager_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS department_category_mapping (
    mapping_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    issue_category_id INT UNSIGNED NOT NULL,
    is_emergency_category TINYINT(1) NOT NULL DEFAULT 0,
    default_priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    routing_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_department_category_mapping (department_id, issue_category_id),
    KEY idx_department_category_mapping_department_id (department_id),
    KEY idx_department_category_mapping_issue_category_id (issue_category_id),
    CONSTRAINT fk_department_category_mapping_department_id
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_department_category_mapping_issue_category_id
        FOREIGN KEY (issue_category_id) REFERENCES issue_categories (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_department_category_mapping_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_assignments (
    assignment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NOT NULL,
    assignment_note TEXT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unassigned_at TIMESTAMP NULL,
    KEY idx_issue_assignments_issue_id (issue_id),
    KEY idx_issue_assignments_assigned_to (assigned_to),
    CONSTRAINT fk_issue_assignments_issue_id
        FOREIGN KEY (issue_id) REFERENCES issues (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_issue_assignments_assigned_by
        FOREIGN KEY (assigned_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_issue_assignments_assigned_to
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO roles (name, description)
VALUES ('department_manager', 'Department Manager responsible for departmental issue oversight')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO permissions (`key`, module, description)
VALUES
    ('manage_departments', 'departments', 'Create, edit, activate, deactivate departments'),
    ('manage_department_staff', 'departments', 'Create and manage staff in assigned department'),
    ('view_department_dashboard', 'dashboards', 'View department level dashboard'),
    ('view_department_reports', 'reports', 'View department level reports'),
    ('manage_routing_rules', 'routing', 'Manage category to department mappings'),
    ('view_emergency_dashboard', 'emergency', 'View emergency incident dashboard')
ON DUPLICATE KEY UPDATE module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` IN ('view_issues', 'edit_issues', 'assign_issues', 'generate_reports', 'view_analytics', 'view_audit_trail', 'manage_department_staff', 'view_department_dashboard', 'view_department_reports', 'view_emergency_dashboard')
WHERE r.name = 'department_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS created_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    ADD INDEX IF NOT EXISTS idx_users_department_id (department_id),
    ADD INDEX IF NOT EXISTS idx_users_created_by (created_by),
    ADD CONSTRAINT fk_users_department_id
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;

ALTER TABLE issues
    ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS routed_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS routing_rule_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS is_emergency TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS emergency_level VARCHAR(20) NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS citizen_verified_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS closed_by BIGINT UNSIGNED NULL,
    ADD INDEX IF NOT EXISTS idx_issues_department_id (department_id),
    ADD INDEX IF NOT EXISTS idx_issues_is_emergency (is_emergency),
    ADD CONSTRAINT fk_issues_department_id
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_issues_routed_by
        FOREIGN KEY (routed_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    ADD CONSTRAINT fk_issues_closed_by
        FOREIGN KEY (closed_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS action_key VARCHAR(80) NULL,
    ADD INDEX IF NOT EXISTS idx_notifications_department_id (department_id),
    ADD INDEX IF NOT EXISTS idx_notifications_action_key (action_key);

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL,
    ADD INDEX IF NOT EXISTS idx_audit_logs_department_id (department_id);

INSERT INTO departments (department_name, description, status)
VALUES
    ('Roads and Engineering', 'Road maintenance, repairs, and engineering works', 1),
    ('Sanitation Services', 'Waste collection and cleanliness', 1),
    ('Drainage and Environment', 'Drainage, floods, and environmental works', 1),
    ('Electrical Services', 'Streetlights and electrical faults', 1),
    ('Water Services', 'Water supply and related faults', 1),
    ('Parks and Recreation', 'Public parks and recreation facilities', 1),
    ('Public Safety and Emergency Response', 'Emergency incidents and public safety cases', 1)
ON DUPLICATE KEY UPDATE description = VALUES(description), status = VALUES(status);

UPDATE users u
INNER JOIN roles r ON r.id = u.role_id
INNER JOIN staff_profiles sp ON sp.user_id = u.id
INNER JOIN departments d ON d.department_name = sp.department
SET u.department_id = d.department_id
WHERE u.department_id IS NULL
    AND r.name IN ('staff', 'department_manager')
    AND sp.department IS NOT NULL
    AND sp.department <> '';

UPDATE users u
INNER JOIN roles r ON r.id = u.role_id
INNER JOIN staff_profiles sp ON sp.user_id = u.id
SET u.department_id = (
        SELECT d.department_id
        FROM departments d
        WHERE LOWER(d.department_name) = LOWER(sp.department)
        LIMIT 1
)
WHERE u.department_id IS NULL
    AND r.name IN ('staff', 'department_manager')
    AND sp.department IS NOT NULL
    AND sp.department <> ''
    AND EXISTS (
            SELECT 1
            FROM departments d
            WHERE LOWER(d.department_name) = LOWER(sp.department)
    );
