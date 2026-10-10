<?php
declare(strict_types=1);
// Execute ONLY from the CLI after configuring a trusted MySQL connection:
// ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='long-unique-password' php tools/create_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
require_once __DIR__ . '/../config/db.php';
$email = trim((string) getenv('ADMIN_EMAIL'));
$password = (string) getenv('ADMIN_PASSWORD');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Set ADMIN_EMAIL and ADMIN_PASSWORD (minimum 12 characters).\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$first = 'Store';
$last = 'Administrator';
$role = 'admin';
$stmt = $conn->prepare('INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, NULL, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)');
$stmt->bind_param('sssss', $first, $last, $email, $hash, $role);
$stmt->execute();
echo "Admin account configured. Remove ADMIN_PASSWORD from your shell history/environment.\n";
