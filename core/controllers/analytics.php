<?php
/* ---------- Collection (browser script) ---------- */

function analytics_same_origin(): bool
{
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o === '') $o = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($o === '') return true;
    $u = parse_url($o);
    $from = strtolower(($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : ''));
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $site = parse_url(site_origin());
    $siteHost = strtolower(($site['host'] ?? '') . (isset($site['port']) ? ':' . $site['port'] : ''));
    return $from !== '' && ($from === $host || $from === $siteHost);
}

function analytics_input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 4096) ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $_POST;
}

function analytics_collect(): void
{
    header('Cache-Control: no-store');
    if (!analytics_same_origin()) { http_response_code(403); exit; }
    try {
        $in = analytics_input();
        if (($in['e'] ?? '') !== '') {
            analytics_event(preg_replace('/[^a-z0-9_:.-]/i', '', (string)$in['e']), (string)($in['m'] ?? ''), (string)($in['p'] ?? '/'));
            http_response_code(204); exit;
        }
        $id = analytics_record_pageview($in);
    } catch (Throwable $e) { error_log('analytics_collect: ' . $e->getMessage()); $id = null; }
    header('Content-Type: application/json');
    echo json_encode(['id' => $id]);
    exit;
}

function analytics_ping(): void
{
    header('Cache-Control: no-store');
    if (!analytics_same_origin()) { http_response_code(403); exit; }
    try {
        $in = analytics_input();
        analytics_heartbeat((int)($in['id'] ?? 0), (int)($in['s'] ?? 0), (int)($in['sc'] ?? 0));
    } catch (Throwable $e) { error_log('analytics_ping: ' . $e->getMessage()); }
    http_response_code(204);
    exit;
}

/* ---------- Admin reports ---------- */

function analytics_range(string $r): array
{
    $today = date('Y-m-d');
    return match ($r) {
        'today' => [$today, $today, 'hour'],
        'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), 'hour'],
        '30d' => [date('Y-m-d', strtotime('-29 days')), $today, 'day'],
        '90d' => [date('Y-m-d', strtotime('-89 days')), $today, 'day'],
        '12m' => [date('Y-m-d', strtotime('-364 days')), $today, 'month'],
        default => [date('Y-m-d', strtotime('-6 days')), $today, 'day'],
    };
}

function analytics_kpis(string $from, string $to): array
{
    $w = "day BETWEEN ? AND ? AND kind = 'pageview'";
    $a = [$from, $to];
    $pv = (int)db_val("SELECT COUNT(*) FROM analytics_hits WHERE $w", $a);
    $vis = (int)db_val("SELECT COUNT(*) FROM (SELECT DISTINCT day, visitor FROM analytics_hits WHERE $w) x", $a);
    $sess = db_one("SELECT COUNT(*) n, SUM(pages = 1) bounces, AVG(secs) avg_secs FROM (SELECT session, COUNT(*) pages, SUM(duration) secs FROM analytics_hits WHERE $w GROUP BY session) s", $a);
    $n = (int)($sess['n'] ?? 0);
    return [
        'visitors' => $vis, 'pageviews' => $pv, 'sessions' => $n,
        'bounce' => $n ? round((int)$sess['bounces'] / $n * 100) : 0,
        'duration' => (int)round((float)($sess['avg_secs'] ?? 0)),
        'ppv' => $n ? round($pv / $n, 1) : 0,
    ];
}

