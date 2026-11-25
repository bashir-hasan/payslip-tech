<?php if (!has_custom_scripts()) return; $asked = consent_state() !== null; ?>
<div class="consent" data-consent role="dialog" aria-modal="false" aria-labelledby="consent-title" <?= $asked ? 'hidden' : '' ?>>
  <div class="consent-inner">
    <div>
      <b id="consent-title"><?= e(t('Cookies on PaySlip.tech')) ?></b>
      <p><?= e(t('We use essential cookies to keep you signed in. With your permission we also use analytics cookies to see which pages help people. Payslips and documents never load these.')) ?> <a href="<?= e(url('/cookies')) ?>"><?= e(t('Cookie Policy')) ?></a></p>
    </div>
    <div class="consent-actions">
      <button type="button" class="btn btn-light" data-consent-choice="essential"><?= e(t('Essential only')) ?></button>
      <button type="button" class="btn btn-primary" data-consent-choice="all"><?= e(t('Accept all')) ?></button>
    </div>
  </div>
</div>
