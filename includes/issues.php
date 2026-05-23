<?php

declare(strict_types=1);

function issue_status_catalog(): array
{
    return [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];
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
        'closed' => 'dark',
        default => 'secondary',
    };
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

    db()->beginTransaction();

    try {
        $stmt = db()->prepare(
            'INSERT INTO issues (ticket_number, user_id, category_id, title, description, image, location, status, assigned_to)
             VALUES (:ticket_number, :user_id, :category_id, :title, :description, :image, :location, :status, NULL)'
        );
        $stmt->execute([
            'ticket_number' => $temporaryTicket,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description,
            'image' => $imageName,
            'location' => $locationText,
            'status' => 'submitted',
        ]);

        $issueId = (int) db()->lastInsertId();
        $ticketNumber = issue_build_ticket_number($issueId, $now);

        $update = db()->prepare('UPDATE issues SET ticket_number = :ticket_number WHERE id = :id');
        $update->execute([
            'ticket_number' => $ticketNumber,
            'id' => $issueId,
        ]);

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

function issue_fetch_status_counts(?int $userId = null): array
{
    $baseWhere = '';
    $params = [];

    if ($userId !== null) {
        $baseWhere = ' WHERE user_id = :user_id';
        $params['user_id'] = $userId;
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
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('submitted', 'under_review', 'assigned', 'in_progress')"
    );
    $openStmt->execute($params);
    $counts['open'] = (int) ($openStmt->fetch()['total'] ?? 0);

    $resolvedStmt = db()->prepare(
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('resolved', 'closed')"
    );
    $resolvedStmt->execute($params);
    $counts['resolved'] = (int) ($resolvedStmt->fetch()['total'] ?? 0);

    $pendingStmt = db()->prepare(
        'SELECT COUNT(*) AS total FROM issues' . $baseWhere . ($baseWhere ? ' AND ' : ' WHERE ') . "status IN ('submitted', 'under_review')"
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

function issue_fetch_management_issues(array $filters = []): array
{
    $conditions = [];
    $params = [];

    if (!empty($filters['ticket_number'])) {
        $conditions[] = 'i.ticket_number LIKE :ticket_number';
        $params['ticket_number'] = '%' . trim((string) $filters['ticket_number']) . '%';
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'i.status = :status';
        $params['status'] = trim((string) $filters['status']);
    }

    if (!empty($filters['category_id'])) {
        $conditions[] = 'i.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['location'])) {
        $conditions[] = 'i.location LIKE :location';
        $params['location'] = '%' . trim((string) $filters['location']) . '%';
    }

    $sql =
        'SELECT i.*, c.name AS category_name, c.slug AS category_slug,
            reporter.full_name AS reporter_name,
            reporter.email AS reporter_email,
            assignee.full_name AS assigned_name
         FROM issues i
         INNER JOIN issue_categories c ON c.id = i.category_id
         INNER JOIN users reporter ON reporter.id = i.user_id
         LEFT JOIN users assignee ON assignee.id = i.assigned_to';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY i.updated_at DESC, i.created_at DESC, i.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

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
         INNER JOIN users reporter ON reporter.id = i.user_id
         LEFT JOIN users assignee ON assignee.id = i.assigned_to
         WHERE i.id = :id
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
         INNER JOIN users reporter ON reporter.id = i.user_id
         LEFT JOIN users assignee ON assignee.id = i.assigned_to
         WHERE i.ticket_number = :ticket_number
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
         INNER JOIN users u ON u.id = ic.user_id
         INNER JOIN roles r ON r.id = u.role_id
         WHERE ic.issue_id = :issue_id
         ORDER BY ic.created_at ASC, ic.id ASC'
    );
    $stmt->execute(['issue_id' => $issueId]);

    return $stmt->fetchAll();
}

function issue_fetch_staff_members(): array
{
    $stmt = db()->query(
        "SELECT u.id, u.full_name, u.email, u.division, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1 AND r.name IN ('staff', 'admin')
         ORDER BY r.name ASC, u.full_name ASC"
    );

    return $stmt->fetchAll();
}

function issue_update_workflow(int $issueId, string $status, ?int $assignedTo, int $actorId, ?string $comment = null): void
{
    db()->beginTransaction();

    try {
        $update = db()->prepare(
            'UPDATE issues
             SET status = :status, assigned_to = :assigned_to
             WHERE id = :id'
        );
        $update->execute([
            'status' => $status,
            'assigned_to' => $assignedTo,
            'id' => $issueId,
        ]);

        if ($comment !== null && trim($comment) !== '') {
            $insertComment = db()->prepare(
                'INSERT INTO issue_comments (issue_id, user_id, comment, is_public)
                 VALUES (:issue_id, :user_id, :comment, 1)'
            );
            $insertComment->execute([
                'issue_id' => $issueId,
                'user_id' => $actorId,
                'comment' => trim($comment),
            ]);
        }

        db()->commit();
    } catch (Throwable $throwable) {
        db()->rollBack();

        throw $throwable;
    }
}

function issue_add_comment(int $issueId, int $userId, string $comment, bool $isPublic = true): void
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
}
