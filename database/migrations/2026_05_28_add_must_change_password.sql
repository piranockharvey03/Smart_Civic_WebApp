ALTER TABLE users
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at;

UPDATE users
SET must_change_password = 1
WHERE role_id = (SELECT id FROM roles WHERE name = 'staff' LIMIT 1)
  AND password IS NOT NULL
  AND must_change_password = 0;
