<?php
$ranges = ['today' => t('Today'), 'yesterday' => t('Yesterday'), '7d' => t('7 days'), '30d' => t('30 days'), '90d' => t('90 days'), '12m' => t('12 months')];
$delta = function ($now, $before, bool $lowerIsBetter = false) {
    if (!$before) return $now ? '<span class="delta up">' . e(t('new')) . '</span>' : '';
    $p = round(($now - $before) / $before * 100);
    if ($p == 0) return '<span class="delta">0%</span>';
    $good = $lowerIsBetter ? $p < 0 : $p > 0;
    return '<span class="delta ' . ($good ? 'up' : 'down') . '">' . ($p > 0 ? '▲ ' : '▼ ') . abs($p) . '%</span>';
};
$list = function (string $title, array $rows, callable $label, string $valKey = 'visitors', string $valLabel = '', string $empty = '') {
    $max = max(1, ...array_map(fn($r) => (int)$r[$valKey], $rows ?: [[$valKey => 1]]));
    ob_start(); ?>
    <div class="card an-list">
      <div class="card-head"><h3><?= e($title) ?></h3><?php if ($valLabel): ?><span class="small muted"><?= e($valLabel) ?></span><?php endif; ?></div>
      <?php if ($rows): ?>
        <ul>
          <?php foreach ($rows as $r): ?>
            <li><span class="bar-bg" style="width:<?= round((int)$r[$valKey] / $max * 100) ?>%"></span><span class="lbl"><?= $label($r) ?></span><b><?= e(num((int)$r[$valKey])) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted small an-empty"><?= e($empty ?: t('No data for this period yet.')) ?></p>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
};
$maxS = max(1, ...array_map(fn($s) => $s['v'], $series));
$dev = ['mobile' => 'Mobile', 'desktop' => 'Desktop', 'tablet' => 'Tablet'];
?>
<div class="page-head">
  <div>
    <h1><?= e(t('Analytics')) ?></h1>
    <p><?= e(t('Who is on PaySlip.tech right now, where visitors come from and what they do. Collected on your own server, without cookies.')) ?></p>
  </div>
  <div class="row">
    <nav class="seg" aria-label="<?= e(t('Period')) ?>">
      <?php foreach ($ranges as $key => $lbl): ?><a href="<?= e(url('/admin/analytics?range=' . $key)) ?>" class="<?= $range === $key ? 'on' : '' ?>" <?= $range === $key ? 'aria-current="page"' : '' ?>><?= e($lbl) ?></a><?php endforeach; ?>
    </nav>
  </div>
</div>

<?php if (!$enabled): ?><div class="alert alert-warn" style="margin-bottom:16px"><?= icon('alert') ?> <?= e(t('Analytics collection is switched off. Turn it on in the settings at the bottom of this page.')) ?></div><?php endif; ?>

