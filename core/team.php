<?php
declare(strict_types=1);

/**
 * Team roles inside a company account.
 *  owner      — everything, including billing, settings, team and auto payroll
 *  hr         — employees, leave, loans, documents, prepares payroll and submits it for approval
 *  accountant — reviews, approves and finalises payroll, emails and revokes payslips, issues documents
 */
function company_roles(): array
{
    return [
        'owner' => t('Owner'),
        'hr' => t('HR manager'),
        'accountant' => t('Accountant / approver'),
    ];
}

function company_role(?array $u = null): string
{
    $u = $u ?? current_user();
    if (!$u || $u['role'] !== 'employer') return '';
    $r = (string)($u['company_role'] ?? '');
    return isset(company_roles()[$r]) ? $r : 'owner';
}

/** Can the signed-in team member use this /app path? Owners can use everything. */
function role_allows(string $role, string $method, string $path): bool
{
    if ($role === 'owner') return true;
    $path = '/' . trim($path, '/');
    // Owner-only areas
    foreach (['#^/app/(billing|settings|team|automation)(/|$)#'] as $re) if (preg_match($re, $path)) return false;
    if ($method === 'GET') return true; // everyone in the team can look
    $rules = [
        'hr' => [
            '#^/app/employees(/|$)#', '#^/app/leave(/|$)#', '#^/app/loans(/|$)#', '#^/app/documents(/|$)#', '#^/app/assistant(/|$)#',
            '#^/app/payroll/create$#', '#^/app/payroll/\d+/slip/\d+$#', '#^/app/payroll/\d+/(submit|delete)$#',
        ],
        'accountant' => [
            '#^/app/documents(/|$)#', '#^/app/payroll/\d+/(finalize|email|reject)$#', '#^/app/payslips/\d+/revoke$#',
        ],
    ];
    foreach ($rules[$role] ?? [] as $re) if (preg_match($re, $path)) return true;
    return false;
}

/** Called by my_company(): stops team members from reaching pages their role does not allow. */
function enforce_company_role(array $u): void
{
    $role = company_role($u);
    if ($role === 'owner') return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
    if (!str_starts_with($path, '/app')) return;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (role_allows($role, $method, $path)) return;
    flash('error', sprintf(t('Your role (%s) cannot do this. Ask the account owner.'), company_roles()[$role]));
    if ($method === 'GET') redirect('/app');
    back('/app');
}

function can_do(string $what, ?array $u = null): bool
{
    $role = company_role($u);
    $map = [
        'finalize' => ['owner', 'accountant'],
        'submit' => ['owner', 'hr'],
        'edit_payroll' => ['owner', 'hr'],
        'manage_people' => ['owner', 'hr'],
        'documents' => ['owner', 'hr', 'accountant'],
        'revoke' => ['owner', 'accountant'],
        'owner' => ['owner'],
    ];
    return in_array($role, $map[$what] ?? [], true);
}

/** Extra team members (beyond the owner) allowed on the company's plan. null = unlimited. */
function team_limit(array $c): ?int
{
    if ((int)$c['id'] === demo_cid() || !billing_enabled()) return null;
    return max(0, (int)(company_plan($c)['team'] ?? 0));
}

function team_members(array $c): array
{
    return db_all("SELECT id, name, email, company_role, status, last_login_at, created_at FROM users WHERE company_id = ? AND role = 'employer' ORDER BY FIELD(company_role,'owner','accountant','hr'), name", [$c['id']]);
}

/** Emails everyone in the company with one of the given roles. */
function notify_roles(array $c, array $roles, string $subject, string $body, ?string $path = null, ?string $label = null): int
{
    $in = implode(',', array_fill(0, count($roles), '?'));
    $rows = db_all("SELECT email FROM users WHERE company_id = ? AND role = 'employer' AND status = 'active' AND COALESCE(company_role,'owner') IN ($in)", array_merge([$c['id']], $roles));
    $n = 0;
    foreach ($rows as $r) if (send_mail($r['email'], $subject, $body, $path ? abs_url($path) : null, $label)) $n++;
    return $n;
}
