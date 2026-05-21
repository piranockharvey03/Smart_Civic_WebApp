USE smart_civic_app;

-- Remove duplicated role-specific data from the shared users table.
UPDATE users u
INNER JOIN roles r
ON r.id = u.role_id
SET u
.phone = NULL,
    u.division = NULL
WHERE r.name IN
('citizen', 'staff', 'admin');
