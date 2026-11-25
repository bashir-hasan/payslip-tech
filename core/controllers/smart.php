<?php
declare(strict_types=1);

/* ---------------- Auto payroll ---------------- */

function emp_automation(): void
{
    $c = my_company();
    $a = automation_settings($c);
    $period = date('Y-m');
    $next = automation_pay_date($a, $period) >= date('Y-m-d') ? $period : date('Y-m', strtotime('first day of next month'));
    render('app/automation', ['title' => t('Auto payroll'), 'nav' => 'automation', 'c' => $c, 'a' => $a, 'nextPay' => automation_pay_date($a, $next), 'nextDraft' => automation_draft_date($a, $next), 'cronOk' => setting('cron_last_run') && strtotime((string)setting('cron_last_run')) > time() - 86400 * 2], 'app');
}

function emp_automation_save(): void
{
    $c = my_company();
    $a = automation_settings($c);
    $was = $a['enabled'];
    $pd = (string)input('pay_day');
    $a['enabled'] = (bool)input('enabled');
    $a['pay_day'] = $pd === 'last' ? 'last' : (string)max(1, min(28, (int)$pd));
    $a['lead_days'] = max(0, min(10, (int)input('lead_days', '3')));
    $a['working_days'] = max(1, min(31, (int)input('working_days', '30')));
    $a['auto_finalize'] = (bool)input('auto_finalize');
    $a['auto_email'] = (bool)input('auto_email');
    $a['notify'] = (bool)input('notify');
    if ($a['enabled'] && !$was) $a['enabled_at'] = now();
    save_automation((int)$c['id'], $a);
    log_activity((int)$c['id'], $a['enabled'] ? 'Auto payroll turned on' : 'Auto payroll settings saved');
    flash('success', $a['enabled'] ? t('Auto payroll is on.') : t('Settings saved. Auto payroll is off.'));
    redirect('/app/automation');
}

/** Secret URL for hosts where a CLI cron is hard to set up: /cron/{token} */
function cron_web(string $token): void
{
    if (!hash_equals(cron_token(), $token)) abort(404);
    if (!rate_limit('cron-web', 4, 600)) json_out(['ok' => false, 'error' => 'too soon'], 429);
    header('Content-Type: text/plain; charset=utf-8');
    foreach (run_automation() as $line) echo $line, "\n";
    foreach (run_reminders() as $line) echo $line, "\n";
    echo 'Done at ', date('c'), "\n";
    exit;
}

/* ---------------- Smart assistant ---------------- */

function emp_assistant(): void
{
    $c = my_company();
    render('app/assistant', ['title' => t('Smart assistant'), 'nav' => 'assistant', 'c' => $c, 'history' => $_SESSION['assistant'][$c['id']] ?? [], 'pending' => $_SESSION['assistant_pending'][$c['id']] ?? null, 'remaining' => smart_remaining($c), 'limit' => smart_limit($c)], 'app');
}

function emp_assistant_ask(): void
{
    $c = my_company();
    $text = trim(mb_substr((string)input('q'), 0, 1000));
    if ($text === '') redirect('/app/assistant');
    if (!rate_limit('assist:' . $c['id'], 60, 600)) {
        flash('error', t('Please slow down a little.'));
        redirect('/app/assistant');
    }
    currency_context($c['currency'] ?? 'BDT');
    $res = smart_parse($text, $c);
    $hist = $_SESSION['assistant'][$c['id']] ?? [];
    $reply = [];
    foreach ($res['answers'] as $a) $reply[] = $a;
    foreach ($res['problems'] as $p) $reply[] = '⚠ ' . $p;
    if ($res['actions']) {
        $_SESSION['assistant_pending'][$c['id']] = ['actions' => $res['actions'], 'labels' => array_map('smart_action_label', $res['actions'])];
        $reply[] = t('Here is what I will do. Check it and press Apply.');
    } else {
        unset($_SESSION['assistant_pending'][$c['id']]);
        if (!$reply) $reply[] = t('I did not understand that. Try one of the examples below.');
    }
    $hist[] = ['q' => $text, 'a' => $reply, 'at' => time()];
    $_SESSION['assistant'][$c['id']] = array_slice($hist, -12);
    redirect('/app/assistant#bottom');
}

