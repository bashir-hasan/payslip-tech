<?php
declare(strict_types=1);

/* ---------------- Documents (employer) ---------------- */

function emp_documents(): void
{
    $c = my_company();
    $rows = db_all("SELECT d.*, e.name, e.emp_code, (SELECT COUNT(*) FROM verifications v WHERE v.document_id = d.id) AS checks FROM documents d JOIN employees e ON e.id = d.employee_id WHERE d.company_id = ? ORDER BY FIELD(d.status,'requested','issued','revoked','declined'), d.id DESC LIMIT 500", [$c['id']]);
    $emps = db_all("SELECT id, name, emp_code FROM employees WHERE company_id = ? ORDER BY status = 'active' DESC, name", [$c['id']]);
    render('app/documents', ['title' => t('Documents'), 'nav' => 'documents', 'rows' => $rows, 'emps' => $emps], 'app');
}

function emp_document_issue(): void
{
    $c = my_company();
    $reqId = (int)input('request_id') ?: null;
    if ($reqId) {
        $req = db_one("SELECT * FROM documents WHERE id = ? AND company_id = ? AND status = 'requested'", [$reqId, $c['id']]);
        if (!$req) abort(404);
        $eid = (int)$req['employee_id'];
        $type = $req['type'];
        $opts = ['addressed_to' => input('addressed_to', $req['addressed_to']), 'purpose' => input('purpose', $req['purpose']), 'year' => $req['year']];
    } else {
        $eid = (int)input('employee_id');
        $type = (string)input('type');
        if (!isset(base_doc_types()[$type])) redirect('/app/documents');
        $opts = ['addressed_to' => input('addressed_to'), 'purpose' => input('purpose'), 'year' => input('year')];
    }
    $res = document_issue($c, $eid, $type, $opts, (int)current_user()['id'], $reqId);
    if (!is_array($res)) {
        flash('error', $res);
        redirect('/app/documents');
    }
    $e = db_one('SELECT name, email FROM employees WHERE id = ?', [$eid]);
    if ($e['email']) send_mail($e['email'], sprintf(t('Your %s is ready', 'en'), doc_type_label($type)), sprintf(t("Hello %s,\n\n%s issued your %s. Anyone you give it to can scan its QR code to confirm it is genuine.\n\nVerification code: %s", 'en'), $e['name'], $c['name'], strtolower(doc_type_label($type)), $res['code']), abs_url('/document/' . $res['code']), t('View document', 'en'));
    flash('success', sprintf(t('%s issued with code %s.'), doc_type_label($type), $res['code']));
    redirect('/app/documents');
}

