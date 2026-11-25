<?php
$d = $res['document'] ?? null;
$cls = ['valid' => 'valid', 'revoked' => 'revoked'][$r] ?? 'invalid';
$snap = $d['snapshot'] ?? [];
$name = $d ? (doc_types()[$d['type']] ?? t('Document')) : t('Document');
?>
<div class="verify-page">
  <div class="result <?= e($cls) ?>">
    <div class="result-head">
      <div class="big-ic"><?= icon($r === 'valid' ? 'check' : ($r === 'revoked' ? 'ban' : 'x'), 30) ?></div>
      <div>
        <?php if ($r === 'valid'): ?><h1><?= e(sprintf(t('Genuine %s'), mb_strtolower($name))) ?></h1><p><?= e(t('This document was issued by the employer below and has not been changed since.')) ?></p>
        <?php elseif ($r === 'revoked'): ?><h1><?= e(t('Document revoked')) ?></h1><p><?= e(t('The employer issued this document but later withdrew it. Do not accept it.')) ?></p>
        <?php elseif ($r === 'tampered'): ?><h1><?= e(t('Integrity check failed')) ?></h1><p><?= e(t('The stored record for this code does not match its fingerprint. Do not accept this document and contact the employer.')) ?></p>
        <?php else: ?><h1><?= e(t('No document found')) ?></h1><p><?= e(t('There is no issued document with this code. Check for typing mistakes. If the code is correct, the document is not genuine.')) ?></p><?php endif; ?>
      </div>
    </div>
    <?php if ($d && in_array($r, ['valid', 'revoked'], true)): ?>
      <dl class="kv" style="margin:0">
        <div><dt><?= e(t('Document')) ?></dt><dd><?= e($name) ?><?= $d['year'] ? ' ' . e(num($d['year'])) : '' ?></dd></div>
        <div><dt><?= e(t('Employer')) ?></dt><dd><?= e($snap['company']['name'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Employee')) ?></dt><dd><?= e($snap['employee']['name'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Employee ID')) ?></dt><dd><?= e($snap['employee']['emp_code'] ?? '') ?></dd></div>
        <div><dt><?= e(t('Designation')) ?></dt><dd><?= e(($snap['employee']['designation'] ?? '') ?: '—') ?></dd></div>
        <div><dt><?= e(t('Joining date')) ?></dt><dd><?= e(date_fmt($snap['employee']['join_date'] ?? null)) ?></dd></div>
        <div><dt><?= e(t('Employment status')) ?></dt><dd><?= e(($snap['employee']['status'] ?? 'active') === 'active' ? t('Currently employed') : t('No longer employed')) ?></dd></div>
        <div><dt><?= e(t('Issued on')) ?></dt><dd><?= e(date_fmt($d['issued_at'], null, true)) ?></dd></div>
        <?php if (!empty($snap['addressed_to'])): ?><div><dt><?= e(t('Addressed to')) ?></dt><dd><?= e($snap['addressed_to']) ?></dd></div><?php endif; ?>
        <?php if (!empty($snap['salary'])): ?>
          <div><dt><?= e(t('Monthly gross salary')) ?></dt><dd><?= e(money($snap['salary']['gross'])) ?></dd></div>
          <div><dt><?= e(t('Monthly net pay')) ?></dt><dd><?= e(money($snap['salary']['net'])) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($snap['totals']) && $d['type'] === 'income_statement'): ?>
          <div><dt><?= e(sprintf(t('Net pay for %s'), num($snap['year']))) ?></dt><dd><?= e(money($snap['totals']['net'])) ?> (<?= e(sprintf(t('%s months'), num(count($snap['months'])))) ?>)</dd></div>
          <div><dt><?= e(t('Gross for the year')) ?></dt><dd><?= e(money($snap['totals']['gross'])) ?></dd></div>
        <?php endif; ?>
        <?php $dt = $snap['data'] ?? []; if ($d['type'] === 'increment_letter' && $dt): ?>
          <div><dt><?= e(t('New monthly gross')) ?></dt><dd><?= e(money($dt['new_gross'] ?? 0)) ?> (<?= e(sprintf(t('from %s'), period_label($dt['effective'] ?? ''))) ?>)</dd></div>
          <div><dt><?= e(t('Previous monthly gross')) ?></dt><dd><?= e(money($dt['old_gross'] ?? 0)) ?></dd></div>
        <?php elseif ($d['type'] === 'final_settlement' && $dt): ?>
          <div><dt><?= e(t('Last working day')) ?></dt><dd><?= e(date_fmt($dt['last_day'] ?? null)) ?></dd></div>
          <div><dt><?= e(t('Net payable')) ?></dt><dd><?= e(money($dt['net'] ?? 0)) ?></dd></div>
        <?php elseif ($d['type'] === 'tax_certificate' && !empty($snap['tax_year'])): ?>
          <div><dt><?= e(t('Income year')) ?></dt><dd><?= e($snap['tax_year']['label']) ?></dd></div>
          <div><dt><?= e(t('Tax deducted')) ?></dt><dd><?= e(money($snap['totals']['tax'])) ?></dd></div>
        <?php endif; ?>
        <div><dt><?= e(t('Verification code')) ?></dt><dd class="mono"><?= e($d['code']) ?></dd></div>
        <div><dt><?= e(t('Digital fingerprint')) ?></dt><dd class="mono"><?= e(fingerprint_display($d['hash'])) ?></dd></div>
        <?php if ($r === 'revoked'): ?><div class="full"><dt><?= e(t('Revoked')) ?></dt><dd><?= e(date_fmt($d['revoked_at'], null, true)) ?> — <?= e($d['revoke_reason']) ?></dd></div><?php endif; ?>
      </dl>
      <div class="tip"><?= icon('alert') ?><div><?= e(t('Compare these details with the document you were given. If any name, date or amount is different, it has been altered.')) ?></div></div>
      <?php partial('trust', ['trust' => company_trust((int)$d['company_id'])]); ?>
    <?php else: ?>
      <dl class="kv" style="margin:0"><div class="full"><dt><?= e(t('Code checked')) ?></dt><dd class="mono"><?= e($res['code'] ?: '—') ?></dd></div></dl>
    <?php endif; ?>
  </div>
  <div class="row" style="justify-content:center;margin-top:20px">
    <a class="btn btn-light" href="<?= e(url('/verify')) ?>"><?= icon('search') ?> <?= e(t('Check another code')) ?></a>
    <span class="small muted"><?= e(sprintf(t('Checked %s'), date_fmt(now(), null, true))) ?></span>
  </div>
  <p class="center small muted" style="margin-top:18px"><?= e(t('PaySlip.tech confirms that a document was issued through our platform by the named employer. It does not independently audit the employer\'s records.')) ?></p>
</div>
