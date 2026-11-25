<h1><?= e(t('Welcome back')) ?></h1>
<p class="sub"><?= e(t('Sign in to manage payroll or view your payslips.')) ?></p>
<form method="post" action="<?= e(url('/login')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="email"><?= e(t('Email')) ?></label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required autofocus></div>
  <div class="field"><div class="row between"><label class="label" for="password"><?= e(t('Password')) ?></label><a class="small" href="<?= e(url('/forgot')) ?>"><?= e(t('Forgot password?')) ?></a></div><input class="input" type="password" id="password" name="password" autocomplete="current-password" required></div>
  <button class="btn btn-primary btn-lg btn-block"><?= e(t('Sign in')) ?></button>
</form>
<?php if (setting('demo_enabled', '0') === '1'): ?>
<a class="btn btn-light btn-block" style="margin-top:10px" href="<?= e(url('/demo')) ?>"><?= icon('eye') ?> <?= e(t('Explore the demo company')) ?></a>
<?php endif; ?>
<p class="auth-alt"><?= e(t('New to PaySlip.tech?')) ?> <a href="<?= e(url('/register')) ?>"><?= e(t('Create a free account')) ?></a></p>
<?php clear_old(); ?>
