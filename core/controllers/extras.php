<?php
declare(strict_types=1);

/* ---------------- Festival bonus & paste from Excel (draft runs) ---------------- */

function emp_run_bonus(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    require_feature($c, 'festival_bonus', '/app/payroll/' . $run['id']);
    $mode = input('mode') === 'fixed' ? 'fixed' : 'percent';
    $res = festival_bonus_apply($c, $run, (string)input('label'), $mode, num_input('value'), (int)input('min_months'));
    if (!is_array($res)) {
        flash('error', $res);
    } else {
        log_activity((int)$c['id'], 'Added ' . (input('label') ?: 'festival bonus') . ' to payroll ' . $run['period']);
        flash('success', sprintf(t('Festival bonus added for %s employees, %s in total.'), num($res[0]), money($res[1])));
    }
    redirect('/app/payroll/' . $run['id']);
}

function emp_run_paste(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    require_feature($c, 'paste', '/app/payroll/' . $run['id']);
    if ($run['status'] !== 'draft') redirect('/app/payroll/' . $run['id']);
    [$rows, $errors] = paste_parse((string)input('data'));
    if ($errors) {
        flash('error', $errors[0]);
        redirect('/app/payroll/' . $run['id']);
    }
    $slips = db_all('SELECT p.id, e.emp_code, e.name FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ?', [$run['id']]);
    $byCode = [];
    $byName = [];
    foreach ($slips as $s) {
        $byCode[mb_strtolower(trim($s['emp_code']))] = $s;
        $byName[mb_strtolower(preg_replace('/\s+/', ' ', trim($s['name'])))] = $s;
    }
    $ok = 0;
    $missed = [];
    foreach ($rows as $r) {
        $s = null;
        if (!empty($r['id'])) $s = $byCode[mb_strtolower(trim((string)$r['id']))] ?? null;
        if (!$s && !empty($r['name'])) $s = $byName[mb_strtolower(preg_replace('/\s+/', ' ', trim((string)$r['name'])))] ?? null;
        if (!$s) { $missed[] = $r['id'] ?? $r['name'] ?? '?'; continue; }
        $changes = array_intersect_key($r, array_flip(['overtime', 'bonus', 'loan', 'other', 'unpaid_days', 'note']));
        $err = payroll_adjust_slip($run, (int)$s['id'], $changes);
        if ($err) $missed[] = $s['name'] . ' (' . $err . ')'; else $ok++;
    }
    log_activity((int)$c['id'], 'Pasted adjustments for ' . $ok . ' employees into payroll ' . $run['period']);
    flash($missed ? 'info' : 'success', sprintf(t('Updated %s employees from your sheet.'), num($ok)) . ($missed ? ' ' . sprintf(t('Not matched: %s'), implode(', ', array_slice($missed, 0, 8))) : ''));
    redirect('/app/payroll/' . $run['id']);
}

/* ---------------- Payment files ---------------- */

function emp_run_payfile(string $id, string $kind): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    require_feature($c, 'payment_file', '/app/payroll/' . $run['id']);
    $rows = payment_file_rows($run, $kind);
    $ref = 'Salary ' . date('M Y', strtotime($run['period'] . '-01'));
    $cur = $c['currency'] ?? 'BDT';
    if ($kind === 'mfs') {
        $head = ['Wallet number', 'Amount', 'Name', 'Provider', 'Reference', 'Employee ID'];
        $data = array_map(fn($r) => [mobile_wallet_number($r['account_no'] ?: $r['phone']), round((float)$r['net'], 2), $r['name'], payment_method_label($r['payment_method'], 'en'), $ref, $r['emp_code']], $rows);
    } elseif ($kind === 'bank') {
        $head = ['Account name', 'Account number', 'Bank', 'Amount (' . $cur . ')', 'Reference', 'Employee ID'];
        $data = array_map(fn($r) => [$r['name'], (string)$r['account_no'], (string)$r['bank_name'], round((float)$r['net'], 2), $ref, $r['emp_code']], $rows);
    } else {
        $head = ['Employee ID', 'Name', 'Payment method', 'Amount (' . $cur . ')', 'Signature on receipt'];
        $data = array_map(fn($r) => [$r['emp_code'], $r['name'], payment_method_label($r['payment_method'], 'en'), round((float)$r['net'], 2), ''], $rows);
    }
    if ($data) $data[] = $kind === 'mfs' ? ['TOTAL', round(array_sum(array_column($data, 1)), 2), count($data) . ' payments', '', '', ''] : ($kind === 'bank' ? ['TOTAL', '', count($data) . ' payments', round(array_sum(array_column($data, 3)), 2), '', ''] : ['', 'TOTAL', count($data) . ' people', round(array_sum(array_column($data, 3)), 2), '']);
    log_activity((int)$c['id'], 'Downloaded ' . $kind . ' payment file for ' . $run['period']);
    send_xlsx('salary-' . $kind . '-' . $run['period'] . '.xlsx', $head, $data, ['bank' => 'Bank transfers', 'mfs' => 'Mobile wallets', 'cash' => 'Cash & cheque'][$kind] ?? 'Payments');
}

