<?php
declare(strict_types=1);

function seo_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    if (setting('seo_noindex', '0') === '1') {
        echo "User-agent: *\nDisallow: /\n";
        exit;
    }
    $private = ['/app', '/admin', '/me', '/account', '/payslip/', '/v/', '/api/', '/invite/', '/reset/', '/login/2fa', '/demo', '/lang/', '/install.php'];
    echo "# " . site_name() . "\nUser-agent: *\n";
    foreach ($private as $p) echo "Disallow: $p\n";
    echo "Allow: /\n\n";
    // Explicitly welcome AI / answer-engine crawlers to the public pages
    foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'Bingbot'] as $bot) {
        echo "User-agent: $bot\n";
        foreach ($private as $p) echo "Disallow: $p\n";
        echo "Allow: /\n\n";
    }
    echo 'Sitemap: ' . site_origin() . "/sitemap.xml\n";
    exit;
}

function seo_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $pages = ['/' => ['1.0', 'weekly'], '/verify' => ['0.9', 'monthly'], '/developers' => ['0.7', 'monthly'], '/register' => ['0.6', 'monthly'], '/privacy' => ['0.3', 'yearly'], '/terms' => ['0.3', 'yearly']];
    $last = date('Y-m-d', max(filemtime(APP . '/views/public/home.php'), filemtime(APP . '/lang/bn.php')));
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach ($pages as $path => [$prio, $freq]) {
        foreach (['en', 'bn'] as $l) {
            echo "  <url>\n    <loc>" . e(canonical_url($path, $l)) . "</loc>\n    <lastmod>$last</lastmod>\n    <changefreq>$freq</changefreq>\n    <priority>" . ($l === 'bn' ? number_format((float)$prio - 0.1, 1) : $prio) . "</priority>\n";
            echo '    <xhtml:link rel="alternate" hreflang="en" href="' . e(canonical_url($path, 'en')) . '"/>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="bn" href="' . e(canonical_url($path, 'bn')) . '"/>' . "\n";
            echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e(canonical_url($path, 'en')) . '"/>' . "\n";
            echo "  </url>\n";
        }
    }
    // Blog index + articles (each article exists in one language)
    $posts = db_all("SELECT slug, lang, cover, updated_at FROM posts WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC");
    $blogLast = $posts ? substr(max(array_column($posts, 'updated_at')), 0, 10) : $last;
    foreach (['en', 'bn'] as $l) {
        echo "  <url>\n    <loc>" . e(canonical_url('/blog', $l)) . "</loc>\n    <lastmod>$blogLast</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
    }
    foreach ($posts as $p) {
        echo "  <url>\n    <loc>" . e(post_abs_url($p)) . "</loc>\n    <lastmod>" . substr($p['updated_at'], 0, 10) . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n";
        if ($p['cover']) echo '    <image:image><image:loc>' . e(post_cover_url($p)) . "</image:loc></image:image>\n";
        echo "  </url>\n";
    }
    echo "</urlset>\n";
    exit;
}

/** llms.txt — a plain summary written for large language models and AI search engines. */
function seo_llms(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $o = site_origin();
    $faq = '';
    foreach (home_faqs('en') as [$q, $a]) $faq .= "\n### $q\n$a\n";
    $extra = trim((string)setting('llms_extra', ''));
    echo "# " . site_name() . "\n\n";
    echo "> " . site_name() . " is an online payroll and payslip-verification platform for teams worldwide (US, Canada, UK and Europe, South Asia, the Gulf, Australia and more). Employers run monthly payroll and issue payslips and pay stubs in English or Bangla, in 20 currencies, with local payslip terms. Every payslip carries a QR code and a digital fingerprint (HMAC-SHA256). Banks, landlords, embassies and employers can verify any payslip for free at {$o}/verify, by scanning its QR code, or through a JSON API.\n\n";
    echo "## Key facts\n";
    echo "- Users: employers (payroll), employees (self-service portal), verifiers (banks, landlords, embassies, HR)\n";
    echo "- Plans: Free (up to 5 employees), Starter and Business paid plans; verification is always free\n";
    echo "- Automation: auto monthly payroll, plain-language smart assistant (English and Bangla), Excel/CSV smart import, payslip explanations\n";
    echo "- Payslip code format: PS-XXXX-XXXX\n";
    echo "- Verification results: valid, revoked, tampered (integrity check failed), not_found\n";
    echo "- Bank account numbers are never shown to verifiers\n\n";
    echo "## Main pages\n";
    echo "- [Home]({$o}/): product overview, how it works, pricing, FAQ\n";
    echo "- [Verify a payslip]({$o}/verify): free public verification\n";
    echo "- [Verification API]({$o}/developers): GET {$o}/api/v1/verify/{CODE} returns JSON\n";
    echo "- [OpenAPI 3.1]({$o}/openapi.json), [API catalog]({$o}/.well-known/api-catalog), [MCP server]({$o}/.well-known/mcp.json) at POST {$o}/mcp, [A2A agent card]({$o}/.well-known/agent-card.json), [agents.json]({$o}/.well-known/agents.json), [AGENTS.md]({$o}/AGENTS.md)\n";
    echo "- Any public page as Markdown: request it with the header Accept: text/markdown\n";
    echo "- [Blog]({$o}/blog): guides on payslips, payroll and income verification\n";
    echo "- [Create an employer account]({$o}/register)\n";
    echo "- [Privacy Policy]({$o}/privacy)\n- [Terms of Service]({$o}/terms)\n\n";
    $posts = db_all("SELECT slug, title, excerpt, meta_description FROM posts WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC LIMIT 50");
    if ($posts) {
        echo "## Guides\n";
        foreach ($posts as $p) echo '- [' . $p['title'] . '](' . post_abs_url($p) . '): ' . ($p['meta_description'] ?: $p['excerpt']) . "\n";
        echo "\n";
    }
    echo "## Frequently asked questions\n" . $faq;
    if ($extra !== '') echo "\n## More\n" . $extra . "\n";
    exit;
}
