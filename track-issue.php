<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$ticketNumber = trim((string) ($_GET['ticket'] ?? $_POST['ticket'] ?? ''));
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$issue = null;
$error = null;
$timeline = ['items' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif ($ticketNumber === '' || $email === '') {
        $error = 'Enter both your ticket number and the email used when reporting.';
    } else {
        $issue = issue_fetch_trackable_issue($ticketNumber, $email);
        if (!$issue) {
            $error = 'No matching ticket was found for that email address. Check your details and try again.';
        } else {
            $timeline = issue_fetch_issue_timeline((int) $issue['id'], 1, 10);
            $timeline['items'] = array_values(array_filter(
                $timeline['items'],
                static fn (array $entry): bool => ($entry['entry_type'] ?? '') === 'log'
                    || !empty($entry['is_public'])
            ));
        }
    }
} elseif ($ticketNumber !== '' && $email !== '') {
    $issue = issue_fetch_trackable_issue($ticketNumber, $email);
    if ($issue) {
        $timeline = issue_fetch_issue_timeline((int) $issue['id'], 1, 10);
        $timeline['items'] = array_values(array_filter(
            $timeline['items'],
            static fn (array $entry): bool => ($entry['entry_type'] ?? '') === 'log'
                || !empty($entry['is_public'])
        ));
    }
}

$pageTitle = APP_NAME . ' | Track Issue';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-lg-5">
            <div class="app-card bg-white p-4 p-lg-5">
                <p class="text-uppercase small text-muted mb-2">Public Ticket Tracking</p>
                <h1 class="h3 mb-2">Track your issue</h1>
                <p class="text-muted mb-4">Enter your KCCA ticket number and the email address on your account to view current status.</p>

                <?php if ($error) : ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="ticket" class="form-label">Ticket number</label>
                        <input type="text" class="form-control form-control-lg" id="ticket" name="ticket" value="<?= e($ticketNumber) ?>" placeholder="KCCA-2026-0001" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= e($email) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Look Up Ticket</button>
                </form>

                <div class="mt-4 small text-muted">
                    Need full access to comments and history?
                    <a href="<?= e(app_url('auth/citizen-login.php')) ?>">Sign in to your citizen account</a>
                    or <a href="<?= e(app_url('auth/register.php')) ?>">create one</a>.
                </div>
            </div>
        </div>

        <?php if ($issue) : ?>
            <div class="col-lg-7">
                <div class="app-card bg-white p-4 p-lg-5 mb-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                        <div>
                            <div class="text-uppercase small text-muted mb-1">Ticket</div>
                            <h2 class="h4 mb-1"><?= e((string) $issue['ticket_number']) ?></h2>
                            <div class="text-muted"><?= e((string) $issue['title']) ?></div>
                        </div>
                        <div class="text-md-end">
                            <span class="issue-badge <?= e(issue_status_badge_class((string) $issue['status'])) ?>"><?= e(issue_status_label((string) $issue['status'])) ?></span>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase">Category</div>
                            <div class="fw-semibold"><?= e((string) $issue['category_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase">Location</div>
                            <div class="fw-semibold"><?= e((string) $issue['location']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase">Submitted</div>
                            <div class="fw-semibold"><?= e(date('d M Y, H:i', strtotime((string) $issue['created_at']))) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small text-uppercase">Last updated</div>
                            <div class="fw-semibold"><?= e(date('d M Y, H:i', strtotime((string) $issue['updated_at']))) ?></div>
                        </div>
                    </div>

                    <?php if (is_logged_in() && current_user_role() === 'citizen' && (int) current_user()['id'] === (int) $issue['user_id']) : ?>
                        <a class="btn btn-outline-primary" href="<?= e(app_url('issues/view.php?id=' . (int) $issue['id'])) ?>">Open full issue details</a>
                    <?php endif; ?>
                </div>

                <div class="app-card bg-white p-4 p-lg-5">
                    <h3 class="h5 mb-3">Recent activity</h3>
                    <?php if (!$timeline['items']) : ?>
                        <p class="text-muted mb-0">No public activity has been recorded yet.</p>
                    <?php else : ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($timeline['items'] as $entry) : ?>
                                <div class="border rounded-3 p-3">
                                    <div class="small text-muted mb-1"><?= e(date('d M Y, H:i', strtotime((string) $entry['created_at']))) ?></div>
                                    <div class="fw-semibold mb-1">
                                        <?php if (($entry['entry_type'] ?? '') === 'log') : ?>
                                            <?= e(issue_log_action_label((string) ($entry['action'] ?? 'status_updated'))) ?>
                                        <?php else : ?>
                                            <?= e((string) ($entry['author_name'] ?? 'Update')) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div><?= nl2br(e((string) ($entry['message'] ?? ''))) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
