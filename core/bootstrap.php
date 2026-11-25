<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('APP_VERSION', '1.7.0');

if (!is_file(ROOT . '/config.php')) {
    header('Location: ' . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/install.php');
    exit;
}

$CONFIG = require ROOT . '/config.php';

date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Dhaka');
error_reporting(E_ALL);
ini_set('display_errors', !empty($CONFIG['debug']) ? '1' : '0');
ini_set('log_errors', '1');

require APP . '/helpers.php';
require APP . '/db.php';
require APP . '/i18n.php';
require APP . '/auth.php';
require APP . '/payroll.php';
require APP . '/mail.php';
require APP . '/security.php';
require APP . '/migrate.php';
require APP . '/seo.php';
require APP . '/locale.php';
require APP . '/billing.php';
require APP . '/automation.php';
require APP . '/chatbot.php';
require APP . '/blog.php';
require APP . '/smart.php';
require APP . '/team.php';
require APP . '/hr.php';
require APP . '/documents.php';
require APP . '/extras.php';

// Security: headers, hardened session, strict CSP by default (public layout may relax it)
base_security_headers();
send_security_headers(false);
start_secure_session();

// Database migrations (no-op once up to date) and housekeeping
run_migrations();
gc_rate_limits();

set_exception_handler(function (Throwable $e) {
    error_log('[payslip] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (cfg('debug')) {
        echo '<pre>' . e((string)$e) . '</pre>';
    } else {
        render('public/error', ['code' => 500, 'title' => t('Something went wrong'), 'message' => t('Please try again in a moment.')], 'public');
    }
    exit;
});
