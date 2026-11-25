<div class="page-head"><div><h1><?= e(t('Companies')) ?></h1><p><?= e(t('Suspending a company blocks its users from signing in. Its issued payslips still verify.')) ?></p></div></div>
<div class="card">
  <div class="toolbar"><form class="search" method="get"><?= icon('search', 16) ?><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Search companies…')) ?>"></form></div>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Company')) ?></th><th><?= e(t('Owner')) ?></th><th class="num"><?= e(t('Employees')) ?></th><th class="num"><?= e(t('Payslips')) ?></th><th class="num"><?= e(t('Checks')) ?></th><th><?= e(t('Country')) ?></th><th><?= e(t('Plan')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('Joined')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr>
        <td class="first"><b><?= e($c['name']) ?></b><?php if ((int)$c['id'] === demo_cid()): ?> <span class="badge badge-info plain"><?= e(t('Demo')) ?></span><?php endif; ?></td>
        <td data-l="<?= e(t('Owner')) ?>"><?= e($c['owner_email'] ?? '—') ?></td>
        <td class="num" data-l="<?= e(t('Employees')) ?>"><?= e(num($c['emps'])) ?></td>
        <td class="num" data-l="<?= e(t('Payslips')) ?>"><?= e(num($c['slips'])) ?></td>
        <td class="num" data-l="<?= e(t('Checks')) ?>"><?= e(num($c['checks'])) ?></td>
        <td data-l="<?= e(t('Country')) ?>"><?= e(($c['country'] ?? '') . ' · ' . ($c['currency'] ?? '')) ?></td>
        <td data-l="<?= e(t('Plan')) ?>"><form method="post" action="<?= e(url('/admin/companies/' . $c['id'] . '/plan')) ?>"><?= csrf_field() ?><select class="select" name="plan" data-autosubmit style="height:34px;width:auto;font-size:13px"><?php $cp = company_plan($c)['key']; foreach (plans() as $pk => $pp): ?><option value="<?= e($pk) ?>" <?= $cp === $pk ? 'selected' : '' ?>><?= e($pp['name']) ?></option><?php endforeach; ?></select></form></td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $c['status']]); ?></td>
        <td data-l="<?= e(t('Joined')) ?>"><?= e(date_fmt($c['created_at'])) ?></td>
        <td class="num"><form method="post" action="<?= e(url('/admin/companies/' . $c['id'] . '/status')) ?>" data-confirm="<?= e($c['status'] === 'active' ? t('Suspend this company?') : t('Reactivate this company?')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $c['status'] === 'active' ? 'btn-danger' : 'btn-light' ?>"><?= e($c['status'] === 'active' ? t('Suspend') : t('Reactivate')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="muted center"><?= e(t('No companies found.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
