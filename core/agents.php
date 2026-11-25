<?php
declare(strict_types=1);

/* ============================================================
 * AI agent readiness: Markdown versions of public pages
 * (content negotiation), Last-Modified, RFC 9457 problem
 * details for the API, OpenAPI 3.1, API catalog (RFC 9727),
 * an MCP server, an A2A agent card and agents.json.
 * ============================================================ */

/** True when the client asks for Markdown (Accept: text/markdown) ahead of HTML. */
function wants_markdown(): bool
{
    $a = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (!str_contains($a, 'text/markdown')) return false;
    $q = function (string $type) use ($a): ?float {
        foreach (explode(',', $a) as $part) {
            $bits = array_map('trim', explode(';', $part));
            if ($bits[0] !== $type) continue;
            foreach (array_slice($bits, 1) as $b) if (str_starts_with($b, 'q=')) return (float)substr($b, 2);
            return 1.0;
        }
        return null;
    };
    $md = $q('text/markdown');
    $html = $q('text/html');
    return $md !== null && $md > 0 && ($html === null || $md >= $html);
}

/** Page freshness for Last-Modified: set by controllers, otherwise the view file's time. */
function page_modified(?int $ts = null): ?int
{
    static $v = null;
    if ($ts !== null) $v = $ts;
    return $v;
}

function http_date(int $ts): string
{
    return gmdate('D, d M Y H:i:s', $ts) . ' GMT';
}

/** Converts rendered page HTML into tidy Markdown for agents. */
function html_to_markdown(string $html): string
{
    if (trim($html) === '') return '';
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) return trim(strip_tags($html));
    $skip = ['script', 'style', 'svg', 'form', 'button', 'nav', 'dialog', 'noscript', 'template', 'iframe', 'select', 'input', 'textarea'];
    $walk = function (DOMNode $n, int $depth = 0) use (&$walk, $skip): string {
        if ($n instanceof DOMText) return preg_replace('/\s+/u', ' ', $n->nodeValue);
        if (!$n instanceof DOMElement) return '';
        $tag = strtolower($n->tagName);
        if (in_array($tag, $skip, true) || $n->hasAttribute('hidden') || $n->getAttribute('aria-hidden') === 'true') return '';
        $inner = function () use ($n, $walk, $depth) { $s = ''; foreach ($n->childNodes as $c) $s .= $walk($c, $depth); return $s; };
        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                return "\n\n" . str_repeat('#', (int)$tag[1]) . ' ' . trim($inner()) . "\n\n";
            case 'p': return "\n\n" . trim($inner()) . "\n\n";
            case 'br': return "  \n";
            case 'hr': return "\n\n---\n\n";
            case 'strong': case 'b': $t = trim($inner()); return $t === '' ? '' : '**' . $t . '**';
            case 'em': case 'i': $t = trim($inner()); return $t === '' ? '' : '*' . $t . '*';
            case 'code': return '`' . trim($n->textContent) . '`';
            case 'pre': return "\n\n```\n" . rtrim($n->textContent) . "\n```\n\n";
            case 'a':
                $t = trim($inner());
                $href = $n->getAttribute('href');
                if ($t === '' || $href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) return $t;
                if (str_starts_with($href, '/')) $href = site_origin() . $href;
                return '[' . $t . '](' . $href . ')';
            case 'img': $alt = trim($n->getAttribute('alt')); return $alt !== '' ? '![' . $alt . '](' . $n->getAttribute('src') . ')' : '';
            case 'ul': case 'ol':
                $out = "\n";
                $i = 0;
                foreach ($n->childNodes as $c) {
                    if (!$c instanceof DOMElement || strtolower($c->tagName) !== 'li') continue;
                    $i++;
                    $item = trim(preg_replace('/\n{2,}/', "\n", $walk($c, $depth + 1)));
                    $out .= str_repeat('  ', $depth) . ($tag === 'ol' ? $i . '. ' : '- ') . str_replace("\n", "\n" . str_repeat('  ', $depth + 1), $item) . "\n";
                }
                return $out . "\n";
            case 'li': return $inner();
            case 'table':
                $rows = [];
                foreach ($n->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    foreach ($tr->childNodes as $td) if ($td instanceof DOMElement && in_array(strtolower($td->tagName), ['td', 'th'], true)) $cells[] = trim(preg_replace('/\s+/u', ' ', str_replace('|', '\|', $walk($td, $depth))));
                    if ($cells) $rows[] = $cells;
                }
                if (!$rows) return '';
                $w = max(array_map('count', $rows));
                $line = fn($r) => '| ' . implode(' | ', array_pad($r, $w, '')) . ' |';
                $md = "\n\n" . $line($rows[0]) . "\n|" . str_repeat(' --- |', $w) . "\n";
                foreach (array_slice($rows, 1) as $r) $md .= $line($r) . "\n";
                return $md . "\n";
            case 'dl':
                $md = "\n";
                foreach ($n->childNodes as $c) {
                    if (!$c instanceof DOMElement) continue;
                    $t = trim(preg_replace('/\s+/u', ' ', $walk($c, $depth)));
                    if (strtolower($c->tagName) === 'dt') $md .= "\n**" . $t . '**: ';
                    elseif (strtolower($c->tagName) === 'dd') $md .= $t;
                    else $md .= $t;
                }
                return $md . "\n\n";
            case 'div': case 'section': case 'article': case 'header': case 'footer': case 'aside': case 'main': case 'details': case 'summary': case 'figure':
                return "\n" . $inner() . "\n";
            default: return $inner();
        }
    };
    $md = $walk($root);
    $md = preg_replace("/[ \t]+\n/", "\n", $md);
    $md = preg_replace("/\n{3,}/", "\n\n", $md);
    return trim($md) . "\n";
}

