<?php

declare(strict_types=1);

function department_tables_ready(): bool
{
    return issue_table_exists('departments');
}

function department_mapping_table_ready(): bool
{
    return issue_table_exists('department_category_mapping');
}

function department_role_id(string $roleName): ?int
{
    static $cache = [];

    if (array_key_exists($roleName, $cache)) {
        return $cache[$roleName];
    }

    try {
        $stmt = db()->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        $row = $stmt->fetch();
        $cache[$roleName] = $row ? (int) $row['id'] : null;
    } catch (Throwable) {
        $cache[$roleName] = null;
    }

    return $cache[$roleName];
}

function department_clear_cache(?int $departmentId = null): void
{
    $keys = ['departments:list:all', 'departments:list:active'];

    if ($departmentId !== null && $departmentId > 0) {
        $keys[] = 'departments:detail:' . $departmentId;
    }

    app_cache_forget($keys);
}

function department_current_user_department_id(?array $user = null): ?int
{
    $user = $user ?? current_user();

    if (!$user || !department_tables_ready()) {
        return null;
    }

    if (!empty($user['department_id'])) {
        return (int) $user['department_id'];
    }

    if (db_column_exists('users', 'department_id')) {
        try {
            $stmt = db()->prepare('SELECT department_id FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $user['id']]);
            $row = $stmt->fetch();
            if ($row && $row['department_id'] !== null) {
                return (int) $row['department_id'];
            }
        } catch (Throwable) {
            // Fall back to profile-based lookup below.
        }
    }

    try {
        $stmt = db()->prepare('SELECT department FROM staff_profiles WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $user['id']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['department'])) {
            $deptStmt = db()->prepare('SELECT department_id FROM departments WHERE department_name = :department_name LIMIT 1');
            $deptStmt->execute(['department_name' => (string) $row['department']]);
            $dept = $deptStmt->fetch();
            return $dept && $dept['department_id'] !== null ? (int) $dept['department_id'] : null;
        }
    } catch (Throwable) {
        return null;
    }

    return null;
}

function department_fetch_departments(bool $onlyActive = false): array
{
    if (!department_tables_ready()) {
        return [];
    }

    $cacheKey = $onlyActive ? 'departments:list:active' : 'departments:list:all';

    return app_cache_remember($cacheKey, 300, function () use ($onlyActive): array {
        $sql = 'SELECT d.*, m.full_name AS manager_name, m.email AS manager_email
                FROM departments d
                LEFT JOIN users m ON m.id = d.manager_id' . sql_table_deleted_cond('users', 'm');
        if ($onlyActive) {
            $sql .= ' WHERE d.status = 1';
        }
        $sql .= ' ORDER BY d.department_name ASC';

        try {
            return db()->query($sql)->fetchAll();
        } catch (Throwable) {
            return [];
        }
    });
}

function department_fetch_department_by_id(int $departmentId): ?array
{
    if (!department_tables_ready()) {
        return null;
    }

    return app_cache_remember('departments:detail:' . $departmentId, 300, function () use ($departmentId): ?array {
        try {
            $stmt = db()->prepare(
                'SELECT d.*, m.full_name AS manager_name, m.email AS manager_email
                 FROM departments d
                 LEFT JOIN users m ON m.id = d.manager_id' . sql_table_deleted_cond('users', 'm') . '
                 WHERE d.department_id = :department_id
                 LIMIT 1'
            );
            $stmt->execute(['department_id' => $departmentId]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    });
}

function department_department_seed_map(): array
{
    return [
        'roads' => ['Roads and Engineering', false, 'medium'],
        'garbage' => ['Sanitation Services', false, 'medium'],
        'drainage' => ['Drainage and Environment', false, 'medium'],
        'streetlights' => ['Electrical Services', false, 'medium'],
        'water' => ['Water Services', false, 'medium'],
        'parks' => ['Parks and Recreation', false, 'medium'],
        'security' => ['Public Safety and Emergency Response', true, 'critical'],
        'public-safety' => ['Public Safety and Emergency Response', true, 'critical'],
        'roads-and-engineering' => ['Roads and Engineering', false, 'medium'],
        'sanitation-services' => ['Sanitation Services', false, 'medium'],
        'drainage-and-environment' => ['Drainage and Environment', false, 'medium'],
        'electrical-services' => ['Electrical Services', false, 'medium'],
        'water-services' => ['Water Services', false, 'medium'],
        'parks-and-recreation' => ['Parks and Recreation', false, 'medium'],
        'public-safety-and-emergency-response' => ['Public Safety and Emergency Response', true, 'critical'],
    ];
}

function department_category_belongs_to_department(int $categoryId, int $departmentId): bool
{
    if (!department_mapping_table_ready()) {
        return false;
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM department_category_mapping 
             WHERE department_id = :department_id AND issue_category_id = :category_id AND status = 1
             LIMIT 1'
        );
        $stmt->execute([
            'department_id' => $departmentId,
            'category_id' => $categoryId,
        ]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function department_resolve_category(int $categoryId): ?array
{
    return app_cache_remember('departments:route:' . $categoryId, 120, function () use ($categoryId): ?array {
        $categoryStmt = db()->prepare('SELECT id, name, slug FROM issue_categories WHERE id = :id LIMIT 1');
        $categoryStmt->execute(['id' => $categoryId]);
        $category = $categoryStmt->fetch();

        if (!$category) {
            return null;
        }

        if (department_mapping_table_ready()) {
            $stmt = db()->prepare(
                'SELECT m.id AS mapping_id, m.department_id, m.issue_category_id, m.is_emergency_category, m.default_priority, m.routing_order,
                        d.department_name AS department_name, d.status AS department_status
                 FROM department_category_mapping m
                 INNER JOIN departments d ON d.department_id = m.department_id
                 WHERE m.issue_category_id = :issue_category_id AND m.status = 1 AND d.status = 1
                 ORDER BY m.routing_order ASC, m.id ASC
                 LIMIT 1'
            );
            $stmt->execute(['issue_category_id' => $categoryId]);
            $mapping = $stmt->fetch();

            if ($mapping) {
                return [
                    'department_id' => (int) $mapping['department_id'],
                    'department_name' => (string) $mapping['department_name'],
                    'mapping_id' => (int) $mapping['mapping_id'],
                    'is_emergency' => !empty($mapping['is_emergency_category']),
                    'priority' => (string) ($mapping['default_priority'] ?? 'medium'),
                ];
            }
        }

        $fallback = department_department_seed_map();
        $lookupKeys = [strtolower((string) $category['slug']), strtolower((string) $category['name'])];

        foreach ($lookupKeys as $key) {
            if (!isset($fallback[$key])) {
                continue;
            }

            [$departmentName, $isEmergency, $priority] = $fallback[$key];
            $departmentStmt = db()->prepare('SELECT department_id, department_name FROM departments WHERE department_name = :department_name AND status = 1 LIMIT 1');
            $departmentStmt->execute(['department_name' => $departmentName]);
            $department = $departmentStmt->fetch();

            if ($department) {
                return [
                    'department_id' => (int) $department['department_id'],
                    'department_name' => (string) $department['department_name'],
                    'mapping_id' => null,
                    'is_emergency' => $isEmergency,
                    'priority' => $priority,
                ];
            }
        }

        return null;
    });
}

function department_record_audit(?int $userId, string $action, ?string $table = null, ?string $record = null, ?string $details = null): void
{
    if (!function_exists('admin_record_audit_log')) {
        return;
    }

    admin_record_audit_log($userId, $action, $table, $record, $details);
}

function department_notify_users(array $userIds, string $message): void
{
    foreach (array_unique(array_filter(array_map('intval', $userIds))) as $userId) {
        if ($userId > 0) {
            issue_create_notification($userId, $message);
        }
    }
}

function department_route_issue(int $issueId, int $categoryId, int $actorId, bool $forceEmergency = false): ?array
{
    $route = department_resolve_category($categoryId);
    if (!$route) {
        return null;
    }

    $hasDepartmentColumn = issue_issue_column_exists('department_id');
    $hasRoutedByColumn = issue_issue_column_exists('routed_by');
    $hasRoutingRuleColumn = issue_issue_column_exists('routing_rule_id');
    $hasEmergencyColumn = issue_issue_column_exists('is_emergency');
    $hasEmergencyLevelColumn = issue_issue_column_exists('emergency_level');
    $hasPriorityColumn = issue_issue_column_exists('priority');
    $hasEmergencyPriority = issue_issue_column_exists('priority');

    $status = 'under_review';
    $priority = $route['priority'] ?? 'medium';
    $isEmergency = $forceEmergency || !empty($route['is_emergency']);

    if ($isEmergency) {
        $priority = 'critical';
    }

    $setParts = ['status = :status'];
    $params = [
        'status' => $status,
        'issue_id' => $issueId,
    ];

    if ($hasDepartmentColumn) {
        $setParts[] = 'department_id = :department_id';
        $params['department_id'] = $route['department_id'];
    }

    if ($hasRoutedByColumn) {
        $setParts[] = 'routed_by = :routed_by';
        $params['routed_by'] = $actorId;
    }

    if ($hasRoutingRuleColumn) {
        $setParts[] = 'routing_rule_id = :routing_rule_id';
        $params['routing_rule_id'] = $route['mapping_id'];
    }

    if ($hasEmergencyColumn) {
        $setParts[] = 'is_emergency = :is_emergency';
        $params['is_emergency'] = $isEmergency ? 1 : 0;
    }

    if ($hasEmergencyLevelColumn) {
        $setParts[] = 'emergency_level = :emergency_level';
        $params['emergency_level'] = $isEmergency ? 'critical' : 'none';
    }

    if ($hasPriorityColumn) {
        $setParts[] = 'priority = :priority';
        $params['priority'] = $priority;
    }

    $stmt = db()->prepare('UPDATE issues SET ' . implode(', ', $setParts) . ' WHERE id = :issue_id');
    $stmt->execute($params);

    issue_record_issue_log($issueId, $actorId, 'issue_routed', 'Automatically routed to ' . $route['department_name'] . '.');
    department_record_audit($actorId, 'issue_routed', 'issues', (string) $issueId, 'Routed to ' . $route['department_name']);

    $managerId = null;
    if (department_tables_ready()) {
        $managerStmt = db()->prepare('SELECT manager_id FROM departments WHERE department_id = :department_id LIMIT 1');
        $managerStmt->execute(['department_id' => $route['department_id']]);
        $managerRow = $managerStmt->fetch();
        $managerId = $managerRow && $managerRow['manager_id'] !== null ? (int) $managerRow['manager_id'] : null;
    }

    if ($managerId !== null) {
        department_notify_users([$managerId], 'A new issue was routed to your department: ' . $route['department_name'] . '.');
    }

    return $route + ['status' => $status, 'priority' => $priority, 'is_emergency' => $isEmergency];
}

function department_fetch_dashboard_summary(int $departmentId): array
{
    if (!department_tables_ready()) {
        return [
            'total_issues' => 0,
            'open_issues' => 0,
            'assigned_issues' => 0,
            'resolved_issues' => 0,
            'emergency_incidents' => 0,
            'staff_count' => 0,
            'avg_resolution_minutes' => null,
        ];
    }

    $stmt = db()->prepare(
        'SELECT
            COUNT(i.id) AS total_issues,
            SUM(CASE WHEN i.status IN ("submitted", "under_review", "assigned", "in_progress", "awaiting_citizen_verification") THEN 1 ELSE 0 END) AS open_issues,
            SUM(CASE WHEN i.status = "assigned" THEN 1 ELSE 0 END) AS assigned_issues,
            SUM(CASE WHEN i.status IN ("resolved", "closed") THEN 1 ELSE 0 END) AS resolved_issues,
            SUM(CASE WHEN COALESCE(i.is_emergency, 0) = 1 THEN 1 ELSE 0 END) AS emergency_incidents,
            AVG(CASE WHEN i.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, i.created_at, i.resolved_at) END) AS avg_resolution_minutes,
            COUNT(DISTINCT u.id) AS staff_count
         FROM departments d
         LEFT JOIN issues i ON i.department_id = d.department_id' . sql_table_deleted_cond('issues', 'i') . '
         LEFT JOIN users u ON u.department_id = d.department_id' . sql_table_deleted_cond('users', 'u') . '
         WHERE d.department_id = :department_id'
    );
    $stmt->execute(['department_id' => $departmentId]);

    $row = $stmt->fetch() ?: [];

    return [
        'total_issues' => (int) ($row['total_issues'] ?? 0),
        'open_issues' => (int) ($row['open_issues'] ?? 0),
        'assigned_issues' => (int) ($row['assigned_issues'] ?? 0),
        'resolved_issues' => (int) ($row['resolved_issues'] ?? 0),
        'emergency_incidents' => (int) ($row['emergency_incidents'] ?? 0),
        'staff_count' => (int) ($row['staff_count'] ?? 0),
        'avg_resolution_minutes' => $row['avg_resolution_minutes'] !== null ? round((float) $row['avg_resolution_minutes'], 1) : null,
    ];
}

function department_fetch_staff_workload(int $departmentId): array
{
    if (!department_tables_ready()) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT
            u.id,
            u.full_name,
            u.email,
            COUNT(i.id) AS active_assignments,
            SUM(CASE WHEN i.status IN ("resolved", "closed") THEN 1 ELSE 0 END) AS resolved_issues,
            SUM(CASE WHEN i.status IN ("submitted", "under_review", "assigned", "in_progress") THEN 1 ELSE 0 END) AS open_issues,
            AVG(CASE WHEN i.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, i.created_at, i.resolved_at) END) AS avg_resolution_minutes
         FROM users u
         LEFT JOIN issues i ON i.assigned_to = u.id' . sql_table_deleted_cond('issues', 'i') . '
         WHERE u.department_id = :department_id AND u.is_active = 1' . sql_table_deleted_cond('users', 'u') . '
         GROUP BY u.id, u.full_name, u.email
         ORDER BY active_assignments DESC, u.full_name ASC'
    );
    $stmt->execute(['department_id' => $departmentId]);

    return $stmt->fetchAll();
}

function department_fetch_recent_issues(int $departmentId, int $limit = 5): array
{
    if (!department_tables_ready()) {
        return [];
    }

    $hasPriorityColumn = issue_issue_column_exists('priority');
    $prioritySelect = $hasPriorityColumn ? 'i.priority' : "'medium' AS priority";

    $stmt = db()->prepare(
        "SELECT i.id, i.ticket_number, i.title, i.status, $prioritySelect, i.created_at, i.updated_at, c.name AS category_name,
                reporter.full_name AS reporter_name, assignee.full_name AS assigned_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id" . sql_table_deleted_cond('users', 'reporter') . "
         LEFT JOIN users assignee ON assignee.id = i.assigned_to" . sql_table_deleted_cond('users', 'assignee') . "
         WHERE i.department_id = :department_id" . sql_table_deleted_cond('issues', 'i') . "
         ORDER BY i.updated_at DESC, i.created_at DESC, i.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':department_id', $departmentId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function department_create_staff(array $data, int $managerId, int $departmentId, array &$errors): ?array
{
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $phone = trim((string) ($data['phone'] ?? ''));
    $jobTitle = trim((string) ($data['job_title'] ?? ''));

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (mb_strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters long and include upper-case letters, lower-case letters, and numbers.';
    }

    if ($phone !== '' && mb_strlen($phone) > 20) {
        $errors[] = 'Phone number must be 20 characters or fewer.';
    }

    if ($jobTitle === '') {
        $jobTitle = 'Staff Member';
    }

    // Validate department exists
    if (!department_tables_ready()) {
        $errors[] = 'Department system is not available.';
    } else {
        $deptCheck = db()->prepare('SELECT department_id FROM departments WHERE department_id = :department_id AND status = 1 LIMIT 1');
        $deptCheck->execute(['department_id' => $departmentId]);
        if (!$deptCheck->fetch()) {
            $errors[] = 'Your department is not valid or active. Please contact an administrator.';
        }
    }

    $exists = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);
    if ($exists->fetch()) {
        $errors[] = 'A user with this email already exists.';
    }

    $roleId = department_role_id('staff');
    if (!$roleId) {
        $errors[] = 'Staff role is not available.';
    }

    if ($errors) {
        return null;
    }

    db()->beginTransaction();

    try {
        $stmt = db()->prepare(
            'INSERT INTO users (full_name, email, password, role_id, department_id, created_by, is_active, must_change_password)
             VALUES (:full_name, :email, :password, :role_id, :department_id, :created_by, 1, 1)'
        );
        $stmt->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $roleId,
            'department_id' => $departmentId,
            'created_by' => $managerId,
        ]);

        $userId = (int) db()->lastInsertId();

        if (issue_table_exists('staff_profiles')) {
            $profileStmt = db()->prepare(
                'INSERT INTO staff_profiles (user_id, employee_number, department, job_title, office_location, phone, division)
                 VALUES (:user_id, :employee_number, :department, :job_title, :office_location, :phone, :division)'
            );
            $profileStmt->execute([
                'user_id' => $userId,
                'employee_number' => 'STAFF-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT),
                'department' => 'Department Staff',
                'job_title' => $jobTitle,
                'office_location' => null,
                'phone' => $phone !== '' ? $phone : null,
                'division' => null,
            ]);
        }

        department_record_audit($managerId, 'staff_created', 'users', (string) $userId, 'Staff created in department ' . $departmentId);

        db()->commit();

        return ['id' => $userId, 'email' => $email, 'full_name' => $fullName];
    } catch (Throwable $throwable) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $throwable;
    }
}

