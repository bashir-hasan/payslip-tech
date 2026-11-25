<?php
$examples = lang() === 'bn'
    ? ['এই মাসের বেতন প্রক্রিয়া চালাও', 'রহিম বোনাস ৫০০০, সাদিয়া ২ দিন অনুপস্থিত', 'সবাইকে বোনাস ২০০০', 'চূড়ান্ত করো এবং ইমেইল পাঠাও', 'মোট বেতন কত?']
    : ['Run payroll for this month', 'Rahim bonus 5000, Sadia 2 unpaid days', 'Give everyone 2000 bonus', 'Tanvir overtime 3500 and advance 2000', 'Finalise and email payslips', 'Add employee Karim Ali as Driver salary 20000', 'What is the total payroll?', 'Who has no email?'];
?>
<div class="page-head"><div><h1><?= e(t('Smart assistant')) ?></h1><p><?= e(t('Tell it what to do in plain English or Bangla. It shows a preview and only changes anything after you press Apply.')) ?></p></div>
  <div class="row"><span class="badge badge-brand plain"><?= e(sprintf(t('%s of %s actions left this month'), num($remaining), num($limit))) ?></span>
  <?php if ($history): ?><form method="post" action="<?= e(url('/app/assistant/clear')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(t('Clear')) ?></button></form><?php endif; ?></div>
</div>

<div class="card" style="max-width:900px">
  <div class="card-body chat-log">
    <div class="msg bot"><div class="bubble"><?= e(t('Hi! I can run payroll, add bonuses, overtime, advances and unpaid days, finalise, email payslips, add employees and answer questions about your payroll.')) ?></div></div>
    <?php foreach ($history as $h): ?>
      <?php if ($h['q'] !== ''): ?><div class="msg me"><div class="bubble"><?= e($h['q']) ?></div></div><?php endif; ?>
      <div class="msg bot"><div class="bubble <?= !empty($h['done']) ? 'done' : '' ?>"><?php foreach ($h['a'] as $line): ?><div><?= e($line) ?></div><?php endforeach; ?></div></div>
    <?php endforeach; ?>
    <?php if ($pending): ?>
      <div class="msg bot"><div class="bubble preview">
        <b><?= e(t('Preview')) ?></b>
        <ol><?php foreach ($pending['labels'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ol>
        <div class="row"><form method="post" action="<?= e(url('/app/assistant/apply')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><?= icon('check', 15) ?> <?= e(t('Apply')) ?></button></form><form method="post" action="<?= e(url('/app/assistant/clear')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm"><?= e(t('Cancel')) ?></button></form></div>
      </div></div>
    <?php endif; ?>
    <span id="bottom"></span>
  </div>
  <form class="card-foot" method="post" action="<?= e(url('/app/assistant')) ?>">
    <?= csrf_field() ?>
    <div class="row" style="flex-wrap:nowrap"><textarea class="textarea" name="q" rows="2" required placeholder="<?= e(t('e.g. Rahim bonus 5000, Sadia 2 unpaid days, then finalise')) ?>" data-enter-submit style="min-height:52px"></textarea><button class="btn btn-primary" style="height:52px"><?= icon('send') ?></button></div>
    <div class="chips"><?php foreach ($examples as $ex): ?><button type="button" class="chip-btn" data-fill-input="<?= e($ex) ?>"><?= e($ex) ?></button><?php endforeach; ?></div>
  </form>
</div>
<p class="small muted" style="max-width:900px"><?= e(t('Tips: use the employee name or ID; amounts like 5000, 5,000 or 5k work; "everyone" applies to all active employees; months like "November" or "this month" are understood. Applying a set of changes uses one action.')) ?></p>
