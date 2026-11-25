<?php $tabs = ['trend' => t('Monthly cost'), 'register' => t('Salary register'), 'departments' => t('By department'), 'yearly' => t('Yearly totals')]; $qs = fn($extra = []) => url('/app/reports?' . http_build_query(array_merge(['tab' => $tab, 'period' => $period, 'year' => $year], $extra))); ?>
<div class="page-head">
  <div><h1><?= e(t('Reports')) ?></h1><p><?= e(t('Salary costs, registers and yearly totals from issued payslips. Download any report as Excel.')) ?></p></div>
  <a class="btn btn-primary" href="<?= e($qs(['export' => 1])) ?>"><?= icon('download') ?> <?= e(t('Download Excel')) ?></a>
</div>
<div class="row" style="gap:6px;margin-bottom:14px;flex-wrap:wrap">
  <?php foreach ($tabs as $k => $lbl): ?><a class="btn btn-sm <?= $tab === $k ? 'btn-dark' : 'btn-light' ?>" href="<?= e(url('/app/reports?tab=' . $k)) ?>"><?= e($lbl) ?></a><?php endforeach; ?>
  <?php if (in_array($tab, ['register', 'departments'], true) && $periods): ?>
    <form method="get" action="<?= e(url('/app/reports')) ?>" style="margin-left:auto"><input type="hidden" name="tab" value="<?= e($tab) ?>"><select class="input" name="period" data-autosubmit style="width:auto"><?php foreach ($periods as $p): ?><option value="<?= e($p) ?>" <?= $p === $period ? 'selected' : '' ?>><?= e(period_label($p)) ?></option><?php endforeach; ?></select></form>
  <?php elseif ($tab === 'yearly'): ?>
    <form method="get" action="<?= e(url('/app/reports')) ?>" style="margin-left:auto"><input type="hidden" name="tab" value="yearly"><select class="input" name="year" data-autosubmit style="width:auto"><?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= e(num($y)) ?></option><?php endfor; ?></select></form>
  <?php endif; ?>
</div>
<?php if ($tab === 'trend' && !empty($report['chart'])): $max = max(array_map(fn($r) => (float)$r['gross'], $report['chart'])) ?: 1; ?>
<div class="card" style="margin-bottom:16px"><div class="card-body">
  <div class="fieldset-title"><?= e(t('Gross salary cost per month')) ?></div>
  <div style="display:flex;align-items:flex-end;justify-content:center;gap:10px;height:180px;padding-top:10px">
    <?php foreach ($report['chart'] as $r): $h = max(4, round((float)$r['gross'] / $max * 150)); ?>
      <div style="flex:1;min-width:0;max-width:90px;text-align:center" title="<?= e(period_label($r['period']) . ': ' . money($r['gross'])) ?>">
        <div class="small" style="font-size:10.5px;white-space:nowrap;overflow:hidden"><?= e(money_fmt($r['gross'], false)) ?></div>
        <div style="height:<?= $h ?>px;background:var(--brand);border-radius:6px 6px 2px 2px;margin-top:4px"></div>
        <div class="small muted" style="font-size:10.5px;margin-top:4px;white-space:nowrap"><?= e(date('M y', strtotime($r['period'] . '-01'))) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div></div>
<?php endif; ?>
<div class="card">
<?php if ($report['rows']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><?php foreach ($report['head'] as $i => $h): ?><th class="<?= in_array($i, $report['money'], true) ? 'num' : '' ?>" style="white-space:nowrap"><?= e(t($h)) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($report['rows'] as $r): ?><tr><?php foreach (array_values($r) as $i => $v): ?><td class="<?= in_array($i, $report['money'], true) ? 'num' : '' ?>" style="white-space:nowrap"><?= in_array($i, $report['money'], true) ? e(money($v)) : e(is_float($v) ? num($v) : (string)$v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    <?php if (count($report['money'])): ?><tfoot><tr><?php foreach ($report['head'] as $i => $h): ?><td class="<?= in_array($i, $report['money'], true) ? 'num' : '' ?>"><b><?= $i === 0 ? e(t('Total')) : (in_array($i, $report['money'], true) ? e(money(array_sum(array_map(fn($r) => (float)array_values($r)[$i], $report['rows'])))) : '') ?></b></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('chart', 24) ?></div><h3><?= e(t('Nothing to report yet')) ?></h3><p><?= e(t('Reports fill in after you finalise your first payroll.')) ?></p></div>
<?php endif; ?>
</div>
