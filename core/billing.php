<?php
declare(strict_types=1);

/**
 * Plans and Polar.sh billing.
 * Polar is the Merchant of Record: it charges customers worldwide, handles sales tax / VAT
 * and pays out to you. We only create checkout sessions and listen to webhooks.
 */

function default_plans(): array
{
    return [
        'free' => ['name' => 'Free', 'price' => 0, 'interval' => 'month', 'employees' => 5, 'smart' => 30, 'team' => 0, 'product_id' => '', 'badge' => '', 'features' => "Up to 5 employees\n30 smart automation actions / month\nAuto monthly payroll\nVerified payslips with QR code\nVerified letters & salary certificates\nProof-of-income links for employees\nLeave & salary advances\nEmployee portal & phone app\nEmail payslips\nVerification log"],
        'starter' => ['name' => 'Starter', 'price' => 9, 'interval' => 'month', 'employees' => 30, 'smart' => 300, 'team' => 2, 'product_id' => '', 'badge' => '', 'features' => "Up to 30 employees\n300 smart automation actions / month\nEverything in Free\n2 team members (HR, accountant)\nOne-click festival bonus\nBank & bKash payment files\nSend payslips on WhatsApp\nPaste bonuses from Excel\nChecks before issuing\nReports & Excel exports\nPay-day & Labour Act reminders\nCSV import & payment export\nCustom fields on payslips\nEmail support"],
        'business' => ['name' => 'Business', 'price' => 29, 'interval' => 'month', 'employees' => 250, 'smart' => 2000, 'team' => 10, 'product_id' => '', 'badge' => 'Most popular', 'features' => "Up to 250 employees\n2,000 smart automation actions / month\nEverything in Starter\n10 team members with payroll approval\nIncrements with verified letters\nCompany seal & signature on documents\nTax deduction certificates\nFinal settlement statements\nRemove PaySlip.tech branding\nPriority verification API limits\nPriority support"],
    ];
}

function plans(): array
{
    $p = json_decode((string)setting('plans', ''), true);
    if (!is_array($p) || !$p) return default_plans();
    foreach (default_plans() as $k => $d) $p[$k] = array_merge($d, $p[$k] ?? []);
    return $p;
}

function company_plan(array $c): array
{
    $all = plans();
    $key = isset($all[$c['plan'] ?? 'free']) ? ($c['plan'] ?? 'free') : 'free';
    // A paid plan only counts while its subscription is active or trialing
    if ($key !== 'free' && !in_array($c['plan_status'] ?? '', ['active', 'trialing'], true)) $key = 'free';
    return ['key' => $key] + $all[$key];
}

function billing_enabled(): bool
{
    return setting('billing_enabled', '0') === '1' && (string)setting('polar_token') !== '';
}

/** Max active employees for a company (demo company and billing-off sites are unlimited). */
function employee_limit(array $c): ?int
{
    if ((int)$c['id'] === demo_cid() || !billing_enabled()) return null;
    return max(1, (int)company_plan($c)['employees']);
}

function within_employee_limit(array $c, int $adding = 1): bool
{
    $limit = employee_limit($c);
    if ($limit === null) return true;
    $active = (int)db_val("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'", [$c['id']]);
    return $active + $adding <= $limit;
}

function shows_branding(array $c): bool
{
    return !billing_enabled() || company_plan($c)['key'] !== 'business';
}

/* ---------------- Polar API ---------------- */

function polar_base(): string
{
    return setting('polar_sandbox', '0') === '1' ? 'https://sandbox-api.polar.sh' : 'https://api.polar.sh';
}

