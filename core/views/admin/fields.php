<?php $types = ['text' => t('Text'), 'number' => t('Number'), 'date' => t('Date'), 'select' => t('Dropdown')]; ?>
<div class="page-head"><div><h1><?= e(t('Custom fields')) ?></h1><p><?= e(t('Add extra employee details for every company, such as NID (last 4), TIN, blood group, branch or grade. Choose which ones print on payslips.')) ?></p></div></div>

<div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:18px;align-items:start" data-cf>
  <div class="card">
    <div class="card-head"><h3><?= e(t('Employee fields')) ?></h3><span class="badge plain"><?= e(num(count($fields))) ?>/20</span></div>
    <?php if ($fields): ?>
    <div class="table-wrap"><table class="table stack-m">
      <thead><tr><th><?= e(t('Field')) ?></th><th><?= e(t('Type')) ?></th><th><?= e(t('On payslip')) ?></th><th><?= e(t('Required')) ?></th><th class="num"></th></tr></thead>
      <tbody>
      <?php foreach ($fields as $i => $f): $btn = function ($action, $extra, $label, $cls = 'btn-light') use ($f) { return '<form method="post" action="' . e(url('/admin/fields')) . '" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="key" value="' . e($f['key']) . '">' . $extra . '<button class="btn btn-sm ' . $cls . '">' . $label . '</button></form>'; }; ?>
        <tr>
          <td class="first"><b><?= e($f['label']) ?></b><?php if (!empty($f['label_bn'])): ?> <span class="muted">· <?= e($f['label_bn']) ?></span><?php endif; ?><div class="small muted mono"><?= e($f['key']) ?><?= $f['type'] === 'select' ? ' · ' . e($f['options']) : '' ?></div></td>
          <td data-l="<?= e(t('Type')) ?>"><?= e($types[$f['type']] ?? $f['type']) ?></td>
          <td data-l="<?= e(t('On payslip')) ?>"><?= $btn('toggle', '<input type="hidden" name="prop" value="on_payslip">', e(!empty($f['on_payslip']) ? t('Yes') : t('No'))) ?></td>
          <td data-l="<?= e(t('Required')) ?>"><?= $btn('toggle', '<input type="hidden" name="prop" value="required">', e(!empty($f['required']) ? t('Yes') : t('No'))) ?></td>
          <td class="num"><div class="row" style="justify-content:flex-end;gap:4px;flex-wrap:nowrap">
            <?php if ($i > 0): ?><?= $btn('move', '<input type="hidden" name="dir" value="up">', '↑', 'btn-ghost') ?><?php endif; ?>
            <?php if ($i < count($fields) - 1): ?><?= $btn('move', '<input type="hidden" name="dir" value="down">', '↓', 'btn-ghost') ?><?php endif; ?>
            <?= $btn('delete', '', icon('trash', 15), 'btn-danger') ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?>
      <div class="empty"><div class="ic-wrap"><?= icon('plus', 24) ?></div><h3><?= e(t('No custom fields yet')) ?></h3><p><?= e(t('Add your first field using the form.')) ?></p></div>
    <?php endif; ?>
  </div>

  <form class="card" method="post" action="<?= e(url('/admin/fields')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="card-head"><h3><?= e(t('Add a field')) ?></h3></div>
    <div class="card-body">
      <div class="field"><label class="label"><?= e(t('Label (English)')) ?> *</label><input class="input" name="label" maxlength="60" required placeholder="<?= e(t('e.g. Blood group')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Label (Bangla)')) ?></label><input class="input" name="label_bn" maxlength="60" placeholder="রক্তের গ্রুপ"></div>
      <div class="field"><label class="label"><?= e(t('Type')) ?></label><select class="select" name="type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label"><?= e(t('Dropdown options (comma separated)')) ?></label><input class="input" name="options" placeholder="A+, A-, B+, B-, O+, O-, AB+, AB-"><span class="hint"><?= e(t('Only used for the Dropdown type.')) ?></span></div>
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="on_payslip" value="1"> <?= e(t('Show on payslips')) ?></label>
      <label class="check"><input type="checkbox" name="required" value="1"> <?= e(t('Required')) ?></label>
      <p class="small muted" style="margin:14px 0 0"><?= e(t('Tip: do not collect full national ID or passport numbers. Store only what payslips need.')) ?></p>
    </div>
    <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icon('plus') ?> <?= e(t('Add field')) ?></button></div>
  </form>
</div>
<style>@media (max-width:960px){[data-cf]{grid-template-columns:1fr!important}}</style>