/* ---------------- Reports ---------------- */

function emp_reports(): void
{
    $c = my_company();
    require_feature($c, 'reports');
    $tab = in_array(input('tab'), ['register', 'trend', 'departments', 'yearly'], true) ? (string)input('tab') : 'trend';
    $periods = array_column(db_all("SELECT period FROM payroll_runs WHERE company_id = ? AND status = 'finalized' ORDER BY period DESC", [$c['id']]), 'period');
    $period = in_array(input('period'), $periods, true) ? (string)input('period') : ($periods[0] ?? null);
    $year = (int)(input('year') ?: date('Y'));
    $data = report_data($c, $tab, $period, $year);
    if (input('export')) send_xlsx($tab . '-' . ($tab === 'yearly' ? $year : ($period ?? 'all')) . '.xlsx', $data['head'], array_map(fn($r) => array_values($r), $data['rows']), ucfirst($tab));
    render('app/reports', ['title' => t('Reports'), 'nav' => 'reports', 'c' => $c, 'tab' => $tab, 'periods' => $periods, 'period' => $period, 'year' => $year, 'report' => $data], 'app');
}

function report_data(array $c, string $tab, ?string $period, int $year): array
{
    $cid = $c['id'];
    if ($tab === 'register') {
        $rows = $period ? db_all("SELECT e.emp_code, e.name, e.department, e.designation, p.breakdown, p.gross, p.total_deductions, p.net, p.code, p.status FROM payslips p JOIN payroll_runs r ON r.id = p.run_id JOIN employees e ON e.id = p.employee_id WHERE p.company_id = ? AND r.period = ? ORDER BY e.department, e.name", [$cid, $period]) : [];
        $head = ['Employee ID', 'Name', 'Department', 'Designation', 'Basic', 'House rent', 'Medical', 'Conveyance', 'Other allowance', 'Overtime', 'Bonus', 'Gross', 'Tax', 'PF', 'Loan / advance', 'Absence', 'Other deduction', 'Total deductions', 'Net pay', 'Payslip code', 'Status'];
        $out = [];
        foreach ($rows as $r) {
            $b = json_decode((string)$r['breakdown'], true) ?: [];
            $ea = $b['earnings'] ?? [];
            $de = $b['deductions'] ?? [];
            $out[] = [$r['emp_code'], $r['name'], (string)$r['department'], (string)$r['designation'], (float)($ea['basic'] ?? 0), (float)($ea['house_rent'] ?? 0), (float)($ea['medical'] ?? 0), (float)($ea['conveyance'] ?? 0), (float)($ea['other_allowance'] ?? 0), (float)($ea['overtime'] ?? 0), (float)($ea['bonus'] ?? 0), (float)$r['gross'], (float)($de['tax'] ?? 0), (float)($de['pf'] ?? 0), (float)($de['loan'] ?? 0), (float)($de['absence'] ?? 0), (float)($de['other'] ?? 0), (float)$r['total_deductions'], (float)$r['net'], (string)$r['code'], $r['status']];
        }
        return ['head' => $head, 'rows' => $out, 'money' => range(4, 18)];
    }
    if ($tab === 'departments') {
        $rows = $period ? db_all("SELECT COALESCE(NULLIF(e.department,''),'—') AS dept, COUNT(*) AS people, SUM(p.gross) AS gross, SUM(p.total_deductions) AS ded, SUM(p.net) AS net FROM payslips p JOIN payroll_runs r ON r.id = p.run_id JOIN employees e ON e.id = p.employee_id WHERE p.company_id = ? AND r.period = ? AND p.status = 'issued' GROUP BY dept ORDER BY net DESC", [$cid, $period]) : [];
        return ['head' => ['Department', 'Employees', 'Gross', 'Deductions', 'Net pay', 'Share of net'], 'rows' => array_map(function ($r) use ($rows) { $tot = array_sum(array_column($rows, 'net')) ?: 1; return [$r['dept'], (int)$r['people'], (float)$r['gross'], (float)$r['ded'], (float)$r['net'], round((float)$r['net'] / $tot * 100, 1) . '%']; }, $rows), 'money' => [2, 3, 4]];
    }
    if ($tab === 'yearly') {
        $rows = db_all("SELECT e.emp_code, e.name, e.department, COUNT(*) AS months, SUM(p.gross) AS gross, SUM(p.total_deductions) AS ded, SUM(p.net) AS net FROM payslips p JOIN payroll_runs r ON r.id = p.run_id JOIN employees e ON e.id = p.employee_id WHERE p.company_id = ? AND r.period LIKE ? AND p.status = 'issued' GROUP BY e.id ORDER BY e.name", [$cid, $year . '-%']);
        $tax = [];
        foreach (db_all("SELECT p.employee_id, p.breakdown FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.company_id = ? AND r.period LIKE ? AND p.status = 'issued'", [$cid, $year . '-%']) as $p) {
            $b = json_decode((string)$p['breakdown'], true) ?: [];
            $tax[$p['employee_id']] = ($tax[$p['employee_id']] ?? 0) + (float)($b['deductions']['tax'] ?? 0);
        }
        $ids = array_column(db_all("SELECT id, emp_code FROM employees WHERE company_id = ?", [$cid]), 'id', 'emp_code');
        return ['head' => ['Employee ID', 'Name', 'Department', 'Months paid', 'Gross', 'Tax deducted', 'Total deductions', 'Net pay'], 'rows' => array_map(fn($r) => [$r['emp_code'], $r['name'], (string)$r['department'], (int)$r['months'], (float)$r['gross'], round($tax[$ids[$r['emp_code']] ?? 0] ?? 0, 2), (float)$r['ded'], (float)$r['net']], $rows), 'money' => [4, 5, 6, 7]];
    }
    $rows = db_all("SELECT r.period, COUNT(p.id) AS people, SUM(p.gross) AS gross, SUM(p.total_deductions) AS ded, SUM(p.net) AS net FROM payroll_runs r JOIN payslips p ON p.run_id = r.id AND p.status = 'issued' WHERE r.company_id = ? AND r.status = 'finalized' GROUP BY r.id ORDER BY r.period DESC LIMIT 24", [$cid]);
    return ['head' => ['Month', 'Employees', 'Gross', 'Deductions', 'Net pay'], 'rows' => array_map(fn($r) => [period_label($r['period'], 'en'), (int)$r['people'], (float)$r['gross'], (float)$r['ded'], (float)$r['net']], $rows), 'money' => [2, 3, 4], 'chart' => array_reverse($rows)];
}