function emp_document_update(string $id): void
{
    $c = my_company();
    $d = db_one('SELECT * FROM documents WHERE id = ? AND company_id = ?', [(int)$id, $c['id']]);
    if (!$d) abort(404);
    if (input('action') === 'decline' && $d['status'] === 'requested') {
        db_update('documents', ['status' => 'declined', 'revoke_reason' => mb_substr((string)input('reason'), 0, 255) ?: null], 'id = ?', [$d['id']]);
        flash('success', t('Request declined.'));
    } elseif (input('action') === 'revoke' && $d['status'] === 'issued') {
        $reason = mb_substr(trim((string)input('reason')), 0, 255);
        if (mb_strlen($reason) < 3) {
            flash('error', t('Please give a reason for revoking.'));
            redirect('/app/documents');
        }
        db_update('documents', ['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => $reason], 'id = ?', [$d['id']]);
        log_activity((int)$c['id'], 'Revoked document ' . $d['code']);
        flash('success', sprintf(t('%s revoked. Verifiers will now see it as revoked.'), $d['code']));
    }
    redirect('/app/documents');
}

/* ---------------- Document view (employer, employee, admin) ---------------- */

function document_view(string $code): void
{
    $code = strtoupper($code);
    $d = db_one("SELECT * FROM documents WHERE code = ? AND status IN ('issued','revoked')", [$code]);
    if (!$d) abort(404);
    $u = current_user();
    $allowed = $u && ($u['role'] === 'admin' || ($u['role'] === 'employer' && (int)$u['company_id'] === (int)$d['company_id']) || ($u['role'] === 'employee' && (int)$u['employee_id'] === (int)$d['employee_id']));
    if (!$allowed) redirect('/v/' . $d['code']);
    $d['snapshot'] = json_decode((string)$d['snapshot'], true) ?: [];
    currency_context(snapshot_currency($d['snapshot']));
    $company = db_one('SELECT * FROM companies WHERE id = ?', [$d['company_id']]);
    $L = in_array(input('lang'), ['en', 'bn'], true) ? (string)input('lang') : ($company['payslip_lang'] ?? 'en');
    render('app/document_doc', ['title' => doc_type_label($d['type']) . ' ' . $d['code'], 'd' => $d, 'L' => $L, 'u' => $u, 'branding' => shows_branding($company)], null);
}

/* ---------------- Employee: documents & proof packs ---------------- */

function me_documents(): void
{
    $e = me_employee();
    $docs = db_all("SELECT d.*, (SELECT COUNT(*) FROM verifications v WHERE v.document_id = d.id) AS checks FROM documents d WHERE d.employee_id = ? ORDER BY d.id DESC", [$e['id']]);
    $years = db_all("SELECT DISTINCT LEFT(r.period, 4) AS y FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' ORDER BY y DESC", [$e['id']]);
    render('me/documents', ['title' => t('My documents'), 'nav' => 'me-docs', 'e' => $e, 'docs' => $docs, 'years' => array_column($years, 'y')], 'app');
}

function me_document_request(): void
{
    $e = me_employee();
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$e['company_id']]);
    $type = (string)input('type');
    if (!rate_limit('docreq:' . $e['id'], 10, 86400)) {
        flash('error', t('Too many requests today. Please try again tomorrow.'));
        redirect('/me/documents');
    }
    if ($type === 'income_statement') {
        // Built only from payslips the employer already issued, so the employee can generate it instantly
        $res = document_issue($c, (int)$e['id'], $type, ['year' => input('year'), 'addressed_to' => input('addressed_to'), 'purpose' => input('purpose')], (int)current_user()['id']);
        if (!is_array($res)) {
            flash('error', $res);
            redirect('/me/documents');
        }
        flash('success', sprintf(t('Income statement ready with code %s.'), $res['code']));
        redirect('/document/' . $res['code']);
    }
    if (!isset(doc_types()[$type])) redirect('/me/documents');
    if (db_val("SELECT 1 FROM documents WHERE employee_id = ? AND type = ? AND status = 'requested'", [$e['id'], $type])) {
        flash('info', t('You already have a pending request for this document.'));
        redirect('/me/documents');
    }
    db_insert('documents', ['company_id' => $e['company_id'], 'employee_id' => $e['id'], 'type' => $type, 'status' => 'requested', 'addressed_to' => mb_substr(trim((string)input('addressed_to')), 0, 200) ?: null, 'purpose' => mb_substr(trim((string)input('purpose')), 0, 200) ?: null, 'requested_by' => current_user()['id'], 'created_at' => now()]);
    notify_roles($c, ['owner', 'hr', 'accountant'], sprintf(t('%s requested a %s', 'en'), $e['name'], strtolower(doc_type_label($type))), sprintf(t("%s asked for a %s.\n\nAddressed to: %s\nPurpose: %s", 'en'), $e['name'], strtolower(doc_type_label($type)), input('addressed_to') ?: '—', input('purpose') ?: '—'), '/app/documents', t('Issue document', 'en'));
    flash('success', t('Request sent to your employer. You will get an email when it is issued.'));
    redirect('/me/documents');
}

function me_proof(): void
{
    $e = me_employee();
    $slips = db_all("SELECT p.id, p.code, p.net, p.gross, r.period FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' ORDER BY r.period DESC LIMIT 24", [$e['id']]);
    $docs = db_all("SELECT id, code, type, issued_at FROM documents WHERE employee_id = ? AND status = 'issued' ORDER BY id DESC", [$e['id']]);
    $shares = db_all('SELECT * FROM shares WHERE employee_id = ? ORDER BY id DESC LIMIT 50', [$e['id']]);
    foreach ($shares as &$s) {
        $s['items'] = json_decode((string)$s['items'], true) ?: [];
        $s['state'] = share_state($s);
        $s['url'] = $s['state'] === 'active' ? share_url((string)share_token($s)) : null;
    }
    unset($s);
    $new = $_SESSION['_new_share'] ?? null;
    unset($_SESSION['_new_share']);
    render('me/proof', ['title' => t('Proof of income'), 'nav' => 'me-proof', 'e' => $e, 'slips' => $slips, 'docs' => $docs, 'shares' => $shares, 'new' => $new], 'app');
}

function me_proof_create(): void
{
    $e = me_employee();
    $months = (int)input('months');
    $slipIds = (array)($_POST['payslips'] ?? []);
    if ($months > 0) {
        $slipIds = array_column(db_all("SELECT p.id FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' ORDER BY r.period DESC LIMIT " . min(24, $months), [$e['id']]), 'id');
    }
    $res = share_create($e, $slipIds, (array)($_POST['documents'] ?? []), (string)input('mode'), num_input('threshold') ?: null, (int)input('days', '30'), (string)input('label'), (int)current_user()['id']);
    if (!is_array($res)) {
        flash('error', $res);
        redirect('/me/proof');
    }
    $_SESSION['_new_share'] = share_url($res[1]);
    flash('success', t('Proof pack created. Copy the link and send it to the bank, embassy or landlord.'));
    redirect('/me/proof');
}