<!-- Live -->
<section class="card an-live" aria-labelledby="live-h" data-live-url="<?= e(url('/admin/analytics/live')) ?>">
  <div class="an-live-top">
    <div>
      <p class="eyebrow-sm"><span class="pulse" aria-hidden="true"></span> <?= e(t('Live')) ?> · <span data-live-at><?= e($live['at']) ?></span></p>
      <h2 id="live-h"><span data-live-count><?= e(num($live['count'])) ?></span> <small><?= e(t('visitors on the site right now')) ?></small></h2>
      <p class="small an-live-sub"><?= e(sprintf(t('Active in the last %d minutes. Updates every 10 seconds.'), ANALYTICS_LIVE_MINUTES)) ?></p>
    </div>
    <div class="an-mins" data-live-mins aria-label="<?= e(t('Page views per minute, last 30 minutes')) ?>" role="img">
      <?php $mm = max(1, ...array_column($live['minutes'], 'n')); foreach ($live['minutes'] as $m): ?><i style="height:<?= max(4, round($m['n'] / $mm * 100)) ?>%" title="<?= e($m['m'] . ' · ' . $m['n']) ?>"></i><?php endforeach; ?>
      <span><?= e(t('Page views per minute, last 30 min')) ?></span>
    </div>
  </div>
  <div class="an-live-body">
    <div class="table-wrap an-live-table" tabindex="0" role="region" aria-label="<?= e(t('Visitors on the site right now')) ?>">
      <table class="table">
        <thead><tr><th><?= e(t('Visitor')) ?></th><th><?= e(t('Now on page')) ?></th><th><?= e(t('Came from')) ?></th><th><?= e(t('Device')) ?></th><th class="num"><?= e(t('In visit')) ?></th></tr></thead>
        <tbody data-live-rows>
        <?php if (!$live['visitors']): ?><tr><td colspan="5" class="muted"><?= e(t('Nobody is browsing right now.')) ?></td></tr><?php endif; ?>
        <?php foreach ($live['visitors'] as $v): ?>
          <tr>
            <td><span class="flag"><?= $v['flag'] ?></span> <?= $v['user'] ? '<b>' . e($v['user']['name']) . '</b> <span class="badge plain">' . e($v['user']['role']) . '</span>' : e($v['place'] ?: t('Unknown')) ?><?= $v['is_new'] ? ' <span class="badge badge-brand plain">' . e(t('new')) . '</span>' : '' ?></td>
            <td class="mono small"><?= e($v['path']) ?></td>
            <td><?= e($v['source']) ?></td>
            <td class="small"><?= e(($dev[$v['device']] ?? $v['device']) . ' · ' . $v['browser']) ?></td>
            <td class="num small"><?= e($v['in_visit']) ?> · <?= e(sprintf(t('%d pages'), $v['pages'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="an-feed">
      <h3><?= e(t('Latest actions')) ?></h3>
      <ul data-live-events>
        <?php if (!$live['events']): ?><li class="muted small"><?= e(t('Sign-ups, demo visits, checkouts and button clicks appear here.')) ?></li><?php endif; ?>
        <?php foreach ($live['events'] as $ev): ?><li><span class="flag"><?= $ev['flag'] ?></span><span><b><?= e(str_replace('_', ' ', $ev['event'])) ?></b><?= $ev['meta'] ? ' · ' . e($ev['meta']) : '' ?><?= $ev['user'] ? ' · ' . e($ev['user']) : '' ?><em><?= e($ev['ago']) ?></em></span></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<!-- KPIs -->
<div class="grid an-kpis">
  <div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e(t('Visitors')) ?></div><div class="v"><?= e(num($k['visitors'])) ?></div><div class="d"><?= $delta($k['visitors'], $prev['visitors']) ?> <?= e(t('vs previous period')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('file', 16) ?> <?= e(t('Page views')) ?></div><div class="v"><?= e(num($k['pageviews'])) ?></div><div class="d"><?= $delta($k['pageviews'], $prev['pageviews']) ?> · <?= e(sprintf(t('%s per visit'), $k['ppv'])) ?></div></div>
  <div class="stat"><div class="k"><?= icon('clock', 16) ?> <?= e(t('Avg. visit')) ?></div><div class="v"><?= e(fmt_secs($k['duration'])) ?></div><div class="d"><?= $delta($k['duration'], $prev['duration']) ?> · <?= e(sprintf(t('%s visits'), num($k['sessions']))) ?></div></div>
  <div class="stat"><div class="k"><?= icon('arrow-right', 16) ?> <?= e(t('Bounce rate')) ?></div><div class="v"><?= e($k['bounce']) ?>%</div><div class="d"><?= $delta($k['bounce'], $prev['bounce'], true) ?> <?= e(t('left after one page')) ?></div></div>
  <div class="stat"><div class="k"><?= icon('shield', 16) ?> <?= e(t('Verifications')) ?></div><div class="v"><?= e(num($verif)) ?></div><div class="d"><?= e(t('payslips and letters checked')) ?></div></div>
</div>

<div class="grid an-2" style="margin-top:18px">
  <div class="card">
    <div class="card-head"><h3><?= e(t('Visitors over time')) ?></h3><span class="small muted"><?= e(date_fmt($from)) ?><?= $from !== $to ? ' – ' . e(date_fmt($to)) : '' ?></span></div>
    <div class="card-body">
      <div class="an-chart" role="img" aria-label="<?= e(t('Visitors over time')) ?>">
        <?php foreach ($series as $i => $s): ?>
          <div class="col" title="<?= e($s['label'] . ': ' . $s['v'] . ' ' . t('visitors') . ', ' . $s['pv'] . ' ' . t('page views')) ?>">
            <i style="height:<?= $s['v'] ? max(3, round($s['v'] / $maxS * 100)) : 0 ?>%"></i>
            <?php if (count($series) <= 14 || $i % max(1, (int)ceil(count($series) / 10)) === 0): ?><span><?= e($s['label']) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= e(t('From visit to customer')) ?></h3></div>
    <div class="card-body an-funnel">
      <?php $top = max(1, $funnel[0]['n']); foreach ($funnel as $i => $f): ?>
        <div class="fstep"><div class="row-between"><span><?= e($f['label']) ?></span><b><?= e(num($f['n'])) ?><?php if ($i > 0 && $funnel[0]['n']): ?> <small class="muted"><?= e(round($f['n'] / $top * 100, 1)) ?>%</small><?php endif; ?></b></div><div class="progress"><i style="width:<?= min(100, max($f['n'] ? 2 : 0, round($f['n'] / $top * 100))) ?>%"></i></div></div>
      <?php endforeach; ?>
      <p class="small muted" style="margin:6px 0 0"><?= e(t('Paying companies is the current total, not just this period.')) ?></p>
    </div>
  </div>
</div>

<div class="grid an-3" style="margin-top:18px">
  <?= $list(t('Where visitors come from'), $sources, fn($r) => e($r['k']), 'visitors', t('Visitors')) ?>
  <?= $list(t('Top pages'), $pages, fn($r) => '<span class="mono">' . e($r['k']) . '</span>' . ($r['secs'] ? '<em>' . e(fmt_secs((int)$r['secs'])) . ($r['scroll'] ? ' · ' . e($r['scroll']) . '% ' . e(t('scrolled')) : '') . '</em>' : ''), 'views', t('Views')) ?>
  <?= $list(t('Countries'), $countries, fn($r) => '<span class="flag">' . country_flag($r['k']) . '</span> ' . e(visitor_country_name($r['k'])), 'visitors', t('Visitors')) ?>
  <?= $list(t('Landing pages'), $entry, fn($r) => '<span class="mono">' . e($r['k']) . '</span>', 'views', t('Visits')) ?>
  <?= $list(t('Devices'), $devices, fn($r) => e($dev[$r['k']] ?? ($r['k'] ?: t('Unknown'))), 'visitors', t('Visitors')) ?>
  <?= $list(t('Browsers'), $browsers, fn($r) => e($r['k'] ?: t('Unknown')), 'visitors', t('Visitors')) ?>
  <?= $list(t('Operating systems'), $oses, fn($r) => e($r['k'] ?: t('Unknown')), 'visitors', t('Visitors')) ?>
  <?= $list(t('Cities'), $cities, fn($r) => e($r['k']), 'visitors', t('Visitors'), $hasCity ? '' : t('Turn on "Add visitor location headers" in Cloudflare (Rules → Transform Rules → Managed Transforms) to see cities.')) ?>
  <?= $list(t('Browser languages'), $langs, fn($r) => e($r['k'] ?: t('Unknown')), 'visitors', t('Visitors')) ?>
  <?= $list(t('Campaigns (UTM)'), $campaigns, fn($r) => e($r['k']), 'visitors', t('Visitors'), t('Add ?utm_source=…&utm_campaign=… to links you share to see them here.')) ?>
  <?= $list(t('Actions'), $events, fn($r) => e(str_replace('_', ' ', $r['k'])), 'views', t('Times'), t('Sign-ups, demo visits, checkouts and chat opens will appear here.')) ?>
  <?= $list(t('Search engines & AI crawlers'), $bots, fn($r) => e($r['k']) . ($r['last_at'] ? '<em>' . e(t('last')) . ' ' . e(time_ago($r['last_at'])) . '</em>' : ''), 'views', t('Requests'), t('Google, Bing, ChatGPT, Claude and Perplexity crawlers are counted here when they visit.')) ?>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-head"><h3><?= e(t('Recent visits')) ?></h3><span class="small muted"><?= e(t('Click a visit to see every page in order')) ?></span></div>
  <?php if ($visits): ?>
  <div class="an-visits">
    <?php foreach ($visits as $v): $u = $users[$v['user_id']] ?? null; ?>
      <details>
        <summary>
          <span class="flag"><?= country_flag($v['country']) ?></span>
          <span class="who"><?= $u ? '<b>' . e($u['name']) . '</b> <span class="small muted">' . e($u['email']) . '</span>' : e(trim(($v['city'] ? $v['city'] . ', ' : '') . visitor_country_name($v['country']))) ?><?= $v['is_new'] ? ' <span class="badge badge-brand plain">' . e(t('new')) . '</span>' : '' ?></span>
          <span class="meta">
            <span class="small muted"><?= e(analytics_source($v)) ?></span>
          <span class="small muted"><?= e(($dev[$v['device']] ?? $v['device']) . ' · ' . $v['browser'] . ' · ' . $v['os']) ?></span>
            <span class="small"><?= e(sprintf(t('%d pages'), $v['pages'])) ?> · <?= e(fmt_secs((int)$v['secs'])) ?></span>
          </span>
          <span class="small muted num"><?= e(time_ago($v['started'])) ?></span>
        </summary>
        <ol class="trail">
          <?php foreach ($trail[$v['session']] ?? [] as $t): ?>
            <li class="<?= $t['kind'] ?>"><span class="small muted"><?= e(date('H:i:s', strtotime($t['ts']))) ?></span> <?= $t['kind'] === 'event' ? '<b>' . e(str_replace('_', ' ', $t['event'])) . '</b> <span class="mono small">' . e($t['path']) . '</span>' : '<span class="mono">' . e($t['path']) . '</span>' . ($t['duration'] ? ' <span class="small muted">' . e(fmt_secs((int)$t['duration'])) . '</span>' : '') ?></li>
          <?php endforeach; ?>
        </ol>
      </details>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty"><div class="ic-wrap"><?= icon('chart', 24) ?></div><p><?= e(t('Visits will appear here as soon as people browse the site.')) ?></p></div>
  <?php endif; ?>
</div>

<div class="grid an-2" style="margin-top:18px">
  <div class="card">
    <div class="card-head"><h3><?= e(t('Google connections')) ?></h3></div>
    <div class="card-body an-google">
      <div class="row-between"><span><b>Google Analytics 4</b><br><span class="small muted"><?= $ga4 ? e(t('Measurement ID')) . ' <span class="mono">' . e($ga4) . '</span>' : e(t('Not connected')) ?></span></span>
        <?= $ga4 ? '<a class="btn btn-light btn-sm" href="https://analytics.google.com/" target="_blank" rel="noopener">' . e(t('Open')) . ' ' . icon('arrow-right', 14) . '</a>' : '<a class="btn btn-light btn-sm" href="' . e(url('/admin/code')) . '">' . e(t('Add ID')) . '</a>' ?></div>
      <div class="row-between"><span><b>Google Search Console</b><br><span class="small muted"><?= $gsc ? e(t('Verification tag added')) : e(t('Not connected')) ?></span></span>
        <?= $gsc ? '<a class="btn btn-light btn-sm" href="https://search.google.com/search-console?resource_id=' . e(rawurlencode(site_origin() . '/')) . '" target="_blank" rel="noopener">' . e(t('Open')) . ' ' . icon('arrow-right', 14) . '</a>' : '<a class="btn btn-light btn-sm" href="' . e(url('/admin/code')) . '">' . e(t('Add tag')) . '</a>' ?></div>
      <p class="small muted" style="margin:0"><?= e(t('Google Analytics only runs for visitors who accept analytics cookies, so its numbers are lower than the ones on this page, which count everyone without cookies.')) ?></p>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= e(t('Settings & privacy')) ?></h3></div>
    <form method="post" action="<?= e(url('/admin/analytics/settings')) ?>" class="card-body an-settings">
      <?= csrf_field() ?>
      <label class="check"><input type="checkbox" name="analytics_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> <span><?= e(t('Collect first-party analytics')) ?></span></label>
      <label class="check"><input type="checkbox" name="analytics_track_admins" value="1" <?= $trackAdmins ? 'checked' : '' ?>> <span><?= e(t('Also count my own visits as admin')) ?></span></label>
      <p class="small muted"><?= e(t('No cookies and no stored IP addresses: visitors are counted with a code that changes every day. Browsers that send Do Not Track or Global Privacy Control are not counted. Data older than 13 months is deleted automatically.')) ?></p>
      <button class="btn btn-primary btn-sm"><?= e(t('Save')) ?></button>
    </form>
  </div>
</div>
