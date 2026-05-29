<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$role = current_user_role();
$user = current_user();
$issueId = (int) ($_GET['id'] ?? 0);
$ticketNumber = trim((string) ($_GET['ticket'] ?? ''));
$timelinePage = max(1, (int) ($_GET['timeline_page'] ?? 1));
$timelinePerPage = 8;
$pageStyles = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
    app_url('assets/css/maps.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/maps.css'),
];
$pageScripts = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
    app_url('assets/js/maps/geolocation.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/geolocation.js'),
    app_url('assets/js/maps/issue-map.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/issue-map.js'),
];

$issue = null;
if ($issueId > 0) {
    $issue = issue_fetch_issue_by_id($issueId);
} elseif ($ticketNumber !== '') {
    $issue = issue_fetch_issue_by_ticket($ticketNumber);
}

if (!$issue) {
    http_response_code(404);
    $pageTitle = APP_NAME . ' | Issue Not Found';
    require_once __DIR__ . '/../includes/header.php';
    if (is_logged_in()) {
        require_once __DIR__ . '/../includes/sidebar.php';
    }
?>
    <section class="container-fluid py-4">
        <div class="app-card bg-white p-4">
            <h1 class="h4 mb-2">Issue not found</h1>
            <p class="text-muted mb-0">The ticket you requested does not exist or you do not have access to it.</p>
        </div>
    </section>
<?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$isCitizenOwner = $role === 'citizen' && (int) $issue['user_id'] === (int) $user['id'];
$isAssignedStaff = $role === 'staff' && (int) ($issue['assigned_to'] ?? 0) === (int) $user['id'];
$canManage = in_array((string) $role, ['staff', 'admin'], true);
$canAccessConversation = $role === 'admin' || $isCitizenOwner || $isAssignedStaff;

if ($role === 'citizen' && !$isCitizenOwner) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

if ($role === 'staff' && !$isAssignedStaff) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$baseQuery = $issueId > 0
    ? ['id' => (int) $issue['id']]
    : ['ticket' => (string) $issue['ticket_number']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token. Please refresh and try again.');
    } else {
        $action = trim((string) ($_POST['action'] ?? 'add_comment'));

        try {
            if ($action === 'add_comment') {
                if (!$canAccessConversation) {
                    throw new RuntimeException('You do not have permission to comment on this issue.');
                }

                $comment = trim((string) ($_POST['comment'] ?? ''));
                if ($comment === '') {
                    throw new RuntimeException('Enter a comment before submitting.');
                }

                issue_add_comment((int) $issue['id'], (int) $user['id'], $comment, true);
                issue_record_issue_log((int) $issue['id'], (int) $user['id'], 'comment_added', 'Comment added to the issue thread.');

                $recipientId = $role === 'citizen'
                    ? (int) ($issue['assigned_to'] ?? 0)
                    : (int) ($issue['user_id'] ?? 0);

                if ($recipientId > 0 && $recipientId !== (int) $user['id']) {
                    issue_create_notification(
                        $recipientId,
                        $role === 'citizen'
                            ? 'A citizen replied to ticket ' . $issue['ticket_number'] . '.'
                            : 'A staff member replied to your issue ' . $issue['ticket_number'] . '.'
                    );
                }

                set_flash('success', 'Your comment was posted successfully.');
                redirect(app_url('issues/view.php?' . http_build_query(array_merge($baseQuery, ['timeline_page' => $timelinePage]))));
            }

            if ($action === 'delete_comment') {
                if (!$canAccessConversation) {
                    throw new RuntimeException('You do not have permission to manage comments on this issue.');
                }

                $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
                if ($commentId <= 0) {
                    throw new RuntimeException('Invalid comment specified.');
                }

                $stmt = db()->prepare('SELECT id, issue_id, user_id FROM issue_comments WHERE id = :id AND deleted_at IS NULL');
                $stmt->execute(['id' => $commentId]);
                $commentRow = $stmt->fetch();

                if (!$commentRow) {
                    throw new RuntimeException('Comment not found or already deleted.');
                }

                if ((int) $commentRow['issue_id'] !== (int) $issue['id']) {
                    throw new RuntimeException('Comment does not belong to this issue.');
                }

                $isOwner = isset($user['id']) && (int) $user['id'] === (int) $commentRow['user_id'];
                if (!in_array($role, ['admin', 'staff'], true) && !$isOwner) {
                    throw new RuntimeException('You are not authorized to delete this comment.');
                }

                issue_soft_delete_comment($commentId, (int) $user['id']);
                app_log_system_event('recovery', 'warning', 'Comment moved to trash', ['comment_id' => $commentId, 'issue_id' => $issue['id']], (int) $user['id'], __FUNCTION__);
                issue_record_issue_log((int) $issue['id'], (int) $user['id'], 'comment_deleted', 'Comment moved to trash.');

                set_flash('success', 'Comment moved to trash.');
                redirect(app_url('issues/view.php?' . http_build_query(array_merge($baseQuery, ['timeline_page' => $timelinePage]))));
            }

            if ($action === 'confirm_resolution') {
                if ($role !== 'citizen') {
                    throw new RuntimeException('Only citizens can confirm a resolution.');
                }

                if (!in_array((string) $issue['status'], ['resolved', 'closed'], true)) {
                    throw new RuntimeException('This issue is not in a resolvable state.');
                }

                issue_update_workflow(
                    (int) $issue['id'],
                    'closed',
                    isset($issue['assigned_to']) ? (int) $issue['assigned_to'] : null,
                    (int) $user['id'],
                    'Citizen confirmed the resolution.',
                    null,
                    null
                );

                set_flash('success', 'Resolution confirmed. The issue has been closed.');
                redirect(app_url('issues/view.php?' . http_build_query(array_merge($baseQuery, ['timeline_page' => $timelinePage]))));
            }

            if ($action === 'reopen_issue') {
                if ($role !== 'citizen') {
                    throw new RuntimeException('Only citizens can reopen an issue.');
                }

                if (!in_array((string) $issue['status'], ['resolved', 'closed'], true)) {
                    throw new RuntimeException('Only resolved or closed issues can be reopened.');
                }

                issue_update_workflow(
                    (int) $issue['id'],
                    'reopened',
                    isset($issue['assigned_to']) ? (int) $issue['assigned_to'] : null,
                    (int) $user['id'],
                    'Citizen reopened the issue for follow-up.',
                    null,
                    null
                );

                set_flash('success', 'The issue has been reopened for follow-up.');
                redirect(app_url('issues/view.php?' . http_build_query(array_merge($baseQuery, ['timeline_page' => $timelinePage]))));
            }

            if ($action !== 'update_workflow') {
                throw new RuntimeException('Unknown action.');
            }

            if (!$canManage) {
                throw new RuntimeException('You do not have permission to update this issue.');
            }

            $status = trim((string) ($_POST['status'] ?? ''));
            $priority = trim((string) ($_POST['priority'] ?? 'medium'));
            $assignedTo = trim((string) ($_POST['assigned_to'] ?? ''));
            $workflowComment = trim((string) ($_POST['comment'] ?? ''));
            $resolutionNotes = trim((string) ($_POST['resolution_notes'] ?? ''));

            $validStatuses = array_keys(issue_status_catalog());
            $validPriorities = array_keys(issue_priority_catalog());

            if (!in_array($status, $validStatuses, true)) {
                throw new RuntimeException('Select a valid issue status.');
            }

            if (!in_array($priority, $validPriorities, true)) {
                throw new RuntimeException('Select a valid issue priority.');
            }

            $assignedId = $assignedTo !== '' ? (int) $assignedTo : null;

            if ($role === 'staff') {
                $assignedId = (int) ($issue['assigned_to'] ?? $user['id']);
            }

            if ($assignedId !== null) {
                $staffStmt = db()->prepare("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND r.name IN ('staff', 'admin') LIMIT 1");
                $staffStmt->execute(['id' => $assignedId]);
                if (!$staffStmt->fetch()) {
                    throw new RuntimeException('Select a valid staff member for assignment.');
                }
            }

            issue_update_workflow(
                (int) $issue['id'],
                $status,
                $assignedId,
                (int) $user['id'],
                $workflowComment !== '' ? $workflowComment : null,
                $priority,
                $resolutionNotes !== '' ? $resolutionNotes : null
            );

            set_flash('success', 'Issue workflow updated successfully.');
            redirect(app_url('issues/view.php?' . http_build_query(array_merge($baseQuery, ['timeline_page' => $timelinePage]))));
        } catch (Throwable $throwable) {
            set_flash('error', $throwable->getMessage() ?: 'The issue could not be updated right now.');
        }
    }
}

