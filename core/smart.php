<?php
declare(strict_types=1);

/**
 * Smart automation that runs on your own server (no external AI API):
 *  - Assistant: turns plain English / Bangla commands into payroll actions (preview → confirm).
 *  - Smart import: reads CSV or Excel (.xlsx) in any layout and maps columns automatically.
 *  - Payslip explainer: explains a payslip and what changed since last month.
 */

/* ============================================================
 * Assistant
 * ============================================================ */

function smart_normalize(string $s): string
{
    $s = bn_to_en_digits($s);
    $map = [
        'বোনাস' => ' bonus ', 'ওভারটাইম' => ' overtime ', 'অগ্রিম' => ' advance ', 'লোন' => ' advance ', 'ঋণ' => ' advance ',
        'অনুপস্থিত' => ' unpaid ', 'অবৈতনিক' => ' unpaid ', 'ছুটি' => ' unpaid ', 'দিন' => ' days ', 'টাকা' => ' ', 'কর্তন' => ' deduct ', 'জরিমানা' => ' deduct ',
        'বেতন প্রক্রিয়া' => ' payroll ', 'পেরোল' => ' payroll ', 'বেতন' => ' payroll ', 'চূড়ান্ত' => ' finalise ', 'ইস্যু' => ' finalise ', 'ইমেইল' => ' email ', 'পাঠাও' => ' send ', 'পাঠান' => ' send ',
        'সবাই' => ' everyone ', 'সবাইকে' => ' everyone ', 'সবার' => ' everyone ', 'নতুন কর্মী' => ' add employee ', 'মোট' => ' total ', 'চালাও' => ' run ', 'চালান' => ' run ', 'তৈরি' => ' create ',
        'এই মাস' => ' this month ', 'গত মাস' => ' last month ', 'আগামী মাস' => ' next month ',
        'জানুয়ারি' => ' january ', 'ফেব্রুয়ারি' => ' february ', 'মার্চ' => ' march ', 'এপ্রিল' => ' april ', 'মে' => ' may ', 'জুন' => ' june ', 'জুলাই' => ' july ', 'আগস্ট' => ' august ', 'সেপ্টেম্বর' => ' september ', 'অক্টোবর' => ' october ', 'নভেম্বর' => ' november ', 'ডিসেম্বর' => ' december ',
    ];
    $s = strtr($s, $map);
    $s = preg_replace('/(\d),(\d{2,3})/', '$1$2', $s);   // 5,000 → 5000
    $s = preg_replace('/(\d)(\.\d+)?\s*k\b/i', '${1}000', $s); // 5k → 5000
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function smart_period(string $clause): ?string
{
    $c = mb_strtolower($clause);
    if (str_contains($c, 'this month')) return date('Y-m');
    if (str_contains($c, 'last month') || str_contains($c, 'previous month')) return date('Y-m', strtotime('first day of last month'));
    if (str_contains($c, 'next month')) return date('Y-m', strtotime('first day of next month'));
    $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    if (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b(?:\s+(\d{4}))?/i', $c, $m)) {
        $mo = $months[strtolower(substr($m[1], 0, 3))];
        $y = !empty($m[2]) ? (int)$m[2] : (int)date('Y');
        if (empty($m[2]) && $mo > (int)date('n') + 1) $y--; // "December" said in January means last year
        return sprintf('%04d-%02d', $y, $mo);
    }
    if (preg_match('/\b(\d{4})-(0[1-9]|1[0-2])\b/', $c, $m)) return $m[1] . '-' . $m[2];
    return null;
}

/** Sound skeleton of a name so "রহিম" matches "Rahim" and "তানভীর" matches "Tanvir". */
function name_skeleton(string $w): string
{
    if (preg_match('/\p{Bengali}/u', $w)) {
        $bn = ['ক' => 'k', 'খ' => 'k', 'গ' => 'g', 'ঘ' => 'g', 'চ' => 's', 'ছ' => 's', 'জ' => 'j', 'ঝ' => 'j', 'ট' => 't', 'ঠ' => 't', 'ড' => 'd', 'ঢ' => 'd', 'ড়' => 'r', 'ঢ়' => 'r', 'ণ' => 'n', 'ত' => 't', 'থ' => 't', 'দ' => 'd', 'ধ' => 'd', 'ন' => 'n', 'প' => 'p', 'ফ' => 'f', 'ব' => 'b', 'ভ' => 'b', 'ম' => 'm', 'য' => 'j', 'য়' => '', 'র' => 'r', 'ল' => 'l', 'শ' => 's', 'ষ' => 's', 'স' => 's', 'হ' => '', 'ং' => 'n', 'ঁ' => '', 'ৎ' => 't', 'ঃ' => ''];
        $w = str_replace(["\u{09AF}\u{09BC}", "\u{09DF}", "\u{09A1}\u{09BC}", "\u{09DC}", "\u{09A2}\u{09BC}", "\u{09DD}"], ['', '', 'র', 'র', 'র', 'র'], $w);
        $out = '';
        foreach (preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) as $ch) $out .= $bn[$ch] ?? '';
        return preg_replace('/(.)\1+/', '$1', $out);
    }
    $w = strtolower($w);
    $w = strtr($w, ['sh' => 's', 'ch' => 's', 'ph' => 'f', 'kh' => 'k', 'gh' => 'g', 'th' => 't', 'dh' => 'd', 'bh' => 'b', 'v' => 'b', 'w' => 'b', 'z' => 'j', 'q' => 'k', 'c' => 'k', 'x' => 's']);
    $w = preg_replace('/[^a-z]|[aeiouyh]/', '', $w);
    return preg_replace('/(.)\1+/', '$1', $w);
}

