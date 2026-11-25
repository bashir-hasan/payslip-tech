<h1><?= e(t('Reset your password')) ?></h1>
<p class="sub"><?= e(t('Enter your email and we will send you a link to set a new password.')) ?></p>
<form method="post" action="<?= e(url('/forgot')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="email"><?= e(t('Email')) ?></label><input class="input" type="email" id="email" name="email" required autofocus></div>
  <button class="btn btn-primary btn-lg btn-block"><?= e(t('Send reset link')) ?></button>
</form>
<p class="auth-alt"><a href="<?= e(url('/login')) ?>">← <?= e(t('Back to sign in')) ?></a></p>
