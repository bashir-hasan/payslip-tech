<?php $tab = in_array(input('tab'), ['requests', 'month', 'settings'], true) ? input('tab') : 'requests'; $mng = can_do('manage_people'); $own = company_role() === 'owner'; ?>
<div class="page-head">
  <div><h1><?= e(t('Leave & attendance')) ?></h1><p><?= e(t('Employees request leave from their portal. Approved unpaid leave is deducted in that month\'s payroll automatically.')) ?></p></div>
  <?php if ($mng && $emps): ?><button class="btn btn-primary" data-dialog="addleave"><?= icon('plus') ?> <?= e(t('Record leave')) ?></button><?php endif; ?>
</div>
<div class="row" style="gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a class="btn btn-sm <?= $tab === 'requests' ? 'btn-dark' : 'btn-light' ?>" href="<?= e(url('/app/leave')) ?>"><?= e(t('Requests')) ?><?= $pending ? ' (' . e(num($pending)) . ')' : '' ?></a>
  <a class="btn btn-sm <?= $tab === 'month' ? 'btn-dark' : 'btn-light' ?>" href="<?= e(url('/app/leave?tab=month')) ?>"><?= e(t('Monthly attendance')) ?></a>
  <?php if ($mng): ?><a class="btn btn-sm <?= $tab === 'settings' ? 'btn-dark' : 'btn-light' ?>" href="<?= e(url('/app/leave?tab=settings')) ?>"><?= e(t('Leave types & weekend')) ?></a><?php endif; ?>
</div>

<?php if ($tab === 'requests'): ?>
<div class="row" style="gap:6px;margin-bottom:10px;flex-wrap:wrap">
  <?php foreach (['pending' => t('Pending'), 'approved' => t('Approved'), 'rejected' => t('Declined'), 'all' => t('All')] as $k => $lbl): ?>
    <a class="small <?= $status === $k ? '' : 'muted' ?>" style="<?= $status === $k ? 'font-weight:700' : '' ?>" href="<?= e(url('/app/leave?status=' . $k)) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
<?php if ($requests): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Type')) ?></th><th><?= e(t('Dates')) ?></th><th class="num"><?= e(t('Days')) ?></th><th><?= e(t('Reason')) ?></th><th><?= e(t('Status')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td class="first"><b><?= e($r['name']) ?></b><div class="small muted mono"><?= e($r['emp_code']) ?></div></td>
        <td data-l="<?= e(t('Type')) ?>"><?= e($r['type_name']) ?><?php if (!(int)$r['paid']): ?> <span class="badge badge-warn"><?= e(t('Unpaid')) ?></span><?php endif; ?></td>
        <td data-l="<?= e(t('Dates')) ?>"><?= e(date_fmt($r['start_date'])) ?> – <?= e(date_fmt($r['end_date'])) ?></td>
        <td class="num" data-l="<?= e(t('Days')) ?>"><?= e(num($r['days'] + 0)) ?></td>
        <td data-l="<?= e(t('Reason')) ?>" class="small"><?= e($r['reason'] ?: '—') ?><?php if ($r['decision_note']): ?><div class="muted"><?= e($r['decision_note']) ?></div><?php endif; ?></td>
        <td data-l="<?= e(t('Status')) ?>"><span class="badge <?= ['pending' => 'badge-info', 'approved' => 'badge-ok', 'rejected' => 'badge-bad'][$r['status']] ?? '' ?>"><?= e(['pending' => t('Pending'), 'approved' => t('Approved'), 'rejected' => t('Declined'), 'cancelled' => t('Cancelled')][$r['status']] ?? $r['status']) ?></span></td>
        <td class="num"><?php if ($mng): ?><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <?php if ($r['status'] === 'pending'): ?>
            <form method="post" action="<?= e(url('/app/leave/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm"><?= icon('check', 15) ?> <?= e(t('Approve')) ?></button></form>
            <form method="post" action="<?= e(url('/app/leave/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button class="btn btn-light btn-sm"><?= e(t('Decline')) ?></button></form>
          <?php elseif ($r['status'] === 'approved'): ?>
            <form method="post" action="<?= e(url('/app/leave/' . $r['id'])) ?>" data-confirm="<?= e(t('Cancel this approved leave? Any draft payroll is updated.')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost btn-sm"><?= e(t('Cancel')) ?></button></form>
          <?php endif; ?>
        </div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('calendar', 24) ?></div><h3><?= e(t('Nothing here')) ?></h3><p><?= e(t('Leave requests from your employees will appear here.')) ?></p></div>
<?php endif; ?>
</div>

