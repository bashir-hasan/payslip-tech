<?php
declare(strict_types=1);

function cfg(string $k, $default = null)
{
    global $CONFIG;
    return $CONFIG[$k] ?? $default;
}

const CLOUDFLARE_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

function ip_in_cidr(string $ip, string $cidr): bool
{
    [$net, $bits] = explode('/', $cidr) + [1 => null];
    $ipb = @inet_pton($ip);
    $netb = @inet_pton($net);
    if ($ipb === false || $netb === false || strlen($ipb) !== strlen($netb)) return false;
    $bits = (int)$bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipb, 0, $bytes) !== substr($netb, 0, $bytes)) return false;
    $rem = $bits % 8;
    if ($rem === 0) return true;
    $mask = chr((0xff << (8 - $rem)) & 0xff);
    return ((ord($ipb[$bytes]) & ord($mask)) === (ord($netb[$bytes]) & ord($mask)));
}

/** True when the request really came through Cloudflare (headers from anyone else are ignored). */
function via_cloudflare(): bool
{
    static $r = null;
    if ($r !== null) return $r;
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $r = false;
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        foreach (CLOUDFLARE_RANGES as $c) if (ip_in_cidr($remote, $c)) { $r = true; break; }
    }
    return $r;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    if (via_cloudflare()) {
        $v = json_decode((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), true);
        return ($v['scheme'] ?? '') === 'https' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
    // Hosts behind their own proxy (LiteSpeed/cPanel) set this; app_url decides as a fallback
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    return str_starts_with((string)cfg('app_url', ''), 'https://');
}

function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($b === '.') $b = '';
    }
    return $b;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = '/'): string
{
    $app = rtrim((string)cfg('app_url', ''), '/');
    if ($app !== '') return $app . '/' . ltrim($path, '/');
    $scheme = is_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . $path;
    $v = is_file($file) ? substr((string)filemtime($file), -6) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function set_old(array $data): void
{
    unset($data['_csrf'], $data['password'], $data['password_confirm']);
    $_SESSION['_old'] = $data;
}

function old(string $k, $default = '')
{
    return $_SESSION['_old'][$k] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        render('public/error', ['code' => 419, 'title' => t('Session expired'), 'message' => t('Please go back, refresh the page and try again.')], 'public');
        exit;
    }
}

