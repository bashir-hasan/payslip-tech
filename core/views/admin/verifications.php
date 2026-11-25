<div class="page-head"><div><h1><?= e(t('All verifications')) ?></h1><p><?= e(t('The latest 500 checks across the platform.')) ?></p></div></div>
<div class="card">
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Code')) ?></th><th><?= e(t('Result')) ?></th><th><?= e(t('Company')) ?></th><th><?= e(t('Employee')) ?></th><th><?= e(t('Checked by')) ?></th><th><?= e(t('Channel')) ?></th><th><?= e(t('IP')) ?></th><th class="num"><?= e(t('When')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $v): $s = json_decode((string)$v['snapshot'], true); ?>
      <tr>
        <td class="first"><span class="codepill"><?= e($v['code_queried']) ?></span></td>
        <td data-l="<?= e(t('Result')) ?>"><?php partial('result_badge', ['r' => $v['result']]); ?></td>
        <td data-l="<?= e(t('Company')) ?>"><?= e($v['company'] ?? '—') ?></td>
        <td data-l="<?= e(t('Employee')) ?>"><?= e($s['employee']['name'] ?? '—') ?></td>
        <td data-l="<?= e(t('Checked by')) ?>"><?= e(verifier_types()[$v['verifier_type'] ?? ''] ?? '—') ?></td>
        <td data-l="<?= e(t('Channel')) ?>"><?= e($v['channel'] === 'api' ? 'API' : 'Web') ?><?php if (!empty($v['country'])): ?> <span class="badge plain"><?= e($v['country']) ?></span><?php endif; ?></td>
        <td data-l="<?= e(t('IP')) ?>" class="mono small"><?= e($v['ip']) ?></td>
        <td class="num" data-l="<?= e(t('When')) ?>"><?= e(date_fmt($v['created_at'], null, true)) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="center muted"><?= e(t('No verifications yet.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
