<?php
$maxD = max(1, ...array_map(fn($d) => (int)$d['n'], $daily ?: [['n' => 1]]));
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach ($daily as $d) $days[$d['d']] = (int)$d['n'];
?>
<div class="page-head"><div><h1><?= e(t('Platform overview')) ?></h1><p><?= e(t('Real usage across all companies. The demo company is excluded from these numbers.')) ?></p></div></div>
<div class="grid grid-4" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('building', 16) ?> <?= e(t('Companies')) ?></div><div class="v"><?= e(num($stats['companies'])) ?></div><div class="d"><?= e(sprintf(t('+%s in 30 days'), num($stats['companies30']))) ?></div></div>
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Active employees')) ?></div><div class="v"><?= e(num($stats['employees'])) ?></div><div class="d"><?= e(sprintf(t('%s user accounts'), num($stats['users']))) ?></div></div>
  <div class="stat"><div class="k"><?= icon('file', 16) ?> <?= e(t('Payslips issued')) ?></div><div class="v"><?= e(num($stats['payslips'])) ?></div><div class="d"><?= e(sprintf(t('%s countries · %s paying companies'), num($stats['countries']), num($stats['paid']))) ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Verifications')) ?></div><div class="v"><?= e(num($stats['verifications'])) ?></div><div class="d"><?= e(sprintf(t('%s in 30 days · %s via API'), num($stats['verifications30']), num($stats['api_calls']))) ?></div></div>
</div>
<div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px" data-ad>
  <div class="card"><div class="card-head"><h3><?= e(t('Verifications, last 14 days')) ?></h3></div><div class="card-body">
    <div class="bars"><?php foreach ($days as $d => $n): ?><div class="bar" title="<?= e($d . ': ' . $n) ?>"><em><?= $n ? e(num($n)) : '' ?></em><i style="height:<?= max(2, round($n / $maxD * 100)) ?>%"></i><span><?= e(date('d', strtotime($d))) ?></span></div><?php endforeach; ?></div>
  </div></div>
  <div class="card"><div class="card-head"><h3><?= e(t('Results')) ?></h3></div><div class="card-body stack">
    <?php foreach (['valid' => t('Genuine'), 'revoked' => t('Revoked'), 'not_found' => t('Not found'), 'tampered' => t('Tampered')] as $k => $lbl): $n = (int)($results[$k] ?? 0); $pct = $stats['verifications'] ? round($n / $stats['verifications'] * 100) : 0; ?>
      <div><div class="row between small"><span><?= e($lbl) ?></span><b><?= e(num($n)) ?></b></div><div class="progress"><i style="width:<?= $pct ?>%;background:<?= $k === 'valid' ? 'var(--ok)' : ($k === 'revoked' ? 'var(--warn)' : 'var(--bad)') ?>"></i></div></div>
    <?php endforeach; ?>
  </div></div>
</div>
<div class="grid grid-2" style="margin-top:18px;align-items:start">
  <div class="card"><div class="card-head"><h3><?= e(t('Newest companies')) ?></h3><a class="small" href="<?= e(url('/admin/companies')) ?>"><?= e(t('All')) ?> →</a></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($companies as $c): ?><tr><td><b><?= e($c['name']) ?></b><?php if ((int)$c['id'] === demo_cid()): ?> <span class="badge badge-info plain"><?= e(t('Demo')) ?></span><?php endif; ?><div class="small muted"><?= e(time_ago($c['created_at'])) ?></div></td><td class="num"><?= e(num($c['emps'])) ?> <?= e(t('employees')) ?></td><td class="num"><?php partial('result_badge', ['r' => $c['status']]); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card"><div class="card-head"><h3><?= e(t('Recent activity')) ?></h3></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($activity as $a): ?><tr><td><b><?= e($a['company'] ?? '—') ?></b><div class="small muted"><?= e($a['action']) ?></div></td><td class="num small muted"><?= e(time_ago($a['created_at'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$activity): ?><tr><td class="muted"><?= e(t('No activity yet.')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<style>@media (max-width:960px){[data-ad]{grid-template-columns:1fr!important}}</style>
