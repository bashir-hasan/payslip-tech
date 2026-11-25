<?php
declare(strict_types=1);

const EARNING_KEYS = ['basic' => 'Basic Salary', 'house_rent' => 'House Rent', 'medical' => 'Medical Allowance', 'conveyance' => 'Conveyance', 'other_allowance' => 'Other Allowance', 'overtime' => 'Overtime', 'bonus' => 'Bonus'];
const DEDUCTION_KEYS = ['tax' => 'Income Tax (TDS)', 'pf' => 'Provident Fund', 'loan' => 'Loan / Advance', 'absence' => 'Absence Deduction', 'other' => 'Other Deduction'];

function default_adjustments(): array
{
    return ['overtime' => 0, 'bonus' => 0, 'loan' => 0, 'other' => 0, 'unpaid_days' => 0, 'note' => ''];
}

/**
 * Compute a payslip from the employee salary structure and monthly adjustments.
 * Returns ['earnings'=>[], 'deductions'=>[], 'gross', 'total_deductions', 'net', 'working_days', 'paid_days'].
 */
function compute_slip(array $emp, array $adj, int $workingDays): array
{
    $adj = array_merge(default_adjustments(), $adj);
    $workingDays = max(1, $workingDays);
    $unpaid = max(0, min($workingDays, (float)$adj['unpaid_days']));

    $fixed = [
        'basic' => (float)$emp['basic'],
        'house_rent' => (float)$emp['house_rent'],
        'medical' => (float)$emp['medical'],
        'conveyance' => (float)$emp['conveyance'],
        'other_allowance' => (float)$emp['other_allowance'],
    ];
    $earnings = $fixed + ['overtime' => (float)$adj['overtime'], 'bonus' => (float)$adj['bonus']];
    $fixedTotal = array_sum($fixed);
    $absence = round($fixedTotal / $workingDays * $unpaid, 2);

    $deductions = [
        'tax' => (float)$emp['tax'],
        'pf' => (float)$emp['pf'],
        'loan' => (float)$adj['loan'],
        'absence' => $absence,
        'other' => round((float)$emp['other_deduction'] + (float)$adj['other'], 2),
    ];

    $earnings = array_map(fn($v) => round($v, 2), $earnings);
    $gross = round(array_sum($earnings), 2);
    $totalDed = round(array_sum($deductions), 2);
    return [
        'earnings' => $earnings,
        'deductions' => $deductions,
        'gross' => $gross,
        'total_deductions' => $totalDed,
        'net' => round($gross - $totalDed, 2),
        'working_days' => $workingDays,
        'paid_days' => $workingDays - $unpaid,
    ];
}

/** Custom field values that the admin marked "show on payslip", with labels in both languages. */
function payslip_custom_values(array $emp): array
{
    $vals = json_decode((string)($emp['custom'] ?? ''), true) ?: [];
    $out = [];
    foreach (custom_fields() as $f) {
        if (empty($f['on_payslip'])) continue;
        $v = trim((string)($vals[$f['key']] ?? ''));
        if ($v === '') continue;
        $out[] = ['label' => $f['label'], 'label_bn' => $f['label_bn'] ?? '', 'type' => $f['type'], 'value' => $v];
    }
    return $out;
}

function employee_snapshot(array $emp, array $company): array
{
    return [
        'employee' => [
            'emp_code' => $emp['emp_code'],
            'name' => $emp['name'],
            'designation' => $emp['designation'],
            'department' => $emp['department'],
            'join_date' => $emp['join_date'],
            'payment_method' => $emp['payment_method'],
            'bank_name' => $emp['bank_name'],
            'account_masked' => mask_account($emp['account_no']),
            'custom' => payslip_custom_values($emp),
        ],
        'company' => [
            'name' => $company['name'],
            'address' => $company['address'],
            'phone' => $company['phone'],
            'email' => $company['email'],
            'website' => $company['website'],
            'tin' => $company['tin'],
            'logo' => $company['logo'],
            'signatory_name' => $company['signatory_name'],
            'signatory_title' => $company['signatory_title'],
            'country' => $company['country'] ?? 'BD',
            'currency' => $company['currency'] ?? 'BDT',
            'labels' => company_labels($company),
        ],
        'hv' => 2,
    ];
}

/** Canonical string that the fingerprint signs. Any change to these values breaks verification. */
function payslip_canonical(array $p, string $period): string
{
    $snap = is_array($p['snapshot']) ? $p['snapshot'] : json_decode((string)$p['snapshot'], true);
    $lines = is_array($p['breakdown']) ? $p['breakdown'] : json_decode((string)$p['breakdown'], true);
    return implode('|', [
        'v1',
        $p['code'],
        (int)$p['company_id'],
        $snap['company']['name'] ?? '',
        $snap['employee']['emp_code'] ?? '',
        $snap['employee']['name'] ?? '',
        $snap['employee']['designation'] ?? '',
        $period,
        number_format((float)$p['gross'], 2, '.', ''),
        number_format((float)$p['total_deductions'], 2, '.', ''),
        number_format((float)$p['net'], 2, '.', ''),
        _canon_lines($lines['earnings'] ?? []),
        _canon_lines($lines['deductions'] ?? []),
        $p['issued_at'],
    ]) . ((int)($snap['hv'] ?? 1) >= 2 ? '|' . ($snap['company']['currency'] ?? '') : '');
}

