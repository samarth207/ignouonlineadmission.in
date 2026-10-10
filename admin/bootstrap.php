<?php
declare(strict_types=1);

const BLOG_ADMIN_ROOT = __DIR__;
const BLOG_ADMIN_BASE = '/admin';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function blog_db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = dirname(__DIR__, 2) . '/ignou-db.php';
    $config = is_file($configPath) ? require $configPath : [];
    $password = $config['password'] ?? getenv('IGNOU_DB_PASSWORD');
    if (!$password) {
        throw new RuntimeException('Database credentials are not configured.');
    }

    $host = $config['host'] ?? (getenv('IGNOU_DB_HOST') ?: 'localhost');
    $database = $config['database'] ?? 'u261758575_ignou';
    $user = $config['user'] ?? 'u261758575_ignou';
    $pdo = new PDO(
        'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4',
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    return $pdo;
}

function blog_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function blog_csrf_token(): string
{
    if (empty($_SESSION['blog_admin_csrf'])) {
        $_SESSION['blog_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['blog_admin_csrf'];
}

function blog_require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || empty($_SESSION['blog_admin_csrf']) || !hash_equals($_SESSION['blog_admin_csrf'], $token)) {
        http_response_code(403);
        exit('Request expired. Refresh the page and try again.');
    }
}

function blog_current_admin(): ?array
{
    $id = $_SESSION['blog_admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $query = blog_db()->prepare('SELECT id, username, full_name, role FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    $query->execute([(int) $id]);
    $admin = $query->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

function blog_require_admin(): array
{
    $admin = blog_current_admin();
    if (!$admin) {
        header('Location: ' . BLOG_ADMIN_BASE . '/login.php');
        exit;
    }
    return $admin;
}