/* ---------------- Increments ---------------- */

function emp_increment_form(string $id): void
{
    $c = my_company();
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    $history = db_all('SELECT s.*, d.code FROM salary_changes s LEFT JOIN documents d ON d.id = s.document_id WHERE s.employee_id = ? ORDER BY s.effective_period DESC, s.id DESC', [$e['id']]);
    render('app/increment', ['title' => t('Increment') . ' — ' . $e['name'], 'nav' => 'employees', 'c' => $c, 'e' => $e, 'history' => $history, 'allowed' => plan_allows($c, 'increments')], 'app');
}

function emp_increment_save(string $id): void
{
    $c = my_company();
    require_feature($c, 'increments', '/app/employees/' . (int)$id . '/increment');
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    $eff = (string)input('effective_period');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $eff)) {
        flash('error', t('Choose the month the new salary starts.'));
        redirect('/app/employees/' . $e['id'] . '/increment');
    }
    $old = salary_of($e);
    $pct = num_input('percent');
    $new = [];
    foreach (SALARY_KEYS as $k) $new[$k] = $pct > 0 ? round($old[$k] * (1 + $pct / 100), 2) : num_input('new_' . $k);
    if (array_sum($new) <= 0 || $new == $old) {
        flash('error', t('Enter a percentage or the new salary amounts.'));
        redirect('/app/employees/' . $e['id'] . '/increment');
    }
    $desig = mb_substr(trim((string)input('designation')), 0, 190);
    $desig = $desig !== '' && $desig !== $e['designation'] ? $desig : null;
    $reason = mb_substr(trim((string)input('reason')), 0, 255) ?: null;
    $due = $eff <= date('Y-m');
    $scId = db_insert('salary_changes', ['company_id' => $c['id'], 'employee_id' => $e['id'], 'effective_period' => $eff, 'old_salary' => json_encode($old), 'new_salary' => json_encode($new), 'new_designation' => $desig, 'reason' => $reason, 'status' => 'scheduled', 'created_by' => current_user()['id'], 'created_at' => now()]);
    if ($due) {
        apply_due_increments($c, date('Y-m'));
        sync_employee_drafts($c, (int)$e['id']);
    }
    $docCode = null;
    if (input('letter')) {
        $oldG = array_sum($old);
        $newG = array_sum($new);
        $doc = document_issue($c, (int)$e['id'], 'increment_letter', ['purpose' => $desig ? t('Promotion and salary increment') : t('Salary increment'), 'data' => [
            'old' => $old, 'new' => $new, 'old_gross' => round($oldG, 2), 'new_gross' => round($newG, 2), 'increase' => round($newG - $oldG, 2),
            'percent' => $oldG > 0 ? round(($newG - $oldG) / $oldG * 100, 1) : null, 'effective' => $eff,
            'designation_old' => $e['designation'], 'designation_new' => $desig, 'reason' => $reason,
        ]], (int)current_user()['id']);
        if (is_array($doc)) {
            db_update('salary_changes', ['document_id' => $doc['id']], 'id = ?', [$scId]);
            $docCode = $doc['code'];
            if ($e['email']) send_mail($e['email'], t('Your increment letter', 'en') . ' — ' . $c['name'], sprintf(t("Hello %s,\n\nCongratulations! %s issued your increment letter. Your new salary starts from %s.\n\nVerification code: %s", 'en'), $e['name'], $c['name'], period_label($eff, 'en'), $docCode), abs_url('/document/' . $docCode), t('View letter', 'en'));
        }
    }
    log_activity((int)$c['id'], 'Increment for ' . $e['name'] . ' from ' . $eff);
    flash('success', ($due ? t('New salary applied.') : sprintf(t('New salary scheduled from %s.'), period_label($eff))) . ($docCode ? ' ' . sprintf(t('Letter %s issued.'), $docCode) : ''));
    redirect('/app/employees/' . $e['id'] . '/increment');
}

