<?php
/**
 * First-party, cookieless analytics.
 * - No cookies or browser storage; visitors are counted with a hash of IP + browser + a salt that changes every day,
 *   so the same person cannot be followed from one day to the next and no IP address is ever stored.
 * - Honors Global Privacy Control and Do Not Track (the browser script sends nothing).
 * - Signed-in users are linked to their account only inside PaySlip.tech, for support and security.
 */

const ANALYTICS_LIVE_MINUTES = 5;

function analytics_enabled(): bool
{
    return setting('analytics_enabled', '1') === '1';
}

/** Daily visitor id: same browser on the same day = same id; a new salt tomorrow. */
function analytics_visitor_id(): string
{
    $salt = hash_hmac('sha256', 'pst-analytics|' . date('Y-m-d'), (string)cfg('app_key'));
    return substr(hash('sha256', $salt . '|' . client_ip() . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
}

function analytics_is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|httpclient|java\/|go-http|scrapy|facebookexternalhit|embedly|whatsapp|telegram|discord|skype/i', $ua);
}

/** Named crawlers, including AI search and training bots. */
function analytics_bot_name(string $ua): ?string
{
    $map = [
        'GPTBot' => 'GPTBot (OpenAI)', 'OAI-SearchBot' => 'OAI-SearchBot (ChatGPT search)', 'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot (Anthropic)', 'Claude-User' => 'Claude-User', 'Claude-SearchBot' => 'Claude-SearchBot', 'anthropic-ai' => 'anthropic-ai',
        'PerplexityBot' => 'PerplexityBot', 'Perplexity-User' => 'Perplexity-User', 'Google-Extended' => 'Google-Extended',
        'Googlebot' => 'Googlebot', 'bingbot' => 'Bingbot', 'Applebot' => 'Applebot', 'DuckDuckBot' => 'DuckDuckBot', 'YandexBot' => 'YandexBot',
        'Bytespider' => 'Bytespider', 'CCBot' => 'CCBot (Common Crawl)', 'meta-externalagent' => 'Meta AI', 'Amazonbot' => 'Amazonbot',
        'cohere-ai' => 'Cohere', 'MistralAI' => 'MistralAI', 'AhrefsBot' => 'AhrefsBot', 'SemrushBot' => 'SemrushBot',
    ];
    foreach ($map as $needle => $name) if (stripos($ua, $needle) !== false) return $name;
    return null;
}

/** Called once per request from the front controller: counts named crawlers (cheap upsert, no personal data). */
function analytics_count_bot(string $path): void
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || !($name = analytics_bot_name($ua))) return;
    try {
        db_exec('INSERT INTO analytics_bots (day, bot, hits, last_path, last_at) VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_path = VALUES(last_path), last_at = VALUES(last_at)',
            [date('Y-m-d'), $name, mb_substr($path, 0, 255), now()]);
    } catch (Throwable $e) { /* never break a page for analytics */ }
}

function analytics_ua_parts(string $ua): array
{
    $device = preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua) ? 'tablet' : (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Opera Mini|IEMobile/i', $ua) ? 'mobile' : 'desktop');
    $browser = match (true) {
        (bool)preg_match('/Edg\//', $ua) => 'Edge',
        (bool)preg_match('/OPR\/|Opera/', $ua) => 'Opera',
        (bool)preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
        (bool)preg_match('/UCBrowser/', $ua) => 'UC Browser',
        (bool)preg_match('/Firefox\/|FxiOS/', $ua) => 'Firefox',
        (bool)preg_match('/CriOS|Chrome\//', $ua) => 'Chrome',
        (bool)preg_match('/Safari\//', $ua) => 'Safari',
        default => 'Other',
    };
    $os = match (true) {
        (bool)preg_match('/Windows/', $ua) => 'Windows',
        (bool)preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
        (bool)preg_match('/Android/', $ua) => 'Android',
        (bool)preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
        (bool)preg_match('/CrOS/', $ua) => 'ChromeOS',
        (bool)preg_match('/Linux/', $ua) => 'Linux',
        default => 'Other',
    };
    return [$device, $browser, $os];
}