/** Finds employees mentioned in a clause. @return array<int, array> */
function smart_find_employees(string $clause, array $emps): array
{
    $c = mb_strtolower($clause);
    if (preg_match('/\b(everyone|everybody|all employees|all staff|all)\b/', $c)) return $emps;
    $found = [];
    foreach ($emps as $e) {
        $code = mb_strtolower($e['emp_code']);
        if ($code !== '' && preg_match('/(^|\s)' . preg_quote($code, '/') . '(\s|$)/u', $c)) { $found[$e['id']] = $e; continue; }
        $name = mb_strtolower(trim($e['name']));
        if ($name !== '' && mb_strpos($c, $name) !== false) { $found[$e['id']] = $e; continue; }
    }
    if ($found) return array_values($found);
    // First-name / last-name match (must be unique), then fuzzy
    $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{M}\p{N}\s-]/u', ' ', $c));
    foreach ($words as $w) {
        if (mb_strlen($w) < 3) continue;
        $hits = array_filter($emps, function ($e) use ($w) {
            foreach (preg_split('/\s+/u', mb_strtolower($e['name'])) as $part) {
                if ($part === $w) return true;
                if (mb_strlen($w) >= 5 && strlen($w) === mb_strlen($w) && levenshtein($part, $w) === 1) return true;
            }
            return false;
        });
        if (!$hits && preg_match('/\p{Bengali}/u', $w)) {
            $sk = name_skeleton($w);
            if (strlen($sk) >= 2) $hits = array_filter($emps, function ($e) use ($sk) {
                foreach (preg_split('/\s+/u', $e['name']) as $part) if (name_skeleton($part) === $sk) return true;
                return false;
            });
        }
        if (count($hits) === 1) $found[reset($hits)['id']] = reset($hits);
        elseif (count($hits) > 1) return ['ambiguous' => array_values($hits)];
    }
    return array_values($found);
}

/**
 * Parses a command into actions.
 * @return array{actions: array, answers: array, problems: array}
 */
