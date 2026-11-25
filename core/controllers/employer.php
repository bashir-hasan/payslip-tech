<?php
declare(strict_types=1);

function is_demo_company(array $c): bool
{
    return (string)$c['id'] === (string)setting('demo_company_id', '-1');
}

function emp_dashboard(): void
{
    $c = my_company();
    $cid = (int)$c['id'];
    $stats = [
        'employees' => (int)db_val("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'", [$cid]),
        'issued' => (int)db_val("SELECT COUNT(*) FROM payslips WHERE company_id = ? AND status IN ('issued','revoked')", [$cid]),
        'verifications' => (int)db_val('SELECT COUNT(*) FROM verifications WHERE company_id = ?', [$cid]),
        'verifications30' => (int)db_val('SELECT COUNT(*) FROM verifications WHERE company_id = ? AND created_at >= ?', [$cid, date('Y-m-d H:i:s', strtotime('-30 days'))]),
    ];
    $lastRun = db_one("SELECT r.*, (SELECT SUM(net) FROM payslips p WHERE p.run_id = r.id) AS total_net, (SELECT COUNT(*) FROM payslips p WHERE p.run_id = r.id) AS slips FROM payroll_runs r WHERE r.company_id = ? ORDER BY r.period DESC LIMIT 1", [$cid]);
    $runs = db_all("SELECT r.period, r.status, (SELECT SUM(net) FROM payslips p WHERE p.run_id = r.id) AS total_net FROM payroll_runs r WHERE r.company_id = ? ORDER BY r.period DESC LIMIT 6", [$cid]);
    $recentVer = db_all("SELECT v.*, p.snapshot FROM verifications v LEFT JOIN payslips p ON p.id = v.payslip_id WHERE v.company_id = ? ORDER BY v.id DESC LIMIT 6", [$cid]);
    $checklist = [
        ['done' => !empty($c['address']) && !empty($c['signatory_name']), 'label' => t('Complete your company profile'), 'url' => '/app/settings'],
        ['done' => $stats['employees'] > 0, 'label' => t('Add your employees'), 'url' => '/app/employees/new'],
        ['done' => (bool)$lastRun, 'label' => t('Run your first payroll'), 'url' => '/app/payroll'],
        ['done' => $stats['issued'] > 0, 'label' => t('Issue verified payslips'), 'url' => '/app/payroll'],
    ];
    render('app/dashboard', ['title' => t('Dashboard'), 'nav' => 'dashboard', 'c' => $c, 'stats' => $stats, 'lastRun' => $lastRun, 'runs' => array_reverse($runs), 'recentVer' => $recentVer, 'checklist' => $checklist], 'app');
}

/* ---------------- Employees ---------------- */

