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
        if ($method === 'POST' && !str_starts_with($path, '/webhooks/')) csrf_check();
        $args = array_values(array_filter($mt, 'is_int', ARRAY_FILTER_USE_KEY));
        array_shift($args);
        $handler(...$args);
        exit;
    }
}

abort(404);
