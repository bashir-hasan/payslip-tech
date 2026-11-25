<?php
$v = fn($k, $d = '') => old($k, $e[$k] ?? $d);
$c = db_one('SELECT * FROM companies WHERE id = ?', [current_user()['company_id']]);
$labels = company_labels($c);
$sym = currency_symbol();
$n = fn($k) => old($k, isset($e[$k]) ? rtrim(rtrim(number_format((float)$e[$k], 2, '.', ''), '0'), '.') : '');
?>
<div class="page-head">
  <div><a class="small" href="<?= e(url('/app/employees')) ?>">← <?= e(t('Employees')) ?></a><h1 style="margin-top:6px"><?= e($e['id'] ? ($e['name'] ?? t('Edit employee')) : t('Add employee')) ?></h1></div>
  <?php if ($e['id']): ?>
    <div class="row" style="gap:8px;flex-wrap:wrap">
    <a class="btn btn-light" href="<?= e(url('/app/employees/' . $e['id'] . '/increment')) ?>"><?= icon('chart') ?> <?= e(t('Increment')) ?><?= plan_pill($c, 'increments') ?></a>
    <?php if ($e['status'] !== 'active' && !str_starts_with((string)$e['name'], 'Erased employee')): ?>
      <form method="post" action="<?= e(url('/app/employees/' . $e['id'] . '/erase')) ?>" data-confirm="<?= e(t('Erase this former employee\'s personal details (name, contact, bank details)? Issued payslips stay as payroll records. This cannot be undone.')) ?>"><?= csrf_field() ?><button class="btn btn-ghost"><?= icon('trash') ?> <?= e(t('Erase personal data')) ?></button></form>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= e(url('/app/employees/' . $e['id'] . '/settlement')) ?>"><?= icon('file') ?> <?= e(t('Final settlement')) ?><?= plan_pill($c, 'settlement') ?></a>
    <form method="post" action="<?= e(url('/app/employees/' . $e['id'] . '/status')) ?>" data-confirm="<?= e($e['status'] === 'active' ? t('Deactivate this employee? They will not be included in future payroll runs. Issued payslips stay valid.') : t('Reactivate this employee?')) ?>">
      <?= csrf_field() ?>
      <button class="btn <?= $e['status'] === 'active' ? 'btn-danger' : 'btn-light' ?>"><?= icon($e['status'] === 'active' ? 'ban' : 'check') ?> <?= e($e['status'] === 'active' ? t('Deactivate') : t('Reactivate')) ?></button>
    </form>
    </div>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(url('/app/employees/save')) ?>" data-salary-form data-sym="<?= e($sym) ?>" data-locale="<?= (CURRENCIES[currency_context()][3] ?? 'intl') === 'sa' ? 'en-IN' : 'en-US' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e((string)($e['id'] ?? '')) ?>">
  <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:18px" data-ef>
    <div class="stack">
      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Personal & job details')) ?></div>
        <div class="form-grid">
          <div class="field"><label class="label"><?= e(t('Full name')) ?> *</label><input class="input" name="name" value="<?= e($v('name')) ?>" required></div>
          <div class="field"><label class="label"><?= e(t('Employee ID')) ?> *</label><input class="input mono" name="emp_code" value="<?= e($v('emp_code')) ?>" required></div>
          <div class="field"><label class="label"><?= e(t('Designation')) ?></label><input class="input" name="designation" value="<?= e($v('designation')) ?>" placeholder="<?= e(t('e.g. Software Engineer')) ?>"></div>
          <div class="field"><label class="label"><?= e(t('Department')) ?></label><input class="input" name="department" value="<?= e($v('department')) ?>"></div>
          <div class="field"><label class="label"><?= e(t('Email')) ?></label><input class="input" type="email" name="email" value="<?= e($v('email')) ?>"><span class="hint"><?= e(t('Needed to invite them to the employee portal.')) ?></span></div>
          <div class="field"><label class="label"><?= e(t('Phone')) ?></label><input class="input" name="phone" value="<?= e($v('phone')) ?>" placeholder="+8801…"></div>
          <div class="field"><label class="label"><?= e(t('Joining date')) ?></label><input class="input" type="date" name="join_date" value="<?= e($v('join_date')) ?>"></div>
        </div>
      </div></div>

      <?php $cfDefs = custom_fields(); if ($cfDefs): $cfVals = json_decode((string)($e['custom'] ?? ''), true) ?: []; ?>
      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Additional details')) ?></div>
        <div class="form-grid">
          <?php foreach ($cfDefs as $f): $name = 'cf_' . $f['key']; $val = old($name, $cfVals[$f['key']] ?? ''); ?>
            <div class="field"><label class="label"><?= e(custom_field_label($f)) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
              <?php if ($f['type'] === 'select'): ?>
                <select class="select" name="<?= e($name) ?>"><option value=""></option><?php foreach (array_filter(array_map('trim', explode(',', $f['options']))) as $opt): ?><option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select>
              <?php else: ?>
                <input class="input" name="<?= e($name) ?>" type="<?= $f['type'] === 'date' ? 'date' : 'text' ?>" <?= $f['type'] === 'number' ? 'inputmode="decimal"' : '' ?> value="<?= e($val) ?>" <?= !empty($f['required']) ? 'required' : '' ?>>
              <?php endif; ?>
              <?php if (!empty($f['on_payslip'])): ?><span class="hint"><?= e(t('Shown on payslips')) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div></div>
      <?php endif; ?>

      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Salary payment')) ?></div>
        <div class="form-grid-3">
          <div class="field"><label class="label"><?= e(t('Method')) ?></label><select class="select" name="payment_method"><?php foreach (payment_methods_for($c['country']) as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $v('payment_method', 'bank') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label class="label"><?= e(t('Bank / wallet name')) ?></label><input class="input" name="bank_name" value="<?= e($v('bank_name')) ?>" placeholder="<?= e(t('e.g. BRAC Bank')) ?>"></div>
          <div class="field"><label class="label"><?= e(t('Account / wallet no.')) ?></label><input class="input mono" name="account_no" value="<?= e($v('account_no')) ?>"><span class="hint"><?= e(t('Only the last 4 digits appear on payslips.')) ?></span></div>
        </div>
      </div></div>
    </div>

    <div class="stack">
      <div class="card"><div class="card-body">
        <div class="row between"><div class="fieldset-title" style="margin:0"><?= e(t('Monthly earnings')) ?></div><button type="button" class="linkbtn small" data-split="<?= e(t('Enter the total monthly gross salary. We will split it as Basic 60%, House rent 30%, Medical 5%, Conveyance 5%.')) ?>"><?= e(t('Split from gross')) ?></button></div>
        <div style="height:12px"></div>
        <?php foreach (['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance'] as $k): $lbl = line_label($labels, $k, lang()) . ($k === 'basic' ? ' *' : ''); ?>
          <div class="field"><label class="label"><?= e($lbl) ?></label><div class="input-group"><span class="prefix"><?= e(trim($sym)) ?></span><input class="input" name="<?= e($k) ?>" value="<?= e($n($k)) ?>" inputmode="decimal" placeholder="0"></div></div>
        <?php endforeach; ?>
        <div class="fieldset-title" style="margin-top:20px"><?= e(t('Monthly deductions')) ?></div>
        <?php foreach (['tax' => 'tax', 'pf' => 'pf', 'other_deduction' => 'other'] as $k => $lk): $lbl = line_label($labels, $lk, lang()); ?>
          <div class="field"><label class="label"><?= e($lbl) ?></label><div class="input-group"><span class="prefix"><?= e(trim($sym)) ?></span><input class="input" name="<?= e($k) ?>" value="<?= e($n($k)) ?>" inputmode="decimal" placeholder="0"></div></div>
        <?php endforeach; ?>
      </div>
      <div class="card-foot">
        <div class="row between small"><span class="muted"><?= e(t('Gross')) ?></span><b data-out="gross"></b></div>
        <div class="row between small"><span class="muted"><?= e(t('Deductions')) ?></span><b data-out="ded"></b></div>
        <div class="row between" style="margin-top:6px"><span><?= e(t('Net pay')) ?></span><b data-out="net" style="font-size:18px"></b></div>
      </div></div>
    </div>
  </div>

  <div class="row" style="margin-top:18px;justify-content:flex-end">
    <a class="btn btn-ghost" href="<?= e(url('/app/employees')) ?>"><?= e(t('Cancel')) ?></a>
    <?php if (!$e['id']): ?><button class="btn btn-light" name="after" value="new"><?= e(t('Save & add another')) ?></button><?php endif; ?>
    <button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save employee')) ?></button>
  </div>
</form>

<?php if (!empty($history)): ?>
<div class="card" style="margin-top:22px">
  <div class="card-head"><h3><?= e(t('Issued payslips')) ?></h3></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(t('Month')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($history as $h): ?><tr><td><?= e(period_label($h['period'])) ?></td><td><span class="codepill"><?= e($h['code']) ?></span></td><td><?php partial('result_badge', ['r' => $h['status']]); ?></td><td class="num"><?= e(money($h['net'])) ?></td><td class="num"><a class="btn btn-light btn-sm" href="<?= e(url('/payslip/' . $h['code'])) ?>"><?= e(t('View')) ?></a></td></tr><?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
<?php clear_old(); ?>
<style>@media (max-width:960px){[data-ef]{grid-template-columns:1fr!important}}</style>
