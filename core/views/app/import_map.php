<div class="page-head"><div><a class="small" href="<?= e(url('/app/employees/import')) ?>">← <?= e(t('Upload another file')) ?></a><h1 style="margin-top:6px"><?= e(t('Check column matching')) ?></h1><p><?= e(sprintf(t('%d rows found. We matched the columns automatically; change any that are wrong, then import.'), $count)) ?></p></div></div>
<?php $hasAdj = (bool)array_filter($guess, fn($g) => str_starts_with((string)$g, 'adj_')); if ($hasAdj && !$draft): ?>
  <div class="alert alert-warn"><?= icon('alert') ?><div><?= e(t('Bonus / overtime / unpaid-day columns need a draft payroll. Create one first, or those columns will be ignored.')) ?></div></div>
<?php elseif ($draft): ?>
  <div class="alert alert-info"><?= icon('alert') ?><div><?= e(sprintf(t('Monthly columns (bonus, overtime, advance, unpaid days) will be added to the %s draft payroll.'), period_label($draft['period']))) ?></div></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/app/employees/import/run')) ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('Column in your file')) ?></th><th><?= e(t('Import as')) ?></th><th><?= e(t('Sample values')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($headers as $i => $h): ?>
        <tr>
          <td><b><?= e($h !== '' ? $h : sprintf(t('Column %d'), $i + 1)) ?></b></td>
          <td style="min-width:240px"><select class="select" name="map[<?= $i ?>]"><option value=""><?= e(t('— Ignore —')) ?></option><?php foreach ($targets as $k => [$label]): ?><option value="<?= e($k) ?>" <?= ($guess[$i] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
          <td class="small muted"><?= e(implode(' · ', array_filter(array_map(fn($r) => mb_substr((string)($r[$i] ?? ''), 0, 24), $rows)))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="card-foot row between"><span class="small muted"><?= e(sprintf(t('Uses 1 smart action (%s left this month).'), num($remaining))) ?></span><button class="btn btn-primary"><?= icon('check') ?> <?= e(sprintf(t('Import %d rows'), $count)) ?></button></div>
  </div>
</form>
