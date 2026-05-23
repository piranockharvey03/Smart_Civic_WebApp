<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$role = current_user_role();
$user = current_user();
$issueId = (int) ($_GET['id'] ?? 0);
$ticketNumber = trim((string) ($_GET['ticket'] ?? ''));

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

if ($role === 'citizen' && (int) $issue['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
}

$allowedUpdateRoles = ['staff', 'admin'];
$canManage = in_array((string) $role, $allowedUpdateRoles, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid security token. Please refresh and try again.');
    } else {
        $status = trim((string) ($_POST['status'] ?? ''));
        $assignedTo = trim((string) ($_POST['assigned_to'] ?? ''));
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $validStatuses = array_keys(issue_status_catalog());

        if (!in_array($status, $validStatuses, true)) {
            set_flash('error', 'Select a valid issue status.');
        } else {
            $assignedId = $assignedTo !== '' ? (int) $assignedTo : null;

            if ($assignedId !== null) {
                $staffStmt = db()->prepare("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND r.name IN ('staff', 'admin') LIMIT 1");
                $staffStmt->execute(['id' => $assignedId]);
                if (!$staffStmt->fetch()) {
                    $assignedId = null;
                }
            }

            try {
                issue_update_workflow((int) $issue['id'], $status, $assignedId, (int) $user['id'], $comment !== '' ? $comment : null);
                set_flash('success', 'Issue workflow updated successfully.');
                redirect(app_url('issues/view.php?id=' . (int) $issue['id']));
            } catch (Throwable) {
                set_flash('error', 'The issue could not be updated right now.');
            }
        }
    }
}

$issue = issue_fetch_issue_by_id((int) $issue['id']) ?? $issue;
$comments = issue_fetch_comments((int) $issue['id']);
$staffMembers = $canManage ? issue_fetch_staff_members() : [];
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
                        <div class="mb-2"><span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span></div>
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
                        <div class="small text-muted">Division: <?= e($issue['reporter_division'] ?? 'Not provided') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1">Description</div>
                        <p class="mb-0"><?= nl2br(e($issue['description'])) ?></p>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-2">Uploaded Photo</div>
                        <?php if (!empty($issue['image'])) : ?>
                            <img class="img-fluid rounded-3 border" src="<?= e(issue_upload_url((string) $issue['image']) ?? '') ?>" alt="Issue photo">
                        <?php else : ?>
                            <div class="alert alert-light mb-0">No image uploaded for this ticket.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="app-card bg-white p-4">
                <div class="section-header">
                    <div>
                        <h2 class="h5 mb-1">Comments and Timeline</h2>
                        <p class="text-muted mb-0">Status updates and response notes appear here.</p>
                    </div>
                </div>

                <?php if (!$comments) : ?>
                    <div class="alert alert-info">No comments have been added yet.</div>
                <?php else : ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($comments as $comment) : ?>
                            <div class="border rounded-3 p-3">
                                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="fw-semibold"><?= e($comment['author_name']) ?> <span class="text-muted small">(<?= e($comment['author_role']) ?>)</span></div>
                                    <div class="small text-muted"><?= e(date('d M Y, H:i', strtotime((string) $comment['created_at']))) ?></div>
                                </div>
                                <div><?= nl2br(e($comment['comment'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
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
                        <div class="text-muted small text-uppercase">Assigned To</div>
                        <div class="fw-semibold"><?= e($issue['assigned_name'] ?? 'Unassigned') ?></div>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Last Updated</div>
                        <div class="fw-semibold"><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($canManage) : ?>
                <div class="app-card bg-white p-4">
                    <h2 class="h5 mb-3">Update Issue</h2>
                    <form method="post" action="">
                        <?= csrf_field() ?>
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
                        <div class="mb-3">
                            <label for="comment" class="form-label">Add Update / Comment</label>
                            <textarea class="form-control" name="comment" id="comment" rows="5" placeholder="Enter progress notes or response details"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save Update</button>
                    </form>
                </div>
            <?php else : ?>
                <div class="app-card bg-white p-4">
                    <h2 class="h5 mb-3">Admin Response</h2>
                    <p class="text-muted mb-0">KCCA staff can update the status, assign this issue, and add response notes from the management console.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>