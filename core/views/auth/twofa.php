<h1><?= e(t('Two-factor authentication')) ?></h1>
<p class="sub"><?= e(t('Open your authenticator app (Google Authenticator, Microsoft Authenticator, Authy…) and enter the 6-digit code for PaySlip.tech.')) ?></p>
<form method="post" action="<?= e(url('/login/2fa')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="code"><?= e(t('Authentication code')) ?></label>
    <input class="input mono" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="123456" required autofocus style="height:54px;font-size:22px;letter-spacing:.25em;text-align:center">
    <span class="hint"><?= e(t('Lost your phone? Enter one of your recovery codes instead (format xxxxx-xxxxx).')) ?></span>
  </div>
  <button class="btn btn-primary btn-lg btn-block"><?= icon('lock') ?> <?= e(t('Verify and sign in')) ?></button>
</form>
<p class="auth-alt"><a href="<?= e(url('/login')) ?>">← <?= e(t('Back to sign in')) ?></a></p>
