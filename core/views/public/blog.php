<section class="blog-hero">
  <div class="container">
    <span class="kicker"><?= e(t('Blog')) ?></span>
    <h1><?= e(t('Payroll & payslip guides')) ?></h1>
    <p class="lead"><?= e(t('Practical guides on payslips, payroll and income verification for employers, employees, banks and landlords.')) ?></p>
    <?php if ($cats): ?><div class="chips" style="margin-top:18px">
      <a class="chip-btn <?= $cat === '' ? 'on' : '' ?>" href="<?= e(url('/blog')) ?>"><?= e(t('All')) ?></a>
      <?php foreach ($cats as $c): ?><a class="chip-btn <?= $cat === $c['category'] ? 'on' : '' ?>" href="<?= e(url('/blog?c=' . rawurlencode($c['category']))) ?>"><?= e($c['category']) ?> <span class="muted"><?= e(num($c['n'])) ?></span></a><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</section>
<section class="container" style="padding-bottom:64px">
  <?php if (!$posts): ?>
    <div class="card card-pad center muted"><?= e(t('New guides are coming soon.')) ?></div>
  <?php else: ?>
  <div class="post-grid">
    <?php foreach ($posts as $i => $p): ?>
      <article class="post-card<?= $i === 0 && $page === 1 && $cat === '' ? ' featured' : '' ?>" lang="<?= e($p['lang']) ?>">
        <a href="<?= e(post_url($p)) ?>" class="post-cover" tabindex="-1" aria-hidden="true"><?php if ($p['cover']): ?><img src="<?= e(url('/uploads/blog/' . rawurlencode($p['cover']))) ?>" alt="" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" decoding="async"><?php else: ?><span class="post-cover-ph"><?= icon('file', 34) ?></span><?php endif; ?></a>
        <div class="post-card-body">
          <div class="post-meta"><?php if ($p['category']): ?><span class="badge plain"><?= e($p['category']) ?></span><?php endif; ?><span><?= e(date_fmt($p['published_at'])) ?></span><span>· <?= e(sprintf(t('%d min read'), reading_minutes($p['body']))) ?></span></div>
          <h2><a href="<?= e(post_url($p)) ?>"><?= e($p['title']) ?></a></h2>
          <?php if ($p['excerpt']): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?><nav class="row" style="justify-content:center;margin-top:28px;gap:6px" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn btn-sm <?= $i === $page ? 'btn-dark' : 'btn-light' ?>" href="<?= e(url('/blog?page=' . $i . ($cat !== '' ? '&c=' . rawurlencode($cat) : ''))) ?>"><?= $i ?></a><?php endfor; ?>
  </nav><?php endif; ?>
  <?php endif; ?>
</section>
