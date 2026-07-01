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
$viewerDepartmentId = function_exists('department_current_user_department_id') ? department_current_user_department_id($user) : null;
$isDepartmentManager = $role === 'department_manager'
    && $viewerDepartmentId !== null
    && isset($issue['department_id'])
    && (int) $issue['department_id'] === $viewerDepartmentId;
$canManage = in_array((string) $role, ['staff', 'admin'], true) || $isDepartmentManager;
$canViewImage = $role === 'admin'
    || $isCitizenOwner
    || ($role === 'staff' && ($isAssignedStaff || $canManage))
    || $isDepartmentManager;

if (!$canViewImage) {
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
