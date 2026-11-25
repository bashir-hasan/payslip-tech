<?php
/**
 * Shared <head>. Variables: $title, $description, $noindex, $seo_page, $jsonld (array of schema nodes), $context.
 */
$context = $context ?? 'public';
$seoPage = $seo_page ?? null;
$pageTitle = ($seoPage ? seo_override($seoPage, 'title') : null) ?? (!empty($page_title) ? $page_title : null) ?? (($title ?? '') !== '' ? $title . ' · ' . site_name() : site_name() . ' — ' . t('Verified payslips & pay stubs, worldwide'));
$desc = ($seoPage ? seo_override($seoPage, 'description') : null) ?? ($description ?? default_description());
$indexable = $context === 'public' && empty($noindex) && (in_array(current_path(), SEO_PAGES, true) || !empty($index_page)) && http_response_code() < 400 && setting('seo_noindex', '0') !== '1';
$path = current_path();
$tw = setting('seo_twitter');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($indexable): ?>
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<?php if (!empty($post_lang)): // an article exists in one language only ?>
<link rel="canonical" href="<?= e(site_origin() . $path) ?>">
<link rel="alternate" hreflang="<?= e($post_lang) ?>" href="<?= e(site_origin() . $path) ?>">
<?php else: ?>
<link rel="canonical" href="<?= e(canonical_url($path)) ?>">
<link rel="alternate" hreflang="en" href="<?= e(canonical_url($path, 'en')) ?>">
<link rel="alternate" hreflang="bn" href="<?= e(canonical_url($path, 'bn')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(canonical_url($path, 'en')) ?>">
<?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="<?= e(site_name()) ?> Blog" href="<?= e(site_origin() . '/blog/feed.xml') ?>">
<link rel="alternate" type="text/plain" title="LLM summary" href="<?= e(site_origin() . '/llms.txt') ?>">
<?php else: ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#0b1f1c">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="application-name" content="<?= e(site_name()) ?>">
<meta name="format-detection" content="telephone=no">
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:type" content="<?= e($og_type ?? ($seoPage === 'home' ? 'website' : 'article')) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e(!empty($post_lang) ? site_origin() . $path : canonical_url($path)) ?>">
<meta property="og:image" content="<?= e($og_image ?? og_image_url()) ?>">
<?php if (empty($og_image)): ?><meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630"><?php endif; ?>
<meta property="og:image:alt" content="<?= e(site_name() . ' — ' . t('Verified payslips & pay stubs, worldwide')) ?>">
<meta property="og:locale" content="<?= ($post_lang ?? lang()) === 'bn' ? 'bn_BD' : 'en_US' ?>">
<meta property="og:locale:alternate" content="<?= lang() === 'bn' ? 'en_US' : 'bn_BD' ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($og_image ?? og_image_url()) ?>">
<?php if ($tw): ?><meta name="twitter:site" content="@<?= e($tw) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(url('assets/img/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('assets/site.webmanifest')) ?>">
<meta name="sw" content="<?= e(url('sw.js')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="PaySlip.tech">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php
if ($indexable) {
    $graph = [jsonld_organization(), jsonld_website()];
    foreach (($jsonld ?? []) as $node) $graph[] = $node;
    echo jsonld_script($graph), "\n";
}
if (tracking_enabled_here($context)) echo head_tracking_code(), "\n";
?>