function smart_parse(string $text, array $c): array
{
    $emps = db_all("SELECT * FROM employees WHERE company_id = ? AND status = 'active' ORDER BY name", [$c['id']]);
    $norm = smart_normalize($text);
    $clauses = preg_split('/\s*(?:[,;\n]+|\band then\b|\bthen\b|\band\b(?=\s+(?:give|set|add|run|create|finali|email|send|mark|deduct|bonus|overtime|ot|advance|loan|unpaid|absent|fine|\S+\s+(?:bonus|overtime|advance|unpaid)))| ও | এবং )\s*/iu', $norm) ?: [];
    $actions = [];
    $answers = [];
    $problems = [];
    $defaultPeriod = smart_period($norm);
    $lastWho = [];
    foreach ($clauses as $cl) {
        $cl = trim($cl);
        if ($cl === '') continue;
        $l = mb_strtolower($cl);
        // Questions
        if (preg_match('/\b(total|how much|sum)\b.*\b(payroll|net|salary|pay)\b|\b(payroll|net) total\b/', $l)) {
            $p = smart_period($l) ?? $defaultPeriod;
            $run = $p ? db_one('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $p]) : db_one('SELECT * FROM payroll_runs WHERE company_id = ? ORDER BY period DESC LIMIT 1', [$c['id']]);
            if ($run) {
                $t = db_one('SELECT COUNT(*) n, SUM(gross) g, SUM(net) net FROM payslips WHERE run_id = ?', [$run['id']]);
                $answers[] = sprintf(t('%s payroll (%s): %d employees, gross %s, net pay %s.'), period_label($run['period']), $run['status'] === 'draft' ? t('Draft') : t('Finalised'), $t['n'], money($t['g']), money($t['net']));
            } else {
                $answers[] = t('There is no payroll for that month yet.');
            }
            continue;
        }
        if (preg_match('/\bhow many\b.*\bemployee/', $l)) {
            $answers[] = sprintf(t('You have %d active employees.'), count($emps));
            continue;
        }
        if (preg_match('/\b(who|which)\b.*\b(no|without|missing)\b.*\bemail/', $l)) {
            $miss = array_column(array_filter($emps, fn($e) => !$e['email']), 'name');
            $answers[] = $miss ? t('No email address:') . ' ' . implode(', ', $miss) : t('All active employees have an email address.');
            continue;
        }
        // Run / create payroll
        if (preg_match('/\b(run|create|start|make|prepare|new|generate)\b.*\bpayroll\b|\bpayroll\b.*\b(for|of)\b/', $l) && !preg_match('/\b(finali|issue|email|send)/', $l)) {
            $actions[] = ['type' => 'create_run', 'period' => smart_period($l) ?? $defaultPeriod ?? date('Y-m')];
            continue;
        }
        if (preg_match('/\b(finali[sz]e|issue|lock|publish|approve)\b/', $l)) {
            $actions[] = ['type' => 'finalize', 'period' => smart_period($l) ?? $defaultPeriod];
            continue;
        }
        if (preg_match('/\b(email|send|mail)\b/', $l) && preg_match('/\b(payslip|slip|employee|everyone|staff|all|them)\b/', $l)) {
            $actions[] = ['type' => 'email', 'period' => smart_period($l) ?? $defaultPeriod];
            continue;
        }
        // Add employee: "add employee Karim Ali, designation Driver, salary 20000"
        if (preg_match('/\b(add|hire|new)\s+(an?\s+)?employee\s+(.+)/i', $cl, $m)) {
            $rest = $m[3];
            $salary = preg_match('/\b(?:salary|gross|pay|basic)\s*(?:of|is|=)?\s*(\d+(?:\.\d+)?)/i', $rest, $sm) ? (float)$sm[1] : (preg_match('/(\d{3,}(?:\.\d+)?)/', $rest, $sm) ? (float)$sm[1] : 0);
            $des = preg_match('/\b(?:as|designation|role|position)\s+(?:an?\s+)?([\p{L} ]+?)(?:\s+(?:with|salary|gross|pay)\b|$)/iu', $rest, $dm) ? trim($dm[1]) : '';
            $name = trim(preg_split('/\b(as|designation|role|position|salary|gross|pay|basic|with)\b|\d/i', $rest)[0]);
            if ($name === '' || $salary <= 0) {
                $problems[] = t('To add an employee, write: add employee Karim Ali as Driver salary 20000');
            } else {
                $actions[] = ['type' => 'add_employee', 'name' => mb_convert_case($name, MB_CASE_TITLE), 'designation' => mb_convert_case($des, MB_CASE_TITLE), 'gross' => $salary];
            }
            continue;
        }
        // Adjustments
        $field = null;
        if (preg_match('/\bbonus\b/', $l)) $field = 'bonus';
        elseif (preg_match('/\b(overtime|ot)\b/', $l)) $field = 'overtime';
        elseif (preg_match('/\b(advance|loan)\b/', $l)) $field = 'loan';
        elseif (preg_match('/\b(unpaid|absent|absence|lwp|leave without pay)\b/', $l)) $field = 'unpaid_days';
        elseif (preg_match('/\b(deduct|deduction|fine|penalty|cut)\b/', $l)) $field = 'other';
        elseif (preg_match('/\b(clear|reset|remove)\b.*\b(adjustment|bonus|overtime)/', $l)) $field = 'reset';
        if ($field) {
            $who = smart_find_employees($l, $emps);
            if (isset($who['ambiguous'])) {
                $problems[] = sprintf(t('"%s" matches more than one employee (%s). Use the full name or employee ID.'), $cl, implode(', ', array_column($who['ambiguous'], 'name')));
                continue;
            }
            if (!$who && $lastWho) $who = $lastWho; // "Tanvir overtime 3500 and advance 2000"
            if (!$who) {
                $problems[] = sprintf(t('I could not find an employee in "%s". Use the name as saved (e.g. Rahim) or the employee ID.'), $cl);
                continue;
            }
            $lastWho = $who;
            $nums = [];
            preg_match_all('/(?<![\w-])(\d+(?:\.\d+)?)(?![\w-])/', preg_replace('/\b\d{4}-\d{2}\b/', '', $l), $nm);
            $nums = array_map('floatval', $nm[1] ?? []);
            if ($field !== 'reset' && !$nums) {
                $problems[] = sprintf(t('Please include an amount in "%s".'), $cl);
                continue;
            }
            $val = $field === 'reset' ? 0 : end($nums);
            foreach ($who as $e) {
                $actions[] = ['type' => 'adjust', 'employee_id' => (int)$e['id'], 'name' => $e['name'], 'field' => $field, 'value' => $val, 'period' => smart_period($l) ?? $defaultPeriod];
            }
            continue;
        }
        $problems[] = sprintf(t('I did not understand "%s".'), $cl);
    }
    return ['actions' => $actions, 'answers' => $answers, 'problems' => $problems];
}

function smart_action_label(array $a): string
{
    $fields = ['bonus' => t('Bonus'), 'overtime' => t('Overtime'), 'loan' => t('Loan / Advance'), 'unpaid_days' => t('Unpaid days'), 'other' => t('Other deduction')];
    switch ($a['type']) {
        case 'create_run': return sprintf(t('Create payroll draft for %s'), period_label($a['period']));
        case 'finalize': return sprintf(t('Finalise and issue payslips%s'), $a['period'] ? ' — ' . period_label($a['period']) : '');
        case 'email': return sprintf(t('Email payslips to employees%s'), $a['period'] ? ' — ' . period_label($a['period']) : '');
        case 'add_employee': return sprintf(t('Add employee %s%s with monthly gross %s'), $a['name'], $a['designation'] ? ' (' . $a['designation'] . ')' : '', money($a['gross']));
        case 'adjust':
            if ($a['field'] === 'reset') return sprintf(t('Clear adjustments for %s'), $a['name']);
            return sprintf('%s: %s = %s', $a['name'], $fields[$a['field']] ?? $a['field'], $a['field'] === 'unpaid_days' ? num($a['value'] + 0) . ' ' . t('days') : money($a['value']));
    }
    return '';
}

/** Executes confirmed actions. Returns result lines. */
function smart_apply(array $actions, array $c, int $userId): array
{
    $out = [];
    $runFor = function (?string $period, bool $draftOnly) use ($c) {
        if ($period) return db_one('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $period]);
        return db_one('SELECT * FROM payroll_runs WHERE company_id = ? ' . ($draftOnly ? "AND status = 'draft' " : '') . 'ORDER BY period DESC LIMIT 1', [$c['id']]);
    };
    // order: employees → runs → adjustments → finalise → email
    $order = ['add_employee' => 0, 'create_run' => 1, 'adjust' => 2, 'finalize' => 3, 'email' => 4];
    usort($actions, fn($a, $b) => ($order[$a['type']] ?? 9) <=> ($order[$b['type']] ?? 9));
    $lastPeriod = null;
    foreach ($actions as $a) {
        switch ($a['type']) {
            case 'add_employee':
                if (!within_employee_limit($c)) { $out[] = '✗ ' . t('Employee limit reached for your plan.'); break; }
                $g = (float)$a['gross'];
                $basic = round($g * 0.6, 2); $hr = round($g * 0.3, 2); $med = round($g * 0.05, 2);
                $n = (int)db_val('SELECT COUNT(*) FROM employees WHERE company_id = ?', [$c['id']]) + 1;
                db_insert('employees', ['company_id' => $c['id'], 'emp_code' => sprintf('EMP-%03d', $n) . (db_val('SELECT 1 FROM employees WHERE company_id = ? AND emp_code = ?', [$c['id'], sprintf('EMP-%03d', $n)]) ? '-' . random_code(3) : ''), 'name' => mb_substr($a['name'], 0, 190), 'designation' => mb_substr($a['designation'], 0, 190) ?: null, 'basic' => $basic, 'house_rent' => $hr, 'medical' => $med, 'conveyance' => round($g - $basic - $hr - $med, 2), 'created_at' => now()]);
                $out[] = '✓ ' . smart_action_label($a);
                break;
            case 'create_run':
                $res = payroll_create_run($c, $a['period'], 30, null, $userId);
                $out[] = is_int($res) ? '✓ ' . smart_action_label($a) : '✗ ' . $res;
                $lastPeriod = $a['period'];
                break;
            case 'adjust':
                $run = $runFor($a['period'] ?? $lastPeriod, true);
                if (!$run || $run['status'] !== 'draft') { $out[] = '✗ ' . sprintf(t('No draft payroll to adjust for %s. Create one first.'), $a['name']); break; }
                $slipId = (int)db_val('SELECT id FROM payslips WHERE run_id = ? AND employee_id = ?', [$run['id'], $a['employee_id']]);
                if (!$slipId) { $out[] = '✗ ' . sprintf(t('%s is not in this payroll.'), $a['name']); break; }
                $changes = $a['field'] === 'reset' ? ['overtime' => 0, 'bonus' => 0, 'loan' => 0, 'other' => 0, 'unpaid_days' => 0] : [$a['field'] => $a['value']];
                $err = payroll_adjust_slip($run, $slipId, $changes);
                $out[] = $err ? '✗ ' . $a['name'] . ': ' . $err : '✓ ' . smart_action_label($a) . ' (' . period_label($run['period']) . ')';
                break;
            case 'finalize':
                $run = $runFor($a['period'] ?? $lastPeriod, true);
                if (!$run || $run['status'] !== 'draft') { $out[] = '✗ ' . t('There is no draft payroll to finalise.'); break; }
                $n = payroll_finalize_run($c, $run);
                $lastPeriod = $run['period'];
                $out[] = '✓ ' . sprintf(t('%d verified payslips issued for %s.'), $n, period_label($run['period']));
                break;
            case 'email':
                $run = $runFor($a['period'] ?? $lastPeriod, false);
                if (!$run || $run['status'] !== 'finalized') { $out[] = '✗ ' . t('Finalise the payroll before emailing payslips.'); break; }
                [$sent, $total] = payroll_email_run($c, $run);
                $out[] = ($sent ? '✓ ' : '✗ ') . sprintf(t('Emailed %d of %d employees.'), $sent, $total);
                break;
        }
    }
    return $out;
}

/* ============================================================
 * Smart import (CSV / XLSX)
 * ============================================================ */

/** Reads the first sheet of an .xlsx file into rows (no libraries needed). */
function xlsx_rows(string $path, int $max = 1500): ?array
{
    if (!class_exists('ZipArchive')) return null;
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return null;
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        if ($sx) foreach ($sx->si as $si) {
            $txt = '';
            if (isset($si->t)) $txt = (string)$si->t;
            foreach ($si->r as $r) $txt .= (string)$r->t;
            $shared[] = $txt;
        }
    }
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
        if (preg_match('/<sheet[^>]*r:id="([^"]+)"/', $wb, $m) && preg_match('/Id="' . preg_quote($m[1], '/') . '"[^>]*Target="([^"]+)"|Target="([^"]+)"[^>]*Id="' . preg_quote($m[1], '/') . '"/', $rels, $t)) {
            $target = ltrim($t[1] ?: $t[2], '/');
            $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }
    }
    $xml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($xml === false) return null;
    $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$sx || !isset($sx->sheetData)) return null;
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            $col = 0;
            foreach (str_split(preg_replace('/\d/', '', $ref)) as $ch) $col = $col * 26 + (ord($ch) - 64);
            $type = (string)$cell['t'];
            $v = (string)($cell->v ?? '');
            if ($type === 's') $v = $shared[(int)$v] ?? '';
            elseif ($type === 'inlineStr') $v = (string)($cell->is->t ?? '');
            $r[max(0, $col - 1)] = $v;
        }
        if ($r) {
            $maxCol = max(array_keys($r));
            $full = [];
            for ($i = 0; $i <= $maxCol; $i++) $full[] = $r[$i] ?? '';
            $rows[] = $full;
        }
        if (count($rows) >= $max) break;
    }
    return $rows;
}

