<?php
declare(strict_types=1);

/* ============================================================
 * GDPR tools: data export (access + portability), closing a
 * company account (erasure), erasing a former employee, and
 * automatic retention clean-up.
 * ============================================================ */

function account_export(): void
{
    $u = require_login();
    if (!rate_limit('export:' . $u['id'], 10, 3600)) {
        flash('error', t('Too many downloads. Please try again in an hour.'));
        redirect('/account');
    }
    $strip = fn(array $row, array $keys) => array_diff_key($row, array_flip($keys));
    $userKeys = ['password_hash', 'token', 'token_expires', 'totp_secret', 'totp_recovery', 'session_version', 'failed_logins', 'locked_until'];
    $out = [
        'exported_at' => date('c'),
        'service' => site_name(),
        'note' => 'Your personal data held by ' . site_name() . ', in a machine-readable format (GDPR Articles 15 and 20).',
        'account' => $strip($u, $userKeys),
        'activity' => db_all('SELECT action, ip, created_at FROM activity WHERE user_id = ? ORDER BY id DESC LIMIT 1000', [$u['id']]),
    ];
    $dec = function (array $rows, array $jsonCols) {
        foreach ($rows as &$r) foreach ($jsonCols as $c) if (isset($r[$c]) && is_string($r[$c])) $r[$c] = json_decode($r[$c], true);
        return $rows;
    };
    if ($u['role'] === 'employee' && $u['employee_id']) {
        $eid = (int)$u['employee_id'];
        $out['employee_record'] = $dec([db_one('SELECT * FROM employees WHERE id = ?', [$eid]) ?: []], ['custom'])[0];
        $out['payslips'] = $dec(db_all("SELECT p.code, r.period, p.status, p.gross, p.total_deductions, p.net, p.breakdown, p.issued_at, p.revoked_at, p.revoke_reason, (SELECT COUNT(*) FROM verifications v WHERE v.payslip_id = p.id) AS times_verified FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status IN ('issued','revoked') ORDER BY r.period", [$eid]), ['breakdown']);
        $out['documents'] = $dec(db_all('SELECT type, code, status, addressed_to, purpose, year, snapshot, issued_at, revoked_at FROM documents WHERE employee_id = ?', [$eid]), ['snapshot']);
        $out['leave_requests'] = db_all('SELECT r.start_date, r.end_date, r.days, t.name AS type, r.reason, r.status, r.decision_note, r.created_at FROM leave_requests r JOIN leave_types t ON t.id = r.type_id WHERE r.employee_id = ?', [$eid]);
        $out['loans'] = db_all('SELECT id, title, principal, installment, start_period, status, created_at FROM loans WHERE employee_id = ?', [$eid]);
        $out['proof_of_income_links'] = db_all('SELECT label, items, created_at, expires_at, revoked_at, views, last_viewed_at FROM shares WHERE employee_id = ?', [$eid]);
    } elseif ($u['role'] === 'employer' && $u['company_id'] && company_role($u) === 'owner') {
        $cid = (int)$u['company_id'];
        $out['company'] = $strip(db_one('SELECT * FROM companies WHERE id = ?', [$cid]) ?: [], ['polar_customer_id', 'polar_subscription_id']);
        $out['team'] = array_map(fn($r) => $strip($r, $userKeys), db_all("SELECT * FROM users WHERE company_id = ? AND role = 'employer'", [$cid]));
        $out['employees'] = $dec(db_all('SELECT * FROM employees WHERE company_id = ?', [$cid]), ['custom']);
        $out['payroll_runs'] = db_all('SELECT * FROM payroll_runs WHERE company_id = ?', [$cid]);
        $out['payslips'] = $dec(db_all('SELECT p.*, r.period FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.company_id = ?', [$cid]), ['snapshot', 'breakdown', 'adjustments']);
        $out['documents'] = $dec(db_all('SELECT * FROM documents WHERE company_id = ?', [$cid]), ['snapshot']);
        $out['leave_types'] = db_all('SELECT * FROM leave_types WHERE company_id = ?', [$cid]);
        $out['leave_requests'] = db_all('SELECT * FROM leave_requests WHERE company_id = ?', [$cid]);
        $out['loans'] = db_all('SELECT * FROM loans WHERE company_id = ?', [$cid]);
        $out['salary_changes'] = $dec(db_all('SELECT * FROM salary_changes WHERE company_id = ?', [$cid]), ['old_salary', 'new_salary']);
        $out['verification_log'] = db_all('SELECT code_queried, result, verifier_type, channel, country, created_at FROM verifications WHERE company_id = ? ORDER BY id DESC LIMIT 20000', [$cid]);
    }
    audit('Downloaded personal data export', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="payslip-tech-data-' . date('Y-m-d') . '.json"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Permanently deletes a company and everything linked to it. */
function company_erase(int $cid): void
{
    $files = db_one('SELECT logo, signature_img, stamp_img FROM companies WHERE id = ?', [$cid]) ?: [];
    $users = array_column(db_all('SELECT id FROM users WHERE company_id = ?', [$cid]), 'id');
    $pdo = db();
    $pdo->beginTransaction();
    db_exec('DELETE lp FROM loan_payments lp JOIN loans l ON l.id = lp.loan_id WHERE l.company_id = ?', [$cid]);
    foreach (['loans', 'salary_changes', 'leave_requests', 'leave_types', 'shares', 'documents', 'verifications', 'payslips', 'payroll_runs', 'employees', 'smart_usage', 'activity'] as $t) {
        db_exec("DELETE FROM $t WHERE company_id = ?", [$cid]);
    }
    if ($users) {
        $in = implode(',', array_map('intval', $users));
        db_exec("DELETE m FROM chat_messages m JOIN chat_sessions s ON s.id = m.session_id WHERE s.user_id IN ($in)");
        db_exec("DELETE FROM chat_sessions WHERE user_id IN ($in)");
        db_exec("DELETE FROM activity WHERE user_id IN ($in)");
    }
    db_exec('DELETE FROM users WHERE company_id = ?', [$cid]);
    db_exec("DELETE FROM settings WHERE k LIKE ?", ['remind:' . $cid . ':%']);
    db_exec('DELETE FROM companies WHERE id = ?', [$cid]);
    $pdo->commit();
    foreach ($files as $f) if ($f && is_file(ROOT . '/uploads/logos/' . basename($f))) @unlink(ROOT . '/uploads/logos/' . basename($f));
}

function account_close(): void
{
    $u = require_role('employer');
    $c = my_company();
    if (company_role($u) !== 'owner') abort(403);
    if (is_demo_company($c)) {
        flash('info', t('The demo account cannot be closed.'));
        redirect('/account');
    }
    if (!password_verify((string)($_POST['password'] ?? ''), (string)$u['password_hash'])) {
        flash('error', t('Your password is not correct.'));
        redirect('/account#close');
    }
    if (mb_strtolower(trim((string)input('confirm_name'))) !== mb_strtolower(trim((string)$c['name']))) {
        flash('error', t('Type your company name exactly to confirm.'));
        redirect('/account#close');
    }
    if (!empty($c['polar_subscription_id']) && in_array($c['plan_status'] ?? '', ['active', 'trialing'], true) && empty($c['plan_cancel_at_end'])) {
        flash('error', t('Cancel your paid plan in Plan & billing first, then close the account.'));
        redirect('/app/billing');
    }
    $name = $c['name'];
    company_erase((int)$c['id']);
    audit('Company account closed and erased: ' . $name);
    logout_user();
    flash('success', t('Your company account and all its data have been deleted.'));
    redirect('/');
}

/** Removes the personal details of a former employee. Issued payslips stay as payroll records. */
function emp_employee_erase(string $id): void
{
    $c = my_company();
    if (!can_do('manage_people')) abort(403);
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    if ($e['status'] === 'active') {
        flash('error', t('Deactivate the employee first.'));
        redirect('/app/employees/' . $e['id']);
    }
    db_update('employees', ['name' => 'Erased employee #' . $e['id'], 'email' => null, 'phone' => null, 'bank_name' => null, 'account_no' => null, 'custom' => null, 'join_date' => null, 'designation' => null, 'department' => null], 'id = ?', [$e['id']]);
    db_exec("DELETE FROM users WHERE employee_id = ? AND role = 'employee'", [$e['id']]);
    db_exec('DELETE FROM shares WHERE employee_id = ?', [$e['id']]);
    db_exec('UPDATE leave_requests SET reason = NULL, decision_note = NULL WHERE employee_id = ?', [$e['id']]);
    log_activity((int)$c['id'], 'Erased personal details of former employee #' . $e['id']);
    flash('success', t('Personal details erased. Issued payslips and documents are kept as payroll records and can still be revoked.'));
    redirect('/app/employees?status=inactive');
}

/** Retention rules from the Privacy Policy. Runs once a day from cron. */
function run_retention(): array
{
    $today = date('Y-m-d');
    if (setting('retention_last', '') === $today) return [];
    db_exec("REPLACE INTO settings (k, v) VALUES ('retention_last', ?)", [$today]);
    $d = fn(string $ago) => date('Y-m-d H:i:s', strtotime($ago));
    $n = [];
    $n[] = db_exec('UPDATE verifications SET ip = NULL, user_agent = NULL WHERE created_at < ? AND (ip IS NOT NULL OR user_agent IS NOT NULL)', [$d('-6 months')]);
    $n[] = db_exec('DELETE FROM verifications WHERE created_at < ?', [$d('-24 months')]);
    $n[] = db_exec('UPDATE activity SET ip = NULL WHERE created_at < ? AND ip IS NOT NULL', [$d('-12 months')]);
    $old = array_column(db_all('SELECT id FROM chat_sessions WHERE updated_at < ?', [$d('-12 months')]), 'id');
    if ($old) {
        $in = implode(',', array_map('intval', $old));
        db_exec("DELETE FROM chat_messages WHERE session_id IN ($in)");
        db_exec("DELETE FROM chat_sessions WHERE id IN ($in)");
    }
    db_exec('DELETE FROM shares WHERE expires_at < ? OR revoked_at < ?', [$d('-6 months'), $d('-6 months')]);
    return ['Retention clean-up done (' . count($old) . ' old chats removed)'];
}
