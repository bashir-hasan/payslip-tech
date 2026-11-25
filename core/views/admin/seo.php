<?php
$v = fn($k) => (string)setting($k, '');
$pages = ['home' => t('Home page'), 'verify' => t('Verify page'), 'developers' => t('API page'), 'register' => t('Sign-up page'), 'login' => t('Sign-in page'), 'privacy' => t('Privacy Policy'), 'terms' => t('Terms of Service')];
$og = setting('seo_og_image');
?>
<div class="page-head"><div><h1><?= e(t('SEO & AI search')) ?></h1><p><?= e(t('Control how PaySlip.tech appears on Google, Bing, social media and AI answer engines (ChatGPT, Gemini, Perplexity, Claude).')) ?></p></div>
  <div class="row"><a class="btn btn-light btn-sm" href="<?= e(url('/sitemap.xml')) ?>" target="_blank">sitemap.xml</a><a class="btn btn-light btn-sm" href="<?= e(url('/robots.txt')) ?>" target="_blank">robots.txt</a><a class="btn btn-light btn-sm" href="<?= e(url('/llms.txt')) ?>" target="_blank">llms.txt</a></div>
</div>

<div class="alert alert-info"><?= icon('check-circle') ?><div><?= e(t('Already automatic: unique titles and descriptions, canonical URLs, English/Bangla hreflang, Open Graph and Twitter cards, JSON-LD schema (Organization, WebSite, SoftwareApplication, FAQPage, BreadcrumbList, TechArticle), XML sitemap, robots.txt with AI crawler rules, and llms.txt. Fields below are optional overrides.')) ?></div></div>

<form method="post" action="<?= e(url('/admin/seo')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid grid-2" style="align-items:start">
    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Site defaults')) ?></div>
      <div class="field"><label class="label"><?= e(t('Default meta description (English)')) ?></label><textarea class="textarea" name="seo_default_description" maxlength="300" rows="3"><?= e($v('seo_default_description')) ?></textarea><span class="hint"><?= e(t('Aim for 140–160 characters.')) ?></span></div>
      <div class="field"><label class="label"><?= e(t('Default meta description (Bangla)')) ?></label><textarea class="textarea" name="seo_default_description_bn" maxlength="300" rows="3"><?= e($v('seo_default_description_bn')) ?></textarea></div>
      <div class="field"><label class="label"><?= e(t('Social share image')) ?></label>
        <div class="row" style="gap:14px"><img src="<?= e(og_image_url()) ?>" alt="" style="width:160px;border-radius:10px;border:1px solid var(--line)"><input class="input" type="file" name="og_image" accept="image/png,image/jpeg" style="padding-top:9px;flex:1;min-width:180px"></div>
        <span class="hint"><?= e(t('PNG or JPG, 1200 × 630 px. A default branded image is used if empty.')) ?></span>
        <?php if ($og): ?><label class="check small" style="margin-top:8px"><input type="checkbox" name="remove_og" value="1"> <?= e(t('Use the default image')) ?></label><?php endif; ?>
      </div>
    </div></div>

    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Brand & entity (for Google and AI engines)')) ?></div>
      <div class="field"><label class="label"><?= e(t('Founder name')) ?></label><input class="input" name="seo_founder" value="<?= e($v('seo_founder')) ?>" placeholder="Bashir Hasan"></div>
      <div class="field"><label class="label"><?= e(t('X (Twitter) username')) ?></label><div class="input-group"><span class="prefix">@</span><input class="input" name="seo_twitter" value="<?= e($v('seo_twitter')) ?>"></div></div>
      <div class="field"><label class="label"><?= e(t('Official profiles (one URL per line)')) ?></label><textarea class="textarea" name="seo_same_as" rows="4" placeholder="https://www.linkedin.com/company/…&#10;https://www.facebook.com/…&#10;https://github.com/…"><?= e($v('seo_same_as')) ?></textarea><span class="hint"><?= e(t('Linking your LinkedIn, Facebook, GitHub and X pages helps search and AI engines recognise PaySlip.tech as one brand.')) ?></span></div>
      <div class="field"><label class="label"><?= e(t('Extra notes for AI engines (llms.txt)')) ?></label><textarea class="textarea" name="llms_extra" rows="4" maxlength="4000"><?= e($v('llms_extra')) ?></textarea><span class="hint"><?= e(t('Plain text or Markdown, e.g. press mentions, partners, roadmap.')) ?></span></div>
      <label class="check"><input type="checkbox" name="seo_noindex" value="1" <?= $v('seo_noindex') === '1' ? 'checked' : '' ?>> <span><b><?= e(t('Hide the whole site from search engines')) ?></b><br><span class="small muted"><?= e(t('Only for testing. Leave unticked when live.')) ?></span></span></label>
    </div></div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="card-head"><h3><?= e(t('Page titles and descriptions')) ?></h3><span class="small muted"><?= e(t('Leave blank to use the built-in text')) ?></span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('Page')) ?></th><th><?= e(t('Title (max 60–65 chars)')) ?></th><th><?= e(t('Description (max 160 chars)')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($pages as $key => $label): ?>
        <tr>
          <td><b><?= e($label) ?></b><div class="small muted"><?= e(SEO_PAGES[$key]) ?></div></td>
          <td style="min-width:240px"><input class="input" name="seo_<?= e($key) ?>_title" value="<?= e($v("seo_{$key}_title")) ?>" maxlength="90" placeholder="English"><input class="input" style="margin-top:6px" name="seo_<?= e($key) ?>_title_bn" value="<?= e($v("seo_{$key}_title_bn")) ?>" maxlength="90" placeholder="বাংলা"></td>
          <td style="min-width:300px"><input class="input" name="seo_<?= e($key) ?>_description" value="<?= e($v("seo_{$key}_description")) ?>" maxlength="300" placeholder="English"><input class="input" style="margin-top:6px" name="seo_<?= e($key) ?>_description_bn" value="<?= e($v("seo_{$key}_description_bn")) ?>" maxlength="300" placeholder="বাংলা"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="row" style="justify-content:flex-end;margin-top:18px"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save SEO settings')) ?></button></div>
</form>
