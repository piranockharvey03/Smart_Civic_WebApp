<?php

declare(strict_types=1);

function issue_status_catalog(): array
{
    $defaults = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'awaiting_citizen_verification' => 'Awaiting Citizen Verification',
        'closed' => 'Closed',
        'reopened' => 'Reopened',
        'rejected' => 'Rejected',
    ];

    try {
        $json = admin_get_setting('default_statuses', json_encode($defaults, JSON_UNESCAPED_SLASHES));
        $decoded = json_decode((string) $json, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        // Ensure keys and values are strings
        foreach ($decoded as $k => $v) {
            if (!is_string($k) || $k === '' || !is_string($v)) {
                return $defaults;
            }
        }

        return $decoded;
    } catch (Throwable) {
        return $defaults;
    }
}

function issue_status_label(string $status): string
{
    $catalog = issue_status_catalog();

    return $catalog[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function issue_status_badge_class(string $status): string
{
    return match ($status) {
        'submitted' => 'secondary',
        'under_review' => 'warning',
        'assigned' => 'info',
        'in_progress' => 'primary',
        'resolved' => 'success',
        'awaiting_citizen_verification' => 'warning',
        'closed' => 'dark',
        'reopened' => 'warning',
        'rejected' => 'danger',
        default => 'secondary',
    };
}

function issue_priority_catalog(): array
{
    $defaults = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    try {
        $json = admin_get_setting('default_priorities', json_encode($defaults, JSON_UNESCAPED_SLASHES));
        $decoded = json_decode((string) $json, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach ($decoded as $k => $v) {
            if (!is_string($k) || $k === '' || !is_string($v)) {
                return $defaults;
            }
        }

        return $decoded;
    } catch (Throwable) {
        return $defaults;
    }
}

function issue_priority_label(string $priority): string
{
    $catalog = issue_priority_catalog();

    return $catalog[$priority] ?? ucwords(str_replace('_', ' ', $priority));
}

function issue_priority_badge_class(string $priority): string
{
    return match ($priority) {
        'low' => 'success',
        'medium' => 'secondary',
        'high' => 'warning',
        'critical' => 'danger',
        default => 'secondary',
    };
}

function issue_normalize_coordinate(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    return (float) $value;
}

function issue_is_valid_latitude(?float $latitude): bool
{
    return $latitude !== null && $latitude >= -90 && $latitude <= 90;
}

function issue_is_valid_longitude(?float $longitude): bool
{
    return $longitude !== null && $longitude >= -180 && $longitude <= 180;
}

function issue_map_status_tier(string $status): string
{
    return match ($status) {
        'resolved', 'closed' => 'success',
        'in_progress', 'assigned', 'under_review', 'pending' => 'warning',
        default => 'danger',
    };
}

function issue_issue_column_exists(string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            'table_name' => 'issues',
            'column_name' => $column,
        ]);
        $cache[$column] = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    } catch (Throwable) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function issue_table_exists(string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute(['table_name' => $table]);
        $cache[$table] = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    } catch (Throwable) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function issue_log_action_label(string $action): string
{
    return match ($action) {
        'issue_submitted' => 'Issue submitted',
        'status_updated' => 'Status updated',
        'priority_updated' => 'Priority updated',
        'issue_assigned' => 'Issue assigned',
        'issue_reassigned' => 'Issue reassigned',
        'comment_added' => 'Comment added',
        'issue_resolved' => 'Issue resolved',
        'issue_reopened' => 'Issue reopened',
        default => ucwords(str_replace('_', ' ', $action)),
    };
}

function issue_workflow_statuses(): array
{
    return ['submitted', 'under_review', 'assigned', 'in_progress', 'resolved', 'awaiting_citizen_verification', 'closed', 'reopened', 'rejected'];
}

function issue_open_statuses(): array
{
    return ['submitted', 'under_review', 'assigned', 'in_progress', 'awaiting_citizen_verification', 'reopened'];
}

function issue_resolved_statuses(): array
{
    return ['resolved', 'awaiting_citizen_verification', 'closed'];
}

function issue_fetch_trackable_issue(string $ticketNumber, string $email): ?array
{
    $ticketNumber = trim($ticketNumber);
    $email = trim(mb_strtolower($email));

    if ($ticketNumber === '' || $email === '') {
        return null;
    }

    $issue = issue_fetch_issue_by_ticket($ticketNumber);
    if (!$issue) {
        return null;
    }

    $reporterEmail = trim(mb_strtolower((string) ($issue['reporter_email'] ?? '')));
    if ($reporterEmail === '' || $reporterEmail !== $email) {
        return null;
    }

    return $issue;
}

function issue_category_seed(): array
{
    return [
        ['name' => 'Roads', 'slug' => 'roads', 'sort_order' => 1],
        ['name' => 'Garbage', 'slug' => 'garbage', 'sort_order' => 2],
        ['name' => 'Drainage', 'slug' => 'drainage', 'sort_order' => 3],
        ['name' => 'Water', 'slug' => 'water', 'sort_order' => 4],
        ['name' => 'Streetlights', 'slug' => 'streetlights', 'sort_order' => 5],
        ['name' => 'Security', 'slug' => 'security', 'sort_order' => 6],
        ['name' => 'Other', 'slug' => 'other', 'sort_order' => 7],
    ];
}

function issue_category_options(): array
{
    try {
        $stmt = db()->query('SELECT id, name, slug, sort_order FROM issue_categories ORDER BY sort_order ASC, name ASC');
        $categories = $stmt->fetchAll();

        if ($categories) {
            return $categories;
        }
    } catch (Throwable) {
        // Fall back to seed data when the schema has not been migrated yet.
    }

    return issue_category_seed();
}

function issue_division_options(): array
{
    return [
        'Central Division',
        'Kawempe Division',
        'Makindye Division',
        'Nakawa Division',
        'Rubaga Division',
        'KCCA Wide / Other',
    ];
}

function issue_upload_directory(): string
{
    return BASE_PATH . '/uploads/issues';
}

function issue_upload_url(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }

    return app_url('uploads/issues/' . rawurlencode($filename));
}

