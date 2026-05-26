<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);

$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
$type = strtolower(trim((string) ($_GET['type'] ?? 'issues')));
$filters = admin_normalize_filters($_GET);
$summary = admin_fetch_report_summary($filters);
$rows = admin_fetch_report_rows($filters);

if ($type !== 'issues') {
    $type = 'issues';
}

if ($format === 'csv') {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            $row['ticket_number'] ?? '',
            $row['title'] ?? '',
            $row['category_name'] ?? '',
            $row['status'] ?? '',
            $row['priority'] ?? '',
            $row['location'] ?? '',
            $row['reporter_name'] ?? '',
            $row['assigned_name'] ?? '',
            $row['created_at'] ?? '',
        ];
    }

    admin_export_csv('civic-issues-report.csv', ['Ticket', 'Title', 'Category', 'Status', 'Priority', 'Location', 'Reporter', 'Assignee', 'Created At'], $csvRows);
    exit;
}

$reportTitle = APP_NAME . ' Issue Report';
$reportDate = date('Y-m-d H:i:s');
$htmlRows = '';
foreach (array_slice($rows, 0, 50) as $row) {
    $htmlRows .= '<tr>'
        . '<td>' . e((string) ($row['ticket_number'] ?? '')) . '</td>'
        . '<td>' . e((string) ($row['title'] ?? '')) . '</td>'
        . '<td>' . e((string) ($row['category_name'] ?? '')) . '</td>'
        . '<td>' . e(issue_status_label((string) ($row['status'] ?? ''))) . '</td>'
        . '<td>' . e(issue_priority_label((string) ($row['priority'] ?? 'medium'))) . '</td>'
        . '<td>' . e((string) ($row['location'] ?? '')) . '</td>'
        . '<td>' . e((string) ($row['reporter_name'] ?? '')) . '</td>'
        . '<td>' . e((string) ($row['assigned_name'] ?? 'Unassigned')) . '</td>'
        . '</tr>';
}

$html = '<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;color:#1f2d1f;margin:28px}
h1,h2{margin:0 0 8px}
.meta{color:#6c757d;font-size:12px;margin-bottom:20px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #cfd8cf;padding:8px;text-align:left;vertical-align:top}
th{background:#f3f7f3}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}
.card{border:1px solid #cfd8cf;border-radius:8px;padding:12px}
.card strong{display:block;font-size:22px;margin-top:6px}
</style></head><body>'
    . '<h1>' . e($reportTitle) . '</h1>'
    . '<div class="meta">Generated at ' . e($reportDate) . '</div>'
    . '<div class="summary">'
    . '<div class="card">Total<strong>' . e((string) $summary['total_issues']) . '</strong></div>'
    . '<div class="card">Open<strong>' . e((string) $summary['open_issues']) . '</strong></div>'
    . '<div class="card">Closed<strong>' . e((string) $summary['closed_issues']) . '</strong></div>'
    . '<div class="card">Avg Resolution<strong>' . ($summary['avg_resolution_minutes'] !== null ? e(number_format((float) $summary['avg_resolution_minutes'], 1)) . ' min' : 'N/A') . '</strong></div>'
    . '</div>'
    . '<table><thead><tr><th>Ticket</th><th>Title</th><th>Category</th><th>Status</th><th>Priority</th><th>Location</th><th>Reporter</th><th>Assignee</th></tr></thead><tbody>'
    . $htmlRows
    . '</tbody></table></body></html>';

if (class_exists(\Dompdf\Dompdf::class)) {
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('civic-issues-report.pdf', ['Attachment' => true]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;