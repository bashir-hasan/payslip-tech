<?php $limit = employee_limit($c); ?>
<div class="page-head"><div><h1><?= e(t('Plan & billing')) ?></h1><p><?= e(t('Payments are handled securely by Polar, our merchant of record. Taxes and VAT are calculated at checkout.')) ?></p></div>
  <?php if ($c['polar_customer_id']): ?><form method="post" action="<?= e(url('/app/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-light"><?= icon('settings') ?> <?= e(t('Manage subscription & invoices')) ?></button></form><?php endif; ?>
</div>

<div class="grid grid-3" style="margin-bottom:18px">
  <div class="stat dark"><div class="k"><?= icon('zap', 16) ?> <?= e(t('Current plan')) ?></div><div class="v"><?= e($current['name']) ?></div><div class="d"><?php if ($current['key'] !== 'free' && $c['plan_renews_at']): ?><?= e(sprintf($c['plan_cancel_at_end'] ? t('Ends on %s') : t('Renews on %s'), date_fmt($c['plan_renews_at']))) ?><?php else: ?><?= e(t('No payment needed')) ?><?php endif; ?></div></div>
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Active employees')) ?></div><div class="v"><?= e(num($active)) ?><?= $limit ? ' <span class="muted" style="font-size:18px">/ ' . e(num($limit)) . '</span>' : '' ?></div><div class="d"><?php if ($limit): ?><div class="progress" style="margin-top:6px"><i style="width:<?= min(100, round($active / $limit * 100)) ?>%"></i></div><?php else: ?><?= e(t('No limit')) ?><?php endif; ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Verification')) ?></div><div class="v"><?= e(t('Free')) ?></div><div class="d"><?= e(t('Always free for anyone checking your payslips')) ?></div></div>
</div>

<?php if (!$enabled): ?>
  <div class="alert alert-info"><?= icon('alert') ?><div><?= e(t('Paid plans are not open yet. Everything is free for now.')) ?></div></div>
<?php endif; ?>

<div class="pricing">
  <?php foreach ($plans as $k => $p): $isCur = $current['key'] === $k; ?>
    <div class="plan <?= $p['badge'] ? 'featured' : '' ?>"><?php if ($p['badge']): ?><span class="tag"><?= e($p['badge']) ?></span><?php endif; ?>
      <h3><?= e($p['name']) ?></h3>
      <div class="price">$<?= e(rtrim(rtrim(number_format((float)$p['price'], 2), '0'), '.')) ?> <small><?= e(t('/ month')) ?></small></div>
      <ul><?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?><li><?= icon('check', 16) ?><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php if ($isCur): ?>
        <button class="btn btn-light btn-block" disabled><?= icon('check') ?> <?= e(t('Current plan')) ?></button>
      <?php elseif ($k === 'free'): ?>
        <?php if ($c['polar_customer_id']): ?><form method="post" action="<?= e(url('/app/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-block"><?= e(t('Downgrade in billing portal')) ?></button></form><?php else: ?><button class="btn btn-light btn-block" disabled><?= e(t('Free')) ?></button><?php endif; ?>
      <?php elseif ($enabled && $p['product_id']): ?>
        <?php if ($c['polar_customer_id'] && $current['key'] !== 'free'): ?>
          <form method="post" action="<?= e(url('/app/billing/portal')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-block"><?= e(sprintf(t('Switch to %s'), $p['name'])) ?></button></form>
        <?php else: ?>
          <form method="post" action="<?= e(url('/app/billing/checkout/' . $k)) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-block"><?= icon('zap') ?> <?= e(sprintf(t('Upgrade to %s'), $p['name'])) ?></button></form>
        <?php endif; ?>
      <?php else: ?>
        <button class="btn btn-light btn-block" disabled><?= e(t('Coming soon')) ?></button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<p class="small muted center" style="margin-top:16px"><?= e(t('Prices in US dollars. Cancel anytime; your plan stays active until the end of the paid period.')) ?></p>
