<div class="page-head">
  <div><h1><?= e(t('Payslips')) ?></h1><p><?= e(t('Every payslip you have issued. Revoke one if it was issued by mistake.')) ?></p></div>
</div>
<div class="card">
  <div class="toolbar">
    <form class="search" method="get"><?= icon('search', 16) ?><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Search name, employee ID or code…')) ?>"><input type="hidden" name="period" value="<?= e($period) ?>"></form>
    <form method="get" class="row"><input type="hidden" name="q" value="<?= e($q) ?>">
      <select class="select" name="period" data-autosubmit style="height:40px;width:auto">
        <option value=""><?= e(t('All months')) ?></option>
        <?php foreach ($periods as $pp): ?><option value="<?= e($pp) ?>" <?= $pp === $period ? 'selected' : '' ?>><?= e(period_label($pp)) ?></option><?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Month')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><th class="num"><?= e(t('Checks')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="first"><div class="who"><span class="avatar"><?= e(initials($r['name'])) ?></span><span><b><?= e($r['name']) ?></b><span><?= e($r['emp_code']) ?></span></span></div></td>
        <td data-l="<?= e(t('Month')) ?>"><?= e(period_label($r['period'])) ?></td>
        <td data-l="<?= e(t('Code')) ?>"><span class="codepill"><?= e($r['code']) ?></span></td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $r['status']]); ?></td>
        <td class="num" data-l="<?= e(t('Net pay')) ?>"><?= e(money($r['net'])) ?></td>
        <td class="num" data-l="<?= e(t('Checks')) ?>"><?= e(num($r['checks'])) ?></td>
        <td class="num"><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/explain/' . $r['code'])) ?>" title="<?= e(t('Explain')) ?>"><?= icon('zap', 15) ?></a>
          <a class="btn btn-light btn-sm" href="<?= e(url('/payslip/' . $r['code'])) ?>"><?= e(t('View')) ?></a>
          <?php if ($r['status'] === 'issued'): ?><button type="button" class="btn btn-danger btn-sm" data-dialog="revoke" data-action="<?= e(url('/app/payslips/' . $r['id'] . '/revoke')) ?>" data-fill='<?= e(json_encode(['code' => $r['code'], 'reason' => ''])) ?>'><?= e(t('Revoke')) ?></button><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <div class="empty"><div class="ic-wrap"><?= icon('file', 24) ?></div><h3><?= e(t('No payslips yet')) ?></h3><p><?= e(t('Payslips appear here once you finalise a payroll run.')) ?></p><a class="btn btn-primary" href="<?= e(url('/app/payroll')) ?>"><?= e(t('Go to payroll')) ?></a></div>
  <?php endif; ?>
</div>

<dialog id="revoke">
  <form method="post" action="">
    <?= csrf_field() ?>
    <div class="dialog-head"><div><h3><?= e(t('Revoke payslip')) ?></h3><span class="small muted mono" data-text="code"></span></div><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <p class="muted"><?= e(t('Anyone who verifies this payslip will see that it was revoked, with your reason. This cannot be undone.')) ?></p>
      <div class="field"><label class="label"><?= e(t('Reason')) ?></label><input class="input" name="reason" maxlength="255" required placeholder="<?= e(t('e.g. Issued with wrong amount, replaced')) ?>"></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-danger"><?= icon('ban') ?> <?= e(t('Revoke')) ?></button></div>
  </form>
</dialog>
