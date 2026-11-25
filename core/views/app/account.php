<?php $has2fa = !empty($u['totp_secret']); ?>
<div class="page-head"><div><h1><?= e(t('My account')) ?></h1><p><?= e(t('Your name, language, password and sign-in security.')) ?></p></div></div>
<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start" data-acc>
<form method="post" action="<?= e(url('/account')) ?>">
  <?= csrf_field() ?>
  <div class="card"><div class="card-body">
    <div class="fieldset-title"><?= e(t('Profile')) ?></div>
    <div class="field"><label class="label"><?= e(t('Name')) ?></label><input class="input" name="name" value="<?= e($u['name']) ?>" required></div>
    <div class="field"><label class="label"><?= e(t('Email')) ?></label><input class="input" value="<?= e($u['email']) ?>" disabled></div>
    <div class="field"><label class="label"><?= e(t('Language')) ?></label><select class="select" name="lang"><option value="en" <?= $u['lang'] === 'en' ? 'selected' : '' ?>>English</option><option value="bn" <?= $u['lang'] === 'bn' ? 'selected' : '' ?>>বাংলা</option></select></div>
    <hr>
    <div class="fieldset-title"><?= e(t('Change password')) ?></div>
    <div class="field"><label class="label"><?= e(t('Current password')) ?></label><input class="input" type="password" name="current_password" autocomplete="current-password"></div>
    <div class="field"><label class="label"><?= e(t('New password')) ?></label><input class="input" type="password" name="new_password" minlength="8" autocomplete="new-password"><span class="hint"><?= e(t('Leave blank to keep your current password. Changing it signs you out on other devices.')) ?></span></div>
  </div>
  <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= e(t('Save changes')) ?></button></div></div>
</form>

<div class="stack" id="security">
  <div class="card"><div class="card-head"><h3><?= icon('shield') ?> <?= e(t('Two-factor authentication')) ?></h3><?php partial('result_badge', ['r' => $has2fa ? 'active' : 'inactive']); ?></div>
  <div class="card-body">
    <?php if ($codes): ?>
      <div class="alert alert-warn" style="display:block"><b><?= e(t('Save these recovery codes now')) ?></b><p style="margin:4px 0 10px"><?= e(t('Each code works once if you lose your phone. They will not be shown again.')) ?></p>
        <div class="grid grid-2 mono" style="gap:6px"><?php foreach ($codes as $c): ?><span class="codepill" style="text-align:center"><?= e($c) ?></span><?php endforeach; ?></div>
        <button type="button" class="btn btn-light btn-sm" style="margin-top:10px" data-copy="<?= e(implode("\n", $codes)) ?>" data-copied="✓ <?= e(t('Copied')) ?>"><?= icon('copy', 15) ?> <?= e(t('Copy codes')) ?></button>
      </div>
    <?php endif; ?>

    <?php if ($has2fa): ?>
      <p class="muted"><?= e(t('Your account asks for a code from your authenticator app every time you sign in.')) ?></p>
      <form method="post" action="<?= e(url('/account/2fa/disable')) ?>" data-confirm="<?= e(t('Turn off two-factor authentication? Your account will be less secure.')) ?>">
        <?= csrf_field() ?>
        <div class="field"><label class="label"><?= e(t('Confirm with your password')) ?></label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
        <button class="btn btn-danger"><?= e(t('Turn off')) ?></button>
      </form>
    <?php elseif ($setup): ?>
      <p><?= e(t('1. Scan this QR code with Google Authenticator, Microsoft Authenticator or Authy.')) ?></p>
      <div class="row" style="gap:18px;align-items:flex-start">
        <div data-qr="<?= e($setup['uri']) ?>" style="width:160px;height:160px;background:#fff;padding:8px;border-radius:12px"></div>
        <div style="flex:1;min-width:180px"><span class="small muted"><?= e(t('Or enter this key manually:')) ?></span><div class="codepill" style="display:block;margin-top:6px;word-break:break-all;white-space:normal"><?= e(trim(chunk_split($setup['secret'], 4, ' '))) ?></div></div>
      </div>
      <form method="post" action="<?= e(url('/account/2fa/enable')) ?>" style="margin-top:16px">
        <?= csrf_field() ?>
        <div class="field"><label class="label"><?= e(t('2. Enter the 6-digit code from the app')) ?></label><input class="input mono" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required style="max-width:200px;letter-spacing:.2em"></div>
        <div class="row"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Turn on')) ?></button></div>
      </form>
      <form method="post" action="<?= e(url('/account/2fa/cancel')) ?>" style="margin-top:8px"><?= csrf_field() ?><button class="linkbtn small"><?= e(t('Cancel')) ?></button></form>
    <?php else: ?>
      <p class="muted"><?= e(t('Add a second step to sign-in. Even if someone steals your password, they cannot get in without your phone.')) ?></p>
      <form method="post" action="<?= e(url('/account/2fa/start')) ?>"><?= csrf_field() ?><button class="btn btn-dark"><?= icon('lock') ?> <?= e(t('Set up two-factor authentication')) ?></button></form>
    <?php endif; ?>
  </div></div>

  <div class="card"><div class="card-head"><h3><?= e(t('Recent account activity')) ?></h3></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($recent as $r): ?><tr><td><?= e($r['action']) ?><div class="small muted mono"><?= e($r['ip'] ?? '') ?></div></td><td class="num small muted"><?= e(time_ago($r['created_at'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td class="muted"><?= e(t('No activity yet.')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="card-foot small muted"><?= e(t('If you see activity you do not recognise, change your password and turn on two-factor authentication.')) ?></div>
  </div>
</div>
</div>
<style>@media (max-width:960px){[data-acc]{grid-template-columns:1fr!important}}</style>
