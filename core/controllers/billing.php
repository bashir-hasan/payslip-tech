<?php
declare(strict_types=1);

function emp_billing(): void
{
    $c = my_company();
    $active = (int)db_val("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'", [$c['id']]);
    if (input('checkout') === 'success') flash('success', t('Thank you! Your payment is being confirmed. Your plan updates within a minute.'));
    render('app/billing', ['title' => t('Plan & billing'), 'nav' => 'billing', 'c' => $c, 'current' => company_plan($c), 'plans' => plans(), 'active' => $active, 'enabled' => billing_enabled()], 'app');
}

function emp_billing_checkout(string $planKey): void
{
    $c = my_company();
    $u = current_user();
    $plans = plans();
    if (!billing_enabled() || !isset($plans[$planKey]) || $planKey === 'free' || $plans[$planKey]['product_id'] === '') {
        flash('error', t('Online payment is not available right now. Please contact support.'));
        redirect('/app/billing');
    }
    if ((int)$c['id'] === demo_cid()) {
        flash('info', t('Billing is disabled in the demo account.'));
        redirect('/app/billing');
    }
    if (!rate_limit('checkout:' . $c['id'], 10, 3600)) redirect('/app/billing');
    [$code, $res] = polar_request('POST', '/v1/checkouts/', [
        'products' => [$plans[$planKey]['product_id']],
        'customer_email' => $u['email'],
        'customer_name' => $c['name'],
        'external_customer_id' => 'company_' . $c['id'],
        'metadata' => ['company_id' => (string)$c['id'], 'plan' => $planKey],
        'success_url' => abs_url('/app/billing?checkout=success'),
        'return_url' => abs_url('/app/billing'),
    ]);
    if ($code >= 200 && $code < 300 && !empty($res['url']) && str_starts_with((string)$res['url'], 'https://')) {
        audit('Started checkout for ' . $planKey . ' plan', (int)$c['id']);
        analytics_event('checkout_start', $planKey);
        redirect((string)$res['url']);
    }
    error_log('[payslip] Polar checkout failed: HTTP ' . $code . ' ' . json_encode($res));
    flash('error', t('Could not open the payment page. Please try again in a moment.'));
    redirect('/app/billing');
}

function emp_billing_portal(): void
{
    $c = my_company();
    if (!billing_enabled() || !$c['polar_customer_id']) redirect('/app/billing');
    [$code, $res] = polar_request('POST', '/v1/customer-sessions/', ['customer_id' => $c['polar_customer_id']]);
    $url = (string)($res['customer_portal_url'] ?? '');
    if ($code >= 200 && $code < 300 && str_starts_with($url, 'https://')) redirect($url);
    flash('error', t('Could not open the billing portal. Please try again in a moment.'));
    redirect('/app/billing');
}

/** POST /webhooks/polar — no CSRF (signed by Polar instead). */
function webhook_polar(): void
{
    $payload = (string)file_get_contents('php://input');
    if (strlen($payload) > 512000) json_out(['ok' => false], 413);
    $headers = [
        'webhook-id' => $_SERVER['HTTP_WEBHOOK_ID'] ?? '',
        'webhook-timestamp' => $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '',
        'webhook-signature' => $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '',
    ];
    $secret = decrypt_str((string)setting('polar_webhook_secret')) ?? '';
    if (!polar_verify_webhook($payload, $headers, $secret)) {
        audit('Rejected webhook with invalid signature');
        json_out(['ok' => false, 'error' => 'invalid signature'], 401);
    }
    $event = json_decode($payload, true);
    $type = (string)($event['type'] ?? '');
    // Idempotency: Polar may deliver the same event more than once
    try {
        db_insert('billing_events', ['event_id' => mb_substr($headers['webhook-id'], 0, 100), 'type' => mb_substr($type, 0, 60), 'payload' => $payload, 'created_at' => now()]);
    } catch (PDOException $e) {
        json_out(['ok' => true, 'duplicate' => true]);
    }
    $cid = null;
    if (str_starts_with($type, 'subscription.') && is_array($event['data'] ?? null)) {
        $cid = apply_polar_subscription($event['data']);
        if ($cid) audit('Billing: ' . $type . ' (' . ($event['data']['status'] ?? '') . ')', $cid, null);
    }
    if ($cid) db_update('billing_events', ['company_id' => $cid], 'event_id = ?', [$headers['webhook-id']]);
    json_out(['ok' => true]);
}

