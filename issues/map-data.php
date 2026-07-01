<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['staff', 'department_manager', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$role = current_user_role();

$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'category_id' => trim((string) ($_GET['category_id'] ?? '')),
    'division' => trim((string) ($_GET['division'] ?? '')),
    'query' => trim((string) ($_GET['query'] ?? '')),
    'department_id' => trim((string) ($_GET['department_id'] ?? '')),
];

$issues = issue_fetch_map_issues($filters, (int) $user['id'], (string) $role, 1500);

$payload = [
    'success' => true,
    'count' => count($issues),
    'items' => array_map(static function (array $issue): array {
        return [
            'id' => (int) $issue['id'],
            'ticket_number' => (string) $issue['ticket_number'],
            'title' => (string) $issue['title'],
            'description' => (string) $issue['description'],
            'status' => (string) $issue['status'],
            'priority' => (string) ($issue['priority'] ?? 'medium'),
            'latitude' => isset($issue['latitude']) ? (float) $issue['latitude'] : null,
            'longitude' => isset($issue['longitude']) ? (float) $issue['longitude'] : null,
            'address' => (string) ($issue['address'] ?? ''),
            'location' => (string) ($issue['location'] ?? ''),
            'division' => (string) ($issue['division'] ?? $issue['reporter_division'] ?? ''),
            'category_name' => (string) $issue['category_name'],
            'reporter_name' => (string) $issue['reporter_name'],
            'assigned_name' => (string) ($issue['assigned_name'] ?? ''),
            'department_name' => (string) ($issue['department_name'] ?? ''),
            'created_at' => (string) $issue['created_at'],
            'updated_at' => (string) $issue['updated_at'],
            'heat_weight' => match ((string) ($issue['priority'] ?? 'medium')) {
                'critical' => 1.0,
                'high' => 0.8,
                'medium' => 0.55,
                'low' => 0.35,
                default => 0.5,
            },
            'status_tier' => issue_map_status_tier((string) $issue['status']),
            'issue_url' => issue_detail_url((int) $issue['id'], current_user_role()),
        ];
    }, $issues),
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);