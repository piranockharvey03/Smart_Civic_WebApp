<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$query = trim((string) ($_GET['q'] ?? ''));
$results = $query !== '' ? admin_fetch_global_search($query, 50) : [];

$pageTitle = APP_NAME . ' | Global Search';
$activePage = 'admin-search';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Global Search</p>
                <h1 class="h2 mb-2">Search across issues, users, comments, and notes</h1>
                <p class="mb-0">Fast lookups for operational review and administration.</p>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label">Search term</label>
                        <input type="text" name="q" value="<?= e($query) ?>" class="form-control" placeholder="Ticket number, user, category, comment, location, or note">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Primary</th>
                                <th>Secondary</th>
                                <th>Category/Role</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result) : ?>
                                <tr>
                                    <td><span class="issue-badge secondary"><?= e((string) $result['result_type']) ?></span></td>
                                    <td><?= e((string) $result['primary_label']) ?></td>
                                    <td><?= e((string) $result['secondary_label']) ?></td>
                                    <td><?= e((string) $result['meta_label']) ?></td>
                                    <td><?= e((string) $result['location_label']) ?></td>
                                    <td><?= e((string) $result['status_label']) ?></td>
                                    <td><?= e((string) ($result['notes_label'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>