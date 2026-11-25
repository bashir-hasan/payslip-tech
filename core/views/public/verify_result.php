<?php
$r = $res['result'];
if (($res['kind'] ?? '') === 'document') { include __DIR__ . '/verify_document.php'; return; }
$p = $res['payslip'] ?? null;
$cls = ['valid' => 'valid', 'revoked' => 'revoked'][$r] ?? 'invalid';
$snap = $p['snapshot'] ?? [];
?>
<div class="verify-page">
  <div class="result <?= e($cls) ?>">
    <div class="result-head">
      <div class="big-ic"><?= icon($r === 'valid' ? 'check' : ($r === 'revoked' ? 'ban' : 'x'), 30) ?></div>
      <div>
        <?php if ($r === 'valid'): ?>
          <h1><?= e(t('Genuine payslip')) ?></h1>
          <p><?= e(t('This payslip was issued by the employer below and has not been changed since.')) ?></p>
        <?php elseif ($r === 'revoked'): ?>
          <h1><?= e(t('Payslip revoked')) ?></h1>
          <p><?= e(t('The employer issued this payslip but later withdrew it. Do not accept it.')) ?></p>
        <?php elseif ($r === 'tampered'): ?>
          <h1><?= e(t('Integrity check failed')) ?></h1>
          <p><?= e(t('The stored record for this code does not match its fingerprint. Do not accept this payslip and contact the employer.')) ?></p>
        <?php else: ?>
          <h1><?= e(t('No payslip found')) ?></h1>
          <p><?= e(t('There is no issued payslip with this code. Check for typing mistakes. If the code is correct, the document is not genuine.')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($p && in_array($r, ['valid', 'revoked'], true)): ?>
      <dl class="kv" style="margin:0">
        <div><dt><?= e(t('Employer')) ?></dt><dd><?= e($snap['company']['name'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Pay period')) ?></dt><dd><?= e(period_label($p['period'])) ?></dd></div>
        <div><dt><?= e(t('Employee')) ?></dt><dd><?= e($snap['employee']['name'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Employee ID')) ?></dt><dd><?= e($snap['employee']['emp_code'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Designation')) ?></dt><dd><?= e($snap['employee']['designation'] ?: '—') ?></dd></div>
        <div><dt><?= e(t('Issued on')) ?></dt><dd><?= e(date_fmt($p['issued_at'], null, true)) ?></dd></div>
        <?php if ((int)$p['show_amounts'] === 1): ?>
          <div><dt><?= e(t('Gross earnings')) ?></dt><dd><?= e(money($p['gross'])) ?></dd></div>
          <div><dt><?= e(t('Total deductions')) ?></dt><dd><?= e(money($p['total_deductions'])) ?></dd></div>
        <?php endif; ?>
        <div><dt><?= e(t('Verification code')) ?></dt><dd class="mono"><?= e($p['code']) ?></dd></div>
        <div><dt><?= e(t('Digital fingerprint')) ?></dt><dd class="mono"><?= e(fingerprint_display($p['hash'])) ?></dd></div>
        <?php if ($r === 'revoked'): ?>
          <div class="full"><dt><?= e(t('Revoked')) ?></dt><dd><?= e(date_fmt($p['revoked_at'], null, true)) ?> — <?= e($p['revoke_reason']) ?></dd></div>
        <?php endif; ?>
      </dl>
      <?php if ((int)$p['show_amounts'] === 1): ?>
        <div class="net-line"><span class="muted"><?= e(t('Net pay')) ?></span><b><?= e(money($p['net'])) ?></b></div>
      <?php endif; ?>
      <div class="tip"><?= icon('alert') ?><div><?= e(t('Compare these details with the payslip you were given. If any name, month or amount is different, the document has been altered.')) ?></div></div>
      <?php partial('trust', ['trust' => company_trust((int)$p['company_id'])]); ?>
    <?php else: ?>
      <dl class="kv" style="margin:0"><div class="full"><dt><?= e(t('Code checked')) ?></dt><dd class="mono"><?= e($res['code'] ?: '—') ?></dd></div></dl>
    <?php endif; ?>
  </div>
  <div class="row" style="justify-content:center;margin-top:20px">
    <a class="btn btn-light" href="<?= e(url('/verify')) ?>"><?= icon('search') ?> <?= e(t('Check another payslip')) ?></a>
    <span class="small muted"><?= e(sprintf(t('Checked %s'), date_fmt(now(), null, true))) ?></span>
  </div>
  <p class="center small muted" style="margin-top:18px"><?= e(t('PaySlip.tech confirms that a payslip was issued through our platform by the named employer. It does not independently audit the employer\'s payroll.')) ?></p>
</div>