function emp_employees(): void
{
    $c = my_company();
    $q = (string)input('q');
    $status = (string)input('status', 'active');
    $sql = 'SELECT e.*, u.status AS portal_status FROM employees e LEFT JOIN users u ON u.employee_id = e.id AND u.role = \'employee\' WHERE e.company_id = ?';
    $params = [$c['id']];
    if (in_array($status, ['active', 'inactive'], true)) {
        $sql .= ' AND e.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (e.name LIKE ? OR e.emp_code LIKE ? OR e.designation LIKE ? OR e.department LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY e.status ASC, e.name ASC';
    $rows = db_all($sql, $params);
    $counts = db_one("SELECT SUM(status='active') AS active, SUM(status='inactive') AS inactive FROM employees WHERE company_id = ?", [$c['id']]);
    render('app/employees', ['title' => t('Employees'), 'nav' => 'employees', 'rows' => $rows, 'q' => $q, 'status' => $status, 'counts' => $counts], 'app');
}

function emp_employee_form(?string $id = null): void
{
    $c = my_company();
    $e = null;
    if ($id !== null) {
        $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
        if (!$e) abort(404);
    } else {
        $next = (int)db_val('SELECT COUNT(*) FROM employees WHERE company_id = ?', [$c['id']]) + 1;
        $e = ['id' => null, 'emp_code' => sprintf('EMP-%03d', $next), 'payment_method' => 'bank'];
    }
    $history = $e['id'] ? db_all("SELECT p.code, p.net, p.status, r.period FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status IN ('issued','revoked') ORDER BY r.period DESC LIMIT 12", [$e['id']]) : [];
    render('app/employee_form', ['title' => $e['id'] ? t('Edit employee') : t('Add employee'), 'nav' => 'employees', 'e' => $e, 'history' => $history], 'app');
}

function emp_employee_save(): void
{
    $c = my_company();
    $id = (int)input('id');
    $existing = $id ? db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [$id, $c['id']]) : null;
    if ($id && !$existing) abort(404);

    $data = [
        'emp_code' => mb_substr((string)input('emp_code'), 0, 60),
        'name' => mb_substr((string)input('name'), 0, 190),
        'designation' => mb_substr((string)input('designation'), 0, 190),
        'department' => mb_substr((string)input('department'), 0, 190),
        'email' => mb_strtolower(mb_substr((string)input('email'), 0, 190)) ?: null,
        'phone' => mb_substr((string)input('phone'), 0, 60) ?: null,
        'join_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)input('join_date')) ? (string)input('join_date') : null,
        'payment_method' => array_key_exists((string)input('payment_method'), payment_methods_for($c['country'] ?? 'BD')) ? (string)input('payment_method') : 'bank',
        'bank_name' => mb_substr((string)input('bank_name'), 0, 190) ?: null,
        'account_no' => mb_substr((string)input('account_no'), 0, 80) ?: null,
    ];
    foreach (['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'tax', 'pf', 'other_deduction'] as $k) $data[$k] = num_input($k);

    // Custom fields defined by the platform admin
    $custom = $existing && $existing['custom'] ? (json_decode((string)$existing['custom'], true) ?: []) : [];
    $customErrors = [];
    foreach (custom_fields() as $f) {
        $val = mb_substr(trim(strip_tags((string)($_POST['cf_' . $f['key']] ?? ''))), 0, 190);
        if ($f['type'] === 'number' && $val !== '' && !is_numeric(bn_to_en_digits(str_replace(',', '', $val)))) $customErrors[] = sprintf(t('%s must be a number.'), $f['label']);
        if ($f['type'] === 'date' && $val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) $val = '';
        if ($f['type'] === 'select' && $val !== '' && !in_array($val, array_map('trim', explode(',', $f['options'])), true)) $val = '';
        if (!empty($f['required']) && $val === '') $customErrors[] = sprintf(t('%s is required.'), custom_field_label($f));
        $custom[$f['key']] = $val;
    }
    $data['custom'] = json_encode($custom, JSON_UNESCAPED_UNICODE);

    set_old($_POST);
    $errors = $customErrors;
    if ($data['emp_code'] === '') $errors[] = t('Employee ID is required.');
    if (mb_strlen($data['name']) < 2) $errors[] = t('Employee name is required.');
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('Enter a valid email address.');
    if ($data['basic'] <= 0) $errors[] = t('Basic salary must be greater than zero.');
    $dup = db_val('SELECT id FROM employees WHERE company_id = ? AND emp_code = ? AND id <> ?', [$c['id'], $data['emp_code'], $id]);
    if ($dup) $errors[] = t('Another employee already uses this Employee ID.');
    if ($errors) {
        foreach ($errors as $er) flash('error', $er);
        redirect($id ? '/app/employees/' . $id : '/app/employees/new');
    }
    clear_old();
    if ($existing) {
        db_update('employees', $data, 'id = ? AND company_id = ?', [$id, $c['id']]);
        log_activity((int)$c['id'], 'Updated employee ' . $data['name']);
        flash('success', t('Employee updated.'));
    } else {
        if (!within_employee_limit($c)) {
            flash('error', sprintf(t('Your %s plan allows %d active employees. Upgrade to add more.'), company_plan($c)['name'], employee_limit($c)));
            redirect('/app/billing');
        }
        $data['company_id'] = $c['id'];
        $data['created_at'] = now();
        $id = db_insert('employees', $data);
        log_activity((int)$c['id'], 'Added employee ' . $data['name']);
        flash('success', t('Employee added.'));
    }
    redirect(input('after') === 'new' ? '/app/employees/new' : '/app/employees');
}

function emp_employee_status(string $id): void
{
    $c = my_company();
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    $new = $e['status'] === 'active' ? 'inactive' : 'active';
    if ($new === 'active' && !within_employee_limit($c)) {
        flash('error', sprintf(t('Your %s plan allows %d active employees. Upgrade to add more.'), company_plan($c)['name'], employee_limit($c)));
        redirect('/app/billing');
    }
    db_update('employees', ['status' => $new], 'id = ?', [$e['id']]);
    db_update('users', ['status' => $new === 'active' ? 'active' : 'suspended'], "employee_id = ? AND role = 'employee' AND status <> 'invited'", [$e['id']]);
    flash('success', $new === 'active' ? t('Employee reactivated.') : t('Employee deactivated. They will not be included in new payroll runs.'));
    back('/app/employees');
}

function emp_employee_invite(string $id): void
{
    $c = my_company();
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$e) abort(404);
    if (!$e['email']) {
        flash('error', t('Add an email address for this employee first.'));
        redirect('/app/employees/' . $e['id']);
    }
    $u = db_one('SELECT * FROM users WHERE email = ?', [$e['email']]);
    if ($u && ((int)$u['employee_id'] !== (int)$e['id'])) {
        flash('error', t('This email is already used by another account.'));
        back('/app/employees');
    }
    if (!$u) {
        $uid = db_insert('users', ['role' => 'employee', 'company_id' => $c['id'], 'employee_id' => $e['id'], 'name' => $e['name'], 'email' => $e['email'], 'status' => 'invited', 'lang' => $c['payslip_lang'] === 'bn' ? 'bn' : 'en', 'created_at' => now()]);
    } else {
        $uid = (int)$u['id'];
    }
    $token = issue_token($uid, 24 * 7);
    $link = abs_url('/invite/' . $token);
    $sent = send_mail($e['email'], sprintf(t('%s invited you to view your payslips'), $c['name']), sprintf(t("Hello %s,\n\n%s uses %s to issue verified payslips. Activate your account to view and download your payslips. This link is valid for 7 days."), $e['name'], $c['name'], site_name()), $link, t('Activate account'));
    $_SESSION['_invite_link'] = ['name' => $e['name'], 'link' => $link, 'sent' => $sent];
    log_activity((int)$c['id'], 'Invited ' . $e['name'] . ' to the employee portal');
    back('/app/employees');
}

function emp_import_form(): void
{
    my_company();
    render('app/import', ['title' => t('Smart import'), 'nav' => 'import'], 'app');
}

/* ---------------- Payroll ---------------- */

function emp_payroll(): void
{
    $c = my_company();
    $runs = db_all("SELECT r.*, COUNT(p.id) AS slips, COALESCE(SUM(p.gross),0) AS total_gross, COALESCE(SUM(p.net),0) AS total_net FROM payroll_runs r LEFT JOIN payslips p ON p.run_id = r.id WHERE r.company_id = ? GROUP BY r.id ORDER BY r.period DESC", [$c['id']]);
    $active = (int)db_val("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'", [$c['id']]);
    $existing = array_column($runs, 'period');
    $suggest = date('Y-m');
    if (in_array($suggest, $existing, true)) $suggest = date('Y-m', strtotime('first day of next month'));
    render('app/payroll', ['title' => t('Payroll'), 'nav' => 'payroll', 'runs' => $runs, 'active' => $active, 'suggest' => $suggest], 'app');
}

function emp_payroll_create(): void
{
    $c = my_company();
    $period = (string)input('period');
    $res = payroll_create_run($c, $period, (int)input('working_days', '30'), (string)input('pay_date') ?: null, (int)current_user()['id']);
    if (!is_int($res)) {
        flash('error', $res);
        redirect(str_contains($res, 'employee') ? '/app/employees/new' : '/app/payroll');
    }
    $n = (int)db_val('SELECT COUNT(*) FROM payslips WHERE run_id = ?', [$res]);
    log_activity((int)$c['id'], 'Started payroll for ' . period_label($period, 'en'));
    flash('success', sprintf(t('Draft payroll created for %d employees. Review and finalise it.'), $n));
    redirect('/app/payroll/' . $res);
}

function load_run(int $id, array $c): array
{
    $r = db_one('SELECT * FROM payroll_runs WHERE id = ? AND company_id = ?', [$id, $c['id']]);
    if (!$r) abort(404);
    return $r;
}

function emp_run(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    $slips = db_all('SELECT p.*, e.name, e.emp_code, e.designation, e.department, e.payment_method, e.email FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? ORDER BY e.name', [$run['id']]);
    foreach ($slips as &$s) {
        $s['adjustments'] = json_decode((string)$s['adjustments'], true) ?: default_adjustments();
        $s['breakdown'] = json_decode((string)$s['breakdown'], true) ?: [];
    }
    unset($s);
    $totals = ['gross' => array_sum(array_column($slips, 'gross')), 'ded' => array_sum(array_column($slips, 'total_deductions')), 'net' => array_sum(array_column($slips, 'net'))];
    render('app/run', ['title' => sprintf(t('Payroll — %s'), period_label($run['period'])), 'nav' => 'payroll', 'run' => $run, 'slips' => $slips, 'totals' => $totals], 'app');
}

function emp_run_slip_save(string $runId, string $slipId): void
{
    $c = my_company();
    $run = load_run((int)$runId, $c);
    $err = payroll_adjust_slip($run, (int)$slipId, [
        'overtime' => num_input('overtime'), 'bonus' => num_input('bonus'), 'loan' => num_input('loan'), 'other' => num_input('other'),
        'unpaid_days' => num_input('unpaid_days'), 'note' => (string)input('note'),
    ]);
    if ($err) {
        flash('error', $err);
        redirect('/app/payroll/' . $run['id']);
    }
    $name = db_val('SELECT e.name FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?', [(int)$slipId]);
    flash('success', sprintf(t('Updated %s.'), $name));
    redirect('/app/payroll/' . $run['id'] . '#slip-' . (int)$slipId);
}

function emp_run_finalize(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    if ($run['status'] !== 'draft') redirect('/app/payroll/' . $run['id']);
    $n = payroll_finalize_run($c, $run);
    log_activity((int)$c['id'], 'Finalised payroll for ' . period_label($run['period'], 'en') . ' (' . $n . ' payslips)');
    flash('success', sprintf(t('%d verified payslips issued. Each one now has its own QR code.'), $n));
    redirect('/app/payroll/' . $run['id']);
}

function emp_run_delete(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    if ($run['status'] !== 'draft') {
        flash('error', t('Finalised payroll cannot be deleted. Revoke individual payslips instead.'));
        redirect('/app/payroll/' . $run['id']);
    }
    db_exec('DELETE FROM payslips WHERE run_id = ?', [$run['id']]);
    db_exec('DELETE FROM payroll_runs WHERE id = ?', [$run['id']]);
    flash('success', t('Draft payroll deleted.'));
    redirect('/app/payroll');
}

function emp_run_email(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    if ($run['status'] !== 'finalized') redirect('/app/payroll/' . $run['id']);
    if (!rate_limit('runmail:' . $run['id'], 3, 3600)) {
        flash('error', t('Payslips for this month were emailed recently. Please wait before sending again.'));
        redirect('/app/payroll/' . $run['id']);
    }
    [$sent, $total] = payroll_email_run($c, $run);
    flash($sent ? 'success' : 'error', $sent ? sprintf(t('Emailed %d of %d employees.'), $sent, $total) : t('No emails could be sent. Check that employees have email addresses and that your hosting allows sending mail.'));
    redirect('/app/payroll/' . $run['id']);
}

function emp_run_export(string $id): void
{
    $c = my_company();
    $run = load_run((int)$id, $c);
    $rows = db_all('SELECT p.*, e.emp_code, e.name, e.designation, e.payment_method, e.bank_name, e.account_no, e.phone FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? ORDER BY e.name', [$run['id']]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payroll-' . $run['period'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Employee ID', 'Name', 'Designation', 'Payment method', 'Bank / wallet', 'Account / wallet no.', 'Phone', 'Gross (BDT)', 'Deductions (BDT)', 'Net pay (BDT)', 'Payslip code', 'Status']);
    foreach ($rows as $r) {
        $acc = (string)$r['account_no'];
        // Prevent spreadsheet formula injection
        $safe = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v;
        fputcsv($out, array_map($safe, [$r['emp_code'], $r['name'], $r['designation'], payment_method_label($r['payment_method'], 'en'), $r['bank_name'], $acc, $r['phone'], number_format((float)$r['gross'], 2, '.', ''), number_format((float)$r['total_deductions'], 2, '.', ''), number_format((float)$r['net'], 2, '.', ''), $r['code'] ?? '', $r['status']]));
    }
    fclose($out);
    exit;
}

