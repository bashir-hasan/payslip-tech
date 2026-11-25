<?php
declare(strict_types=1);

function public_home(): void
{
    $demo = demo_cid();
    $stats = [
        'payslips' => (int)db_val("SELECT COUNT(*) FROM payslips WHERE status IN ('issued','revoked') AND company_id <> ?", [$demo]),
        'verifications' => (int)db_val('SELECT COUNT(*) FROM verifications WHERE COALESCE(company_id, 0) <> ?', [$demo]),
        'companies' => (int)db_val("SELECT COUNT(*) FROM companies WHERE status = 'active' AND id <> ?", [$demo]),
    ];
    $faqs = home_faqs();
    render('public/home', [
        'title' => '',
        'seo_page' => 'home',
        'description' => default_description(),
        'stats' => $stats,
        'faqs' => $faqs,
        'plans' => plans(),
        'demo' => (bool)setting('demo_enabled', '0'),
        'jsonld' => [jsonld_software(), jsonld_faq($faqs)],
    ]);
}

function public_lang(string $l): void
{
    set_lang($l);
    if ($u = current_user()) db_update('users', ['lang' => $l], 'id = ?', [$u['id']]);
    back('/');
}

function public_verify_form(): void
{
    render('public/verify', [
        'title' => t('Verify a payslip'),
        'seo_page' => 'verify',
        'description' => t('Check if a payslip from a Bangladeshi employer is genuine. Scan the QR code or enter the payslip code to see the original figures. Free, no account needed.'),
        'code' => strtoupper((string)input('code')),
        'jsonld' => [
            ['@type' => 'WebPage', 'name' => t('Verify a payslip'), 'url' => canonical_url('/verify'), 'description' => t('Free online payslip verification for Bangladesh.'), 'isPartOf' => ['@id' => site_origin() . '/#website']],
            jsonld_breadcrumb([[site_name(), '/'], [t('Verify a payslip'), '/verify']]),
        ],
    ]);
}

function public_verify_submit(): void
{
    $code = strtoupper(preg_replace('/\s+/', '', (string)input('code')));
    if (preg_match('#/v/([A-Z0-9-]+)#i', $code, $m)) $code = strtoupper($m[1]);
    if ($code === '') {
        flash('error', t('Please enter a verification code.'));
        redirect('/verify');
    }
    $type = (string)input('verifier_type');
    if (array_key_exists($type, verifier_types())) $_SESSION['_vtype'] = $type;
    redirect('/v/' . rawurlencode($code));
}

function public_verify_result(string $code): void
{
    if (!rate_limit('verify:' . client_ip(), 60, 3600)) {
        http_response_code(429);
        render('public/error', ['code' => 429, 'title' => t('Too many checks'), 'message' => t('Please wait a while before verifying more payslips.')], 'public');
        return;
    }
    $res = verify_lookup($code);
    $vtype = $_SESSION['_vtype'] ?? null;
    unset($_SESSION['_vtype']);
    // Don't count the issuing company's own views as verifications
    $u = current_user();
    $own = $u && isset($res['payslip']) && (int)($u['company_id'] ?? 0) === (int)$res['payslip']['company_id'];
    if (!$own) log_verification($res, 'web', $vtype);
    render('public/verify_result', ['title' => t('Verification result'), 'res' => $res, 'noindex' => true]);
}

