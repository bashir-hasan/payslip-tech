<?php send_security_headers(has_custom_scripts()); ?><!doctype html>
<html lang="<?= e(lang()) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', get_defined_vars() + ['context' => 'public']); ?></head>
<body>
<?= body_tracking_code() ?>
<?php if ($ann = setting('announcement')): ?><div class="announce"><?= e($ann) ?></div><?php endif; ?>
<header class="topbar-public">
  <div class="container">
    <?php partial('brand'); ?>
    <nav class="nav-public">
      <a class="nl" href="<?= e(url('/#how')) ?>"><?= e(t('How it works')) ?></a>
      <a class="nl" href="<?= e(url('/#pricing')) ?>"><?= e(t('Pricing')) ?></a>
      <a class="nl" href="<?= e(url('/blog')) ?>"><?= e(t('Blog')) ?></a>
      <a class="nl" href="<?= e(url('/developers')) ?>"><?= e(t('API')) ?></a>
      <a class="nl" href="<?= e(url('/verify')) ?>"><?= e(t('Verify a payslip')) ?></a>
      <?php partial('langswitch'); ?>
      <?php partial('themetoggle'); ?>
      <?php if ($cu = current_user()): ?>
        <a class="btn btn-dark btn-sm" href="<?= e(url(home_for($cu))) ?>"><?= e(t('Open dashboard')) ?></a>
      <?php else: ?>
        <a class="nl hide-m" href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
        <a class="btn btn-dark btn-sm hide-m" href="<?= e(url('/register')) ?>"><?= e(t('Start free')) ?></a>
      <?php endif; ?>
    </nav>
    <button class="btn btn-ghost btn-icon menu-toggle" data-public-menu aria-label="Menu"><?= icon('menu', 22) ?></button>
  </div>
</header>
<?php partial('flash'); ?>
<main><?= $content ?></main>
<footer class="footer">
  <div class="container">
    <div><?php partial('brand'); ?><p class="small" style="margin:10px 0 0"><?= e(t('Verified payslips & pay stubs, worldwide.')) ?> © <?= date('Y') ?></p></div>
    <nav>
      <a href="<?= e(url('/verify')) ?>"><?= e(t('Verify')) ?></a>
      <a href="<?= e(url('/blog')) ?>"><?= e(t('Blog')) ?></a>
      <a href="<?= e(url('/developers')) ?>"><?= e(t('API')) ?></a>
      <a href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy')) ?></a>
      <a href="<?= e(url('/terms')) ?>"><?= e(t('Terms')) ?></a>
      <?php if ($se = setting('support_email')): ?><a href="mailto:<?= e($se) ?>"><?= e(t('Contact')) ?></a><?php endif; ?>
    </nav>
  </div>
</footer>
<?php partial('chatwidget'); ?>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= footer_tracking_code() ?>
</body>
</html>
