<?php $cur = snapshot_currency($p['snapshot']); currency_context($cur); $backUrl = current_user()['role'] === 'employee' ? '/me' : '/app/payslips'; ?>
<div class="page-head"><div><a class="small" href="<?= e(url($backUrl)) ?>">← <?= e(t('Back')) ?></a><h1 style="margin-top:6px"><?= e(t('Explain my payslip')) ?></h1><p><?= e(period_label($p['period'])) ?> · <span class="codepill"><?= e($p['code']) ?></span></p></div>
  <a class="btn btn-light" href="<?= e(url('/payslip/' . $p['code'])) ?>"><?= icon('file') ?> <?= e(t('View / PDF')) ?></a>
</div>
<div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:18px;align-items:start" data-ex>
  <div class="card"><div class="card-body">
    <p style="font-size:17px;margin-top:0"><?= e($exp['summary']) ?></p>
    <?php if ($exp['points']): ?><ul class="explain-list"><?php foreach ($exp['points'] as $pt): ?><li><?= icon('check', 16) ?><span><?= e($pt) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if (!$prev): ?><p class="small muted" style="margin-bottom:0"><?= e(t('This is your first payslip here, so there is no previous month to compare with.')) ?></p><?php endif; ?>
  </div></div>
  <div class="card"><div class="card-head"><h3><?= e(t('Ask about this payslip')) ?></h3></div><div class="card-body">
    <?php if ($answer !== null): ?><div class="msg me"><div class="bubble"><?= e($q) ?></div></div><div class="msg bot"><div class="bubble"><?= e($answer) ?></div></div><?php endif; ?>
    <form method="get"><div class="row" style="flex-wrap:nowrap"><input class="input" name="q" maxlength="300" placeholder="<?= e(t('e.g. Why is my salary lower this month?')) ?>" required><button class="btn btn-primary btn-icon"><?= icon('send') ?></button></div></form>
    <div class="chips" style="margin-top:12px"><?php foreach ([t('Why is my pay different from last month?'), t('What are my deductions?'), t('When was I paid?'), t('How do I prove my income?'), t('Something looks wrong')] as $qq): ?><a class="chip-btn" href="?q=<?= e(rawurlencode($qq)) ?>"><?= e($qq) ?></a><?php endforeach; ?></div>
  </div></div>
</div>
<style>@media (max-width:960px){[data-ex]{grid-template-columns:1fr!important}}</style>
