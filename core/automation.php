<?php
declare(strict_types=1);

/**
 * Auto monthly payroll.
 * Each company can choose a pay day. A few days before it, a draft is prepared (employer is emailed to review
 * bonuses/overtime/unpaid days). On pay day the draft is finalised and payslips are emailed automatically.
 * Runs from cPanel cron:  php /home/USER/public_html/cron.php   (or the secret cron URL shown in admin).
 */

function automation_defaults(): array
{
    return ['enabled' => false, 'pay_day' => 'last', 'lead_days' => 3, 'working_days' => 30, 'auto_finalize' => true, 'auto_email' => true, 'notify' => true, 'enabled_at' => null, 'log' => []];
}

function automation_settings(array $c): array
{
    $a = json_decode((string)($c['automation'] ?? ''), true);
    return array_merge(automation_defaults(), is_array($a) ? $a : []);
}

function save_automation(int $companyId, array $a): void
{
    $a['log'] = array_slice($a['log'] ?? [], 0, 15);
    db_update('companies', ['automation' => json_encode($a, JSON_UNESCAPED_UNICODE)], 'id = ?', [$companyId]);
}

/** Pay date for a given month according to the company's setting. */
function automation_pay_date(array $a, string $period): string
{
    $last = (int)date('t', strtotime($period . '-01'));
    $day = $a['pay_day'] === 'last' ? $last : min($last, max(1, (int)$a['pay_day']));
    return sprintf('%s-%02d', $period, $day);
}

function automation_draft_date(array $a, string $period): string
{
    $pay = automation_pay_date($a, $period);
    $d = date('Y-m-d', strtotime($pay . ' -' . max(0, min(20, (int)$a['lead_days'])) . ' days'));
    return substr($d, 0, 7) === $period ? $d : $period . '-01';
}

function cron_token(): string
{
    return substr(hash_hmac('sha256', 'payslip-cron-v1', (string)cfg('app_key')), 0, 40);
}

/** Runs automation for every company. Returns log lines. */
function run_automation(?string $today = null): array
{
    $today = $today ?? date('Y-m-d');
    $period = substr($today, 0, 7);
    $out = [];
    $companies = db_all("SELECT * FROM companies WHERE status = 'active' AND automation IS NOT NULL AND automation LIKE '%enabled%'");
    foreach ($companies as $c) {
        $a = automation_settings($c);
        if (!$a['enabled']) continue;
        $payDate = automation_pay_date($a, $period);
        $draftDate = automation_draft_date($a, $period);
        // Never act on a month whose pay day had already passed when automation was switched on
        if (!empty($a['enabled_at']) && $payDate < substr((string)$a['enabled_at'], 0, 10)) continue;
        $owner = db_one("SELECT * FROM users WHERE company_id = ? AND role = 'employer' AND status = 'active' ORDER BY id LIMIT 1", [$c['id']]);
        currency_context($c['currency'] ?? 'BDT');
        $run = db_one('SELECT * FROM payroll_runs WHERE company_id = ? AND period = ?', [$c['id'], $period]);
        $label = period_label($period, 'en');

        // 1) Prepare the draft
        if (!$run && $today >= $draftDate) {
            $res = payroll_create_run($c, $period, (int)$a['working_days'], $payDate, null);
            if (is_int($res)) {
                $run = db_one('SELECT * FROM payroll_runs WHERE id = ?', [$res]);
                $msg = "Draft payroll for $label prepared automatically";
                array_unshift($a['log'], ['at' => now(), 'msg' => $msg]);
                log_activity((int)$c['id'], 'Auto payroll: ' . $msg);
                $out[] = "#{$c['id']} {$c['name']}: $msg";
                if ($a['notify'] && $owner && $today < $payDate) {
                    send_mail($owner['email'], "Review $label payroll before " . date('j M', strtotime($payDate)) . ' — ' . $c['name'],
                        "Hello {$owner['name']},\n\nYour $label payroll draft is ready. Add any bonus, overtime, advances or unpaid days before " . date('j F', strtotime($payDate)) . ($a['auto_finalize'] ? ", when payslips will be issued and emailed automatically." : '.'),
                        abs_url('/app/payroll/' . $res), 'Review payroll');
                }
            } else {
                array_unshift($a['log'], ['at' => now(), 'msg' => 'Could not prepare payroll: ' . $res]);
                $out[] = "#{$c['id']} {$c['name']}: skipped ($res)";
            }
        }

        // 2) Finalise and email on pay day
        if ($run && $run['status'] === 'draft' && $a['auto_finalize'] && $today >= $payDate) {
            $n = payroll_finalize_run($c, $run);
            $run = db_one('SELECT * FROM payroll_runs WHERE id = ?', [$run['id']]);
            $msg = "$n payslips for $label issued automatically";
            [$sent, $total] = $a['auto_email'] ? payroll_email_run($c, $run) : [0, 0];
            if ($a['auto_email']) $msg .= " and $sent/$total emailed";
            array_unshift($a['log'], ['at' => now(), 'msg' => $msg]);
            log_activity((int)$c['id'], 'Auto payroll: ' . $msg);
            $out[] = "#{$c['id']} {$c['name']}: $msg";
            if ($a['notify'] && $owner) {
                $total_net = (float)db_val('SELECT SUM(net) FROM payslips WHERE run_id = ?', [$run['id']]);
                send_mail($owner['email'], "$label payslips issued — " . $c['name'],
                    "Hello {$owner['name']},\n\n$msg.\nTotal net pay: " . money($total_net, 'en') . "\n\nDownload the bank / wallet payment sheet from the payroll page.",
                    abs_url('/app/payroll/' . $run['id']), 'Open payroll');
            }
        }
        save_automation((int)$c['id'], $a);
    }
    set_setting('cron_last_run', now());
    return $out;
}
