<?php
declare(strict_types=1);

/* ---------------- Leave & attendance (employer) ---------------- */

function emp_leave(): void
{
    $c = my_company();
    $types = leave_types($c, false);
    $status = in_array(input('status'), ['pending', 'approved', 'rejected', 'cancelled', 'all'], true) ? (string)input('status') : 'pending';
    $sql = 'SELECT r.*, e.name, e.emp_code, t.name AS type_name, t.paid FROM leave_requests r JOIN employees e ON e.id = r.employee_id JOIN leave_types t ON t.id = r.type_id WHERE r.company_id = ?';
    $params = [$c['id']];
    if ($status !== 'all') { $sql .= ' AND r.status = ?'; $params[] = $status; }
    $requests = db_all($sql . ' ORDER BY r.start_date DESC LIMIT 300', $params);
    $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)input('month')) ? (string)input('month') : date('Y-m');
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $emps = db_all("SELECT id, name, emp_code FROM employees WHERE company_id = ? AND status = 'active' ORDER BY name", [$c['id']]);
    $rows = db_all("SELECT r.*, t.paid, t.name AS type_name FROM leave_requests r JOIN leave_types t ON t.id = r.type_id WHERE r.company_id = ? AND r.status = 'approved' AND r.start_date <= ? AND r.end_date >= ?", [$c['id'], $end, $start]);
    $summary = [];
    foreach ($emps as $e) $summary[$e['id']] = ['e' => $e, 'paid' => 0.0, 'unpaid' => 0.0, 'types' => []];
    foreach ($rows as $r) {
        if (!isset($summary[$r['employee_id']])) continue;
        $d = leave_working_days($c, $r['start_date'], $r['end_date'], $month);
        $summary[$r['employee_id']][(int)$r['paid'] ? 'paid' : 'unpaid'] += $d;
        $summary[$r['employee_id']]['types'][$r['type_name']] = ($summary[$r['employee_id']]['types'][$r['type_name']] ?? 0) + $d;
    }
    $workdays = leave_working_days($c, $start, $end);
    $pending = (int)db_val("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending'", [$c['id']]);
    render('app/leave', ['title' => t('Leave & attendance'), 'nav' => 'leave', 'c' => $c, 'types' => $types, 'requests' => $requests, 'status' => $status, 'month' => $month, 'summary' => $summary, 'workdays' => $workdays, 'emps' => $emps, 'pending' => $pending], 'app');
}

function emp_leave_create(): void
{
    $c = my_company();
    $eid = (int)input('employee_id');
    if (!db_val('SELECT 1 FROM employees WHERE id = ? AND company_id = ?', [$eid, $c['id']])) abort(404);
    $res = leave_request_create($c, $eid, (int)input('type_id'), (string)input('start_date'), (string)input('end_date'), (string)input('reason'), (int)current_user()['id'], true);
    flash(is_int($res) ? 'success' : 'error', is_int($res) ? t('Leave recorded and approved.') : $res);
    redirect('/app/leave?status=approved');
}

function emp_leave_decide(string $id): void
{
    $c = my_company();
    $r = db_one('SELECT r.*, e.name, e.email, t.name AS type_name FROM leave_requests r JOIN employees e ON e.id = r.employee_id JOIN leave_types t ON t.id = r.type_id WHERE r.id = ? AND r.company_id = ?', [(int)$id, $c['id']]);
    if (!$r) abort(404);
    $action = (string)input('action');
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled'];
    if (!isset($map[$action]) || ($action !== 'cancel' && $r['status'] !== 'pending') || ($action === 'cancel' && !in_array($r['status'], ['pending', 'approved'], true))) {
        back('/app/leave');
    }
    $note = mb_substr(trim((string)input('note')), 0, 200);
    db_update('leave_requests', ['status' => $map[$action], 'decided_by' => current_user()['id'], 'decided_at' => now(), 'decision_note' => $note ?: null], 'id = ?', [$r['id']]);
    sync_employee_drafts($c, (int)$r['employee_id']);
    if ($r['email'] && $action !== 'cancel') {
        $range = date_fmt($r['start_date'], 'en') . ' – ' . date_fmt($r['end_date'], 'en');
        send_mail($r['email'], sprintf($action === 'approve' ? t('Your leave was approved', 'en') . ' (%s)' : t('Your leave request was declined', 'en') . ' (%s)', $range), sprintf(t("Hello %s,\n\nYour %s request for %s (%s days) was %s.%s", 'en'), $r['name'], $r['type_name'], $range, $r['days'] + 0, $action === 'approve' ? 'approved' : 'declined', $note ? "\n\nNote: " . $note : ''), abs_url('/me/leave'), t('View my leave', 'en'));
    }
    flash('success', ['approve' => t('Leave approved.'), 'reject' => t('Leave declined.'), 'cancel' => t('Leave cancelled.')][$action]);
    back('/app/leave');
}

