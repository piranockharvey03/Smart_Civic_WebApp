<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['department_manager']);

$user = current_user();
$role = current_user_role();
$departmentId = department_current_user_department_id($user);

$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'category_id' => trim((string) ($_GET['category_id'] ?? '')),
    'division' => trim((string) ($_GET['division'] ?? '')),
    'query' => trim((string) ($_GET['query'] ?? '')),
];

$categories = issue_category_options();
$divisions = issue_division_options();
$divisionBreakdown = issue_fetch_division_breakdown((int) $user['id'], (string) $role, 8);
$pageTitle = APP_NAME . ' | Department Issue Map';
$activePage = 'department-manager-map';
$pageStyles = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
    'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
    'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
    app_url('assets/css/maps.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/maps.css'),
];
$pageScripts = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
    'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
    'https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js',
    app_url('assets/js/maps/geolocation.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/geolocation.js'),
    app_url('assets/js/maps/heatmap.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/heatmap.js'),
    app_url('assets/js/maps/admin-map.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/admin-map.js'),
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Department Issue Locations</p>
                        <h1 class="h3 mb-2">Department Map</h1>
                        <p class="mb-0 text-muted">View civic issues routed to your department on a spatial map and open tickets directly.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e(app_url('department-manager/dashboard.php')) ?>" class="btn btn-outline-primary">Dashboard</a>
                        <a href="<?= e(app_url('department-manager/issues.php')) ?>" class="btn btn-primary">Department Issues</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="app-card bg-white p-3 p-lg-4">
                <form id="issueMapFilters" method="get" action="" class="row g-3 align-items-end mb-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="query">Search</label>
                        <input type="text" class="form-control" id="query" name="query" value="<?= e($filters['query']) ?>" placeholder="Ticket, title, or location">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (issue_status_options() as $status) : ?>
                                <option value="<?= e($status['status_key']) ?>" <?= $filters['status'] === $status['status_key'] ? 'selected' : '' ?>><?= e($status['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All</option>
                            <?php foreach (issue_priority_catalog() as $priorityKey => $priorityLabel) : ?>
                                <option value="<?= e($priorityKey) ?>" <?= $filters['priority'] === $priorityKey ? 'selected' : '' ?>><?= e($priorityLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="division">Division</label>
                        <select class="form-select" id="division" name="division">
                            <option value="">All</option>
                            <?php foreach ($divisions as $division) : ?>
                                <option value="<?= e($division) ?>" <?= $filters['division'] === $division ? 'selected' : '' ?>><?= e($division) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>

                <div class="map-toolbar mb-3">
                    <div class="btn-group" role="group" aria-label="Map view toggle">
                        <button type="button" class="btn btn-outline-primary active" data-map-view="markers">Markers</button>
                        <button type="button" class="btn btn-outline-primary" data-map-view="heat">Heatmap</button>
                    </div>
                    <div class="small text-muted" data-map-status>Loading issue locations...</div>
                </div>

                <div class="map-canvas map-canvas--dashboard" id="adminIssueMap" data-map-source="<?= e(app_url('issues/map-data.php?role=' . urlencode((string) $role))) ?>" data-map-role="<?= e((string) $role) ?>" data-map-filters="issueMapFilters"></div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="app-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Division Snapshot</h2>
                <div class="d-grid gap-2 compact-stack">
                    <?php foreach ($divisionBreakdown as $division) : ?>
                        <div class="border rounded-3 p-3">
                            <div class="fw-semibold mb-1"><?= e($division['division_name']) ?></div>
                            <div class="small text-muted mb-1">Open: <?= e((string) $division['open_count']) ?> | Resolved: <?= e((string) $division['resolved_count']) ?></div>
                            <span class="issue-badge secondary"><?= e((string) $division['issue_count']) ?> issues</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$divisionBreakdown) : ?>
                        <div class="alert alert-info mb-0">No department issues with coordinates are available yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card bg-white p-4">
                <h2 class="h5 mb-3">Legend</h2>
                <div class="d-grid gap-2">
                    <div class="d-flex align-items-center gap-2"><span class="map-legend-dot danger"></span> Critical / Open</div>
                    <div class="d-flex align-items-center gap-2"><span class="map-legend-dot warning"></span> In Progress</div>
                    <div class="d-flex align-items-center gap-2"><span class="map-legend-dot success"></span> Resolved</div>
                    <div class="small text-muted mt-2">Marker clusters reduce overcrowding. Heatmap highlights location density across your department.</div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
