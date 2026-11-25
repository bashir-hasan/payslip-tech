<?php
declare(strict_types=1);

/* ============================================================
 * Loans / salary advances with monthly instalments,
 * leave types, requests and balances.
 * Both feed into payroll drafts automatically.
 * ============================================================ */

/** ISO weekday numbers (1 = Monday … 7 = Sunday) that are weekly holidays. */
function company_weekend(array $c): array
{
    $w = trim((string)($c['weekend'] ?? ''));
    if ($w === '') {
        $w = match ($c['country'] ?? 'BD') {
            'BD' => '5',
            'AE', 'SA', 'QA', 'KW', 'BH', 'OM', 'EG', 'JO' => '5,6',
            'NP' => '6',
            default => '6,7',
        };
    }
    return array_values(array_filter(array_map('intval', explode(',', $w)), fn($d) => $d >= 1 && $d <= 7));
}

function weekday_names(): array
{
    return [1 => t('Monday'), 2 => t('Tuesday'), 3 => t('Wednesday'), 4 => t('Thursday'), 5 => t('Friday'), 6 => t('Saturday'), 7 => t('Sunday')];
}

/** Working days between two dates (inclusive), skipping the weekly holidays. Optional clamp to a month. */
function leave_working_days(array $c, string $from, string $to, ?string $period = null): float
{
    $a = strtotime($from);
    $b = strtotime($to);
    if (!$a || !$b || $b < $a) return 0;
    if ($period) {
        $a = max($a, strtotime($period . '-01'));
        $b = min($b, strtotime(date('Y-m-t', strtotime($period . '-01'))));
        if ($b < $a) return 0;
    }
    $weekend = company_weekend($c);
    $n = 0;
    for ($d = $a; $d <= $b; $d = strtotime('+1 day', $d)) {
        if (!in_array((int)date('N', $d), $weekend, true)) $n++;
    }
    return (float)$n;
}

/* ---------------- Loans ---------------- */

function loan_paid(int $loanId): float
{
    return (float)db_val('SELECT COALESCE(SUM(amount),0) FROM loan_payments WHERE loan_id = ?', [$loanId]);
}

/** Instalments due from one employee for a pay month: [loan_id => amount]. */
function loans_due(int $employeeId, string $period): array
{
    $out = [];
    foreach (db_all("SELECT * FROM loans WHERE employee_id = ? AND status = 'active' AND start_period <= ? ORDER BY id", [$employeeId, $period]) as $l) {
        $left = round((float)$l['principal'] - loan_paid((int)$l['id']), 2);
        if ($left <= 0) continue;
        $out[(int)$l['id']] = round(min((float)$l['installment'], $left), 2);
    }
    return $out;
}

/* ---------------- Leave ---------------- */

function default_leave_types(): array
{
    return [['Annual leave', 14, 1], ['Sick leave', 14, 1], ['Casual leave', 10, 1], ['Unpaid leave', 0, 0]];
}

function leave_types(array $c, bool $activeOnly = true): array
{
    $rows = db_all('SELECT * FROM leave_types WHERE company_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY id', [$c['id']]);
    if (!$rows && !db_val('SELECT 1 FROM leave_types WHERE company_id = ?', [$c['id']])) {
        foreach (default_leave_types() as [$n, $d, $paid]) db_insert('leave_types', ['company_id' => $c['id'], 'name' => $n, 'days_per_year' => $d, 'paid' => $paid, 'active' => 1]);
        return leave_types($c, $activeOnly);
    }
    return $rows;
}

/** Unpaid leave days (approved) that fall inside a pay month. */
function leave_unpaid_days(array $c, int $employeeId, string $period): float
{
    $start = $period . '-01';
    $end = date('Y-m-t', strtotime($start));
    $rows = db_all("SELECT r.* FROM leave_requests r JOIN leave_types t ON t.id = r.type_id WHERE r.employee_id = ? AND r.status = 'approved' AND t.paid = 0 AND r.start_date <= ? AND r.end_date >= ?", [$employeeId, $end, $start]);
    $n = 0.0;
    foreach ($rows as $r) $n += leave_working_days($c, $r['start_date'], $r['end_date'], $period);
    return $n;
}

/** Per-type balance for a calendar year: [[type, entitled, used, pending, left]]. */
function leave_balances(array $c, int $employeeId, ?int $year = null): array
{
    $year = $year ?? (int)date('Y');
    $out = [];
    foreach (leave_types($c) as $t) {
        $used = (float)db_val("SELECT COALESCE(SUM(days),0) FROM leave_requests WHERE employee_id = ? AND type_id = ? AND status = 'approved' AND YEAR(start_date) = ?", [$employeeId, $t['id'], $year]);
        $pending = (float)db_val("SELECT COALESCE(SUM(days),0) FROM leave_requests WHERE employee_id = ? AND type_id = ? AND status = 'pending' AND YEAR(start_date) = ?", [$employeeId, $t['id'], $year]);
        $ent = (float)$t['days_per_year'];
        $out[] = ['type' => $t, 'entitled' => $ent, 'used' => $used, 'pending' => $pending, 'left' => $ent > 0 ? max(0, $ent - $used) : null];
    }
    return $out;
}