/** Sends a Markdown version of a public page and stops. */
function send_markdown_page(string $title, string $body, string $description = ''): never
{
    $ts = page_modified() ?? time();
    header('Content-Type: text/markdown; charset=utf-8');
    header('Vary: Accept');
    header('Last-Modified: ' . http_date($ts));
    header('X-Robots-Tag: noindex');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $front = "---\ntitle: " . json_encode($title, JSON_UNESCAPED_UNICODE) . "\nurl: " . site_origin() . $path . "\n" . ($description !== '' ? 'description: ' . json_encode($description, JSON_UNESCAPED_UNICODE) . "\n" : '') . 'updated: ' . date('c', $ts) . "\n---\n\n";
    if (!preg_match('/^\s*# \S/m', $body)) $body = '# ' . $title . "\n\n" . $body;
    echo $front . $body;
    exit;
}

/** Headers every public HTML page gets: freshness, Vary and a pointer to its Markdown twin. */
function public_page_headers(): void
{
    if (headers_sent()) return;
    $ts = page_modified();
    if ($ts) header('Last-Modified: ' . http_date($ts));
    header('Vary: Accept, Accept-Encoding');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    header('Link: <' . site_origin() . $path . '>; rel="alternate"; type="text/markdown", <' . site_origin() . '/.well-known/api-catalog>; rel="api-catalog"', false);
}

/* ---------------- Problem details (RFC 9457) ---------------- */

