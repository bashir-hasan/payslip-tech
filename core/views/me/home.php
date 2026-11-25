<div class="page-head">
  <div><h1><?= e(t('My payslips')) ?></h1><p><?= e(sprintf(t('%s · %s'), $e['designation'] ?: $e['emp_code'], $e['company_name'])) ?></p></div>
</div>
<?php $last = $slips[0] ?? null; ?>
<div class="grid grid-3" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('wallet', 16) ?> <?= e(t('Latest net pay')) ?></div><div class="v"><?= $last ? e(money($last['net'])) : '—' ?></div><div class="d"><?= $last ? e(period_label($last['period'])) : e(t('No payslips yet')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('file', 16) ?> <?= e(t('Payslips')) ?></div><div class="v"><?= e(num(count($slips))) ?></div><div class="d"><?= e(t('All verifiable by QR')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Times verified')) ?></div><div class="v"><?= e(num(array_sum(array_column($slips, 'checks')))) ?></div><div class="d"><?= e(t('By banks, embassies and others')) ?></div></div>
</div>
<div class="card">
<?php if ($slips): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Month')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Gross')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($slips as $s): ?>
      <tr>
        <td class="first"><b><?= e(period_label($s['period'])) ?></b></td>
        <td data-l="<?= e(t('Code')) ?>"><span class="codepill"><?= e($s['code']) ?></span></td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $s['status']]); ?></td>
        <td class="num" data-l="<?= e(t('Gross')) ?>"><?= e(money($s['gross'])) ?></td>
        <td class="num" data-l="<?= e(t('Net pay')) ?>"><b><?= e(money($s['net'])) ?></b></td>
        <td class="num"><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <button class="btn btn-ghost btn-sm" data-copy="<?= e(verify_url($s['code'])) ?>" data-copied="✓"><?= icon('link', 15) ?> <?= e(t('Copy link')) ?></button>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('/explain/' . $s['code'])) ?>"><?= icon('zap', 15) ?> <?= e(t('Explain')) ?></a>
          <a class="btn btn-light btn-sm" href="<?= e(url('/payslip/' . $s['code'])) ?>"><?= icon('download', 15) ?> <?= e(t('View / PDF')) ?></a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('file', 24) ?></div><h3><?= e(t('No payslips yet')) ?></h3><p><?= e(t('Your payslips will appear here as soon as your employer issues them.')) ?></p></div>
<?php endif; ?>
</div>
<div class="alert alert-info" style="margin-top:18px"><?= icon('shield') ?><div><?= e(t('Applying for a loan, visa or flat? Send a proof of income link with your last few payslips, or ask for a salary certificate. The bank or office can check everything is genuine.')) ?>
  <div class="row" style="gap:8px;margin-top:8px;flex-wrap:wrap"><a class="btn btn-primary btn-sm" href="<?= e(url('/me/proof')) ?>"><?= icon('send', 15) ?> <?= e(t('Create proof of income link')) ?></a><a class="btn btn-light btn-sm" href="<?= e(url('/me/documents')) ?>"><?= icon('award', 15) ?> <?= e(t('Letters & certificates')) ?></a></div></div></div>
