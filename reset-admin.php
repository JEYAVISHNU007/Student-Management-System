<?php
require 'config.php';
header('Content-Type: text/plain; charset=utf-8');
$token = $_GET['token'] ?? '';
$expected = 'reset-campus-admin-2026';
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    exit("Reset blocked. Open this file with the setup token shown in README.md.");
}
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $db->prepare("SELECT id FROM users WHERE username='admin' LIMIT 1");
$stmt->execute();
if ($stmt->fetch()) {
    $db->prepare("UPDATE users SET password_hash=?, role='admin' WHERE username='admin'")->execute([$hash]);
} else {
    $db->prepare("INSERT INTO users(username,password_hash,role) VALUES('admin',?,'admin')")->execute([$hash]);
}
echo "Admin password reset successfully. Login with admin / admin123. Delete reset-admin.php now.";
