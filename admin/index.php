<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$admin = blog_require_admin();
$pages = [
    'dashboard' => 'Dashboard',
    'blogs' => 'All Blogs',
    'editor' => 'New Blog',
    'categories' => 'Categories',
    'authors' => 'Authors',
    'settings' => 'Settings',
];
$page = (string) ($_GET['page'] ?? 'dashboard');
if (!isset($pages[$page])) {
    $page = 'dashboard';
}
$title = $page === 'editor' && !empty($_GET['id']) ? 'Edit Blog' : $pages[$page];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= blog_escape(blog_csrf_token()) ?>">
    <title><?= blog_escape($title) ?> | IGNOU Blog Desk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="admin-page" data-page="<?= blog_escape($page) ?>" data-blog-id="<?= (int) ($_GET['id'] ?? 0) ?>">
    <div class="mobile-scrim" data-action="close-menu"></div>
    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="brand-lockup"><img src="../img/logo.png" alt=""><span><strong>IGNOU</strong><small>EDITORIAL DESK</small></span></a>
        <p class="nav-group-label">MAIN</p>
        <nav class="side-nav" aria-label="Admin navigation">
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard"><span class="nav-icon">⌂</span>Dashboard</a>
            <a class="<?= $page === 'blogs' ? 'active' : '' ?>" href="index.php?page=blogs"><span class="nav-icon">▤</span>All Blogs</a>
            <a class="<?= $page === 'editor' ? 'active' : '' ?>" href="index.php?page=editor"><span class="nav-icon">＋</span>New Blog</a>
        </nav>
        <p class="nav-group-label">MANAGE</p>
        <nav class="side-nav" aria-label="Content management">
            <a class="<?= $page === 'categories' ? 'active' : '' ?>" href="index.php?page=categories"><span class="nav-icon">▦</span>Categories</a>
            <a class="<?= $page === 'authors' ? 'active' : '' ?>" href="index.php?page=authors"><span class="nav-icon">♙</span>Authors</a>
        </nav>
        <p class="nav-group-label">SYSTEM</p>
        <nav class="side-nav" aria-label="System">
            <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="index.php?page=settings"><span class="nav-icon">⚙</span>Settings</a>
            <a href="../blog/"><span class="nav-icon">▣</span>View Blog</a>
            <a href="../index.html" target="_blank" rel="noopener"><span class="nav-icon">↗</span>View Site</a>
        </nav>
        <div class="sidebar-user">
            <span class="user-avatar"><?= blog_escape(strtoupper(substr((string) $admin['full_name'], 0, 1))) ?></span>
            <span class="user-details"><strong><?= blog_escape((string) $admin['full_name']) ?></strong><small><?= blog_escape(ucfirst((string) $admin['role'])) ?></small></span>
            <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= blog_escape(blog_csrf_token()) ?>"><button class="icon-button logout-button" aria-label="Log out" title="Log out">↪</button></form>
        </div>
    </aside>
    <main class="workspace">
        <header class="topbar"><div class="topbar-title"><button class="mobile-menu" type="button" data-action="open-menu" aria-label="Open menu">☰</button><span class="topbar-kicker">IGNOU ONLINE ADMISSION</span><h1><?= blog_escape($title) ?></h1></div><div class="topbar-actions"><a class="button button-quiet" href="../index.html" target="_blank" rel="noopener">View Site <span aria-hidden="true">↗</span></a><a class="button button-primary" href="index.php?page=editor"><span aria-hidden="true">＋</span> New Blog</a></div></header>
        <section class="page-content" id="app" aria-live="polite"><div class="loading-state">Loading editorial desk...</div></section>
    </main>
    <div class="toast-region" aria-live="polite" aria-atomic="true"></div>
    <dialog class="confirm-dialog" id="confirm-dialog"><form method="dialog"><button class="dialog-close" aria-label="Close">×</button><h2 id="confirm-title">Confirm action</h2><p id="confirm-message"></p><div class="dialog-actions"><button class="button button-quiet" value="cancel">Cancel</button><button class="button button-danger" id="confirm-accept" value="confirm">Continue</button></div></form></dialog>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="assets/admin.js" defer></script>
</body>
</html>