<?php if ($state !== 'active'): ?>
<div class="verify-page"><div class="result invalid"><div class="result-head"><div class="big-ic"><?= icon('lock', 30) ?></div><div>
  <h1><?= e($state === 'missing' ? t('Link not found') : ($state === 'expired' ? t('This link has expired') : t('This link was switched off'))) ?></h1>
  <p><?= e(t('Ask the person who sent it for a new proof of income link.')) ?></p>
</div></div></div></div>
<?php return; endif;
$mode = $s['items']['mode'] ?? 'full';
$thr = (float)($s['items']['threshold'] ?? 0);
$valid = array_values(array_filter($items, fn($i) => $i['result'] === 'valid'));
$nets = array_map(fn($i) => (float)$i['payslip']['net'], $valid);
$bad = count($items) - count($valid);
$meets = $valid && min($nets) >= $thr;
?>
<div class="verify-page" style="max-width:860px">
  <div class="result <?= $bad ? 'revoked' : 'valid' ?>">
    <div class="result-head"><div class="big-ic"><?= icon($bad ? 'alert' : 'check', 30) ?></div><div>
      <h1><?= e(t('Proof of income')) ?> — <?= e($e['name']) ?></h1>
      <p><?= e($bad ? sprintf(t('%s of %s items could not be confirmed. See below.'), num($bad), num(count($items))) : sprintf(t('All %s payslips were checked just now and are genuine.'), num(count($items)))) ?></p>
    </div></div>
    <dl class="kv" style="margin:0">
      <div><dt><?= e(t('Employer')) ?></dt><dd><?= e($e['company_name']) ?></dd></div>
      <div><dt><?= e(t('Employee ID')) ?></dt><dd><?= e($e['emp_code']) ?></dd></div>
      <div><dt><?= e(t('Designation')) ?></dt><dd><?= e($e['designation'] ?: '—') ?></dd></div>
      <div><dt><?= e(t('Employment status')) ?></dt><dd><?= e($e['status'] === 'active' ? t('Currently employed') : t('No longer employed')) ?></dd></div>
      <?php if ($valid && $mode === 'full'): ?>
        <div><dt><?= e(t('Average net pay')) ?></dt><dd><b><?= e(money(array_sum($nets) / count($nets))) ?></b> / <?= e(t('month')) ?></dd></div>
        <div><dt><?= e(t('Lowest month')) ?></dt><dd><?= e(money(min($nets))) ?></dd></div>
      <?php elseif ($valid && $mode === 'threshold'): ?>
        <div class="full"><dt><?= e(sprintf(t('Net pay at least %s every month?'), money($thr))) ?></dt><dd><b style="color:<?= $meets ? 'var(--brand)' : '#b91c1c' ?>"><?= e($meets ? (count($valid) === 1 ? t('Yes, in the month shared') : sprintf(t('Yes, in all %s months'), num(count($valid)))) : t('No')) ?></b></dd></div>
      <?php endif; ?>
      <?php if ($s['label']): ?><div><dt><?= e(t('Prepared for')) ?></dt><dd><?= e($s['label']) ?></dd></div><?php endif; ?>
      <div><dt><?= e(t('Link valid until')) ?></dt><dd><?= e(date_fmt($s['expires_at'])) ?></dd></div>
    </dl>
    <?php partial('trust', ['trust' => $trust]); ?>
  </div>

  <?php if ($items): ?>
  <div class="card" style="margin-top:18px">
    <div class="table-wrap"><table class="table stack-m">
      <thead><tr><th><?= e(t('Month')) ?></th><th><?= e(t('Result')) ?></th><?php if ($mode === 'full'): ?><th class="num"><?= e(t('Gross')) ?></th><th class="num"><?= e(t('Net pay')) ?></th><?php endif; ?><th><?= e(t('Code')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): $p = $i['payslip'] ?? null; ?>
        <tr>
          <td class="first"><b><?= e($p ? period_label($p['period']) : '—') ?></b></td>
          <td data-l="<?= e(t('Result')) ?>"><?php partial('result_badge', ['r' => $i['result']]); ?></td>
          <?php if ($mode === 'full'): ?><td class="num" data-l="<?= e(t('Gross')) ?>"><?= $p ? e(money($p['gross'])) : '—' ?></td><td class="num" data-l="<?= e(t('Net pay')) ?>"><b><?= $p ? e(money($p['net'])) : '—' ?></b></td><?php endif; ?>
          <td data-l="<?= e(t('Code')) ?>"><a class="codepill" href="<?= e(url('/v/' . $i['code'])) ?>"><?= e($i['code']) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <?php if ($docs): ?>
  <div class="card" style="margin-top:18px"><div class="card-body">
    <div class="fieldset-title"><?= e(t('Documents')) ?></div>
    <?php foreach ($docs as $dres): $dd = $dres['document'] ?? null; ?>
      <div class="row" style="justify-content:space-between;padding:8px 0;border-top:1px solid var(--line);gap:8px;flex-wrap:wrap">
        <div><b><?= e($dd ? doc_types()[$dd['type']] ?? '' : t('Document')) ?></b> <span class="small muted"><?= e($dd ? date_fmt($dd['issued_at']) : '') ?></span></div>
        <div class="row" style="gap:8px"><?php partial('result_badge', ['r' => $dres['result']]); ?><a class="codepill" href="<?= e(url('/v/' . $dres['code'])) ?>"><?= e($dres['code']) ?></a></div>
      </div>
    <?php endforeach; ?>
  </div></div>
  <?php endif; ?>

  <p class="center small muted" style="margin-top:18px"><?= e(sprintf(t('%s chose to share this with you. Each item was checked against the employer\'s signed record when you opened this page (%s).'), $e['name'], date_fmt(now(), null, true))) ?></p>
</div>
