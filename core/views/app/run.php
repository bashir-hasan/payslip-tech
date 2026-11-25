<?php $co = db_one('SELECT * FROM companies WHERE id = ?', [$run['company_id']]); $checks = $run['status'] === 'draft' && plan_allows($co, 'checks') ? run_checks($co, $run) : []; $canWa = plan_allows($co, 'whatsapp'); $payLang = $co['payslip_lang'] ?? 'en';
$draft = $run['status'] === 'draft'; $canFin = can_do('finalize'); $canEdit = can_do('edit_payroll'); $submitter = $run['submitted_by'] ? db_val('SELECT name FROM users WHERE id = ?', [$run['submitted_by']]) : null; $approver = $run['approved_by'] ? db_val('SELECT name FROM users WHERE id = ?', [$run['approved_by']]) : null; ?>
<div class="page-head">
  <div>
    <a class="small" href="<?= e(url('/app/payroll')) ?>">← <?= e(t('Payroll')) ?></a>
    <h1 class="row" style="margin-top:6px;gap:12px"><?= e(period_label($run['period'])) ?> <?php partial('result_badge', ['r' => $run['status']]); ?></h1>
    <p><?= e(sprintf(t('%s working days · Pay date %s'), num($run['working_days']), date_fmt($run['pay_date']))) ?><?php if (!$draft): ?> · <?= e(sprintf(t('Finalised %s'), date_fmt($run['finalized_at'], null, true))) ?><?php endif; ?></p>
  </div>
  <div class="row">
    <a class="btn btn-light" href="<?= e(url('/app/payroll/' . $run['id'] . '/export')) ?>"><?= icon('download') ?> <?= e(t('Payment sheet (CSV)')) ?></a>
    <?php if ($draft && $canEdit): ?>
      <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/delete')) ?>" data-confirm="<?= e(t('Delete this draft payroll? This cannot be undone.')) ?>"><?= csrf_field() ?><button class="btn btn-danger"><?= icon('trash') ?> <?= e(t('Delete draft')) ?></button></form>
    <?php endif; ?>
    <?php if ($draft && $canFin): ?>
      <?php if ($run['submitted_at'] && company_role() === 'accountant'): ?>
        <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/reject')) ?>" class="row" style="gap:6px"><?= csrf_field() ?><input class="input" name="note" placeholder="<?= e(t('What should HR change?')) ?>" style="width:200px"><button class="btn btn-light"><?= icon('x') ?> <?= e(t('Send back')) ?></button></form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/finalize')) ?>" data-confirm="<?= e(t('Finalise and issue all payslips? They will be locked and become verifiable. You can revoke individual payslips later, but not edit them.')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('lock') ?> <?= e($run['submitted_at'] ? t('Approve & issue') : t('Finalise & issue')) ?></button></form>
    <?php elseif ($draft): ?>
      <?php if (!$run['submitted_at']): ?>
        <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/submit')) ?>" data-confirm="<?= e(t('Send this payroll to the accountant or owner for approval?')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('send') ?> <?= e(t('Submit for approval')) ?></button></form>
      <?php else: ?>
        <span class="badge badge-info"><?= e(t('Waiting for approval')) ?></span>
      <?php endif; ?>
    <?php elseif (!$draft): ?>
      <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/email')) ?>" data-confirm="<?= e(t('Email each employee a link to their payslip?')) ?>"><?= csrf_field() ?><button class="btn btn-dark"><?= icon('mail') ?> <?= e(t('Email payslips')) ?></button></form>
    <?php endif; ?>
  </div>
</div>

<?php if ($draft && $run['submitted_at']): ?>
  <div class="alert alert-info"><?= icon('send') ?><div><?= e(sprintf(t('Submitted for approval by %s on %s.'), $submitter ?: '—', date_fmt($run['submitted_at'], null, true))) ?></div></div>
<?php endif; ?>
<?php if (!$draft && $approver): ?>
  <div class="alert alert-info"><?= icon('check') ?><div><?= e(sprintf(t('Approved and issued by %s.'), $approver)) ?><?= $submitter ? ' ' . e(sprintf(t('Prepared by %s.'), $submitter)) : '' ?></div></div>
<?php endif; ?>
<?php if ($draft && $canEdit): ?>
  <div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <button type="button" class="btn btn-light btn-sm" data-dialog="festival"><?= icon('zap', 15) ?> <?= e(t('Festival bonus')) ?><?= plan_pill($co, 'festival_bonus') ?></button>
    <button type="button" class="btn btn-light btn-sm" data-dialog="paste"><?= icon('upload', 15) ?> <?= e(t('Paste from Excel')) ?><?= plan_pill($co, 'paste') ?></button>
  </div>
<?php endif; ?>
<?php if (!$draft): ?>
  <div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
    <span class="small muted"><?= e(t('Payment files:')) ?></span>
    <a class="btn btn-light btn-sm" href="<?= e(url('/app/payroll/' . $run['id'] . '/payfile/bank')) ?>"><?= icon('bank', 15) ?> <?= e(t('Bank transfers')) ?><?= plan_pill($co, 'payment_file') ?></a>
    <a class="btn btn-light btn-sm" href="<?= e(url('/app/payroll/' . $run['id'] . '/payfile/mfs')) ?>"><?= icon('wallet', 15) ?> <?= e(t('bKash / Nagad / Rocket')) ?><?= plan_pill($co, 'payment_file') ?></a>
    <a class="btn btn-light btn-sm" href="<?= e(url('/app/payroll/' . $run['id'] . '/payfile/cash')) ?>"><?= icon('file', 15) ?> <?= e(t('Cash sheet')) ?><?= plan_pill($co, 'payment_file') ?></a>
  </div>
<?php endif; ?>
<?php if ($draft && $checks): $warn = count(array_filter($checks, fn($x) => $x['level'] === 'warn')); ?>
  <div class="alert <?= $warn ? 'alert-warn' : 'alert-info' ?>"><?= icon('alert') ?><div><b><?= e($warn ? sprintf(t('%s things to check before issuing'), num(count($checks))) : sprintf(t('%s notes before issuing'), num(count($checks)))) ?></b>
    <ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($checks as $ck): ?><li class="small"><?= $ck['slip'] ? '<a href="#slip-' . e((string)$ck['slip']) . '" style="color:inherit">' . e($ck['text']) . '</a>' : e($ck['text']) ?></li><?php endforeach; ?></ul>
  </div></div>
<?php elseif ($draft && !plan_allows($co, 'checks')): ?>
  <p class="small muted" style="margin:0 0 10px"><?= icon('shield', 14) ?> <?= e(t('Automatic checks before issuing (big pay changes, missing bank details, leavers) come with the Starter plan.')) ?></p>
<?php elseif ($draft): ?>
  <div class="alert alert-success"><?= icon('check') ?><div><?= e(t('No problems found. Pay changes, bank details and joiners look fine.')) ?></div></div>
<?php endif; ?>
<?php if ($draft): ?>
  <div class="alert alert-warn"><?= icon('alert') ?><div><?= e(t('This is a draft. Adjust bonus, overtime, advances or unpaid days below, then finalise to issue verified payslips.')) ?></div></div>
<?php endif; ?>

<div class="run-summary">
  <div><span><?= e(t('Employees')) ?></span><b><?= e(num(count($slips))) ?></b></div>
  <div><span><?= e(t('Gross earnings')) ?></span><b><?= e(money($totals['gross'])) ?></b></div>
  <div><span><?= e(t('Deductions')) ?></span><b><?= e(money($totals['ded'])) ?></b></div>
  <div><span><?= e(t('Net payable')) ?></span><b style="color:var(--brand)"><?= e(money($totals['net'])) ?></b></div>
</div>

<div class="card">
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th class="num"><?= e(t('Gross')) ?></th><th class="num"><?= e(t('Deductions')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><th><?= e($draft ? t('Adjustments') : t('Payslip')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($slips as $s): $a = $s['adjustments']; $chips = [];
      if ($a['bonus'] > 0) $chips[] = t('Bonus') . ' ' . money_fmt($a['bonus'], false);
      if ($a['overtime'] > 0) $chips[] = t('Overtime') . ' ' . money_fmt($a['overtime'], false);
      if ($a['loan'] > 0) $chips[] = (!empty($a['loan_auto']) ? t('Loan instalment') : t('Advance')) . ' −' . money_fmt($a['loan'], false);
      if (!empty($a['leave_unpaid'])) $chips[] = sprintf(t('%s days unpaid leave'), num($a['leave_unpaid'] + 0));
      if ($a['other'] > 0) $chips[] = t('Other') . ' −' . money_fmt($a['other'], false);
      if ($a['unpaid_days'] > 0) $chips[] = sprintf(t('%s unpaid days'), num($a['unpaid_days'] + 0)); ?>
      <tr id="slip-<?= e((string)$s['id']) ?>">
        <td class="first"><div class="who"><span class="avatar"><?= e(initials($s['name'])) ?></span><span><b><?= e($s['name']) ?></b><span><?= e($s['emp_code']) ?> · <?= e($s['designation'] ?: '—') ?></span></span></div></td>
        <td class="num" data-l="<?= e(t('Gross')) ?>"><?= e(money($s['gross'])) ?></td>
        <td class="num" data-l="<?= e(t('Deductions')) ?>"><?= e(money($s['total_deductions'])) ?></td>
        <td class="num" data-l="<?= e(t('Net pay')) ?>"><b><?= e(money($s['net'])) ?></b></td>
        <td data-l="<?= e($draft ? t('Adjustments') : t('Payslip')) ?>">
          <?php if ($draft): ?>
            <?php if ($chips): foreach ($chips as $c): ?><span class="badge plain" style="margin:2px 4px 2px 0"><?= e($c) ?></span><?php endforeach; else: ?><span class="muted small">—</span><?php endif; ?>
          <?php else: ?>
            <span class="codepill"><?= e($s['code']) ?></span> <?php if ($s['status'] === 'revoked'): partial('result_badge', ['r' => 'revoked']); endif; ?>
          <?php endif; ?>
        </td>
        <td class="num">
          <div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <?php if ($draft): ?>
            <button type="button" class="btn btn-light btn-sm" data-dialog="adjust" data-action="<?= e(url('/app/payroll/' . $run['id'] . '/slip/' . $s['id'])) ?>" data-fill='<?= e(json_encode(['name' => $s['name'], 'bonus' => $a['bonus'] ?: '', 'overtime' => $a['overtime'] ?: '', 'loan' => $a['loan'] ?: '', 'other' => $a['other'] ?: '', 'unpaid_days' => $a['unpaid_days'] ?: '', 'note' => $a['note'] ?? ''])) ?>'><?= icon('edit', 15) ?> <?= e(t('Adjust')) ?></button>
            <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/payslip/draft-' . $s['id'])) ?>" title="<?= e(t('Preview')) ?>" target="_blank"><?= icon('eye', 16) ?></a>
          <?php else: ?>
            <?php $wa = wa_number($s['phone'], $co['country'] ?? 'BD'); if ($s['status'] === 'issued' && $wa): ?>
              <?php if ($canWa): ?><a class="btn btn-ghost btn-sm" href="<?= e(wa_link($wa, wa_payslip_text($co, $s['name'], $run['period'], $s['code'], $payLang))) ?>" target="_blank" rel="noopener" title="<?= e(t('Send on WhatsApp')) ?>"><?= icon('send', 15) ?> WhatsApp</a>
              <?php else: ?><a class="btn btn-ghost btn-sm" href="<?= e(url(billing_enabled() ? '/app/billing' : '/app')) ?>" title="<?= e(t('Send on WhatsApp')) ?>"><?= icon('lock', 14) ?> WhatsApp</a><?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-light btn-sm" href="<?= e(url('/payslip/' . $s['code'])) ?>"><?= icon('file', 15) ?> <?= e(t('View')) ?></a>
          <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($draft): ?>
<dialog id="adjust">
  <form method="post" action="">
    <?= csrf_field() ?>
    <div class="dialog-head"><div><h3><?= e(t('Monthly adjustments')) ?></h3><span class="small muted" data-text="name"></span></div><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="fieldset-title"><?= e(t('Add to earnings')) ?></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Bonus')) ?></label><div class="input-group"><span class="prefix"><?= e(trim(currency_symbol())) ?></span><input class="input" name="bonus" inputmode="decimal" placeholder="0"></div></div>
        <div class="field"><label class="label"><?= e(t('Overtime')) ?></label><div class="input-group"><span class="prefix"><?= e(trim(currency_symbol())) ?></span><input class="input" name="overtime" inputmode="decimal" placeholder="0"></div></div>
      </div>
      <div class="fieldset-title"><?= e(t('Deduct')) ?></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Loan / Advance')) ?></label><div class="input-group"><span class="prefix"><?= e(trim(currency_symbol())) ?></span><input class="input" name="loan" inputmode="decimal" placeholder="0"></div></div>
        <div class="field"><label class="label"><?= e(t('Other deduction')) ?></label><div class="input-group"><span class="prefix"><?= e(trim(currency_symbol())) ?></span><input class="input" name="other" inputmode="decimal" placeholder="0"></div></div>
        <div class="field"><label class="label"><?= e(t('Unpaid days')) ?></label><input class="input" name="unpaid_days" inputmode="decimal" placeholder="0"><span class="hint"><?= e(sprintf(t('Deducted pro-rata from fixed pay over %s working days.'), num($run['working_days']))) ?></span></div>
        <div class="field"><label class="label"><?= e(t('Note on payslip')) ?></label><input class="input" name="note" maxlength="200" placeholder="<?= e(t('Optional')) ?>"></div>
      </div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= e(t('Save')) ?></button></div>
  </form>
</dialog>
<?php endif; ?>

<?php if ($draft && $canEdit): ?>
<dialog id="festival">
  <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/bonus')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Festival bonus for everyone')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Name on the payslip')) ?></label><input class="input" name="label" value="<?= e(t('Eid bonus')) ?>" maxlength="60"></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('How much')) ?></label><select class="input" name="mode"><option value="percent"><?= e(t('% of basic salary')) ?></option><option value="fixed"><?= e(t('Same amount for everyone')) ?></option></select></div>
        <div class="field"><label class="label"><?= e(t('Value')) ?></label><input class="input" name="value" inputmode="decimal" placeholder="100" required></div>
      </div>
      <div class="field"><label class="label"><?= e(t('Only staff with at least … months of service')) ?></label><input class="input" name="min_months" value="0" inputmode="numeric"><span class="hint"><?= e(t('In Bangladesh, festival bonus is usually for workers with 12 months of service. Use 0 for everyone.')) ?></span></div>
      <p class="small muted"><?= e(t('Running this again replaces the earlier festival bonus. Bonuses you typed by hand are kept.')) ?></p>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('zap') ?> <?= e(t('Add bonus')) ?></button></div>
  </form>
</dialog>
<dialog id="paste">
  <form method="post" action="<?= e(url('/app/payroll/' . $run['id'] . '/paste')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Paste from Excel')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <p class="small muted"><?= e(t('Copy the rows from your sheet, including the header row, and paste them here. Columns we understand: Employee ID or Name, Overtime, Bonus, Advance, Other deduction, Unpaid days, Note.')) ?></p>
      <textarea class="input mono" name="data" rows="9" required placeholder="Employee ID&#9;Overtime&#9;Bonus&#9;Unpaid days&#10;EMP-001&#9;2500&#9;0&#9;1&#10;EMP-002&#9;0&#9;5000&#9;0" style="font-size:12.5px"></textarea>
      <p class="small muted"><?= e(t('Values replace what is in the payroll for the columns you paste. Other columns stay as they are.')) ?></p>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('upload') ?> <?= e(t('Apply to payroll')) ?></button></div>
  </form>
</dialog>
<?php endif; ?>
