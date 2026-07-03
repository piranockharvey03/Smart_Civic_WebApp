-- Add priority column to issues table
-- This migration adds the priority column that was missing from the schema

ALTER TABLE issues
ADD COLUMN priority VARCHAR(20) DEFAULT 'medium' COMMENT 'Issue priority: low, medium, high, critical'
AFTER status;

-- Add index for priority filtering
ALTER TABLE issues
ADD INDEX idx_priority (priority);
