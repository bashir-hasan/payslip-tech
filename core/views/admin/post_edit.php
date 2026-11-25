<?php $live = $p['id'] && $p['status'] === 'published'; $pubVal = $p['published_at'] ? date('Y-m-d\TH:i', strtotime((string)$p['published_at'])) : ''; ?>
<div class="page-head"><div><a class="small" href="<?= e(url('/admin/blog')) ?>">← <?= e(t('Blog')) ?></a><h1 style="margin-top:6px"><?= e($p['id'] ? t('Edit article') : t('New article')) ?></h1></div>
  <?php if ($p['id']): ?><a class="btn btn-light" href="<?= e(url('/blog/' . rawurlencode($p['slug']))) ?>" target="_blank"><?= icon('globe') ?> <?= e($live ? t('View article') : t('Preview')) ?></a><?php endif; ?>
</div>
<form method="post" action="<?= e(url('/admin/blog/' . ($p['id'] ?: 'new'))) ?>" enctype="multipart/form-data" class="post-editor">
  <?= csrf_field() ?>
  <div class="stack">
    <div class="card card-pad">
      <div class="field"><label class="label"><?= e(t('Title')) ?></label><input class="input input-lg" name="title" value="<?= e($p['title']) ?>" maxlength="200" required placeholder="<?= e(t('e.g. How to check if a payslip is real')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Short summary (shown under the title and in the blog list)')) ?></label><textarea class="textarea" name="excerpt" rows="2" maxlength="400"><?= e((string)$p['excerpt']) ?></textarea></div>
      <div class="field"><label class="label"><?= e(t('Article')) ?></label>
        <textarea class="textarea mono md-editor" name="body" rows="26" required><?= e($p['body']) ?></textarea>
        <details class="small" style="margin-top:8px"><summary><?= e(t('Formatting help')) ?></summary>
          <pre class="code small">## Section heading      ### Smaller heading
**bold**   *italic*   `code`
[link text](/verify)   [external](https://example.com)
![image description](https://…/image.jpg)
- bullet list          1. numbered list
> quote
| Column | Column |
|---|---|
| cell | cell |
---   (divider)
[[cta]]   (sign-up box)</pre>
          <p class="muted"><?= e(t('HTML and scripts are not allowed in articles, for security.')) ?></p>
        </details>
      </div>
      <div class="field"><label class="label"><?= e(t('FAQ (appears at the end and as Google FAQ rich results)')) ?></label><textarea class="textarea" name="faq" rows="6" placeholder="Q: How do I verify a payslip?&#10;A: Scan the QR code or enter the code at payslip.tech/verify."><?= e((string)$p['faq']) ?></textarea><span class="hint"><?= e(t('One question per "Q:" line, answer on the next "A:" line. Bangla: প্রশ্ন: / উত্তর:')) ?></span></div>
    </div>
  </div>
  <div class="stack post-side">
    <div class="card card-pad">
      <div class="fieldset-title"><?= e(t('Publish')) ?></div>
      <div class="field"><label class="label"><?= e(t('Status')) ?></label><select class="select" name="status"><option value="draft" <?= $p['status'] !== 'published' ? 'selected' : '' ?>><?= e(t('Draft')) ?></option><option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>><?= e(t('Published')) ?></option></select></div>
      <div class="field"><label class="label"><?= e(t('Publish date (future date = scheduled)')) ?></label><input class="input" type="datetime-local" name="published_at" value="<?= e($pubVal) ?>"></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Language')) ?></label><select class="select" name="lang"><option value="en" <?= $p['lang'] === 'en' ? 'selected' : '' ?>>English</option><option value="bn" <?= $p['lang'] === 'bn' ? 'selected' : '' ?>>বাংলা</option></select></div>
        <div class="field"><label class="label"><?= e(t('Category')) ?></label><input class="input" name="category" value="<?= e((string)$p['category']) ?>" list="cats" maxlength="80"><datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
      </div>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> <?= e(t('Save')) ?></button>
    </div>
    <div class="card card-pad">
      <div class="fieldset-title"><?= e(t('Search engines')) ?></div>
      <div class="field"><label class="label"><?= e(t('Focus keyword')) ?></label><input class="input" name="keyword" value="<?= e((string)$p['keyword']) ?>" maxlength="120" placeholder="<?= e(t('e.g. verify payslip')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('SEO title')) ?> <span class="muted small" data-count-for="meta_title"></span></label><input class="input" name="meta_title" value="<?= e((string)$p['meta_title']) ?>" maxlength="200" data-count placeholder="<?= e(t('Defaults to the title')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Meta description')) ?> <span class="muted small" data-count-for="meta_description"></span></label><textarea class="textarea" name="meta_description" rows="3" maxlength="300" data-count placeholder="<?= e(t('Defaults to the summary')) ?>"><?= e((string)$p['meta_description']) ?></textarea></div>
      <div class="field"><label class="label"><?= e(t('URL slug')) ?></label><div class="input-prefix"><span>/blog/</span><input class="input mono" name="slug" value="<?= e($p['slug']) ?>" maxlength="160" placeholder="<?= e(t('auto from title')) ?>"></div></div>
      <div class="field"><label class="label"><?= e(t('Cover image (1200×630 recommended)')) ?></label>
        <?php if ($p['cover']): ?><img src="<?= e(url('/uploads/blog/' . rawurlencode($p['cover']))) ?>" alt="" style="width:100%;border-radius:10px;margin-bottom:8px"><label class="check small"><input type="checkbox" name="remove_cover" value="1"> <?= e(t('Remove cover')) ?></label><?php endif; ?>
        <input class="input" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></div>
    </div>
    <?php if ($checks): $ok = count(array_filter($checks, fn($c) => $c[0])); ?>
    <div class="card card-pad">
      <div class="fieldset-title row" style="justify-content:space-between"><span><?= e(t('SEO checklist')) ?></span><span class="seo-score <?= $ok / count($checks) >= .8 ? 'good' : ($ok / count($checks) >= .5 ? 'ok' : 'low') ?>"><?= e((string)round(100 * $ok / count($checks))) ?></span></div>
      <ul class="seo-checks"><?php foreach ($checks as [$pass, $label]): ?><li class="<?= $pass ? 'pass' : 'fail' ?>"><?= icon($pass ? 'check' : 'x', 15) ?><span><?= e($label) ?></span></li><?php endforeach; ?></ul>
      <p class="small muted" style="margin:8px 0 0"><?= e(t('Updated each time you save.')) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($p['id']): ?>
    <div class="card card-pad"><button class="btn btn-danger btn-block" name="action" value="delete" formnovalidate data-confirm="<?= e(t('Delete this article permanently?')) ?>"><?= icon('trash') ?> <?= e(t('Delete article')) ?></button></div>
    <?php endif; ?>
  </div>
</form>