function input(string $k, $default = '')
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function num_input(string $k): float
{
    $v = str_replace([',', ' '], '', (string)input($k, '0'));
    $v = bn_to_en_digits($v);
    return is_numeric($v) ? round(max(0, (float)$v), 2) : 0.0;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    if (via_cloudflare()) {
        $ip = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

/** Two-letter visitor country from Cloudflare (if enabled), e.g. "BD". */
function client_country(): ?string
{
    $c = via_cloudflare() ? strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')) : '';
    return preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' ? $c : null;
}

function abort(int $code = 404, ?string $msg = null): never
{
    http_response_code($code);
    $titles = [403 => t('Access denied'), 404 => t('Page not found')];
    render('public/error', [
        'code' => $code,
        'title' => $titles[$code] ?? t('Error'),
        'message' => $msg ?? ($code === 404 ? t('The page you are looking for does not exist.') : t('You do not have permission to view this page.')),
    ], 'public');
    exit;
}

/** Render a view inside a layout. */
function render(string $view, array $data = [], ?string $layout = 'public'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === 'public') {
        if (page_modified() === null) page_modified((int)filemtime(APP . '/views/' . $view . '.php'));
        if (wants_markdown() && http_response_code() < 400 && empty($data['noindex'])) {
            send_markdown_page((string)(($data['page_title'] ?? '') ?: (($data['title'] ?? '') ?: site_name())), (string)($data['markdown'] ?? html_to_markdown($content)), (string)($data['description'] ?? ''));
        }
        public_page_headers();
    }
    if ($layout === null) {
        echo $content;
        return;
    }
    require APP . '/views/layouts/' . $layout . '.php';
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP . '/views/partials/' . $name . '.php';
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/** Simple fixed-window rate limiter stored in DB. */
function rate_limit(string $key, int $max, int $windowSeconds): bool
{
    $k = substr($key, 0, 120);
    $now = time();
    $row = db_one('SELECT hits, window_start FROM rate_limits WHERE k = ?', [$k]);
    if (!$row || ($now - (int)$row['window_start']) > $windowSeconds) {
        db_exec('REPLACE INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?)', [$k, $now]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$k]);
    return true;
}

/* ---------- Numbers & money ---------- */

function bn_digits(string $s): string
{
    return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function bn_to_en_digits(string $s): string
{
    return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
}

/**
 * Formats an amount for the active currency: South Asian grouping (12,34,567.00) for BDT/INR/PKR/LKR/NPR,
 * international grouping (1,234,567.00) otherwise. Bangla digits when the language is Bangla.
 */
function money_fmt($amount, bool $decimals = true, ?string $lang = null, ?string $cur = null): string
{
    $cur = $cur ?? currency_context();
    $sa = (CURRENCIES[$cur][3] ?? 'intl') === 'sa';
    $amount = round((float)$amount, 2);
    $neg = $amount < 0;
    $amount = abs($amount);
    $int = (string)floor($amount);
    $dec = str_pad((string)round(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT);
    if ($dec === '100') { $int = (string)((int)$int + 1); $dec = '00'; }
    if ($sa && strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
        $int = $rest . ',' . $last3;
    } elseif (!$sa) {
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);
    }
    $out = ($neg ? '-' : '') . $int . ($decimals ? '.' . $dec : '');
    $lang = $lang ?? lang();
    return $lang === 'bn' ? bn_digits($out) : $out;
}

function money($amount, ?string $lang = null, ?string $cur = null): string
{
    return currency_symbol($cur) . money_fmt($amount, true, $lang, $cur);
}

function num($n, ?string $lang = null): string
{
    $lang = $lang ?? lang();
    $s = (string)$n;
    return $lang === 'bn' ? bn_digits($s) : $s;
}

/** Amount in words in English: lakh/crore for South Asian currencies, million/billion otherwise. */
function amount_in_words(float $amount, ?string $cur = null): string
{
    $cur = $cur ?? currency_context();
    [, $major, $minor, $sys] = CURRENCIES[$cur] ?? CURRENCIES['USD'];
    $amount = round($amount, 2);
    $whole = (int)floor($amount);
    $cents = (int)round(($amount - $whole) * 100);
    $w = fn(int $n) => $n === 0 ? 'Zero' : ($sys === 'sa' ? _num_words($n) : _num_words_intl($n));
    if ($sys === 'sa') {
        $out = $major . ' ' . $w($whole);
        if ($cents > 0) $out .= ' and ' . $w($cents) . ' ' . $minor;
    } else {
        $out = $w($whole) . ' ' . $major;
        if ($cents > 0) $out .= ' and ' . $w($cents) . ' ' . $minor;
    }
    return $out . ' Only';
}

function _num_words_intl(int $n): string
{
    $scales = [1000000000000 => 'Trillion', 1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'];
    $parts = [];
    foreach ($scales as $v => $name) {
        if ($n >= $v) {
            $parts[] = _num_words_below_1000(intdiv($n, $v)) . ' ' . $name;
            $n %= $v;
        }
    }
    if ($n) $parts[] = _num_words_below_1000($n);
    return implode(' ', $parts);
}

function _num_words_below_1000(int $n): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $s = '';
    if ($n >= 100) { $s = $ones[intdiv($n, 100)] . ' Hundred'; $n %= 100; }
    if ($n >= 20) { $s .= ($s ? ' ' : '') . $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : ''); }
    elseif ($n > 0) { $s .= ($s ? ' ' : '') . $ones[$n]; }
    return $s;
}

function _num_words(int $n): string
{
    $parts = [];
    $crore = intdiv($n, 10000000); $n %= 10000000;
    $lakh = intdiv($n, 100000); $n %= 100000;
    $thousand = intdiv($n, 1000); $n %= 1000;
    if ($crore) $parts[] = _num_words($crore) . ' Crore';
    if ($lakh) $parts[] = _num_words_below_1000($lakh) . ' Lakh';
    if ($thousand) $parts[] = _num_words_below_1000($thousand) . ' Thousand';
    if ($n) $parts[] = _num_words_below_1000($n);
    return implode(' ', $parts);
}

function period_label(string $period, ?string $lang = null): string
{
    $lang = $lang ?? lang();
    [$y, $m] = array_map('intval', explode('-', $period) + [0, 1]);
    $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $bn = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $m = max(1, min(12, $m));
    return $lang === 'bn' ? $bn[$m - 1] . ' ' . bn_digits((string)$y) : $en[$m - 1] . ' ' . $y;
}

function date_fmt(?string $d, ?string $lang = null, bool $time = false): string
{
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    $s = date($time ? 'd M Y, h:i A' : 'd M Y', $ts);
    $lang = $lang ?? lang();
    if ($lang === 'bn') {
        $months = ['Jan' => 'জানু', 'Feb' => 'ফেব্রু', 'Mar' => 'মার্চ', 'Apr' => 'এপ্রিল', 'May' => 'মে', 'Jun' => 'জুন', 'Jul' => 'জুলাই', 'Aug' => 'আগস্ট', 'Sep' => 'সেপ্টে', 'Oct' => 'অক্টো', 'Nov' => 'নভে', 'Dec' => 'ডিসে', 'AM' => 'পূর্বাহ্ণ', 'PM' => 'অপরাহ্ণ'];
        $s = bn_digits(strtr($s, $months));
    }
    return $s;
}

function time_ago(?string $d): string
{
    if (!$d) return '—';
    $diff = time() - strtotime($d);
    if ($diff < 60) return t('just now');
    if ($diff < 3600) return sprintf(t('%d min ago'), intdiv($diff, 60));
    if ($diff < 86400) return sprintf(t('%d h ago'), intdiv($diff, 3600));
    if ($diff < 86400 * 30) return sprintf(t('%d days ago'), intdiv($diff, 86400));
    return date_fmt($d);
}

function mask_account(?string $acc): string
{
    $acc = (string)$acc;
    if (strlen($acc) <= 4) return $acc;
    return str_repeat('•', min(6, strlen($acc) - 4)) . substr($acc, -4);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) $s .= mb_strtoupper(mb_substr($p, 0, 1));
    return $s ?: '?';
}

function random_code(int $len, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $s = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) $s .= $alphabet[random_int(0, $max)];
    return $s;
}

