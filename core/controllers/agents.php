<?php
declare(strict_types=1);

/* ---------------- Discovery files for AI agents ---------------- */

function agents_md(): void
{
    $o = site_origin();
    page_modified((int)filemtime(__FILE__));
    header('Content-Type: text/markdown; charset=utf-8');
    header('Last-Modified: ' . http_date((int)page_modified()));
    echo <<<MD
# AGENTS.md — {$o}

Guidance for AI agents and assistants working with PaySlip.tech.

## What this site is
PaySlip.tech is payroll software that issues payslips and employment documents (letters, salary certificates, income statements, tax deduction and settlement statements) with a QR code and a keyed digital fingerprint. Anyone can check that a document is genuine.

## The one thing agents usually need: verify a document
- Codes look like `PS-XXXX-XXXX` (payslips) or `DC-XXXX-XXXX` (documents). They are printed under the QR code.
- REST: `GET {$o}/api/v1/verify/{CODE}` → JSON. No key, no sign-in. Optional `?verifier=bank|embassy|landlord|employer|other`.
- OpenAPI 3.1: {$o}/openapi.json · API catalog: {$o}/.well-known/api-catalog
- MCP (Streamable HTTP, JSON-RPC 2.0): `POST {$o}/mcp`, tool `verify_document`. Server card: {$o}/.well-known/mcp.json
- A2A: agent card at {$o}/.well-known/agent-card.json, JSON-RPC endpoint `POST {$o}/a2a` (`message/send` with the code in the text).
- Results: `valid` (genuine), `revoked` (withdrawn by the employer), `tampered` (failed integrity check), `not_found`.
- Also report `employer_trust.level` (0–3). Level 0 means a new, unchecked employer: suggest confirming employment directly.

## Rules
- Never invent a result. If the code is malformed, ask the person to re-type it from the document.
- Do not guess or ask for bank or wallet numbers; the API never returns them.
- Every check is logged and shown to the employer. Do not run bulk or repeated checks (limit 120 per hour per IP).
- Errors are RFC 9457 problem details (`application/problem+json`).
- Signed-in areas (`/app`, `/me`, `/admin`) are for people. Do not automate sign-in or payroll actions.

## Reading the site
- Every public page is available as Markdown: send `Accept: text/markdown`.
- Summary for language models: {$o}/llms.txt · Sitemap: {$o}/sitemap.xml
- Guides: {$o}/blog · API docs: {$o}/developers · Privacy: {$o}/privacy

## Contact
contact@payslip.tech

MD;
    exit;
}

function openapi_json(): void
{
    header('Content-Type: application/vnd.oai.openapi+json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Last-Modified: ' . http_date((int)filemtime(APP . '/agents.php')));
    echo json_encode(openapi_spec(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/** RFC 9727 API catalog (linkset). */
function api_catalog(): void
{
    $o = site_origin();
    header('Content-Type: application/linkset+json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['linkset' => [[
        'anchor' => $o . '/api/v1/',
        'service-desc' => [['href' => $o . '/openapi.json', 'type' => 'application/vnd.oai.openapi+json']],
        'service-doc' => [['href' => $o . '/developers', 'type' => 'text/html']],
        'service-meta' => [['href' => $o . '/.well-known/mcp.json', 'type' => 'application/json'], ['href' => $o . '/.well-known/agent-card.json', 'type' => 'application/json'], ['href' => $o . '/.well-known/agents.json', 'type' => 'application/json']],
    ]]], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function mcp_server_card(): array
{
    $o = site_origin();
    return [
        'name' => 'payslip-tech',
        'title' => site_name() . ' verification',
        'description' => 'Verify payslips and employment documents issued on ' . site_name() . ' by their code.',
        'version' => APP_VERSION,
        'protocolVersion' => '2025-06-18',
        'endpoint' => $o . '/mcp',
        'transport' => ['type' => 'streamable-http', 'url' => $o . '/mcp'],
        'authentication' => ['required' => false],
        'capabilities' => ['tools' => ['listChanged' => false]],
        'tools' => [mcp_tool_def()],
        'documentation' => $o . '/developers',
    ];
}

function mcp_card(): void
{
    header('Access-Control-Allow-Origin: *');
    json_out(mcp_server_card());
}

function mcp_tool_def(): array
{
    return [
        'name' => 'verify_document',
        'title' => 'Verify a payslip or document',
        'description' => 'Check whether a payslip (PS-XXXX-XXXX) or employment document (DC-XXXX-XXXX) issued on ' . site_name() . ' is genuine. Returns valid, revoked, tampered or not_found, with employer, employee and employer trust level.',
        'inputSchema' => ['type' => 'object', 'properties' => [
            'code' => ['type' => 'string', 'description' => 'Code printed under the QR code, e.g. PS-7KQM-4XHD', 'pattern' => '^(PS|DC)-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$'],
            'verifier' => ['type' => 'string', 'enum' => ['bank', 'embassy', 'landlord', 'employer', 'other'], 'description' => 'Optional: who is checking'],
        ], 'required' => ['code']],
        'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false, 'idempotentHint' => true],
    ];
}

function rpc_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 65536) problem_out(413, 'Request too large');
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        header('Content-Type: application/json');
        echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        exit;
    }
    return $j;
}