function csv_rows(string $path, int $max = 1500): array
{
    $raw = (string)file_get_contents($path, false, null, 0, 3 * 1024 * 1024);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $first = strtok($raw, "\n") ?: '';
    $delim = ',';
    foreach ([';', "\t", '|'] as $d) if (substr_count($first, $d) > substr_count($first, $delim)) $delim = $d;
    $rows = [];
    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $delim)) !== false && count($rows) < $max) {
        if ($r === [null]) continue;
        $rows[] = array_map(fn($v) => trim((string)$v), $r);
    }
    fclose($fh);
    return $rows;
}

/** Import targets with recognised header synonyms (English and Bangla). */
function import_targets(): array
{
    $t = [
        'emp_code' => [t('Employee ID'), ['emp code', 'employee id', 'emp id', 'id', 'staff id', 'employee no', 'employee number', 'code', 'কর্মী আইডি', 'আইডি']],
        'name' => [t('Full name'), ['name', 'full name', 'employee name', 'employee', 'staff name', 'নাম', 'কর্মীর নাম']],
        'designation' => [t('Designation'), ['designation', 'title', 'job title', 'position', 'role', 'পদবি']],
        'department' => [t('Department'), ['department', 'dept', 'team', 'division', 'বিভাগ']],
        'email' => [t('Email'), ['email', 'e-mail', 'mail', 'email address', 'ইমেইল']],
        'phone' => [t('Phone'), ['phone', 'mobile', 'cell', 'contact', 'phone number', 'ফোন', 'মোবাইল']],
        'join_date' => [t('Joining date'), ['join date', 'joining date', 'date of joining', 'doj', 'start date', 'hire date', 'যোগদান']],
        'payment_method' => [t('Payment method'), ['payment method', 'pay method', 'payment mode', 'mode']],
        'bank_name' => [t('Bank / wallet name'), ['bank', 'bank name', 'wallet']],
        'account_no' => [t('Account / wallet no.'), ['account', 'account no', 'account number', 'a/c', 'ac no', 'iban', 'bkash number', 'wallet number']],
        'gross' => [t('Gross salary (auto-split)'), ['gross', 'gross salary', 'salary', 'monthly salary', 'total salary', 'ctc', 'pay', 'মোট বেতন', 'বেতন']],
        'basic' => [t('Basic Salary'), ['basic', 'basic salary', 'basic pay', 'base', 'base salary', 'regular pay', 'মূল বেতন']],
        'house_rent' => [t('House Rent'), ['house rent', 'hra', 'housing', 'house rent allowance', 'বাড়ি ভাড়া']],
        'medical' => [t('Medical Allowance'), ['medical', 'medical allowance', 'health', 'চিকিৎসা']],
        'conveyance' => [t('Conveyance'), ['conveyance', 'transport', 'travel', 'commute', 'যাতায়াত']],
        'other_allowance' => [t('Other Allowance'), ['other allowance', 'special allowance', 'allowance', 'allowances']],
        'tax' => [t('Income Tax (TDS)'), ['tax', 'income tax', 'tds', 'paye', 'withholding', 'আয়কর']],
        'pf' => [t('Provident Fund'), ['pf', 'provident fund', 'epf', 'pension', '401k', 'retirement', 'ভবিষ্য তহবিল']],
        'other_deduction' => [t('Other Deduction'), ['other deduction', 'deduction', 'deductions']],
        'adj_bonus' => [t('This month: bonus'), ['bonus', 'incentive', 'বোনাস']],
        'adj_overtime' => [t('This month: overtime'), ['overtime', 'ot', 'overtime pay', 'ওভারটাইম']],
        'adj_loan' => [t('This month: advance / loan'), ['advance', 'loan', 'salary advance', 'অগ্রিম']],
        'adj_unpaid_days' => [t('This month: unpaid days'), ['unpaid days', 'absent', 'absent days', 'lwp', 'leave without pay', 'অনুপস্থিত']],
    ];
    foreach (custom_fields() as $f) $t['cf_' . $f['key']] = [$f['label'] . ' (' . t('custom') . ')', [mb_strtolower($f['label']), $f['key'], mb_strtolower((string)($f['label_bn'] ?? ''))]];
    return $t;
}

