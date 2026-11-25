<?php
declare(strict_types=1);

/* ============================================================
 * Verified employment documents (DC- codes), proof packs that
 * employees share with banks / embassies, and employer trust.
 * ============================================================ */

function doc_types(): array
{
    return [
        'employment_letter' => t('Employment letter'),
        'salary_certificate' => t('Salary certificate'),
        'income_statement' => t('Annual income statement'),
    ];
}

function doc_type_label(string $type, ?string $lang = null): string
{
    $en = ['employment_letter' => 'Employment Letter', 'salary_certificate' => 'Salary Certificate', 'income_statement' => 'Income Statement'][$type] ?? 'Document';
    return $lang === 'bn' ? tp($en, 'bn') : $en;
}

function new_document_code(): string
{
    do {
        $code = 'DC-' . random_code(4) . '-' . random_code(4);
    } while (db_val('SELECT 1 FROM documents WHERE code = ?', [$code]));
    return $code;
}

function document_hash(array $d): string
{
    $canon = implode('|', ['doc', $d['code'], $d['type'], (int)$d['company_id'], (int)$d['employee_id'], $d['issued_at'], (string)$d['snapshot']]);
    return hash_hmac('sha256', $canon, (string)cfg('app_key'));
}

/** Builds the frozen content of a document from the employee record and issued payslips. */
function document_snapshot(string $type, array $e, array $c, array $opts): array
{
    $snap = employee_snapshot($e, $c);
    $snap['employee']['status'] = $e['status'];
    $snap['addressed_to'] = $opts['addressed_to'] ?? '';
    $snap['purpose'] = $opts['purpose'] ?? '';
    if ($type === 'salary_certificate') {
        $calc = compute_slip($e, default_adjustments(), 30);
        $snap['salary'] = ['earnings' => array_filter($calc['earnings'], fn($v) => $v > 0), 'deductions' => array_filter($calc['deductions'], fn($v) => $v > 0), 'gross' => $calc['gross'], 'total_deductions' => $calc['total_deductions'], 'net' => $calc['net']];
    }
    if ($type === 'income_statement') {
        $year = (int)($opts['year'] ?? date('Y'));
        $rows = db_all("SELECT p.code, p.gross, p.total_deductions, p.net, r.period, p.breakdown FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' AND r.period LIKE ? ORDER BY r.period", [$e['id'], $year . '-%']);
        $months = [];
        $tax = 0.0;
        foreach ($rows as $r) {
            $b = json_decode((string)$r['breakdown'], true) ?: [];
            $tax += (float)($b['deductions']['tax'] ?? 0);
            $months[] = ['period' => $r['period'], 'code' => $r['code'], 'gross' => (float)$r['gross'], 'deductions' => (float)$r['total_deductions'], 'net' => (float)$r['net']];
        }
        $snap['year'] = $year;
        $snap['months'] = $months;
        $snap['totals'] = ['gross' => round(array_sum(array_column($months, 'gross')), 2), 'deductions' => round(array_sum(array_column($months, 'deductions')), 2), 'net' => round(array_sum(array_column($months, 'net')), 2), 'tax' => round($tax, 2)];
    }
    return $snap;
}

/** Issues a document (new, or one an employee requested). Returns the document row or an error string. */
function document_issue(array $c, int $employeeId, string $type, array $opts, ?int $userId, ?int $requestId = null)
{
    if (!isset(doc_types()[$type])) return t('Choose a document type.');
    $e = db_one('SELECT * FROM employees WHERE id = ? AND company_id = ?', [$employeeId, $c['id']]);
    if (!$e) return t('Employee not found.');
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$c['id']]);
    $opts['addressed_to'] = mb_substr(trim((string)($opts['addressed_to'] ?? '')), 0, 200);
    $opts['purpose'] = mb_substr(trim((string)($opts['purpose'] ?? '')), 0, 200);
    if ($type === 'income_statement') {
        $opts['year'] = (int)($opts['year'] ?? date('Y'));
        if ($opts['year'] < 2000 || $opts['year'] > (int)date('Y')) return t('Choose a valid year.');
        if (!db_val("SELECT 1 FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.employee_id = ? AND p.status = 'issued' AND r.period LIKE ?", [$e['id'], $opts['year'] . '-%'])) {
            return t('There are no issued payslips for that year yet.');
        }
    }
    $row = [
        'company_id' => $c['id'], 'employee_id' => $e['id'], 'type' => $type, 'code' => new_document_code(), 'status' => 'issued',
        'addressed_to' => $opts['addressed_to'] ?: null, 'purpose' => $opts['purpose'] ?: null, 'year' => $type === 'income_statement' ? $opts['year'] : null,
        'snapshot' => json_encode(document_snapshot($type, $e, $c, $opts), JSON_UNESCAPED_UNICODE), 'issued_by' => $userId, 'issued_at' => now(),
    ];
    $row['hash'] = document_hash($row);
    if ($requestId) {
        db_update('documents', $row, 'id = ? AND company_id = ?', [$requestId, $c['id']]);
        $id = $requestId;
    } else {
        $id = db_insert('documents', $row + ['requested_by' => $userId, 'created_at' => now()]);
    }
    log_activity((int)$c['id'], 'Issued ' . doc_type_label($type) . ' ' . $row['code'] . ' for ' . $e['name']);
    return db_one('SELECT * FROM documents WHERE id = ?', [$id]);
}