function department_update_staff(int $staffId, array $data, int $managerId, int $departmentId, array &$errors): ?array
{
    $staffStmt = db()->prepare('SELECT id, department_id, role_id FROM users WHERE id = :id LIMIT 1');
    $staffStmt->execute(['id' => $staffId]);
    $staff = $staffStmt->fetch();

    if (!$staff || (int) $staff['department_id'] !== $departmentId) {
        $errors[] = 'The selected staff member is not part of your department.';
        return null;
    }

    $fullName = trim((string) ($data['full_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $jobTitle = trim((string) ($data['job_title'] ?? ''));
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    $emailExists = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $emailExists->execute(['email' => $email, 'id' => $staffId]);
    if ($emailExists->fetch()) {
        $errors[] = 'Another user already uses this email.';
    }

    if ($errors) {
        return null;
    }

    db()->beginTransaction();

    try {
        $stmt = db()->prepare(
            'UPDATE users
             SET full_name = :full_name,
                 email = :email,
                 is_active = :is_active,
                 department_id = :department_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'full_name' => $fullName,
            'email' => $email,
            'is_active' => $isActive,
            'department_id' => $departmentId,
            'id' => $staffId,
        ]);

        if (issue_table_exists('staff_profiles')) {
            $profileStmt = db()->prepare(
                'INSERT INTO staff_profiles (user_id, employee_number, department, job_title, office_location, phone, division)
                 VALUES (:user_id, :employee_number, :department, :job_title, :office_location, :phone, :division)
                 ON DUPLICATE KEY UPDATE
                    job_title = VALUES(job_title),
                    phone = VALUES(phone)'
            );
            $profileStmt->execute([
                'user_id' => $staffId,
                'employee_number' => 'STAFF-' . str_pad((string) $staffId, 5, '0', STR_PAD_LEFT),
                'department' => 'Department Staff',
                'job_title' => $jobTitle !== '' ? $jobTitle : 'Staff Member',
                'office_location' => null,
                'phone' => $phone !== '' ? $phone : null,
                'division' => null,
            ]);
        }

        department_record_audit($managerId, 'staff_updated', 'users', (string) $staffId, 'Staff details updated');

        db()->commit();

        return ['id' => $staffId, 'full_name' => $fullName, 'email' => $email];
    } catch (Throwable $throwable) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $throwable;
    }
}

function department_toggle_staff_status(int $staffId, int $managerId, int $departmentId, bool $isActive): void
{
    $stmt = db()->prepare('UPDATE users SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND department_id = :department_id');
    $stmt->execute([
        'is_active' => $isActive ? 1 : 0,
        'id' => $staffId,
        'department_id' => $departmentId,
    ]);

    department_record_audit($managerId, $isActive ? 'staff_activated' : 'staff_deactivated', 'users', (string) $staffId, 'Staff status changed');
}
