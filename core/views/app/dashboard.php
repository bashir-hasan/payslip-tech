<?php
$doneCount = count(array_filter($checklist, fn($i) => $i['done']));
$maxNet = max(1, ...array_map(fn($r) => (float)$r['total_net'], $runs ?: [['total_net' => 1]]));
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

<div class="grid grid-4" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('wallet', 16) ?> <?= e(t('Last payroll')) ?></div><div class="v"><?= $lastRun ? e(money($lastRun['total_net'])) : '—' ?></div><div class="d"><?= $lastRun ? e(period_label($lastRun['period'])) . ' · ' . e($lastRun['status'] === 'finalized' ? t('Finalised') : t('Draft')) : e(t('No payroll yet')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Active employees')) ?></div><div class="v"><?= e(num($stats['employees'])) ?></div><div class="d"><a href="<?= e(url('/app/employees')) ?>"><?= e(t('Manage team')) ?> →</a></div></div>
  <div class="stat"><div class="k"><?= icon('file', 16) ?> <?= e(t('Payslips issued')) ?></div><div class="v"><?= e(num($stats['issued'])) ?></div><div class="d"><?= e(t('All with QR verification')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Verifications')) ?></div><div class="v"><?= e(num($stats['verifications'])) ?></div><div class="d"><?= e(sprintf(t('%s in the last 30 days'), num($stats['verifications30']))) ?></div></div>
</div>

<div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px" data-dash>
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
    <div class="card-head"><h3><?= e(t('Getting started')) ?></h3><span class="badge badge-brand plain"><?= e(num($doneCount)) ?>/<?= e(num(count($checklist))) ?></span></div>
    <div class="card-body">
      <div class="progress" style="margin-bottom:14px"><i style="width:<?= round($doneCount / count($checklist) * 100) ?>%"></i></div>
      <div class="checklist">
        <?php foreach ($checklist as $i): ?>
          <a href="<?= e(url($i['url'])) ?>" class="<?= $i['done'] ? 'done' : '' ?>"><span class="tick"><?= $i['done'] ? icon('check', 13) : '' ?></span><?= e($i['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
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
<style>@media (max-width:960px){[data-dash]{grid-template-columns:1fr!important}}</style>