function _canon_lines(array $lines): string
{
    ksort($lines);
    $out = [];
    foreach ($lines as $k => $v) $out[] = $k . '=' . number_format((float)$v, 2, '.', '');
    return implode(',', $out);
}

function payslip_hash(array $p, string $period): string
{
    return hash_hmac('sha256', payslip_canonical($p, $period), (string)cfg('app_key'));
}

function fingerprint_display(?string $hash): string
{
    if (!$hash) return '—';
    return strtoupper(implode(' ', str_split(substr($hash, 0, 16), 4)));
}

function new_payslip_code(): string
{
    do {
        $code = 'PS-' . random_code(4) . '-' . random_code(4);
    } while (db_val('SELECT 1 FROM payslips WHERE code = ?', [$code]));
    return $code;
}

function verify_url(string $code): string
{
    return abs_url('/v/' . $code);
}

/** Look up a payslip by code and evaluate its integrity. */
function verify_lookup(string $code): array
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9-]/', '', $code);
    if (!preg_match('/^PS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code)) {
        return ['result' => 'not_found', 'code' => $code];
    }
    $p = db_one('SELECT p.*, r.period, r.pay_date, c.status AS company_status, c.show_amounts FROM payslips p JOIN payroll_runs r ON r.id = p.run_id JOIN companies c ON c.id = p.company_id WHERE p.code = ?', [$code]);
    if (!$p || !in_array($p['status'], ['issued', 'revoked'], true)) {
        return ['result' => 'not_found', 'code' => $code];
    }
    $p['snapshot'] = json_decode((string)$p['snapshot'], true);
    $p['breakdown'] = json_decode((string)$p['breakdown'], true);
    currency_context(snapshot_currency($p['snapshot'] ?: []));
    if (!hash_equals((string)$p['hash'], payslip_hash($p, $p['period']))) {
        return ['result' => 'tampered', 'code' => $code, 'payslip' => $p];
    }
    if ($p['status'] === 'revoked') {
        return ['result' => 'revoked', 'code' => $code, 'payslip' => $p];
    }
    return ['result' => 'valid', 'code' => $code, 'payslip' => $p];
}

function log_verification(array $res, string $channel, ?string $verifierType = null): void
{
    db_insert('verifications', [
        'payslip_id' => $res['payslip']['id'] ?? null,
        'company_id' => $res['payslip']['company_id'] ?? null,
        'code_queried' => substr((string)$res['code'], 0, 40),
        'result' => $res['result'],
        'verifier_type' => $verifierType ? substr($verifierType, 0, 30) : null,
        'channel' => $channel,
        'country' => client_country(),
        'ip' => client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'created_at' => now(),
    ]);
}

function snapshot_currency(array $snap): string
{
    return $snap['company']['currency'] ?? 'BDT';
}

function snapshot_labels(array $snap): array
{
    return $snap['company']['labels'] ?? label_presets()['south_asia'];
}

function verifier_types(): array
{
    return ['bank' => t('Bank / financial institution'), 'embassy' => t('Embassy / visa office'), 'landlord' => t('Landlord / property'), 'employer' => t('New employer / HR'), 'other' => t('Other')];
}

/* ============================================================
 * Payroll operations (used by the UI, the auto-payroll job and the smart assistant)
 * ============================================================ */

/** Creates a draft run. Returns the run id, or an error string. */
function payroll_create_run(array $c, string $period, int $workingDays = 30, ?string $payDate = null, ?int $userId = null)
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) return t('Choose a valid month.');
    if ($workingDays < 1 || $workingDays > 31) $workingDays = 30;
    if (db_val('SELECT 1 FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $period])) return t('A payroll run already exists for this month.');
    $emps = db_all("SELECT * FROM employees WHERE company_id = ? AND status = 'active' ORDER BY name", [$c['id']]);
    if (!$emps) return t('Add at least one active employee before running payroll.');
    $payDate = $payDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate) ? $payDate : date('Y-m-t', strtotime($period . '-01'));
    db()->beginTransaction();
    $rid = db_insert('payroll_runs', ['company_id' => $c['id'], 'period' => $period, 'pay_date' => $payDate, 'working_days' => $workingDays, 'created_by' => $userId, 'created_at' => now()]);
    foreach ($emps as $e) {
        $adj = default_adjustments();
        $calc = compute_slip($e, $adj, $workingDays);
        db_insert('payslips', [
            'run_id' => $rid, 'company_id' => $c['id'], 'employee_id' => $e['id'], 'status' => 'draft',
            'adjustments' => json_encode($adj), 'breakdown' => json_encode(['earnings' => $calc['earnings'], 'deductions' => $calc['deductions'], 'working_days' => $calc['working_days'], 'paid_days' => $calc['paid_days']]),
            'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'], 'created_at' => now(),
        ]);
    }
    db()->commit();
    return $rid;
}

