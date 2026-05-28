UPDATE users
SET password = 'PASTE_PASSWORD_HASH_HERE',
    must_change_password = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE email = 'admin@example.com'
LIMIT 1;