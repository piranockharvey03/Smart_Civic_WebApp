<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$filters = admin_normalize_filters($_GET);
$summary = admin_fetch_report_summary($filters);

[$monthLabels, $monthValues] = admin_json_chart_labels($summary['monthly_trend'], 'month_label', 'issue_count');
[$categoryLabels, $categoryValues] = admin_json_chart_labels($summary['category_breakdown'], 'name', 'issue_count');
[$locationLabels, $locationValues] = admin_json_chart_labels($summary['location_breakdown'], 'location', 'issue_count');
[$priorityLabels, $priorityValues] = admin_json_chart_labels($summary['priority_breakdown'], 'priority', 'issue_count');

$pageTitle = APP_NAME . ' | Analytics Dashboard';
$activePage = 'admin-analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<section class="container-fluid">
    <div class="row g-4">
        <div class="col-12">
            <div class="app-card issue-panel compact-card p-4 p-lg-4">
                <p class="text-uppercase small text-muted mb-2">Analytics Dashboard</p>
                <h1 class="h2 mb-2">Operational trends and service performance</h1>
                <p class="mb-0">Visual reporting for issue volume, response patterns, and civic service pressure.</p>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Total Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['total_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Open Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['open_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Closed Issues</div>
                <div class="card-value mt-2"><?= e((string) $summary['closed_issues']) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="app-card bg-white compact-card h-100">
                <div class="card-kicker">Avg Resolution</div>
                <div class="card-value mt-2"><?= $summary['avg_resolution_minutes'] !== null ? e(number_format((float) $summary['avg_resolution_minutes'], 1)) . ' min' : 'N/A' ?></div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card bg-white compact-card h-100">
                <h2 class="h5 mb-3">Monthly Issue Trends</h2>
                <canvas id="monthlyTrendChart" height="140"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card bg-white compact-card h-100">
                <h2 class="h5 mb-3">Category Distribution</h2>
                <canvas id="categoryChart" height="180"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="app-card bg-white compact-card h-100">
                <h2 class="h5 mb-3">Priority Distribution</h2>
                <canvas id="priorityChart" height="180"></canvas>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="app-card bg-white compact-card h-100">
                <h2 class="h5 mb-3">Location Pressure</h2>
                <canvas id="locationChart" height="140"></canvas>
            </div>
        </div>
        <div class="col-12">
            <div class="app-card bg-white compact-card">
                <h2 class="h5 mb-3">Top Staff Performance</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Assigned</th>
                                <th>Resolved</th>
                                <th>Avg Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['staff_performance'] as $staff) : ?>
                                <tr>
                                    <td><?= e($staff['full_name']) ?></td>
                                    <td><?= e((string) $staff['assigned_count']) ?></td>
                                    <td><?= e((string) $staff['resolved_count']) ?></td>
                                    <td><?= $staff['avg_resolution_minutes'] !== null ? e(number_format((float) $staff['avg_resolution_minutes'], 1)) . ' min' : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const monthlyLabels = <?= json_encode($monthLabels, JSON_UNESCAPED_SLASHES) ?>;
const monthlyValues = <?= json_encode($monthValues, JSON_UNESCAPED_SLASHES) ?>;
const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_SLASHES) ?>;
const categoryValues = <?= json_encode($categoryValues, JSON_UNESCAPED_SLASHES) ?>;
const priorityLabels = <?= json_encode($priorityLabels, JSON_UNESCAPED_SLASHES) ?>;
const priorityValues = <?= json_encode($priorityValues, JSON_UNESCAPED_SLASHES) ?>;
const locationLabels = <?= json_encode($locationLabels, JSON_UNESCAPED_SLASHES) ?>;
const locationValues = <?= json_encode($locationValues, JSON_UNESCAPED_SLASHES) ?>;

const themeColors = {
    green: 'rgba(31, 122, 61, 0.85)',
    greenSoft: 'rgba(31, 122, 61, 0.2)',
    gold: 'rgba(246, 196, 69, 0.9)',
    red: 'rgba(198, 40, 40, 0.85)',
    blue: 'rgba(13, 110, 253, 0.85)',
    teal: 'rgba(13, 202, 240, 0.85)',
    gray: 'rgba(108, 117, 125, 0.8)'
};

new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: { labels: monthlyLabels, datasets: [{ label: 'Issues', data: monthlyValues, borderColor: themeColors.green, backgroundColor: themeColors.greenSoft, tension: 0.35, fill: true }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: { labels: categoryLabels, datasets: [{ data: categoryValues, backgroundColor: [themeColors.green, themeColors.gold, themeColors.red, themeColors.blue, themeColors.teal, themeColors.gray] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('priorityChart'), {
    type: 'bar',
    data: { labels: priorityLabels, datasets: [{ label: 'Issues', data: priorityValues, backgroundColor: themeColors.blue }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('locationChart'), {
    type: 'bar',
    data: { labels: locationLabels, datasets: [{ label: 'Issues', data: locationValues, backgroundColor: themeColors.green }] },
    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>