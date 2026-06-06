<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

// Ensure we set a known session id before bootstrap starts session (use allowed chars)
session_id('sess-' . bin2hex(random_bytes(8)));

require_once __DIR__ . '/../config/bootstrap.php';

echo "Starting logout-other test\n";

// Find or create a test user
$roleStmt = db()->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
$roleStmt->execute(['name' => 'citizen']);
$role = $roleStmt->fetch();
$roleId = $role ? (int) $role['id'] : 1;

$email = 'test-logout+' . bin2hex(random_bytes(4)) . '@example.local';

$create = db()->prepare('INSERT INTO users (full_name, email, password, role_id, is_active) VALUES (:full_name, :email, :password, :role_id, 1)');
$create->execute([
    'full_name' => 'Test Logout User',
    'email' => $email,
    'password' => password_hash('Password123!', PASSWORD_DEFAULT),
    'role_id' => $roleId,
]);

$userId = (int) db()->lastInsertId();

echo "Created test user id={$userId}\n";

// Insert sessions: one current, two others
$currentSession = session_id();

$insertSession = db()->prepare('INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (:user_id, :session_id, :ip_address, :user_agent)');
$insertSession->execute(['user_id' => $userId, 'session_id' => $currentSession, 'ip_address' => '127.0.0.1', 'user_agent' => 'cli-test/1']);
$insertSession->execute(['user_id' => $userId, 'session_id' => $currentSession . '-other1', 'ip_address' => '127.0.0.2', 'user_agent' => 'cli-test/2']);
$insertSession->execute(['user_id' => $userId, 'session_id' => $currentSession . '-other2', 'ip_address' => '127.0.0.3', 'user_agent' => 'cli-test/3']);

$countSessions = (int) db()->query('SELECT COUNT(*) AS cnt FROM user_sessions WHERE user_id = ' . $userId)->fetch()['cnt'];
echo "User sessions before revoke: {$countSessions}\n";

// Insert remember tokens if table exists
$tokensTable = false;
try {
    $tokensTable = (bool) db()->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_remember_tokens'")->fetch();
} catch (Throwable) {
    $tokensTable = false;
}

if ($tokensTable) {
    $ins = db()->prepare('INSERT INTO user_remember_tokens (user_id, selector, token_hash, ip_address, user_agent, expires_at) VALUES (:user_id, :selector, :token_hash, :ip_address, :user_agent, :expires_at)');
    $ins->execute(['user_id' => $userId, 'selector' => bin2hex(random_bytes(16)), 'token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'ip_address' => '127.0.0.1', 'user_agent' => 'cli-test', 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
    $ins->execute(['user_id' => $userId, 'selector' => bin2hex(random_bytes(16)), 'token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'ip_address' => '127.0.0.2', 'user_agent' => 'cli-test', 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);

    $countTokens = (int) db()->query('SELECT COUNT(*) AS cnt FROM user_remember_tokens WHERE user_id = ' . $userId)->fetch()['cnt'];
    echo "Remember tokens before revoke: {$countTokens}\n";
} else {
    echo "Remember tokens table not present; skipping tokens test.\n";
}

// Simulate logged-in session in PHP
$_SESSION['user'] = ['id' => $userId, 'full_name' => 'Test Logout User', 'role' => 'citizen', 'role_id' => $roleId];

// Call revoke_other_devices()
$result = revoke_other_devices();

echo "Revoke result: sessions={$result['sessions']}, tokens={$result['tokens']}\n";

$countSessionsAfter = (int) db()->query('SELECT COUNT(*) AS cnt FROM user_sessions WHERE user_id = ' . $userId)->fetch()['cnt'];
echo "User sessions after revoke: {$countSessionsAfter}\n";

if ($tokensTable) {
    $countTokensAfter = (int) db()->query('SELECT COUNT(*) AS cnt FROM user_remember_tokens WHERE user_id = ' . $userId . ' AND revoked_at IS NULL')->fetch()['cnt'];
    echo "Remember tokens after revoke (not revoked): {$countTokensAfter}\n";
}

echo "Test complete.\n";
