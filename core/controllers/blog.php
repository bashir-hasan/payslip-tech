<?php
declare(strict_types=1);

/* ---------------- Public ---------------- */

function blog_published_sql(): string
{
    return "status = 'published' AND published_at <= NOW()";
}

function public_blog(): void
{
    $cat = trim((string)($_GET['c'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 12;
    $where = blog_published_sql();
    $args = [];
    if ($cat !== '') { $where .= ' AND category = ?'; $args[] = $cat; }
    $total = (int)db_val("SELECT COUNT(*) FROM posts WHERE $where", $args);
    // Posts in the visitor's language first
    $posts = db_all("SELECT id, slug, lang, title, excerpt, cover, category, published_at, body FROM posts WHERE $where ORDER BY (lang = ?) DESC, published_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), array_merge($args, [lang()]));
    $cats = db_all("SELECT category, COUNT(*) n FROM posts WHERE " . blog_published_sql() . " AND category IS NOT NULL AND category <> '' GROUP BY category ORDER BY n DESC");
    $items = array_map(fn($p) => ['@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => post_abs_url($p), 'datePublished' => date('c', strtotime($p['published_at']))], $posts);
    render('public/blog', [
        'title' => t('Payroll & payslip guides'),
        'seo_page' => 'blog',
        'index_page' => $page === 1 && $cat === '',
        'noindex' => $page > 1 || $cat !== '',
        'description' => t('Practical guides on payslips, payroll and income verification for employers, employees, banks and landlords.'),
        'posts' => $posts, 'cats' => $cats, 'cat' => $cat, 'page' => $page, 'pages' => (int)ceil($total / $per),
        'jsonld' => [
            ['@type' => 'Blog', '@id' => site_origin() . '/blog#blog', 'name' => site_name() . ' Blog', 'url' => site_origin() . '/blog', 'publisher' => ['@id' => site_origin() . '/#organization'], 'blogPost' => $items],
            jsonld_breadcrumb([[site_name(), '/'], [t('Blog'), '/blog']]),
        ],
    ]);
}

function public_post(string $slug): void
{
    $slug = rawurldecode($slug);
    $p = db_one('SELECT p.*, u.name AS author_name FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.slug = ?', [$slug]);
    $u = current_user();
    $isAdmin = $u && $u['role'] === 'admin';
    $live = $p && $p['status'] === 'published' && strtotime((string)$p['published_at']) <= time();
    if (!$p || (!$live && !$isAdmin)) abort(404);
    if ($live && !$isAdmin && !preg_match('/bot|crawl|spider|slurp|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        db_exec('UPDATE posts SET views = views + 1 WHERE id = ?', [$p['id']]);
    }
    $r = md_render($p['body']);
    $faqs = parse_faq($p['faq']);
    $related = db_all('SELECT id, slug, lang, title, excerpt, cover, published_at, body FROM posts WHERE ' . blog_published_sql() . ' AND id <> ? ORDER BY (lang = ?) DESC, (category <=> ?) DESC, published_at DESC LIMIT 3', [$p['id'], $p['lang'], $p['category']]);
    $graph = [jsonld_blogposting($p, $faqs), jsonld_breadcrumb([[site_name(), '/'], [t('Blog'), '/blog'], [$p['title'], '/blog/' . rawurlencode($p['slug'])]])];
    if ($faqs) $graph[] = jsonld_faq($faqs);
    page_modified((int)strtotime((string)($p['updated_at'] ?: $p['published_at'])));
    render('public/post', [
        'markdown' => '# ' . $p['title'] . "\n\n" . ($p['excerpt'] ? '> ' . $p['excerpt'] . "\n\n" : '') . preg_replace('/\[\[cta\]\]/', '[Start free on ' . site_name() . '](' . site_origin() . '/register)', (string)$p['body']) . ($faqs ? "\n\n## FAQ\n\n" . implode("\n\n", array_map(fn($f) => '### ' . $f[0] . "\n\n" . $f[1], $faqs)) : ''),
        'title' => $p['title'],
        'page_title' => $p['meta_title'] ?: null,
        'description' => $p['meta_description'] ?: ($p['excerpt'] ?: mb_substr(strip_tags($r['html']), 0, 155)),
        'index_page' => $live,
        'noindex' => !$live,
        'post_lang' => $p['lang'],
        'og_type' => 'article',
        'og_image' => post_cover_url($p),
        'p' => $p, 'html' => $r['html'], 'toc' => $r['toc'], 'faqs' => $faqs, 'related' => $related, 'preview' => !$live,
        'jsonld' => $graph,
    ]);
}

function blog_feed(): void
{
    header('Content-Type: application/rss+xml; charset=utf-8');
    $o = site_origin();
    $posts = db_all('SELECT * FROM posts WHERE ' . blog_published_sql() . ' ORDER BY published_at DESC LIMIT 30');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\"><channel>\n";
    echo '<title>' . e(site_name()) . ' Blog</title><link>' . e($o . '/blog') . '</link><description>' . e(t('Practical guides on payslips, payroll and income verification for employers, employees, banks and landlords.')) . "</description>\n";
    echo '<atom:link href="' . e($o . '/blog/feed.xml') . "\" rel=\"self\" type=\"application/rss+xml\"/>\n";
    foreach ($posts as $p) {
        echo '<item><title>' . e($p['title']) . '</title><link>' . e(post_abs_url($p)) . '</link><guid isPermaLink="true">' . e(post_abs_url($p)) . '</guid><pubDate>' . date(DATE_RSS, strtotime($p['published_at'])) . '</pubDate><description>' . e($p['meta_description'] ?: (string)$p['excerpt']) . "</description></item>\n";
    }
    echo "</channel></rss>\n";
    exit;
}

/* ---------------- Admin ---------------- */

function admin_posts(): void
{
    require_role('admin');
    $f = in_array($_GET['f'] ?? '', ['published', 'draft', 'scheduled'], true) ? $_GET['f'] : 'all';
    $where = match ($f) {
        'published' => blog_published_sql(),
        'draft' => "status = 'draft'",
        'scheduled' => "status = 'published' AND published_at > NOW()",
        default => '1=1',
    };
    $rows = db_all("SELECT id, slug, lang, title, status, category, views, published_at, updated_at, body, keyword, meta_title, meta_description, excerpt, faq, cover FROM posts WHERE $where ORDER BY COALESCE(published_at, updated_at) DESC");
    foreach ($rows as &$r) {
        $checks = post_seo_checks($r);
        $r['score'] = (int)round(100 * count(array_filter($checks, fn($c) => $c[0])) / max(1, count($checks)));
        unset($r['body']);
    }
    unset($r);
    $counts = db_one("SELECT COUNT(*) a, SUM(status = 'draft') d, SUM(" . blog_published_sql() . ") p, SUM(status = 'published' AND published_at > NOW()) s FROM posts");
    render('admin/posts', ['title' => t('Blog'), 'nav' => 'admin-blog', 'rows' => $rows, 'f' => $f, 'counts' => $counts], 'app');
}

function admin_post_edit(string $id = 'new'): void
{
    require_role('admin');
    if ($id === 'new') {
        $p = ['id' => '', 'slug' => '', 'lang' => lang(), 'title' => '', 'excerpt' => '', 'body' => "Write an introduction that answers the question in the first two sentences.\n\n## First section\n\nText…\n\n## Second section\n\n- Point one\n- Point two\n\n[[cta]]\n", 'faq' => '', 'cover' => '', 'meta_title' => '', 'meta_description' => '', 'keyword' => '', 'category' => '', 'status' => 'draft', 'published_at' => null];
    } else {
        $p = db_one('SELECT * FROM posts WHERE id = ?', [(int)$id]) ?? abort(404);
    }
    $cats = db_all("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    render('admin/post_edit', ['title' => $p['id'] ? t('Edit article') : t('New article'), 'nav' => 'admin-blog', 'p' => $p, 'checks' => $p['id'] ? post_seo_checks($p) : [], 'cats' => array_column($cats, 'category')], 'app');
}

function admin_post_save(string $id = 'new'): void
{
    $u = require_role('admin');
    $existing = $id === 'new' ? null : (db_one('SELECT * FROM posts WHERE id = ?', [(int)$id]) ?? abort(404));
    if (($_POST['action'] ?? '') === 'delete' && $existing) {
        db_exec('DELETE FROM posts WHERE id = ?', [$existing['id']]);
        if ($existing['cover']) @unlink(ROOT . '/uploads/blog/' . basename($existing['cover']));
        audit('Blog article deleted: ' . $existing['slug']);
        flash('ok', t('Article deleted.'));
        redirect('/admin/blog');
    }
    $in = fn($k, $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $data = [
        'title' => $in('title', 200),
        'lang' => ($_POST['lang'] ?? 'en') === 'bn' ? 'bn' : 'en',
        'excerpt' => $in('excerpt', 400) ?: null,
        'body' => mb_substr(str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')), 0, 200000),
        'faq' => mb_substr(str_replace("\r\n", "\n", trim((string)($_POST['faq'] ?? ''))), 0, 20000) ?: null,
        'meta_title' => $in('meta_title', 200) ?: null,
        'meta_description' => $in('meta_description', 300) ?: null,
        'keyword' => $in('keyword', 120) ?: null,
        'category' => $in('category', 80) ?: null,
        'updated_at' => now(),
    ];
    if ($data['title'] === '' || trim($data['body']) === '') {
        flash('err', t('Title and article text are required.'));
        redirect($existing ? '/admin/blog/' . $existing['id'] : '/admin/blog/new');
    }
    $slug = slugify($in('slug', 160) ?: $data['title']);
    $base = $slug; $k = 2;
    while (db_val('SELECT 1 FROM posts WHERE slug = ? AND id <> ?', [$slug, $existing['id'] ?? 0])) $slug = mb_substr($base, 0, 150) . '-' . $k++;
    $data['slug'] = $slug;

    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $data['status'] = $status;
    $when = trim((string)($_POST['published_at'] ?? ''));
    if ($status === 'published') {
        $ts = $when !== '' ? strtotime($when) : false;
        $data['published_at'] = $ts ? date('Y-m-d H:i:s', $ts) : ($existing['published_at'] ?? now());
    } else {
        $data['published_at'] = $existing['published_at'] ?? null;
    }

    // Cover image (re-encoded with GD, max 1600px)
    if (!empty($_POST['remove_cover']) && !empty($existing['cover'])) {
        @unlink(ROOT . '/uploads/blog/' . basename($existing['cover']));
        $data['cover'] = null;
    }
    $f = $_FILES['cover'] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $info = @getimagesize($f['tmp_name']);
        if ($f['size'] > 4 * 1024 * 1024 || !$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            flash('err', t('Cover must be a JPG, PNG or WebP image under 4 MB.'));
        } else {
            if (!is_dir(ROOT . '/uploads/blog')) @mkdir(ROOT . '/uploads/blog', 0755, true);
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
            $name = mb_substr(preg_replace('/[^a-z0-9-]/', '', strtolower($slug)) ?: 'post', 0, 60) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (sanitize_image($f['tmp_name'], ROOT . '/uploads/blog/' . $name, (int)$info[2], 1600)) {
                if (!empty($existing['cover'])) @unlink(ROOT . '/uploads/blog/' . basename($existing['cover']));
                $data['cover'] = $name;
            }
        }
    }

    if ($existing) {
        db_update('posts', $data, 'id = ?', [$existing['id']]);
        $pid = (int)$existing['id'];
    } else {
        $pid = db_insert('posts', $data + ['author_id' => $u['id'], 'created_at' => now()]);
    }
    audit('Blog article saved (' . $status . '): ' . $slug);
    flash('ok', $status === 'published' ? t('Article published.') : t('Draft saved.'));
    redirect('/admin/blog/' . $pid);
}