/* ---------------- Final settlement ---------------- */

function settlement_defaults(array $c, array $e, string $last): array
{
    $sal = salary_of($e);
    $gross = array_sum($sal);
    $day = $gross / 30;
    $lastPeriod = substr($last, 0, 7);
    $issued = (bool)db_val("SELECT 1 FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND r.period = ? AND p.status IN ('issued','draft')", [$e['id'], $lastPeriod]);
    $unpaidDays = $issued ? 0 : (int)date('j', strtotime($last));
    $annual = 0.0;
    foreach (leave_balances($c, (int)$e['id'], (int)substr($last, 0, 4)) as $b) {
        if (preg_match('/annual|earned/i', $b['type']['name']) && $b['left'] !== null) $annual += $b['left'];
    }
    $loans = 0.0;
    foreach (db_all("SELECT * FROM loans WHERE employee_id = ? AND status = 'active'", [$e['id']]) as $l) $loans += max(0, (float)$l['principal'] - loan_paid((int)$l['id']));
    $years = $e['join_date'] ? (strtotime($last) - strtotime($e['join_date'])) / (365.25 * 86400) : 0;
    return [
        'salary_days' => $unpaidDays, 'salary_amount' => round($day * $unpaidDays, 2),
        'leave_days' => $annual, 'leave_amount' => round($day * $annual, 2),
        'notice_amount' => 0, 'gratuity' => 0, 'other_earning' => 0,
        'loans' => round($loans, 2), 'tax' => 0, 'other_deduction' => 0,
        'service_years' => round(max(0, $years), 1), 'day_rate' => round($day, 2), 'gross_monthly' => round($gross, 2),
    ];
}

