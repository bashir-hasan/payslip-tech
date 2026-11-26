<?php
$doneCount = count(array_filter($checklist, fn($i) => $i['done']));
$maxNet = max(1, ...array_map(fn($r) => (float)$r['total_net'], $runs ?: [['total_net' => 1]]));
$a = $attention;
$features = function (array $p): array {
    $lines = array_values(array_filter(array_map('trim', explode("\n", (string)$p['features'])), fn($l) => $l !== '' && stripos($l, 'Everything in') !== 0));
    return array_slice($lines, 0, 5);
};
$order = array_keys($plans);
$curIdx = array_search($plan['key'], $order, true);
$upgrades = array_filter($plans, fn($p, $k) => array_search($k, $order, true) > $curIdx && (float)$p['price'] > 0, ARRAY_FILTER_USE_BOTH);
?>
<div class="page-head">
  <div>
    <h1><?= e(sprintf(t('Hello, %s'), explode(' ', current_user()['name'])[0])) ?> 👋</h1>
    <p><?= e(t('Here is what is happening with payroll and verifications.')) ?></p>
  </div>
  <div class="row">
    <a class="btn btn-light" href="<?= e(url('/app/assistant')) ?>"><?= icon('zap') ?> <?= e(t('Smart assistant')) ?></a>
    <a class="btn btn-light" href="<?= e(url('/app/employees/new')) ?>"><?= icon('plus') ?> <?= e(t('Add employee')) ?></a>
    <a class="btn btn-primary" href="<?= e(url('/app/payroll')) ?>"><?= icon('wallet') ?> <?= e(t('Run payroll')) ?></a>
  </div>
</div>

