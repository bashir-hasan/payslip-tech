<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';

foreach (glob(APP . '/controllers/*.php') as $f) require $f;

$routes = require APP . '/routes.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
$path = '/' . trim($path, '/');
if ($path === '/index.php') $path = '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ?lang=bn / ?lang=en switches the interface language (used by hreflang URLs).
// Payslip documents use ?lang only for the document itself.
if ($method === 'GET' && isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'], true) && !str_starts_with($path, '/payslip/') && !str_starts_with($path, '/document/')) {
    set_lang($_GET['lang']);
}

foreach ($routes as [$m, $pattern, $handler]) {
    if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) continue;
    if (preg_match('#^' . $pattern . '$#', $path, $mt)) {
        if ($method === 'POST' && !str_starts_with($path, '/webhooks/') && !in_array($path, ['/mcp', '/a2a'], true)) csrf_check();
        $args = array_values(array_filter($mt, 'is_int', ARRAY_FILTER_USE_KEY));
        array_shift($args);
        $handler(...$args);
        exit;
    }
}

// Machine endpoints answer with RFC 9457 problem details, never an HTML page
if (str_starts_with($path, '/api/') || in_array($path, ['/mcp', '/a2a'], true) || str_starts_with($path, '/.well-known/')) {
    if ($path === '/mcp' && $method === 'GET') { header('Allow: POST, OPTIONS'); problem_out(405, 'Method not allowed', 'This MCP server does not offer an event stream. Send JSON-RPC requests with POST.'); }
    problem_out(404, 'Not found', 'No API resource at this address. See ' . site_origin() . '/openapi.json');
}
abort(404);