function emp_settlement_form(string $id): void
{
    $c = my_company();
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    $last = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)input('last')) ? (string)input('last') : date('Y-m-d');
    $done = db_all("SELECT code, issued_at FROM documents WHERE employee_id = ? AND type = 'final_settlement' AND status = 'issued'", [$e['id']]);
    render('app/settlement', ['title' => t('Final settlement') . ' — ' . $e['name'], 'nav' => 'employees', 'c' => $c, 'e' => $e, 'last' => $last, 'd' => settlement_defaults($c, $e, $last), 'done' => $done, 'allowed' => plan_allows($c, 'settlement')], 'app');
}

function emp_settlement_save(string $id): void
{
    $c = my_company();
    require_feature($c, 'settlement', '/app/employees/' . (int)$id . '/settlement');
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    $last = (string)input('last');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
        flash('error', t('Choose the last working day.'));
        redirect('/app/employees/' . $e['id'] . '/settlement');
    }
    $earn = array_filter([
        'Salary for ' . num_input('salary_days') . ' days' => num_input('salary_amount'),
        'Leave encashment (' . num_input('leave_days') . ' days)' => num_input('leave_amount'),
        'Notice pay' => num_input('notice_amount'),
        'Gratuity / service benefit' => num_input('gratuity'),
        (mb_substr(trim((string)input('other_earning_label')), 0, 60) ?: 'Other dues') => num_input('other_earning'),
    ], fn($v) => $v > 0);
    $ded = array_filter([
        'Outstanding loans and advances' => num_input('loans'),
        'Income tax' => num_input('tax'),
        (mb_substr(trim((string)input('other_deduction_label')), 0, 60) ?: 'Other deductions') => num_input('other_deduction'),
    ], fn($v) => $v > 0);
    $te = round(array_sum($earn), 2);
    $td = round(array_sum($ded), 2);
    $data = ['last_day' => $last, 'reason' => mb_substr(trim((string)input('reason')), 0, 120) ?: 'Resignation', 'earnings' => $earn, 'deductions' => $ded, 'total_earnings' => $te, 'total_deductions' => $td, 'net' => round($te - $td, 2), 'pay_by' => input('pay_by') ?: null, 'note' => mb_substr(trim((string)input('note')), 0, 300)];
    $doc = document_issue($c, (int)$e['id'], 'final_settlement', ['purpose' => $data['reason'], 'data' => $data], (int)current_user()['id']);
    if (!is_array($doc)) {
        flash('error', $doc);
        redirect('/app/employees/' . $e['id'] . '/settlement');
    }
    if (num_input('loans') > 0) {
        foreach (db_all("SELECT * FROM loans WHERE employee_id = ? AND status = 'active'", [$e['id']]) as $l) {
            $left = max(0, (float)$l['principal'] - loan_paid((int)$l['id']));
            if ($left > 0) db_exec('INSERT IGNORE INTO loan_payments (loan_id, payslip_id, period, amount, created_at) VALUES (?, 0, ?, ?, ?)', [$l['id'], substr($last, 0, 7), $left, now()]);
            db_update('loans', ['status' => 'closed', 'closed_at' => now()], 'id = ?', [$l['id']]);
        }
    }
    if (input('deactivate')) {
        db_update('employees', ['status' => 'inactive'], 'id = ?', [$e['id']]);
        db_update('users', ['status' => 'suspended'], "employee_id = ? AND role = 'employee' AND status = 'active'", [$e['id']]);
    }
    log_activity((int)$c['id'], 'Final settlement ' . $doc['code'] . ' for ' . $e['name']);
    flash('success', sprintf(t('Final settlement %s issued. Net payable %s.'), $doc['code'], money($data['net'])));
    redirect('/document/' . $doc['code']);
}

