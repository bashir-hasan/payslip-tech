<div class="page-head"><div><h1><?= e(t('Platform settings')) ?></h1><p><?= e(t('Site-wide options.')) ?></p></div></div>
<form method="post" action="<?= e(url('/admin/settings')) ?>" style="max-width:680px">
  <?= csrf_field() ?>
  <div class="card"><div class="card-body">
    <div class="form-grid">
      <div class="field"><label class="label"><?= e(t('Site name')) ?></label><input class="input" name="site_name" value="<?= e(setting('site_name', 'PaySlip.tech')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Support email')) ?></label><input class="input" type="email" name="support_email" value="<?= e(setting('support_email', '')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Legal name and address of the operator')) ?></label><input class="input" name="legal_entity" maxlength="200" value="<?= e(setting('legal_entity', '')) ?>" placeholder="Md Bashir Hasan Saikat, Dhaka, Bangladesh"><span class="hint"><?= e(t('Shown as the data controller in the Privacy Policy and the Data Processing Agreement.')) ?></span></div>
      <div class="field span-2"><label class="label"><?= e(t('Send emails from')) ?></label><input class="input" type="email" name="mail_from" value="<?= e(setting('mail_from', '')) ?>" placeholder="no-reply@payslip.tech"><span class="hint"><?= e(t('Create this mailbox in cPanel so emails are delivered reliably.')) ?></span></div>
      <div class="field span-2"><label class="label"><?= e(t('Announcement bar')) ?></label><input class="input" name="announcement" value="<?= e(setting('announcement', '')) ?>" maxlength="200" placeholder="<?= e(t('Optional message shown on the public site')) ?>"></div>
    </div>
    <label class="check" style="margin-bottom:12px"><input type="checkbox" name="allow_registration" value="1" <?= setting('allow_registration', '1') === '1' ? 'checked' : '' ?>> <span><b><?= e(t('Allow new company sign-ups')) ?></b></span></label>
    <label class="check"><input type="checkbox" name="demo_enabled" value="1" <?= setting('demo_enabled', '0') === '1' ? 'checked' : '' ?> <?= setting('demo_company_id') ? '' : 'disabled' ?>> <span><b><?= e(t('Enable one-click demo at /demo')) ?></b><br><span class="small muted"><?= e(setting('demo_company_id') ? t('Lets judges and visitors explore a sample company.') : t('No demo company was created during installation.')) ?></span></span></label>
  </div>
  </div>
  <div class="card" style="margin-top:18px"><div class="card-head"><h3><?= icon('mail') ?> <?= e(t('Email delivery')) ?></h3></div><div class="card-body">
    <p class="small muted" style="margin-top:0"><?= e(t('Most hosts block PHP mail(). Use SMTP: create a mailbox such as no-reply@payslip.tech in cPanel → Email Accounts, then enter its details. Gmail, Zoho, Brevo and Resend SMTP also work.')) ?></p>
    <div class="form-grid">
      <div class="field"><label class="label"><?= e(t('Send method')) ?></label><select class="select" name="mail_driver"><option value="smtp" <?= setting('mail_driver', 'mail') === 'smtp' ? 'selected' : '' ?>>SMTP (<?= e(t('recommended')) ?>)</option><option value="mail" <?= setting('mail_driver', 'mail') === 'mail' ? 'selected' : '' ?>>PHP mail()</option></select></div>
      <div class="field"><label class="label"><?= e(t('SMTP host')) ?></label><input class="input mono" name="smtp_host" value="<?= e(setting('smtp_host', '')) ?>" placeholder="mail.payslip.tech"></div>
      <div class="field"><label class="label"><?= e(t('Port')) ?></label><input class="input" name="smtp_port" type="number" value="<?= e(setting('smtp_port', '465')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Encryption')) ?></label><select class="select" name="smtp_secure"><?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => t('None')] as $k => $l): ?><option value="<?= e($k) ?>" <?= setting('smtp_secure', 'ssl') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label"><?= e(t('Username')) ?></label><input class="input mono" name="smtp_user" value="<?= e(setting('smtp_user', '')) ?>" placeholder="no-reply@payslip.tech" autocomplete="off"></div>
      <div class="field"><label class="label"><?= e(t('Password')) ?> <?= setting('smtp_pass') ? '<span class="badge badge-ok plain">' . e(t('saved')) . '</span>' : '' ?></label><input class="input" type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= e(setting('smtp_pass') ? t('Leave blank to keep') : '') ?>"></div>
    </div>
    <p class="small muted"><?= e(t('Tip: set "Send emails from" above to the same address as the SMTP username. In Cloudflare DNS, add the SPF, DKIM and DMARC records shown in cPanel → Email Deliverability so emails do not land in spam.')) ?></p>
    <label class="check"><input type="checkbox" name="send_test" value="1"> <?= e(t('Send a test email to me after saving')) ?></label>
  </div>
  <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= e(t('Save settings')) ?></button></div></div>
</form>
<div class="card card-pad" style="max-width:680px;margin-top:18px">
  <div class="fieldset-title"><?= e(t('Scheduler (auto payroll)')) ?></div>
  <p class="small muted" style="margin-top:0"><?= e(t('In cPanel → Cron Jobs, add this command to run once per hour:')) ?></p>
  <div class="copy-row"><input class="input" readonly data-select value="<?= e('/usr/local/bin/php ' . ROOT . '/cron.php >/dev/null 2>&1') ?>"><button type="button" class="btn btn-light" data-copy="<?= e('/usr/local/bin/php ' . ROOT . '/cron.php >/dev/null 2>&1') ?>" data-copied="✓"><?= icon('copy') ?></button></div>
  <p class="small muted"><?= e(t('Or, if your host only allows URLs, call this secret link hourly (keep it private):')) ?></p>
  <div class="copy-row"><input class="input" readonly data-select value="<?= e(abs_url('/cron/' . cron_token())) ?>"><button type="button" class="btn btn-light" data-copy="<?= e(abs_url('/cron/' . cron_token())) ?>" data-copied="✓"><?= icon('copy') ?></button></div>
  <p class="small" style="margin:10px 0 0"><?= setting('cron_last_run') ? e(sprintf(t('Last run: %s'), time_ago((string)setting('cron_last_run')))) : '<span style="color:var(--warn)">' . e(t('Not run yet')) . '</span>' ?></p>
</div>
<div class="card card-pad" style="max-width:680px;margin-top:18px">
  <div class="fieldset-title"><?= e(t('System')) ?></div>
  <p class="small muted" style="margin:0">PHP <?= e(PHP_VERSION) ?> · PaySlip.tech v<?= e(APP_VERSION) ?> · <?= e(t('Timezone')) ?> <?= e(date_default_timezone_get()) ?></p>
</div>
