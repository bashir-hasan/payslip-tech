<?php $sal = salary_of($e); $labels = company_labels($c); $kl = ['basic' => line_label($labels, 'basic', lang()), 'house_rent' => line_label($labels, 'house_rent', lang()), 'medical' => line_label($labels, 'medical', lang()), 'conveyance' => line_label($labels, 'conveyance', lang()), 'other_allowance' => line_label($labels, 'other_allowance', lang())]; ?>
<div class="page-head">
  <div><a class="small" href="<?= e(url('/app/employees/' . $e['id'])) ?>">← <?= e($e['name']) ?></a><h1 style="margin-top:6px"><?= e(t('Increment or promotion')) ?></h1><p><?= e(t('Set the new salary once. It starts automatically from the month you choose, and the employee gets a verified increment letter.')) ?></p></div>
</div>
<?php if (!$allowed): ?><div class="alert alert-info"><?= icon('lock') ?><div><?= e(sprintf(t('%s is part of the %s plan.'), feature_names()['increments'], plans()['business']['name'])) ?> <?php if (billing_enabled()): ?><a href="<?= e(url('/app/billing')) ?>"><?= e(t('See plans')) ?> →</a><?php endif; ?></div></div><?php endif; ?>
<div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:18px" data-ef>
<form class="card" method="post" action="<?= e(url('/app/employees/' . $e['id'] . '/increment')) ?>"><div class="card-body">
  <?= csrf_field() ?>
  <div class="form-grid">
    <div class="field"><label class="label"><?= e(t('New salary starts from')) ?></label><input class="input" type="month" name="effective_period" value="<?= e(date('Y-m')) ?>" required></div>
    <div class="field"><label class="label"><?= e(t('Increase all parts by (%)')) ?></label><input class="input" name="percent" inputmode="decimal" placeholder="10"><span class="hint"><?= e(t('Or leave empty and type the new amounts below.')) ?></span></div>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(t('Salary part')) ?></th><th class="num"><?= e(t('Now')) ?></th><th><?= e(t('New')) ?></th></tr></thead>
    <tbody><?php foreach (SALARY_KEYS as $k): ?><tr><td><?= e($kl[$k]) ?></td><td class="num"><?= e(money($sal[$k])) ?></td><td><input class="input" name="new_<?= e($k) ?>" value="<?= e(rtrim(rtrim(number_format($sal[$k], 2, '.', ''), '0'), '.')) ?>" inputmode="decimal" style="max-width:160px"></td></tr><?php endforeach; ?>
    <tr><td><b><?= e(t('Gross')) ?></b></td><td class="num"><b><?= e(money(array_sum($sal))) ?></b></td><td></td></tr></tbody>
  </table></div>
  <div class="form-grid" style="margin-top:12px">
    <div class="field"><label class="label"><?= e(t('New designation (promotion)')) ?></label><input class="input" name="designation" placeholder="<?= e($e['designation'] ?: '') ?>"></div>
    <div class="field"><label class="label"><?= e(t('Reason')) ?></label><input class="input" name="reason" maxlength="255" placeholder="<?= e(t('e.g. Annual increment 2026, Good performance')) ?>"></div>
  </div>
  <label class="check" style="margin:8px 0 14px"><input type="checkbox" name="letter" value="1" checked> <span><?= e(t('Issue a verified increment letter and email it to the employee')) ?></span></label>
  <button class="btn btn-primary" <?= $allowed ? '' : 'disabled' ?>><?= icon('check') ?> <?= e(t('Save increment')) ?></button>
</div></form>
<div class="card"><div class="card-body">
  <div class="fieldset-title"><?= e(t('Salary history')) ?></div>
  <?php if ($history): foreach ($history as $h): $o = json_decode($h['old_salary'], true); $n = json_decode($h['new_salary'], true); $og = array_sum($o); $ng = array_sum($n); ?>
    <div style="padding:10px 0;border-top:1px solid var(--line)">
      <div class="row" style="justify-content:space-between"><b><?= e(period_label($h['effective_period'])) ?></b><span class="badge <?= $h['status'] === 'applied' ? 'badge-ok' : 'badge-info' ?>"><?= e($h['status'] === 'applied' ? t('Applied') : t('Scheduled')) ?></span></div>
      <div class="small"><?= e(money($og)) ?> → <b><?= e(money($ng)) ?></b><?= $og > 0 ? ' (' . e(($ng >= $og ? '+' : '') . num(round(($ng - $og) / $og * 100, 1))) . '%)' : '' ?><?= $h['new_designation'] ? ' · ' . e($h['new_designation']) : '' ?></div>
      <?php if ($h['reason']): ?><div class="small muted"><?= e($h['reason']) ?></div><?php endif; ?>
      <?php if ($h['code']): ?><a class="small" href="<?= e(url('/document/' . $h['code'])) ?>"><?= e(t('Letter')) ?> <?= e($h['code']) ?></a><?php endif; ?>
    </div>
  <?php endforeach; else: ?><p class="small muted"><?= e(t('No salary changes recorded yet.')) ?></p><?php endif; ?>
</div></div>
</div>
