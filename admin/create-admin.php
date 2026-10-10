<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string) fgets(STDIN));
}

$username = prompt('Admin username: ');
$fullName = prompt('Admin full name: ');
$email = prompt('Admin email (optional): ');
$password = prompt('Password (minimum 12 characters): ');

if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username) || $fullName === '' || strlen($fullName) > 160 || strlen($password) < 12 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    fwrite(STDERR, "Invalid input. Username must be 3-80 characters; password must be at least 12 characters.\n");
    exit(1);
}

try {
    $statement = blog_db()->prepare('INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, 'administrator']);
    fwrite(STDOUT, "Admin account created.\n");
} catch (Throwable $error) {
    error_log('Blog admin provisioning failed: ' . $error->getMessage());
    fwrite(STDERR, "Unable to create admin. Check database setup and ensure the username is unique.\n");
    exit(1);
}