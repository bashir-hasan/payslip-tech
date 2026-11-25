<div class="page-head"><div><h1><?= e(t('Auto payroll')) ?></h1><p><?= e(t('Run payroll on autopilot. A draft is prepared before pay day for you to review, then payslips are issued and emailed automatically.')) ?></p></div>
  <?php partial('result_badge', ['r' => $a['enabled'] ? 'active' : 'inactive']); ?>
</div>

<?php if ($a['enabled'] && !$cronOk): ?>
  <div class="alert alert-warn"><?= icon('alert') ?><div><?= e(t('The scheduler has not run recently. Ask the site administrator to set up the cron job (Admin → Platform settings).')) ?></div></div>
<?php endif; ?>

<div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:18px;align-items:start" data-au>
  <form class="card" method="post" action="<?= e(url('/app/automation')) ?>">
    <?= csrf_field() ?>
    <div class="card-body">
      <label class="check" style="margin-bottom:18px"><input type="checkbox" name="enabled" value="1" <?= $a['enabled'] ? 'checked' : '' ?>> <span><b><?= e(t('Turn on auto payroll')) ?></b><br><span class="small muted"><?= e(t('Uses your active employees and their salary structure every month.')) ?></span></span></label>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Pay day')) ?></label><select class="select" name="pay_day"><option value="last" <?= $a['pay_day'] === 'last' ? 'selected' : '' ?>><?= e(t('Last day of the month')) ?></option><?php for ($d = 1; $d <= 28; $d++): ?><option value="<?= $d ?>" <?= (string)$a['pay_day'] === (string)$d ? 'selected' : '' ?>><?= e(sprintf(t('Day %d'), $d)) ?></option><?php endfor; ?></select></div>
        <div class="field"><label class="label"><?= e(t('Prepare draft (days before)')) ?></label><select class="select" name="lead_days"><?php foreach ([0, 1, 2, 3, 5, 7] as $d): ?><option value="<?= $d ?>" <?= (int)$a['lead_days'] === $d ? 'selected' : '' ?>><?= $d ? e(sprintf(t('%d days before'), $d)) : e(t('On pay day')) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="label"><?= e(t('Working days per month')) ?></label><input class="input" type="number" name="working_days" min="1" max="31" value="<?= e((string)$a['working_days']) ?>"></div>
      </div>
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="auto_finalize" value="1" <?= $a['auto_finalize'] ? 'checked' : '' ?>> <span><?= e(t('Finalise and issue payslips automatically on pay day')) ?></span></label>
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="auto_email" value="1" <?= $a['auto_email'] ? 'checked' : '' ?>> <span><?= e(t('Email payslips to employees automatically')) ?></span></label>
      <label class="check"><input type="checkbox" name="notify" value="1" <?= $a['notify'] ? 'checked' : '' ?>> <span><?= e(t('Email me when the draft is ready and when payslips are issued')) ?></span></label>
    </div>
    <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save')) ?></button></div>
  </form>

  <div class="stack">
    <div class="card"><div class="card-head"><h3><?= e(t('Next run')) ?></h3></div><div class="card-body">
      <?php if ($a['enabled']): ?>
        <div class="checklist">
          <a href="<?= e(url('/app/payroll')) ?>"><span class="tick"><?= icon('clock', 13) ?></span><?= e(sprintf(t('Draft prepared on %s'), date_fmt($nextDraft))) ?></a>
          <a href="<?= e(url('/app/payroll')) ?>"><span class="tick"><?= icon('check', 13) ?></span><?= e($a['auto_finalize'] ? sprintf(t('Payslips issued on %s'), date_fmt($nextPay)) : sprintf(t('You finalise on %s'), date_fmt($nextPay))) ?></a>
        </div>
        <p class="small muted" style="margin:12px 0 0"><?= e(t('Between these dates add bonus, overtime or unpaid days on the draft, or with the smart assistant.')) ?></p>
      <?php else: ?>
        <p class="muted" style="margin:0"><?= e(t('Auto payroll is off.')) ?></p>
      <?php endif; ?>
    </div></div>
    <div class="card"><div class="card-head"><h3><?= e(t('Recent automatic runs')) ?></h3></div>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($a['log'] as $l): ?><tr><td><?= e($l['msg']) ?></td><td class="num small muted"><?= e(time_ago($l['at'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$a['log']): ?><tr><td class="muted"><?= e(t('Nothing yet.')) ?></td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
<style>@media (max-width:960px){[data-au]{grid-template-columns:1fr!important}}</style>
