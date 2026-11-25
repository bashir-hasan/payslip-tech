<?php
declare(strict_types=1);
/**
 * PaySlip.tech scheduled jobs (auto monthly payroll).
 * cPanel → Cron Jobs → "Once per hour":
 *   /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/cron.php >/dev/null 2>&1
 * Web requests to this file are refused; use the secret cron URL from Admin → Automation instead if needed.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = '/cron';
require __DIR__ . '/core/bootstrap.php';
require __DIR__ . '/core/controllers/privacy.php';
foreach (run_automation() as $line) echo $line, PHP_EOL;
foreach (run_reminders() as $line) echo $line, PHP_EOL;
foreach (run_retention() as $line) echo $line, PHP_EOL;
echo 'Done at ', date('c'), PHP_EOL;
