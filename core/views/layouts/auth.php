<?php send_security_headers(has_custom_scripts()); ?><!doctype html>
<html lang="<?= e(lang()) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', get_defined_vars() + ['context' => 'public']); ?></head>
<body>
<?= body_tracking_code() ?>
<?php partial('flash'); ?>
<div class="auth">
  <aside class="auth-side">
    <?php partial('brand'); ?>
    <div style="position:relative;z-index:1">
      <h2><?= e(t('Payslips that prove themselves.')) ?></h2>
      <ul>
        <li><?= icon('check') ?><span><?= e(t('Run monthly payroll in minutes, in 20 currencies')) ?></span></li>
        <li><?= icon('check') ?><span><?= e(t('Every payslip gets a QR code and a tamper-proof signature')) ?></span></li>
        <li><?= icon('check') ?><span><?= e(t('Banks, landlords and embassies verify it free in one scan')) ?></span></li>
      </ul>
    </div>
    <p class="small" style="color:#7f9792;position:relative;z-index:1">© <?= date('Y') ?> <?= e(site_name()) ?></p>
  </aside>
  <main class="auth-main">
    <div class="auth-box">
      <div class="row between" style="margin-bottom:28px"><span></span><span class="row" style="gap:8px"><?php partial('themetoggle'); ?><?php partial('langswitch'); ?></span></div>
      <?= $content ?>
    </div>
  </main>
</div>
<?php partial('chatwidget'); ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= footer_tracking_code() ?>
</body>
</html>
