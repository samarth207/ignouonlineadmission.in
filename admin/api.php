<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function blog_api_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function blog_slugify(string $value): string
{
    $value = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) : $value;
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    return substr($slug !== '' ? $slug : 'story-' . substr(bin2hex(random_bytes(4)), 0, 8), 0, 170);
}

function blog_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $length = preg_match_all('/./us', $value);
    return $length === false ? strlen($value) : $length;
}

function blog_unique_slug(PDO $db, string $slug, int $exceptId = 0): string
{
    $base = substr($slug, 0, 170);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $query = $db->prepare('SELECT id FROM blogs WHERE slug = ? AND id <> ? LIMIT 1');
        $query->execute([$candidate, $exceptId]);
        if (!$query->fetchColumn()) {
            return $candidate;
        }
        $candidate = substr($base, 0, 160) . '-' . $suffix++;
    }
}

function blog_unique_category_slug(PDO $db, string $slug, int $exceptId = 0): string
{
    $base = substr($slug, 0, 130);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $query = $db->prepare('SELECT id FROM blog_categories WHERE slug = ? AND id <> ? LIMIT 1');
        $query->execute([$candidate, $exceptId]);
        if (!$query->fetchColumn()) {
            return $candidate;
        }
        $candidate = substr($base, 0, 120) . '-' . $suffix++;
    }
}

function blog_safe_editor_html(string $html): string
{
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? '';
    $allowed = '<p><h1><h2><h3><h4><ul><ol><li><strong><b><em><i><u><s><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><br><hr><div><span><figure><figcaption>';
    $html = strip_tags($html, $allowed);
    if (!class_exists('DOMDocument')) {
        $tags = ['p','h1','h2','h3','h4','ul','ol','li','strong','b','em','i','u','s','blockquote','pre','code','a','img','table','thead','tbody','tr','th','td','br','hr','div','span','figure','figcaption'];
        return preg_replace_callback('/<\/?([a-z][a-z0-9]*)\b[^>]*>/i', function ($match) use ($tags) {
            $tag = strtolower($match[1]);
            if (!in_array($tag, $tags, true)) {
                return '';
            }
            return isset($match[0][1]) && $match[0][1] === '/' ? '</' . $tag . '>' : '<' . $tag . '>';
        }, $html) ?? '';
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="editor-content">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $root = $document->getElementsByTagName('div')->item(0);
    if (!$root) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return '';
    }
    $allowedAttributes = ['href', 'src', 'alt', 'title', 'target', 'rel', 'class', 'colspan', 'rowspan'];
    $clean = function ($node) use (&$clean, $allowedAttributes): void {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            if (!in_array(strtolower($child->nodeName), ['p','h1','h2','h3','h4','ul','ol','li','strong','b','em','i','u','s','blockquote','pre','code','a','img','table','thead','tbody','tr','th','td','br','hr','div','span','figure','figcaption'], true)) {
                $node->removeChild($child);
                continue;
            }
            $attributes = [];
            foreach ($child->attributes as $attribute) {
                $attributes[] = $attribute->nodeName;
            }
            foreach ($attributes as $attributeName) {
                $attribute = $child->getAttributeNode($attributeName);
                $value = $attribute ? trim($attribute->value) : '';
                if (!in_array(strtolower($attributeName), $allowedAttributes, true)) {
                    $child->removeAttribute($attributeName);
                } elseif (in_array(strtolower($attributeName), ['href', 'src'], true)) {
                    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                    if (($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) || strpos($value, '//') === 0) {
                        $child->removeAttribute($attributeName);
                    }
                } elseif ($attributeName === 'class' && !preg_match('/^(faq-block|lead-form|faq-question|faq-answer)(\s+(faq-block|lead-form|faq-question|faq-answer))*$/', $value)) {
                    $child->removeAttribute($attributeName);
                } elseif ($attributeName === 'target' && !in_array($value, ['_blank', '_self'], true)) {
                    $child->removeAttribute($attributeName);
                }
            }
            if (strtolower($child->nodeName) === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $clean($child);
        }
    };
    $clean($root);
    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $result;
}