/** Guesses a target for each column header. */
function import_guess(array $headers): array
{
    $targets = import_targets();
    $map = [];
    $used = [];
    foreach ($headers as $i => $h) {
        $hn = trim(mb_strtolower(preg_replace('/[_\.]+/', ' ', (string)$h)));
        $hn = trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $hn));
        $best = '';
        $bestScore = 0;
        foreach ($targets as $key => [, $syns]) {
            if (isset($used[$key])) continue;
            foreach ($syns as $s) {
                if ($s === '') continue;
                $score = $hn === $s ? 100 : (str_contains($hn, $s) && mb_strlen($s) >= 3 ? 60 + mb_strlen($s) : 0);
                if (!$score && mb_strlen($hn) >= 4 && strlen($hn) === mb_strlen($hn) && levenshtein($hn, $s) <= 1) $score = 50;
                if ($score > $bestScore) { $bestScore = $score; $best = $key; }
            }
        }
        $map[$i] = $bestScore >= 50 ? $best : '';
        if ($map[$i]) $used[$map[$i]] = true;
    }
    return $map;
}

/** Finds the header row: the row (among the first 10) whose cells best match known headers. */
function import_header_row(array $rows): int
{
    $best = 0;
    $bestHits = -1;
    foreach (array_slice($rows, 0, 10, true) as $i => $r) {
        $hits = count(array_filter(import_guess($r)));
        if ($hits > $bestHits) { $bestHits = $hits; $best = $i; }
    }
    return $best;
}