function analytics_top(string $col, string $from, string $to, int $limit = 10, string $extra = ''): array
{
    return db_all("SELECT $col AS k, COUNT(*) AS views, COUNT(DISTINCT CONCAT(day, visitor)) AS visitors FROM analytics_hits
                   WHERE day BETWEEN ? AND ? AND kind = 'pageview' $extra GROUP BY $col ORDER BY visitors DESC, views DESC LIMIT $limit", [$from, $to]);
}

function fmt_secs(int $s): string
{
    if ($s < 60) return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm ' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT) . 's';
    return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
}

function analytics_live_data(): array
{
    $since = date('Y-m-d H:i:s', strtotime('-' . ANALYTICS_LIVE_MINUTES . ' minutes'));
    $rows = db_all("SELECT h.*, u.name AS user_name, u.email AS user_email,
                       (SELECT COUNT(*) FROM analytics_hits x WHERE x.session = h.session AND x.kind = 'pageview') AS pages
                    FROM analytics_hits h
                    JOIN (SELECT visitor, MAX(id) mid FROM analytics_hits WHERE last_seen >= ? AND kind = 'pageview' GROUP BY visitor) m ON m.mid = h.id
                    LEFT JOIN users u ON u.id = h.user_id
                    ORDER BY h.last_seen DESC LIMIT 100", [$since]);
    $first = [];
    if ($rows) {
        $in = implode(',', array_map(fn($r) => db()->quote($r['session']), $rows));
        foreach (db_all("SELECT session, ref_host, utm_source, MIN(ts) started FROM analytics_hits WHERE session IN ($in) AND kind = 'pageview' GROUP BY session, ref_host, utm_source ORDER BY started") as $f)
            if (!isset($first[$f['session']])) $first[$f['session']] = $f;
    }
    $visitors = array_map(function ($r) use ($first) {
        $f = $first[$r['session']] ?? $r;
        return [
            'path' => $r['path'], 'title' => $r['title'], 'country' => $r['country'], 'flag' => country_flag($r['country']),
            'place' => trim(implode(', ', array_filter([$r['city'], visitor_country_name($r['country'])])), ', '),
            'device' => $r['device'], 'browser' => $r['browser'], 'os' => $r['os'],
            'source' => analytics_source($f), 'pages' => (int)$r['pages'],
            'on_page' => fmt_secs(max(0, strtotime($r['last_seen']) - strtotime($r['ts']))),
            'in_visit' => fmt_secs(max(0, time() - strtotime((string)($f['started'] ?? $r['ts'])))),
            'user' => $r['user_name'] ? ['name' => $r['user_name'], 'email' => $r['user_email'], 'role' => $r['role']] : null,
            'is_new' => (bool)$r['is_new'],
        ];
    }, $rows);
    $perMin = [];
    foreach (db_all("SELECT DATE_FORMAT(ts, '%H:%i') m, COUNT(*) n FROM analytics_hits WHERE ts >= ? AND kind = 'pageview' GROUP BY m", [date('Y-m-d H:i:s', strtotime('-30 minutes'))]) as $r) $perMin[$r['m']] = (int)$r['n'];
    $mins = [];
    for ($i = 29; $i >= 0; $i--) { $k = date('H:i', strtotime("-$i minutes")); $mins[] = ['m' => $k, 'n' => $perMin[$k] ?? 0]; }
    $pages = [];
    foreach ($visitors as $v) $pages[$v['path']] = ($pages[$v['path']] ?? 0) + 1;
    arsort($pages);
    $events = db_all("SELECT h.event, h.meta, h.path, h.ts, h.country, u.name AS user_name FROM analytics_hits h LEFT JOIN users u ON u.id = h.user_id WHERE h.kind = 'event' AND h.ts >= ? ORDER BY h.id DESC LIMIT 15", [date('Y-m-d H:i:s', strtotime('-60 minutes'))]);
    return ['count' => count($visitors), 'visitors' => $visitors, 'minutes' => $mins, 'pages' => array_slice($pages, 0, 8, true),
        'events' => array_map(fn($e) => ['event' => $e['event'], 'meta' => $e['meta'], 'path' => $e['path'], 'ago' => time_ago($e['ts']), 'flag' => country_flag($e['country']), 'user' => $e['user_name']], $events),
        'at' => date('H:i:s')];
}

function admin_analytics_live(): void
{
    require_role('admin');
    header('Cache-Control: no-store');
    json_out(analytics_live_data());
}

function admin_analytics(): void
{
    require_role('admin');
    $range = (string)($_GET['range'] ?? '7d');
    if (!in_array($range, ['today', 'yesterday', '7d', '30d', '90d', '12m'], true)) $range = '7d';
    [$from, $to, $step] = analytics_range($range);
    $days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
    $pFrom = date('Y-m-d', strtotime($from . " -$days days")); $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
    $k = analytics_kpis($from, $to);
    $prev = analytics_kpis($pFrom, $pTo);

    // Time series
    $series = [];
    if ($step === 'hour') {
        $rows = db_all("SELECT HOUR(ts) h, COUNT(*) pv, COUNT(DISTINCT visitor) v FROM analytics_hits WHERE day = ? AND kind = 'pageview' GROUP BY h", [$from]);
        $by = array_column($rows, null, 'h');
        for ($h = 0; $h < 24; $h++) $series[] = ['label' => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00', 'v' => (int)($by[$h]['v'] ?? 0), 'pv' => (int)($by[$h]['pv'] ?? 0)];
    } elseif ($step === 'month') {
        $rows = db_all("SELECT DATE_FORMAT(day, '%Y-%m') m, COUNT(*) pv, COUNT(DISTINCT CONCAT(day, visitor)) v FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY m", [$from, $to]);
        $by = array_column($rows, null, 'm');
        for ($i = 11; $i >= 0; $i--) { $m = date('Y-m', strtotime("first day of -$i months")); $series[] = ['label' => date('M y', strtotime($m . '-01')), 'v' => (int)($by[$m]['v'] ?? 0), 'pv' => (int)($by[$m]['pv'] ?? 0)]; }
    } else {
        $rows = db_all("SELECT day, COUNT(*) pv, COUNT(DISTINCT visitor) v FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY day", [$from, $to]);
        $by = array_column($rows, null, 'day');
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) { $key = date('Y-m-d', $d); $series[] = ['label' => date($days > 31 ? 'j M' : 'D j', $d), 'v' => (int)($by[$key]['v'] ?? 0), 'pv' => (int)($by[$key]['pv'] ?? 0)]; }
    }

    // Sources: grouped in PHP so "google.com" and "google.co.uk" land together
    $sources = [];
    // A visit's source is where its first page came from
    foreach (db_all("SELECT h.ref_host, h.utm_source, COUNT(*) s, COUNT(DISTINCT CONCAT(h.day, h.visitor)) v FROM analytics_hits h
                     JOIN (SELECT MIN(id) id FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY session) f ON f.id = h.id
                     GROUP BY h.ref_host, h.utm_source", [$from, $to]) as $r) {
        $name = analytics_source($r);
        $sources[$name] = ['k' => $name, 'visitors' => ($sources[$name]['visitors'] ?? 0) + (int)$r['v'], 'views' => ($sources[$name]['views'] ?? 0) + (int)$r['s']];
    }
    usort($sources, fn($a, $b) => $b['visitors'] <=> $a['visitors']);

    $entry = db_all("SELECT path AS k, COUNT(*) AS views FROM analytics_hits h JOIN (SELECT MIN(id) id FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY session) f ON f.id = h.id GROUP BY path ORDER BY views DESC LIMIT 10", [$from, $to]);
    $pages = db_all("SELECT path AS k, MAX(title) AS title, COUNT(*) AS views, COUNT(DISTINCT CONCAT(day, visitor)) AS visitors, ROUND(AVG(NULLIF(duration, 0))) AS secs, ROUND(AVG(NULLIF(scroll, 0))) AS scroll FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY path ORDER BY views DESC LIMIT 15", [$from, $to]);
    $campaigns = db_all("SELECT CONCAT_WS(' / ', utm_source, utm_medium, utm_campaign) AS k, COUNT(DISTINCT session) AS views, COUNT(DISTINCT CONCAT(day, visitor)) AS visitors FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' AND utm_campaign IS NOT NULL GROUP BY k ORDER BY visitors DESC LIMIT 10", [$from, $to]);
    $events = db_all("SELECT event AS k, COUNT(*) AS views, COUNT(DISTINCT CONCAT(day, visitor)) AS visitors FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'event' GROUP BY event ORDER BY views DESC LIMIT 15", [$from, $to]);
    $bots = db_all('SELECT bot AS k, SUM(hits) AS views, MAX(last_at) AS last_at FROM analytics_bots WHERE day BETWEEN ? AND ? GROUP BY bot ORDER BY views DESC LIMIT 20', [$from, $to]);

    // Funnel from first visit to paying customer (the demo company is excluded)
    $dc = demo_cid();
    $f1 = $from . ' 00:00:00'; $f2 = $to . ' 23:59:59';
    $funnel = [
        ['label' => t('Visitors'), 'n' => $k['visitors']],
        ['label' => t('Opened the demo'), 'n' => (int)db_val("SELECT COUNT(DISTINCT CONCAT(day, visitor)) FROM analytics_hits WHERE day BETWEEN ? AND ? AND (path = '/demo' OR event = 'demo_open')", [$from, $to])],
        ['label' => t('Signed up'), 'n' => (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'employer' AND created_at BETWEEN ? AND ? AND COALESCE(company_id, 0) <> ?", [$f1, $f2, $dc])],
        ['label' => t('Started checkout'), 'n' => (int)db_val("SELECT COUNT(*) FROM analytics_hits WHERE day BETWEEN ? AND ? AND event = 'checkout_start'", [$from, $to])],
        ['label' => t('Paying companies'), 'n' => (int)db_val("SELECT COUNT(*) FROM companies WHERE id <> ? AND plan <> 'free' AND plan_status IN ('active','trialing')", [$dc])],
    ];
    $verif = (int)db_val('SELECT COUNT(*) FROM verifications WHERE created_at BETWEEN ? AND ? AND COALESCE(company_id, 0) <> ?', [$f1, $f2, $dc]);

    // Recent visits with their page trail
    $visits = db_all("SELECT session, MIN(ts) started, MAX(last_seen) ended, COUNT(*) pages, SUM(duration) secs, MAX(user_id) user_id,
                        SUBSTRING_INDEX(GROUP_CONCAT(country ORDER BY id), ',', 1) country, SUBSTRING_INDEX(GROUP_CONCAT(city ORDER BY id SEPARATOR '|'), '|', 1) city,
                        SUBSTRING_INDEX(GROUP_CONCAT(device ORDER BY id), ',', 1) device, SUBSTRING_INDEX(GROUP_CONCAT(browser ORDER BY id), ',', 1) browser,
                        SUBSTRING_INDEX(GROUP_CONCAT(os ORDER BY id), ',', 1) os, SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(ref_host, '') ORDER BY id), ',', 1) ref_host,
                        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(utm_source, '') ORDER BY id), ',', 1) utm_source, MAX(is_new) is_new
                      FROM analytics_hits WHERE day BETWEEN ? AND ? AND kind = 'pageview' GROUP BY session ORDER BY started DESC LIMIT 40", [$from, $to]);
    $trail = [];
    if ($visits) {
        $in = implode(',', array_map(fn($v) => db()->quote($v['session']), $visits));
        foreach (db_all("SELECT session, kind, path, event, ts, duration FROM analytics_hits WHERE session IN ($in) ORDER BY id") as $t) $trail[$t['session']][] = $t;
        $uids = array_filter(array_column($visits, 'user_id'));
        $users = $uids ? array_column(db_all('SELECT id, name, email, role FROM users WHERE id IN (' . implode(',', array_map('intval', $uids)) . ')'), null, 'id') : [];
    }

    render('admin/analytics', [
        'title' => t('Analytics'), 'nav' => 'admin-analytics', 'range' => $range, 'from' => $from, 'to' => $to, 'step' => $step,
        'k' => $k, 'prev' => $prev, 'series' => $series, 'sources' => array_slice($sources, 0, 12), 'entry' => $entry, 'pages' => $pages,
        'countries' => analytics_top('country', $from, $to, 12), 'cities' => analytics_top("CONCAT_WS(', ', city, country)", $from, $to, 10, 'AND city IS NOT NULL'),
        'devices' => analytics_top('device', $from, $to, 5), 'browsers' => analytics_top('browser', $from, $to, 8), 'oses' => analytics_top('os', $from, $to, 8),
        'langs' => analytics_top('lang', $from, $to, 8), 'campaigns' => $campaigns, 'events' => $events, 'bots' => $bots,
        'funnel' => $funnel, 'verif' => $verif, 'visits' => $visits, 'trail' => $trail, 'users' => $users ?? [],
        'live' => analytics_live_data(),
        'ga4' => (string)setting('ga4_id', ''), 'gsc' => (string)setting('google_site_verification', ''),
        'hasCity' => (bool)db_val('SELECT 1 FROM analytics_hits WHERE city IS NOT NULL LIMIT 1'),
        'trackAdmins' => setting('analytics_track_admins', '0') === '1', 'enabled' => analytics_enabled(),
    ], 'app');
}

function admin_analytics_settings(): void
{
    require_role('admin');
    db_exec("REPLACE INTO settings (k, v) VALUES ('analytics_enabled', ?)", [!empty($_POST['analytics_enabled']) ? '1' : '0']);
    db_exec("REPLACE INTO settings (k, v) VALUES ('analytics_track_admins', ?)", [!empty($_POST['analytics_track_admins']) ? '1' : '0']);
    flash('success', t('Analytics settings saved.'));
    redirect('/admin/analytics');
}
