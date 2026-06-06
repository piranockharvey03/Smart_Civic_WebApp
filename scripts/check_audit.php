<?php

require_once __DIR__ . '/../config/bootstrap.php';

if ($argc < 2) {
    echo "Usage: php check_audit.php <user_id>\n";
    exit(1);
}
$userId = (int)$argv[1];
$st = db()->prepare('SELECT COUNT(*) AS cnt FROM auth_audit_logs WHERE user_id = :uid AND action = :action');
$st->execute(['uid' => $userId, 'action' => 'revoke_other_devices']);
$row = $st->fetch();
$cnt = $row ? (int)$row['cnt'] : 0;
echo "Audit rows for user {$userId}: {$cnt}\n";
