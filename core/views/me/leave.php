<div class="page-head">
  <div><h1><?= e(t('Leave & advances')) ?></h1><p><?= e(sprintf(t('Your leave balance for %s and any salary advances being repaid.'), num(date('Y')))) ?></p></div>
  <?php if ($types): ?><button class="btn btn-primary" data-dialog="reqleave"><?= icon('plus') ?> <?= e(t('Request leave')) ?></button><?php endif; ?>
</div>
<div class="grid grid-3" style="margin-bottom:18px">
  <?php foreach ($balances as $b): ?>
    <div class="stat"><div class="k"><?= icon('calendar', 16) ?> <?= e($b['type']['name']) ?></div>
      <div class="v"><?= $b['left'] !== null ? e(num($b['left'] + 0)) : '∞' ?></div>
      <div class="d"><?= e($b['left'] !== null ? sprintf(t('days left of %s · %s used'), num($b['entitled'] + 0), num($b['used'] + 0)) : sprintf(t('%s days used'), num($b['used'] + 0))) ?><?= $b['pending'] ? ' · ' . e(sprintf(t('%s pending'), num($b['pending'] + 0))) : '' ?></div></div>
  <?php endforeach; ?>
</div>
<div class="card">
<?php if ($requests): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Type')) ?></th><th><?= e(t('Dates')) ?></th><th class="num"><?= e(t('Days')) ?></th><th><?= e(t('Status')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td class="first"><b><?= e($r['type_name']) ?></b><?php if ($r['reason']): ?><div class="small muted"><?= e($r['reason']) ?></div><?php endif; ?></td>
        <td data-l="<?= e(t('Dates')) ?>"><?= e(date_fmt($r['start_date'])) ?> – <?= e(date_fmt($r['end_date'])) ?></td>
        <td class="num" data-l="<?= e(t('Days')) ?>"><?= e(num($r['days'] + 0)) ?></td>
        <td data-l="<?= e(t('Status')) ?>"><span class="badge <?= ['pending' => 'badge-info', 'approved' => 'badge-ok', 'rejected' => 'badge-bad'][$r['status']] ?? '' ?>"><?= e(['pending' => t('Pending'), 'approved' => t('Approved'), 'rejected' => t('Declined'), 'cancelled' => t('Cancelled')][$r['status']] ?? $r['status']) ?></span><?php if ($r['decision_note']): ?><div class="small muted"><?= e($r['decision_note']) ?></div><?php endif; ?></td>
        <td class="num"><?php if ($r['status'] === 'pending'): ?><form method="post" action="<?= e(url('/me/leave/' . $r['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('Cancel')) ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('calendar', 24) ?></div><h3><?= e(t('No leave requests yet')) ?></h3><p><?= e(t('Request leave here instead of on WhatsApp. You get an email when it is approved.')) ?></p></div>
<?php endif; ?>
</div>

<?php if ($loans): ?>
<div class="card" style="margin-top:18px"><div class="card-body">
  <div class="fieldset-title"><?= e(t('Advances being repaid')) ?></div>
  <?php foreach ($loans as $l): $left = max(0, $l['principal'] - $l['paid']); ?>
    <div class="row" style="justify-content:space-between;padding:8px 0;border-top:1px solid var(--line)"><div><b><?= e($l['title']) ?></b><div class="small muted"><?= e(sprintf(t('%s per month from %s'), money($l['installment']), period_label($l['start_period']))) ?></div></div><div style="text-align:right"><b><?= e(money($left)) ?></b><div class="small muted"><?= e(sprintf(t('left of %s'), money($l['principal']))) ?></div></div></div>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>

<dialog id="reqleave">
  <form method="post" action="<?= e(url('/me/leave')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Request leave')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Leave type')) ?></label><select class="input" name="type_id" required><?php foreach ($types as $t): ?><option value="<?= e((string)$t['id']) ?>"><?= e($t['name']) ?><?= (int)$t['paid'] ? '' : ' (' . e(t('Unpaid')) . ')' ?></option><?php endforeach; ?></select></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('From')) ?></label><input class="input" type="date" name="start_date" required min="<?= e(date('Y-m-d', strtotime('-30 days'))) ?>"></div>
        <div class="field"><label class="label"><?= e(t('To')) ?></label><input class="input" type="date" name="end_date" required></div>
      </div>
      <div class="field"><label class="label"><?= e(t('Reason')) ?></label><input class="input" name="reason" maxlength="300"></div>
      <p class="small muted"><?= e(t('Weekly holidays are not counted.')) ?></p>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('send') ?> <?= e(t('Send request')) ?></button></div>
  </form>
</dialog>
