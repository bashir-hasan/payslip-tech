<?php $base = abs_url('/api/v1/verify/'); ?>
<div class="page-narrow prose">
  <span class="kicker"><?= e(t('For banks, NBFIs and agencies')) ?></span>
  <h1><?= e(t('Verification API')) ?></h1>
  <p><?= e(t('Check payslips automatically from your loan, visa or onboarding system. The API is free and needs no key.')) ?></p>

  <h2><?= e(t('Request')) ?></h2>
  <pre class="code">GET <?= e($base) ?>{CODE}

# <?= e(t('Example')) ?>

curl <?= e($base) ?>PS-7KQM-4XHD?verifier=bank</pre>
  <p><?= e(t('The optional "verifier" parameter (bank, embassy, landlord, employer, other) tells the employer what kind of organisation checked the payslip.')) ?></p>

  <h2><?= e(t('Response')) ?></h2>
  <pre class="code">{
  "ok": true,
  "code": "PS-7KQM-4XHD",
  "result": "valid",
  "checked_at": "2026-11-25T14:03:11+06:00",
  "payslip": {
    "employer": "Nodi Digital Ltd.",
    "employee_name": "Rahim Uddin",
    "employee_id": "ND-001",
    "designation": "Senior Software Engineer",
    "period": "2026-10",
    "currency": "BDT",
    "issued_at": "2026-10-31T17:00:00+06:00",
    "fingerprint": "3F9A 1C07 B2D4 88E1",
    "gross": 60000,
    "total_deductions": 4800,
    "net": 55200
  }
}</pre>

  <h2><?= e(t('Result values')) ?></h2>
  <ul>
    <li><b>valid</b> — <?= e(t('issued by the employer and unchanged.')) ?></li>
    <li><b>revoked</b> — <?= e(t('the employer withdrew it; includes revoked_at and revoke_reason.')) ?></li>
    <li><b>tampered</b> — <?= e(t('the stored record failed its integrity check. Treat as invalid.')) ?></li>
    <li><b>not_found</b> — <?= e(t('no issued payslip has this code.')) ?></li>
  </ul>
  <p><?= e(t('Amounts are only included when the employer allows them to be shown. Bank account numbers are never returned.')) ?></p>

  <h2><?= e(t('Limits')) ?></h2>
  <p><?= e(t('120 requests per hour per IP address. Contact us for higher limits.')) ?></p>
  <?php if ($se = setting('support_email')): ?><p><a class="btn btn-dark" href="mailto:<?= e($se) ?>?subject=API%20access"><?= icon('mail') ?> <?= e(t('Contact us about API access')) ?></a></p><?php endif; ?>
</div>
