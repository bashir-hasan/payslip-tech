<div class="verify-page">
  <div class="center" style="margin-bottom:28px">
    <span class="eyebrow"><?= icon('shield', 15) ?> <?= e(t('Free · No account needed')) ?></span>
    <h1><?= e(t('Verify a payslip')) ?></h1>
    <p class="muted" style="max-width:520px;margin:0 auto"><?= e(t('Scan the QR code on the payslip with your phone camera, or type the code printed below it.')) ?></p>
  </div>
  <div class="card card-pad">
    <form method="post" action="<?= e(url('/verify')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label class="label" for="code"><?= e(t('Verification code')) ?></label>
        <input class="input mono" id="code" name="code" value="<?= e($code) ?>" placeholder="PS-XXXX-XXXX" style="height:54px;font-size:20px;letter-spacing:.08em;text-transform:uppercase" autocomplete="off" autofocus required>
        <span class="hint"><?= e(t('You can also paste the full verification link.')) ?></span>
      </div>
      <div class="field">
        <label class="label" for="vt"><?= e(t('Who is verifying? (optional)')) ?></label>
        <select class="select" id="vt" name="verifier_type">
          <option value=""><?= e(t('Prefer not to say')) ?></option>
          <?php foreach (verifier_types() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select>
        <span class="hint"><?= e(t('Helps the employer understand how their payslips are used. We do not ask for your name.')) ?></span>
      </div>
      <button class="btn btn-primary btn-lg btn-block"><?= icon('shield') ?> <?= e(t('Verify payslip')) ?></button>
    </form>
  </div>
  <div class="grid grid-3" style="margin-top:20px">
    <div class="card card-pad"><b class="row" style="gap:8px;color:var(--ok)"><?= icon('check-circle') ?> <?= e(t('Genuine')) ?></b><p class="small muted" style="margin:6px 0 0"><?= e(t('Issued by the employer and unchanged since.')) ?></p></div>
    <div class="card card-pad"><b class="row" style="gap:8px;color:var(--warn)"><?= icon('ban') ?> <?= e(t('Revoked')) ?></b><p class="small muted" style="margin:6px 0 0"><?= e(t('The employer withdrew this payslip.')) ?></p></div>
    <div class="card card-pad"><b class="row" style="gap:8px;color:var(--bad)"><?= icon('x-circle') ?> <?= e(t('Not valid')) ?></b><p class="small muted" style="margin:6px 0 0"><?= e(t('No matching payslip, or its data was altered.')) ?></p></div>
  </div>
</div>