function blog_fetch_post(PDO $db, int $id): ?array
{
    $query = $db->prepare("SELECT b.*, a.name AS author_name FROM blogs b LEFT JOIN blog_authors a ON a.id = b.author_id WHERE b.id = ? AND b.status <> 'deleted' LIMIT 1");
    $query->execute([$id]);
    $post = $query->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        return null;
    }
    foreach (['category_ids' => 'SELECT category_id FROM blog_category_relations WHERE blog_id = ?', 'tags' => 'SELECT t.name FROM blog_tags t JOIN blog_tag_relations r ON r.tag_id = t.id WHERE r.blog_id = ?'] as $key => $sql) {
        $related = $db->prepare($sql);
        $related->execute([$id]);
        $post[$key] = $key === 'category_ids' ? array_map('intval', $related->fetchAll(PDO::FETCH_COLUMN)) : $related->fetchAll(PDO::FETCH_COLUMN);
    }
    $post['toc'] = $post['toc'] ? json_decode((string) $post['toc'], true) : [];
    return $post;
}

try {
    $admin = blog_current_admin();
    if (!$admin) {
        blog_api_response(401, ['message' => 'Please sign in again.']);
    }
    $db = blog_db();
    $db->exec("UPDATE blogs SET status = 'published' WHERE status = 'scheduled' AND publish_date <= UTC_TIMESTAMP()");
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = (string) ($_GET['action'] ?? '');
        if ($action === 'dashboard') {
            $counts = $db->query("SELECT status, COUNT(*) AS total FROM blogs WHERE status <> 'deleted' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
            $views = (int) $db->query("SELECT COALESCE(SUM(views), 0) FROM blogs WHERE status <> 'deleted'")->fetchColumn();
            $recent = $db->query("SELECT b.id, b.title, b.status, b.views, b.updated_at, b.slug FROM blogs b WHERE b.status <> 'deleted' ORDER BY b.updated_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
            $upcoming = $db->query("SELECT id, title, publish_date FROM blogs WHERE status = 'scheduled' ORDER BY publish_date ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            blog_api_response(200, ['counts' => $counts, 'views' => $views, 'recent' => $recent, 'upcoming' => $upcoming]);
        }
        if ($action === 'blogs') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = 12;
            $search = trim((string) ($_GET['search'] ?? ''));
            $status = (string) ($_GET['status'] ?? 'all');
            $conditions = ["b.status <> 'deleted'"];
            $params = [];
            if (in_array($status, ['published','draft','pending','scheduled'], true)) {
                $conditions[] = 'b.status = ?';
                $params[] = $status;
            }
            if ($search !== '') {
                $conditions[] = '(b.title LIKE ? OR b.slug LIKE ?)';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            $where = implode(' AND ', $conditions);
            $count = $db->prepare('SELECT COUNT(*) FROM blogs b WHERE ' . $where);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            $query = $db->prepare('SELECT b.id, b.title, b.slug, b.status, b.publish_date, b.updated_at, b.views, b.read_time, a.name AS author_name FROM blogs b LEFT JOIN blog_authors a ON a.id = b.author_id WHERE ' . $where . ' ORDER BY b.updated_at DESC LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit));
            $query->execute($params);
            $counts = $db->query("SELECT status, COUNT(*) AS total FROM blogs WHERE status <> 'deleted' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
            blog_api_response(200, ['items' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'counts' => $counts]);
        }
        if ($action === 'blog') {
            $post = blog_fetch_post($db, (int) ($_GET['id'] ?? 0));
            blog_api_response($post ? 200 : 404, $post ? ['item' => $post] : ['message' => 'Blog not found.']);
        }
        if ($action === 'categories') {
            $items = $db->query('SELECT c.*, COUNT(r.blog_id) AS blog_count FROM blog_categories c LEFT JOIN blog_category_relations r ON r.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll(PDO::FETCH_ASSOC);
            blog_api_response(200, ['items' => $items]);
        }
        if ($action === 'authors') {
            $items = $db->query('SELECT a.*, COUNT(b.id) AS blog_count FROM blog_authors a LEFT JOIN blogs b ON b.author_id = a.id AND b.status <> \'deleted\' GROUP BY a.id ORDER BY a.name')->fetchAll(PDO::FETCH_ASSOC);
            blog_api_response(200, ['items' => $items]);
        }
        if ($action === 'settings') {
            blog_api_response(200, ['item' => ['username' => $admin['username'], 'role' => $admin['role'], 'full_name' => $admin['full_name']]]);
        }
        blog_api_response(404, ['message' => 'Unknown action.']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        blog_api_response(405, ['message' => 'Method not allowed.']);
    }
    blog_require_csrf();
    $input = $_POST;
    $raw = file_get_contents('php://input');
    if (strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $input = json_decode($raw ?: '', true);
        if (!is_array($input)) {
            blog_api_response(400, ['message' => 'Invalid request data.']);
        }
    }
    $action = (string) ($input['action'] ?? '');
    if ($action !== 'upload' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2500000) {
        blog_api_response(413, ['message' => 'The blog content is too large to save.']);
    }

    if ($action === 'upload') {
        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
            blog_api_response(422, ['message' => 'Choose an image under 5 MB.']);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($types[$mime])) {
            blog_api_response(422, ['message' => 'Only JPG, PNG, or WebP images are accepted.']);
        }
        $directory = dirname(__DIR__) . '/uploads/blog';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            blog_api_response(500, ['message' => 'Image storage is unavailable.']);
        }
        $filename = bin2hex(random_bytes(18)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
            blog_api_response(500, ['message' => 'The image could not be stored.']);
        }
        blog_api_response(201, ['path' => '/uploads/blog/' . $filename]);
    }

    if ($action === 'save_blog') {
        $id = max(0, (int) ($input['id'] ?? 0));
        $title = trim((string) ($input['title'] ?? ''));
        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        $image = trim((string) ($input['feature_image'] ?? ''));
        $imageAlt = trim((string) ($input['feature_image_alt'] ?? ''));
        $focusKeyword = trim((string) ($input['focus_keyword'] ?? ''));
        $categories = array_values(array_unique(array_filter(array_map('intval', (array) ($input['category_ids'] ?? [])))));
        if ($title === '' || blog_text_length($title) > 70 || $excerpt === '' || blog_text_length($excerpt) > 250 || !preg_match('#^/uploads/blog/[A-Za-z0-9._-]+$#', $image) || $imageAlt === '' || blog_text_length($imageAlt) > 250 || $focusKeyword === '' || blog_text_length($focusKeyword) > 160 || !$categories) {
            blog_api_response(422, ['message' => 'Complete the title, excerpt, uploaded feature image and alt text, focus keyword, and at least one category.']);
        }
        $categoryCheck = $db->prepare('SELECT COUNT(*) FROM blog_categories WHERE is_active = 1 AND id IN (' . implode(',', array_fill(0, count($categories), '?')) . ')');
        $categoryCheck->execute($categories);
        if ((int) $categoryCheck->fetchColumn() !== count($categories)) {
            blog_api_response(422, ['message' => 'Select active categories only.']);
        }
        $slug = blog_unique_slug($db, blog_slugify(trim((string) ($input['slug'] ?? '')) ?: $title), $id);
        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, ['draft','pending','published','scheduled'], true)) {
            blog_api_response(422, ['message' => 'Select a valid publishing status.']);
        }
        $publishDate = null;
        if ($status === 'scheduled') {
            $localDate = trim((string) ($input['publish_date'] ?? '')) . ' ' . trim((string) ($input['publish_time'] ?? '')) . ':00';
            try {
                $timezone = new DateTimeZone((string) ($input['timezone'] ?? 'UTC'));
            } catch (Throwable $exception) {
                blog_api_response(422, ['message' => 'Choose a valid local timezone.']);
            }
            $date = DateTime::createFromFormat('!Y-m-d H:i:s', $localDate, $timezone);
            if (!$date || $date->format('Y-m-d H:i:s') !== $localDate || $date->getTimestamp() <= time()) {
                blog_api_response(422, ['message' => 'Choose a future publish date and time.']);
            }
            $date->setTimezone(new DateTimeZone('UTC'));
            $publishDate = $date->format('Y-m-d H:i:s');
        } elseif ($status === 'published') {
            $existing = null;
            if ($id > 0) {
                $existingQuery = $db->prepare('SELECT publish_date, status FROM blogs WHERE id = ? AND status <> \'deleted\'');
                $existingQuery->execute([$id]);
                $existing = $existingQuery->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $publishDate = $existing && $existing['status'] === 'published' && $existing['publish_date'] ? $existing['publish_date'] : gmdate('Y-m-d H:i:s');
        }
        $metaTitle = trim((string) ($input['meta_title'] ?? '')) ?: $title;
        $metaDescription = trim((string) ($input['meta_description'] ?? '')) ?: $excerpt;
        if (blog_text_length($metaTitle) > 70 || blog_text_length($metaDescription) > 250 || blog_text_length(trim((string) ($input['primary_keyword'] ?? ''))) > 160 || blog_text_length(trim((string) ($input['feature_image_title'] ?? ''))) > 250) {
            blog_api_response(422, ['message' => 'Check the SEO and image title lengths.']);
        }
        $authorId = (int) ($input['author_id'] ?? 0) ?: null;
        if ($authorId !== null) {
            $authorCheck = $db->prepare('SELECT COUNT(*) FROM blog_authors WHERE id = ? AND is_active = 1');
            $authorCheck->execute([$authorId]);
            if (!(int) $authorCheck->fetchColumn()) {
                blog_api_response(422, ['message' => 'Select an active author.']);
            }
        }
        $content = blog_safe_editor_html((string) ($input['content'] ?? ''));
        $toc = json_encode(is_array($input['toc'] ?? null) ? $input['toc'] : [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $tags = array_slice(array_values(array_unique(array_filter(array_map(function ($tag) {
            return trim(strip_tags((string) $tag));
        }, (array) ($input['tags'] ?? []))))), 0, 30);
        foreach ($tags as $tag) {
            if (blog_text_length($tag) > 100) {
                blog_api_response(422, ['message' => 'Tags must be 100 characters or fewer.']);
            }
        }
        $words = preg_match_all('/[\pL\pN]+/u', strip_tags($content));
        $readTime = max(1, (int) ceil(($words ?: 0) / 200));
        $db->beginTransaction();
        try {
            $values = [$title, $slug, $excerpt, $content, $toc, $image, $imageAlt, trim((string) ($input['feature_image_title'] ?? '')), $metaTitle, $metaDescription, $focusKeyword, trim((string) ($input['primary_keyword'] ?? '')), $authorId, $status, $publishDate, $readTime];
            if ($id > 0) {
                $values[] = $id;
                $save = $db->prepare('UPDATE blogs SET title=?, slug=?, excerpt=?, content=?, toc=?, feature_image=?, feature_image_alt=?, feature_image_title=?, meta_title=?, meta_description=?, focus_keyword=?, primary_keyword=?, author_id=?, status=?, publish_date=?, read_time=? WHERE id=? AND status <> \'deleted\'');
                $save->execute($values);
                if ($save->rowCount() === 0 && !blog_fetch_post($db, $id)) {
                    throw new RuntimeException('Blog not found.');
                }
            } else {
                $save = $db->prepare('INSERT INTO blogs (title, slug, excerpt, content, toc, feature_image, feature_image_alt, feature_image_title, meta_title, meta_description, focus_keyword, primary_keyword, author_id, status, publish_date, read_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $save->execute($values);
                $id = (int) $db->lastInsertId();
            }
            $db->prepare('DELETE FROM blog_category_relations WHERE blog_id = ?')->execute([$id]);
            $relation = $db->prepare('INSERT INTO blog_category_relations (blog_id, category_id) VALUES (?, ?)');
            foreach ($categories as $categoryId) {
                $relation->execute([$id, $categoryId]);
            }
            $db->prepare('DELETE FROM blog_tag_relations WHERE blog_id = ?')->execute([$id]);
            foreach ($tags as $tag) {
                $tagQuery = $db->prepare('SELECT id FROM blog_tags WHERE name = ? LIMIT 1');
                $tagQuery->execute([$tag]);
                $tagId = (int) $tagQuery->fetchColumn();
                if (!$tagId) {
                    $tagSlug = blog_slugify($tag);
                    $tagInsert = $db->prepare('INSERT INTO blog_tags (name, slug) VALUES (?, ?)');
                    $tagInsert->execute([$tag, substr($tagSlug, 0, 120) . '-' . substr(bin2hex(random_bytes(3)), 0, 6)]);
                    $tagId = (int) $db->lastInsertId();
                }
                $db->prepare('INSERT INTO blog_tag_relations (blog_id, tag_id) VALUES (?, ?)')->execute([$id, $tagId]);
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
        blog_api_response(200, ['message' => 'Blog saved.', 'id' => $id, 'slug' => $slug, 'updated_at' => gmdate(DATE_ATOM)]);
    }

    if ($action === 'delete_blog') {
        $db->prepare("UPDATE blogs SET status = 'deleted' WHERE id = ?")->execute([(int) ($input['id'] ?? 0)]);
        blog_api_response(200, ['message' => 'Blog moved to deleted.']);
    }
    if ($action === 'save_category') {
        $id = (int) ($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $slug = blog_slugify(trim((string) ($input['slug'] ?? '')) ?: $name);
        $description = trim((string) ($input['description'] ?? ''));
        if ($name === '' || blog_text_length($name) > 120 || blog_text_length($description) > 4000) {
            blog_api_response(422, ['message' => 'Enter a category name under 120 characters.']);
        }
        $slug = blog_unique_category_slug($db, $slug, $id);
        if ($id) {
            $db->prepare('UPDATE blog_categories SET name=?, slug=?, description=? WHERE id=?')->execute([$name, $slug, $description, $id]);
        } else {
            $db->prepare('INSERT INTO blog_categories (name, slug, description) VALUES (?, ?, ?)')->execute([$name, $slug, $description]);
        }
        blog_api_response(200, ['message' => 'Category saved.']);
    }
    if ($action === 'toggle_category') {
        $db->prepare('UPDATE blog_categories SET is_active = 1 - is_active WHERE id = ?')->execute([(int) ($input['id'] ?? 0)]);
        blog_api_response(200, ['message' => 'Category status updated.']);
    }
    if ($action === 'save_author') {
        $id = (int) ($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        $image = trim((string) ($input['image'] ?? ''));
        $pageUrl = trim((string) ($input['page_url'] ?? ''));
        $validImage = $image === '' || (filter_var($image, FILTER_VALIDATE_URL) && strtolower((string) parse_url($image, PHP_URL_SCHEME)) === 'https') || preg_match('#^/uploads/blog/[A-Za-z0-9._-]+$#', $image);
        $validPageUrl = $pageUrl === '' || (filter_var($pageUrl, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($pageUrl, PHP_URL_SCHEME)), ['http', 'https'], true));
        if ($name === '' || blog_text_length($name) > 160 || blog_text_length($bio) > 10000 || !$validImage || !$validPageUrl) {
            blog_api_response(422, ['message' => 'Check the author name, image URL, and page URL.']);
        }
        if ($id) {
            $db->prepare('UPDATE blog_authors SET name=?, bio=?, image=?, page_url=? WHERE id=?')->execute([$name, $bio, $image ?: null, $pageUrl ?: null, $id]);
        } else {
            $db->prepare('INSERT INTO blog_authors (name, bio, image, page_url) VALUES (?, ?, ?, ?)')->execute([$name, $bio, $image ?: null, $pageUrl ?: null]);
        }
        blog_api_response(200, ['message' => 'Author saved.']);
    }
    if ($action === 'toggle_author') {
        $db->prepare('UPDATE blog_authors SET is_active = 1 - is_active WHERE id = ?')->execute([(int) ($input['id'] ?? 0)]);
        blog_api_response(200, ['message' => 'Author status updated.']);
    }
    if ($action === 'change_password') {
        $current = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['new_password'] ?? '');
        if (strlen($password) < 12 || $password !== (string) ($input['confirm_password'] ?? '')) {
            blog_api_response(422, ['message' => 'New passwords must match and contain at least 12 characters.']);
        }
        $query = $db->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $query->execute([(int) $admin['id']]);
        if (!password_verify($current, (string) $query->fetchColumn())) {
            blog_api_response(422, ['message' => 'Current password is incorrect.']);
        }
        $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);
        blog_api_response(200, ['message' => 'Password updated.']);
    }
    blog_api_response(404, ['message' => 'Unknown action.']);
} catch (Throwable $exception) {
    error_log('Blog admin API failed: ' . $exception->getMessage());
    blog_api_response(500, ['message' => 'The request could not be completed. Check the CMS setup and try again.']);
}