<?php if ($doneCount < count($checklist)): ?>
<div class="card setup-strip" style="margin-bottom:18px">
  <div class="setup-strip-head"><h3><?= e(t('Getting started')) ?></h3><span class="badge badge-brand plain"><?= e(num($doneCount)) ?>/<?= e(num(count($checklist))) ?></span></div>
  <div class="setup-steps">
    <?php foreach ($checklist as $n => $i): ?>
      <a href="<?= e(url($i['url'])) ?>" class="<?= $i['done'] ? 'done' : '' ?>"><span class="tick"><?= $i['done'] ? icon('check', 13) : e((string)($n + 1)) ?></span><?= e($i['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('wallet', 16) ?> <?= e(t('Last payroll')) ?></div><div class="v"><?= $lastRun ? e(money($lastRun['total_net'])) : '—' ?></div><div class="d"><?= $lastRun ? e(period_label($lastRun['period'])) . ' · ' . e($lastRun['status'] === 'finalized' ? t('Finalised') : t('Draft')) : e(t('No payroll yet')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Active employees')) ?></div><div class="v"><?= e(num($stats['employees'])) ?><?= $limit ? ' <span class="muted" style="font-size:16px">/ ' . e(num($limit)) . '</span>' : '' ?></div><div class="d"><a href="<?= e(url('/app/employees')) ?>"><?= e(t('Manage team')) ?> →</a></div></div>
  <div class="stat"><div class="k"><?= icon('file', 16) ?> <?= e(t('Payslips issued')) ?></div><div class="v"><?= e(num($stats['issued'])) ?></div><div class="d"><?= e(t('All with QR verification')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Verifications')) ?></div><div class="v"><?= e(num($stats['verifications'])) ?></div><div class="d"><?= e(sprintf(t('%s in the last 30 days'), num($stats['verifications30']))) ?></div></div>
</div>

<div class="grid dash-row" style="margin-bottom:18px">
  <div class="card">
    <div class="card-head"><h3><?= e(t('Net payroll by month')) ?></h3><a class="small" href="<?= e(url('/app/payroll')) ?>"><?= e(t('All payroll')) ?> →</a></div>
    <div class="card-body">
      <?php if ($runs): ?>
        <div class="bars">
          <?php foreach ($runs as $r): $h = max(3, round((float)$r['total_net'] / $maxNet * 100)); ?>
            <div class="bar <?= $r['status'] === 'draft' ? 'draft' : '' ?>" title="<?= e(money($r['total_net'])) ?>">
              <em><?= e(money_fmt(round((float)$r['total_net'] / 1000), false)) ?>k</em>
              <i style="height:<?= $h ?>%"></i>
              <span><?= e(mb_substr(period_label($r['period']), 0, 3) . ' ' . substr($r['period'], 2, 2)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="small muted" style="margin:12px 0 0"><?= e(t('Striped bars are drafts that have not been finalised yet.')) ?></p>
      <?php else: ?>
        <div class="empty" style="padding:24px"><div class="ic-wrap"><?= icon('chart', 24) ?></div><p><?= e(t('Your payroll history will appear here after your first run.')) ?></p></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3><?= e(t('Needs attention')) ?></h3></div>
    <div class="todo-list">
      <?php
      $run = $a['run'];
      $runLabel = !$run ? t('Not started') : ($run['status'] === 'draft' ? t('Draft, not issued yet') : t('Issued'));
      $runTone = !$run ? 'warn' : ($run['status'] === 'draft' ? 'warn' : 'ok');
      ?>
      <a href="<?= e(url($run ? '/app/payroll/' . $run['id'] : '/app/payroll')) ?>" class="todo <?= $runTone ?>"><span class="ti"><?= icon('wallet', 16) ?></span><span><b><?= e(sprintf(t('%s payroll'), period_label($a['period']))) ?></b><?= e($runLabel) ?></span><?= icon('arrow-right', 15) ?></a>
      <?php if ($a['deadline']): ?>
        <a href="<?= e(url($a['deadline']['run'] ? '/app/payroll/' . $a['deadline']['run']['id'] : '/app/payroll')) ?>" class="todo bad"><span class="ti"><?= icon('clock', 16) ?></span><span><b><?= e(sprintf(t('Pay %s wages by %s'), period_label($a['deadline']['period']), date_fmt($a['deadline']['date']))) ?></b><?= e(t('Bangladesh Labour Act: 7th working day')) ?></span><?= icon('arrow-right', 15) ?></a>
      <?php endif; ?>
      <a href="<?= e(url('/app/leave')) ?>" class="todo <?= $a['leave'] ? 'warn' : 'ok' ?>"><span class="ti"><?= icon('calendar', 16) ?></span><span><b><?= e($a['leave'] ? sprintf(t($a['leave'] === 1 ? '%s leave request to review' : '%s leave requests to review'), num($a['leave'])) : t('No leave requests waiting')) ?></b><?= e(t('Leave & attendance')) ?></span><?= icon('arrow-right', 15) ?></a>
      <a href="<?= e(url('/app/documents')) ?>" class="todo <?= $a['letters'] ? 'warn' : 'ok' ?>"><span class="ti"><?= icon('award', 16) ?></span><span><b><?= e($a['letters'] ? sprintf(t($a['letters'] === 1 ? '%s letter requested' : '%s letters requested'), num($a['letters'])) : t('No letter requests')) ?></b><?= e(t('Documents')) ?></span><?= icon('arrow-right', 15) ?></a>
      <a href="<?= e(url('/app/loans')) ?>" class="todo info"><span class="ti"><?= icon('bank', 16) ?></span><span><b><?= e($a['loans'] ? sprintf(t($a['loans'] === 1 ? '%s active loan' : '%s active loans'), num($a['loans'])) : t('No active loans')) ?></b><?= e($a['loans'] ? sprintf(t('%s still to be repaid'), money($a['loan_left'])) : t('Loans & advances')) ?></span><?= icon('arrow-right', 15) ?></a>
    </div>
  </div>
</div>

<?php if ($showPlans): ?>
<section class="card plan-box" style="margin-bottom:18px" aria-labelledby="plan-h">
  <div class="plan-now">
    <p class="eyebrow-sm"><?= e(t('Your plan')) ?></p>
    <h3 id="plan-h"><?= e($isDemo ? t('Demo company') : sprintf(t('%s plan'), $plan['name'])) ?></h3>
    <?php if ($isDemo): ?>
      <p class="small muted"><?= e(t('Everything is unlocked in the demo. Create your own account to start free and upgrade anytime.')) ?></p>
      <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>"><?= e(t('Start free')) ?> →</a>
    <?php else: ?>
      <?php if ($limit): ?>
        <div class="usage"><span><?= e(t('Employees')) ?></span><b><?= e(num($active)) ?> / <?= e(num($limit)) ?></b></div>
        <div class="progress <?= $active >= $limit ? 'full' : '' ?>"><i style="width:<?= min(100, round($active / max(1, $limit) * 100)) ?>%"></i></div>
        <?php if ($active >= $limit): ?><p class="small" style="color:var(--danger);margin:8px 0 0"><?= e(t('You have reached your employee limit. Upgrade to add more.')) ?></p><?php endif; ?>
      <?php endif; ?>
      <?php if ($plan['key'] !== 'free' && !empty($c['plan_renews_at'])): ?><p class="small muted" style="margin:10px 0 0"><?= e(sprintf(!empty($c['plan_cancel_at_end']) ? t('Ends on %s') : t('Renews on %s'), date_fmt($c['plan_renews_at']))) ?></p><?php endif; ?>
      <a class="small" href="<?= e(url('/app/billing')) ?>" style="display:inline-block;margin-top:12px"><?= e(t('Compare all plans')) ?> →</a>
    <?php endif; ?>
  </div>
  <?php if ($upgrades): ?>
  <div class="plan-offers">
    <?php foreach ($upgrades as $k => $p): ?>
      <div class="offer <?= $p['badge'] ? 'featured' : '' ?>">
        <div class="offer-top"><b><?= e($p['name']) ?></b><?php if ($p['badge']): ?><span class="badge badge-brand plain"><?= e($p['badge']) ?></span><?php endif; ?></div>
        <div class="offer-price">$<?= e(rtrim(rtrim(number_format((float)$p['price'], 2), '0'), '.')) ?> <small><?= e(t('/ month')) ?></small></div>
        <ul><?php foreach ($features($p) as $f): ?><li><?= icon('check', 14) ?><?= e($f) ?></li><?php endforeach; ?></ul>
        <?php if ($isDemo): ?>
          <a class="btn <?= $p['badge'] ? 'btn-primary' : 'btn-light' ?> btn-block btn-sm" href="<?= e(url('/register')) ?>"><?= e(sprintf(t('Choose %s'), $p['name'])) ?></a>
        <?php elseif ($p['product_id'] === ''): ?>
          <button class="btn btn-light btn-block btn-sm" disabled><?= e(t('Coming soon')) ?></button>
        <?php elseif (!empty($c['polar_customer_id']) && $plan['key'] !== 'free'): ?>
          <form method="post" action="<?= e(url('/app/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-block btn-sm"><?= e(sprintf(t('Switch to %s'), $p['name'])) ?></button></form>
        <?php else: ?>
          <form method="post" action="<?= e(url('/app/billing/checkout/' . $k)) ?>"><?= csrf_field() ?><button class="btn <?= $p['badge'] ? 'btn-primary' : 'btn-light' ?> btn-block btn-sm"><?= icon('zap', 15) ?> <?= e(sprintf(t('Upgrade to %s'), $p['name'])) ?></button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="plan-offers single"><p class="muted small" style="margin:0"><?= e(t('You are on our top plan. Thank you for supporting PaySlip.tech.')) ?></p>
    <?php if (!empty($c['polar_customer_id'])): ?><form method="post" action="<?= e(url('/app/billing/portal')) ?>" style="margin-top:10px"><?= csrf_field() ?><button class="btn btn-light btn-sm"><?= icon('settings', 15) ?> <?= e(t('Manage subscription & invoices')) ?></button></form><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h3><?= e(t('Recent verifications')) ?></h3><a class="small" href="<?= e(url('/app/verifications')) ?>"><?= e(t('View log')) ?> →</a></div>
  <?php if ($recentVer): ?>
    <div class="table-wrap"><table class="table stack-m">
      <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Result')) ?></th><th><?= e(t('Checked by')) ?></th><th class="num"><?= e(t('When')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($recentVer as $v): $s = json_decode((string)$v['snapshot'], true); ?>
        <tr>
          <td class="first"><b><?= e($s['employee']['name'] ?? '—') ?></b></td>
          <td data-l="<?= e(t('Code')) ?>"><span class="codepill"><?= e($v['code_queried']) ?></span></td>
          <td data-l="<?= e(t('Result')) ?>"><?php partial('result_badge', ['r' => $v['result']]); ?></td>
          <td data-l="<?= e(t('Checked by')) ?>"><?= e(verifier_types()[$v['verifier_type'] ?? ''] ?? ($v['channel'] === 'api' ? 'API' : t('Not specified'))) ?></td>
          <td class="num" data-l="<?= e(t('When')) ?>"><?= e(time_ago($v['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty"><div class="ic-wrap"><?= icon('shield', 24) ?></div><h3><?= e(t('No verifications yet')) ?></h3><p><?= e(t('When a bank, embassy or landlord scans one of your payslips, it will show up here.')) ?></p></div>
  <?php endif; ?>
</div>