function emp_leave_settings(): void
{
    $c = my_company();
    $weekend = array_values(array_intersect(array_map('intval', (array)($_POST['weekend'] ?? [])), range(1, 7)));
    db_update('companies', ['weekend' => $weekend ? implode(',', $weekend) : null], 'id = ?', [$c['id']]);
    foreach ((array)($_POST['types'] ?? []) as $tid => $t) {
        $name = mb_substr(trim((string)($t['name'] ?? '')), 0, 60);
        $data = ['name' => $name, 'days_per_year' => max(0, min(365, (float)($t['days'] ?? 0))), 'paid' => !empty($t['paid']) ? 1 : 0, 'active' => !empty($t['active']) ? 1 : 0];
        if ($tid === 'new') {
            if ($name !== '') db_insert('leave_types', $data + ['company_id' => $c['id']]);
        } elseif ($name !== '') {
            db_update('leave_types', $data, 'id = ? AND company_id = ?', [(int)$tid, $c['id']]);
        }
    }
    flash('success', t('Leave settings saved.'));
    redirect('/app/leave?tab=settings');
}

/* ---------------- Loans & advances (employer) ---------------- */

function emp_loans(): void
{
    $c = my_company();
    $rows = db_all("SELECT l.*, e.name, e.emp_code, (SELECT COALESCE(SUM(amount),0) FROM loan_payments p WHERE p.loan_id = l.id) AS paid, (SELECT COUNT(*) FROM loan_payments p WHERE p.loan_id = l.id) AS instalments FROM loans l JOIN employees e ON e.id = l.employee_id WHERE l.company_id = ? ORDER BY FIELD(l.status,'active','closed','cancelled'), l.id DESC LIMIT 500", [$c['id']]);
    $emps = db_all("SELECT id, name, emp_code FROM employees WHERE company_id = ? AND status = 'active' ORDER BY name", [$c['id']]);
    $out = array_sum(array_map(fn($r) => $r['status'] === 'active' ? max(0, (float)$r['principal'] - (float)$r['paid']) : 0, $rows));
    render('app/loans', ['title' => t('Loans & advances'), 'nav' => 'loans', 'rows' => $rows, 'emps' => $emps, 'outstanding' => $out], 'app');
}

function emp_loan_save(): void
{
    $c = my_company();
    $eid = (int)input('employee_id');
    if (!db_val("SELECT 1 FROM employees WHERE id = ? AND company_id = ? AND status = 'active'", [$eid, $c['id']])) {
        flash('error', t('Choose an employee.'));
        redirect('/app/loans');
    }
    $principal = num_input('principal');
    $inst = num_input('installment');
    $start = (string)input('start_period');
    if ($principal <= 0 || $inst <= 0 || $inst > $principal || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $start)) {
        flash('error', t('Enter the amount, a monthly instalment no larger than the amount, and the first month.'));
        redirect('/app/loans');
    }
    $title = mb_substr(trim((string)input('title')), 0, 120) ?: t('Salary advance');
    db_insert('loans', ['company_id' => $c['id'], 'employee_id' => $eid, 'title' => $title, 'principal' => $principal, 'installment' => $inst, 'start_period' => $start, 'status' => 'active', 'created_by' => current_user()['id'], 'created_at' => now()]);
    sync_employee_drafts($c, $eid);
    $name = db_val('SELECT name FROM employees WHERE id = ?', [$eid]);
    log_activity((int)$c['id'], 'Added ' . $title . ' for ' . $name);
    flash('success', sprintf(t('%s added. %s will be deducted each month from %s until it is repaid.'), $title, money($inst), period_label($start)));
    redirect('/app/loans');
}

