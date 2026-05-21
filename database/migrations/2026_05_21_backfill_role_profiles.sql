USE smart_civic_app;

-- Backfill citizen profile rows for existing citizen users.
INSERT INTO citizen_profiles
    (user_id, phone, division)
SELECT u.id, u.phone, u.division
FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN citizen_profiles cp ON cp.user_id = u.id
WHERE r.name = 'citizen'
    AND cp.user_id IS NULL;

-- Backfill staff profile rows for existing staff and admin users.
INSERT INTO staff_profiles
    (user_id, employee_number, department, job_title, office_location, phone, division)
SELECT u.id, NULL,
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
