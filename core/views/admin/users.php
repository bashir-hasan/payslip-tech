<?php $me = current_user(); $roles = ['admin' => t('Platform admin'), 'employer' => t('Employer'), 'employee' => t('Employee')]; ?>
<div class="page-head"><div><h1><?= e(t('Users')) ?></h1><p><?= e(t('Everyone who can sign in.')) ?></p></div></div>
<div class="card">
  <div class="toolbar"><form class="search" method="get"><?= icon('search', 16) ?><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Search name or email…')) ?>"></form></div>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('User')) ?></th><th><?= e(t('Role')) ?></th><th><?= e(t('Company')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('Last sign-in')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td class="first"><div class="who"><span class="avatar"><?= e(initials($u['name'])) ?></span><span><b><?= e($u['name']) ?></b><span><?= e($u['email']) ?></span></span></div></td>
        <td data-l="<?= e(t('Role')) ?>"><?= e($roles[$u['role']] ?? $u['role']) ?></td>
        <td data-l="<?= e(t('Company')) ?>"><?= e($u['company'] ?? '—') ?></td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $u['status']]); ?></td>
        <td data-l="<?= e(t('Last sign-in')) ?>"><?= e(time_ago($u['last_login_at'])) ?></td>
        <td class="num"><?php if ((int)$u['id'] !== (int)$me['id'] && $u['status'] !== 'invited'): ?><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/status')) ?>"><?= csrf_field() ?><button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-light' ?>"><?= e($u['status'] === 'active' ? t('Suspend') : t('Reactivate')) ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
