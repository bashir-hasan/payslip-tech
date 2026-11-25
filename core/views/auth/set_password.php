<h1><?= e($title) ?></h1>
<p class="sub"><?= e($intro) ?></p>
<form method="post" action="<?= e(url($action)) ?>">
  <?= csrf_field() ?>
  <div class="field"><label class="label"><?= e(t('Email')) ?></label><input class="input" value="<?= e($u['email']) ?>" disabled></div>
  <div class="field"><label class="label" for="password"><?= e(t('New password')) ?></label><input class="input" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus></div>
  <div class="field"><label class="label" for="password_confirm"><?= e(t('Confirm password')) ?></label><input class="input" type="password" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password" required></div>
  <button class="btn btn-primary btn-lg btn-block"><?= e(t('Save password')) ?></button>
</form>