/** Validates and saves a leave request. Returns the new id or an error string. */
function leave_request_create(array $c, int $employeeId, int $typeId, string $from, string $to, string $reason, ?int $userId, bool $approve = false)
{
    $t = db_one('SELECT * FROM leave_types WHERE id = ? AND company_id = ? AND active = 1', [$typeId, $c['id']]);
    if (!$t) return t('Choose a leave type.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) return t('Choose a valid start and end date.');
    if ((strtotime($to) - strtotime($from)) / 86400 > 180) return t('A single request can cover at most 180 days.');
    $days = leave_working_days($c, $from, $to);
    if ($days <= 0) return t('These dates are all weekly holidays.');
    if (db_val("SELECT 1 FROM leave_requests WHERE employee_id = ? AND status IN ('pending','approved') AND start_date <= ? AND end_date >= ?", [$employeeId, $to, $from])) {
        return t('There is already a leave request for some of these days.');
    }
    if ((float)$t['days_per_year'] > 0) {
        foreach (leave_balances($c, $employeeId, (int)substr($from, 0, 4)) as $b) {
            if ((int)$b['type']['id'] === $typeId && $b['used'] + $b['pending'] + $days > $b['entitled']) {
                return sprintf(t('Not enough %s left this year (%s days remaining).'), $t['name'], num(max(0, $b['entitled'] - $b['used'] - $b['pending'])));
            }
        }
    }
    $id = db_insert('leave_requests', [
        'company_id' => $c['id'], 'employee_id' => $employeeId, 'type_id' => $typeId, 'start_date' => $from, 'end_date' => $to, 'days' => $days,
        'reason' => mb_substr($reason, 0, 300) ?: null, 'status' => $approve ? 'approved' : 'pending', 'requested_by' => $userId,
        'decided_by' => $approve ? $userId : null, 'decided_at' => $approve ? now() : null, 'created_at' => now(),
    ]);
    if ($approve) sync_employee_drafts($c, $employeeId);
    return $id;
}

/* ---------------- Payroll integration ---------------- */

/** Loan instalments and unpaid leave that payroll should apply automatically for one employee and month. */
function auto_adjustments(array $c, int $employeeId, string $period, int $workingDays): array
{
    $loans = loans_due($employeeId, $period);
    $leave = min((float)$workingDays, leave_unpaid_days($c, $employeeId, $period));
    return ['loans' => $loans, 'loan_auto' => round(array_sum($loans), 2), 'leave_unpaid' => $leave];
}

/** Re-applies automatic loan and leave figures to one draft payslip, keeping manual changes. */
function slip_sync_auto(array $c, array $run, array $slip): ?string
{
    if ($run['status'] !== 'draft') return null;
    $adj = array_merge(default_adjustments(), json_decode((string)$slip['adjustments'], true) ?: []);
    $auto = auto_adjustments($c, (int)$slip['employee_id'], $run['period'], (int)$run['working_days']);
    $prevLoan = (float)($adj['loan_auto'] ?? 0);
    $prevLeave = (float)($adj['leave_unpaid'] ?? 0);
    $changes = [
        'loan' => max(0, (float)$adj['loan'] - $prevLoan) + $auto['loan_auto'],
        'unpaid_days' => max(0, (float)$adj['unpaid_days'] - $prevLeave) + $auto['leave_unpaid'],
        'loans' => $auto['loans'], 'loan_auto' => $auto['loan_auto'], 'leave_unpaid' => $auto['leave_unpaid'],
    ];
    return payroll_adjust_slip($run, (int)$slip['id'], $changes);
}

/** After a loan or leave change, refresh that employee's payslip in every draft run. */
function sync_employee_drafts(array $c, int $employeeId): void
{
    foreach (db_all("SELECT * FROM payroll_runs WHERE company_id = ? AND status = 'draft'", [$c['id']]) as $run) {
        $slip = db_one('SELECT * FROM payslips WHERE run_id = ? AND employee_id = ?', [$run['id'], $employeeId]);
        if ($slip) slip_sync_auto($c, $run, $slip);
    }
}

/** On finalise: record loan instalments actually deducted and close loans that are fully repaid. */
function record_loan_payments(array $slip, array $adj, string $period): void
{
    $available = (float)($adj['loan'] ?? 0);
    foreach (($adj['loans'] ?? []) as $loanId => $amt) {
        $pay = round(min((float)$amt, $available), 2);
        if ($pay <= 0) continue;
        $available -= $pay;
        db_exec('INSERT IGNORE INTO loan_payments (loan_id, payslip_id, period, amount, created_at) VALUES (?, ?, ?, ?, ?)', [(int)$loanId, $slip['id'], $period, $pay, now()]);
        $l = db_one('SELECT * FROM loans WHERE id = ?', [(int)$loanId]);
        if ($l && loan_paid((int)$l['id']) >= (float)$l['principal'] - 0.009) {
            db_update('loans', ['status' => 'closed', 'closed_at' => now()], 'id = ?', [$l['id']]);
        }
    }
}
