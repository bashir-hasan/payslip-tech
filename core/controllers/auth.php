<?php
declare(strict_types=1);

const MAX_FAILED_LOGINS = 5;
const LOCK_MINUTES = 15;

function auth_login_form(): void
{
    if ($u = current_user()) redirect(home_for($u));
    render('auth/login', ['title' => t('Sign in'), 'seo_page' => 'login', 'description' => t('Sign in to PaySlip.tech to run payroll or view your payslips.')], 'auth');
}

function auth_login(): void
{
    $email = mb_strtolower(trim((string)input('email')));
    $pass = (string)($_POST['password'] ?? '');
    set_old(['email' => $email]);
    $fail = function (string $msg = '') use ($email) {
        flash('error', $msg ?: t('Incorrect email or password.'));
        redirect('/login');
    };
    if (!rate_limit('login:' . client_ip(), 10, 900) || !rate_limit('login-acct:' . sha1($email), 20, 3600)) {
        $fail(t('Too many sign-in attempts. Please wait 15 minutes.'));
    }
    $u = $email !== '' ? db_one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    if (!$u || !$u['password_hash']) {
        password_verify($pass, '$2y$10$usesomesillystringforsaltpZ5rN0x0VjA7hY4Jx3nW7q8m9k1eUu'); // equal timing
        audit('Failed sign-in (unknown email)');
        $fail();
    }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        audit('Sign-in blocked: account locked', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
        $fail(sprintf(t('This account is temporarily locked after too many wrong passwords. Try again in %d minutes.'), max(1, (int)ceil((strtotime($u['locked_until']) - time()) / 60))));
    }
    if (!password_verify($pass, $u['password_hash'])) {
        $n = (int)$u['failed_logins'] + 1;
        $upd = ['failed_logins' => $n];
        if ($n >= MAX_FAILED_LOGINS) $upd += ['locked_until' => date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60), 'failed_logins' => 0];
        db_update('users', $upd, 'id = ?', [$u['id']]);
        audit('Failed sign-in', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
        $fail();
    }
    if ($u['status'] !== 'active') {
        $fail(t('This account is not active. Please contact your administrator.'));
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db_update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    }
    clear_old();
    if (!empty($u['totp_secret'])) {
        session_regenerate_id(true);
        $_SESSION['_2fa_uid'] = (int)$u['id'];
        $_SESSION['_2fa_at'] = time();
        redirect('/login/2fa');
    }
    finish_login($u);
}

function finish_login(array $u): never
{
    login_user($u);
    audit('Signed in', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
    $to = $_SESSION['_intended'] ?? null;
    unset($_SESSION['_intended']);
    if (is_string($to) && str_starts_with($to, base_path() . '/') && !str_contains($to, '//') && !str_contains($to, '\\')) {
        header('Location: ' . $to);
        exit;
    }
    if ($u['role'] === 'admin' && empty($u['totp_secret'])) {
        flash('warn', t('Protect your admin account: turn on two-factor authentication in My account.'));
    }
    redirect(home_for($u));
}

function pending_2fa_user(): ?array
{
    $id = $_SESSION['_2fa_uid'] ?? null;
    if (!$id || (time() - (int)($_SESSION['_2fa_at'] ?? 0)) > 300) {
        unset($_SESSION['_2fa_uid'], $_SESSION['_2fa_at']);
        return null;
    }
    return db_one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$id]);
}

function auth_2fa_form(): void
{
    if (!pending_2fa_user()) redirect('/login');
    render('auth/twofa', ['title' => t('Two-factor authentication')], 'auth');
}