function problem_out(int $status, string $title, string $detail = '', array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/problem+json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    echo json_encode(['type' => site_origin() . '/developers#error-' . trim($slug, '-'), 'title' => $title, 'status' => $status] + ($detail !== '' ? ['detail' => $detail] : []) + ['instance' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)] + $extra, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- Machine-readable API description ---------------- */

function openapi_spec(): array
{
    $o = site_origin();
    $doc = [
        'type' => 'object',
        'properties' => [
            'ok' => ['type' => 'boolean'], 'code' => ['type' => 'string', 'example' => 'PS-7KQM-4XHD'],
            'result' => ['type' => 'string', 'enum' => ['valid', 'revoked', 'tampered', 'not_found'], 'description' => 'valid = issued and unchanged; revoked = withdrawn by the employer; tampered = stored record fails its integrity check; not_found = no such code.'],
            'type' => ['type' => 'string', 'enum' => ['document'], 'description' => 'Present for DC- document codes.'],
            'checked_at' => ['type' => 'string', 'format' => 'date-time'],
            'payslip' => ['$ref' => '#/components/schemas/Payslip'],
            'document' => ['$ref' => '#/components/schemas/Document'],
            'employer_trust' => ['$ref' => '#/components/schemas/EmployerTrust'],
        ],
        'required' => ['ok', 'code', 'result', 'checked_at'],
    ];
    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => site_name() . ' Verification API',
            'version' => '1.0.0',
            'summary' => 'Check that a payslip or employment document issued on ' . site_name() . ' is genuine.',
            'description' => 'Free, read-only and needs no API key. Payslip codes look like PS-XXXX-XXXX; letters, salary certificates, income statements, tax certificates and settlement statements use DC-XXXX-XXXX. Bank and wallet numbers are never returned. Amounts appear only when the employer allows it. Limit: 120 requests per hour per IP. Errors use RFC 9457 problem details.',
            'contact' => ['email' => setting('support_email', '') ?: 'contact@payslip.tech', 'url' => $o . '/developers'],
            'termsOfService' => $o . '/terms',
        ],
        'servers' => [['url' => $o]],
        'security' => [],
        'externalDocs' => ['url' => $o . '/developers'],
        'paths' => [
            '/api/v1/verify/{code}' => ['get' => [
                'operationId' => 'verifyDocument',
                'summary' => 'Verify a payslip or document by its code',
                'description' => 'Returns the verification result and the details printed on the original. Each call is logged so the employer can see that their document was checked.',
                'tags' => ['Verification'],
                'security' => [],
                'parameters' => [
                    ['name' => 'code', 'in' => 'path', 'required' => true, 'description' => 'The code printed under the QR code.', 'schema' => ['type' => 'string', 'pattern' => '^(PS|DC)-[A-Z0-9]{4}-[A-Z0-9]{4}$'], 'example' => 'PS-7KQM-4XHD'],
                    ['name' => 'verifier', 'in' => 'query', 'required' => false, 'description' => 'What kind of organisation is checking.', 'schema' => ['type' => 'string', 'enum' => ['bank', 'embassy', 'landlord', 'employer', 'other']]],
                ],
                'responses' => [
                    '200' => ['description' => 'Verification result (including not_found).', 'content' => ['application/json' => ['schema' => $doc]]],
                    '429' => ['description' => 'Too many requests.', 'headers' => ['Retry-After' => ['schema' => ['type' => 'integer']]], 'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]],
                    'default' => ['description' => 'Error', 'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]],
                ],
            ]],
        ],
        'components' => [
            'securitySchemes' => new stdClass(),
            'schemas' => [
                'Payslip' => ['type' => 'object', 'properties' => [
                    'employer' => ['type' => 'string'], 'employee_name' => ['type' => 'string'], 'employee_id' => ['type' => 'string'], 'designation' => ['type' => ['string', 'null']],
                    'period' => ['type' => 'string', 'example' => '2026-10'], 'currency' => ['type' => 'string', 'example' => 'BDT'], 'issued_at' => ['type' => 'string', 'format' => 'date-time'],
                    'fingerprint' => ['type' => 'string'], 'gross' => ['type' => 'number'], 'total_deductions' => ['type' => 'number'], 'net' => ['type' => 'number'],
                    'revoked_at' => ['type' => 'string', 'format' => 'date-time'], 'revoke_reason' => ['type' => 'string'],
                ]],
                'Document' => ['type' => 'object', 'properties' => [
                    'kind' => ['type' => 'string', 'enum' => array_keys(doc_types())], 'employer' => ['type' => 'string'], 'employee_name' => ['type' => 'string'], 'employee_id' => ['type' => 'string'],
                    'designation' => ['type' => ['string', 'null']], 'join_date' => ['type' => ['string', 'null'], 'format' => 'date'], 'currently_employed' => ['type' => 'boolean'],
                    'issued_at' => ['type' => 'string', 'format' => 'date-time'], 'fingerprint' => ['type' => 'string'], 'monthly_net' => ['type' => 'number'],
                    'year' => ['type' => 'integer'], 'annual_net' => ['type' => 'number'], 'months' => ['type' => 'integer'], 'revoke_reason' => ['type' => 'string'],
                ]],
                'EmployerTrust' => ['type' => 'object', 'properties' => [
                    'level' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 3], 'domain' => ['type' => ['string', 'null']], 'business_checked' => ['type' => 'boolean'], 'payroll_months' => ['type' => 'integer'],
                ]],
                'Problem' => ['type' => 'object', 'description' => 'RFC 9457 problem details', 'properties' => [
                    'type' => ['type' => 'string', 'format' => 'uri'], 'title' => ['type' => 'string'], 'status' => ['type' => 'integer'], 'detail' => ['type' => 'string'], 'instance' => ['type' => 'string'],
                ], 'required' => ['type', 'title', 'status']],
            ],
        ],
    ];
}