function me_proof_revoke(string $id): void
{
    $e = me_employee();
    db_exec('UPDATE shares SET revoked_at = ? WHERE id = ? AND employee_id = ? AND revoked_at IS NULL', [now(), (int)$id, $e['id']]);
    flash('success', t('Link switched off. Nobody can open it any more.'));
    redirect('/me/proof');
}

/* ---------------- Public proof pack page ---------------- */

function public_share(string $token): void
{
    if (!rate_limit('pack:' . client_ip(), 60, 3600)) {
        http_response_code(429);
        render('public/error', ['code' => 429, 'title' => t('Too many checks'), 'message' => t('Please wait a while before verifying more payslips.')], 'public');
        return;
    }
    $s = share_by_token($token);
    $state = $s ? share_state($s) : 'missing';
    if ($state !== 'active') {
        http_response_code($state === 'missing' ? 404 : 410);
        render('public/share', ['title' => t('Proof of income'), 'state' => $state, 'noindex' => true], 'public');
        return;
    }
    $e = db_one('SELECT e.name, e.emp_code, e.designation, e.join_date, e.status, c.name AS company_name, c.id AS cid FROM employees e JOIN companies c ON c.id = e.company_id WHERE e.id = ?', [$s['employee_id']]);
    $items = [];
    foreach ($s['items']['payslips'] ?? [] as $pid) {
        $code = db_val('SELECT code FROM payslips WHERE id = ? AND employee_id = ?', [(int)$pid, $s['employee_id']]);
        if ($code) $items[] = verify_lookup((string)$code);
    }
    usort($items, fn($a, $b) => strcmp($b['payslip']['period'] ?? '', $a['payslip']['period'] ?? ''));
    $docs = [];
    foreach ($s['items']['documents'] ?? [] as $did) {
        $code = db_val('SELECT code FROM documents WHERE id = ? AND employee_id = ?', [(int)$did, $s['employee_id']]);
        if ($code) $docs[] = verify_lookup((string)$code);
    }
    $first = empty($_SESSION['_seen_pack'][$s['id']]);
    if ($first) {
        $_SESSION['_seen_pack'][$s['id']] = 1;
        db_exec('UPDATE shares SET views = views + 1, last_viewed_at = ? WHERE id = ?', [now(), $s['id']]);
        foreach (array_merge($items, $docs) as $r) log_verification($r, 'pack', 'proof_pack');
    }
    render('public/share', ['title' => t('Proof of income') . ' — ' . $e['name'], 'state' => 'active', 's' => $s, 'e' => $e, 'items' => $items, 'docs' => $docs, 'trust' => company_trust((int)$e['cid']), 'noindex' => true], 'public');
}

/* ---------------- Company domain verification (owner) ---------------- */

function emp_domain_save(): void
{
    $c = my_company();
    $domain = clean_domain((string)input('domain'));
    if (!$domain) {
        flash('error', t('Enter your company domain, for example yourcompany.com.'));
        redirect('/app/settings#domain');
    }
    if (input('action') === 'check') {
        if (!rate_limit('dns:' . $c['id'], 20, 3600)) {
            flash('error', t('Too many checks. Try again in an hour.'));
            redirect('/app/settings#domain');
        }
        if (domain_check($c, $domain)) {
            db_update('companies', ['domain' => $domain, 'domain_verified_at' => now()], 'id = ?', [$c['id']]);
            audit('Verified company domain ' . $domain, (int)$c['id']);
            flash('success', sprintf(t('%s is verified. Verifiers will now see it on your payslips.'), $domain));
        } else {
            db_update('companies', ['domain' => $domain, 'domain_verified_at' => null], 'id = ?', [$c['id']]);
            flash('error', t('We could not find the TXT record yet. DNS changes can take up to an hour. Check the value and try again.'));
        }
    } else {
        db_update('companies', ['domain' => $domain, 'domain_verified_at' => null], 'id = ?', [$c['id']]);
        flash('info', t('Now add the TXT record below at your domain provider, then press Check.'));
    }
    redirect('/app/settings#domain');
}

/* ---------------- Admin: mark a business as checked ---------------- */

function admin_company_verify(string $id): void
{
    require_role('admin');
    $on = (bool)input('on');
    db_update('companies', ['business_verified_at' => $on ? now() : null], 'id = ?', [(int)$id]);
    audit(($on ? 'Marked' : 'Unmarked') . ' company #' . (int)$id . ' as a checked business', (int)$id);
    flash('success', $on ? t('Company marked as a checked business.') : t('Business check removed.'));
    back('/admin/companies');
}