/* ---------------- Admin ---------------- */

function admin_billing(): void
{
    require_role('admin');
    $byPlan = db_all("SELECT plan, COUNT(*) AS n FROM companies WHERE id <> ? AND plan_status IN ('active','trialing') GROUP BY plan", [demo_cid()]);
    $events = db_all('SELECT b.type, b.created_at, c.name FROM billing_events b LEFT JOIN companies c ON c.id = b.company_id ORDER BY b.id DESC LIMIT 20');
    render('admin/billing', ['title' => t('Billing & plans'), 'nav' => 'admin-billing', 'plans' => plans(), 'byPlan' => array_column($byPlan, 'n', 'plan'), 'events' => $events], 'app');
}

function admin_billing_save(): void
{
    require_role('admin');
    $plans = plans();
    foreach ($plans as $k => &$p) {
        $p['name'] = mb_substr(trim(strip_tags((string)input("{$k}_name"))), 0, 30) ?: $p['name'];
        $p['price'] = $k === 'free' ? 0 : round(max(0, (float)input("{$k}_price")), 2);
        $p['employees'] = max(1, min(100000, (int)input("{$k}_employees")));
        $p['smart'] = max(0, min(1000000, (int)input("{$k}_smart", (string)($p['smart'] ?? 30))));
        $p['team'] = max(0, min(1000, (int)input("{$k}_team", (string)($p['team'] ?? 0))));
        $p['badge'] = mb_substr(trim(strip_tags((string)input("{$k}_badge"))), 0, 30);
        $p['features'] = mb_substr(trim(strip_tags((string)input("{$k}_features"))), 0, 800);
        $pid = trim((string)input("{$k}_product_id"));
        $p['product_id'] = $k === 'free' ? '' : (preg_match('/^[0-9a-f-]{36}$/i', $pid) ? strtolower($pid) : '');
    }
    unset($p);
    set_setting('plans', json_encode($plans, JSON_UNESCAPED_UNICODE));
    set_setting('billing_enabled', input('billing_enabled') ? '1' : '0');
    set_setting('polar_sandbox', input('polar_sandbox') ? '1' : '0');
    $tok = trim((string)input('polar_token'));
    if ($tok !== '' && preg_match('/^polar_[a-z]+_[A-Za-z0-9]{10,}$/', $tok)) set_setting('polar_token', encrypt_str($tok));
    elseif ($tok !== '') flash('error', t('The Polar access token format looks wrong (it starts with polar_oat_).'));
    $whs = trim((string)input('polar_webhook_secret'));
    if ($whs !== '') set_setting('polar_webhook_secret', encrypt_str($whs));
    if (input('test_connection')) {
        [$code] = polar_request('GET', '/v1/products/?limit=1');
        flash($code === 200 ? 'success' : 'error', $code === 200 ? t('Connected to Polar successfully.') : sprintf(t('Polar connection failed (HTTP %d). Check the token, its scopes and sandbox setting.'), $code));
    }
    audit('Updated billing settings');
    flash('success', t('Billing settings saved.'));
    redirect('/admin/billing');
}

function admin_company_plan(string $id): void
{
    require_role('admin');
    $plan = (string)input('plan');
    if (!isset(plans()[$plan])) redirect('/admin/companies');
    db_update('companies', ['plan' => $plan, 'plan_status' => $plan === 'free' ? null : 'active', 'plan_renews_at' => null], 'id = ?', [(int)$id]);
    audit('Admin set plan to ' . $plan, (int)$id);
    flash('success', t('Plan updated.'));
    back('/admin/companies');
}
