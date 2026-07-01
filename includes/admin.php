<?php

declare(strict_types=1);

function admin_settings_defaults(): array
{
    return [
        'system_name' => APP_NAME,
        'organization_name' => 'Kampala Capital City Authority',
        'organization_tagline' => 'Citizen Services Tracking and Reporting',
        'logo_url' => '',
        // Use explicit defaults here to avoid recursive calls to issue_status_catalog()/issue_priority_catalog()
        'default_statuses' => json_encode([
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'awaiting_citizen_verification' => 'Awaiting Citizen Verification',
            'closed' => 'Closed',
            'reopened' => 'Reopened',
            'rejected' => 'Rejected',
        ], JSON_UNESCAPED_SLASHES),
        'default_priorities' => json_encode([
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ], JSON_UNESCAPED_SLASHES),
        'upload_limit_mb' => '5',
        'session_timeout_minutes' => '30',
        'reports_retention_days' => '365',
        'enable_audit_logging' => '1',
    ];
}

function admin_settings_table_exists(): bool
{
    return issue_table_exists('settings');
}

function admin_permissions_table_exists(): bool
{
    return issue_table_exists('permissions');
}

function admin_permission_seed(): array
{
    return [
        ['key' => 'view_issues', 'module' => 'issues', 'description' => 'View issue records'],
        ['key' => 'edit_issues', 'module' => 'issues', 'description' => 'Edit issue records'],
        ['key' => 'delete_issues', 'module' => 'issues', 'description' => 'Delete issue records'],
        ['key' => 'assign_issues', 'module' => 'issues', 'description' => 'Assign issues to staff'],
        ['key' => 'generate_reports', 'module' => 'reports', 'description' => 'Generate administrative reports'],
        ['key' => 'manage_users', 'module' => 'users', 'description' => 'Manage user accounts and roles'],
        ['key' => 'manage_settings', 'module' => 'settings', 'description' => 'Manage system settings'],
        ['key' => 'view_audit_trail', 'module' => 'audit', 'description' => 'View audit log entries'],
        ['key' => 'view_analytics', 'module' => 'analytics', 'description' => 'View analytics dashboards'],
        ['key' => 'manage_departments', 'module' => 'departments', 'description' => 'Create and manage departments'],
        ['key' => 'manage_department_staff', 'module' => 'departments', 'description' => 'Create and manage staff in a department'],
        ['key' => 'view_department_dashboard', 'module' => 'dashboards', 'description' => 'View departmental dashboards'],
        ['key' => 'view_department_reports', 'module' => 'reports', 'description' => 'View departmental reports'],
        ['key' => 'manage_routing_rules', 'module' => 'routing', 'description' => 'Manage category to department mappings'],
        ['key' => 'view_emergency_dashboard', 'module' => 'emergency', 'description' => 'View emergency dashboards'],
    ];
}

function admin_seed_permissions(): void
{
    if (!admin_permissions_table_exists()) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO permissions (`key`, module, `description`)
         VALUES (:key, :module, :description)
         ON DUPLICATE KEY UPDATE
            module = VALUES(module),
            `description` = VALUES(`description`)'
    );

    foreach (admin_permission_seed() as $permission) {
        $stmt->execute($permission);
    }
}

function admin_audit_table_name(): string
{
    return issue_table_exists('audit_logs') ? 'audit_logs' : 'auth_audit_logs';
}

function admin_query_parts(array $filters = [], bool $includePriority = true): array
{
    $conditions = [];
    $params = [];

    $conditions[] = 'i.deleted_at IS NULL';

    if (!empty($filters['ticket_number'])) {
        $conditions[] = 'i.ticket_number LIKE :ticket_number';
        $params['ticket_number'] = '%' . trim((string) $filters['ticket_number']) . '%';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'i.status = :status';
        $params['status'] = trim((string) $filters['status']);
    }

    if ($includePriority && issue_issue_column_exists('priority') && !empty($filters['priority'])) {
        $conditions[] = 'i.priority = :priority';
        $params['priority'] = trim((string) $filters['priority']);
    }

    if (!empty($filters['category_id'])) {
        $conditions[] = 'i.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['assigned_to'])) {
        $conditions[] = 'i.assigned_to = :assigned_to';
        $params['assigned_to'] = (int) $filters['assigned_to'];
    }

    if (!empty($filters['location'])) {
        $conditions[] = 'i.location LIKE :location';
        $params['location'] = '%' . trim((string) $filters['location']) . '%';
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'DATE(i.created_at) >= :date_from';
        $params['date_from'] = trim((string) $filters['date_from']);
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'DATE(i.created_at) <= :date_to';
        $params['date_to'] = trim((string) $filters['date_to']);
    }

    return [$conditions, $params];
}

