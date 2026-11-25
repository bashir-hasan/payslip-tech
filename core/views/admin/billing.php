<?php $hasToken = (bool)setting('polar_token'); $hasSecret = (bool)setting('polar_webhook_secret'); ?>
<div class="page-head"><div><h1><?= e(t('Billing & plans')) ?></h1><p><?= e(t('Sell subscriptions through Polar.sh. Polar is the merchant of record: it charges customers worldwide, handles sales tax/VAT and pays out to your bank.')) ?></p></div></div>

<div class="grid grid-3" style="margin-bottom:18px">
  <?php foreach ($plans as $k => $p): ?><div class="stat"><div class="k"><?= icon('users', 16) ?> <?= e($p['name']) ?></div><div class="v"><?= e(num($k === 'free' ? '—' : (int)($byPlan[$k] ?? 0))) ?></div><div class="d"><?= e($k === 'free' ? t('Everyone else') : t('active subscriptions')) ?></div></div><?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('/admin/billing')) ?>">
  <?= csrf_field() ?>
  <div class="grid grid-2" style="align-items:start">
    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Polar connection')) ?></div>
      <ol class="small muted" style="padding-left:18px;margin-top:0">
        <li><?= e(t('In Polar, create 2 products (e.g. Starter and Business) with a monthly recurring price.')) ?></li>
        <li><?= e(t('Settings → Developers → New token with scopes checkouts:write, customer_sessions:write, products:read.')) ?></li>
        <li><?= e(t('Settings → Webhooks → Add endpoint:')) ?> <code class="codepill"><?= e(abs_url('/webhooks/polar')) ?></code> — <?= e(t('format Raw, events: all subscription.* events.')) ?></li>
        <li><?= e(t('Paste the token, webhook secret and product IDs here, then enable billing.')) ?></li>
      </ol>
      <div class="field"><label class="label"><?= e(t('Access token')) ?> <?= $hasToken ? '<span class="badge badge-ok plain">' . e(t('saved')) . '</span>' : '' ?></label><input class="input mono" type="password" name="polar_token" autocomplete="off" placeholder="<?= e($hasToken ? t('Leave blank to keep the saved token') : 'polar_oat_…') ?>"></div>
      <div class="field"><label class="label"><?= e(t('Webhook secret')) ?> <?= $hasSecret ? '<span class="badge badge-ok plain">' . e(t('saved')) . '</span>' : '' ?></label><input class="input mono" type="password" name="polar_webhook_secret" autocomplete="off" placeholder="<?= e($hasSecret ? t('Leave blank to keep the saved secret') : 'whsec_…') ?>"><span class="hint"><?= e(t('Stored encrypted. Never shown again.')) ?></span></div>
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="polar_sandbox" value="1" <?= setting('polar_sandbox', '0') === '1' ? 'checked' : '' ?>> <span><b><?= e(t('Sandbox mode')) ?></b> <span class="small muted">— <?= e(t('test payments at sandbox.polar.sh')) ?></span></span></label>
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="billing_enabled" value="1" <?= setting('billing_enabled', '0') === '1' ? 'checked' : '' ?>> <span><b><?= e(t('Enable paid plans and employee limits')) ?></b><br><span class="small muted"><?= e(t('While off, every company is unlimited and free.')) ?></span></span></label>
      <label class="check"><input type="checkbox" name="test_connection" value="1"> <?= e(t('Test the Polar connection when saving')) ?></label>
    </div></div>

    <div class="card"><div class="card-head"><h3><?= e(t('Recent billing events')) ?></h3></div>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($events as $ev): ?><tr><td><span class="codepill"><?= e($ev['type']) ?></span><div class="small muted"><?= e($ev['name'] ?? '—') ?></div></td><td class="num small muted"><?= e(time_ago($ev['created_at'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$events): ?><tr><td class="muted"><?= e(t('No webhook events received yet.')) ?></td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="card-head"><h3><?= e(t('Plans')) ?></h3><span class="small muted"><?= e(t('Prices are shown on the website; the real charge is the price set on the Polar product.')) ?></span></div>
    <div class="card-body"><div class="grid grid-3">
      <?php foreach ($plans as $k => $p): ?>
        <div>
          <div class="fieldset-title"><?= e(strtoupper($k)) ?></div>
          <div class="field"><label class="label"><?= e(t('Name')) ?></label><input class="input" name="<?= e($k) ?>_name" value="<?= e($p['name']) ?>"></div>
          <div class="form-grid">
            <div class="field"><label class="label"><?= e(t('Price (USD / month)')) ?></label><input class="input" name="<?= e($k) ?>_price" value="<?= e((string)$p['price']) ?>" <?= $k === 'free' ? 'disabled' : '' ?>></div>
            <div class="field"><label class="label"><?= e(t('Max employees')) ?></label><input class="input" name="<?= e($k) ?>_employees" value="<?= e((string)$p['employees']) ?>" type="number" min="1"></div>
          </div>
          <div class="field"><label class="label"><?= e(t('Smart automation actions / month')) ?></label><input class="input" name="<?= e($k) ?>_smart" value="<?= e((string)($p['smart'] ?? 30)) ?>" type="number" min="0"><span class="hint"><?= e(t('Assistant commands, smart imports and payslip explanations. Auto payroll is unlimited.')) ?></span></div>
          <?php if ($k !== 'free'): ?><div class="field"><label class="label"><?= e(t('Polar product ID')) ?></label><input class="input mono" name="<?= e($k) ?>_product_id" value="<?= e($p['product_id']) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></div><?php endif; ?>
          <div class="field"><label class="label"><?= e(t('Badge')) ?></label><input class="input" name="<?= e($k) ?>_badge" value="<?= e($p['badge']) ?>" placeholder="<?= e(t('e.g. Most popular')) ?>"></div>
          <div class="field"><label class="label"><?= e(t('Features (one per line)')) ?></label><textarea class="textarea" name="<?= e($k) ?>_features" rows="5"><?= e($p['features']) ?></textarea></div>
        </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="row" style="justify-content:flex-end;margin-top:18px"><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save billing settings')) ?></button></div>
</form>