function api_verify(string $code): void
{
    if (!rate_limit('api:' . client_ip(), 120, 3600)) {
        json_out(['ok' => false, 'error' => 'rate_limited', 'message' => 'Too many requests. Try again later.'], 429);
    }
    $res = verify_lookup($code);
    log_verification($res, 'api', (string)input('verifier') ?: null);
    $out = ['ok' => true, 'code' => $res['code'], 'result' => $res['result'], 'checked_at' => date('c')];
    if (($res['kind'] ?? '') === 'document') {
        $out['type'] = 'document';
        if (in_array($res['result'], ['valid', 'revoked'], true)) {
            $d = $res['document'];
            $sn = $d['snapshot'];
            $out['document'] = [
                'kind' => $d['type'], 'employer' => $sn['company']['name'] ?? null, 'employee_name' => $sn['employee']['name'] ?? null,
                'employee_id' => $sn['employee']['emp_code'] ?? null, 'designation' => $sn['employee']['designation'] ?? null,
                'join_date' => $sn['employee']['join_date'] ?? null, 'currently_employed' => ($sn['employee']['status'] ?? 'active') === 'active',
                'issued_at' => date('c', strtotime($d['issued_at'])), 'fingerprint' => fingerprint_display($d['hash']),
            ];
            if (!empty($sn['salary'])) $out['document']['monthly_net'] = (float)$sn['salary']['net'];
            if (!empty($sn['totals'])) $out['document'] += ['year' => $sn['year'], 'annual_net' => (float)$sn['totals']['net'], 'months' => count($sn['months'])];
            if ($res['result'] === 'revoked') $out['document']['revoke_reason'] = $d['revoke_reason'];
            $tr = company_trust((int)$d['company_id']);
            $out['employer_trust'] = ['level' => $tr['level'], 'domain' => $tr['domain'], 'business_checked' => $tr['checks']['business'], 'payroll_months' => $tr['runs']];
        }
        json_out($out);
    }
    if (in_array($res['result'], ['valid', 'revoked'], true)) {
        $p = $res['payslip'];
        $out['payslip'] = [
            'employer' => $p['snapshot']['company']['name'] ?? null,
            'employee_name' => $p['snapshot']['employee']['name'] ?? null,
            'employee_id' => $p['snapshot']['employee']['emp_code'] ?? null,
            'designation' => $p['snapshot']['employee']['designation'] ?? null,
            'period' => $p['period'],
            'currency' => snapshot_currency($p['snapshot']),
            'issued_at' => date('c', strtotime($p['issued_at'])),
            'fingerprint' => fingerprint_display($p['hash']),
        ];
        if ((int)$p['show_amounts'] === 1) {
            $out['payslip']['gross'] = (float)$p['gross'];
            $out['payslip']['total_deductions'] = (float)$p['total_deductions'];
            $out['payslip']['net'] = (float)$p['net'];
        }
        $tr = company_trust((int)$p['company_id']);
        $out['employer_trust'] = ['level' => $tr['level'], 'domain' => $tr['domain'], 'business_checked' => $tr['checks']['business'], 'payroll_months' => $tr['runs']];
        if ($res['result'] === 'revoked') {
            $out['payslip']['revoked_at'] = date('c', strtotime($p['revoked_at']));
            $out['payslip']['revoke_reason'] = $p['revoke_reason'];
        }
    }
    json_out($out);
}

function public_developers(): void
{
    render('public/developers', [
        'title' => t('Verification API'),
        'seo_page' => 'developers',
        'description' => t('Free JSON API to verify Bangladeshi payslips issued on PaySlip.tech. One GET request returns valid, revoked, tampered or not_found with the original figures.'),
        'jsonld' => [
            ['@type' => 'TechArticle', 'headline' => 'PaySlip.tech Verification API', 'url' => canonical_url('/developers'), 'about' => 'Payslip verification API', 'proficiencyLevel' => 'Beginner', 'publisher' => ['@id' => site_origin() . '/#organization']],
            jsonld_breadcrumb([[site_name(), '/'], [t('Verification API'), '/developers']]),
        ],
    ]);
}

function public_privacy(): void
{
    render('public/privacy', ['title' => t('Privacy Policy'), 'seo_page' => 'privacy', 'description' => t('How PaySlip.tech collects, uses and protects employer, employee and verification data.'), 'jsonld' => [jsonld_breadcrumb([[site_name(), '/'], [t('Privacy Policy'), '/privacy']])]]);
}

function public_cookies(): void
{
    render('public/cookies', ['title' => t('Cookie Policy'), 'seo_page' => 'cookies', 'description' => t('The cookies PaySlip.tech uses and how to change your choice.')]);
}

function public_dpa(): void
{
    render('public/dpa', ['title' => t('Data Processing Agreement'), 'seo_page' => 'dpa', 'description' => t('GDPR data processing terms for businesses that use PaySlip.tech.')]);
}

function public_accessibility(): void
{
    render('public/accessibility', ['title' => t('Accessibility Statement'), 'seo_page' => 'accessibility', 'description' => t('How PaySlip.tech meets WCAG 2.2 AA and how to report a problem.')]);
}

function public_terms(): void
{
    render('public/terms', ['title' => t('Terms of Service'), 'seo_page' => 'terms', 'description' => t('The terms for using PaySlip.tech to run payroll and issue verifiable payslips.'), 'jsonld' => [jsonld_breadcrumb([[site_name(), '/'], [t('Terms of Service'), '/terms']])]]);
}

/** One-click demo login for reviewers (read/write sandbox company). */
function public_demo_login(): void
{
    if (!setting('demo_enabled', '0')) abort(404);
    $u = db_one("SELECT * FROM users WHERE email = ? AND role = 'employer'", [(string)setting('demo_email', 'demo@payslip.tech')]);
    if (!$u) abort(404);
    login_user($u);
    flash('success', t('You are exploring the demo company. Feel free to try everything.'));
    redirect('/app');
}
