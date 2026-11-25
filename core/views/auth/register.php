<h1><?= e(t('Create your company account')) ?></h1>
<p class="sub"><?= e(t('Free for up to 5 employees. No card needed. Takes less than a minute.')) ?></p>
<form method="post" action="<?= e(url('/register')) ?>">
  <?= csrf_field() ?>
  <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
  <div class="field"><label class="label" for="company"><?= e(t('Company name')) ?></label><input class="input" id="company" name="company" value="<?= e(old('company')) ?>" required autofocus></div>
  <div class="field"><label class="label" for="country"><?= e(t('Country')) ?></label><select class="select" id="country" name="country"><?php $dc = old('country', default_country()); foreach (countries() as $code => [$cn, $cur]): ?><option value="<?= e($code) ?>" <?= $dc === $code ? 'selected' : '' ?>><?= e($cn) ?> (<?= e($cur) ?>)</option><?php endforeach; ?></select><span class="hint"><?= e(t('Sets your currency and local payslip terms. You can change it later.')) ?></span></div>
  <div class="field"><label class="label" for="name"><?= e(t('Your name')) ?></label><input class="input" id="name" name="name" value="<?= e(old('name')) ?>" autocomplete="name" required></div>
  <div class="field"><label class="label" for="email"><?= e(t('Work email')) ?></label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required></div>
  <div class="field"><label class="label" for="password"><?= e(t('Password')) ?></label><input class="input" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required><span class="hint"><?= e(t('At least 8 characters.')) ?></span></div>
  <div class="field"><label class="check"><input type="checkbox" name="agree" value="1" required> <span><?= t('I agree to the') ?> <a href="<?= e(url('/terms')) ?>" target="_blank"><?= e(t('Terms')) ?></a> <?= t('and') ?> <a href="<?= e(url('/privacy')) ?>" target="_blank"><?= e(t('Privacy Policy')) ?></a>.</span></label></div>
  <button class="btn btn-primary btn-lg btn-block"><?= e(t('Create account')) ?></button>
</form>
<p class="auth-alt"><?= e(t('Already have an account?')) ?> <a href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a></p>
<?php clear_old(); ?>
