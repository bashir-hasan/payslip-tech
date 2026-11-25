<div class="page-head">
  <div><h1><?= e(t('Proof of income')) ?></h1><p><?= e(t('Send one link instead of a pile of PDFs. The bank or embassy sees your payslips checked live, and the link stops working when you say.')) ?></p></div>
</div>
<?php if ($new): ?>
  <div class="alert alert-success"><?= icon('check') ?><div><b><?= e(t('Your link is ready')) ?></b>
    <div class="row" style="gap:8px;margin-top:6px;flex-wrap:wrap"><span class="mono small" style="word-break:break-all"><?= e($new) ?></span><button class="btn btn-light btn-sm" data-copy="<?= e($new) ?>" data-copied="✓ <?= e(t('Copied')) ?>"><?= icon('copy', 15) ?> <?= e(t('Copy')) ?></button><button class="btn btn-light btn-sm" data-share="<?= e($new) ?>"><?= icon('send', 15) ?> <?= e(t('Share')) ?></button></div>
  </div></div>
<?php endif; ?>

<?php if ($slips || $docs): ?>
<form class="card" method="post" action="<?= e(url('/me/proof')) ?>" style="margin-bottom:18px"><div class="card-body">
  <?= csrf_field() ?>
  <div class="fieldset-title"><?= e(t('Create a proof pack')) ?></div>
  <div class="form-grid">
    <div class="field"><label class="label"><?= e(t('Who is it for?')) ?></label><input class="input" name="label" maxlength="120" placeholder="<?= e(t('e.g. City Bank home loan')) ?>"></div>
    <div class="field"><label class="label"><?= e(t('Payslips')) ?></label><select class="input" name="months"><?php foreach ([3, 6, 12] as $n): if ($n > count($slips) && $n !== 3) continue; ?><option value="<?= $n ?>" <?= $n === 6 ? 'selected' : '' ?>><?= e(sprintf(t('Last %s months'), num($n))) ?></option><?php endforeach; ?><option value="0"><?= e(t('None')) ?></option></select></div>
    <div class="field"><label class="label"><?= e(t('Link works for')) ?></label><select class="input" name="days"><option value="7"><?= e(t('7 days')) ?></option><option value="30" selected><?= e(t('30 days')) ?></option><option value="90"><?= e(t('90 days')) ?></option></select></div>
  </div>
  <div class="field"><label class="label"><?= e(t('What can they see?')) ?></label>
    <label class="row" style="gap:8px;margin:4px 0"><input type="radio" name="mode" value="full" checked> <?= e(t('Full amounts (gross, deductions and net pay)')) ?></label>
    <label class="row" style="gap:8px;margin:4px 0;flex-wrap:wrap"><input type="radio" name="mode" value="threshold"> <?= e(t('Only confirm that my net pay was at least')) ?> <input class="input" name="threshold" inputmode="decimal" style="width:140px" placeholder="50000"> <?= e(t('every month')) ?></label>
    <label class="row" style="gap:8px;margin:4px 0"><input type="radio" name="mode" value="hidden"> <?= e(t('No amounts, just that the payslips are genuine')) ?></label>
  </div>
  <?php if ($docs): ?>
  <div class="field"><label class="label"><?= e(t('Also include')) ?></label>
    <?php foreach ($docs as $d): ?><label class="row" style="gap:8px;margin:4px 0"><input type="checkbox" name="documents[]" value="<?= e((string)$d['id']) ?>"> <?= e(doc_types()[$d['type']] ?? $d['type']) ?> <span class="small muted mono"><?= e($d['code']) ?></span></label><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <button class="btn btn-primary"><?= icon('link') ?> <?= e(t('Create link')) ?></button>
</div></form>
<?php endif; ?>

<div class="card">
<?php if ($shares): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Link')) ?></th><th><?= e(t('Includes')) ?></th><th><?= e(t('Opened')) ?></th><th><?= e(t('Status')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($shares as $s): $it = $s['items']; ?>
      <tr>
        <td class="first"><b><?= e($s['label'] ?: t('Proof pack')) ?></b><div class="small muted"><?= e(sprintf(t('Created %s · until %s'), date_fmt($s['created_at']), date_fmt($s['expires_at']))) ?></div></td>
        <td data-l="<?= e(t('Includes')) ?>" class="small"><?= e(sprintf(t('%s payslips, %s documents'), num(count($it['payslips'] ?? [])), num(count($it['documents'] ?? [])))) ?><div class="muted"><?= e(['full' => t('Full amounts'), 'threshold' => sprintf(t('At least %s'), money($it['threshold'] ?? 0)), 'hidden' => t('No amounts')][$it['mode'] ?? 'full'] ?? '') ?></div></td>
        <td data-l="<?= e(t('Opened')) ?>" class="small"><?= e(sprintf(t('%s times'), num($s['views']))) ?><?php if ($s['last_viewed_at']): ?><div class="muted"><?= e(date_fmt($s['last_viewed_at'], null, true)) ?></div><?php endif; ?></td>
        <td data-l="<?= e(t('Status')) ?>"><span class="badge <?= $s['state'] === 'active' ? 'badge-ok' : '' ?>"><?= e(['active' => t('Active'), 'expired' => t('Expired'), 'revoked' => t('Switched off')][$s['state']]) ?></span></td>
        <td class="num"><?php if ($s['state'] === 'active'): ?><div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
          <button class="btn btn-ghost btn-sm" data-copy="<?= e($s['url']) ?>" data-copied="✓"><?= icon('copy', 15) ?> <?= e(t('Copy link')) ?></button>
          <form method="post" action="<?= e(url('/me/proof/' . $s['id'] . '/revoke')) ?>" data-confirm="<?= e(t('Switch off this link? Anyone who has it will no longer be able to open it.')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm"><?= e(t('Switch off')) ?></button></form>
        </div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('send', 24) ?></div><h3><?= e(t('No links yet')) ?></h3><p><?= e($slips ? t('Create your first proof pack above.') : t('You can create a proof pack after your employer issues your first payslip.')) ?></p></div>
<?php endif; ?>
</div>
