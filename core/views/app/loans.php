<div class="page-head">
  <div><h1><?= e(t('Loans & advances')) ?></h1><p><?= e(t('Give a salary advance or loan once. PaySlip deducts the instalment from each month\'s payroll until it is repaid.')) ?></p></div>
  <?php if ($emps && can_do('manage_people')): ?><button class="btn btn-primary" data-dialog="newloan"><?= icon('plus') ?> <?= e(t('New loan or advance')) ?></button><?php endif; ?>
</div>
<div class="grid grid-3" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('bank', 16) ?> <?= e(t('Outstanding')) ?></div><div class="v"><?= e(money($outstanding)) ?></div><div class="d"><?= e(t('Still to be repaid')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Active loans')) ?></div><div class="v"><?= e(num(count(array_filter($rows, fn($r) => $r['status'] === 'active')))) ?></div><div class="d"><?= e(t('Deducted automatically')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('check', 16) ?> <?= e(t('Repaid')) ?></div><div class="v"><?= e(num(count(array_filter($rows, fn($r) => $r['status'] === 'closed')))) ?></div><div class="d"><?= e(t('Closed loans')) ?></div></div>
</div>
<div class="card">
<?php if ($rows): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Loan')) ?></th><th class="num"><?= e(t('Amount')) ?></th><th class="num"><?= e(t('Monthly')) ?></th><th class="num"><?= e(t('Repaid')) ?></th><th><?= e(t('Status')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $left = max(0, (float)$r['principal'] - (float)$r['paid']); $pct = $r['principal'] > 0 ? min(100, round($r['paid'] / $r['principal'] * 100)) : 0; ?>
      <tr>
        <td class="first"><b><?= e($r['name']) ?></b><div class="small muted mono"><?= e($r['emp_code']) ?></div></td>
        <td data-l="<?= e(t('Loan')) ?>"><?= e($r['title']) ?><div class="small muted"><?= e(sprintf(t('From %s'), period_label($r['start_period']))) ?></div></td>
        <td class="num" data-l="<?= e(t('Amount')) ?>"><?= e(money($r['principal'])) ?></td>
        <td class="num" data-l="<?= e(t('Monthly')) ?>"><?= e(money($r['installment'])) ?></td>
        <td class="num" data-l="<?= e(t('Repaid')) ?>"><?= e(money($r['paid'])) ?><div class="small muted"><?= e(sprintf(t('%s%% · %s left'), num($pct), money($left))) ?></div></td>
        <td data-l="<?= e(t('Status')) ?>"><span class="badge <?= $r['status'] === 'active' ? 'badge-info' : ($r['status'] === 'closed' ? 'badge-ok' : '') ?>"><?= e(['active' => t('Active'), 'closed' => t('Repaid'), 'cancelled' => t('Stopped')][$r['status']] ?? $r['status']) ?></span></td>
        <td class="num"><?php if ($r['status'] === 'active' && can_do('manage_people')): ?><form method="post" action="<?= e(url('/app/loans/' . $r['id'] . '/cancel')) ?>" data-confirm="<?= e(t('Stop deducting this loan? Instalments already taken stay on the issued payslips.')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('Stop')) ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('bank', 24) ?></div><h3><?= e(t('No loans or advances yet')) ?></h3><p><?= e(t('When you give someone an advance, add it here once. The monthly instalment appears on their payslip automatically.')) ?></p></div>
<?php endif; ?>
</div>

<dialog id="newloan">
  <form method="post" action="<?= e(url('/app/loans/save')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('New loan or advance')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Employee')) ?></label><select class="input" name="employee_id" required><option value=""><?= e(t('Choose…')) ?></option><?php foreach ($emps as $e): ?><option value="<?= e((string)$e['id']) ?>"><?= e($e['name'] . ' (' . $e['emp_code'] . ')') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label"><?= e(t('Title')) ?></label><input class="input" name="title" maxlength="120" placeholder="<?= e(t('e.g. Salary advance, Eid advance, Motorbike loan')) ?>"></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Amount')) ?></label><input class="input" name="principal" inputmode="decimal" required></div>
        <div class="field"><label class="label"><?= e(t('Monthly instalment')) ?></label><input class="input" name="installment" inputmode="decimal" required></div>
      </div>
      <div class="field"><label class="label"><?= e(t('First deduction month')) ?></label><input class="input" type="month" name="start_period" value="<?= e(date('Y-m')) ?>" required><span class="hint"><?= e(t('If that month\'s payroll is still a draft, the instalment is added to it right away.')) ?></span></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save')) ?></button></div>
  </form>
</dialog>
