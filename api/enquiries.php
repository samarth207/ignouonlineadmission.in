<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_start(['cookie_httponly' => true, 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['enquiry_csrf'])) {
        $_SESSION['enquiry_csrf'] = bin2hex(random_bytes(32));
    }
    respond(200, ['csrf' => $_SESSION['enquiry_csrf']]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['message' => 'Method not allowed.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    respond(413, ['message' => 'Request too large.']);
}
$fields = ['csrf', 'name', 'email', 'phone', 'course', 'page', 'consent', 'website'];
foreach ($fields as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
        respond(422, ['message' => 'Please complete all required fields.']);
    }
}
if (empty($_SESSION['enquiry_csrf']) || !hash_equals($_SESSION['enquiry_csrf'], $_POST['csrf'])) {
    respond(403, ['message' => 'Your session expired. Please try again.']);
}
if ($_POST['website'] !== '') {
    respond(422, ['message' => 'Unable to accept this enquiry.']);
}
if (time() - ($_SESSION['enquiry_last_submit'] ?? 0) < 60) {
    respond(429, ['message' => 'Please wait a minute before submitting another enquiry.']);
}
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$course = trim($_POST['course']);
$page = trim($_POST['page']);
if ($name === '' || strlen($name) > 480 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || !preg_match('/^[+0-9() .-]{7,30}$/D', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7 || $course === '' || strlen($course) > 1000 || strlen($page) > 2000 || substr($page, 0, 1) !== '/' || $_POST['consent'] !== '1') {
    respond(422, ['message' => 'Please enter a valid name, email, phone and programme, and give consent.']);
}
try {
    $configPath = dirname(__DIR__, 2) . '/ignou-db.php';
    $config = is_file($configPath) ? require $configPath : [];
    $password = $config['password'] ?? getenv('IGNOU_DB_PASSWORD');
    if (!$password) {
        throw new RuntimeException('Database password is not configured.');
    }
    $host = $config['host'] ?? (getenv('IGNOU_DB_HOST') ?: 'localhost');
    $database = $config['database'] ?? 'u261758575_ignou';
    $user = $config['user'] ?? 'u261758575_ignou';
    $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $statement = $pdo->prepare('INSERT INTO programme_enquiries (name, email, phone, course, source_page, consent_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
    $statement->execute([$name, $email, $phone, $course, $page]);
    $_SESSION['enquiry_last_submit'] = time();
    respond(201, ['message' => 'Enquiry received.']);
} catch (Throwable $error) {
    error_log('Programme enquiry storage failed: ' . $error->getMessage());
    respond(503, ['message' => 'Enquiries are temporarily unavailable. Please try again later.']);
}