function emp_assistant_apply(): void
{
    $c = my_company();
    $p = $_SESSION['assistant_pending'][$c['id']] ?? null;
    unset($_SESSION['assistant_pending'][$c['id']]);
    if (!$p) redirect('/app/assistant');
    if (!smart_consume($c)) {
        flash('error', sprintf(t('You have used all %d smart actions for this month. Upgrade your plan for more.'), smart_limit($c)));
        redirect('/app/billing');
    }
    $out = smart_apply($p['actions'], $c, (int)current_user()['id']);
    log_activity((int)$c['id'], 'Smart assistant: ' . mb_substr(implode('; ', $out), 0, 220));
    $hist = $_SESSION['assistant'][$c['id']] ?? [];
    $hist[] = ['q' => '', 'a' => $out, 'at' => time(), 'done' => true];
    $_SESSION['assistant'][$c['id']] = array_slice($hist, -12);
    redirect('/app/assistant#bottom');
}

function emp_assistant_clear(): void
{
    $c = my_company();
    unset($_SESSION['assistant'][$c['id']], $_SESSION['assistant_pending'][$c['id']]);
    redirect('/app/assistant');
}

/* ---------------- Smart import ---------------- */

function import_tmp_path(): string
{
    $dir = sys_get_temp_dir() . '/payslip-import';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}

function emp_import(): void
{
    $c = my_company();
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024 || !is_uploaded_file($f['tmp_name'])) {
        flash('error', t('Please upload a CSV or Excel file under 3 MB.'));
        redirect('/app/employees/import');
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $rows = $ext === 'xlsx' ? xlsx_rows($f['tmp_name']) : ($ext === 'csv' || $ext === 'txt' ? csv_rows($f['tmp_name']) : null);
    if ($rows === null) {
        flash('error', $ext === 'xls' ? t('Old .xls files are not supported. In Excel choose File → Save As → .xlsx or CSV.') : t('Please upload a .csv or .xlsx file.'));
        redirect('/app/employees/import');
    }
    $rows = array_values(array_filter($rows, fn($r) => count(array_filter($r, fn($v) => trim((string)$v) !== '')) > 0));
    if (count($rows) < 2) {
        flash('error', t('The file is empty.'));
        redirect('/app/employees/import');
    }
    $h = import_header_row($rows);
    $headers = $rows[$h];
    $data = array_slice($rows, $h + 1, 1000);
    $id = bin2hex(random_bytes(12));
    file_put_contents(import_tmp_path() . "/$id.json", json_encode(['company' => (int)$c['id'], 'headers' => $headers, 'rows' => $data], JSON_UNESCAPED_UNICODE));
    $_SESSION['import_id'] = $id;
    redirect('/app/employees/import/map');
}

function load_import(array $c): ?array
{
    $id = $_SESSION['import_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{24}$/', $id)) return null;
    $p = import_tmp_path() . "/$id.json";
    if (!is_file($p) || filemtime($p) < time() - 3600) return null;
    $d = json_decode((string)file_get_contents($p), true);
    return ($d && (int)$d['company'] === (int)$c['id']) ? $d : null;
}

function emp_import_map(): void
{
    $c = my_company();
    $d = load_import($c);
    if (!$d) redirect('/app/employees/import');
    $draft = db_one("SELECT * FROM payroll_runs WHERE company_id = ? AND status = 'draft' ORDER BY period DESC LIMIT 1", [$c['id']]);
    render('app/import_map', ['title' => t('Check column matching'), 'nav' => 'employees', 'headers' => $d['headers'], 'rows' => array_slice($d['rows'], 0, 5), 'count' => count($d['rows']), 'guess' => import_guess($d['headers']), 'targets' => import_targets(), 'draft' => $draft, 'remaining' => smart_remaining($c)], 'app');
}