function analytics_clean_path(string $p): string
{
    $p = (string)(parse_url($p, PHP_URL_PATH) ?: '/');
    // Never store document codes or share tokens in full: they are keys to private documents
    $p = preg_replace('#^/(v|verify|payslip|document)/((?:PS|DC)-[A-Z0-9]{4})-[A-Z0-9]{4}#i', '/$1/$2-****', $p);
    $p = preg_replace('#^/(p|s|share|proof)/[A-Za-z0-9_-]{8,}#', '/$1/…', $p);
    $p = preg_replace('#/[a-f0-9]{24,}#i', '/…', $p);
    return mb_substr($p, 0, 255);
}

/** Record a page view (from the browser script). Returns the hit id for heartbeats. */
function analytics_record_pageview(array $in): ?int
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!analytics_enabled() || analytics_is_bot($ua)) return null;
    $u = current_user();
    if ($u && $u['role'] === 'admin' && setting('analytics_track_admins', '0') !== '1') return null;
    $visitor = analytics_visitor_id();
    if (!rate_limit('an:' . $visitor, 120, 60)) return null;

    $rawPath = (string)(parse_url((string)($in['p'] ?? '/'), PHP_URL_PATH) ?: '/');
    $path = analytics_clean_path($rawPath);
    // Titles of private documents can contain names: keep them only for ordinary pages
    if ($path !== mb_substr($rawPath, 0, 255) || preg_match('#^/(v|verify|payslip|document|p|s|share|proof|me)\b#', $path)) $in['t'] = '';
    $ref = (string)($in['r'] ?? '');
    $refHost = $ref !== '' ? strtolower((string)parse_url($ref, PHP_URL_HOST)) : '';
    $self = strtolower((string)parse_url(site_origin(), PHP_URL_HOST));
    $refHost = preg_replace('/^www\./', '', $refHost);
    if ($refHost === preg_replace('/^www\./', '', $self)) { $refHost = ''; $ref = ''; }
    $q = []; parse_str((string)parse_url((string)($in['u'] ?? ''), PHP_URL_QUERY), $q);
    $utm = fn(string $k) => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? mb_substr(strtolower(trim($q[$k])), 0, 80) : null;
    [$device, $browser, $os] = analytics_ua_parts($ua);

    // A visit (session) continues while the same visitor is active within 30 minutes
    $prev = db_one('SELECT session, last_seen FROM analytics_hits WHERE visitor = ? AND day = ? ORDER BY id DESC LIMIT 1', [$visitor, date('Y-m-d')]);
    $session = ($prev && strtotime($prev['last_seen']) > time() - 1800) ? $prev['session'] : substr(bin2hex(random_bytes(8)), 0, 16);
    $isNew = $prev ? 0 : 1;

    $cfCity = via_cloudflare() ? trim((string)($_SERVER['HTTP_CF_IPCITY'] ?? '')) : '';
    $cfRegion = via_cloudflare() ? trim((string)($_SERVER['HTTP_CF_REGION'] ?? '')) : '';
    $lang = substr(preg_replace('/[^A-Za-z-]/', '', (string)($in['l'] ?? '')), 0, 12);

    db_exec('INSERT INTO analytics_hits (ts, last_seen, day, kind, visitor, session, is_new, path, title, ref_host, referrer, utm_source, utm_medium, utm_campaign, country, region, city, device, browser, os, lang, screen_w, user_id, role)
             VALUES (?, ?, ?, \'pageview\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        now(), now(), date('Y-m-d'), $visitor, $session, $isNew, $path, mb_substr(trim((string)($in['t'] ?? '')), 0, 200) ?: null,
        $refHost ?: null, $ref !== '' ? mb_substr(preg_replace('/\?.*$/', '', $ref), 0, 255) : null,
        $utm('utm_source') ?? $utm('ref'), $utm('utm_medium'), $utm('utm_campaign'),
        client_country(), $cfRegion !== '' ? mb_substr($cfRegion, 0, 80) : null, $cfCity !== '' ? mb_substr($cfCity, 0, 80) : null,
        $device, $browser, $os, $lang ?: null, min(65535, max(0, (int)($in['w'] ?? 0))) ?: null,
        $u['id'] ?? null, $u['role'] ?? null,
    ]);
    return (int)db()->lastInsertId();
}

