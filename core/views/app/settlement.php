<?php $f = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); ?>
<div class="page-head">
  <div><a class="small" href="<?= e(url('/app/employees/' . $e['id'])) ?>">← <?= e($e['name']) ?></a><h1 style="margin-top:6px"><?= e(t('Final settlement')) ?></h1><p><?= e(t('Work out what a leaving employee is owed, then issue a verified settlement statement. Check every amount against your policy and the law before issuing.')) ?></p></div>
</div>
<?php if (!$allowed): ?><div class="alert alert-info"><?= icon('lock') ?><div><?= e(sprintf(t('%s is part of the %s plan.'), feature_names()['settlement'], plans()['business']['name'])) ?> <?php if (billing_enabled()): ?><a href="<?= e(url('/app/billing')) ?>"><?= e(t('See plans')) ?> →</a><?php endif; ?></div></div><?php endif; ?>
<?php if ($done): ?><div class="alert alert-info"><?= icon('file') ?><div><?= e(t('Already issued:')) ?> <?php foreach ($done as $x): ?><a href="<?= e(url('/document/' . $x['code'])) ?>"><?= e($x['code']) ?></a> <?php endforeach; ?></div></div><?php endif; ?>
<form class="row" method="get" action="<?= e(url('/app/employees/' . $e['id'] . '/settlement')) ?>" style="gap:8px;margin-bottom:14px"><label class="small"><?= e(t('Last working day')) ?></label><input class="input" type="date" name="last" value="<?= e($last) ?>" style="width:auto"><button class="btn btn-light"><?= e(t('Recalculate')) ?></button></form>
<p class="small muted"><?= e(sprintf(t('Monthly gross %s · day rate %s (gross ÷ 30) · %s years of service'), money($d['gross_monthly']), money($d['day_rate']), num($d['service_years']))) ?></p>
<form class="card" method="post" action="<?= e(url('/app/employees/' . $e['id'] . '/settlement')) ?>"><div class="card-body">
  <?= csrf_field() ?><input type="hidden" name="last" value="<?= e($last) ?>">
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <div>
      <div class="fieldset-title"><?= e(t('Amounts owed')) ?></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Unpaid salary days')) ?></label><input class="input" name="salary_days" value="<?= e($f($d['salary_days'])) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Unpaid salary')) ?></label><input class="input" name="salary_amount" value="<?= e($f($d['salary_amount'])) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Leave days to encash')) ?></label><input class="input" name="leave_days" value="<?= e($f($d['leave_days'])) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Leave encashment')) ?></label><input class="input" name="leave_amount" value="<?= e($f($d['leave_amount'])) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Notice pay')) ?></label><input class="input" name="notice_amount" value="0"></div>
        <div class="field"><label class="label"><?= e(t('Gratuity / service benefit')) ?></label><input class="input" name="gratuity" value="0"></div>
        <div class="field"><label class="label"><?= e(t('Other dues (label)')) ?></label><input class="input" name="other_earning_label" placeholder="<?= e(t('e.g. Festival bonus due')) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Other dues')) ?></label><input class="input" name="other_earning" value="0"></div>
      </div>
    </div>
    <div>
      <div class="fieldset-title"><?= e(t('Deductions')) ?></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Outstanding loans and advances')) ?></label><input class="input" name="loans" value="<?= e($f($d['loans'])) ?>"><span class="hint"><?= e(t('Open loans are marked repaid when you issue.')) ?></span></div>
        <div class="field"><label class="label"><?= e(t('Income tax')) ?></label><input class="input" name="tax" value="0"></div>
        <div class="field"><label class="label"><?= e(t('Other deductions (label)')) ?></label><input class="input" name="other_deduction_label" placeholder="<?= e(t('e.g. Notice period shortfall')) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Other deductions')) ?></label><input class="input" name="other_deduction" value="0"></div>
      </div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Reason for leaving')) ?></label><select class="input" name="reason"><?php foreach (['Resignation', 'Termination', 'Retirement', 'End of contract', 'Discharge'] as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="label"><?= e(t('Pay by')) ?></label><input class="input" type="date" name="pay_by" value="<?= e(date('Y-m-d', strtotime($last . ' +30 days'))) ?>"><span class="hint"><?= e(t('Bangladesh law: within 30 working days of leaving.')) ?></span></div>
      </div>
      <div class="field"><label class="label"><?= e(t('Note')) ?></label><input class="input" name="note" maxlength="300"></div>
    </div>
  </div>
  <label class="check" style="margin:10px 0 14px"><input type="checkbox" name="deactivate" value="1" checked> <span><?= e(t('Mark the employee inactive and close their portal access')) ?></span></label>
  <button class="btn btn-primary" <?= $allowed ? '' : 'disabled' ?> data-confirm="<?= e(t('Issue the final settlement statement? It becomes verifiable straight away.')) ?>"><?= icon('award') ?> <?= e(t('Issue settlement statement')) ?></button>
</div></form>