/** Saves monthly adjustments on one draft payslip. Returns null on success or an error string. */
function payroll_adjust_slip(array $run, int $slipId, array $changes): ?string
{
    if ($run['status'] !== 'draft') return t('This payroll is finalised and can no longer be edited.');
    $s = db_one('SELECT * FROM payslips WHERE id = ? AND run_id = ?', [$slipId, $run['id']]);
    if (!$s) return t('Payslip not found.');
    $e = db_one('SELECT * FROM employees WHERE id = ?', [$s['employee_id']]);
    $adj = array_merge(default_adjustments(), json_decode((string)$s['adjustments'], true) ?: [], $changes);
    foreach (['overtime', 'bonus', 'loan', 'other', 'unpaid_days'] as $k) $adj[$k] = round(max(0, (float)$adj[$k]), 2);
    $adj['unpaid_days'] = min((float)$run['working_days'], $adj['unpaid_days']);
    $adj['note'] = mb_substr((string)$adj['note'], 0, 200);
    $calc = compute_slip($e, $adj, (int)$run['working_days']);
    if ($calc['net'] < 0) return t('Deductions cannot be larger than earnings.');
    db_update('payslips', [
        'adjustments' => json_encode($adj, JSON_UNESCAPED_UNICODE),
        'breakdown' => json_encode(['earnings' => $calc['earnings'], 'deductions' => $calc['deductions'], 'working_days' => $calc['working_days'], 'paid_days' => $calc['paid_days']]),
        'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'],
    ], 'id = ?', [$s['id']]);
    return null;
}

/** Locks a draft run and issues verifiable payslips. Returns the number issued. */
function payroll_finalize_run(array $c, array $run): int
{
    if ($run['status'] !== 'draft') return 0;
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$c['id']]) ?: $c;
    $slips = db_all("SELECT * FROM payslips WHERE run_id = ? AND status = 'draft'", [$run['id']]);
    $pdo = db();
    $pdo->beginTransaction();
    $issuedAt = now();
    foreach ($slips as $s) {
        $e = db_one('SELECT * FROM employees WHERE id = ?', [$s['employee_id']]);
        $adj = json_decode((string)$s['adjustments'], true) ?: default_adjustments();
        $calc = compute_slip($e, $adj, (int)$run['working_days']);
        $lines = ['earnings' => $calc['earnings'], 'deductions' => $calc['deductions'], 'working_days' => $calc['working_days'], 'paid_days' => $calc['paid_days'], 'note' => $adj['note'] ?? ''];
        $row = ['code' => new_payslip_code(), 'company_id' => $c['id'], 'snapshot' => employee_snapshot($e, $c), 'breakdown' => $lines, 'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'], 'issued_at' => $issuedAt];
        $hash = payslip_hash($row, $run['period']);
        db_update('payslips', [
            'code' => $row['code'], 'status' => 'issued', 'snapshot' => json_encode($row['snapshot'], JSON_UNESCAPED_UNICODE), 'breakdown' => json_encode($lines, JSON_UNESCAPED_UNICODE),
            'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net'], 'hash' => $hash, 'issued_at' => $issuedAt,
        ], 'id = ?', [$s['id']]);
    }
    db_update('payroll_runs', ['status' => 'finalized', 'finalized_at' => $issuedAt], 'id = ?', [$run['id']]);
    $pdo->commit();
    return count($slips);
}

/** Emails every employee (with an email address) a link to their payslip. Returns [sent, total]. */
function payroll_email_run(array $c, array $run): array
{
    $slips = db_all("SELECT p.code, e.name, e.email FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? AND p.status = 'issued' AND e.email IS NOT NULL AND e.email <> ''", [$run['id']]);
    $sent = 0;
    $label = period_label($run['period'], 'en');
    foreach ($slips as $s) {
        $ok = send_mail($s['email'], sprintf(t('Your payslip for %s', 'en'), $label) . ' — ' . $c['name'], sprintf(t("Hello %s,\n\nYour payslip for %s from %s is ready. You can view, download and share it using the link below. Anyone you share it with can confirm it is genuine by scanning its QR code.\n\nVerification code: %s", 'en'), $s['name'], $label, $c['name'], $s['code']), verify_url($s['code']), t('View payslip', 'en'));
        if ($ok) $sent++;
    }
    return [$sent, count($slips)];
}
