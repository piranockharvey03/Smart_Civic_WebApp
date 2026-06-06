-- Migration: change system_logs.message from VARCHAR(500) to TEXT
-- Run this against your database to preserve longer log messages.

ALTER TABLE system_logs
  MODIFY COLUMN message TEXT NOT NULL;

-- Note: reversing this migration will truncate existing messages if you change back to VARCHAR(500).