/* ---------------- Payslips & verifications ---------------- */

function emp_payslips(): void
{
    $c = my_company();
    $q = (string)input('q');
    $period = (string)input('period');
    $sql = "SELECT p.*, r.period, e.name, e.emp_code, (SELECT COUNT(*) FROM verifications v WHERE v.payslip_id = p.id) AS checks FROM payslips p JOIN payroll_runs r ON r.id = p.run_id JOIN employees e ON e.id = p.employee_id WHERE p.company_id = ? AND p.status IN ('issued','revoked')";
    $params = [$c['id']];
    if ($q !== '') {
        $sql .= ' AND (e.name LIKE ? OR p.code LIKE ? OR e.emp_code LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if (preg_match('/^\d{4}-\d{2}$/', $period)) {
        $sql .= ' AND r.period = ?';
        $params[] = $period;
    }
    $sql .= ' ORDER BY r.period DESC, e.name ASC LIMIT 500';
    $rows = db_all($sql, $params);
    $periods = db_all("SELECT period FROM payroll_runs WHERE company_id = ? AND status = 'finalized' ORDER BY period DESC", [$c['id']]);
    render('app/payslips', ['title' => t('Payslips'), 'nav' => 'payslips', 'rows' => $rows, 'q' => $q, 'period' => $period, 'periods' => array_column($periods, 'period')], 'app');
}

