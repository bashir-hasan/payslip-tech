<?php
declare(strict_types=1);

/* ============================================================
 * v1.6 paid-plan tools: festival bonus, payment files, pre-issue
 * checks, WhatsApp sending, paste from Excel, increments, seal and
 * signature, reports, tax certificates, final settlement, reminders.
 * ============================================================ */

/** Lowest plan that includes each feature. */
function feature_tiers(): array
{
    return [
        'pwa' => 'free',
        'festival_bonus' => 'starter', 'payment_file' => 'starter', 'whatsapp' => 'starter', 'paste' => 'starter',
        'checks' => 'starter', 'reminders' => 'starter', 'reports' => 'starter',
        'increments' => 'business', 'stamp' => 'business', 'tax_certificate' => 'business', 'settlement' => 'business',
    ];
}

function feature_names(): array
{
    return [
        'festival_bonus' => t('One-click festival bonus'), 'payment_file' => t('Bank & bKash payment files'), 'whatsapp' => t('Send payslips on WhatsApp'),
        'paste' => t('Paste from Excel'), 'checks' => t('Checks before issuing'), 'reminders' => t('Pay-day & Labour Act reminders'), 'reports' => t('Reports & Excel exports'),
        'increments' => t('Increments with letters'), 'stamp' => t('Company seal & signature'), 'tax_certificate' => t('Tax deduction certificates'), 'settlement' => t('Final settlement statements'),
    ];
}

function plan_allows(array $c, string $feature): bool
{
    if ((int)$c['id'] === demo_cid() || !billing_enabled()) return true;
    $rank = ['free' => 0, 'starter' => 1, 'business' => 2];
    $need = $rank[feature_tiers()[$feature] ?? 'business'] ?? 2;
    return ($rank[company_plan($c)['key']] ?? 0) >= $need;
}

/** Stops the request with an upgrade message when the plan lacks a feature. */
function require_feature(array $c, string $feature, string $back = '/app'): void
{
    if (plan_allows($c, $feature)) return;
    $tier = feature_tiers()[$feature] ?? 'business';
    flash('info', sprintf(t('%s is part of the %s plan. Upgrade to use it.'), feature_names()[$feature] ?? $feature, plans()[$tier]['name'] ?? ucfirst($tier)));
    redirect(billing_enabled() ? '/app/billing' : $back);
}

/** Small "Starter" / "Business" pill shown next to locked features. */
function plan_pill(array $c, string $feature): string
{
    if (plan_allows($c, $feature)) return '';
    $tier = feature_tiers()[$feature] ?? 'business';
    return ' <span class="badge badge-info" style="font-size:10px">' . e(plans()[$tier]['name'] ?? ucfirst($tier)) . '</span>';
}

/* ---------------- Festival bonus ---------------- */

/** Adds a festival bonus to every payslip in a draft run. Returns [count, total] or an error string. */
function festival_bonus_apply(array $c, array $run, string $label, string $mode, float $value, int $minMonths)
{
    if ($run['status'] !== 'draft') return t('This payroll is finalised and can no longer be edited.');
    if ($value <= 0 || ($mode === 'percent' && $value > 300)) return t('Enter a bonus percentage or amount.');
    $label = mb_substr(trim($label), 0, 60) ?: t('Festival bonus');
    $periodEnd = date('Y-m-t', strtotime($run['period'] . '-01'));
    $n = 0;
    $total = 0.0;
    foreach (db_all('SELECT p.*, e.basic, e.join_date FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ?', [$run['id']]) as $s) {
        $adj = array_merge(default_adjustments(), json_decode((string)$s['adjustments'], true) ?: []);
        $eligible = true;
        if ($minMonths > 0 && $s['join_date']) {
            $eligible = strtotime($s['join_date'] . ' +' . $minMonths . ' months') <= strtotime($periodEnd);
        }
        $amount = $eligible ? round($mode === 'percent' ? (float)$s['basic'] * $value / 100 : $value, 2) : 0.0;
        $prev = (float)($adj['festival'] ?? 0);
        $changes = ['bonus' => max(0, (float)$adj['bonus'] - $prev) + $amount, 'festival' => $amount, 'festival_label' => $amount > 0 ? $label : ''];
        if ($amount > 0 && trim((string)$adj['note']) === '') $changes['note'] = $label;
        if (!payroll_adjust_slip($run, (int)$s['id'], $changes) && $amount > 0) {
            $n++;
            $total += $amount;
        }
    }
    return [$n, $total];
}