function setting(string $k, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
        } catch (Throwable $e) {
        }
    }
    return $cache[$k] ?? $default;
}

function set_setting(string $k, ?string $v): void
{
    db_exec('REPLACE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

function demo_cid(): int
{
    return (int)setting('demo_company_id', '-1');
}

function site_name(): string
{
    return (string)setting('site_name', 'PaySlip.tech');
}

function log_activity(?int $companyId, string $action): void
{
    $u = current_user();
    db_insert('activity', [
        'company_id' => $companyId,
        'user_id' => $u['id'] ?? null,
        'action' => mb_substr($action, 0, 255),
        'ip' => client_ip(),
        'created_at' => now(),
    ]);
}

function payment_methods(): array
{
    return ['bank' => t('Bank transfer / direct deposit'), 'bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'wallet' => t('Mobile wallet'), 'cash' => t('Cash'), 'cheque' => t('Cheque')];
}

function payment_method_label(?string $m, ?string $lang = null): string
{
    $map = ['bank' => ['Bank transfer', 'ব্যাংক ট্রান্সফার'], 'wallet' => ['Mobile wallet', 'মোবাইল ওয়ালেট'], 'bkash' => ['bKash', 'বিকাশ'], 'nagad' => ['Nagad', 'নগদ'], 'rocket' => ['Rocket', 'রকেট'], 'cash' => ['Cash', 'নগদ অর্থ'], 'cheque' => ['Cheque', 'চেক']];
    $lang = $lang ?? lang();
    $pair = $map[$m ?? ''] ?? [(string)$m, (string)$m];
    return $lang === 'bn' ? $pair[1] : $pair[0];
}

function icon(string $name, int $size = 18): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'wallet' => '<path d="M20 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'alert' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v.01"/><path d="M14 20h.01"/><path d="M17 20h4v-3"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'ban' => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
        'zap' => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        'code' => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'bank' => '<path d="M3 21h18"/><path d="M3 10h18"/><path d="m5 6 7-3 7 3"/><path d="M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'chat' => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.9-.9L3 20.5l1.5-4.6a8.4 8.4 0 0 1-.9-3.9A8.5 8.5 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8z"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Visitor's colour theme: light, dark, or auto (follows the device). */
function theme(): string
{
    // Light by default; dark only when the visitor picks it with the toggle
    $t = $_COOKIE['pst_theme'] ?? 'light';
    return $t === 'dark' ? 'dark' : 'light';
}
