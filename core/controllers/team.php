<?php
declare(strict_types=1);

/* ---------------- Team members & roles (owner only, enforced in my_company) ---------------- */

function emp_team(): void
{
    $c = my_company();
    $members = team_members($c);
    $extra = count(array_filter($members, fn($m) => $m['company_role'] !== 'owner' && $m['status'] !== 'suspended'));
    $link = $_SESSION['_invite_link'] ?? null;
    unset($_SESSION['_invite_link']);
    render('app/team', ['title' => t('Team'), 'nav' => 'team', 'c' => $c, 'members' => $members, 'limit' => team_limit($c), 'extra' => $extra, 'link' => $link], 'app');
}

function emp_team_invite(): void
{
    $c = my_company();
    if (is_demo_company($c)) {
        flash('info', t('Team invites are disabled in the demo account.'));
        redirect('/app/team');
    }
    $name = mb_substr(trim((string)input('name')), 0, 190);
    $email = strtolower(trim((string)input('email')));
    $role = (string)input('company_role');
    if (!in_array($role, ['hr', 'accountant'], true) || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', t('Enter a name, a valid email and a role.'));
        redirect('/app/team');
    }
    $limit = team_limit($c);
    $extra = (int)db_val("SELECT COUNT(*) FROM users WHERE company_id = ? AND role = 'employer' AND COALESCE(company_role,'owner') <> 'owner' AND status <> 'suspended'", [$c['id']]);
    if ($limit !== null && $extra >= $limit) {
        flash('error', $limit === 0 ? t('Team members are available on the Starter and Business plans.') : sprintf(t('Your plan allows %d team members. Upgrade to add more.'), $limit));
        redirect(billing_enabled() ? '/app/billing' : '/app/team');
    }
    $u = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($u && !((int)$u['company_id'] === (int)$c['id'] && $u['role'] === 'employer' && $u['status'] === 'invited')) {
        flash('error', t('This email is already used by another account.'));
        redirect('/app/team');
    }
    if (!$u) {
        $uid = db_insert('users', ['role' => 'employer', 'company_id' => $c['id'], 'company_role' => $role, 'name' => $name, 'email' => $email, 'status' => 'invited', 'lang' => lang(), 'created_at' => now()]);
    } else {
        $uid = (int)$u['id'];
        db_update('users', ['company_role' => $role, 'name' => $name], 'id = ?', [$uid]);
    }
    $token = issue_token($uid, 24 * 7);
    $link = abs_url('/invite/' . $token);
    $roleName = company_roles()[$role];
    $sent = send_mail($email, sprintf(t('%s invited you to their PaySlip team'), $c['name']), sprintf(t("Hello %s,\n\n%s added you to their PaySlip account as %s. Choose a password to get started. This link is valid for 7 days."), $name, $c['name'], $roleName), $link, t('Accept invitation'));
    $_SESSION['_invite_link'] = ['name' => $name, 'link' => $link, 'sent' => $sent];
    audit('Invited ' . $email . ' to the team as ' . $role, (int)$c['id']);
    redirect('/app/team');
}

function emp_team_update(string $id): void
{
    $c = my_company();
    $m = db_one("SELECT * FROM users WHERE id = ? AND company_id = ? AND role = 'employer'", [(int)$id, $c['id']]);
    if (!$m || company_role($m) === 'owner') abort(404);
    $action = (string)input('action');
    if ($action === 'remove') {
        db_update('users', ['status' => 'suspended', 'token' => null, 'session_version' => (int)$m['session_version'] + 1], 'id = ?', [$m['id']]);
        audit('Removed ' . $m['email'] . ' from the team', (int)$c['id']);
        flash('success', sprintf(t('%s no longer has access.'), $m['name']));
    } elseif ($action === 'restore') {
        $limit = team_limit($c);
        $extra = (int)db_val("SELECT COUNT(*) FROM users WHERE company_id = ? AND role = 'employer' AND COALESCE(company_role,'owner') <> 'owner' AND status <> 'suspended'", [$c['id']]);
        if ($limit !== null && $extra >= $limit) {
            flash('error', sprintf(t('Your plan allows %d team members. Upgrade to add more.'), $limit));
            redirect('/app/team');
        }
        db_update('users', ['status' => $m['password_hash'] ? 'active' : 'invited'], 'id = ?', [$m['id']]);
        flash('success', sprintf(t('%s has access again.'), $m['name']));
    } elseif (in_array(input('company_role'), ['hr', 'accountant'], true)) {
        db_update('users', ['company_role' => (string)input('company_role')], 'id = ?', [$m['id']]);
        audit('Changed role of ' . $m['email'] . ' to ' . input('company_role'), (int)$c['id']);
        flash('success', t('Role updated.'));
    }
    redirect('/app/team');
}

/* ---------------- Payroll approval ---------------- */

function emp_run_submit(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    if ($run['status'] !== 'draft') redirect('/app/payroll/' . $run['id']);
    $u = current_user();
    db_update('payroll_runs', ['submitted_at' => now(), 'submitted_by' => $u['id']], 'id = ?', [$run['id']]);
    $label = period_label($run['period'], 'en');
    notify_roles($c, ['owner', 'accountant'], sprintf(t('%s payroll is ready for approval'), $label), sprintf(t("Hello,\n\n%s prepared the %s payroll for %s and submitted it for approval. Review it and finalise to issue the payslips."), $u['name'], $label, $c['name']), '/app/payroll/' . $run['id'], t('Review payroll'));
    log_activity((int)$c['id'], 'Submitted payroll for ' . $label . ' for approval');
    flash('success', t('Sent for approval. The accountant or owner will review and finalise it.'));
    redirect('/app/payroll/' . $run['id']);
}

function emp_run_reject(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    if ($run['status'] !== 'draft') redirect('/app/payroll/' . $run['id']);
    $note = mb_substr(trim((string)input('note')), 0, 300);
    db_update('payroll_runs', ['submitted_at' => null, 'submitted_by' => null], 'id = ?', [$run['id']]);
    $label = period_label($run['period'], 'en');
    notify_roles($c, ['hr'], sprintf(t('%s payroll needs changes'), $label), sprintf(t("Hello,\n\n%s sent the %s payroll back for changes.\n\n%s"), current_user()['name'], $label, $note ?: '—'), '/app/payroll/' . $run['id'], t('Open payroll'));
    log_activity((int)$c['id'], 'Sent payroll for ' . $label . ' back for changes');
    flash('success', t('Sent back to HR with your note.'));
    redirect('/app/payroll/' . $run['id']);
}
