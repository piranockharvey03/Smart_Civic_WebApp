USE smart_civic_app;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS deleted_by BIGINT UNSIGNED NULL;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_deleted_at (deleted_at),
    ADD INDEX IF NOT EXISTS idx_users_deleted_by (deleted_by);