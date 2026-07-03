-- Smart Civic App combined database install
-- Built from database/schema.sql, database/seed.sql, and database/migrations/*.sql.
-- Excludes resetadminpass.sql and resetpss.php because they are maintenance-only placeholders.

CREATE DATABASE IF NOT EXISTS smart_civic_app
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smart_civic_app;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    division VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_id (role_id),
    KEY idx_users_division (division),
    KEY idx_users_deleted_at (deleted_at),
    KEY idx_users_deleted_by (deleted_by),
    KEY idx_users_department_id (department_id),
    KEY idx_users_created_by (created_by),
    CONSTRAINT fk_users_roles
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_users_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

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

DROP PROCEDURE IF EXISTS add_fk_users_department_id;

DELIMITER $$
CREATE PROCEDURE add_fk_users_department_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'users'
          AND constraint_name = 'fk_users_department_id'
          AND constraint_type = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE users
            ADD CONSTRAINT fk_users_department_id
            FOREIGN KEY (department_id) REFERENCES departments (department_id)
            ON UPDATE CASCADE
            ON DELETE SET NULL;
    END IF;
END$$
CALL add_fk_users_department_id()$$
DROP PROCEDURE add_fk_users_department_id$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS citizen_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    national_id VARCHAR(50) NULL,
    phone VARCHAR(20) NULL,
    division VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    ward VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_citizen_profiles_division (division),
    CONSTRAINT fk_citizen_profiles_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_profiles (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    employee_number VARCHAR(50) NULL,
    department VARCHAR(120) NULL,
    job_title VARCHAR(120) NULL,
    office_location VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    division VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_profiles_employee_number (employee_number),
    KEY idx_staff_profiles_division (division),
    CONSTRAINT fk_staff_profiles_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sessions_session_id (session_id),
    KEY idx_user_sessions_user_id (user_id),
    KEY idx_user_sessions_last_activity (last_activity),
    CONSTRAINT fk_sessions_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_remember_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_remember_tokens_selector (selector),
    KEY idx_user_remember_tokens_user_id (user_id),
    KEY idx_user_remember_tokens_expires_at (expires_at),
    KEY idx_user_remember_tokens_revoked_at (revoked_at),
    CONSTRAINT fk_user_remember_tokens_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_audit_logs_user_id (user_id),
    KEY idx_auth_audit_logs_action (action),
    CONSTRAINT fk_auth_audit_logs_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issue_categories_name (name),
    KEY idx_issue_categories_sort_order (sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_key VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issue_status_key (status_key),
    UNIQUE KEY uq_issue_status_label (label),
    KEY idx_issue_status_sort_order (sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    routed_by BIGINT UNSIGNED NULL,
    routing_rule_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    address VARCHAR(255) NULL,
    division VARCHAR(100) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'submitted',
    priority VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Issue priority: low, medium, high, critical',
    assigned_to BIGINT UNSIGNED NULL,
    resolution_notes TEXT NULL,
    resolved_at TIMESTAMP NULL,
    reopened_at TIMESTAMP NULL,
    is_emergency TINYINT(1) NOT NULL DEFAULT 0,
    emergency_level VARCHAR(20) NOT NULL DEFAULT 'none',
    citizen_verified_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    closed_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issues_ticket_number (ticket_number),
    KEY idx_issues_user_id (user_id),
    KEY idx_issues_category_id (category_id),
    KEY idx_issues_department_id (department_id),
    KEY idx_issues_routed_by (routed_by),
    KEY idx_issues_routing_rule_id (routing_rule_id),
    KEY idx_issues_status (status),
    KEY idx_issues_priority (priority),
    KEY idx_issues_location (location),
    KEY idx_issues_division (division),
    KEY idx_issues_latitude (latitude),
    KEY idx_issues_longitude (longitude),
    KEY idx_issues_assigned_to (assigned_to),
    KEY idx_issues_is_emergency (is_emergency),
    KEY idx_issues_created_at (created_at),
    KEY idx_issues_updated_at (updated_at),
    KEY idx_issues_resolved_at (resolved_at),
    KEY idx_issues_deleted_at (deleted_at),
    KEY idx_issues_deleted_by (deleted_by),
    CONSTRAINT fk_issues_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_issues_categories
        FOREIGN KEY (category_id) REFERENCES issue_categories (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_issues_department_id
        FOREIGN KEY (department_id) REFERENCES departments (department_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_issues_routed_by
        FOREIGN KEY (routed_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_issues_assigned_to
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_issues_closed_by
        FOREIGN KEY (closed_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    KEY idx_issue_comments_issue_id (issue_id),
    KEY idx_issue_comments_user_id (user_id),
    KEY idx_issue_comments_created_at (created_at),
    KEY idx_issue_comments_deleted_at (deleted_at),
    KEY idx_issue_comments_deleted_by (deleted_by),
    CONSTRAINT fk_issue_comments_issues
        FOREIGN KEY (issue_id) REFERENCES issues (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_issue_comments_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_issue_logs_issue_id (issue_id),
    KEY idx_issue_logs_user_id (user_id),
    KEY idx_issue_logs_action (action),
    KEY idx_issue_logs_created_at (created_at),
    CONSTRAINT fk_issue_logs_issues
        FOREIGN KEY (issue_id) REFERENCES issues (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_issue_logs_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    action_key VARCHAR(80) NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_id (user_id),
    KEY idx_notifications_department_id (department_id),
    KEY idx_notifications_action_key (action_key),
    KEY idx_notifications_is_read (is_read),
    KEY idx_notifications_created_at (created_at),
    CONSTRAINT fk_notifications_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    log_type VARCHAR(40) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    source VARCHAR(120) NULL,
    message TEXT NOT NULL,
    context_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_system_logs_log_type (log_type),
    KEY idx_system_logs_severity (severity),
    KEY idx_system_logs_user_id (user_id),
    KEY idx_system_logs_created_at (created_at),
    CONSTRAINT fk_system_logs_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_reset_tokens_user_id (user_id),
    KEY idx_password_reset_tokens_token_hash (token_hash),
    CONSTRAINT fk_password_reset_tokens_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_permissions_module (module)
) ENGINE=InnoDB;

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
    department_id BIGINT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    affected_table VARCHAR(80) NULL,
    affected_record VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_department_id (department_id),
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

CREATE TABLE IF NOT EXISTS app_cache (
    cache_key VARCHAR(190) NOT NULL PRIMARY KEY,
    cache_value LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_app_cache_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS department_category_mapping (
    -- The original migration used mapping_id, but the current routing helper queries m.id.
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
VALUES
    ('citizen', 'Public user who submits civic service reports'),
    ('staff', 'KCCA staff member who manages assigned issues'),
    ('admin', 'System administrator with full access'),
    ('department_manager', 'Department Manager responsible for departmental issue oversight')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

INSERT INTO issue_categories (name, slug, sort_order)
VALUES
    ('Roads', 'roads', 1),
    ('Garbage', 'garbage', 2),
    ('Drainage', 'drainage', 3),
    ('Water', 'water', 4),
    ('Streetlights', 'streetlights', 5),
    ('Security', 'security', 6),
    ('Other', 'other', 7)
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    sort_order = VALUES(sort_order);

INSERT INTO issue_status (status_key, label, sort_order)
VALUES
    ('submitted', 'Submitted', 1),
    ('under_review', 'Under Review', 2),
    ('assigned', 'Assigned', 3),
    ('in_progress', 'In Progress', 4),
    ('resolved', 'Resolved', 5),
    ('awaiting_citizen_verification', 'Awaiting Citizen Verification', 6),
    ('closed', 'Closed', 7),
    ('reopened', 'Reopened', 8),
    ('rejected', 'Rejected', 9)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    sort_order = VALUES(sort_order);

INSERT INTO settings (`key`, `value`, `group_name`)
VALUES
    ('system_name', 'Smart Civic App', 'general'),
    ('organization_name', 'Kampala Capital City Authority', 'general'),
    ('organization_tagline', 'Citizen Services Tracking and Reporting', 'general'),
    ('logo_url', '', 'general'),
    ('default_statuses', '{"submitted":"Submitted","under_review":"Under Review","assigned":"Assigned","in_progress":"In Progress","resolved":"Resolved","awaiting_citizen_verification":"Awaiting Citizen Verification","closed":"Closed","reopened":"Reopened","rejected":"Rejected"}', 'workflow'),
    ('default_priorities', '{"low":"Low","medium":"Medium","high":"High","critical":"Critical"}', 'workflow'),
    ('upload_limit_mb', '5', 'security'),
    ('session_timeout_minutes', '30', 'security'),
    ('reports_retention_days', '365', 'reports'),
    ('enable_audit_logging', '1', 'security')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `group_name` = VALUES(`group_name`);

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
    ('view_analytics', 'analytics', 'View analytics dashboards'),
    ('manage_departments', 'departments', 'Create and manage departments'),
    ('manage_department_staff', 'departments', 'Create and manage staff in a department'),
    ('view_department_dashboard', 'dashboards', 'View departmental dashboards'),
    ('view_department_reports', 'reports', 'View departmental reports'),
    ('manage_routing_rules', 'routing', 'Manage category to department mappings'),
    ('view_emergency_dashboard', 'emergency', 'View emergency dashboards')
ON DUPLICATE KEY UPDATE
    module = VALUES(module),
    `description` = VALUES(`description`);

INSERT INTO departments (department_name, description, status)
VALUES
    ('Roads and Engineering', 'Road maintenance, repairs, and engineering works', 1),
    ('Sanitation Services', 'Waste collection and cleanliness', 1),
    ('Drainage and Environment', 'Drainage, floods, and environmental works', 1),
    ('Electrical Services', 'Streetlights and electrical faults', 1),
    ('Water Services', 'Water supply and related faults', 1),
    ('Parks and Recreation', 'Public parks and recreation facilities', 1),
    ('Public Safety and Emergency Response', 'Emergency incidents and public safety cases', 1)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 0, 'medium', 1, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'roads'
WHERE d.department_name = 'Roads and Engineering'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 0, 'medium', 2, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'garbage'
WHERE d.department_name = 'Sanitation Services'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 0, 'medium', 3, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'drainage'
WHERE d.department_name = 'Drainage and Environment'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 0, 'medium', 4, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'water'
WHERE d.department_name = 'Water Services'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 0, 'medium', 5, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'streetlights'
WHERE d.department_name = 'Electrical Services'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO department_category_mapping
    (department_id, issue_category_id, is_emergency_category, default_priority, routing_order, status)
SELECT d.department_id, c.id, 1, 'critical', 6, 1
FROM departments d
INNER JOIN issue_categories c ON c.slug = 'security'
WHERE d.department_name = 'Public Safety and Emergency Response'
ON DUPLICATE KEY UPDATE
    is_emergency_category = VALUES(is_emergency_category),
    default_priority = VALUES(default_priority),
    routing_order = VALUES(routing_order),
    status = VALUES(status);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin'
ON DUPLICATE KEY UPDATE
    granted_at = granted_at;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` IN (
    'view_issues',
    'edit_issues',
    'assign_issues',
    'generate_reports',
    'view_analytics',
    'view_audit_trail',
    'view_department_dashboard',
    'view_department_reports',
    'view_emergency_dashboard'
)
WHERE r.name = 'staff'
ON DUPLICATE KEY UPDATE
    granted_at = granted_at;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` IN (
    'view_issues',
    'edit_issues',
    'assign_issues',
    'generate_reports',
    'view_analytics',
    'view_audit_trail',
    'manage_department_staff',
    'view_department_dashboard',
    'view_department_reports',
    'view_emergency_dashboard'
)
WHERE r.name = 'department_manager'
ON DUPLICATE KEY UPDATE
    granted_at = granted_at;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.`key` = 'view_issues'
WHERE r.name = 'citizen'
ON DUPLICATE KEY UPDATE
    granted_at = granted_at;

INSERT INTO users
    (full_name, email, password, role_id, must_change_password)
SELECT
    'System Administrator',
    'admin@smartcivic.local',
    '$2y$10$atKhQtNMRg59crl2CVnDbeq127Z9WEsSRfHOdjoCcM.ww/TCtH/WG',
    r.id,
    0
FROM roles r
WHERE r.name = 'admin'
  AND NOT EXISTS (
      SELECT 1
      FROM users u
      WHERE u.email = 'admin@smartcivic.local'
  );

INSERT INTO users
    (full_name, email, password, role_id, must_change_password)
SELECT
    'KCCA Service Officer',
    'staff@smartcivic.local',
    '$2y$10$vRsIpM7JX5Dd.6.iPrpskuPkK.ysg9tZgKsaQnBe4bMEBKWk7RByS',
    r.id,
    1
FROM roles r
WHERE r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1
      FROM users u
      WHERE u.email = 'staff@smartcivic.local'
  );

INSERT INTO staff_profiles
    (user_id, employee_number, department, job_title, office_location, phone, division)
SELECT u.id, 'ADM-0001', 'Administration', 'System Administrator', 'Head Office', '+256700000001', 'Head Office'
FROM users u
INNER JOIN roles r ON r.id = u.role_id
WHERE u.email = 'admin@smartcivic.local'
  AND r.name = 'admin'
  AND NOT EXISTS (
      SELECT 1
      FROM staff_profiles sp
      WHERE sp.user_id = u.id
  );

INSERT INTO staff_profiles
    (user_id, employee_number, department, job_title, office_location, phone, division)
SELECT u.id, 'STF-0001', 'Operations', 'Service Officer', 'Head Office', '+256700000002', 'Operations Division'
FROM users u
INNER JOIN roles r ON r.id = u.role_id
WHERE u.email = 'staff@smartcivic.local'
  AND r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1
      FROM staff_profiles sp
      WHERE sp.user_id = u.id
  );

INSERT INTO citizen_profiles
    (user_id, phone, division)
SELECT u.id, u.phone, u.division
FROM users u
INNER JOIN roles r ON r.id = u.role_id
LEFT JOIN citizen_profiles cp ON cp.user_id = u.id
WHERE r.name = 'citizen'
  AND cp.user_id IS NULL;

INSERT INTO staff_profiles
    (user_id, employee_number, department, job_title, office_location, phone, division)
SELECT
    u.id,
    NULL,
    CASE WHEN r.name = 'admin' THEN 'Administration' ELSE 'Operations' END,
    CASE WHEN r.name = 'admin' THEN 'System Administrator' ELSE 'Service Officer' END,
    'Head Office',
    u.phone,
    u.division
FROM users u
INNER JOIN roles r ON r.id = u.role_id
LEFT JOIN staff_profiles sp ON sp.user_id = u.id
WHERE r.name IN ('staff', 'admin')
  AND sp.user_id IS NULL;

UPDATE users u
INNER JOIN roles r ON r.id = u.role_id
SET u.phone = NULL,
    u.division = NULL
WHERE r.name IN ('citizen', 'staff', 'admin', 'department_manager');

UPDATE users
SET must_change_password = 1
WHERE role_id = (SELECT id FROM roles WHERE name = 'staff' LIMIT 1)
  AND password IS NOT NULL
  AND must_change_password = 0;

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

INSERT INTO auth_audit_logs
    (user_id, action, details)
SELECT NULL, 'schema_initialized', 'Combined schema created'
WHERE NOT EXISTS (
    SELECT 1
    FROM auth_audit_logs
    WHERE action = 'schema_initialized'
);

INSERT INTO auth_audit_logs
    (user_id, action, details)
SELECT NULL, 'seed_loaded', 'Default admin and staff accounts created'
WHERE NOT EXISTS (
    SELECT 1
    FROM auth_audit_logs
    WHERE action = 'seed_loaded'
);

INSERT INTO backup_logs (backup_name, backup_type, status, notes)
SELECT 'Initial backup log', 'database', 'ready', 'Backup management interface initialized'
WHERE NOT EXISTS (
    SELECT 1
    FROM backup_logs
    WHERE backup_name = 'Initial backup log'
);

INSERT INTO user_activity_logs (user_id, action, entity_type, entity_id, metadata, ip_address)
SELECT u.id, 'platform_initialized', 'system', NULL, JSON_OBJECT('module', 'combined_schema'), NULL
FROM users u
WHERE NOT EXISTS (
    SELECT 1
    FROM user_activity_logs
    WHERE action = 'platform_initialized'
);
