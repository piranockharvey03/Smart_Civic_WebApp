<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$issueId = (int) ($_GET['id'] ?? 0);
$issue = $issueId > 0 ? issue_fetch_issue_by_id($issueId) : null;

if (!$issue || empty($issue['image'])) {
    http_response_code(404);
    exit;
}

$role = current_user_role();
$user = current_user();
$isCitizenOwner = $role === 'citizen' && (int) $issue['user_id'] === (int) $user['id'];
$isAssignedStaff = $role === 'staff' && (int) ($issue['assigned_to'] ?? 0) === (int) $user['id'];
$canManage = in_array((string) $role, ['staff', 'admin'], true);

if (($role === 'citizen' && !$isCitizenOwner) || ($role === 'staff' && !$isAssignedStaff && !$canManage)) {
    http_response_code(403);
    exit;
}

$filename = basename((string) $issue['image']);
$filePath = issue_upload_directory() . '/' . $filename;

if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);