function emp_loan_cancel(string $id): void
{
    $c = my_company();
    $l = db_one("SELECT * FROM loans WHERE id = ? AND company_id = ? AND status = 'active'", [(int)$id, $c['id']]);
    if (!$l) abort(404);
    db_update('loans', ['status' => 'cancelled', 'closed_at' => now()], 'id = ?', [$l['id']]);
    sync_employee_drafts($c, (int)$l['employee_id']);
    flash('success', t('Loan stopped. No more instalments will be deducted.'));
    redirect('/app/loans');
}

/* ---------------- Employee: my leave ---------------- */

function me_employee(): array
{
    $u = require_role('employee');
    $e = db_one('SELECT e.*, c.name AS company_name, c.currency FROM employees e JOIN companies c ON c.id = e.company_id WHERE e.id = ?', [$u['employee_id']]);
    if (!$e) abort(403);
    currency_context($e['currency'] ?? 'BDT');
    return $e;
}

function me_leave(): void
{
    $e = me_employee();
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$e['company_id']]);
    $requests = db_all('SELECT r.*, t.name AS type_name FROM leave_requests r JOIN leave_types t ON t.id = r.type_id WHERE r.employee_id = ? ORDER BY r.start_date DESC LIMIT 100', [$e['id']]);
    $loans = db_all("SELECT l.*, (SELECT COALESCE(SUM(amount),0) FROM loan_payments p WHERE p.loan_id = l.id) AS paid FROM loans l WHERE l.employee_id = ? AND l.status = 'active'", [$e['id']]);
    render('me/leave', ['title' => t('My leave'), 'nav' => 'me-leave', 'e' => $e, 'c' => $c, 'types' => leave_types($c), 'balances' => leave_balances($c, (int)$e['id']), 'requests' => $requests, 'loans' => $loans], 'app');
}

function me_leave_request(): void
{
    $e = me_employee();
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$e['company_id']]);
    if (!rate_limit('leave:' . $e['id'], 20, 86400)) {
        flash('error', t('Too many requests today. Please try again tomorrow.'));
        redirect('/me/leave');
    }
    $res = leave_request_create($c, (int)$e['id'], (int)input('type_id'), (string)input('start_date'), (string)input('end_date'), (string)input('reason'), (int)current_user()['id']);
    if (!is_int($res)) {
        flash('error', $res);
        redirect('/me/leave');
    }
    $r = db_one('SELECT r.*, t.name AS type_name FROM leave_requests r JOIN leave_types t ON t.id = r.type_id WHERE r.id = ?', [$res]);
    notify_roles($c, ['owner', 'hr'], sprintf(t('Leave request from %s', 'en'), $e['name']), sprintf(t("%s asked for %s: %s to %s (%s days).\n\nReason: %s", 'en'), $e['name'], $r['type_name'], date_fmt($r['start_date'], 'en'), date_fmt($r['end_date'], 'en'), $r['days'] + 0, $r['reason'] ?: '—'), '/app/leave', t('Review request', 'en'));
    flash('success', t('Request sent. You will get an email when it is approved.'));
    redirect('/me/leave');
}

function me_leave_cancel(string $id): void
{
    $e = me_employee();
    $r = db_one("SELECT * FROM leave_requests WHERE id = ? AND employee_id = ? AND status = 'pending'", [(int)$id, $e['id']]);
    if ($r) db_update('leave_requests', ['status' => 'cancelled', 'decided_at' => now()], 'id = ?', [$r['id']]);
    flash('success', t('Request cancelled.'));
    redirect('/me/leave');
}