/* ---------------- Payment files ---------------- */

/** Rows of a bulk payment file. $kind: bank | mfs | cash. */
function payment_file_rows(array $run, string $kind): array
{
    $rows = db_all('SELECT p.net, p.code, e.emp_code, e.name, e.payment_method, e.bank_name, e.account_no, e.phone FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? AND p.status <> \'revoked\' ORDER BY e.name', [$run['id']]);
    $mfs = ['bkash', 'nagad', 'rocket', 'wallet'];
    $out = [];
    foreach ($rows as $r) {
        $m = (string)$r['payment_method'];
        $isMfs = in_array($m, $mfs, true);
        if ($kind === 'bank' && $m !== 'bank') continue;
        if ($kind === 'mfs' && !$isMfs) continue;
        if ($kind === 'cash' && ($m === 'bank' || $isMfs)) continue;
        $out[] = $r;
    }
    return $out;
}

function mobile_wallet_number(?string $n): string
{
    $d = preg_replace('/\D+/', '', bn_to_en_digits((string)$n));
    if (str_starts_with($d, '880')) $d = substr($d, 2);
    if (strlen($d) === 10 && $d[0] === '1') $d = '0' . $d;
    return $d;
}

/* ---------------- Checks before issuing ---------------- */

/** Warnings for a draft run: [['level' => warn|info, 'text' => …, 'slip' => id], …] */
function run_checks(array $c, array $run): array
{
    $out = [];
    $prevRun = db_one("SELECT id, period FROM payroll_runs WHERE company_id = ? AND status = 'finalized' AND period < ? ORDER BY period DESC LIMIT 1", [$c['id'], $run['period']]);
    $prev = [];
    if ($prevRun) foreach (db_all('SELECT employee_id, net FROM payslips WHERE run_id = ?', [$prevRun['id']]) as $p) $prev[(int)$p['employee_id']] = (float)$p['net'];
    $end = date('Y-m-t', strtotime($run['period'] . '-01'));
    $slips = db_all('SELECT p.id, p.net, p.adjustments, e.id AS eid, e.name, e.payment_method, e.account_no, e.phone, e.email, e.join_date FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? ORDER BY e.name', [$run['id']]);
    foreach ($slips as $s) {
        $adj = json_decode((string)$s['adjustments'], true) ?: [];
        if (isset($prev[$s['eid']]) && $prev[$s['eid']] > 0) {
            $chg = ((float)$s['net'] - $prev[$s['eid']]) / $prev[$s['eid']] * 100;
            if (abs($chg) >= 30) $out[] = ['level' => 'warn', 'slip' => $s['id'], 'text' => sprintf(t('%s: net pay is %s%% %s than %s (%s → %s).'), $s['name'], num(round(abs($chg))), $chg > 0 ? t('higher') : t('lower'), period_label($prevRun['period']), money($prev[$s['eid']]), money($s['net']))];
        } elseif ($prevRun && !isset($prev[$s['eid']])) {
            $out[] = ['level' => 'info', 'slip' => $s['id'], 'text' => sprintf(t('%s: first payslip. Check the salary and joining date.'), $s['name'])];
        }
        if ($s['join_date'] && $s['join_date'] > $end) $out[] = ['level' => 'warn', 'slip' => $s['id'], 'text' => sprintf(t('%s joins on %s, after this month ends.'), $s['name'], date_fmt($s['join_date']))];
        $m = (string)$s['payment_method'];
        if ($m === 'bank' && trim((string)$s['account_no']) === '') $out[] = ['level' => 'warn', 'slip' => $s['id'], 'text' => sprintf(t('%s is paid by bank but has no account number.'), $s['name'])];
        if (in_array($m, ['bkash', 'nagad', 'rocket', 'wallet'], true) && strlen(mobile_wallet_number($s['account_no'] ?: $s['phone'])) < 11) $out[] = ['level' => 'warn', 'slip' => $s['id'], 'text' => sprintf(t('%s is paid by %s but has no valid wallet number.'), $s['name'], payment_method_label($m))];
        if ((float)($adj['unpaid_days'] ?? 0) >= 10) $out[] = ['level' => 'info', 'slip' => $s['id'], 'text' => sprintf(t('%s has %s unpaid days this month.'), $s['name'], num($adj['unpaid_days'] + 0))];
        if ((float)$s['net'] <= 0) $out[] = ['level' => 'warn', 'slip' => $s['id'], 'text' => sprintf(t('%s has no net pay this month.'), $s['name'])];
    }
    if ($prevRun) {
        $left = db_all("SELECT e.name FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? AND e.id NOT IN (SELECT employee_id FROM payslips WHERE run_id = ?)", [$prevRun['id'], $run['id']]);
        foreach ($left as $l) $out[] = ['level' => 'info', 'slip' => null, 'text' => sprintf(t('%s was paid last month but is not in this payroll.'), $l['name'])];
    }
    $pending = (int)db_val("SELECT COUNT(*) FROM leave_requests WHERE company_id = ? AND status = 'pending' AND start_date <= ?", [$c['id'], $end]);
    if ($pending) $out[] = ['level' => 'info', 'slip' => null, 'text' => sprintf(t('%s leave requests for this month are still waiting for a decision.'), num($pending))];
    return $out;
}

