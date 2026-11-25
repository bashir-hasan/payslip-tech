<?php
declare(strict_types=1);

/**
 * Payslip document. Access:
 *  - issued/revoked by code: the issuing employer, the employee themselves, platform admin,
 *    or anyone holding the code (same data the verification page shows) when ?share=1
 *  - drafts (draft-<id>): only the employer, shown with a DRAFT watermark
 */
function payslip_view(string $code): void
{
    $u = current_user();
    if (str_starts_with($code, 'draft-')) {
        $c = my_company();
        $p = db_one("SELECT p.*, r.period, r.pay_date FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.id = ? AND p.company_id = ? AND p.status = 'draft'", [(int)substr($code, 6), $c['id']]);
        if (!$p) abort(404);
        $e = db_one('SELECT * FROM employees WHERE id = ?', [$p['employee_id']]);
        $p['snapshot'] = employee_snapshot($e, $c);
        $p['breakdown'] = json_decode((string)$p['breakdown'], true);
    } else {
        $p = db_one('SELECT p.*, r.period, r.pay_date FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.code = ? AND p.status IN (\'issued\',\'revoked\')', [strtoupper($code)]);
        if (!$p) abort(404);
        $allowed = $u && (
            $u['role'] === 'admin'
            || ($u['role'] === 'employer' && (int)$u['company_id'] === (int)$p['company_id'])
            || ($u['role'] === 'employee' && (int)$u['employee_id'] === (int)$p['employee_id'])
        );
        if (!$allowed) {
            // Holders of the code can always see the verification page instead
            redirect('/v/' . $p['code']);
        }
        $p['snapshot'] = json_decode((string)$p['snapshot'], true);
        $p['breakdown'] = json_decode((string)$p['breakdown'], true);
    }
    $company = db_one('SELECT * FROM companies WHERE id = ?', [$p['company_id']]);
    currency_context(snapshot_currency($p['snapshot']));
    $p['branding'] = shows_branding($company);
    $docLang = in_array(input('lang'), ['en', 'bn'], true) ? (string)input('lang') : ($company['payslip_lang'] ?? 'en');
    render('app/payslip_doc', ['title' => 'Payslip ' . ($p['code'] ?? 'Draft'), 'p' => $p, 'L' => $docLang, 'u' => $u], null);
}

function me_home(): void
{
    $u = require_role('employee');
    $e = db_one('SELECT e.*, c.name AS company_name, c.logo, c.currency FROM employees e JOIN companies c ON c.id = e.company_id WHERE e.id = ?', [$u['employee_id']]);
    if (!$e) abort(403);
    currency_context($e['currency'] ?? 'BDT');
    $slips = db_all("SELECT p.*, r.period, (SELECT COUNT(*) FROM verifications v WHERE v.payslip_id = p.id) AS checks FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status IN ('issued','revoked') ORDER BY r.period DESC", [$e['id']]);
    render('me/home', ['title' => t('My payslips'), 'nav' => 'me', 'e' => $e, 'slips' => $slips], 'app');
}