function document_lookup(string $code): array
{
    $d = db_one('SELECT d.*, c.status AS company_status FROM documents d JOIN companies c ON c.id = d.company_id WHERE d.code = ?', [$code]);
    if (!$d || !in_array($d['status'], ['issued', 'revoked'], true)) return ['result' => 'not_found', 'code' => $code, 'kind' => 'document'];
    $ok = hash_equals((string)$d['hash'], document_hash($d));
    $d['snapshot'] = json_decode((string)$d['snapshot'], true) ?: [];
    currency_context(snapshot_currency($d['snapshot']));
    $res = ['code' => $code, 'kind' => 'document', 'document' => $d];
    $res['result'] = !$ok ? 'tampered' : ($d['status'] === 'revoked' ? 'revoked' : 'valid');
    return $res;
}

/* ---------------- Employer trust ---------------- */

/** What a verifier should know about the issuing employer itself. */
function company_trust(int $companyId): array
{
    $c = db_one('SELECT id, name, created_at, domain, domain_verified_at, business_verified_at FROM companies WHERE id = ?', [$companyId]);
    if (!$c) return ['level' => 0, 'checks' => []];
    $runs = (int)db_val("SELECT COUNT(*) FROM payroll_runs WHERE company_id = ? AND status = 'finalized'", [$companyId]);
    $first = db_val("SELECT MIN(period) FROM payroll_runs WHERE company_id = ? AND status = 'finalized'", [$companyId]);
    $staff = (int)db_val("SELECT COUNT(DISTINCT employee_id) FROM payslips WHERE company_id = ? AND status = 'issued' AND issued_at >= ?", [$companyId, date('Y-m-d H:i:s', strtotime('-90 days'))]);
    $checks = [
        'domain' => !empty($c['domain_verified_at']),
        'business' => !empty($c['business_verified_at']),
        'history' => $runs >= 3,
    ];
    return [
        'level' => array_sum(array_map('intval', $checks)),
        'checks' => $checks,
        'domain' => $checks['domain'] ? $c['domain'] : null,
        'since' => $c['created_at'],
        'runs' => $runs,
        'first_period' => $first,
        'staff' => $staff,
    ];
}

function domain_token(array $c, string $domain): string
{
    return 'payslip-verify=' . substr(hash_hmac('sha256', 'domain|' . $c['id'] . '|' . strtolower($domain), (string)cfg('app_key')), 0, 24);
}

function clean_domain(string $d): string
{
    $d = strtolower(trim($d));
    $d = preg_replace('#^https?://#', '', $d);
    $d = preg_replace('#^www\.#', '', $d);
    $d = explode('/', $d)[0];
    return preg_match('/^(?=.{3,190}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $d) ? $d : '';
}

/** Looks for the TXT record on the domain. */
function domain_check(array $c, string $domain): bool
{
    $want = domain_token($c, $domain);
    $recs = @dns_get_record($domain, DNS_TXT) ?: [];
    foreach ($recs as $r) {
        $txt = $r['txt'] ?? implode('', $r['entries'] ?? []);
        if (trim($txt) === $want) return true;
    }
    return false;
}

/* ---------------- Proof packs ---------------- */

function share_url(string $token): string
{
    return abs_url('/p/' . $token);
}

function share_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $s = db_one('SELECT * FROM shares WHERE token_hash = ?', [hash('sha256', $token)]);
    if (!$s) return null;
    $s['items'] = json_decode((string)$s['items'], true) ?: [];
    return $s;
}

function share_token(array $s): ?string
{
    return decrypt_str($s['token_enc']);
}

/** Creates a proof pack. Returns [share id, token] or an error string. */
function share_create(array $e, array $slipIds, array $docIds, string $mode, ?float $threshold, int $days, string $label, ?int $userId)
{
    $slipIds = array_map('intval', $slipIds);
    $docIds = array_map('intval', $docIds);
    if ($slipIds) $slipIds = array_map('intval', array_column(db_all('SELECT id FROM payslips WHERE employee_id = ? AND status = \'issued\' AND id IN (' . implode(',', $slipIds) . ')', [$e['id']]), 'id'));
    if ($docIds) $docIds = array_map('intval', array_column(db_all('SELECT id FROM documents WHERE employee_id = ? AND status = \'issued\' AND id IN (' . implode(',', $docIds) . ')', [$e['id']]), 'id'));
    if (!$slipIds && !$docIds) return t('Choose at least one payslip or document to include.');
    if (count($slipIds) > 24) return t('A proof pack can hold up to 24 payslips.');
    if (!in_array($mode, ['full', 'threshold', 'hidden'], true)) $mode = 'full';
    if ($mode === 'threshold' && (!$threshold || $threshold <= 0)) return t('Enter the minimum monthly net pay to confirm.');
    $days = in_array($days, [7, 30, 90], true) ? $days : 30;
    $token = bin2hex(random_bytes(16));
    $id = db_insert('shares', [
        'company_id' => $e['company_id'], 'employee_id' => $e['id'], 'token_hash' => hash('sha256', $token), 'token_enc' => encrypt_str($token),
        'label' => mb_substr(trim($label), 0, 120) ?: null,
        'items' => json_encode(['payslips' => $slipIds, 'documents' => $docIds, 'mode' => $mode, 'threshold' => $mode === 'threshold' ? round((float)$threshold, 2) : null]),
        'created_by' => $userId, 'created_at' => now(), 'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
    ]);
    return [$id, $token];
}

function share_state(array $s): string
{
    if ($s['revoked_at']) return 'revoked';
    if (strtotime($s['expires_at']) < time()) return 'expired';
    return 'active';
}