function issue_status_options(): array
{
    try {
        $stmt = db()->query('SELECT status_key, label FROM issue_status ORDER BY sort_order ASC, label ASC');
        $statuses = $stmt->fetchAll();

        if ($statuses) {
            return $statuses;
        }
    } catch (Throwable) {
        // Fall back to the built-in catalog when the schema is not present yet.
    }

    $options = [];
    foreach (issue_status_catalog() as $key => $label) {
        $options[] = ['status_key' => $key, 'label' => $label];
    }

    return $options;
}

function issue_build_ticket_number(int $issueId, ?DateTimeInterface $createdAt = null): string
{
    $year = $createdAt?->format('Y') ?? date('Y');

    return sprintf('KCCA-%s-%04d', $year, $issueId);
}

function issue_build_location(array $data): string
{
    $division = trim((string) ($data['division'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($division !== '' && $location !== '') {
        return $division . ' - ' . $location;
    }

    return $division !== '' ? $division : $location;
}

function issue_build_display_address(array $data): string
{
    $address = trim((string) ($data['address'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($address !== '') {
        return $address;
    }

    return $location;
}

function issue_validate_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload a photo or image evidence for the issue.';

        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'The uploaded file could not be processed. Please try again.';

        return null;
    }

    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        $errors[] = 'Invalid upload detected.';

        return null;
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        $errors[] = 'Image size must not exceed 5 MB.';

        return null;
    }

    $imageInfo = @getimagesize((string) $file['tmp_name']);
    if ($imageInfo === false || empty($imageInfo['mime'])) {
        $errors[] = 'Only valid image files are allowed.';

        return null;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $mime = (string) $imageInfo['mime'];
    if (!isset($allowedMimes[$mime])) {
        $errors[] = 'Allowed image types are JPG, PNG, GIF, and WEBP.';

        return null;
    }

    $directory = issue_upload_directory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        $errors[] = 'The issue upload directory could not be created.';

        return null;
    }

    $filename = sprintf(
        'issue_%s_%s.%s',
        date('YmdHis'),
        bin2hex(random_bytes(6)),
        $allowedMimes[$mime]
    );

    $targetPath = $directory . '/' . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        $errors[] = 'The uploaded image could not be saved.';

        return null;
    }

    return $filename;
}

function issue_create_report(int $userId, array $data, array $file, array &$errors): ?array
{
    $categoryId = (int) ($data['category_id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $division = trim((string) ($data['division'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $latitude = issue_normalize_coordinate($data['latitude'] ?? null);
    $longitude = issue_normalize_coordinate($data['longitude'] ?? null);

    if ($categoryId < 1) {
        $errors[] = 'Please select an issue category.';
    }

    if ($title === '' || mb_strlen($title) < 5) {
        $errors[] = 'Issue title must be at least 5 characters long.';
    }

    if ($description === '' || mb_strlen($description) < 20) {
        $errors[] = 'Issue description must be at least 20 characters long.';
    }

    if ($division === '') {
        $errors[] = 'Please select a location/division.';
    }

    if ($location === '' || mb_strlen($location) < 3) {
        $errors[] = 'Please provide a brief location description.';
    }

    if (($latitude !== null || $longitude !== null) && (!issue_is_valid_latitude($latitude) || !issue_is_valid_longitude($longitude))) {
        $errors[] = 'Please provide valid latitude and longitude coordinates.';
    }

    if ($address !== '' && mb_strlen($address) > 255) {
        $errors[] = 'The address must be 255 characters or fewer.';
    }

    $categoryStmt = db()->prepare('SELECT id FROM issue_categories WHERE id = :id LIMIT 1');
    $categoryStmt->execute(['id' => $categoryId]);
    if (!$categoryStmt->fetch()) {
        $errors[] = 'The selected issue category is not available.';
    }

    $imageName = issue_validate_upload($file, $errors);

    if ($errors) {
        return null;
    }

    $locationText = issue_build_location([
        'division' => $division,
        'location' => $location,
    ]);

    $now = new DateTimeImmutable('now');
    $temporaryTicket = 'TMP-' . bin2hex(random_bytes(8));
    $initialPriority = 'medium';
    $hasPriorityColumn = issue_issue_column_exists('priority');

    db()->beginTransaction();

    try {
        $columns = ['ticket_number', 'user_id', 'category_id', 'title', 'description', 'image', 'location', 'latitude', 'longitude', 'address', 'division', 'status', 'assigned_to'];
        $placeholders = [':ticket_number', ':user_id', ':category_id', ':title', ':description', ':image', ':location', ':latitude', ':longitude', ':address', ':division', ':status', 'NULL'];
        $params = [
            'ticket_number' => $temporaryTicket,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description,
            'image' => $imageName,
            'location' => $locationText,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address !== '' ? $address : null,
            'division' => $division !== '' ? $division : null,
            'status' => 'submitted',
        ];

        if ($hasPriorityColumn) {
            $columns[] = 'priority';
            $placeholders[] = ':priority';
            $params['priority'] = $initialPriority;
        }

        $stmt = db()->prepare(
            'INSERT INTO issues (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        $issueId = (int) db()->lastInsertId();
        $ticketNumber = issue_build_ticket_number($issueId, $now);

        $update = db()->prepare('UPDATE issues SET ticket_number = :ticket_number WHERE id = :id');
        $update->execute([
            'ticket_number' => $ticketNumber,
            'id' => $issueId,
        ]);

        if (function_exists('department_route_issue')) {
            try {
                department_route_issue($issueId, $categoryId, $userId, false);
            } catch (Throwable) {
                // Keep the issue submission intact even if department routing cannot be completed.
            }
        }

        issue_record_issue_log($issueId, $userId, 'issue_submitted', 'Issue submitted by citizen.');

        issue_create_notification(
            $userId,
            'Your issue ' . $ticketNumber . ' was submitted successfully. KCCA will review it shortly.'
        );

        db()->commit();

        return [
            'id' => $issueId,
            'ticket_number' => $ticketNumber,
        ];
    } catch (Throwable $throwable) {
        db()->rollBack();

        if ($imageName !== null) {
            $uploadedPath = issue_upload_directory() . '/' . $imageName;
            if (is_file($uploadedPath)) {
                @unlink($uploadedPath);
            }
        }

        throw $throwable;
    }
}

function issue_fetch_status_counts(?int $userId = null, ?int $departmentId = null): array
{
    $baseWhere = '';
    $params = [];

    if ($userId !== null) {
        $baseWhere = ' WHERE user_id = :user_id';
        $params['user_id'] = $userId;
    }

    if ($departmentId !== null && issue_issue_column_exists('department_id')) {
        $baseWhere = $baseWhere ? $baseWhere . ' AND department_id = :department_id' : ' WHERE department_id = :department_id';
        $params['department_id'] = $departmentId;
    }

    $counts = [
        'total' => 0,
        'open' => 0,
        'resolved' => 0,
        'pending' => 0,
    ];

    $totalStmt = db()->prepare('SELECT COUNT(*) AS total FROM issues' . $baseWhere);
    $totalStmt->execute($params);
    $counts['total'] = (int) ($totalStmt->fetch()['total'] ?? 0);

    $openStmt = db()->prepare(
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('submitted', 'under_review', 'assigned', 'in_progress', 'pending', 'reopened')"
    );
    $openStmt->execute($params);
    $counts['open'] = (int) ($openStmt->fetch()['total'] ?? 0);

    $resolvedStmt = db()->prepare(
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('resolved', 'closed')"
    );
    $resolvedStmt->execute($params);
    $counts['resolved'] = (int) ($resolvedStmt->fetch()['total'] ?? 0);

    $pendingStmt = db()->prepare(
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('submitted', 'under_review', 'pending')"
    );
    $pendingStmt->execute($params);
    $counts['pending'] = (int) ($pendingStmt->fetch()['total'] ?? 0);

    return $counts;
}

function issue_fetch_citizen_issues(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug, u.full_name AS assigned_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         LEFT JOIN users u ON u.id = i.assigned_to
         WHERE i.user_id = :user_id
         ORDER BY i.created_at DESC, i.id DESC'
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function issue_fetch_citizen_issue_page(int $userId, int $page = 1, int $perPage = 10): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $countStmt = db()->prepare('SELECT COUNT(*) AS total FROM issues WHERE user_id = :user_id');
    $countStmt->execute(['user_id' => $userId]);
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    $stmt = db()->prepare(
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug, u.full_name AS assigned_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         LEFT JOIN users u ON u.id = i.assigned_to
         WHERE i.user_id = :user_id
         ORDER BY i.created_at DESC, i.id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
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

function issue_fetch_management_issues(array $filters = [], ?array $viewer = null): array
{
    $conditions = [];
    $params = [];
    $hasPriorityColumn = issue_issue_column_exists('priority');
    $hasDepartmentColumn = issue_issue_column_exists('department_id');
    $viewer = $viewer ?? current_user();
    $viewerRole = $viewer['role'] ?? null;
    $viewerDepartmentId = function_exists('department_current_user_department_id') ? department_current_user_department_id($viewer) : null;
    $deletedFilter = trim((string) ($filters['deleted'] ?? ''));
    $deletedClause = 'i.deleted_at IS NULL';

    if ($deletedFilter === '1') {
        $deletedClause = 'i.deleted_at IS NOT NULL';
    } elseif ($deletedFilter !== 'all') {
        $deletedClause = 'i.deleted_at IS NULL';
    }

    if (!empty($filters['ticket_number'])) {
        $conditions[] = 'i.ticket_number LIKE :ticket_number';
        $params['ticket_number'] = '%' . trim((string) $filters['ticket_number']) . '%';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'i.status = :status';
        $params['status'] = trim((string) $filters['status']);
    }

    if ($hasPriorityColumn && !empty($filters['priority'])) {
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

    if (!empty($filters['date_from'])) {
        $conditions[] = 'DATE(i.created_at) >= :date_from';
        $params['date_from'] = trim((string) $filters['date_from']);
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'DATE(i.created_at) <= :date_to';
        $params['date_to'] = trim((string) $filters['date_to']);
    }

    if (!empty($filters['location'])) {
        $conditions[] = 'i.location LIKE :location';
        $params['location'] = '%' . trim((string) $filters['location']) . '%';
    }

    if ($viewerRole === 'staff' && !empty($viewer['id'])) {
        $conditions[] = '(i.assigned_to = :viewer_assigned_to OR i.user_id = :viewer_reporter_id)';
        $params['viewer_assigned_to'] = (int) $viewer['id'];
        $params['viewer_reporter_id'] = (int) $viewer['id'];
    } elseif ($viewerRole === 'department_manager' && $hasDepartmentColumn && $viewerDepartmentId !== null) {
        $conditions[] = 'i.department_id = :viewer_department_id';
        $params['viewer_department_id'] = $viewerDepartmentId;
    }

    $sql =
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug,
            ' . ($hasDepartmentColumn ? 'dept.department_name AS department_name,' : '') . '
            reporter.full_name AS reporter_name,
            reporter.email AS reporter_email,
            assignee.full_name AS assigned_name,
            assignee.email AS assigned_email
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
         ' . ($hasDepartmentColumn ? 'LEFT JOIN departments dept ON dept.department_id = i.department_id' : '') . '
         WHERE ' . $deletedClause;

    if ($conditions) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY i.updated_at DESC, i.created_at DESC, i.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function issue_fetch_management_issue_page(array $filters = [], int $page = 1, int $perPage = 10, ?array $viewer = null): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));

    $conditions = [];
    $params = [];
    $hasPriorityColumn = issue_issue_column_exists('priority');
    $hasDepartmentColumn = issue_issue_column_exists('department_id');
    $viewer = $viewer ?? current_user();
    $viewerRole = $viewer['role'] ?? null;
    $viewerDepartmentId = function_exists('department_current_user_department_id') ? department_current_user_department_id($viewer) : null;
    $deletedFilter = trim((string) ($filters['deleted'] ?? ''));
    $deletedClause = 'i.deleted_at IS NULL';

    if ($deletedFilter === '1') {
        $deletedClause = 'i.deleted_at IS NOT NULL';
    } elseif ($deletedFilter !== 'all') {
        $deletedClause = 'i.deleted_at IS NULL';
    }

    if (!empty($filters['ticket_number'])) {
        $conditions[] = 'i.ticket_number LIKE :ticket_number';
        $params['ticket_number'] = '%' . trim((string) $filters['ticket_number']) . '%';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'i.status = :status';
        $params['status'] = trim((string) $filters['status']);
    }

    if ($hasPriorityColumn && !empty($filters['priority'])) {
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

    if (!empty($filters['date_from'])) {
        $conditions[] = 'DATE(i.created_at) >= :date_from';
        $params['date_from'] = trim((string) $filters['date_from']);
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'DATE(i.created_at) <= :date_to';
        $params['date_to'] = trim((string) $filters['date_to']);
    }

    if (!empty($filters['location'])) {
        $conditions[] = 'i.location LIKE :location';
        $params['location'] = '%' . trim((string) $filters['location']) . '%';
    }

    if ($viewerRole === 'staff' && !empty($viewer['id'])) {
        $conditions[] = '(i.assigned_to = :viewer_assigned_to OR i.user_id = :viewer_reporter_id)';
        $params['viewer_assigned_to'] = (int) $viewer['id'];
        $params['viewer_reporter_id'] = (int) $viewer['id'];
    } elseif ($viewerRole === 'department_manager' && $hasDepartmentColumn && $viewerDepartmentId !== null) {
        $conditions[] = 'i.department_id = :viewer_department_id';
        $params['viewer_department_id'] = $viewerDepartmentId;
    }

    $whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $countStmt = db()->prepare(
        'SELECT COUNT(*) AS total
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
            LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
            ' . ($hasDepartmentColumn ? 'LEFT JOIN departments dept ON dept.department_id = i.department_id' : '') . '
            WHERE ' . $deletedClause . ($whereSql ? ' AND ' . ltrim($whereSql, ' WHERE ') : '')
    );
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    $offset = ($page - 1) * $perPage;

    $sql =
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug,
            reporter.full_name AS reporter_name,
            reporter.email AS reporter_email,
            assignee.full_name AS assigned_name,
            assignee.email AS assigned_email
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
         ' . ($hasDepartmentColumn ? 'LEFT JOIN departments dept ON dept.department_id = i.department_id' : '') . '
         WHERE ' . $deletedClause .
        ($whereSql ? ' AND ' . ltrim($whereSql, ' WHERE ') : '') .
        ' ORDER BY i.updated_at DESC, i.created_at DESC, i.id DESC
         LIMIT :limit OFFSET :offset';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function issue_fetch_map_issues(array $filters = [], ?int $viewerId = null, ?string $viewerRole = null, int $limit = 1000): array
{
    $limit = max(1, min(2000, $limit));
    $conditions = ['i.deleted_at IS NULL', 'i.latitude IS NOT NULL', 'i.longitude IS NOT NULL'];
    $params = [];
    $hasPriorityColumn = issue_issue_column_exists('priority');
    $hasDepartmentColumn = issue_issue_column_exists('department_id');

    if ($viewerRole === 'staff' && $viewerId !== null) {
        $conditions[] = '(i.assigned_to = :viewer_assigned_id OR i.user_id = :viewer_reporter_id)';
        $params['viewer_assigned_id'] = $viewerId;
        $params['viewer_reporter_id'] = $viewerId;
    } elseif ($viewerRole === 'department_manager' && $viewerId !== null && $hasDepartmentColumn && function_exists('department_current_user_department_id')) {
        $departmentId = department_current_user_department_id(['id' => $viewerId]);
        if ($departmentId !== null) {
            $conditions[] = 'i.department_id = :viewer_department_id';
            $params['viewer_department_id'] = $departmentId;
        }
    } elseif ($viewerRole === 'citizen' && $viewerId !== null) {
        $conditions[] = 'i.user_id = :viewer_id';
        $params['viewer_id'] = $viewerId;
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'i.status = :status';
        $params['status'] = trim((string) $filters['status']);
    }

    if ($hasPriorityColumn && !empty($filters['priority'])) {
        $conditions[] = 'i.priority = :priority';
        $params['priority'] = trim((string) $filters['priority']);
    }

    if (!empty($filters['category_id'])) {
        $conditions[] = 'i.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['division'])) {
        $conditions[] = 'COALESCE(NULLIF(TRIM(i.division), \'\'), NULLIF(TRIM(reporter.division), \'\')) = :division';
        $params['division'] = trim((string) $filters['division']);
    }

    if (!empty($filters['query'])) {
        $queryLike = '%' . trim((string) $filters['query']) . '%';
        $conditions[] = '(i.ticket_number LIKE :query_ticket OR i.title LIKE :query_title OR i.location LIKE :query_location OR i.address LIKE :query_address)';
        $params['query_ticket'] = $queryLike;
        $params['query_title'] = $queryLike;
        $params['query_location'] = $queryLike;
        $params['query_address'] = $queryLike;
    }

    $sql =
        'SELECT i.id, i.ticket_number, i.title, i.description, i.status, ' . ($hasPriorityColumn ? 'i.priority' : "'medium' AS priority") . ',
            i.latitude, i.longitude, i.address, i.location, i.division, i.created_at, i.updated_at,
            c.name AS category_name,
                ' . ($hasDepartmentColumn ? 'dept.department_name AS department_name,' : '') . '
            reporter.full_name AS reporter_name,
            reporter.email AS reporter_email,
            reporter.division AS reporter_division,
            assignee.full_name AS assigned_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
            ' . ($hasDepartmentColumn ? 'LEFT JOIN departments dept ON dept.department_id = i.department_id' : '') . '
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY i.updated_at DESC, i.created_at DESC, i.id DESC
         LIMIT :limit';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function issue_fetch_division_breakdown(?int $viewerId = null, ?string $viewerRole = null, int $limit = 10): array
{
    $limit = max(1, min(20, $limit));
    $conditions = ['i.deleted_at IS NULL'];
    $params = [];

    if ($viewerRole === 'staff' && $viewerId !== null) {
        $conditions[] = '(i.assigned_to = :viewer_assigned_id OR i.user_id = :viewer_reporter_id)';
        $params['viewer_assigned_id'] = $viewerId;
        $params['viewer_reporter_id'] = $viewerId;
    } elseif ($viewerRole === 'department_manager' && $viewerId !== null && issue_issue_column_exists('department_id') && function_exists('department_current_user_department_id')) {
        $departmentId = department_current_user_department_id(['id' => $viewerId]);
        if ($departmentId !== null) {
            $conditions[] = 'i.department_id = :viewer_department_id';
            $params['viewer_department_id'] = $departmentId;
        }
    } elseif ($viewerRole === 'citizen' && $viewerId !== null) {
        $conditions[] = 'i.user_id = :viewer_id';
        $params['viewer_id'] = $viewerId;
    }

    $sql =
        'SELECT
            COALESCE(NULLIF(TRIM(i.division), \'\'), NULLIF(TRIM(reporter.division), \'\'), \'Unknown\') AS division_name,
            COUNT(*) AS issue_count,
            SUM(CASE WHEN i.status IN (\'resolved\', \'closed\') THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN i.status IN (\'submitted\', \'under_review\', \'assigned\', \'in_progress\', \'pending\', \'reopened\') THEN 1 ELSE 0 END) AS open_count,
            AVG(CASE WHEN i.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, i.created_at, i.resolved_at) END) AS avg_resolution_minutes
         FROM issues i
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         WHERE ' . implode(' AND ', $conditions) . '
         GROUP BY division_name
         ORDER BY issue_count DESC, division_name ASC
         LIMIT :limit';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function issue_fetch_issue_by_id(int $issueId): ?array
{
     $stmt = db()->prepare(
          'SELECT i.*, c.name AS category_name, c.slug AS category_slug,
                reporter.full_name AS reporter_name,
                reporter.email AS reporter_email,
                reporter.division AS reporter_division,
                assignee.full_name AS assigned_name,
                assignee.email AS assigned_email
            FROM issues i
            INNER JOIN issue_categories c ON c.id = i.category_id
            INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
            LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
            WHERE i.id = :id AND i.deleted_at IS NULL
            LIMIT 1'
     );
    $stmt->execute(['id' => $issueId]);

    $issue = $stmt->fetch();

    return $issue ?: null;
}

function issue_fetch_issue_by_ticket(string $ticketNumber): ?array
{
    $stmt = db()->prepare(
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug,
            reporter.full_name AS reporter_name,
            reporter.email AS reporter_email,
            reporter.division AS reporter_division,
            assignee.full_name AS assigned_name,
            assignee.email AS assigned_email
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id' . sql_table_deleted_cond('users', 'reporter') . '
         LEFT JOIN users assignee ON assignee.id = i.assigned_to' . sql_table_deleted_cond('users', 'assignee') . '
         WHERE i.ticket_number = :ticket_number AND i.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['ticket_number' => $ticketNumber]);

    $issue = $stmt->fetch();

    return $issue ?: null;
}

function issue_fetch_comments(int $issueId): array
{
    $stmt = db()->prepare(
        'SELECT ic.*, u.full_name AS author_name, r.name AS author_role
         FROM issue_comments ic
        INNER JOIN users u ON u.id = ic.user_id' . sql_table_deleted_cond('users', 'u') . '
         INNER JOIN roles r ON r.id = u.role_id
         WHERE ic.issue_id = :issue_id AND ic.deleted_at IS NULL
         ORDER BY ic.created_at ASC, ic.id ASC'
    );
    $stmt->execute(['issue_id' => $issueId]);

    return $stmt->fetchAll();
}

function issue_fetch_staff_members(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.full_name, u.email, u.division, u.department_id, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1" . sql_table_deleted_cond('users', 'u') . " AND r.name IN ('staff', 'admin')
         ORDER BY r.name ASC, u.full_name ASC"
    );

    return $stmt->fetchAll();
}

function issue_record_issue_log(int $issueId, int $userId, string $action, string $description): void
{
    if (!issue_table_exists('issue_logs')) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO issue_logs (issue_id, user_id, action, description)
         VALUES (:issue_id, :user_id, :action, :description)'
    );
    $stmt->execute([
        'issue_id' => $issueId,
        'user_id' => $userId,
        'action' => $action,
        'description' => trim($description),
    ]);
}

function issue_create_notification(int $userId, string $message): void
{
    if (!issue_table_exists('notifications')) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, message, is_read)
         VALUES (:user_id, :message, 0)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'message' => trim($message),
    ]);
}

function issue_fetch_issue_timeline(int $issueId, int $page = 1, int $perPage = 10): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    $issueParamOne = $issueId;
    $issueParamTwo = $issueId;
    $hasLogsTable = issue_table_exists('issue_logs');

    if ($hasLogsTable) {
        $countStmt = db()->prepare(
            'SELECT COUNT(*) AS total FROM (
                SELECT id, created_at FROM issue_comments WHERE issue_id = :issue_id_one AND deleted_at IS NULL
                UNION ALL
                SELECT id, created_at FROM issue_logs WHERE issue_id = :issue_id_two
             ) AS timeline_entries'
        );
        $countStmt->execute([
            'issue_id_one' => $issueParamOne,
            'issue_id_two' => $issueParamTwo,
        ]);
    } else {
        $countStmt = db()->prepare('SELECT COUNT(*) AS total FROM issue_comments WHERE issue_id = :issue_id_one AND deleted_at IS NULL');
        $countStmt->execute(['issue_id_one' => $issueParamOne]);
    }
    $total = (int) ($countStmt->fetch()['total'] ?? 0);

    if ($hasLogsTable) {
        $sql = "SELECT * FROM (
            SELECT
                ic.id AS entry_id,
                ic.issue_id,
                ic.user_id,
                ic.comment AS message,
                ic.created_at,
                ic.is_public,
                'comment' AS entry_type,
                u.full_name AS author_name,
                r.name AS author_role,
                NULL AS action,
                NULL AS description
            FROM issue_comments ic
            INNER JOIN users u ON u.id = ic.user_id" . sql_table_deleted_cond('users', 'u') . "
            INNER JOIN roles r ON r.id = u.role_id
            WHERE ic.issue_id = :issue_id_one AND ic.deleted_at IS NULL
            UNION ALL
            SELECT
                il.id AS entry_id,
                il.issue_id,
                il.user_id,
                il.description AS message,
                il.created_at,
                1 AS is_public,
                'log' AS entry_type,
                u.full_name AS author_name,
                r.name AS author_role,
                il.action,
                il.description
            FROM issue_logs il
            INNER JOIN users u ON u.id = il.user_id" . sql_table_deleted_cond('users', 'u') . "
            INNER JOIN roles r ON r.id = u.role_id
                WHERE il.issue_id = :issue_id_two
         ) AS timeline
         ORDER BY created_at DESC, entry_id DESC
         LIMIT :limit OFFSET :offset";

        $stmt = db()->prepare($sql);
        $stmt->bindValue(':issue_id_one', $issueId, PDO::PARAM_INT);
        $stmt->bindValue(':issue_id_two', $issueId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $sql = "SELECT
                ic.id AS entry_id,
                ic.issue_id,
                ic.user_id,
                ic.comment AS message,
                ic.created_at,
                ic.is_public,
                'comment' AS entry_type,
                u.full_name AS author_name,
                r.name AS author_role,
                NULL AS action,
                NULL AS description
            FROM issue_comments ic
            INNER JOIN users u ON u.id = ic.user_id" . sql_table_deleted_cond('users', 'u') . "
            INNER JOIN roles r ON r.id = u.role_id
                WHERE ic.issue_id = :issue_id_one AND ic.deleted_at IS NULL
            ORDER BY ic.created_at DESC, ic.id DESC
            LIMIT :limit OFFSET :offset";

        $stmt = db()->prepare($sql);
        $stmt->bindValue(':issue_id_one', $issueId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function issue_fetch_recent_activity(?int $userId = null, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $params = ['limit' => $limit];
    $userFilterLogs = '';
    $userFilterComments = '';
    $prioritySelect = issue_issue_column_exists('priority') ? 'i.priority' : 'NULL AS priority';
    $hasLogsTable = issue_table_exists('issue_logs');

    if ($userId !== null) {
        $userFilterLogs = ' WHERE i.user_id = :user_id_logs_one OR i.assigned_to = :user_id_logs_two';
        $userFilterComments = ' WHERE i.user_id = :user_id_comments_one OR i.assigned_to = :user_id_comments_two';
        $params['user_id_logs_one'] = $userId;
        $params['user_id_logs_two'] = $userId;
        $params['user_id_comments_one'] = $userId;
        $params['user_id_comments_two'] = $userId;
    }

    if ($hasLogsTable) {
        $stmt = db()->prepare(
                        "SELECT t.* FROM (
                                 SELECT il.id AS entry_id, il.issue_id, il.user_id, il.action, il.description, il.created_at,
                                         i.ticket_number, i.title, i.status, $prioritySelect,
                                     u.full_name AS actor_name, r.name AS actor_role
                        FROM issue_logs il
                            INNER JOIN issues i ON i.id = il.issue_id AND i.deleted_at IS NULL
                            INNER JOIN users u ON u.id = il.user_id" . sql_table_deleted_cond('users', 'u') . "
                                 INNER JOIN roles r ON r.id = u.role_id" . $userFilterLogs . "
                        UNION ALL
                                 SELECT ic.id AS entry_id, ic.issue_id, ic.user_id, 'comment_added' AS action, ic.comment AS description, ic.created_at,
                                         i.ticket_number, i.title, i.status, $prioritySelect,
                                     u.full_name AS actor_name, r.name AS actor_role
                        FROM issue_comments ic
                            INNER JOIN issues i ON i.id = ic.issue_id AND i.deleted_at IS NULL
                            INNER JOIN users u ON u.id = ic.user_id" . sql_table_deleted_cond('users', 'u') . "
                                 INNER JOIN roles r ON r.id = u.role_id" . $userFilterComments . "
                ) AS t
                ORDER BY t.created_at DESC, t.entry_id DESC
                LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
    } else {
        $stmt = db()->prepare(
                        "SELECT ic.id AS entry_id, ic.issue_id, ic.user_id, 'comment_added' AS action, ic.comment AS description, ic.created_at,
                                     i.ticket_number, i.title, i.status, $prioritySelect,
                                     u.full_name AS actor_name, r.name AS actor_role
                         FROM issue_comments ic
                             INNER JOIN issues i ON i.id = ic.issue_id AND i.deleted_at IS NULL
                             INNER JOIN users u ON u.id = ic.user_id" . sql_table_deleted_cond('users', 'u') . "
                         INNER JOIN roles r ON r.id = u.role_id" . $userFilterComments . "
                         ORDER BY ic.created_at DESC, ic.id DESC
                         LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    return $stmt->fetchAll();
}

function issue_fetch_latest_staff_responses(int $userId, int $limit = 5): array
{
    $prioritySelect = issue_issue_column_exists('priority') ? 'i.priority' : 'NULL AS priority';

    $stmt = db()->prepare(
          "SELECT ic.id, ic.comment, ic.created_at, i.ticket_number, i.title, i.status, $prioritySelect,
                     u.full_name AS author_name, r.name AS author_role
            FROM issue_comments ic
                INNER JOIN issues i ON i.id = ic.issue_id AND i.deleted_at IS NULL
                INNER JOIN users u ON u.id = ic.user_id" . sql_table_deleted_cond('users', 'u') . "
            INNER JOIN roles r ON r.id = u.role_id
                WHERE i.user_id = :user_id AND i.deleted_at IS NULL AND r.name IN ('staff', 'admin')
            ORDER BY ic.created_at DESC, ic.id DESC
            LIMIT :limit"
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function issue_fetch_staff_workload(?int $departmentId = null): array
{
    $statusColumn = 'i.status';
    $departmentClause = '';
    $params = [];
    if ($departmentId !== null && db_column_exists('users', 'department_id')) {
        $departmentClause = ' AND u.department_id = :department_id';
        $params['department_id'] = $departmentId;
    }

    $stmt = db()->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            COUNT(i.id) AS total_assigned,
            SUM(CASE WHEN $statusColumn IN ('submitted', 'under_review', 'pending') THEN 1 ELSE 0 END) AS pending_tasks,
            SUM(CASE WHEN $statusColumn IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) AS active_tasks,
            SUM(CASE WHEN $statusColumn IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved_tasks
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN issues i ON i.assigned_to = u.id AND i.deleted_at IS NULL
                WHERE u.is_active = 1" . sql_table_deleted_cond('users', 'u') . $departmentClause . " AND r.name IN ('staff', 'department_manager', 'admin')
         GROUP BY u.id, u.full_name, u.email
         ORDER BY total_assigned DESC, u.full_name ASC"
    );

    if ($params) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    return $stmt->fetchAll();
}

function issue_fetch_common_categories(int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.name, c.slug, COUNT(i.id) AS issue_count
         FROM issue_categories c
            LEFT JOIN issues i ON i.category_id = c.id AND i.deleted_at IS NULL
         GROUP BY c.id, c.name, c.slug
         ORDER BY issue_count DESC, c.sort_order ASC, c.name ASC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function issue_fetch_notifications(int $userId, int $limit = 10): array
{
    if (!issue_table_exists('notifications')) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, user_id, message, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function issue_mark_notification_read(int $notificationId, int $userId): void
{
    if (!issue_table_exists('notifications')) {
        return;
    }

    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);
}

function issue_issue_status_transition_description(string $status, ?string $previousStatus = null): string
{
    if ($previousStatus !== null && $previousStatus !== '' && $previousStatus !== $status) {
        return sprintf('Issue status changed from %s to %s.', issue_status_label($previousStatus), issue_status_label($status));
    }

    return sprintf('Issue marked as %s.', issue_status_label($status));
}

function issue_add_comment(int $issueId, int $userId, string $comment, bool $isPublic = true): int
{
    $stmt = db()->prepare(
        'INSERT INTO issue_comments (issue_id, user_id, comment, is_public)
         VALUES (:issue_id, :user_id, :comment, :is_public)'
    );
    $stmt->execute([
        'issue_id' => $issueId,
        'user_id' => $userId,
        'comment' => trim($comment),
        'is_public' => $isPublic ? 1 : 0,
    ]);

    return (int) db()->lastInsertId();
}

function issue_soft_delete_comment(int $commentId, int $deletedBy): void
{
    if (!issue_table_exists('issue_comments')) {
        return;
    }

    $stmt = db()->prepare('UPDATE issue_comments SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute([
        'deleted_by' => $deletedBy,
        'id' => $commentId,
    ]);
}

function issue_soft_delete_issue(int $issueId, int $deletedBy): void
{
    $stmt = db()->prepare('UPDATE issues SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute([
        'deleted_by' => $deletedBy,
        'id' => $issueId,
    ]);
}

function issue_restore_issue(int $issueId): void
{
    $stmt = db()->prepare('UPDATE issues SET deleted_at = NULL, deleted_by = NULL WHERE id = :id AND deleted_at IS NOT NULL');
    $stmt->execute(['id' => $issueId]);
}

function issue_update_workflow(int $issueId, string $status, ?int $assignedTo, int $actorId, ?string $comment = null, ?string $priority = null, ?string $resolutionNotes = null): void
{
    $issue = issue_fetch_issue_by_id($issueId);
    if (!$issue) {
        throw new RuntimeException('Issue not found.');
    }

    $validStatuses = issue_workflow_statuses();
    if (!in_array($status, $validStatuses, true)) {
        throw new InvalidArgumentException('Invalid workflow status.');
    }

    if ($status === 'resolved') {
        $status = 'awaiting_citizen_verification';
    }

    $validPriorities = array_keys(issue_priority_catalog());
    if ($priority !== null && !in_array($priority, $validPriorities, true)) {
        throw new InvalidArgumentException('Invalid issue priority.');
    }

    $hasPriorityColumn = issue_issue_column_exists('priority');
    $hasResolutionNotesColumn = issue_issue_column_exists('resolution_notes');
    $hasResolvedAtColumn = issue_issue_column_exists('resolved_at');
    $hasReopenedAtColumn = issue_issue_column_exists('reopened_at');
    $hasCitizenVerifiedAtColumn = issue_issue_column_exists('citizen_verified_at');
    $hasClosedAtColumn = issue_issue_column_exists('closed_at');

    db()->beginTransaction();

    try {
        $previousStatus = (string) $issue['status'];
        $previousPriority = (string) ($issue['priority'] ?? 'medium');
        $previousAssignee = isset($issue['assigned_to']) ? (int) $issue['assigned_to'] : null;
        $newPriority = $priority ?? $previousPriority;
        $normalizedResolutionNotes = $resolutionNotes !== null ? trim($resolutionNotes) : null;

        $setParts = [
            'status = :status',
            'assigned_to = :assigned_to',
        ];
        $updateParams = [
            'status' => $status,
            'assigned_to' => $assignedTo,
            'id' => $issueId,
        ];

        if ($hasPriorityColumn) {
            $setParts[] = 'priority = :priority';
            $updateParams['priority'] = $newPriority;
        }

        if ($hasResolutionNotesColumn) {
            $setParts[] = 'resolution_notes = COALESCE(:resolution_notes, resolution_notes)';
            $updateParams['resolution_notes'] = $normalizedResolutionNotes;
        }

        if ($hasResolvedAtColumn) {
            $setParts[] = 'resolved_at = CASE WHEN :status_resolved = 1 THEN COALESCE(resolved_at, CURRENT_TIMESTAMP) ELSE resolved_at END';
            $updateParams['status_resolved'] = in_array($status, issue_resolved_statuses(), true) ? 1 : 0;
        }

        if ($hasReopenedAtColumn) {
            $setParts[] = 'reopened_at = CASE WHEN :status_reopened = 1 THEN CURRENT_TIMESTAMP ELSE reopened_at END';
            $updateParams['status_reopened'] = $status === 'reopened' ? 1 : 0;
        }

        if ($hasCitizenVerifiedAtColumn && $status === 'closed') {
            $setParts[] = 'citizen_verified_at = COALESCE(citizen_verified_at, CURRENT_TIMESTAMP)';
        }

        if ($hasClosedAtColumn && $status === 'closed') {
            $setParts[] = 'closed_at = COALESCE(closed_at, CURRENT_TIMESTAMP)';
        }

        $update = db()->prepare('UPDATE issues SET ' . implode(', ', $setParts) . ' WHERE id = :id');
        $update->execute($updateParams);

        if ($previousStatus !== $status) {
            issue_record_issue_log($issueId, $actorId, 'status_updated', issue_issue_status_transition_description($status, $previousStatus));
        }

        if ($hasPriorityColumn && $previousPriority !== $newPriority) {
            issue_record_issue_log($issueId, $actorId, 'priority_updated', sprintf('Priority changed from %s to %s.', issue_priority_label($previousPriority), issue_priority_label($newPriority)));
        }

        if ($previousAssignee !== $assignedTo) {
            if ($previousAssignee === null && $assignedTo !== null) {
                issue_record_issue_log($issueId, $actorId, 'issue_assigned', 'Issue assigned to a staff member.');
            } elseif ($previousAssignee !== null && $assignedTo !== null) {
                issue_record_issue_log($issueId, $actorId, 'issue_reassigned', 'Issue reassigned to another staff member.');
            } elseif ($previousAssignee !== null && $assignedTo === null) {
                issue_record_issue_log($issueId, $actorId, 'issue_reassigned', 'Issue was unassigned.');
            }
        }

        if ($hasResolutionNotesColumn && $normalizedResolutionNotes !== null && $normalizedResolutionNotes !== '') {
            $resolvedAction = in_array($status, issue_resolved_statuses(), true) ? 'issue_resolved' : 'status_updated';
            issue_record_issue_log($issueId, $actorId, $resolvedAction, 'Resolution notes added: ' . $normalizedResolutionNotes);
        }

        if ($comment !== null && trim($comment) !== '') {
            issue_add_comment($issueId, $actorId, trim($comment), true);
            issue_record_issue_log($issueId, $actorId, 'comment_added', 'Workflow comment added.');
        }

        $notifications = [];
        $reporterId = (int) ($issue['user_id'] ?? 0);

        if ($reporterId > 0) {
            $statusMessage = match ($status) {
                'awaiting_citizen_verification' => 'Your issue ' . ($issue['ticket_number'] ?? '') . ' has been marked resolved. Please confirm or reopen it.',
                'closed' => 'Your issue ' . ($issue['ticket_number'] ?? '') . ' has been closed.',
                'reopened' => 'Your issue ' . ($issue['ticket_number'] ?? '') . ' was reopened for follow-up.',
                default => 'Your issue ' . ($issue['ticket_number'] ?? '') . ' was updated.',
            };
            $notifications[] = [
                'user_id' => $reporterId,
                'message' => $statusMessage,
            ];
        }

        if ($assignedTo !== null) {
            $notifications[] = [
                'user_id' => $assignedTo,
                'message' => 'An issue has been assigned to you.',
            ];
        }

        foreach ($notifications as $notification) {
            if ($notification['user_id'] !== $actorId) {
                issue_create_notification((int) $notification['user_id'], (string) $notification['message']);
            }
        }

        db()->commit();
    } catch (Throwable $throwable) {
        db()->rollBack();

        throw $throwable;
    }
}

function issue_fetch_issue_logs(int $issueId, int $page = 1, int $perPage = 20): array
{
    $timeline = issue_fetch_issue_timeline($issueId, $page, $perPage);

    $items = [];
    foreach ($timeline['items'] as $entry) {
        if (($entry['entry_type'] ?? '') !== 'log') {
            continue;
        }

        $items[] = $entry;
    }

    $timeline['items'] = $items;

    return $timeline;
}