function admin_report_where_sql(array $filters = [], bool $includePriority = true): array
{
    [$conditions, $params] = admin_query_parts($filters, $includePriority);

    return [
        'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
    ];
}

function admin_fetch_settings(): array
{
    $settings = admin_settings_defaults();

    if (!admin_settings_table_exists()) {
        return $settings;
    }

    try {
        $stmt = db()->query('SELECT `key`, `value` FROM settings ORDER BY `group_name` ASC, `key` ASC');
        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }
    } catch (Throwable) {
        // Fall back to defaults when the schema is not yet migrated.
    }

    return $settings;
}

function admin_get_setting(string $key, mixed $default = null): mixed
{
    $settings = admin_fetch_settings();

    return $settings[$key] ?? $default;
}

function admin_upsert_setting(string $key, string $value, ?int $updatedBy = null, string $groupName = 'general'): void
{
    if (!admin_settings_table_exists()) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`, `group_name`, `updated_by`)
         VALUES (:key, :value, :group_name, :updated_by)
         ON DUPLICATE KEY UPDATE
            `value` = VALUES(`value`),
            `group_name` = VALUES(`group_name`),
            `updated_by` = VALUES(`updated_by`)'
    );
    $stmt->execute([
        'key' => $key,
        'value' => $value,
        'group_name' => $groupName,
        'updated_by' => $updatedBy,
    ]);
}

function admin_update_settings(array $payload, ?int $updatedBy = null): void
{
    foreach ($payload as $key => $value) {
        admin_upsert_setting((string) $key, (string) $value, $updatedBy, 'general');
    }
}

function admin_record_audit_log(?int $userId, string $action, ?string $affectedTable = null, ?string $affectedRecord = null, ?string $details = null): void
{
    $table = admin_audit_table_name();

    if (!issue_table_exists($table)) {
        return;
    }

    $columns = ['user_id', 'action', 'ip_address'];
    $placeholders = [':user_id', ':action', ':ip_address'];
    $params = [
        'user_id' => $userId,
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    if ($table === 'audit_logs') {
        $columns[] = 'affected_table';
        $columns[] = 'affected_record';
        $columns[] = 'details';
        $placeholders[] = ':affected_table';
        $placeholders[] = ':affected_record';
        $placeholders[] = ':details';
        $params['affected_table'] = $affectedTable;
        $params['affected_record'] = $affectedRecord;
        $params['details'] = $details;
    } else {
        $columns[] = 'user_agent';
        $columns[] = 'details';
        $placeholders[] = ':user_agent';
        $placeholders[] = ':details';
        $params['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? null), 0, 255) ?: null;
        $params['details'] = trim(implode(' | ', array_filter([$affectedTable, $affectedRecord, $details])));
    }

    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

function admin_record_user_activity(int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    if (!issue_table_exists('user_activity_logs')) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO user_activity_logs (user_id, action, entity_type, entity_id, metadata, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :metadata, :ip_address)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function admin_seed_role_permissions(): void
{
    admin_seed_permissions();

    if (!admin_permissions_table_exists() || !issue_table_exists('role_permissions')) {
        return;
    }

    $adminStmt = db()->prepare(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id
         FROM roles r
            CROSS JOIN permissions p
         WHERE r.name = 'admin'"
    );
    $adminStmt->execute();

    $staffStmt = db()->prepare(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id
         FROM roles r
            INNER JOIN permissions p ON p.`key` IN ('view_issues', 'edit_issues', 'assign_issues', 'generate_reports', 'view_analytics', 'view_audit_trail', 'view_department_dashboard', 'view_department_reports', 'view_emergency_dashboard')
         WHERE r.name = 'staff'"
    );
    $staffStmt->execute();

        $managerStmt = db()->prepare(
           "INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r
            INNER JOIN permissions p ON p.`key` IN ('view_issues', 'edit_issues', 'assign_issues', 'generate_reports', 'view_analytics', 'view_audit_trail', 'manage_department_staff', 'view_department_dashboard', 'view_department_reports', 'view_emergency_dashboard')
            WHERE r.name = 'department_manager'"
        );
        $managerStmt->execute();

    $citizenStmt = db()->prepare(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id
         FROM roles r
         INNER JOIN permissions p ON p.`key` = 'view_issues'
         WHERE r.name = 'citizen'"
    );
    $citizenStmt->execute();
}

function admin_normalize_filters(array $filters): array
{
    return [
        'ticket_number' => trim((string) ($filters['ticket_number'] ?? '')),
        'status' => trim((string) ($filters['status'] ?? '')),
        'priority' => trim((string) ($filters['priority'] ?? '')),
        'category_id' => (int) ($filters['category_id'] ?? 0),
        'assigned_to' => (int) ($filters['assigned_to'] ?? 0),
        'location' => trim((string) ($filters['location'] ?? '')),
        'date_from' => trim((string) ($filters['date_from'] ?? '')),
        'date_to' => trim((string) ($filters['date_to'] ?? '')),
    ];
}

function admin_fetch_report_summary(array $filters = []): array
{
    $filters = admin_normalize_filters($filters);
    ['where' => $whereSql, 'params' => $params] = admin_report_where_sql($filters);
    $hasPriority = issue_issue_column_exists('priority');
    $prioritySelect = $hasPriority ? 'i.priority' : 'NULL AS priority';

    $metricsStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN i.status IN (\'submitted\', \'under_review\', \'assigned\', \'in_progress\', \'pending\', \'reopened\') THEN 1 ELSE 0 END) AS open_issues,
            SUM(CASE WHEN i.status IN (\'resolved\', \'closed\') THEN 1 ELSE 0 END) AS closed_issues,
            AVG(CASE WHEN i.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, i.created_at, i.resolved_at) END) AS avg_resolution_minutes
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql
    );
    $metricsStmt->execute($params);
    $metrics = $metricsStmt->fetch() ?: [];

    $categoryStmt = db()->prepare(
        'SELECT c.id, c.name, COUNT(i.id) AS issue_count
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
            LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql . '
         GROUP BY c.id, c.name, c.sort_order
         ORDER BY issue_count DESC, c.sort_order ASC, c.name ASC
         LIMIT 8'
    );
    $categoryStmt->execute($params);

    $locationStmt = db()->prepare(
        'SELECT i.location, COUNT(*) AS issue_count
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
            LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql . '
         GROUP BY i.location
         ORDER BY issue_count DESC, i.location ASC
         LIMIT 8'
    );
    $locationStmt->execute($params);

    $trendStmt = db()->prepare(
        'SELECT DATE_FORMAT(i.created_at, "%Y-%m") AS month_key, DATE_FORMAT(i.created_at, "%b %Y") AS month_label, COUNT(*) AS issue_count
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
            LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql . '
         GROUP BY month_key, month_label
         ORDER BY month_key ASC'
    );
    $trendStmt->execute($params);

    $priorityDistribution = [];
    if ($hasPriority) {
        $priorityStmt = db()->prepare(
            'SELECT i.priority, COUNT(*) AS issue_count
             FROM issues i
             INNER JOIN issue_categories c ON c.id = i.category_id
               INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
               LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql . '
             GROUP BY i.priority
             ORDER BY issue_count DESC, i.priority ASC'
        );
        $priorityStmt->execute($params);
        $priorityDistribution = $priorityStmt->fetchAll();
    }

    $staffStmt = db()->prepare(
        'SELECT
            assignee.id,
            assignee.full_name,
            assignee.email,
            COUNT(i.id) AS assigned_count,
            SUM(CASE WHEN i.status IN (\'resolved\', \'closed\') THEN 1 ELSE 0 END) AS resolved_count,
            AVG(CASE WHEN i.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, i.created_at, i.resolved_at) END) AS avg_resolution_minutes
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         INNER JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . $whereSql . '
         GROUP BY assignee.id, assignee.full_name, assignee.email
         ORDER BY assigned_count DESC, assignee.full_name ASC
         LIMIT 10'
    );
    $staffStmt->execute($params);

    return [
        'total_issues' => (int) ($metrics['total'] ?? 0),
        'open_issues' => (int) ($metrics['open_issues'] ?? 0),
        'closed_issues' => (int) ($metrics['closed_issues'] ?? 0),
        'avg_resolution_minutes' => $metrics['avg_resolution_minutes'] !== null ? round((float) $metrics['avg_resolution_minutes'], 1) : null,
        'category_breakdown' => $categoryStmt->fetchAll(),
        'location_breakdown' => $locationStmt->fetchAll(),
        'monthly_trend' => $trendStmt->fetchAll(),
        'priority_breakdown' => $priorityDistribution,
        'staff_performance' => $staffStmt->fetchAll(),
        'filters' => $filters,
        'priority_enabled' => $hasPriority,
    ];
}

function admin_fetch_report_rows(array $filters = []): array
{
    return issue_fetch_management_issues(admin_normalize_filters($filters));
}

function admin_fetch_users(array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    $conditions = [];
    $params = [];

    $deletedFilter = trim((string) ($filters['deleted'] ?? ''));

    if (db_column_exists('users', 'deleted_at')) {
        if ($deletedFilter === '1') {
            $conditions[] = 'u.deleted_at IS NOT NULL';
        } elseif ($deletedFilter !== 'all') {
            $conditions[] = 'u.deleted_at IS NULL';
        }
    }

    if (!empty($filters['role'])) {
        $conditions[] = 'r.name = :role';
        $params['role'] = trim((string) $filters['role']);
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $conditions[] = 'u.is_active = :is_active';
        $params['is_active'] = (int) $filters['is_active'];
    }

    if (!empty($filters['search'])) {
        $conditions[] = '(u.full_name LIKE :search OR u.email LIKE :search OR u.division LIKE :search)';
        $params['search'] = '%' . trim((string) $filters['search']) . '%';
    }

    $sql =
        'SELECT u.id, u.full_name, u.email, u.phone, u.division, u.role_id, u.is_active, u.last_login_at, u.created_at,
            r.name AS role_name,
            cp.national_id,
            cp.ward,
            sp.employee_number,
            sp.department,
            sp.job_title,
            sp.office_location
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         LEFT JOIN citizen_profiles cp ON cp.user_id = u.id
         LEFT JOIN staff_profiles sp ON sp.user_id = u.id
         WHERE 1=1';

    if ($conditions) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY u.is_active DESC, r.name ASC, u.full_name ASC';

    $countSql =
        'SELECT COUNT(*) AS total
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         LEFT JOIN citizen_profiles cp ON cp.user_id = u.id
         LEFT JOIN staff_profiles sp ON sp.user_id = u.id
         WHERE 1=1';

    if ($conditions) {
        $countSql .= ' AND ' . implode(' AND ', $conditions);
    }

    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    $sql .= ' LIMIT :limit OFFSET :offset';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function admin_fetch_audit_page(int $page = 1, int $perPage = 20): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    $table = admin_audit_table_name();

    if (!issue_table_exists($table)) {
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 1];
    }

    $countStmt = db()->query('SELECT COUNT(*) AS total FROM ' . $table);
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    $sql = 'SELECT * FROM ' . $table . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function admin_fetch_backup_logs(): array
{
    if (!issue_table_exists('backup_logs')) {
        return [];
    }

    $stmt = db()->query('SELECT * FROM backup_logs ORDER BY created_at DESC, id DESC');

    return $stmt->fetchAll();
}

function admin_export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, array_map(static fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES), $row));
    }

    fclose($output);
}

function admin_json_chart_labels(array $rows, string $labelKey, string $valueKey): array
{
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        $labels[] = (string) ($row[$labelKey] ?? '');
        $values[] = (int) ($row[$valueKey] ?? 0);
    }

    return [$labels, $values];
}

function admin_fetch_global_search(string $term, int $limit = 50): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $hasResolutionNotesColumn = issue_issue_column_exists('resolution_notes');

    $like = '%' . $term . '%';

    $sql = "SELECT * FROM (
        SELECT
            'issue' AS result_type,
            i.id AS result_id,
            i.ticket_number AS primary_label,
            i.title AS secondary_label,
            c.name AS meta_label,
            i.location AS location_label,
            i.status AS status_label,
            " . ($hasResolutionNotesColumn ? 'i.resolution_notes AS notes_label' : 'NULL AS notes_label') . ",
            reporter.full_name AS owner_label,
            assignee.full_name AS assigned_label,
            i.created_at AS created_at
        FROM issues i
        INNER JOIN issue_categories c ON c.id = i.category_id
                    LEFT JOIN users assignee ON assignee.id = i.assigned_to" . sql_table_deleted_cond('users', 'assignee') . "
                    INNER JOIN users reporter ON reporter.id = i.user_id" . sql_table_deleted_cond('users', 'reporter') . "
          WHERE i.deleted_at IS NULL
             AND (i.ticket_number LIKE :issue_ticket
           OR i.title LIKE :issue_title
           OR i.description LIKE :issue_description
           OR i.location LIKE :issue_location
           OR c.name LIKE :issue_category
           OR reporter.full_name LIKE :issue_owner
           OR reporter.email LIKE :issue_owner_email
           OR assignee.full_name LIKE :issue_assignee
              " . ($hasResolutionNotesColumn ? "OR i.resolution_notes LIKE :issue_notes" : '') . ")
        UNION ALL
        SELECT
            'comment' AS result_type,
            ic.issue_id AS result_id,
            i.ticket_number AS primary_label,
            SUBSTRING(ic.comment, 1, 120) AS secondary_label,
            c.name AS meta_label,
            i.location AS location_label,
            i.status AS status_label,
            NULL AS notes_label,
            commenter.full_name AS owner_label,
            assignee.full_name AS assigned_label,
            ic.created_at AS created_at
        FROM issue_comments ic
        INNER JOIN issues i ON i.id = ic.issue_id AND i.deleted_at IS NULL
        INNER JOIN issue_categories c ON c.id = i.category_id
        INNER JOIN users commenter ON commenter.id = ic.user_id" . sql_table_deleted_cond('users', 'commenter') . "
        LEFT JOIN users assignee ON assignee.id = i.assigned_to" . sql_table_deleted_cond('users', 'assignee') . "
        WHERE ic.deleted_at IS NULL AND ic.comment LIKE :comment_search
        UNION ALL
        SELECT
            'user' AS result_type,
            u.id AS result_id,
            u.full_name AS primary_label,
            u.email AS secondary_label,
            r.name AS meta_label,
            u.division AS location_label,
            IF(u.is_active = 1, 'Active', 'Inactive') AS status_label,
            NULL AS notes_label,
            u.full_name AS owner_label,
            NULL AS assigned_label,
            u.created_at AS created_at
          FROM users u
        INNER JOIN roles r ON r.id = u.role_id
                    WHERE 1=1" . sql_table_deleted_cond('users', 'u') . " AND (u.full_name LIKE :user_full_name
           OR u.email LIKE :user_email
              OR u.division LIKE :user_division)
    ) AS search_results
    ORDER BY created_at DESC, result_type ASC, primary_label ASC
    LIMIT :limit";

    $stmt = db()->prepare($sql);
    $stmt->bindValue(':issue_ticket', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_title', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_description', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_location', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_category', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_owner', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_owner_email', $like, PDO::PARAM_STR);
    $stmt->bindValue(':issue_assignee', $like, PDO::PARAM_STR);
    if ($hasResolutionNotesColumn) {
        $stmt->bindValue(':issue_notes', $like, PDO::PARAM_STR);
    }
    $stmt->bindValue(':comment_search', $like, PDO::PARAM_STR);
    $stmt->bindValue(':user_full_name', $like, PDO::PARAM_STR);
    $stmt->bindValue(':user_email', $like, PDO::PARAM_STR);
    $stmt->bindValue(':user_division', $like, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
