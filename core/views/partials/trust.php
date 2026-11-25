<?php
/** Employer trust panel shown to verifiers. Expects $trust from company_trust(). */
if (empty($trust) || empty($trust['since'])) return;
$ck = $trust['checks'];
$items = [
    ['ok' => $ck['business'], 'yes' => t('Business checked by PaySlip.tech'), 'no' => t('Business documents not checked yet')],
    ['ok' => $ck['domain'], 'yes' => sprintf(t('Owns the domain %s'), $trust['domain'] ?? ''), 'no' => t('Company domain not verified')],
    ['ok' => $ck['history'], 'yes' => sprintf(t('%s months of payroll issued since %s'), num($trust['runs']), $trust['first_period'] ? period_label($trust['first_period']) : '—'), 'no' => $trust['runs'] === 1 ? t('Only 1 month of payroll history') : sprintf(t('Only %s months of payroll history'), num($trust['runs']))],
];
$lvl = $trust['level'];
?>
<div class="trust" style="margin-top:16px;border:1px solid var(--line);border-radius:12px;padding:14px 16px">
  <div class="row" style="justify-content:space-between;gap:8px;flex-wrap:wrap">
    <b style="display:inline-flex;align-items:center;gap:8px"><?= icon('building', 16) ?> <?= e(t('About this employer')) ?></b>
    <span class="badge <?= $lvl >= 2 ? 'badge-ok' : ($lvl === 1 ? 'badge-info' : 'badge-warn') ?>"><?= e([t('New employer'), t('Basic trust'), t('Trusted employer'), t('Fully verified employer')][$lvl]) ?></span>
  </div>
  <ul style="list-style:none;padding:0;margin:10px 0 0;display:grid;gap:6px;font-size:14px">
    <?php foreach ($items as $i): ?><li class="row" style="gap:8px;align-items:flex-start"><span style="color:<?= $i['ok'] ? 'var(--brand)' : 'var(--muted)' ?>"><?= icon($i['ok'] ? 'check-circle' : 'alert', 16) ?></span><span class="<?= $i['ok'] ? '' : 'muted' ?>"><?= e($i['ok'] ? $i['yes'] : $i['no']) ?></span></li><?php endforeach; ?>
    <li class="row small muted" style="gap:8px"><?= icon('clock', 16) ?> <?= e(sprintf(t('On PaySlip.tech since %s · %s employees paid in the last 90 days'), date_fmt($trust['since']), num($trust['staff']))) ?></li>
  </ul>
  <?php if ($lvl === 0): ?><p class="small" style="margin:10px 0 0"><?= e(t('This employer is new and unverified. For large loans or visas, also confirm the job directly with the company.')) ?></p><?php endif; ?>
</div>
