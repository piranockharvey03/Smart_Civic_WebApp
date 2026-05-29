<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['citizen']);

$errors = [];
$data = [
    'category_id' => '',
    'title' => '',
    'description' => '',
    'division' => '',
    'location' => '',
    'address' => '',
    'latitude' => '',
    'longitude' => '',
];

$categories = issue_category_options();
$divisions = issue_division_options();
$pageStyles = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',
    'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
    'https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
    app_url('assets/css/maps.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/maps.css'),
];
$pageScripts = [
    'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
    app_url('assets/js/maps/geolocation.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/geolocation.js'),
    app_url('assets/js/maps/issue-map.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/maps/issue-map.js'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $data['category_id'] = (string) ($_POST['category_id'] ?? '');
        $data['title'] = trim((string) ($_POST['title'] ?? ''));
        $data['description'] = trim((string) ($_POST['description'] ?? ''));
        $data['division'] = trim((string) ($_POST['division'] ?? ''));
        $data['location'] = trim((string) ($_POST['location'] ?? ''));
        $data['address'] = trim((string) ($_POST['address'] ?? ''));
        $data['latitude'] = trim((string) ($_POST['latitude'] ?? ''));
        $data['longitude'] = trim((string) ($_POST['longitude'] ?? ''));

        try {
            $result = issue_create_report((int) current_user()['id'], $data, $_FILES['image'] ?? [], $errors);

            if ($result !== null) {
                clear_old();
                set_flash('success', 'Your issue has been submitted successfully. Ticket ' . $result['ticket_number'] . ' has been generated.');
                redirect(app_url('issues/view.php?id=' . $result['id']));
            }
        } catch (Throwable $throwable) {
            $errors[] = 'The issue could not be submitted right now. Please try again.';
        }
    }

    flash_old($data);
}

$pageTitle = APP_NAME . ' | Submit Issue';
$activePage = 'citizen-report';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$user = current_user();
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel p-4 p-lg-5">
                <div class="section-header mb-0">
                    <div>
                        <p class="text-uppercase small text-muted mb-2">Citizen Service Reporting</p>
                        <h1 class="h3 mb-2">Submit a new civic issue</h1>
                        <p class="mb-0 text-muted">Report a road defect, garbage accumulation, drainage problem, or other public service concern to KCCA.</p>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <div class="small text-muted">Logged in as</div>
                        <div class="fw-semibold"><?= e($user['full_name'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white p-4">
                <?php foreach ($errors as $error) : ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Issue Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?= e((string) $category['id']) ?>" <?= ((string) $category['id'] === old('category_id', $data['category_id'])) ? 'selected' : '' ?>>
                                        <?= e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="division" class="form-label">Division</label>
                            <select class="form-select" id="division" name="division" required>
                                <option value="">Select division</option>
                                <?php foreach ($divisions as $division) : ?>
                                    <option value="<?= e($division) ?>" <?= ($division === old('division', $data['division'])) ? 'selected' : '' ?>>
                                        <?= e($division) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="title" class="form-label">Issue Title</label>
                            <input type="text" class="form-control" id="title" name="title" maxlength="180" value="<?= old('title', $data['title']) ?>" placeholder="Example: Pothole blocking traffic on Jinja Road" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Detailed Description</label>
                            <textarea class="form-control" id="description" name="description" rows="6" placeholder="Describe what happened, the exact location, and any landmarks nearby." required><?= old('description', $data['description']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location Details</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?= old('location', $data['location']) ?>" placeholder="Street, landmark, ward, or block" required>
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">Address / Landmark</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= old('address', $data['address']) ?>" placeholder="Optional address captured from the map picker">
                        </div>
                        <div class="col-md-6">
                            <label for="image" class="form-label">Photo Evidence</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <div class="upload-note mt-2">Accepted files: JPG, PNG, GIF, WEBP. Maximum size: 5 MB.</div>
                        </div>
                    </div>

                    <div class="map-block mt-4 p-3 p-lg-4 border rounded-4" data-map-mode="picker" data-map-center-lat="0.3476" data-map-center-lng="32.5825" data-map-zoom="13">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h5 mb-1">Capture the Issue Location</h2>
                                <p class="text-muted mb-0">Use your current GPS location or click the map to place the pin manually.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-geolocate-btn>
                                    <span class="me-1">Use my location</span>
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" data-geolocate-spinner></span>
                                </button>
                                <div class="small text-muted" data-location-status>Map ready for selection.</div>
                            </div>
                        </div>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-lg-8">
                                <label for="locationSearch" class="form-label">Search a place</label>
                                <input type="text" class="form-control" id="locationSearch" placeholder="Search by street, landmark, or area" data-map-search-input>
                            </div>
                            <div class="col-lg-4 d-grid">
                                <button type="button" class="btn btn-outline-secondary" data-map-search-btn>Search location</button>
                            </div>
                        </div>
                        <div class="map-canvas" id="reportIssueMap" aria-label="Issue location picker"></div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-4">
                                <label class="form-label" for="latitude">Latitude</label>
                                <input type="text" class="form-control" id="latitude" name="latitude" value="<?= old('latitude', $data['latitude']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="longitude">Longitude</label>
                                <input type="text" class="form-control" id="longitude" name="longitude" value="<?= old('longitude', $data['longitude']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="locationCoordinates">Current Pin</label>
                                <input type="text" class="form-control" id="locationCoordinates" value="No coordinates selected yet" readonly data-coordinate-display>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
                        <a href="<?= e(app_url('citizen/issues.php')) ?>" class="btn btn-outline-secondary">Back to My Reports</a>
                        <button type="submit" class="btn btn-primary btn-lg">Submit Issue</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card bg-white p-4 h-100">
                <h2 class="h5 mb-3">Submission Tips</h2>
                <ul class="text-muted mb-0">
                    <li>Use a clear title that explains the problem quickly.</li>
                    <li>Include landmarks so KCCA can locate the issue accurately.</li>
                    <li>Upload one clear photo showing the problem.</li>
                    <li>Use the generated ticket number to follow up later.</li>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>