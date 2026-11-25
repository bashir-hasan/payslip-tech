<?php $inv = $_SESSION['_invite_link'] ?? null; unset($_SESSION['_invite_link']); ?>
<div class="page-head">
  <div><h1><?= e(t('Employees')) ?></h1><p><?= e(t('Your team and their salary structure. Active employees are included in each payroll run.')) ?></p></div>
  <div class="row">
    <a class="btn btn-light" href="<?= e(url('/app/employees/import')) ?>"><?= icon('upload') ?> <?= e(t('Smart import')) ?></a>
    <a class="btn btn-primary" href="<?= e(url('/app/employees/new')) ?>"><?= icon('plus') ?> <?= e(t('Add employee')) ?></a>
  </div>
</div>

<?php if ($inv): ?>
  <div class="alert alert-<?= $inv['sent'] ? 'success' : 'info' ?>" style="display:block">
    <b><?= e(sprintf(t('Invitation for %s'), $inv['name'])) ?></b>
    <p style="margin:4px 0 10px"><?= e($inv['sent'] ? t('We emailed the invitation. You can also share this link directly (valid 7 days):') : t('Email could not be sent from this server. Share this link with the employee by WhatsApp or SMS (valid 7 days):')) ?></p>
    <div class="copy-row"><input class="input" value="<?= e($inv['link']) ?>" readonly data-select><button type="button" class="btn btn-dark" data-copy="<?= e($inv['link']) ?>" data-copied="✓ <?= e(t('Copied')) ?>"><?= icon('copy') ?> <?= e(t('Copy')) ?></button></div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="toolbar">
    <form class="search" method="get"><?= icon('search', 16) ?><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Search name, ID, designation…')) ?>"><input type="hidden" name="status" value="<?= e($status) ?>"></form>
    <div class="tabs">
      <a href="<?= e(url('/app/employees?status=active')) ?>" class="<?= $status === 'active' ? 'on' : '' ?>"><?= e(t('Active')) ?> (<?= e(num((int)($counts['active'] ?? 0))) ?>)</a>
      <a href="<?= e(url('/app/employees?status=inactive')) ?>" class="<?= $status === 'inactive' ? 'on' : '' ?>"><?= e(t('Inactive')) ?> (<?= e(num((int)($counts['inactive'] ?? 0))) ?>)</a>
      <a href="<?= e(url('/app/employees?status=all')) ?>" class="<?= $status === 'all' ? 'on' : '' ?>"><?= e(t('All')) ?></a>
    </div>
  </div>
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Employee')) ?></th><th><?= e(t('Department')) ?></th><th><?= e(t('Payment')) ?></th><th class="num"><?= e(t('Gross / month')) ?></th><th><?= e(t('Portal')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $gross = $r['basic'] + $r['house_rent'] + $r['medical'] + $r['conveyance'] + $r['other_allowance']; ?>
      <tr style="<?= $r['status'] === 'inactive' ? 'opacity:.6' : '' ?>">
        <td class="first"><a class="who" href="<?= e(url('/app/employees/' . $r['id'])) ?>" style="color:inherit"><span class="avatar"><?= e(initials($r['name'])) ?></span><span><b><?= e($r['name']) ?></b><span><?= e($r['emp_code']) ?> · <?= e($r['designation'] ?: '—') ?></span></span></a></td>
        <td data-l="<?= e(t('Department')) ?>"><?= e($r['department'] ?: '—') ?></td>
        <td data-l="<?= e(t('Payment')) ?>"><?= e(payment_method_label($r['payment_method'])) ?><?php if ($r['account_no']): ?> <span class="muted small mono"><?= e(mask_account($r['account_no'])) ?></span><?php endif; ?></td>
        <td class="num" data-l="<?= e(t('Gross / month')) ?>"><b><?= e(money($gross)) ?></b></td>
        <td data-l="<?= e(t('Portal')) ?>"><?php if ($r['portal_status']): partial('result_badge', ['r' => $r['portal_status']]); else: ?><span class="muted small"><?= e(t('Not invited')) ?></span><?php endif; ?></td>
        <td class="num">
          <div class="row" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap">
            <?php if ($r['status'] === 'active' && $r['email'] && $r['portal_status'] !== 'active'): ?>
              <form method="post" action="<?= e(url('/app/employees/' . $r['id'] . '/invite')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm" title="<?= e(t('Invite to employee portal')) ?>"><?= icon('send', 15) ?> <?= e(t('Invite')) ?></button></form>
            <?php endif; ?>
            <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/app/employees/' . $r['id'])) ?>" title="<?= e(t('Edit')) ?>"><?= icon('edit', 16) ?></a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <div class="empty"><div class="ic-wrap"><?= icon('users', 24) ?></div>
      <h3><?= e($q !== '' ? t('No matching employees') : t('Add your first employee')) ?></h3>
      <p><?= e($q !== '' ? t('Try a different search.') : t('Enter each person once with their salary structure. You can also import a CSV file.')) ?></p>
      <?php if ($q === ''): ?><a class="btn btn-primary" href="<?= e(url('/app/employees/new')) ?>"><?= icon('plus') ?> <?= e(t('Add employee')) ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
