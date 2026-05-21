USE smart_civic_app;

INSERT INTO users
  (full_name, email, password, role_id)
SELECT 'System Administrator', 'admin@smartcivic.local', '$2y$10$atKhQtNMRg59crl2CVnDbeq127Z9WEsSRfHOdjoCcM.ww/TCtH/WG', r.id
FROM roles r
WHERE r.name = 'admin'
  AND NOT EXISTS (
      SELECT 1
  FROM users u
  WHERE u.email = 'admin@smartcivic.local'
  );

INSERT INTO users
  (full_name, email, password, role_id)
SELECT 'KCCA Service Officer', 'staff@smartcivic.local', '$2y$10$vRsIpM7JX5Dd.6.iPrpskuPkK.ysg9tZgKsaQnBe4bMEBKWk7RByS', r.id
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

INSERT INTO auth_audit_logs
  (user_id, action, details)
SELECT NULL, 'seed_loaded', 'Default admin and staff accounts created'
WHERE NOT EXISTS (
    SELECT 1
FROM auth_audit_logs
WHERE action = 'seed_loaded'
);
