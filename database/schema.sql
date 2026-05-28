CREATE DATABASE
IF NOT EXISTS smart_civic_app
    CHARACTER
SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE smart_civic_app;

CREATE TABLE
IF NOT EXISTS roles
(
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR
(50) NOT NULL UNIQUE,
    description VARCHAR
(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ensure soft-delete columns exist (safe to run on an existing database)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;

ALTER TABLE issues
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;

ALTER TABLE issue_comments
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;

-- Add indexes for soft-delete columns if supported by the server
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_deleted_at (deleted_at);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_deleted_by (deleted_by);
ALTER TABLE issues ADD INDEX IF NOT EXISTS idx_issues_deleted_at (deleted_at);
ALTER TABLE issues ADD INDEX IF NOT EXISTS idx_issues_deleted_by (deleted_by);
ALTER TABLE issue_comments ADD INDEX IF NOT EXISTS idx_issue_comments_deleted_at (deleted_at);
ALTER TABLE issue_comments ADD INDEX IF NOT EXISTS idx_issue_comments_deleted_by (deleted_by);

INSERT INTO roles
    (name, description)
VALUES
    ('citizen', 'Public user who submits civic service reports'),
    ('staff', 'KCCA staff member who manages assigned issues'),
    ('admin', 'System administrator with full access')
ON DUPLICATE KEY
UPDATE
    description = VALUES
(description);

CREATE TABLE
IF NOT EXISTS users
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR
(150) NOT NULL,
    email VARCHAR
(150) NOT NULL,
    phone VARCHAR
(20) NULL,
    password VARCHAR
(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    division VARCHAR
(120) NULL,
    is_active TINYINT
(1) NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
     deleted_at TIMESTAMP NULL,
     deleted_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_id
(role_id),
    KEY idx_users_division
(division),
    CONSTRAINT fk_users_roles
        FOREIGN KEY
(role_id) REFERENCES roles
(id)
        ON
UPDATE CASCADE
        ON
DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS citizen_profiles
(
    user_id BIGINT UNSIGNED PRIMARY KEY,
    national_id VARCHAR
(50) NULL,
    phone VARCHAR
(20) NULL,
    division VARCHAR
(120) NULL,
    address VARCHAR
(255) NULL,
    ward VARCHAR
(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    KEY idx_citizen_profiles_division
(division),
    CONSTRAINT fk_citizen_profiles_users
FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS staff_profiles
(
    user_id BIGINT UNSIGNED PRIMARY KEY,
    employee_number VARCHAR
(50) NULL,
    department VARCHAR
(120) NULL,
    job_title VARCHAR
(120) NULL,
    office_location VARCHAR
(120) NULL,
    phone VARCHAR
(20) NULL,
    division VARCHAR
(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_profiles_employee_number
(employee_number),
    KEY idx_staff_profiles_division
(division),
    CONSTRAINT fk_staff_profiles_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS user_sessions
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR
(128) NOT NULL,
    ip_address VARCHAR
(45) NULL,
    user_agent VARCHAR
(255) NULL,
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP
NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_sessions_session_id
(session_id),
    KEY idx_user_sessions_user_id
(user_id),
    KEY idx_user_sessions_last_activity
(last_activity),
    CONSTRAINT fk_sessions_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS auth_audit_logs
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR
(50) NOT NULL,
    ip_address VARCHAR
(45) NULL,
    user_agent VARCHAR
(255) NULL,
    details VARCHAR
(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_audit_logs_user_id
(user_id),
    KEY idx_auth_audit_logs_action
(action),
    CONSTRAINT fk_auth_audit_logs_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO auth_audit_logs
    (user_id, action, details)
SELECT NULL, 'schema_initialized', 'Phase one schema created'
WHERE NOT EXISTS (
    SELECT 1
FROM auth_audit_logs
WHERE action = 'schema_initialized'
);

CREATE TABLE
IF NOT EXISTS issue_categories
(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR
(120) NOT NULL,
    slug VARCHAR
(140) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issue_categories_name
(name),
    KEY idx_issue_categories_sort_order
(sort_order)
) ENGINE=InnoDB;

INSERT INTO issue_categories
    (name, slug, sort_order)
VALUES
    ('Roads', 'roads', 1),
    ('Garbage', 'garbage', 2),
    ('Drainage', 'drainage', 3),
    ('Water', 'water', 4),
    ('Streetlights', 'streetlights', 5),
    ('Security', 'security', 6),
    ('Other', 'other', 7)
ON DUPLICATE KEY
UPDATE
    slug = VALUES
(slug),
    sort_order = VALUES
(sort_order);

CREATE TABLE
IF NOT EXISTS issue_status
(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_key VARCHAR
(40) NOT NULL,
    label VARCHAR
(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issue_status_key
(status_key),
    UNIQUE KEY uq_issue_status_label
(label),
    KEY idx_issue_status_sort_order
(sort_order)
) ENGINE=InnoDB;

INSERT INTO issue_status
    (status_key, label, sort_order)
VALUES
    ('submitted', 'Submitted', 1),
    ('under_review', 'Under Review', 2),
    ('assigned', 'Assigned', 3),
    ('in_progress', 'In Progress', 4),
    ('pending', 'Pending', 5),
    ('resolved', 'Resolved', 6),
    ('closed', 'Closed', 7),
    ('reopened', 'Reopened', 8)
ON DUPLICATE KEY
UPDATE
    label = VALUES
(label),
    sort_order = VALUES
(sort_order);

CREATE TABLE
IF NOT EXISTS issues
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR
(30) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR
(180) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR
(255) NOT NULL,
    location VARCHAR
(255) NOT NULL,
    status VARCHAR
(40) NOT NULL DEFAULT 'submitted',
    priority VARCHAR
(20) NOT NULL DEFAULT 'medium',
    assigned_to BIGINT UNSIGNED NULL,
    resolution_notes TEXT NULL,
    resolved_at TIMESTAMP NULL,
    reopened_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issues_ticket_number (ticket_number),
    KEY idx_issues_user_id
(user_id),
    KEY idx_issues_category_id
(category_id),
    KEY idx_issues_status
(status),
    KEY idx_issues_priority
(priority),
    KEY idx_issues_location
(location),
    KEY idx_issues_assigned_to
    (assigned_to),
    KEY idx_issues_created_at
    (created_at),
    KEY idx_issues_updated_at
    (updated_at),
    KEY idx_issues_resolved_at
    (resolved_at),
    CONSTRAINT fk_issues_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE,
    CONSTRAINT fk_issues_categories
        FOREIGN KEY
(category_id) REFERENCES issue_categories
(id)
        ON
UPDATE CASCADE
        ON
DELETE RESTRICT,
    CONSTRAINT fk_issues_assigned_to
        FOREIGN KEY
(assigned_to) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS issue_comments
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    is_public TINYINT
(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    KEY idx_issue_comments_issue_id
(issue_id),
    KEY idx_issue_comments_user_id
(user_id),
    KEY idx_issue_comments_deleted_at
(deleted_at),
    KEY idx_issue_comments_deleted_by
(deleted_by),
    CONSTRAINT fk_issue_comments_issues
        FOREIGN KEY
(issue_id) REFERENCES issues
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE,
    CONSTRAINT fk_issue_comments_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS issue_logs
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR
(60) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_issue_logs_issue_id
(issue_id),
    KEY idx_issue_logs_user_id
(user_id),
    KEY idx_issue_logs_action
(action),
    CONSTRAINT fk_issue_logs_issues
        FOREIGN KEY
(issue_id) REFERENCES issues
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE,
    CONSTRAINT fk_issue_logs_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE
IF NOT EXISTS notifications
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR
(255) NOT NULL,
    is_read TINYINT
(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_id
(user_id),
    KEY idx_notifications_is_read
(is_read),
    KEY idx_notifications_created_at
(created_at),
    CONSTRAINT fk_notifications_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON
DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE
IF NOT EXISTS system_logs
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    log_type VARCHAR
(40) NOT NULL,
    severity VARCHAR
(20) NOT NULL,
    source VARCHAR
(120) NULL,
    message VARCHAR
(500) NOT NULL,
    context_json JSON NULL,
    ip_address VARCHAR
(45) NULL,
    user_agent VARCHAR
(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_system_logs_log_type
(log_type),
    KEY idx_system_logs_severity
(severity),
    KEY idx_system_logs_user_id
(user_id),
    KEY idx_system_logs_created_at
(created_at),
    CONSTRAINT fk_system_logs_users
        FOREIGN KEY
(user_id) REFERENCES users
(id)
        ON
UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;