/** Heartbeat from an open page: keeps the visitor "live" and records time on page and scroll depth. */
function analytics_heartbeat(int $id, int $seconds, int $scroll): void
{
    if ($id <= 0) return;
    db_exec('UPDATE analytics_hits SET last_seen = ?, duration = GREATEST(duration, ?), scroll = GREATEST(scroll, ?) WHERE id = ? AND visitor = ? AND ts > ?',
        [now(), min(86400, max(0, $seconds)), min(100, max(0, $scroll)), $id, analytics_visitor_id(), date('Y-m-d H:i:s', strtotime('-6 hours'))]);
}

/** Record an event (sign-up, checkout started, demo opened, button clicks marked with data-track). */
function analytics_event(string $name, string $meta = '', ?string $path = null): void
{
    try {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!analytics_enabled() || analytics_is_bot($ua)) return;
        $u = current_user();
        $visitor = analytics_visitor_id();
        $sess = db_val('SELECT session FROM analytics_hits WHERE visitor = ? AND day = ? ORDER BY id DESC LIMIT 1', [$visitor, date('Y-m-d')]) ?: substr(bin2hex(random_bytes(8)), 0, 16);
        [$device, $browser, $os] = analytics_ua_parts($ua);
        db_exec('INSERT INTO analytics_hits (ts, last_seen, day, kind, event, meta, visitor, session, path, country, device, browser, os, user_id, role)
                 VALUES (?, ?, ?, \'event\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [now(), now(), date('Y-m-d'), mb_substr($name, 0, 60), mb_substr($meta, 0, 255) ?: null, $visitor, $sess,
             analytics_clean_path($path ?? (string)($_SERVER['REQUEST_URI'] ?? '/')), client_country(), $device, $browser, $os, $u['id'] ?? null, $u['role'] ?? null]);
    } catch (Throwable $e) { error_log('analytics_event: ' . $e->getMessage()); }
}

function visitor_country_name(?string $cc): string
{
    if (!$cc) return t('Unknown');
    if (isset(countries()[$cc]) && $cc !== 'XX') return countries()[$cc][0];
    if (class_exists('Locale')) { $n = \Locale::getDisplayRegion('-' . $cc, 'en'); if ($n && $n !== $cc) return $n; }
    return $cc;
}

function country_flag(?string $cc): string
{
    if (!$cc || !preg_match('/^[A-Z]{2}$/', $cc)) return '🌐';
    return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
}

/** Where a visit came from, in plain words. */
function analytics_source(array $h): string
{
    if (!empty($h['utm_source'])) return $h['utm_source'];
    $r = (string)($h['ref_host'] ?? '');
    if ($r === '') return 'Direct';
    $map = ['google.' => 'Google', 'bing.com' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'facebook.com' => 'Facebook', 'fb.com' => 'Facebook', 'l.facebook' => 'Facebook',
        'instagram.com' => 'Instagram', 'linkedin.com' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 't.co' => 'X (Twitter)', 'x.com' => 'X (Twitter)', 'twitter.com' => 'X (Twitter)',
        'reddit.com' => 'Reddit', 'youtube.com' => 'YouTube', 'chatgpt.com' => 'ChatGPT', 'chat.openai.com' => 'ChatGPT', 'perplexity.ai' => 'Perplexity', 'claude.ai' => 'Claude',
        'gemini.google' => 'Gemini', 'copilot.microsoft' => 'Copilot', 'github.com' => 'GitHub', 'producthunt.com' => 'Product Hunt', 'get.tech' => '.tech', 'polar.sh' => 'Polar', 'whatsapp' => 'WhatsApp'];
    foreach ($map as $needle => $name) if (str_contains($r, $needle)) return $name;
    return $r;
}
