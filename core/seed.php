<?php
declare(strict_types=1);

/**
 * Creates a fictional demo company for reviewers and judges.
 * It never creates verification records, so platform usage numbers stay honest.
 */
function seed_demo(): void
{
    $email = 'demo@payslip.tech';
    if (db_val('SELECT 1 FROM users WHERE email = ?', [$email])) return;

    $cid = db_insert('companies', [
        'name' => 'Nodi Digital Ltd. (Demo)',
        'address' => "House 12, Road 5, Dhanmondi\nDhaka 1205, Bangladesh",
        'phone' => '+880 1700-000000',
        'email' => 'hr@example.com',
        'website' => 'https://example.com',
        'tin' => '000000000000',
        'signatory_name' => 'Farhana Akter',
        'signatory_title' => 'Head of HR & Finance',
        'payslip_lang' => 'en',
        'show_amounts' => 1,
        'status' => 'active',
        'created_at' => now(),
    ]);
    db_insert('users', ['role' => 'employer', 'company_id' => $cid, 'name' => 'Demo Admin', 'email' => $email, 'password_hash' => password_hash('demo12345', PASSWORD_DEFAULT), 'status' => 'active', 'lang' => 'en', 'created_at' => now()]);

    $people = [
        ['Rahim Uddin', 'Senior Software Engineer', 'Engineering', 'bank', 'Dutch-Bangla Bank', '1021034567890', 60000],
        ['Nusrat Jahan', 'Product Designer', 'Design', 'bank', 'BRAC Bank', '1501203344556', 45000],
        ['Tanvir Hasan', 'Frontend Developer', 'Engineering', 'bkash', 'bKash', '01711000001', 38000],
        ['Sadia Islam', 'HR Executive', 'Human Resources', 'bank', 'City Bank', '2302456789012', 32000],
        ['Mehedi Hasan', 'QA Engineer', 'Engineering', 'nagad', 'Nagad', '01811000002', 30000],
        ['Sharmin Sultana', 'Accounts Officer', 'Finance', 'bank', 'Eastern Bank', '1041122334455', 34000],
        ['Arif Hossain', 'Customer Support Lead', 'Support', 'bkash', 'bKash', '01911000003', 26000],
        ['Jannatul Ferdous', 'Marketing Associate', 'Marketing', 'bank', 'Islami Bank', '2050101234567', 28000],
    ];
    $emps = [];
    foreach ($people as $i => [$name, $des, $dept, $pm, $bank, $acc, $gross]) {
        // Typical BD structure: basic 60%, house rent 30%, medical 5%, conveyance 5%
        $basic = round($gross * 0.6);
        $row = [
            'company_id' => $cid, 'emp_code' => sprintf('ND-%03d', $i + 1), 'name' => $name, 'designation' => $des, 'department' => $dept,
            'email' => null, 'phone' => '+8801' . str_pad((string)(700000000 + $i * 1111), 9, '0', STR_PAD_LEFT),
            'join_date' => date('Y-m-d', strtotime('-' . (8 + $i * 5) . ' months')), 'payment_method' => $pm, 'bank_name' => $bank, 'account_no' => $acc,
            'basic' => $basic, 'house_rent' => round($gross * 0.3), 'medical' => round($gross * 0.05), 'conveyance' => $gross - $basic - round($gross * 0.3) - round($gross * 0.05),
            'other_allowance' => 0, 'tax' => $gross >= 45000 ? round($gross * 0.03) : 0, 'pf' => round($basic * 0.05), 'other_deduction' => 0,
            'status' => 'active', 'created_at' => now(),
        ];
        $row['id'] = db_insert('employees', $row);
        $emps[] = $row;
    }
    $company = db_one('SELECT * FROM companies WHERE id = ?', [$cid]);

    // Two finalised months + one draft
    $periods = [date('Y-m', strtotime('first day of -2 months')), date('Y-m', strtotime('first day of last month')), date('Y-m')];
    foreach ($periods as $idx => $period) {
        $final = $idx < 2;
        $rid = db_insert('payroll_runs', ['company_id' => $cid, 'period' => $period, 'pay_date' => date('Y-m-t', strtotime($period . '-01')), 'working_days' => 30, 'status' => $final ? 'finalized' : 'draft', 'finalized_at' => $final ? date('Y-m-d H:i:s', strtotime(date('Y-m-t', strtotime($period . '-01')) . ' 17:00:00')) : null, 'created_at' => now()]);
        $issuedAt = $final ? date('Y-m-d H:i:s', strtotime(date('Y-m-t', strtotime($period . '-01')) . ' 17:00:00')) : null;
        foreach ($emps as $j => $e) {
            $adj = default_adjustments();
            if ($j === 0 && $idx === 1) $adj['bonus'] = 15000;
            if ($j === 2) $adj['overtime'] = 2500;
            if ($j === 6 && $idx === 0) $adj['unpaid_days'] = 2;
            $calc = compute_slip($e, $adj, 30);
            $lines = ['earnings' => $calc['earnings'], 'deductions' => $calc['deductions'], 'working_days' => 30, 'paid_days' => $calc['paid_days'], 'note' => ''];
            $row = ['run_id' => $rid, 'company_id' => $cid, 'employee_id' => $e['id'], 'status' => 'draft', 'adjustments' => json_encode($adj), 'breakdown' => json_encode($lines), 'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'], 'created_at' => now()];
            if ($final) {
                $code = new_payslip_code();
                $snap = employee_snapshot($e, $company);
                $h = payslip_hash(['code' => $code, 'company_id' => $cid, 'snapshot' => $snap, 'breakdown' => $lines, 'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'], 'issued_at' => $issuedAt], $period);
                $row = array_merge($row, ['code' => $code, 'status' => 'issued', 'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'hash' => $h, 'issued_at' => $issuedAt]);
            }
            db_insert('payslips', $row);
        }
    }
    set_setting('demo_company_id', (string)$cid);
    set_setting('demo_email', $email);
    set_setting('demo_enabled', '1');
}

