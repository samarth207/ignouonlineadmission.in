<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['blog_admin_id'])) {
    header('Location: ' . BLOG_ADMIN_BASE . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    blog_require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $attempts = (int) ($_SESSION['blog_login_attempts'] ?? 0);
    $lastAttempt = (int) ($_SESSION['blog_login_last_attempt'] ?? 0);
    if ($attempts >= 5 && time() - $lastAttempt < 900) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        if (time() - $lastAttempt >= 900) {
            $attempts = 0;
        }
        try {
            $query = blog_db()->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
            $query->execute([$username]);
            $user = $query->fetch(PDO::FETCH_ASSOC);
            if ($user && strlen($password) <= 1024 && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['blog_admin_id'] = (int) $user['id'];
                $_SESSION['blog_login_attempts'] = 0;
                $_SESSION['blog_admin_csrf'] = bin2hex(random_bytes(32));
                blog_db()->prepare('UPDATE admin_users SET last_login = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $user['id']]);
                header('Location: ' . BLOG_ADMIN_BASE . '/index.php');
                exit;
            }
            $_SESSION['blog_login_attempts'] = $attempts + 1;
            $_SESSION['blog_login_last_attempt'] = time();
            $error = 'The username or password is incorrect.';
        } catch (Throwable $exception) {
            error_log('Blog admin sign-in failed: ' . $exception->getMessage());
            $error = 'Sign-in is temporarily unavailable. Check the CMS database setup.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | IGNOU Blog Desk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="login-page">
    <main class="login-wrap">
        <a class="login-brand" href="../index.html"><img src="../img/logo.png" alt="IGNOU Online Admission"><span><strong>IGNOU</strong><small>EDITORIAL DESK</small></span></a>
        <section class="login-panel">
            <p class="eyebrow">CONTENT ADMINISTRATION</p>
            <h1>Welcome back</h1>
            <p class="login-copy">Sign in to manage stories and updates.</p>
            <?php if ($error !== ''): ?><p class="alert alert-danger" role="alert"><?= blog_escape($error) ?></p><?php endif; ?>
            <form method="post" action="login.php">
                <input type="hidden" name="csrf" value="<?= blog_escape(blog_csrf_token()) ?>">
                <label for="username">Username</label>
                <input id="username" name="username" autocomplete="username" required maxlength="80" autofocus>
                <label for="password">Password</label>
                <div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" aria-label="Show password">Show</button></div>
                <button class="button button-primary login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <a class="back-site" href="../index.html">← Back to IGNOU Online Admission</a>
    </main>
    <script>
        document.querySelector('.password-toggle').addEventListener('click', function () {
            const input = document.querySelector('#password');
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? 'Show' : 'Hide';
            this.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
        });
    </script>
</body>
</html>