<?php $cta = '<aside class="post-cta"><div><b>' . e(t('Issue payslips anyone can verify')) . '</b><p>' . e(t('Run payroll and give every payslip a QR code that banks, landlords and embassies can check in seconds. Free for up to 5 employees.')) . '</p></div><div class="row" style="gap:8px"><a class="btn btn-primary" href="' . e(url('/register')) . '">' . e(t('Create free account')) . '</a><a class="btn btn-light" href="' . e(url('/verify')) . '">' . e(t('Verify a payslip')) . '</a></div></aside>';
$body = str_contains($html, '<!--cta-->') ? str_replace('<!--cta-->', $cta, $html) : $html; ?>
<?php if ($preview): ?><div class="announce" style="background:var(--warn-bg);color:var(--ink)"><?= e(t('Preview: this article is not published yet. Only admins can see it.')) ?> <a href="<?= e(url('/admin/blog/' . $p['id'])) ?>"><?= e(t('Edit')) ?></a></div><?php endif; ?>
<article class="post" lang="<?= e($p['lang']) ?>">
  <header class="post-head container">
    <nav class="crumbs small" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(site_name()) ?></a> / <a href="<?= e(url('/blog')) ?>"><?= e(t('Blog')) ?></a><?php if ($p['category']): ?> / <a href="<?= e(url('/blog?c=' . rawurlencode($p['category']))) ?>"><?= e($p['category']) ?></a><?php endif; ?></nav>
    <h1><?= e($p['title']) ?></h1>
    <?php if ($p['excerpt']): ?><p class="lead"><?= e($p['excerpt']) ?></p><?php endif; ?>
    <div class="post-meta"><?php if ($p['author_name']): ?><span><?= e(sprintf(t('By %s'), $p['author_name'])) ?></span><?php endif; ?><time datetime="<?= e(date('c', strtotime((string)($p['published_at'] ?: $p['created_at'])))) ?>"><?= e(date_fmt($p['published_at'] ?: $p['created_at'])) ?></time><?php if (substr((string)$p['updated_at'], 0, 10) > substr((string)$p['published_at'], 0, 10) && $p['published_at']): ?><span><?= e(sprintf(t('Updated %s'), date_fmt($p['updated_at']))) ?></span><?php endif; ?><span><?= e(sprintf(t('%d min read'), reading_minutes($p['body']))) ?></span></div>
  </header>
  <?php if ($p['cover']): ?><figure class="post-figure container"><img src="<?= e(url('/uploads/blog/' . rawurlencode($p['cover']))) ?>" alt="<?= e($p['title']) ?>" fetchpriority="high" decoding="async"></figure><?php endif; ?>
  <div class="post-layout container">
    <?php if (count($toc) >= 3): ?><aside class="post-toc"><div class="post-toc-in"><b><?= e(t('On this page')) ?></b><ol><?php foreach ($toc as [$id, $tx]): ?><li><a href="#<?= e($id) ?>"><?= e($tx) ?></a></li><?php endforeach; ?></ol></div></aside><?php endif; ?>
    <div class="post-body prose">
      <?= $body ?>
      <?php if ($faqs): ?>
        <h2 id="faq"><?= e(t('Frequently asked questions')) ?></h2>
        <div class="faq"><?php foreach ($faqs as [$q, $a]): ?><details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if (!str_contains($html, '<!--cta-->')) echo $cta; ?>
      <div class="post-share small"><span class="muted"><?= e(t('Share')) ?>:</span>
        <?php $su = rawurlencode(post_abs_url($p)); $st = rawurlencode($p['title']); ?>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $su ?>" target="_blank" rel="noopener">LinkedIn</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $su ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://x.com/intent/post?url=<?= $su ?>&amp;text=<?= $st ?>" target="_blank" rel="noopener">X</a>
        <a href="https://wa.me/?text=<?= $st ?>%20<?= $su ?>" target="_blank" rel="noopener">WhatsApp</a>
      </div>
    </div>
  </div>
</article>
<?php if ($related): ?>
<section class="container" style="padding:24px 20px 64px">
  <h2 style="font-size:22px"><?= e(t('Related guides')) ?></h2>
  <div class="post-grid three">
    <?php foreach ($related as $r): ?>
      <article class="post-card" lang="<?= e($r['lang']) ?>"><div class="post-card-body"><div class="post-meta"><span><?= e(date_fmt($r['published_at'])) ?></span></div><h3><a href="<?= e(post_url($r)) ?>"><?= e($r['title']) ?></a></h3><?php if ($r['excerpt']): ?><p><?= e(mb_substr($r['excerpt'], 0, 140)) ?></p><?php endif; ?></div></article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