function emp_import_run(): void
{
    $c = my_company();
    $d = load_import($c);
    if (!$d) redirect('/app/employees/import');
    if (!smart_consume($c)) {
        flash('error', sprintf(t('You have used all %d smart actions for this month. Upgrade your plan for more.'), smart_limit($c)));
        redirect('/app/billing');
    }
    $map = [];
    $targets = import_targets();
    foreach ($d['headers'] as $i => $_) {
        $t = (string)($_POST['map'][$i] ?? '');
        if ($t !== '' && isset($targets[$t]) && !in_array($t, $map, true)) $map[$i] = $t;
    }
    if (!in_array('name', $map, true) && !in_array('emp_code', $map, true)) {
        flash('error', t('Match at least the Name or Employee ID column.'));
        redirect('/app/employees/import/map');
    }
    $draft = db_one("SELECT * FROM payroll_runs WHERE company_id = ? AND status = 'draft' ORDER BY period DESC LIMIT 1", [$c['id']]);
    $added = $updated = $skipped = $adjusted = 0;
    $limitHit = false;
    $existing = db_all('SELECT * FROM employees WHERE company_id = ?', [$c['id']]);
    $n = count($existing);
    foreach ($d['rows'] as $row) {
        $r = [];
        $cf = [];
        $adj = [];
        foreach ($map as $i => $t) {
            $v = trim((string)($row[$i] ?? ''));
            if (str_starts_with($t, 'adj_')) { if ($v !== '') $adj[substr($t, 4)] = import_number($v); }
            elseif (str_starts_with($t, 'cf_')) $cf[substr($t, 3)] = mb_substr(strip_tags($v), 0, 190);
            else $r[$t] = $v;
        }
        $name = mb_substr(strip_tags($r['name'] ?? ''), 0, 190);
        $code = mb_substr(strip_tags($r['emp_code'] ?? ''), 0, 60);
        // match existing by code, then by exact name
        $match = null;
        foreach ($existing as $e) {
            if (($code !== '' && $e['emp_code'] === $code) || ($code === '' && $name !== '' && mb_strtolower($e['name']) === mb_strtolower($name))) { $match = $e; break; }
        }
        if (!$match && $name === '') { $skipped++; continue; }
        $fields = [];
        foreach (['name', 'designation', 'department', 'phone', 'bank_name', 'account_no'] as $k) if (isset($r[$k]) && $r[$k] !== '') $fields[$k] = mb_substr(strip_tags($r[$k]), 0, 190);
        if (!empty($r['email']) && filter_var($r['email'], FILTER_VALIDATE_EMAIL)) $fields['email'] = mb_strtolower($r['email']);
        if (!empty($r['join_date'])) $fields['join_date'] = import_date($r['join_date']);
        if (!empty($r['payment_method'])) {
            $pm = strtolower(preg_replace('/[^a-z]/i', '', $r['payment_method']));
            $pm = ['banktransfer' => 'bank', 'directdeposit' => 'bank', 'check' => 'cheque'][$pm] ?? $pm;
            if (array_key_exists($pm, payment_methods_for($c['country'] ?? 'BD'))) $fields['payment_method'] = $pm;
        }
        foreach (['basic', 'house_rent', 'medical', 'conveyance', 'other_allowance', 'tax', 'pf', 'other_deduction'] as $k) if (isset($r[$k]) && $r[$k] !== '') $fields[$k] = import_number($r[$k]);
        if (isset($r['gross']) && $r['gross'] !== '' && empty($fields['basic'])) {
            $g = import_number($r['gross']);
            $fields['basic'] = round($g * 0.6, 2); $fields['house_rent'] = round($g * 0.3, 2); $fields['medical'] = round($g * 0.05, 2);
            $fields['conveyance'] = round($g - $fields['basic'] - $fields['house_rent'] - $fields['medical'], 2);
        }
        if ($cf) {
            $old = $match ? (json_decode((string)$match['custom'], true) ?: []) : [];
            $fields['custom'] = json_encode(array_merge($old, $cf), JSON_UNESCAPED_UNICODE);
        }
        if ($match) {
            if ($fields) db_update('employees', $fields, 'id = ?', [$match['id']]);
            $empId = (int)$match['id'];
            $updated++;
        } else {
            if (($fields['basic'] ?? 0) <= 0) { $skipped++; continue; }
            if (!within_employee_limit($c)) { $skipped++; $limitHit = true; continue; }
            $fields['emp_code'] = $code !== '' ? $code : sprintf('EMP-%03d', ++$n);
            if (db_val('SELECT 1 FROM employees WHERE company_id = ? AND emp_code = ?', [$c['id'], $fields['emp_code']])) $fields['emp_code'] .= '-' . random_code(3);
            $empId = db_insert('employees', $fields + ['company_id' => $c['id'], 'created_at' => now()]);
            $existing[] = db_one('SELECT * FROM employees WHERE id = ?', [$empId]);
            $added++;
        }
        if ($adj && $draft) {
            $slipId = (int)db_val('SELECT id FROM payslips WHERE run_id = ? AND employee_id = ?', [$draft['id'], $empId]);
            if ($slipId && !payroll_adjust_slip($draft, $slipId, $adj)) $adjusted++;
        }
    }
    @unlink(import_tmp_path() . '/' . $_SESSION['import_id'] . '.json');
    unset($_SESSION['import_id']);
    log_activity((int)$c['id'], "Smart import: $added added, $updated updated, $adjusted payroll adjustments");
    flash('success', sprintf(t('Import finished: %d added, %d updated, %d skipped.'), $added, $updated, $skipped) . ($adjusted ? ' ' . sprintf(t('%d payroll adjustments applied to %s.'), $adjusted, period_label($draft['period'])) : ''));
    if ($limitHit) flash('error', sprintf(t('Some rows were skipped because your %s plan allows %d active employees.'), company_plan($c)['name'], employee_limit($c)));
    redirect($adjusted ? '/app/payroll/' . $draft['id'] : '/app/employees');
}

