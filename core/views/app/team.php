<div class="page-head">
  <div><h1><?= e(t('Team')) ?></h1><p><?= e(t('Give HR and your accountant their own sign-in. HR prepares payroll, the accountant approves it. Nobody shares one password.')) ?></p></div>
  <?php if ($limit === null || $extra < $limit): ?><button class="btn btn-primary" data-dialog="invite"><?= icon('plus') ?> <?= e(t('Invite team member')) ?></button><?php endif; ?>
</div>

<?php if ($link): ?>
  <div class="alert alert-<?= $link['sent'] ? 'success' : 'info' ?>"><?= icon('mail') ?><div>
    <?= e($link['sent'] ? sprintf(t('Invitation emailed to %s.'), $link['name']) : sprintf(t('Email could not be sent. Send this link to %s yourself:'), $link['name'])) ?>
    <div class="row" style="gap:8px;margin-top:6px"><span class="mono small" style="word-break:break-all"><?= e($link['link']) ?></span><button class="btn btn-light btn-sm" data-copy="<?= e($link['link']) ?>" data-copied="✓"><?= icon('copy', 15) ?></button></div>
  </div></div>
<?php endif; ?>

<?php if ($limit !== null): ?>
  <div class="alert alert-info"><?= icon('users') ?><div>
    <?= e($limit === 0 ? t('Your plan includes only the owner. Team members are available on the Starter and Business plans.') : sprintf(t('%d of %d team member places used on your plan.'), $extra, $limit)) ?>
    <?php if ($limit === 0 || $extra >= $limit): ?> <a href="<?= e(url('/app/billing')) ?>"><?= e(t('See plans')) ?> →</a><?php endif; ?>
  </div></div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Name')) ?></th><th><?= e(t('Role')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('Last sign-in')) ?></th><th class="num"></th></tr></thead>
    <tbody>
    <?php foreach ($members as $m): $r = company_role($m + ['role' => 'employer']); ?>
      <tr>
        <td class="first"><b><?= e($m['name']) ?></b><div class="small muted"><?= e($m['email']) ?></div></td>
        <td data-l="<?= e(t('Role')) ?>">
          <?php if ($r === 'owner'): ?><span class="badge badge-ok"><?= e(t('Owner')) ?></span>
          <?php else: ?>
            <form method="post" action="<?= e(url('/app/team/' . $m['id'])) ?>" class="row" style="gap:6px"><?= csrf_field() ?>
              <select class="input" name="company_role" style="width:auto" onchange="this.form.submit()"><?php foreach (['hr', 'accountant'] as $k): ?><option value="<?= e($k) ?>" <?= $r === $k ? 'selected' : '' ?>><?= e(company_roles()[$k]) ?></option><?php endforeach; ?></select>
            </form>
          <?php endif; ?>
        </td>
        <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $m['status']]); ?></td>
        <td data-l="<?= e(t('Last sign-in')) ?>" class="small"><?= $m['last_login_at'] ? e(date_fmt($m['last_login_at'], null, true)) : '—' ?></td>
        <td class="num"><?php if ($r !== 'owner'): ?>
          <form method="post" action="<?= e(url('/app/team/' . $m['id'])) ?>" <?= $m['status'] !== 'suspended' ? 'data-confirm="' . e(t('Remove this person\'s access? They are signed out straight away.')) . '"' : '' ?>><?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $m['status'] === 'suspended' ? 'restore' : 'remove' ?>">
            <button class="btn btn-<?= $m['status'] === 'suspended' ? 'light' : 'ghost' ?> btn-sm"><?= e($m['status'] === 'suspended' ? t('Restore') : t('Remove')) ?></button>
          </form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="grid grid-3" style="margin-top:18px">
  <div class="card"><div class="card-body"><b><?= e(t('Owner')) ?></b><p class="small muted"><?= e(t('Everything, including billing, company settings, auto payroll and the team.')) ?></p></div></div>
  <div class="card"><div class="card-body"><b><?= e(t('HR manager')) ?></b><p class="small muted"><?= e(t('Employees, leave, loans and documents. Prepares the monthly payroll and submits it for approval. Cannot issue payslips.')) ?></p></div></div>
  <div class="card"><div class="card-body"><b><?= e(t('Accountant / approver')) ?></b><p class="small muted"><?= e(t('Reviews payroll, sends it back or approves and issues it. Emails and revokes payslips, issues documents.')) ?></p></div></div>
</div>

<dialog id="invite">
  <form method="post" action="<?= e(url('/app/team/invite')) ?>">
    <?= csrf_field() ?>
    <div class="dialog-head"><h3><?= e(t('Invite team member')) ?></h3><button type="button" class="btn btn-ghost btn-icon" data-close><?= icon('x') ?></button></div>
    <div class="dialog-body">
      <div class="field"><label class="label"><?= e(t('Full name')) ?></label><input class="input" name="name" required maxlength="190"></div>
      <div class="field"><label class="label"><?= e(t('Email')) ?></label><input class="input" type="email" name="email" required></div>
      <div class="field"><label class="label"><?= e(t('Role')) ?></label><select class="input" name="company_role"><option value="hr"><?= e(t('HR manager')) ?></option><option value="accountant"><?= e(t('Accountant / approver')) ?></option></select></div>
    </div>
    <div class="dialog-foot"><button type="button" class="btn btn-ghost" data-close><?= e(t('Cancel')) ?></button><button class="btn btn-primary"><?= icon('send') ?> <?= e(t('Send invitation')) ?></button></div>
  </form>
</dialog>