function import_number($v): float
{
    $v = bn_to_en_digits((string)$v);
    $v = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $v));
    return is_numeric($v) ? round(max(0, (float)$v), 2) : 0.0;
}

function import_date($v): ?string
{
    $v = trim((string)$v);
    if ($v === '') return null;
    if (is_numeric($v) && (float)$v > 20000 && (float)$v < 80000) return date('Y-m-d', (int)(((float)$v - 25569) * 86400)); // Excel serial
    $ts = strtotime(str_replace('/', '-', $v));
    return $ts ? date('Y-m-d', $ts) : null;
}

/* ============================================================
 * Payslip explainer
 * ============================================================ */

/** @return array{summary: string, points: string[], faq: array<string,string>} */
function explain_payslip(array $p, ?array $prev, string $lang = 'en'): array
{
    $L = $lang;
    $labels = snapshot_labels($p['snapshot']);
    $lines = $p['breakdown'];
    $cur = snapshot_currency($p['snapshot']);
    $m = fn($v) => money($v, $L, $cur);
    $points = [];
    $summary = sprintf(t('For %s you earned %s in total. After %s of deductions, your net pay is %s.', $L), period_label($p['period'], $L), $m($p['gross']), $m($p['total_deductions']), $m($p['net']));
    if (($lines['paid_days'] ?? 0) < ($lines['working_days'] ?? 0)) {
        $points[] = sprintf(t('You were paid for %s of %s working days; %s unpaid day(s) reduced your pay by %s.', $L), num($lines['paid_days'] + 0, $L), num($lines['working_days'], $L), num(($lines['working_days'] - $lines['paid_days']) + 0, $L), $m($lines['deductions']['absence'] ?? 0));
    }
    foreach (['bonus', 'overtime'] as $k) {
        if (($lines['earnings'][$k] ?? 0) > 0) $points[] = sprintf(t('%s of %s was added this month.', $L), line_label($labels, $k, $L), $m($lines['earnings'][$k]));
    }
    if (($lines['deductions']['loan'] ?? 0) > 0) $points[] = sprintf(t('%s of %s was deducted to repay an advance or loan.', $L), line_label($labels, 'loan', $L), $m($lines['deductions']['loan']));
    if ($prev) {
        $diff = round((float)$p['net'] - (float)$prev['net'], 2);
        if (abs($diff) < 0.01) {
            $points[] = sprintf(t('Your net pay is the same as %s.', $L), period_label($prev['period'], $L));
        } else {
            $points[] = sprintf($diff > 0 ? t('Your net pay is %s higher than %s.', $L) : t('Your net pay is %s lower than %s.', $L), $m(abs($diff)), period_label($prev['period'], $L));
            $pl = $prev['breakdown'];
            foreach (['earnings' => 1, 'deductions' => -1] as $grp => $sign) {
                foreach (($lines[$grp] ?? []) + ($pl[$grp] ?? []) as $k => $_) {
                    $a = (float)($lines[$grp][$k] ?? 0);
                    $b = (float)($pl[$grp][$k] ?? 0);
                    if (abs($a - $b) >= 0.01) {
                        $points[] = sprintf(t('%s changed from %s to %s.', $L), line_label($labels, $k, $L), $m($b), $m($a));
                    }
                }
            }
        }
    }
    $dedList = [];
    foreach (($lines['deductions'] ?? []) as $k => $v) if ($v > 0) $dedList[] = line_label($labels, $k, $L) . ' ' . $m($v);
    $faq = [
        'deductions' => $dedList ? t('Your deductions this month:', $L) . ' ' . implode(', ', $dedList) . '.' : t('There are no deductions on this payslip.', $L),
        'paid' => sprintf(t('This payslip was issued on %s for the pay date %s, by %s.', $L), date_fmt($p['issued_at'], $L), date_fmt($p['pay_date'], $L), payment_method_label($p['snapshot']['employee']['payment_method'] ?? '', $L)),
        'prove' => t('Share the verification link or the PDF. The bank, embassy or landlord scans the QR code to confirm your payslip is genuine. It is free for them.', $L),
        'wrong' => t('If something looks wrong, contact your employer\'s HR or finance team. They can revoke this payslip and issue a corrected one.', $L),
    ];
    return ['summary' => $summary, 'points' => $points, 'faq' => $faq];
}

function explain_answer(string $q, array $exp, string $lang = 'en'): string
{
    $l = mb_strtolower(bn_to_en_digits($q));
    if (preg_match('/deduct|tax|pf|provident|cut|কর্তন|কর|কাটা/u', $l)) return $exp['faq']['deductions'];
    if (preg_match('/low|less|reduce|decrease|high|more|increase|different|change|why|কম|বেশি|কেন|পরিবর্তন/u', $l)) return $exp['points'] ? implode(' ', $exp['points']) : $exp['summary'];
    if (preg_match('/when|date|paid|bank|account|কবে|তারিখ|পরিশোধ/u', $l)) return $exp['faq']['paid'];
    if (preg_match('/prove|verify|loan|visa|landlord|rent|embassy|share|যাচাই|লোন|ভিসা|ভাড়া/u', $l)) return $exp['faq']['prove'];
    if (preg_match('/wrong|mistake|error|incorrect|ভুল/u', $l)) return $exp['faq']['wrong'];
    return $exp['summary'];
}
