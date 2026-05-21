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
