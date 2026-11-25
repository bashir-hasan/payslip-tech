<?php
$checks = [
    [is_https(), t('HTTPS is active'), t('Turn on SSL in cPanel or Cloudflare.')],
    [!is_file(ROOT . '/install.php'), t('Installer removed'), t('Delete install.php from the server.')],
    [!$no2fa, t('All admins use two-factor sign-in'), t('Turn on two-factor authentication in My account.')],
    [version_compare(PHP_VERSION, '8.2.0', '>='), sprintf(t('PHP %s'), PHP_VERSION), t('Use PHP 8.2 or newer in cPanel for security updates.')],
    [!cfg('debug'), t('Debug mode off'), t('Set debug to false in config.php.')],
    [via_cloudflare() || !empty($_SERVER['HTTP_CF_RAY']), t('Protected by Cloudflare'), t('Optional: put the site behind Cloudflare for DDoS and bot protection.')],
];
?>
<div class="page-head"><div><h1><?= e(t('Security')) ?></h1><p><?= e(t('Security health check, locked accounts and the sign-in audit log.')) ?></p></div></div>
<div class="grid grid-3" style="margin-bottom:18px">
  <?php foreach ($checks as [$ok, $label, $fix]): ?>
    <div class="card card-pad"><div class="row" style="gap:10px;flex-wrap:nowrap"><span style="color:<?= $ok ? 'var(--ok)' : 'var(--warn)' ?>"><?= icon($ok ? 'check-circle' : 'alert', 20) ?></span><b><?= e($label) ?></b></div><?php if (!$ok): ?><p class="small muted" style="margin:8px 0 0"><?= e($fix) ?></p><?php endif; ?></div>
  <?php endforeach; ?>
</div>

<?php if ($locked): ?>
<div class="card" style="margin-bottom:18px"><div class="card-head"><h3><?= e(t('Locked accounts')) ?></h3></div>
  <div class="table-wrap"><table class="table"><tbody>
  <?php foreach ($locked as $l): ?><tr><td><b><?= e($l['name']) ?></b><div class="small muted"><?= e($l['email']) ?></div></td><td class="small muted"><?= e(sprintf(t('Until %s'), date_fmt($l['locked_until'], null, true))) ?></td><td class="num"><form method="post" action="<?= e(url('/admin/users/' . $l['id'] . '/unlock')) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm"><?= e(t('Unlock')) ?></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>

<div class="card"><div class="card-head"><h3><?= e(t('Sign-in & admin audit log')) ?></h3><span class="small muted"><?= e(t('Last 200 events')) ?></span></div>
  <div class="table-wrap"><table class="table stack-m">
    <thead><tr><th><?= e(t('Event')) ?></th><th><?= e(t('User')) ?></th><th><?= e(t('IP')) ?></th><th class="num"><?= e(t('When')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): $bad = str_starts_with($ev['action'], 'Failed') || str_contains($ev['action'], 'locked'); ?>
      <tr><td class="first"><span style="color:<?= $bad ? 'var(--bad)' : 'inherit' ?>"><?= e($ev['action']) ?></span></td><td data-l="<?= e(t('User')) ?>"><?= e($ev['email'] ?? '—') ?></td><td data-l="<?= e(t('IP')) ?>" class="mono small"><?= e($ev['ip'] ?? '') ?></td><td class="num small" data-l="<?= e(t('When')) ?>"><?= e(date_fmt($ev['created_at'], null, true)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="4" class="muted center"><?= e(t('No events yet.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
