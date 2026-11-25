<?php $req = array_filter($rows, fn($r) => $r['status'] === 'requested'); $done = array_filter($rows, fn($r) => $r['status'] !== 'requested'); ?>
<div class="page-head">
  <div><h1><?= e(t('Documents')) ?></h1><p><?= e(t('Employment letters, salary certificates and income statements with a QR code. Banks and embassies verify them like payslips.')) ?></p></div>
  <?php if ($emps): ?><button class="btn btn-primary" data-dialog="issuedoc"><?= icon('plus') ?> <?= e(t('Issue document')) ?></button><?php endif; ?>
</div>

<?php $cc = db_one('SELECT * FROM companies WHERE id = ?', [current_user()['company_id']]); $ty0 = (int)date('Y') - ((($cc['country'] ?? 'BD') === 'BD' && (int)date('n') < 7) || (($cc['country'] ?? '') === 'IN' && (int)date('n') < 4) ? 1 : 0); ?>
<form class="card" method="post" action="<?= e(url('/app/documents/tax')) ?>" style="margin-bottom:18px"><div class="card-body row" style="gap:10px;flex-wrap:wrap;align-items:center">
  <?= csrf_field() ?>
  <div style="flex:1;min-width:240px"><b><?= icon('file', 16) ?> <?= e(t('Tax deduction certificates')) ?><?= plan_pill($cc, 'tax_certificate') ?></b><div class="small muted"><?= e(t('One verified certificate per employee, listing the tax deducted from each payslip in the income year. Employees need it for their tax return.')) ?></div></div>
  <select class="input" name="year" style="width:auto"><?php for ($y = $ty0; $y >= $ty0 - 3; $y--): ?><option value="<?= $y ?>"><?= e(tax_year($cc, $y)[2]) ?></option><?php endfor; ?></select>
  <button class="btn btn-light" data-confirm="<?= e(t('Issue tax certificates for everyone paid in this income year?')) ?>"><?= e(t('Issue for all employees')) ?></button>
</div></form>

<?php if ($req): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding-bottom:0"><div class="fieldset-title"><?= e(sprintf(t('Requests from employees (%s)'), num(count($req)))) ?></div></div>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Document')) ?></th><th><?= e(t('Addressed to')) ?></th><th><?= e(t('Requested')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($req as $r): ?>
      <tr>
        <td class="first"><b><?= e($r['name']) ?></b><div class="small muted mono"><?= e($r['emp_code']) ?></div></td>
        <td data-l="<?= e(t('Document')) ?>"><?= e(doc_types()[$r['type']] ?? $r['type']) ?><?php if ($r['purpose']): ?><div class="small muted"><?= e($r['purpose']) ?></div><?php endif; ?></td>
        <td data-l="<?= e(t('Addressed to')) ?>"><?= e($r['addressed_to'] ?: t('To whom it may concern')) ?></td>
        <td data-l="<?= e(t('Requested')) ?>" class="small"><?= e(date_fmt($r['created_at'], null, true)) ?></td>
        <td class="num"><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <form method="post" action="<?= e(url('/app/documents/issue')) ?>" data-confirm="<?= e(t('Issue this document? It is signed with today\'s details and becomes verifiable straight away.')) ?>"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= e((string)$r['id']) ?>"><button class="btn btn-primary btn-sm"><?= icon('check', 15) ?> <?= e(t('Issue')) ?></button></form>
          <form method="post" action="<?= e(url('/app/documents/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><button class="btn btn-ghost btn-sm"><?= e(t('Decline')) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
<?php if ($done): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Document')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Checks')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($done as $r): ?>
      <tr>
        <td class="first"><b><?= e($r['name']) ?></b><div class="small muted mono"><?= e($r['emp_code']) ?></div></td>
        <td data-l="<?= e(t('Document')) ?>"><?= e(doc_types()[$r['type']] ?? $r['type']) ?><?= $r['year'] ? ' ' . e(num($r['year'])) : '' ?><div class="small muted"><?= e($r['addressed_to'] ?: '') ?></div></td>
        <td data-l="<?= e(t('Code')) ?>"><?= $r['code'] ? '<span class="codepill">' . e($r['code']) . '</span>' : '—' ?></td>
        <td data-l="<?= e(t('Status')) ?>"><?php if ($r['status'] === 'declined'): ?><span class="badge"><?= e(t('Declined')) ?></span><?php else: partial('result_badge', ['r' => $r['status']]); endif; ?></td>
        <td class="num" data-l="<?= e(t('Checks')) ?>"><?= e(num($r['checks'])) ?></td>
        <td class="num"><?php if ($r['code']): ?><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <a class="btn btn-light btn-sm" href="<?= e(url('/document/' . $r['code'])) ?>"><?= icon('download', 15) ?> <?= e(t('View / PDF')) ?></a>
          <?php if ($r['status'] === 'issued' && can_do('documents')): ?><button type="button" class="btn btn-danger btn-sm" data-dialog="revokedoc" data-action="<?= e(url('/app/documents/' . $r['id'])) ?>" data-fill='<?= e(json_encode(['code' => $r['code'], 'reason' => ''])) ?>'><?= e(t('Revoke')) ?></button><?php endif; ?>
        </div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('award', 24) ?></div><h3><?= e(t('No documents yet')) ?></h3><p><?= e(t('Issue an employment letter or salary certificate in seconds, instead of typing one in Word and stamping it.')) ?></p></div>
<?php endif; ?>
</div>

<dialog id="issuedoc">
  <form method="post" action="<?= e(url('/app/documents/issue')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Issue document')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Employee')) ?></label><select class="input" name="employee_id" required><option value=""><?= e(t('Choose…')) ?></option><?php foreach ($emps as $e): ?><option value="<?= e((string)$e['id']) ?>"><?= e($e['name'] . ' (' . $e['emp_code'] . ')') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label"><?= e(t('Document')) ?></label><select class="input" name="type"><?php foreach (base_doc_types() as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select><span class="hint"><?= e(t('Salary certificates show the current monthly salary. Income statements list every issued payslip for the year.')) ?></span></div>
      <div class="field"><label class="label"><?= e(t('Year (income statement only)')) ?></label><input class="input" name="year" value="<?= e(date('Y')) ?>" inputmode="numeric"></div>
      <div class="field"><label class="label"><?= e(t('Addressed to')) ?></label><input class="input" name="addressed_to" maxlength="200" placeholder="<?= e(t('e.g. The Visa Officer, Embassy of Italy, Dhaka')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Purpose')) ?></label><input class="input" name="purpose" maxlength="200" placeholder="<?= e(t('e.g. Tourist visa application, home loan')) ?>"></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('award') ?> <?= e(t('Issue')) ?></button></div>
  </form>
</dialog>
<dialog id="revokedoc">
  <form method="post" action="">
    <?= csrf_field() ?><input type="hidden" name="action" value="revoke">
    <div class="dialog-head"><div><h3><?= e(t('Revoke document')) ?></h3><span class="small muted mono" data-text="code"></span></div><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body"><p class="muted"><?= e(t('Anyone who verifies it will see that it was revoked, with your reason. This cannot be undone.')) ?></p><div class="field"><label class="label"><?= e(t('Reason')) ?></label><input class="input" name="reason" maxlength="255" required></div></div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-danger"><?= icon('ban') ?> <?= e(t('Revoke')) ?></button></div>
  </form>
</dialog>
