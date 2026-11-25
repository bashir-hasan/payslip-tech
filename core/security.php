<?php
declare(strict_types=1);

/* ============================================================
 * HTTP security headers
 * ============================================================ */

/**
 * Strict CSP everywhere. Public pages get a relaxed script policy only when the
 * platform admin has added tracking/custom code (those snippets need inline JS).
 */
function send_security_headers(bool $allowThirdPartyScripts = false): void
{
    if (headers_sent()) return;
    $fonts = 'https://fonts.googleapis.com';
    $fontFiles = 'https://fonts.gstatic.com';
    if ($allowThirdPartyScripts) {
        $script = "'self' 'unsafe-inline' https:";
        $connect = "'self' https:";
        $img = "'self' data: https:";
        $frame = 'https:';
    } else {
        $script = "'self'";
        $connect = "'self'";
        $img = "'self' data:";
        $frame = "'none'";
    }
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src $script",
        "style-src 'self' 'unsafe-inline' $fonts",
        "font-src 'self' $fontFiles data:",
        "img-src $img",
        "connect-src $connect",
        "frame-src $frame",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]) . (is_https() ? '; upgrade-insecure-requests' : '');
    header('Content-Security-Policy: ' . $csp);
}

function base_security_headers(): void
{
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* ============================================================
 * Sessions
 * ============================================================ */

const SESSION_IDLE = 7200;      // 2 hours of inactivity
const SESSION_ABSOLUTE = 43200; // 12 hours maximum

function start_secure_session(): void
{
    $secure = is_https();
    $hostPrefix = $secure && base_path() === '';
    session_name($hostPrefix ? '__Host-pst' : 'pst_sess');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $hostPrefix ? '/' : (base_path() ?: '/'),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (!empty($_SESSION['uid'])) {
        $idle = $now - (int)($_SESSION['_last'] ?? $now);
        $age = $now - (int)($_SESSION['_born'] ?? $now);
        $fp = $_SESSION['_ua'] ?? '';
        if ($idle > SESSION_IDLE || $age > SESSION_ABSOLUTE || !hash_equals($fp, ua_fingerprint())) {
            $lang = $_SESSION['lang'] ?? null;
            $_SESSION = [];
            session_regenerate_id(true);
            if ($lang) $_SESSION['lang'] = $lang;
            $_SESSION['_flash'][] = ['type' => 'info', 'msg' => 'Your session expired. Please sign in again.'];
        }
    }
    $_SESSION['_last'] = $now;
}

function ua_fingerprint(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . cfg('app_key'));
}

/* ============================================================
 * Encryption (AES-256-GCM, key derived from app_key)
 * ============================================================ */

function enc_key(): string
{
    return hash_hmac('sha256', 'payslip-encryption-v1', (string)cfg('app_key'), true);
}

function encrypt_str(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', enc_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function decrypt_str(?string $data): ?string
{
    if (!$data || !str_starts_with($data, 'v1:')) return null;
    $raw = base64_decode(substr($data, 3), true);
    if ($raw === false || strlen($raw) < 29) return null;
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', enc_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? null : $pt;
}

/* ============================================================
 * Two-factor authentication (TOTP, RFC 6238)
 * ============================================================ */

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

function totp_at(string $secret, int $step): string
{
    $key = base32_decode($secret);
    $msg = pack('N*', 0) . pack('N*', $step);
    $h = hash_hmac('sha1', $msg, $key, true);
    $o = ord($h[19]) & 0x0f;
    $num = ((ord($h[$o]) & 0x7f) << 24) | ((ord($h[$o + 1]) & 0xff) << 16) | ((ord($h[$o + 2]) & 0xff) << 8) | (ord($h[$o + 3]) & 0xff);
    return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code and ±1 step; each code works only once (replay protection). */
function totp_verify(string $secret, string $code, int $userId): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $now = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_at($secret, $now + $i), $code)) {
            $k = 'totp_used:' . $userId . ':' . ($now + $i);
            if (db_val('SELECT 1 FROM rate_limits WHERE k = ?', [$k])) return false;
            db_exec('REPLACE INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?)', [$k, time()]);
            return true;
        }
    }
    return false;
}

function totp_uri(string $secret, string $email): string
{
    $issuer = rawurlencode(site_name());
    return 'otpauth://totp/' . $issuer . ':' . rawurlencode($email) . '?secret=' . $secret . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
}

/** @return array{0: string[], 1: string} plain codes and hashed JSON for storage */
function make_recovery_codes(): array
{
    $plain = [];
    $hashes = [];
    for ($i = 0; $i < 8; $i++) {
        $c = strtolower(random_code(5, 'abcdefghjkmnpqrstuvwxyz23456789') . '-' . random_code(5, 'abcdefghjkmnpqrstuvwxyz23456789'));
        $plain[] = $c;
        $hashes[] = password_hash($c, PASSWORD_DEFAULT);
    }
    return [$plain, json_encode($hashes)];
}

function use_recovery_code(array $user, string $code): bool
{
    $code = strtolower(trim($code));
    $hashes = json_decode((string)$user['totp_recovery'], true) ?: [];
    foreach ($hashes as $i => $h) {
        if (password_verify($code, $h)) {
            unset($hashes[$i]);
            db_update('users', ['totp_recovery' => json_encode(array_values($hashes))], 'id = ?', [$user['id']]);
            return true;
        }
    }
    return false;
}

/* ============================================================
 * Passwords & uploads
 * ============================================================ */

function password_problem(string $p, string $email = '', int $min = 8): ?string
{
    if (mb_strlen($p) < $min) return sprintf(t('Password must be at least %d characters.'), $min);
    $common = ['password', 'password1', 'password123', '12345678', '123456789', '1234567890', 'qwerty123', 'qwertyuiop', '11111111', '00000000', 'iloveyou', 'bangladesh', 'bangladesh1', 'admin123', 'welcome1', 'abc12345', 'payslip123', 'letmein1'];
    $lower = mb_strtolower($p);
    if (in_array($lower, $common, true)) return t('This password is too common. Please choose another.');
    if ($email !== '' && str_contains($lower, mb_strtolower(strstr($email, '@', true) ?: '#@#'))) return t('Your password should not contain your email name.');
    if (preg_match('/^(.)\1+$/', $p)) return t('This password is too common. Please choose another.');
    return null;
}

/** Re-encodes an uploaded image with GD, which strips hidden payloads and metadata. */
function sanitize_image(string $tmp, string $dest, int $type, int $max = 600): bool
{
    if (!is_uploaded_file($tmp)) return false;
    if (!function_exists('imagecreatefromstring')) {
        return move_uploaded_file($tmp, $dest);
    }
    $img = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$img) return false;
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $max || $h > $max) {
        $scale = min($max / $w, $max / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    } else {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($img, $dest, 88),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($img, $dest, 88) : imagepng($img, $dest),
        default => imagepng($img, $dest),
    };
    imagedestroy($img);
    @unlink($tmp);
    return (bool)$ok;
}

function audit(string $action, ?int $companyId = null, ?int $userId = null): void
{
    try {
        db_insert('activity', ['company_id' => $companyId, 'user_id' => $userId ?? (current_user()['id'] ?? null), 'action' => mb_substr($action, 0, 255), 'ip' => client_ip(), 'created_at' => now()]);
    } catch (Throwable $e) {
    }
}

/** Occasional cleanup of expired rate-limit rows. */
function gc_rate_limits(): void
{
    if (random_int(1, 200) === 1) {
        db_exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400 * 2]);
    }
}
