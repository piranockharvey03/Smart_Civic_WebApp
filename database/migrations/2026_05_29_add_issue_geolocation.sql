USE smart_civic_app;

ALTER TABLE issues
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL AFTER location,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL AFTER latitude,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER longitude,
    ADD COLUMN IF NOT EXISTS division VARCHAR(100) NULL AFTER address;

ALTER TABLE issues
    ADD INDEX IF NOT EXISTS idx_issues_division (division),
    ADD INDEX IF NOT EXISTS idx_issues_latitude (latitude),
    ADD INDEX IF NOT EXISTS idx_issues_longitude (longitude);