$issue = issue_fetch_issue_by_id((int) $issue['id']) ?? $issue;
$timeline = issue_fetch_issue_timeline((int) $issue['id'], $timelinePage, $timelinePerPage);
$staffMembers = $canManage ? issue_fetch_staff_members() : [];
$notifications = $role === 'citizen' ? issue_fetch_notifications((int) $user['id'], 5) : [];
$pageTitle = APP_NAME . ' | ' . $issue['ticket_number'];
$activePage = $role === 'citizen' ? 'citizen-issues' : ($role === 'staff' ? 'staff-issues' : 'admin-issues');

require_once __DIR__ . '/../includes/header.php';
if (is_logged_in()) {
    require_once __DIR__ . '/../includes/sidebar.php';
}
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Issue Ticket</p>
                        <h1 class="h3 mb-2"><?= e($issue['ticket_number']) ?></h1>
                        <p class="mb-0 text-muted"><?= e($issue['title']) ?></p>
                    </div>
                    <div class="text-lg-end">
                        <div class="mb-2 d-flex justify-content-lg-end gap-2 flex-wrap">
                            <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                            <span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span>
                        </div>
                        <div class="small text-muted">Category: <?= e($issue['category_name']) ?></div>
                        <div class="small text-muted">Submitted: <?= e(date('d M Y, H:i', strtotime((string) $issue['created_at']))) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white p-4 mb-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1">Reported By</div>
                        <div class="fw-semibold"><?= e($issue['reporter_name']) ?></div>
                        <div class="small text-muted"><?= e($issue['reporter_email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1">Location</div>
                        <div class="fw-semibold"><?= e($issue['location']) ?></div>
                        <div class="small text-muted">Division: <?= e($issue['division'] ?? $issue['reporter_division'] ?? 'Not provided') ?></div>
                        <?php if (!empty($issue['address'])) : ?>
                            <div class="small text-muted">Address: <?= e((string) $issue['address']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($issue['latitude']) && !empty($issue['longitude'])) : ?>
                            <div class="small text-muted">Coordinates: <?= e(number_format((float) $issue['latitude'], 6)) ?>, <?= e(number_format((float) $issue['longitude'], 6)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1">Priority</div>
                        <div class="fw-semibold">
                            <span class="issue-badge <?= e(issue_priority_badge_class((string) ($issue['priority'] ?? 'medium'))) ?>"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1">Description</div>
                        <p class="mb-0"><?= nl2br(e($issue['description'])) ?></p>
                    </div>
                    <?php if (!empty($issue['resolution_notes'])) : ?>
                        <div class="col-12">
                            <div class="text-muted small text-uppercase mb-1">Resolution Notes</div>
                            <div class="alert alert-success mb-0"><?= nl2br(e((string) $issue['resolution_notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card bg-white p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Activity Timeline</h2>
                        <p class="text-muted mb-0">Comments, status updates, assignments, and resolution events appear here in chronological order.</p>
                    </div>
                </div>

                <?php if (!$timeline['items']) : ?>
                    <div class="alert alert-info">No activity has been recorded for this issue yet.</div>
                <?php else : ?>
                    <div class="timeline-list d-grid gap-3 mb-4">
                        <?php foreach ($timeline['items'] as $entry) : ?>
                            <div class="border rounded-3 p-3 bg-white">
                                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2 align-items-center">
                                    <div class="fw-semibold">
                                        <?php if (($entry['entry_type'] ?? '') === 'comment') : ?>
                                            <?= e($entry['author_name'] ?? 'Unknown') ?> <span class="text-muted small">(<?= e($entry['author_role'] ?? 'user') ?>)</span>
                                        <?php else : ?>
                                            <?= e(issue_log_action_label((string) ($entry['action'] ?? 'event'))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="small text-muted"><?= e(date('d M Y, H:i', strtotime((string) $entry['created_at']))) ?></div>
                                        <?php if (($entry['entry_type'] ?? '') === 'comment') : ?>
                                            <?php $canDeleteComment = isset($role) && (in_array($role, ['admin', 'staff'], true) || (isset($user['id']) && (int) $user['id'] === (int) ($entry['user_id'] ?? 0))); ?>
                                            <?php if ($canDeleteComment) : ?>
                                                <form method="post" action="" onsubmit="return confirm('Move this comment to trash?');" class="mb-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_comment">
                                                    <input type="hidden" name="comment_id" value="<?= e((string) ($entry['entry_id'] ?? '')) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div><?= nl2br(e((string) ($entry['message'] ?? $entry['description'] ?? ''))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($timeline['pages'] > 1) : ?>
                        <nav aria-label="Timeline pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($pageIndex = 1; $pageIndex <= $timeline['pages']; $pageIndex++) : ?>
                                    <?php $pageQuery = array_merge($baseQuery, ['timeline_page' => $pageIndex]); ?>
                                    <li class="page-item <?= $pageIndex === $timeline['page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= e(app_url('issues/view.php?' . http_build_query($pageQuery))) ?>"><?= e((string) $pageIndex) ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="mt-4">
                    <form method="post" action="" class="comment-compose">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Add a comment</label>
                            <textarea class="form-control" name="comment" id="comment" rows="4" placeholder="Add a progress note, question, or response"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Post Comment</button>
                    </form>
                </div>

                <?php if ($role === 'citizen' && in_array((string) $issue['status'], ['resolved', 'closed'], true)) : ?>
                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <form method="post" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="confirm_resolution">
                            <button type="submit" class="btn btn-success">Confirm Resolution</button>
                        </form>
                        <form method="post" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reopen_issue">
                            <button type="submit" class="btn btn-outline-warning">Reopen Issue</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Submitted Photo</h2>
                <?php if (!empty($issue['image'])) : ?>
                    <?php $issueImageUrl = app_url('issues/image.php?id=' . (int) $issue['id']); ?>
                    <?php if ($issueImageUrl) : ?>
                        <a href="<?= e($issueImageUrl) ?>" target="_blank" rel="noopener noreferrer" class="d-block">
                            <img class="img-fluid rounded-3 border w-100" src="<?= e($issueImageUrl) ?>" alt="Citizen submitted issue photo" style="max-height: 320px; object-fit: cover;">
                        </a>
                        <div class="small text-muted mt-2">Click the image to open the full-size upload.</div>
                    <?php else : ?>
                        <div class="alert alert-light mb-0">The uploaded image could not be located.</div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="alert alert-light mb-0">No image was uploaded for this ticket.</div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Issue Location</h2>
                <?php if (!empty($issue['latitude']) && !empty($issue['longitude'])) : ?>
                    <div class="map-canvas map-canvas--sm" id="issueDetailMap" data-map-mode="readonly" data-issue-lat="<?= e((string) $issue['latitude']) ?>" data-issue-lng="<?= e((string) $issue['longitude']) ?>" data-issue-title="<?= e($issue['ticket_number']) ?>" data-issue-address="<?= e((string) ($issue['address'] ?? '')) ?>"></div>
                    <div class="small text-muted mt-2">Pin location: <?= e(number_format((float) $issue['latitude'], 6)) ?>, <?= e(number_format((float) $issue['longitude'], 6)) ?></div>
                <?php else : ?>
                    <div class="alert alert-light mb-0">No GPS coordinates were captured for this ticket.</div>
                <?php endif; ?>
            </div>

            <div class="app-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Issue Summary</h2>
                <div class="d-grid gap-3">
                    <div>
                        <div class="text-muted small text-uppercase">Ticket Number</div>
                        <div class="fw-semibold"><?= e($issue['ticket_number']) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Current Status</div>
                        <div class="fw-semibold"><?= e(issue_status_label((string) $issue['status'])) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Priority</div>
                        <div class="fw-semibold"><?= e(issue_priority_label((string) ($issue['priority'] ?? 'medium'))) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Assigned To</div>
                        <div class="fw-semibold"><?= e($issue['assigned_name'] ?? 'Unassigned') ?></div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Last Updated</div>
                        <div class="fw-semibold"><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($notifications) : ?>
                <div class="app-card bg-white p-4 mb-4">
                    <h2 class="h5 mb-3">Recent Notifications</h2>
                    <div class="d-grid gap-3">
                        <?php foreach ($notifications as $notification) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="small text-muted mb-1"><?= e(date('d M Y, H:i', strtotime((string) $notification['created_at']))) ?></div>
                                <div><?= e($notification['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canManage) : ?>
                <div class="app-card bg-white p-4">
                    <h2 class="h5 mb-3">Workflow Update</h2>
                    <form method="post" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_workflow">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status" required>
                                <?php foreach (issue_status_options() as $statusOption) : ?>
                                    <option value="<?= e($statusOption['status_key']) ?>" <?= ((string) $issue['status'] === (string) $statusOption['status_key']) ? 'selected' : '' ?>>
                                        <?= e($statusOption['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" name="priority" id="priority" required>
                                <?php foreach (issue_priority_catalog() as $priorityKey => $priorityLabel) : ?>
                                    <option value="<?= e($priorityKey) ?>" <?= ((string) ($issue['priority'] ?? 'medium') === (string) $priorityKey) ? 'selected' : '' ?>>
                                        <?= e($priorityLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($role === 'admin') : ?>
                            <div class="mb-3">
                                <label for="assigned_to" class="form-label">Assign To</label>
                                <select class="form-select" name="assigned_to" id="assigned_to">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staffMembers as $staffMember) : ?>
                                        <option value="<?= e((string) $staffMember['id']) ?>" <?= ((int) ($issue['assigned_to'] ?? 0) === (int) $staffMember['id']) ? 'selected' : '' ?>>
                                            <?= e($staffMember['full_name']) ?> (<?= e($staffMember['role_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="assigned_to" value="<?= e((string) ($issue['assigned_to'] ?? $user['id'])) ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="resolution_notes" class="form-label">Resolution Notes</label>
                            <textarea class="form-control" name="resolution_notes" id="resolution_notes" rows="4" placeholder="Add follow-up notes or resolution details"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="workflow_comment" class="form-label">Progress Comment</label>
                            <textarea class="form-control" name="comment" id="workflow_comment" rows="4" placeholder="Add progress notes for the conversation thread"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save Workflow Update</button>
                    </form>
                </div>
            <?php else : ?>
                <div class="app-card bg-white p-4">
                    <h2 class="h5 mb-3">Citizen Actions</h2>
                    <p class="text-muted mb-0">You can comment on this issue, confirm the resolution when it is fixed, or reopen it if the problem persists.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>