<?php elseif ($tab === 'month'): ?>
<form class="row" style="gap:8px;margin-bottom:12px" method="get" action="<?= e(url('/app/leave')) ?>"><input type="hidden" name="tab" value="month"><input class="input" type="month" name="month" value="<?= e($month) ?>" style="width:auto"><button class="btn btn-light"><?= e(t('Show')) ?></button><span class="small muted"><?= e(sprintf(t('%s working days in %s'), num($workdays), period_label($month))) ?></span></form>
<div class="card">
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th class="num"><?= e(t('Present')) ?></th><th class="num"><?= e(t('Paid leave')) ?></th><th class="num"><?= e(t('Unpaid leave')) ?></th><th><?= e(t('Breakdown')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($summary as $s): ?>
      <tr>
        <td class="first"><b><?= e($s['e']['name']) ?></b> <span class="small muted mono"><?= e($s['e']['emp_code']) ?></span></td>
        <td class="num" data-l="<?= e(t('Present')) ?>"><?= e(num(max(0, $workdays - $s['paid'] - $s['unpaid']))) ?></td>
        <td class="num" data-l="<?= e(t('Paid leave')) ?>"><?= e(num($s['paid'])) ?></td>
        <td class="num" data-l="<?= e(t('Unpaid leave')) ?>"><?= $s['unpaid'] ? '<b style="color:var(--danger,#b91c1c)">' . e(num($s['unpaid'])) . '</b>' : '0' ?></td>
        <td data-l="<?= e(t('Breakdown')) ?>" class="small muted"><?= e(implode(' · ', array_map(fn($k, $v) => $k . ' ' . num($v), array_keys($s['types']), $s['types'])) ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<p class="small muted" style="margin-top:10px"><?= e(t('Present days assume everyone worked every working day without leave. Unpaid leave days are carried into that month\'s payroll as unpaid days.')) ?></p>

<?php elseif ($mng): ?>
<form method="post" action="<?= e(url('/app/leave/settings')) ?>">
  <?= csrf_field() ?>
  <div class="card"><div class="card-body">
    <div class="fieldset-title"><?= e(t('Weekly holidays')) ?></div>
    <p class="small muted"><?= e(t('These days are not counted as leave days.')) ?></p>
    <div class="row" style="gap:14px;flex-wrap:wrap"><?php $wk = company_weekend($c); foreach (weekday_names() as $n => $lbl): ?><label class="row" style="gap:6px"><input type="checkbox" name="weekend[]" value="<?= $n ?>" <?= in_array($n, $wk, true) ? 'checked' : '' ?>> <?= e($lbl) ?></label><?php endforeach; ?></div>
  </div></div>
  <div class="card" style="margin-top:14px"><div class="card-body">
    <div class="fieldset-title"><?= e(t('Leave types')) ?></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('Name')) ?></th><th><?= e(t('Days per year')) ?></th><th><?= e(t('Paid')) ?></th><th><?= e(t('Active')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($types as $t): ?>
        <tr><td><input class="input" name="types[<?= $t['id'] ?>][name]" value="<?= e($t['name']) ?>"></td><td><input class="input" name="types[<?= $t['id'] ?>][days]" value="<?= e((string)($t['days_per_year'] + 0)) ?>" style="width:90px"></td><td><input type="checkbox" name="types[<?= $t['id'] ?>][paid]" value="1" <?= (int)$t['paid'] ? 'checked' : '' ?>></td><td><input type="checkbox" name="types[<?= $t['id'] ?>][active]" value="1" <?= (int)$t['active'] ? 'checked' : '' ?>></td></tr>
      <?php endforeach; ?>
        <tr><td><input class="input" name="types[new][name]" placeholder="<?= e(t('Add a leave type, e.g. Maternity leave')) ?>"></td><td><input class="input" name="types[new][days]" style="width:90px" placeholder="0"></td><td><input type="checkbox" name="types[new][paid]" value="1" checked></td><td><input type="checkbox" name="types[new][active]" value="1" checked></td></tr>
      </tbody>
    </table></div>
    <p class="small muted"><?= e(t('Set days per year to 0 for no limit. Unpaid types reduce pay for the days taken.')) ?></p>
  </div></div>
  <div style="margin-top:14px"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save leave settings')) ?></button></div>
</form>
<?php endif; ?>

<?php if ($mng && $emps): ?>
<dialog id="addleave">
  <form method="post" action="<?= e(url('/app/leave/create')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Record leave')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Employee')) ?></label><select class="input" name="employee_id" required><option value=""><?= e(t('Choose…')) ?></option><?php foreach ($emps as $e): ?><option value="<?= e((string)$e['id']) ?>"><?= e($e['name'] . ' (' . $e['emp_code'] . ')') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label"><?= e(t('Leave type')) ?></label><select class="input" name="type_id" required><?php foreach ($types as $t): if (!(int)$t['active']) continue; ?><option value="<?= e((string)$t['id']) ?>"><?= e($t['name']) ?><?= (int)$t['paid'] ? '' : ' (' . e(t('Unpaid')) . ')' ?></option><?php endforeach; ?></select></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('From')) ?></label><input class="input" type="date" name="start_date" required></div>
        <div class="field"><label class="label"><?= e(t('To')) ?></label><input class="input" type="date" name="end_date" required></div>
      </div>
      <div class="field"><label class="label"><?= e(t('Note')) ?></label><input class="input" name="reason" maxlength="300"></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save as approved')) ?></button></div>
  </form>
</dialog>
<?php endif; ?>