/**
 * Adds examples of the v1.5–v1.6 features to the demo company so visitors who
 * open the demo see documents, leave, loans and salary history straight away.
 * Safe to run more than once: it only fills what is missing.
 */
function enrich_demo(): void
{
    $cid = demo_cid();
    $c = $cid > 0 ? db_one('SELECT * FROM companies WHERE id = ?', [$cid]) : null;
    if (!$c) return;
    $emps = db_all("SELECT * FROM employees WHERE company_id = ? AND status = 'active' ORDER BY emp_code", [$cid]);
    if (count($emps) < 4) return;
    $owner = (int)db_val("SELECT id FROM users WHERE company_id = ? AND role = 'employer' ORDER BY id LIMIT 1", [$cid]);
    $types = [];
    foreach (leave_types($c) as $t) $types[$t['name']] = (int)$t['id'];
    if (!db_val('SELECT 1 FROM leave_requests WHERE company_id = ?', [$cid])) {
        $next = date('Y-m-d', strtotime('monday next week'));
        $ins = function (array $e, string $type, string $from, string $to, string $reason, string $status) use ($c, $owner, $types) {
            if (!isset($types[$type])) return;
            db_insert('leave_requests', ['company_id' => $c['id'], 'employee_id' => $e['id'], 'type_id' => $types[$type], 'start_date' => $from, 'end_date' => $to, 'days' => leave_working_days($c, $from, $to), 'reason' => $reason, 'status' => $status, 'requested_by' => $owner, 'decided_by' => $status === 'pending' ? null : $owner, 'decided_at' => $status === 'pending' ? null : now(), 'created_at' => now()]);
        };
        $ins($emps[1], 'Annual leave', $next, date('Y-m-d', strtotime($next . ' +2 days')), 'Family trip to Cox\'s Bazar', 'pending');
        $ins($emps[3], 'Sick leave', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-9 days')), 'Fever', 'approved');
        $ins($emps[2], 'Casual leave', date('Y-m-d', strtotime($next . ' +7 days')), date('Y-m-d', strtotime($next . ' +7 days')), 'Personal work', 'pending');
    }
    if (!db_val('SELECT 1 FROM loans WHERE company_id = ?', [$cid])) {
        db_insert('loans', ['company_id' => $cid, 'employee_id' => $emps[2]['id'], 'title' => 'Eid advance', 'principal' => 15000, 'installment' => 5000, 'start_period' => date('Y-m'), 'status' => 'active', 'created_by' => $owner, 'created_at' => now()]);
        sync_employee_drafts($c, (int)$emps[2]['id']);
    }
    if (!db_val('SELECT 1 FROM salary_changes WHERE company_id = ?', [$cid])) {
        $e = $emps[0];
        $new = salary_of($e);
        $old = array_map(fn($v) => round($v / 1.1, 2), $new);
        db_insert('salary_changes', ['company_id' => $cid, 'employee_id' => $e['id'], 'effective_period' => date('Y-m', strtotime('first day of -2 months')), 'old_salary' => json_encode($old), 'new_salary' => json_encode($new), 'reason' => 'Annual increment', 'status' => 'applied', 'created_by' => $owner, 'created_at' => now(), 'applied_at' => now()]);
    }
    if (!db_val("SELECT 1 FROM documents WHERE company_id = ? AND status = 'issued'", [$cid])) {
        document_issue($c, (int)$emps[0]['id'], 'salary_certificate', ['addressed_to' => 'The Visa Officer, Embassy of Italy, Dhaka', 'purpose' => 'Tourist visa application'], $owner);
        document_issue($c, (int)$emps[1]['id'], 'employment_letter', ['addressed_to' => 'City Bank PLC', 'purpose' => 'Home loan application'], $owner);
        document_issue($c, (int)$emps[3]['id'], 'income_statement', ['year' => (int)date('Y')], $owner);
        db_insert('documents', ['company_id' => $cid, 'employee_id' => $emps[4]['id'], 'type' => 'employment_letter', 'status' => 'requested', 'addressed_to' => 'Canadian High Commission', 'purpose' => 'Visitor visa', 'requested_by' => $owner, 'created_at' => now()]);
    }
}