/* ---------------- Payslip explainer (employee portal) ---------------- */

function me_explain(string $code): void
{
    $u = require_role('employee', 'employer');
    $p = db_one("SELECT p.*, r.period, r.pay_date FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.code = ? AND p.status IN ('issued','revoked')", [strtoupper($code)]);
    if (!$p) abort(404);
    $ok = ($u['role'] === 'employee' && (int)$u['employee_id'] === (int)$p['employee_id']) || ($u['role'] === 'employer' && (int)$u['company_id'] === (int)$p['company_id']);
    if (!$ok) abort(403);
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$p['company_id']]);
    $p['snapshot'] = json_decode((string)$p['snapshot'], true);
    $p['breakdown'] = json_decode((string)$p['breakdown'], true);
    $prev = db_one("SELECT p.*, r.period FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' AND r.period < ? ORDER BY r.period DESC LIMIT 1", [$p['employee_id'], $p['period']]);
    if ($prev) {
        $prev['snapshot'] = json_decode((string)$prev['snapshot'], true);
        $prev['breakdown'] = json_decode((string)$prev['breakdown'], true);
    }
    // One smart action per payslip per session
    $key = 'explained_' . $p['id'];
    if (empty($_SESSION[$key])) {
        if (!smart_consume($c)) {
            flash('error', t('The explainer is not available right now. Please try again next month or ask your employer.'));
            redirect($u['role'] === 'employee' ? '/me' : '/app/payslips');
        }
        $_SESSION[$key] = true;
    }
    $exp = explain_payslip($p, $prev, lang());
    $q = trim(mb_substr((string)input('q'), 0, 300));
    $answer = $q !== '' ? explain_answer($q, $exp, lang()) : null;
    render('me/explain', ['title' => t('Explain my payslip'), 'nav' => $u['role'] === 'employee' ? 'me' : 'payslips', 'p' => $p, 'exp' => $exp, 'q' => $q, 'answer' => $answer, 'prev' => $prev], 'app');
}