/** Shared verification summary used by the API, MCP and A2A answers. */
function verify_summary(string $code, string $channel, ?string $verifier = null): array
{
    $res = verify_lookup($code);
    log_verification($res, $channel, $verifier);
    $out = ['code' => $res['code'], 'result' => $res['result'], 'checked_at' => date('c'), 'verify_url' => verify_url($res['code'])];
    if (in_array($res['result'], ['valid', 'revoked'], true)) {
        if (($res['kind'] ?? '') === 'document') {
            $d = $res['document'];
            $s = $d['snapshot'];
            $out += ['kind' => $d['type'], 'employer' => $s['company']['name'] ?? null, 'employee_name' => $s['employee']['name'] ?? null, 'designation' => $s['employee']['designation'] ?? null, 'issued_at' => date('c', strtotime($d['issued_at']))];
            $cid = (int)$d['company_id'];
        } else {
            $p = $res['payslip'];
            $s = $p['snapshot'];
            $out += ['kind' => 'payslip', 'employer' => $s['company']['name'] ?? null, 'employee_name' => $s['employee']['name'] ?? null, 'designation' => $s['employee']['designation'] ?? null, 'period' => $p['period'], 'issued_at' => date('c', strtotime($p['issued_at']))];
            if ((int)$p['show_amounts'] === 1) $out += ['currency' => snapshot_currency($s), 'gross' => (float)$p['gross'], 'net' => (float)$p['net']];
            $cid = (int)$p['company_id'];
        }
        $tr = company_trust($cid);
        $out['employer_trust'] = ['level' => $tr['level'], 'domain' => $tr['domain'], 'business_checked' => $tr['checks']['business'], 'payroll_months' => $tr['runs']];
    }
    return $out;
}

function verify_summary_text(array $s): string
{
    $label = ['valid' => 'GENUINE', 'revoked' => 'REVOKED by the employer — do not accept', 'tampered' => 'FAILED the integrity check — do not accept', 'not_found' => 'NOT FOUND — no document was issued with this code'][$s['result']] ?? $s['result'];
    $t = $s['code'] . ': ' . $label . '.';
    if (!empty($s['employer'])) {
        $t .= ' ' . ucfirst(str_replace('_', ' ', (string)$s['kind'])) . ' issued by ' . $s['employer'] . ' for ' . $s['employee_name'] . (!empty($s['period']) ? ' (' . $s['period'] . ')' : '') . '.';
        if (isset($s['net'])) $t .= ' Net pay ' . number_format($s['net'], 2) . ' ' . $s['currency'] . '.';
        $t .= ' Employer trust level ' . $s['employer_trust']['level'] . '/3.';
    }
    return $t . ' Details: ' . $s['verify_url'];
}
