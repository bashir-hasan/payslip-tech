<div class="page-head">
  <div><h1><?= e(t('My documents')) ?></h1><p><?= e(t('Employment letters, salary certificates and income statements with a QR code that banks and embassies can check.')) ?></p></div>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-bottom:18px">
  <form class="card" method="post" action="<?= e(url('/me/documents')) ?>"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="type" value="income_statement">
    <b><?= icon('zap', 16) ?> <?= e(t('Annual income statement')) ?></b><p class="small muted"><?= e(t('Ready instantly. Built only from payslips your employer already issued.')) ?></p>
    <?php if ($years): ?>
      <div class="field"><select class="input" name="year"><?php foreach ($years as $y): ?><option value="<?= e($y) ?>"><?= e(num($y)) ?></option><?php endforeach; ?></select></div>
      <div class="field"><input class="input" name="addressed_to" maxlength="200" placeholder="<?= e(t('Addressed to (optional)')) ?>"></div>
      <button class="btn btn-primary btn-sm"><?= e(t('Create now')) ?></button>
    <?php else: ?><p class="small"><?= e(t('Available after your first payslip.')) ?></p><?php endif; ?>
  </div></form>
  <?php foreach (['employment_letter' => t('Confirms your job, position and joining date. No salary shown.'), 'salary_certificate' => t('Shows your current monthly salary breakdown. Often asked for visas and loans.')] as $type => $desc): ?>
  <form class="card" method="post" action="<?= e(url('/me/documents')) ?>"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
    <b><?= icon('award', 16) ?> <?= e(doc_types()[$type]) ?></b><p class="small muted"><?= e($desc) ?></p>
    <div class="field"><input class="input" name="addressed_to" maxlength="200" placeholder="<?= e(t('Addressed to, e.g. Embassy of Italy')) ?>"></div>
    <div class="field"><input class="input" name="purpose" maxlength="200" placeholder="<?= e(t('Purpose, e.g. Tourist visa')) ?>"></div>
    <button class="btn btn-light btn-sm"><?= icon('send', 15) ?> <?= e(t('Request from employer')) ?></button>
  </div></form>
  <?php endforeach; ?>
</div>
<div class="card">
<?php if ($docs): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Document')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Status')) ?></th><th class="num"><?= e(t('Checks')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($docs as $d): ?>
      <tr>
        <td class="first"><b><?= e(doc_types()[$d['type']] ?? $d['type']) ?><?= $d['year'] ? ' ' . e(num($d['year'])) : '' ?></b><div class="small muted"><?= e($d['addressed_to'] ?: '') ?></div></td>
        <td data-l="<?= e(t('Code')) ?>"><?= $d['code'] ? '<span class="codepill">' . e($d['code']) . '</span>' : '—' ?></td>
        <td data-l="<?= e(t('Status')) ?>"><?php if ($d['status'] === 'requested'): ?><span class="badge badge-info"><?= e(t('Requested')) ?></span><?php elseif ($d['status'] === 'declined'): ?><span class="badge"><?= e(t('Declined')) ?></span><?php else: partial('result_badge', ['r' => $d['status']]); endif; ?></td>
        <td class="num" data-l="<?= e(t('Checks')) ?>"><?= e(num($d['checks'])) ?></td>
        <td class="num"><?php if ($d['code']): ?><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap"><button class="btn btn-ghost btn-sm" data-copy="<?= e(verify_url($d['code'])) ?>" data-copied="✓"><?= icon('link', 15) ?> <?= e(t('Copy link')) ?></button><a class="btn btn-light btn-sm" href="<?= e(url('/document/' . $d['code'])) ?>"><?= icon('download', 15) ?> <?= e(t('View / PDF')) ?></a></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('award', 24) ?></div><h3><?= e(t('No documents yet')) ?></h3><p><?= e(t('Create or request one above.')) ?></p></div>
<?php endif; ?>
</div>