function rpc_reply($id, ?array $result, ?array $error = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id] + ($error ? ['error' => $error] : ['result' => $result]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Minimal MCP server (Streamable HTTP, JSON responses). */
function mcp_endpoint(): void
{
    if (!rate_limit('mcp:' . client_ip(), 240, 3600)) problem_out(429, 'Too many requests', 'Try again later.');
    $req = rpc_body();
    $id = $req['id'] ?? null;
    $method = (string)($req['method'] ?? '');
    if (!array_key_exists('id', $req)) { http_response_code(202); exit; } // notification
    switch ($method) {
        case 'initialize':
            rpc_reply($id, ['protocolVersion' => '2025-06-18', 'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => ['name' => 'payslip-tech', 'title' => site_name(), 'version' => APP_VERSION], 'instructions' => 'Use verify_document with the code printed under the QR code. Report the result exactly; level-0 employer trust means the employer is new and unchecked.']);
        case 'ping':
            rpc_reply($id, []);
        case 'tools/list':
            rpc_reply($id, ['tools' => [mcp_tool_def()]]);
        case 'tools/call':
            $name = (string)($req['params']['name'] ?? '');
            $args = (array)($req['params']['arguments'] ?? []);
            if ($name !== 'verify_document') rpc_reply($id, null, ['code' => -32602, 'message' => 'Unknown tool: ' . $name]);
            $code = strtoupper(trim((string)($args['code'] ?? '')));
            if (!preg_match('/^(PS|DC)-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code)) {
                rpc_reply($id, ['content' => [['type' => 'text', 'text' => 'That does not look like a code. Codes look like PS-7KQM-4XHD or DC-7KQM-4XHD.']], 'isError' => true]);
            }
            $v = in_array($args['verifier'] ?? '', ['bank', 'embassy', 'landlord', 'employer', 'other'], true) ? (string)$args['verifier'] : 'ai_agent';
            $s = verify_summary($code, 'mcp', $v);
            rpc_reply($id, ['content' => [['type' => 'text', 'text' => verify_summary_text($s)]], 'structuredContent' => $s, 'isError' => false]);
        default:
            rpc_reply($id, null, ['code' => -32601, 'message' => 'Method not found: ' . $method]);
    }
}

function a2a_card(): array
{
    $o = site_origin();
    return [
        'protocolVersion' => '0.3.0',
        'name' => site_name() . ' Verifier',
        'description' => 'Verifies payslips and employment documents issued on ' . site_name() . '. Send a message containing a code like PS-7KQM-4XHD or DC-7KQM-4XHD.',
        'url' => $o . '/a2a',
        'preferredTransport' => 'JSONRPC',
        'version' => APP_VERSION,
        'provider' => ['organization' => site_name(), 'url' => $o],
        'documentationUrl' => $o . '/developers',
        'capabilities' => ['streaming' => false, 'pushNotifications' => false, 'stateTransitionHistory' => false],
        'defaultInputModes' => ['text/plain', 'application/json'],
        'defaultOutputModes' => ['text/plain', 'application/json'],
        'skills' => [[
            'id' => 'verify_document',
            'name' => 'Verify a payslip or document',
            'description' => 'Checks a PS- or DC- code and reports valid, revoked, tampered or not_found with the employer, employee and employer trust level.',
            'tags' => ['payslip', 'verification', 'income', 'employment', 'fraud'],
            'examples' => ['Is payslip PS-7KQM-4XHD genuine?', 'Verify DC-4R8T-ZK2M for a visa application'],
        ]],
    ];
}

function a2a_card_out(): void
{
    header('Access-Control-Allow-Origin: *');
    json_out(a2a_card());
}

/** Minimal A2A JSON-RPC endpoint: message/send. */
function a2a_endpoint(): void
{
    if (!rate_limit('a2a:' . client_ip(), 240, 3600)) problem_out(429, 'Too many requests', 'Try again later.');
    $req = rpc_body();
    $id = $req['id'] ?? null;
    if (($req['method'] ?? '') !== 'message/send') rpc_reply($id, null, ['code' => -32601, 'message' => 'Method not found. Supported: message/send']);
    $text = '';
    foreach ((array)($req['params']['message']['parts'] ?? []) as $p) {
        if (($p['kind'] ?? $p['type'] ?? '') === 'text') $text .= ' ' . (string)($p['text'] ?? '');
        if (($p['kind'] ?? '') === 'data') $text .= ' ' . json_encode($p['data'] ?? []);
    }
    $reply = function (string $t, ?array $data = null) use ($id, $req) {
        $parts = [['kind' => 'text', 'text' => $t]];
        if ($data) $parts[] = ['kind' => 'data', 'data' => $data];
        rpc_reply($id, ['kind' => 'message', 'role' => 'agent', 'messageId' => bin2hex(random_bytes(8)), 'contextId' => (string)($req['params']['message']['contextId'] ?? bin2hex(random_bytes(8))), 'parts' => $parts]);
    };
    if (!preg_match_all('/\b(PS|DC)-[A-Z0-9]{4}-[A-Z0-9]{4}\b/i', $text, $m)) $reply('Send the code printed under the QR code, for example PS-7KQM-4XHD or DC-7KQM-4XHD.');
    $codes = array_slice(array_unique(array_map('strtoupper', $m[0])), 0, 5);
    $all = array_map(fn($c) => verify_summary($c, 'a2a', 'ai_agent'), $codes);
    $reply(implode("\n", array_map('verify_summary_text', $all)), ['results' => $all]);
}

/** agents.json (intent → API flow map). */
function agents_json(): void
{
    $o = site_origin();
    header('Access-Control-Allow-Origin: *');
    json_out([
        'agentsJson' => '0.1.0',
        'info' => ['title' => site_name(), 'version' => APP_VERSION, 'description' => 'Verify payslips and employment documents issued on ' . site_name() . '.'],
        'sources' => [['id' => 'verify_api', 'path' => $o . '/openapi.json']],
        'overrides' => [],
        'flows' => [[
            'id' => 'verify_payslip_or_document',
            'title' => 'Verify a payslip or employment document',
            'description' => 'Use when someone gives you a PaySlip.tech code (PS-XXXX-XXXX or DC-XXXX-XXXX) and asks whether the document is genuine.',
            'actions' => [['id' => 'verify', 'sourceId' => 'verify_api', 'operationId' => 'verifyDocument']],
            'links' => [],
            'fields' => [
                'parameters' => [['name' => 'code', 'type' => 'string', 'required' => true, 'description' => 'The code under the QR code'], ['name' => 'verifier', 'type' => 'string', 'required' => false, 'description' => 'bank, embassy, landlord, employer or other']],
                'responses' => ['success' => ['type' => 'object', 'properties' => ['result' => ['type' => 'string'], 'employer_trust' => ['type' => 'object']]]],
            ],
        ]],
    ]);
}

/** OPTIONS pre-flight for the agent endpoints. */
function agent_preflight(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version');
    http_response_code(204);
    exit;
}