/* ---------------- Tax certificates ---------------- */

function emp_tax_certificates(): void
{
    $c = my_company();
    require_feature($c, 'tax_certificate', '/app/documents');
    $year = (int)input('year');
    if ($year < 2000 || $year > (int)date('Y')) {
        flash('error', t('Choose a valid year.'));
        redirect('/app/documents');
    }
    [$from, $to, $label] = tax_year($c, $year);
    $emps = db_all("SELECT DISTINCT p.employee_id FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.company_id = ? AND p.status = 'issued' AND r.period BETWEEN ? AND ?", [$c['id'], $from, $to]);
    $n = 0;
    foreach ($emps as $row) {
        if (db_val("SELECT 1 FROM documents WHERE employee_id = ? AND type = 'tax_certificate' AND year = ? AND status = 'issued'", [$row['employee_id'], $year])) continue;
        if (is_array(document_issue($c, (int)$row['employee_id'], 'tax_certificate', ['year' => $year, 'purpose' => 'Income tax return ' . $label], (int)current_user()['id']))) $n++;
    }
    flash('success', sprintf(t('%s tax certificates issued for %s. Employees can download them from their portal.'), num($n), $label));
    redirect('/app/documents');
}

function me_tax_certificate(): void
{
    $e = me_employee();
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$e['company_id']]);
    if (!plan_allows($c, 'tax_certificate')) {
        flash('info', t('Ask your employer to issue your tax certificate.'));
        redirect('/me/documents');
    }
    $res = document_issue($c, (int)$e['id'], 'tax_certificate', ['year' => (int)input('year'), 'purpose' => 'Income tax return'], (int)current_user()['id']);
    if (!is_array($res)) {
        flash('error', $res);
        redirect('/me/documents');
    }
    redirect('/document/' . $res['code']);
}

/* ---------------- Company seal & signature ---------------- */

function emp_seal_save(): void
{
    $c = my_company();
    require_feature($c, 'stamp', '/app/settings');
    $data = [];
    foreach (['signature_img' => 'signature', 'stamp_img' => 'stamp'] as $col => $field) {
        if (input('remove_' . $field)) {
            $data[$col] = null;
            continue;
        }
        if (empty($_FILES[$field]['name'])) continue;
        $f = $_FILES[$field];
        $info = $f['error'] === UPLOAD_ERR_OK && $f['size'] <= 1024 * 1024 ? @getimagesize($f['tmp_name']) : false;
        $ext = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
        if (!$ext) {
            flash('error', t('Upload a PNG (best, with a transparent background), JPG or WebP under 1 MB.'));
            redirect('/app/settings#seal');
        }
        $name = $field . $c['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (sanitize_image($f['tmp_name'], ROOT . '/uploads/logos/' . $name, (int)$info[2], 500)) {
            // Old files stay: issued payslips and letters still point to them
            $data[$col] = $name;
        }
    }
    if ($data) db_update('companies', $data, 'id = ?', [$c['id']]);
    audit('Updated company seal / signature', (int)$c['id']);
    flash('success', t('Saved. New payslips and letters will show your seal and signature.'));
    redirect('/app/settings#seal');
}
