<div class="page-head"><div><h1><?= e(t('Verification log')) ?></h1><p><?= e(t('Every time someone checks one of your payslips. Useful for spotting suspicious activity.')) ?></p></div></div>
<div class="card">
<?php if ($rows): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Month')) ?></th><th><?= e(t('Code')) ?></th><th><?= e(t('Result')) ?></th><th><?= e(t('Checked by')) ?></th><th><?= e(t('Channel')) ?></th><th class="num"><?= e(t('When')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $v): $s = json_decode((string)$v['snapshot'], true); ?>
      <tr>
        <td class="first"><b><?= e($s['employee']['name'] ?? '—') ?></b></td>
        <td data-l="<?= e(t('Month')) ?>"><?= $v['period'] ? e(period_label($v['period'])) : '—' ?></td>
        <td data-l="<?= e(t('Code')) ?>"><span class="codepill"><?= e($v['code_queried']) ?></span></td>
        <td data-l="<?= e(t('Result')) ?>"><?php partial('result_badge', ['r' => $v['result']]); ?></td>
        <td data-l="<?= e(t('Checked by')) ?>"><?= e(verifier_types()[$v['verifier_type'] ?? ''] ?? t('Not specified')) ?></td>
        <td data-l="<?= e(t('Channel')) ?>"><?= e($v['channel'] === 'api' ? 'API' : t('Web / QR')) ?><?php if (!empty($v['country'])): ?> <span class="badge plain"><?= e($v['country']) ?></span><?php endif; ?></td>
        <td class="num" data-l="<?= e(t('When')) ?>" title="<?= e($v['created_at']) ?>"><?= e(date_fmt($v['created_at'], null, true)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty"><div class="ic-wrap"><?= icon('shield', 24) ?></div><h3><?= e(t('No verifications yet')) ?></h3><p><?= e(t('When a bank, embassy or landlord scans one of your payslips, it will show up here.')) ?></p></div>
<?php endif; ?>
</div>
