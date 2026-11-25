<?php $tabs = ['all' => [t('All'), (int)$counts['a']], 'published' => [t('Published'), (int)$counts['p']], 'scheduled' => [t('Scheduled'), (int)$counts['s']], 'draft' => [t('Drafts'), (int)$counts['d']]]; ?>
<div class="page-head"><div><h1><?= e(t('Blog')) ?></h1><p><?= e(t('Write guides that bring visitors from Google and AI search. Articles get SEO titles, schema, FAQ rich results, the sitemap and an RSS feed automatically.')) ?></p></div>
  <div class="row" style="gap:8px"><a class="btn btn-light" href="<?= e(url('/blog')) ?>" target="_blank"><?= icon('globe') ?> <?= e(t('View blog')) ?></a><a class="btn btn-primary" href="<?= e(url('/admin/blog/new')) ?>"><?= icon('plus') ?> <?= e(t('New article')) ?></a></div>
</div>
<div class="card">
  <div class="toolbar"><div class="tabs"><?php foreach ($tabs as $k => [$l, $n]): ?><a href="<?= e(url('/admin/blog?f=' . $k)) ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= e($l) ?> (<?= e(num($n)) ?>)</a><?php endforeach; ?></div></div>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Title')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('SEO')) ?></th><th class="num"><?= e(t('Views')) ?></th><th class="num"><?= e(t('Date')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $sched = $r['status'] === 'published' && strtotime((string)$r['published_at']) > time(); ?>
      <tr>
        <td class="first"><a href="<?= e(url('/admin/blog/' . $r['id'])) ?>" style="color:inherit"><b><?= e($r['title']) ?></b></a> <span class="badge plain"><?= e(strtoupper($r['lang'])) ?></span><div class="small muted">/blog/<?= e($r['slug']) ?><?= $r['category'] ? ' · ' . e($r['category']) : '' ?></div></td>
        <td data-l="<?= e(t('Status')) ?>"><span class="badge <?= $r['status'] === 'published' ? ($sched ? 'badge-warn' : 'badge-ok') : '' ?>"><?= e($r['status'] === 'published' ? ($sched ? t('Scheduled') : t('Published')) : t('Draft')) ?></span></td>
        <td data-l="<?= e(t('SEO')) ?>"><span class="seo-score <?= $r['score'] >= 80 ? 'good' : ($r['score'] >= 50 ? 'ok' : 'low') ?>"><?= e((string)$r['score']) ?></span></td>
        <td class="num" data-l="<?= e(t('Views')) ?>"><?= e(num($r['views'])) ?></td>
        <td class="num small muted" data-l="<?= e(t('Date')) ?>"><?= e(date_fmt($r['published_at'] ?: $r['updated_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted center"><?= e(t('No articles here yet.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
