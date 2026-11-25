<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $cache = null;
    $u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$u || $u['status'] !== 'active' || (int)($u['session_version'] ?? 1) !== (int)($_SESSION['sv'] ?? 1)) {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    // Suspended company locks out its users (but not the platform admin)
    if ($u['company_id'] && $u['role'] !== 'admin') {
        $st = db_val('SELECT status FROM companies WHERE id = ?', [$u['company_id']]);
        if ($st !== 'active') {
            unset($_SESSION['uid']);
            flash('error', t('This company account is suspended. Please contact support.'));
            return $cache = null;
        }
    }
    return $cache = $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    unset($_SESSION['_2fa_uid'], $_SESSION['_2fa_at']);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['sv'] = (int)($u['session_version'] ?? 1);
    $_SESSION['_born'] = time();
    $_SESSION['_last'] = time();
    $_SESSION['_ua'] = ua_fingerprint();
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    db_update('users', ['last_login_at' => now(), 'failed_logins' => 0, 'locked_until' => null], 'id = ?', [$u['id']]);
    if (!empty($u['lang'])) set_lang($u['lang']);
}

function logout_user(): void
{
    $lang = lang();
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['lang'] = $lang;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('info', t('Please sign in to continue.'));
        redirect('/login');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) abort(403);
    return $u;
}

function home_for(array $u): string
{
    return match ($u['role']) {
        'admin' => '/admin',
        'employee' => '/me',
        default => '/app',
    };
}

function my_company(): array
{
    $u = require_role('employer');
    $c = db_one('SELECT * FROM companies WHERE id = ?', [$u['company_id']]);
    if (!$c) abort(403);
    currency_context($c['currency'] ?? 'BDT');
    return $c;
}

function password_ok(string $p): bool
{
    return password_problem($p) === null;
}

/** Signs out every other session of this user (after password / 2FA changes). */
function bump_session_version(int $userId): void
{
    db_exec('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$userId]);
    $_SESSION['sv'] = (int)db_val('SELECT session_version FROM users WHERE id = ?', [$userId]);
}

function issue_token(int $userId, int $hours): string
{
    $token = bin2hex(random_bytes(24));
    db_update('users', ['token' => hash('sha256', $token), 'token_expires' => date('Y-m-d H:i:s', time() + $hours * 3600)], 'id = ?', [$userId]);
    return $token;
}

function user_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $u = db_one('SELECT * FROM users WHERE token = ? AND token_expires > ?', [hash('sha256', $token), now()]);
    return $u ?: null;
}