/* ---------------- WhatsApp ---------------- */

function wa_number(?string $phone, string $country = 'BD'): string
{
    $d = preg_replace('/\D+/', '', bn_to_en_digits((string)$phone));
    if ($d === '') return '';
    if ($country === 'BD') {
        if (str_starts_with($d, '01') && strlen($d) === 11) $d = '88' . $d;
        elseif (str_starts_with($d, '1') && strlen($d) === 10) $d = '880' . $d;
    }
    return strlen($d) >= 10 ? $d : '';
}

function wa_link(string $number, string $text): string
{
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

function wa_payslip_text(array $c, string $name, string $period, string $code, string $lang = 'en'): string
{
    if ($lang === 'bn') return sprintf("আসসালামু আলাইকুম %s,\n%s মাসের আপনার পে-স্লিপ তৈরি। দেখুন ও ডাউনলোড করুন:\n%s\n\nকোড: %s\n— %s", $name, period_label($period, 'bn'), verify_url($code), $code, $c['name']);
    return sprintf("Hello %s,\nYour payslip for %s is ready. View and download it here:\n%s\n\nCode: %s\n— %s", $name, period_label($period, 'en'), verify_url($code), $code, $c['name']);
}

/* ---------------- Paste from Excel ---------------- */

/** Parses pasted rows with a header line. Returns [rows, errors]. Each row: ['key' => id or name, field => value]. */
function paste_parse(string $text): array
{
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', bn_to_en_digits($text))), fn($l) => $l !== ''));
    if (count($lines) < 2) return [[], [t('Paste a header row and at least one employee row.')]];
    $split = fn($l) => array_map('trim', str_contains($l, "\t") ? explode("\t", $l) : str_getcsv($l));
    $head = array_map(fn($h) => mb_strtolower(trim($h)), $split($lines[0]));
    $map = [];
    foreach ($head as $i => $h) {
        $field = match (true) {
            (bool)preg_match('/^(emp(loyee)?[ _]?(id|code|no)?|id|code|staff ?id)$/u', $h) => 'id',
            (bool)preg_match('/^(name|employee name|full name|নাম)$/u', $h) => 'name',
            (bool)preg_match('/(overtime|^ot$|ওভারটাইম)/u', $h) => 'overtime',
            (bool)preg_match('/(bonus|বোনাস)/u', $h) => 'bonus',
            (bool)preg_match('/(advance|loan|অগ্রিম)/u', $h) => 'loan',
            (bool)preg_match('/(unpaid|absent|absence|অনুপস্থিত)/u', $h) => 'unpaid_days',
            (bool)preg_match('/(other|deduction|fine|কর্তন)/u', $h) => 'other',
            (bool)preg_match('/(note|remark)/u', $h) => 'note',
            default => null,
        };
        if ($field && !in_array($field, $map, true)) $map[$i] = $field;
    }
    if (!in_array('id', $map, true) && !in_array('name', $map, true)) return [[], [t('The first row must have an Employee ID or Name column.')]];
    if (!array_intersect($map, ['overtime', 'bonus', 'loan', 'unpaid_days', 'other', 'note'])) return [[], [t('Add at least one column: Overtime, Bonus, Advance, Other or Unpaid days.')]];
    $rows = [];
    foreach (array_slice($lines, 1) as $l) {
        $cells = $split($l);
        $r = [];
        foreach ($map as $i => $f) {
            $v = $cells[$i] ?? '';
            $r[$f] = in_array($f, ['id', 'name', 'note'], true) ? $v : (float)str_replace([',', ' '], '', $v);
        }
        $rows[] = $r;
    }
    return [$rows, []];
}

/* ---------------- Increments ---------------- */

const SALARY_KEYS = ['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance'];

function salary_of(array $e): array
{
    $s = [];
    foreach (SALARY_KEYS as $k) $s[$k] = round((float)$e[$k], 2);
    return $s;
}

/** Applies increments that have become due (effective month reached). */
function apply_due_increments(array $c, string $upTo): int
{
    $n = 0;
    foreach (db_all("SELECT * FROM salary_changes WHERE company_id = ? AND status = 'scheduled' AND effective_period <= ?", [$c['id'], $upTo]) as $sc) {
        $new = json_decode((string)$sc['new_salary'], true) ?: [];
        $upd = array_intersect_key($new, array_flip(SALARY_KEYS));
        if ($sc['new_designation']) $upd['designation'] = $sc['new_designation'];
        if ($upd) db_update('employees', $upd, 'id = ? AND company_id = ?', [$sc['employee_id'], $c['id']]);
        db_update('salary_changes', ['status' => 'applied', 'applied_at' => now()], 'id = ?', [$sc['id']]);
        $n++;
    }
    return $n;
}

/* ---------------- Fiscal years & reports ---------------- */

/** [start period, end period, label] of the tax year that starts in $year. */
function tax_year(array $c, int $year): array
{
    if (($c['country'] ?? 'BD') === 'BD') return [$year . '-07', ($year + 1) . '-06', $year . '–' . substr((string)($year + 1), 2)];
    if (($c['country'] ?? '') === 'IN') return [$year . '-04', ($year + 1) . '-03', $year . '–' . substr((string)($year + 1), 2)];
    return [$year . '-01', $year . '-12', (string)$year];
}

/** Writes a simple .xlsx (one sheet, header row bold) and returns the file path. */
function xlsx_write(array $header, array $rows, string $sheet = 'Sheet1'): string
{
    $col = function (int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xml = '';
    foreach (array_merge([$header], $rows) as $ri => $r) {
        $xml .= '<row r="' . ($ri + 1) . '">';
        foreach (array_values($r) as $ci => $v) {
            $ref = $col($ci) . ($ri + 1);
            $style = $ri === 0 ? ' s="1"' : '';
            if (is_int($v) || is_float($v)) $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
            else $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $path = tempnam(sys_get_temp_dir(), 'xl');
    $z = new ZipArchive();
    $z->open($path, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc(mb_substr($sheet, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
    $z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xml . '</sheetData></worksheet>');
    $z->close();
    return $path;
}

function send_xlsx(string $filename, array $header, array $rows, string $sheet = 'Sheet1'): never
{
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    $rows = array_map(fn($r) => array_map($safe, $r), $rows);
    $path = xlsx_write($header, $rows, $sheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    @unlink($path);
    exit;
}

/* ---------------- Reminders (cron) ---------------- */

/** The Nth working day of a month (skipping the company's weekly holidays). */
function nth_working_day(array $c, string $period, int $n): string
{
    $weekend = company_weekend($c);
    $d = strtotime($period . '-01');
    $count = 0;
    while (true) {
        if (!in_array((int)date('N', $d), $weekend, true)) $count++;
        if ($count >= $n) return date('Y-m-d', $d);
        $d = strtotime('+1 day', $d);
    }
}

/** Sends pay-day and Labour Act reminders once each. Returns log lines. */
function run_reminders(): array
{
    $out = [];
    $today = date('Y-m-d');
    foreach (db_all("SELECT * FROM companies WHERE status = 'active'") as $c) {
        if (!plan_allows($c, 'reminders') || (int)$c['id'] === demo_cid()) continue;
        if (!db_val("SELECT 1 FROM employees WHERE company_id = ? AND status = 'active'", [$c['id']])) continue;
        $send = function (string $key, string $subject, string $body, string $path) use ($c, &$out) {
            $k = 'remind:' . $c['id'] . ':' . $key;
            if (db_val('SELECT 1 FROM settings WHERE k = ?', [$k])) return;
            db_exec('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, now()]);
            $n = notify_roles($c, ['owner', 'hr', 'accountant'], $subject, $body, $path, t('Open payroll', 'en'));
            $out[] = $c['name'] . ': ' . $subject . ' (' . $n . ' sent)';
        };
        // 1) Three days before the month ends: this month's payroll not issued yet
        $period = date('Y-m');
        $run = db_one('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $period]);
        if ((int)date('t') - (int)date('j') <= 3 && (!$run || $run['status'] === 'draft')) {
            $label = period_label($period, 'en');
            $send('payday:' . $period, sprintf(t('%s payroll is not issued yet', 'en'), $label), sprintf(t("Hello,\n\nPay day is close and the %s payroll for %s has not been issued yet. %s", 'en'), $label, $c['name'], $run ? 'A draft is ready to review.' : 'Create it in one click from Payroll.'), $run ? '/app/payroll/' . $run['id'] : '/app/payroll');
        }
        // 2) Bangladesh Labour Act: wages before the 7th working day after the wage period
        if (($c['country'] ?? 'BD') === 'BD') {
            $prevPeriod = date('Y-m', strtotime('first day of last month'));
            $deadline = nth_working_day($c, $period, 7);
            $prev = db_one('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $prevPeriod]);
            $daysLeft = (strtotime($deadline) - strtotime($today)) / 86400;
            if ($daysLeft >= 0 && $daysLeft <= 3 && (!$prev || $prev['status'] === 'draft')) {
                $label = period_label($prevPeriod, 'en');
                $send('law:' . $prevPeriod, sprintf(t('Wage deadline: %s salaries are due by %s', 'en'), $label, date_fmt($deadline, 'en')), sprintf(t("Hello,\n\nUnder the Bangladesh Labour Act, wages for %s must be paid before the end of the seventh working day, which is %s for %s. The payroll has not been issued yet.", 'en'), $label, date_fmt($deadline, 'en'), $c['name']), $prev ? '/app/payroll/' . $prev['id'] : '/app/payroll');
            }
        }
    }
    return $out;
}