/** @return array{0:int,1:array|null} */
function polar_request(string $method, string $path, ?array $body = null): array
{
    $token = decrypt_str((string)setting('polar_token')) ?? '';
    if ($token === '' || !function_exists('curl_init')) return [0, null];
    $ch = curl_init(polar_base() . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json', 'User-Agent: PaySlip.tech/' . APP_VERSION],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($res) ? json_decode($res, true) : null];
}

/**
 * Verifies a Standard Webhooks signature (used by Polar).
 * Supports both secret formats Polar has issued: the base64 "whsec_" key and the older raw-string key.
 */
function polar_verify_webhook(string $payload, array $headers, string $secret): bool
{
    $id = $headers['webhook-id'] ?? '';
    $ts = $headers['webhook-timestamp'] ?? '';
    $sigHeader = $headers['webhook-signature'] ?? '';
    if ($id === '' || !ctype_digit($ts) || $sigHeader === '' || $secret === '') return false;
    if (abs(time() - (int)$ts) > 300) return false; // 5-minute replay window
    $signed = $id . '.' . $ts . '.' . $payload;
    $keys = [$secret];
    if (str_starts_with($secret, 'whsec_')) {
        $decoded = base64_decode(substr($secret, 6), true);
        if ($decoded !== false) array_unshift($keys, $decoded);
    }
    foreach ($keys as $key) {
        $expected = base64_encode(hash_hmac('sha256', $signed, $key, true));
        foreach (preg_split('/\s+/', trim($sigHeader)) as $part) {
            $sig = str_contains($part, ',') ? substr($part, strpos($part, ',') + 1) : $part;
            if (hash_equals($expected, $sig)) return true;
        }
    }
    return false;
}

/** Applies a subscription object from Polar to the matching company. */
function apply_polar_subscription(array $sub): ?int
{
    // The Polar organization may sell other products and send their events here too.
    // Only act on subscriptions for this site's own products.
    $productId = (string)($sub['product_id'] ?? ($sub['product']['id'] ?? ''));
    $ours = array_filter(array_map(fn($p) => (string)($p['product_id'] ?? ''), plans()));
    if ($productId === '' || !in_array($productId, $ours, true)) return null;

    $meta = $sub['metadata'] ?? [];
    $cid = (int)($meta['company_id'] ?? 0);
    if (!$cid && preg_match('/^company_(\d+)$/', (string)($sub['customer']['external_id'] ?? ''), $m)) $cid = (int)$m[1];
    if (!$cid && !empty($sub['id'])) $cid = (int)db_val('SELECT id FROM companies WHERE polar_subscription_id = ?', [$sub['id']]);
    if (!$cid || !db_val('SELECT 1 FROM companies WHERE id = ?', [$cid])) return null;

    $planKey = 'free';
    foreach (plans() as $k => $p) if ($k !== 'free' && $p['product_id'] !== '' && $p['product_id'] === $productId) $planKey = $k;
    $status = (string)($sub['status'] ?? '');
    $end = $sub['current_period_end'] ?? null;
    db_update('companies', [
        'plan' => in_array($status, ['active', 'trialing'], true) ? $planKey : 'free',
        'plan_status' => mb_substr($status, 0, 20),
        'plan_renews_at' => $end ? date('Y-m-d H:i:s', strtotime((string)$end)) : null,
        'plan_cancel_at_end' => !empty($sub['cancel_at_period_end']) ? 1 : 0,
        'polar_customer_id' => mb_substr((string)($sub['customer_id'] ?? ($sub['customer']['id'] ?? '')), 0, 64) ?: null,
        'polar_subscription_id' => mb_substr((string)($sub['id'] ?? ''), 0, 64) ?: null,
    ], 'id = ?', [$cid]);
    return $cid;
}

/* ---------------- Smart automation usage (monthly limit per plan) ---------------- */

function smart_limit(array $c): int
{
    if ((int)$c['id'] === demo_cid()) return 1000;
    return max(0, (int)(company_plan($c)['smart'] ?? 30));
}

function smart_used(array $c): int
{
    return (int)db_val('SELECT used FROM smart_usage WHERE company_id = ? AND ym = ?', [$c['id'], date('Y-m')]);
}

function smart_remaining(array $c): int
{
    return max(0, smart_limit($c) - smart_used($c));
}

/** Counts one smart action. Returns false (and counts nothing) when the monthly limit is reached. */
function smart_consume(array $c, int $n = 1): bool
{
    if (smart_remaining($c) < $n) return false;
    db_exec('INSERT INTO smart_usage (company_id, ym, used) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE used = used + VALUES(used)', [$c['id'], date('Y-m'), $n]);
    return true;
}