function emp_payslip_revoke(string $id): void
{
    $c = my_company();
    $p = db_one("SELECT * FROM payslips WHERE id = ? AND company_id = ? AND status = 'issued'", [(int)$id, $c['id']]);
    if (!$p) abort(404);
    $reason = mb_substr((string)input('reason'), 0, 255);
    if (mb_strlen($reason) < 3) {
        flash('error', t('Please give a reason for revoking.'));
        back('/app/payslips');
    }
    db_update('payslips', ['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => $reason], 'id = ?', [$p['id']]);
    log_activity((int)$c['id'], 'Revoked payslip ' . $p['code']);
    flash('success', sprintf(t('Payslip %s revoked. Verifiers will now see it as revoked.'), $p['code']));
    back('/app/payslips');
}

function emp_verifications(): void
{
    $c = my_company();
    $rows = db_all('SELECT v.*, COALESCE(p.snapshot, d.snapshot) AS snapshot, r.period, d.type AS doc_type FROM verifications v LEFT JOIN payslips p ON p.id = v.payslip_id LEFT JOIN payroll_runs r ON r.id = p.run_id LEFT JOIN documents d ON d.id = v.document_id WHERE v.company_id = ? ORDER BY v.id DESC LIMIT 300', [$c['id']]);
    render('app/verifications', ['title' => t('Verification log'), 'nav' => 'verifications', 'rows' => $rows], 'app');
}

/* ---------------- Settings ---------------- */

function emp_settings(): void
{
    $c = my_company();
    render('app/settings', ['title' => t('Company settings'), 'nav' => 'settings', 'c' => $c, 'demo' => is_demo_company($c)], 'app');
}

function emp_settings_save(): void
{
    $c = my_company();
    if (is_demo_company($c)) {
        flash('info', t('Company settings are locked in the demo account.'));
        redirect('/app/settings');
    }
    $data = [];
    foreach (['name' => 190, 'phone' => 60, 'email' => 190, 'website' => 190, 'tin' => 60, 'signatory_name' => 190, 'signatory_title' => 190] as $k => $max) {
        $data[$k] = mb_substr((string)input($k), 0, $max) ?: null;
    }
    $data['address'] = mb_substr((string)input('address'), 0, 500) ?: null;
    $data['payslip_lang'] = in_array(input('payslip_lang'), ['en', 'bn'], true) ? (string)input('payslip_lang') : 'en';
    $country = strtoupper((string)input('country'));
    $data['country'] = isset(countries()[$country]) ? $country : ($c['country'] ?? 'BD');
    $cur = strtoupper((string)input('currency'));
    $data['currency'] = isset(CURRENCIES[$cur]) ? $cur : countries()[$data['country']][1];
    if (input('reset_labels') || $data['country'] !== ($c['country'] ?? '')) {
        $data['labels'] = null; // use the new country's local terms
    } else {
        $preset = company_labels(['country' => $data['country'], 'labels' => null]);
        $custom = [];
        foreach (array_keys($preset) as $k) {
            $v = mb_substr(trim(strip_tags((string)input('label_' . $k))), 0, 60);
            if ($v !== '' && $v !== $preset[$k]) $custom[$k] = $v;
        }
        $data['labels'] = $custom ? json_encode($custom, JSON_UNESCAPED_UNICODE) : null;
    }
    $data['show_amounts'] = input('show_amounts') ? 1 : 0;
    if (!$data['name']) {
        flash('error', t('Company name is required.'));
        redirect('/app/settings');
    }
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $data['email'] = null;

    if (!empty($_FILES['logo']['name'])) {
        $f = $_FILES['logo'];
        $info = $f['error'] === UPLOAD_ERR_OK && $f['size'] <= 1024 * 1024 ? @getimagesize($f['tmp_name']) : false;
        $ext = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
        if (!$ext) {
            flash('error', t('Logo must be a PNG, JPG or WebP image under 1 MB.'));
            redirect('/app/settings');
        }
        $name = 'c' . $c['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (sanitize_image($f['tmp_name'], ROOT . '/uploads/logos/' . $name, (int)$info[2])) {
            if ($c['logo'] && is_file(ROOT . '/uploads/logos/' . basename($c['logo']))) @unlink(ROOT . '/uploads/logos/' . basename($c['logo']));
            $data['logo'] = $name;
        }
    } elseif (input('remove_logo')) {
        $data['logo'] = null;
    }
    db_update('companies', $data, 'id = ?', [$c['id']]);
    log_activity((int)$c['id'], 'Updated company settings');
    flash('success', t('Company settings saved. New payslips will use these details.'));
    redirect('/app/settings');
}
