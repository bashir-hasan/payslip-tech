<div class="page-head">
  <div><h1><?= e(t('Payroll')) ?></h1><p><?= e(t('One run per month. Review drafts, then finalise to issue verified payslips.')) ?></p></div>
  <button class="btn btn-primary" data-dialog="newrun"><?= icon('plus') ?> <?= e(t('New payroll run')) ?></button>
</div>

<div class="card">
<?php if ($runs): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Month')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Employees')) ?></th><th class="num"><?= e(t('Gross')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><th><?= e(t('Pay date')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td class="first"><a href="<?= e(url('/app/payroll/' . $r['id'])) ?>" style="color:inherit"><b><?= e(period_label($r['period'])) ?></b></a></td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $r['status']]); ?></td>
        <td class="num" data-l="<?= e(t('Employees')) ?>"><?= e(num($r['slips'])) ?></td>
        <td class="num" data-l="<?= e(t('Gross')) ?>"><?= e(money($r['total_gross'])) ?></td>
        <td class="num" data-l="<?= e(t('Net pay')) ?>"><b><?= e(money($r['total_net'])) ?></b></td>
        <td data-l="<?= e(t('Pay date')) ?>"><?= e(date_fmt($r['pay_date'])) ?></td>
        <td class="num"><a class="btn btn-light btn-sm" href="<?= e(url('/app/payroll/' . $r['id'])) ?>"><?= e($r['status'] === 'draft' ? t('Review') : t('Open')) ?> →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('wallet', 24) ?></div><h3><?= e(t('Run your first payroll')) ?></h3>
    <p><?= $active ? e(sprintf(t('You have %s active employees. Create a draft for this month to get started.'), num($active))) : e(t('Add employees first, then create a payroll run.')) ?></p>
    <?php if ($active): ?><button class="btn btn-primary" data-dialog="newrun"><?= icon('plus') ?> <?= e(t('New payroll run')) ?></button><?php else: ?><a class="btn btn-primary" href="<?= e(url('/app/employees/new')) ?>"><?= icon('plus') ?> <?= e(t('Add employee')) ?></a><?php endif; ?>
  </div>
<?php endif; ?>
</div>

<dialog id="newrun">
  <form method="post" action="<?= e(url('/app/payroll/create')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('New payroll run')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Month')) ?></label><input class="input" type="month" name="period" value="<?= e($suggest) ?>" required></div>
        <div class="field"><label class="label"><?= e(t('Working days')) ?></label><input class="input" type="number" name="working_days" value="30" min="1" max="31" required><span class="hint"><?= e(t('Used to calculate unpaid-day deductions.')) ?></span></div>
        <div class="field span-2"><label class="label"><?= e(t('Pay date')) ?></label><input class="input" type="date" name="pay_date" value="<?= e(date('Y-m-t', strtotime($suggest . '-01'))) ?>"></div>
      </div>
      <div class="alert alert-info" style="margin:0"><?= icon('alert') ?><div><?= e(sprintf(t('A draft payslip will be created for each of your %s active employees. Nothing is issued until you finalise.'), num($active))) ?></div></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= e(t('Create draft')) ?></button></div>
  </form>
</dialog>
