<?php $v = fn($k) => (string)setting($k, ''); ?>
<div class="page-head"><div><h1><?= e(t('Code & tracking')) ?></h1><p><?= e(t('Add analytics, verification tags and any custom HTML, CSS or JavaScript without editing files.')) ?></p></div></div>

<div class="alert alert-warn"><?= icon('alert') ?><div><?= e(t('Only paste code from sources you trust. Custom code runs on your public pages and can read what visitors type there. It never runs on payslip documents or the payslip pages of signed-in users.')) ?></div></div>

<div class="alert alert-info"><?= icon('shield') ?><div><?= e(t('GDPR: analytics, pixels and custom code below load only after a visitor clicks "Accept all" in the cookie banner. The banner appears automatically once you add any of them.')) ?></div></div>
<form method="post" action="<?= e(url('/admin/code')) ?>">
  <?= csrf_field() ?>
  <div class="grid grid-2" style="align-items:start">
    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Analytics (just paste the ID)')) ?></div>
      <div class="field"><label class="label">Google Analytics 4 — Measurement ID</label><input class="input mono" name="ga4_id" value="<?= e($v('ga4_id')) ?>" placeholder="G-XXXXXXXXXX"></div>
      <div class="field"><label class="label">Google Tag Manager — Container ID</label><input class="input mono" name="gtm_id" value="<?= e($v('gtm_id')) ?>" placeholder="GTM-XXXXXXX"></div>
      <div class="field"><label class="label">Meta (Facebook) Pixel ID</label><input class="input mono" name="fb_pixel_id" value="<?= e($v('fb_pixel_id')) ?>" placeholder="123456789012345"></div>
      <div class="field"><label class="label">Microsoft Clarity — Project ID</label><input class="input mono" name="clarity_id" value="<?= e($v('clarity_id')) ?>" placeholder="abcd1234ef"></div>
    </div></div>
    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Site verification (paste only the content value)')) ?></div>
      <div class="field"><label class="label">Google Search Console</label><input class="input mono" name="google_site_verification" value="<?= e($v('google_site_verification')) ?>" placeholder="abc123…"><span class="hint">&lt;meta name="google-site-verification" content="<b>this part</b>"&gt;</span></div>
      <div class="field"><label class="label">Bing Webmaster Tools</label><input class="input mono" name="bing_site_verification" value="<?= e($v('bing_site_verification')) ?>"></div>
      <div class="field"><label class="label">Facebook domain verification</label><input class="input mono" name="fb_domain_verification" value="<?= e($v('fb_domain_verification')) ?>"></div>
      <label class="check" style="margin-top:8px"><input type="checkbox" name="code_in_app" value="1" <?= $v('code_in_app') === '1' ? 'checked' : '' ?>> <span><b><?= e(t('Also load on dashboards (employer, employee, admin)')) ?></b><br><span class="small muted"><?= e(t('Off by default for privacy and security. Never loads on payslip documents.')) ?></span></span></label>
    </div></div>
  </div>

  <div class="card" style="margin-top:18px"><div class="card-body">
    <div class="fieldset-title"><?= e(t('Custom code snippets')) ?></div>
    <div class="field"><label class="label"><?= e(t('Inside <head>')) ?></label><textarea class="textarea mono" name="code_head" rows="6" spellcheck="false" placeholder="&lt;script&gt;…&lt;/script&gt; or &lt;style&gt;…&lt;/style&gt; or &lt;meta …&gt;"><?= e($v('code_head')) ?></textarea></div>
    <div class="field"><label class="label"><?= e(t('Right after <body> opens')) ?></label><textarea class="textarea mono" name="code_body" rows="4" spellcheck="false"><?= e($v('code_body')) ?></textarea></div>
    <div class="field"><label class="label"><?= e(t('Before </body> closes (chat widgets, etc.)')) ?></label><textarea class="textarea mono" name="code_footer" rows="6" spellcheck="false"><?= e($v('code_footer')) ?></textarea></div>
  </div></div>
  <div class="row" style="justify-content:flex-end;margin-top:18px"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save code settings')) ?></button></div>
</form>
