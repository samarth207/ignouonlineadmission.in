<?php
declare(strict_types=1);
require __DIR__ . '/admin/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = null;
$categories = [];
$posts = [];
$canonical = 'https://ignouonlineadmission.in/blog/';
blog_db()->exec("UPDATE blogs SET status = 'published' WHERE status = 'scheduled' AND publish_date <= UTC_TIMESTAMP()");
if ($slug !== '') {
    if (!preg_match('/^[a-z0-9-]{1,180}$/', $slug)) {
        http_response_code(404);
    } else {
        $query = blog_db()->prepare("SELECT b.*, a.name AS author_name, a.image AS author_image, a.bio AS author_bio FROM blogs b LEFT JOIN blog_authors a ON a.id = b.author_id WHERE b.slug = ? AND b.status = 'published' AND (b.publish_date IS NULL OR b.publish_date <= UTC_TIMESTAMP()) LIMIT 1");
        $query->execute([$slug]);
        $post = $query->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($post) {
            blog_db()->prepare('UPDATE blogs SET views = views + 1 WHERE id = ?')->execute([(int) $post['id']]);
            $categoryQuery = blog_db()->prepare('SELECT c.name, c.slug FROM blog_categories c JOIN blog_category_relations r ON r.category_id = c.id WHERE r.blog_id = ? AND c.is_active = 1 ORDER BY c.name');
            $categoryQuery->execute([(int) $post['id']]);
            $categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
            $canonical .= rawurlencode($slug);
        } else {
            http_response_code(404);
        }
    }
} else {
    $posts = blog_db()->query("SELECT b.id, b.title, b.slug, b.excerpt, b.feature_image, b.feature_image_alt, b.publish_date, b.read_time, a.name AS author_name FROM blogs b LEFT JOIN blog_authors a ON a.id = b.author_id WHERE b.status = 'published' AND (b.publish_date IS NULL OR b.publish_date <= UTC_TIMESTAMP()) ORDER BY b.publish_date DESC, b.updated_at DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = $post ? (string) ($post['meta_title'] ?: $post['title']) : 'IGNOU Online Admission Blog';
$description = $post ? (string) ($post['meta_description'] ?: $post['excerpt']) : 'Guides, updates, and useful information for IGNOU online learners.';
$pageDescription = blog_escape($description);
$schema = $post ? json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $post['excerpt'],
    'image' => $post['feature_image'],
    'datePublished' => $post['publish_date'],
    'dateModified' => $post['updated_at'],
    'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?: 'IGNOU Online Admission'],
    'mainEntityOfPage' => $canonical,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= blog_escape($pageTitle) ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link rel="canonical" href="<?= blog_escape($canonical) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Raleway:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/blog.css" rel="stylesheet">
    <?php if ($schema !== ''): ?><script type="application/ld+json"><?= $schema ?></script><?php endif; ?>
</head>
<body>
    <header class="blog-masthead"><div class="blog-nav"><a href="index.html" class="site-mark"><img src="img/logo.png" alt="IGNOU Online Admission"><span>IGNOU <small>ONLINE ADMISSION</small></span></a><nav><a href="index.html">Home</a><a href="programme/0.html">Programmes</a><a href="contact.html">Contact</a></nav></div></header>
    <?php if ($post): ?>
        <main class="article-page">
            <div class="article-breadcrumb"><a href="/blog/">Blog</a><span>/</span><span><?= blog_escape($post['title']) ?></span></div>
            <article>
                <header class="article-header"><div class="article-categories"><?php foreach ($categories as $category): ?><span><?= blog_escape($category['name']) ?></span><?php endforeach; ?></div><h1><?= blog_escape($post['title']) ?></h1><p class="article-excerpt"><?= blog_escape($post['excerpt']) ?></p><div class="article-byline"><span class="byline-avatar"><?= blog_escape(strtoupper(substr((string) ($post['author_name'] ?: 'I'), 0, 1))) ?></span><span><strong><?= blog_escape($post['author_name'] ?: 'IGNOU Online Admission') ?></strong><small><?= blog_escape(date('F j, Y', strtotime((string) $post['publish_date']))) ?> · <?= (int) $post['read_time'] ?> min read</small></span></div></header>
                <figure class="article-feature"><img src="<?= blog_escape($post['feature_image']) ?>" alt="<?= blog_escape($post['feature_image_alt']) ?>" title="<?= blog_escape((string) $post['feature_image_title']) ?>"></figure>
                <div class="article-layout"><aside class="article-toc"><h2>In this article</h2><ol id="article-toc-list"></ol></aside><div class="article-content" id="article-content"><?= $post['content'] ?></div></div>
                <?php if (!empty($post['author_bio'])): ?><aside class="author-note"><strong>About <?= blog_escape($post['author_name']) ?></strong><p><?= blog_escape($post['author_bio']) ?></p></aside><?php endif; ?>
            </article>
        </main>
        <script>
            (function () {
                const article = document.querySelector('#article-content');
                const items = [...article.querySelectorAll('h2,h3,h4')];
                const toc = document.querySelector('#article-toc-list');
                items.forEach((heading, index) => {
                    const id = heading.id || `section-${index + 1}`;
                    heading.id = id;
                    const item = document.createElement('li');
                    item.className = `toc-level-${heading.tagName.slice(1)}`;
                    item.innerHTML = `<a href="#${id}"></a>`;
                    item.querySelector('a').textContent = heading.textContent;
                    toc.append(item);
                });
                if (!items.length) document.querySelector('.article-toc').hidden = true;
            })();
        </script>
    <?php elseif ($posts): ?>
        <main class="blog-index"><header class="blog-index-heading"><p>IGNOU ONLINE ADMISSION</p><h1>Learning, one story at a time.</h1><span>Updates and practical guidance for your next step.</span></header><div class="post-grid"><?php foreach ($posts as $item): ?><article class="post-card"><a class="post-image" href="/blog/<?= rawurlencode($item['slug']) ?>"><img src="<?= blog_escape($item['feature_image']) ?>" alt="<?= blog_escape($item['feature_image_alt']) ?>" loading="lazy"></a><div class="post-card-copy"><p class="post-meta"><?= blog_escape(date('M j, Y', strtotime((string) $item['publish_date']))) ?> <span>·</span> <?= (int) $item['read_time'] ?> min read</p><h2><a href="/blog/<?= rawurlencode($item['slug']) ?>"><?= blog_escape($item['title']) ?></a></h2><p><?= blog_escape($item['excerpt']) ?></p><a class="read-link" href="/blog/<?= rawurlencode($item['slug']) ?>">Read story <span>→</span></a></div></article><?php endforeach; ?></div></main>
    <?php else: ?>
        <main class="blog-empty"><p>IGNOU ONLINE ADMISSION</p><h1><?= $slug !== '' ? 'This story is not available.' : 'Stories are on their way.' ?></h1><a href="index.html">Return to the website</a></main>
    <?php endif; ?>
    <footer class="blog-footer"><div><a href="index.html">IGNOU Online Admission</a><span>Information and updates for online learners.</span></div><a href="contact.html">Contact us →</a></footer>
</body>
</html>