function auth_2fa(): void
{
    $u = pending_2fa_user();
    if (!$u) {
        flash('error', t('Your sign-in expired. Please sign in again.'));
        redirect('/login');
    }
    if (!rate_limit('2fa:' . $u['id'], 6, 600)) {
        unset($_SESSION['_2fa_uid']);
        flash('error', t('Too many wrong codes. Please sign in again in 10 minutes.'));
        redirect('/login');
    }
    $code = trim((string)input('code'));
    $secret = decrypt_str($u['totp_secret']);
    $ok = ($secret && preg_match('/^\d[\d ]{4,7}$/', $code) && totp_verify($secret, $code, (int)$u['id']))
        || (str_contains($code, '-') && use_recovery_code($u, $code));
    if (!$ok) {
        audit('Failed two-factor code', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
        flash('error', t('That code is not correct. Try again.'));
        redirect('/login/2fa');
    }
    finish_login($u);
}

function auth_register_form(): void
{
    if ($u = current_user()) redirect(home_for($u));
    if (setting('allow_registration', '1') !== '1') {
        flash('info', t('New sign-ups are paused right now.'));
        redirect('/login');
    }
    render('auth/register', ['title' => t('Create your company account'), 'seo_page' => 'register', 'description' => t('Create a free PaySlip.tech account to run payroll and issue QR-verified payslips for your team in Bangladesh.')], 'auth');
}

function auth_register(): void
{
    if (setting('allow_registration', '1') !== '1') redirect('/login');
    // Honeypot: bots fill hidden fields
    if (trim((string)($_POST['website'] ?? '')) !== '') redirect('/login');
    $data = [
        'company' => trim((string)input('company')),
        'name' => trim((string)input('name')),
        'email' => mb_strtolower(trim((string)input('email'))),
    ];
    $pass = (string)($_POST['password'] ?? '');
    set_old($data + ['country' => strtoupper((string)input('country'))]);
    $errors = [];
    if (mb_strlen($data['company']) < 2) $errors[] = t('Enter your company name.');
    if (mb_strlen($data['name']) < 2) $errors[] = t('Enter your name.');
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('Enter a valid email address.');
    if ($pp = password_problem($pass, $data['email'])) $errors[] = $pp;
    if (empty($_POST['agree'])) $errors[] = t('Please accept the Terms and Privacy Policy.');
    if (!$errors && db_val('SELECT 1 FROM users WHERE email = ?', [$data['email']])) $errors[] = t('An account with this email already exists.');
    if (!rate_limit('register:' . client_ip(), 5, 3600)) $errors[] = t('Too many sign-ups from this network. Try again later.');
    if ($errors) {
        foreach ($errors as $er) flash('error', $er);
        redirect('/register');
    }
    $pdo = db();
    $pdo->beginTransaction();
    $country = strtoupper((string)input('country'));
    if (!isset(countries()[$country])) $country = default_country();
    $cid = db_insert('companies', ['name' => mb_substr($data['company'], 0, 190), 'email' => $data['email'], 'signatory_name' => mb_substr($data['name'], 0, 190), 'payslip_lang' => $country === 'BD' ? lang() : 'en', 'country' => $country, 'currency' => countries()[$country][1], 'created_at' => now()]);
    $uid = db_insert('users', ['role' => 'employer', 'company_id' => $cid, 'name' => mb_substr($data['name'], 0, 190), 'email' => $data['email'], 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'lang' => lang(), 'created_at' => now()]);
    $pdo->commit();
    clear_old();
    login_user(db_one('SELECT * FROM users WHERE id = ?', [$uid]));
    log_activity($cid, 'Company account created');
    flash('success', t('Welcome! Start by completing your company profile.'));
    redirect('/app');
}

function auth_logout(): void
{
    if ($u = current_user()) audit('Signed out', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
    logout_user();
    redirect('/login');
}

function auth_forgot_form(): void
{
    render('auth/forgot', ['title' => t('Reset your password')], 'auth');
}

function auth_forgot(): void
{
    $email = mb_strtolower(trim((string)input('email')));
    if (rate_limit('forgot:' . client_ip(), 5, 3600) && rate_limit('forgot-acct:' . sha1($email), 3, 3600)) {
        $u = db_one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($u) {
            $token = issue_token((int)$u['id'], 1);
            send_mail($u['email'], t('Reset your password') . ' — ' . site_name(), t('We received a request to reset your password. This link is valid for 1 hour. If you did not request it, you can ignore this email.'), abs_url('/reset/' . $token), t('Set a new password'));
            audit('Password reset requested', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
        }
    }
    flash('success', t('If an account exists for that email, we have sent a reset link.'));
    redirect('/login');
}

function auth_reset_form(string $token): void
{
    $u = user_by_token($token);
    if (!$u) {
        flash('error', t('This link is invalid or has expired.'));
        redirect('/forgot');
    }
    render('auth/set_password', ['title' => t('Set a new password'), 'action' => '/reset/' . $token, 'u' => $u, 'intro' => t('Choose a new password for your account.')], 'auth');
}

function auth_reset(string $token): void
{
    $u = user_by_token($token);
    if (!$u) redirect('/forgot');
    $p = (string)($_POST['password'] ?? '');
    $pp = password_problem($p, $u['email'], $u['role'] === 'admin' ? 10 : 8);
    if ($pp || $p !== ($_POST['password_confirm'] ?? '')) {
        flash('error', $pp ?: t('Passwords must match and be at least 8 characters.'));
        redirect('/reset/' . $token);
    }
    db_update('users', ['password_hash' => password_hash($p, PASSWORD_DEFAULT), 'token' => null, 'token_expires' => null, 'failed_logins' => 0, 'locked_until' => null], 'id = ?', [$u['id']]);
    db_exec('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$u['id']]);
    audit('Password reset completed', $u['company_id'] ? (int)$u['company_id'] : null, (int)$u['id']);
    flash('success', t('Password updated. Please sign in.'));
    redirect('/login');
}

function auth_invite_form(string $token): void
{
    $u = user_by_token($token);
    if (!$u) {
        flash('error', t('This invitation link is invalid or has expired. Ask your employer for a new one.'));
        redirect('/login');
    }
    $company = db_one('SELECT name FROM companies WHERE id = ?', [$u['company_id']]);
    render('auth/set_password', ['title' => t('Activate your account'), 'action' => '/invite/' . $token, 'u' => $u, 'intro' => sprintf(t('%s invited you to view your payslips. Choose a password to activate your account.'), $company['name'] ?? '')], 'auth');
}

function auth_invite(string $token): void
{
    $u = user_by_token($token);
    if (!$u) redirect('/login');
    $p = (string)($_POST['password'] ?? '');
    $pp = password_problem($p, $u['email']);
    if ($pp || $p !== ($_POST['password_confirm'] ?? '')) {
        flash('error', $pp ?: t('Passwords must match and be at least 8 characters.'));
        redirect('/invite/' . $token);
    }
    db_update('users', ['password_hash' => password_hash($p, PASSWORD_DEFAULT), 'token' => null, 'token_expires' => null, 'status' => 'active'], 'id = ?', [$u['id']]);
    login_user(db_one('SELECT * FROM users WHERE id = ?', [$u['id']]));
    flash('success', t('Your account is active.'));
    redirect('/me');
}

/* ---------------- My account & two-factor ---------------- */

function account_form(): void
{
    $u = require_login();
    $setup = null;
    if (!empty($_SESSION['_2fa_setup'])) {
        $setup = ['secret' => $_SESSION['_2fa_setup'], 'uri' => totp_uri($_SESSION['_2fa_setup'], $u['email'])];
    }
    $codes = $_SESSION['_recovery_once'] ?? null;
    unset($_SESSION['_recovery_once']);
    $recent = db_all('SELECT action, ip, created_at FROM activity WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$u['id']]);
    render('app/account', ['title' => t('My account'), 'u' => $u, 'nav' => 'account', 'setup' => $setup, 'codes' => $codes, 'recent' => $recent], 'app');
}

function is_demo_user(array $u): bool
{
    return (string)setting('demo_email') === $u['email'];
}

function account_save(): void
{
    $u = require_login();
    $name = trim((string)input('name'));
    $lang = (string)input('lang');
    if (mb_strlen($name) >= 2) db_update('users', ['name' => mb_substr($name, 0, 190)], 'id = ?', [$u['id']]);
    if (in_array($lang, ['en', 'bn'], true)) {
        db_update('users', ['lang' => $lang], 'id = ?', [$u['id']]);
        set_lang($lang);
    }
    $new = (string)($_POST['new_password'] ?? '');
    if ($new !== '') {
        if (is_demo_user($u)) {
            flash('error', t('The demo account password cannot be changed.'));
            redirect('/account');
        }
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$u['password_hash'])) {
            flash('error', t('Current password is incorrect.'));
            redirect('/account');
        }
        if ($pp = password_problem($new, $u['email'], $u['role'] === 'admin' ? 10 : 8)) {
            flash('error', $pp);
            redirect('/account');
        }
        db_update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        bump_session_version((int)$u['id']);
        audit('Password changed (other sessions signed out)');
    }
    flash('success', t('Account updated.'));
    redirect('/account');
}

function account_2fa_start(): void
{
    $u = require_login();
    if (is_demo_user($u)) {
        flash('error', t('Two-factor authentication cannot be changed on the demo account.'));
        redirect('/account');
    }
    $_SESSION['_2fa_setup'] = totp_new_secret();
    redirect('/account#security');
}

function account_2fa_enable(): void
{
    $u = require_login();
    $secret = $_SESSION['_2fa_setup'] ?? null;
    if (!$secret) redirect('/account');
    if (!rate_limit('2fa-setup:' . $u['id'], 8, 600) || !totp_verify($secret, (string)input('code'), (int)$u['id'])) {
        flash('error', t('That code is not correct. Make sure the time on your phone is set automatically, then try again.'));
        redirect('/account#security');
    }
    [$plain, $hashed] = make_recovery_codes();
    db_update('users', ['totp_secret' => encrypt_str($secret), 'totp_recovery' => $hashed], 'id = ?', [$u['id']]);
    unset($_SESSION['_2fa_setup']);
    bump_session_version((int)$u['id']);
    $_SESSION['_recovery_once'] = $plain;
    audit('Two-factor authentication turned on');
    flash('success', t('Two-factor authentication is on. Save your recovery codes now.'));
    redirect('/account#security');
}

function account_2fa_disable(): void
{
    $u = require_login();
    if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$u['password_hash'])) {
        flash('error', t('Current password is incorrect.'));
        redirect('/account#security');
    }
    db_update('users', ['totp_secret' => null, 'totp_recovery' => null], 'id = ?', [$u['id']]);
    unset($_SESSION['_2fa_setup']);
    audit('Two-factor authentication turned off');
    flash('success', t('Two-factor authentication is off.'));
    redirect('/account#security');
}

function account_2fa_cancel(): void
{
    require_login();
    unset($_SESSION['_2fa_setup']);
    redirect('/account#security');
}
