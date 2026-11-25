<div class="page-head"><div><h1><?= e(t('Company settings')) ?></h1><p><?= e(t('These details appear on every new payslip. Payslips already issued keep the details they were issued with.')) ?></p></div></div>
<?php if ($demo): ?><div class="alert alert-info"><?= icon('lock') ?><div><?= e(t('Company settings are locked in the demo account.')) ?></div></div><?php endif; ?>
<form method="post" action="<?= e(url('/app/settings')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:18px;align-items:start" data-st>
    <div class="card"><div class="card-body">
      <div class="fieldset-title"><?= e(t('Company profile')) ?></div>
      <div class="form-grid">
        <div class="field span-2"><label class="label"><?= e(t('Company name')) ?> *</label><input class="input" name="name" value="<?= e($c['name']) ?>" required></div>
        <div class="field span-2"><label class="label"><?= e(t('Address')) ?></label><textarea class="textarea" name="address" rows="3"><?= e($c['address']) ?></textarea></div>
        <div class="field"><label class="label"><?= e(t('Phone')) ?></label><input class="input" name="phone" value="<?= e($c['phone']) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Email')) ?></label><input class="input" type="email" name="email" value="<?= e($c['email']) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Website')) ?></label><input class="input" name="website" value="<?= e($c['website']) ?>" placeholder="https://"></div>
        <div class="field"><label class="label"><?= e(company_labels($c)['tax_id']) ?></label><input class="input mono" name="tin" value="<?= e($c['tin']) ?>"></div>
      </div>
      <div class="fieldset-title" style="margin-top:8px"><?= e(t('Authorised signatory')) ?></div>
      <div class="form-grid">
        <div class="field"><label class="label"><?= e(t('Name')) ?></label><input class="input" name="signatory_name" value="<?= e($c['signatory_name']) ?>"></div>
        <div class="field"><label class="label"><?= e(t('Title')) ?></label><input class="input" name="signatory_title" value="<?= e($c['signatory_title']) ?>" placeholder="<?= e(t('e.g. HR Manager')) ?>"></div>
      </div>
    </div></div>

    <div class="stack">
      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Logo')) ?></div>
        <div class="row" style="gap:16px;margin-bottom:12px">
          <div style="width:72px;height:72px;border:1px dashed var(--line);border-radius:14px;display:grid;place-items:center;overflow:hidden;background:#fff"><?php if ($c['logo']): ?><img src="<?= e(url('uploads/logos/' . $c['logo'])) ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain"><?php else: ?><span class="muted"><?= icon('building', 26) ?></span><?php endif; ?></div>
          <div style="flex:1;min-width:0"><input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp" style="padding-top:9px"><span class="hint"><?= e(t('PNG, JPG or WebP, under 1 MB.')) ?></span></div>
        </div>
        <?php if ($c['logo']): ?><label class="check small"><input type="checkbox" name="remove_logo" value="1"> <?= e(t('Remove logo')) ?></label><?php endif; ?>
      </div></div>
      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Country & currency')) ?></div>
        <div class="field"><label class="label"><?= e(t('Country')) ?></label><select class="select" name="country"><?php foreach (countries() as $code => [$cn]): ?><option value="<?= e($code) ?>" <?= ($c['country'] ?? 'BD') === $code ? 'selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select><span class="hint"><?= e(t('Changing the country switches payslip terms to local ones (for example "Pay Stub" and FICA in the US).')) ?></span></div>
        <div class="field"><label class="label"><?= e(t('Currency')) ?></label><select class="select" name="currency"><?php foreach (CURRENCIES as $code => [$sym, $nm]): ?><option value="<?= e($code) ?>" <?= ($c['currency'] ?? 'BDT') === $code ? 'selected' : '' ?>><?= e($code . ' — ' . $nm . ' (' . trim($sym) . ')') ?></option><?php endforeach; ?></select></div>
      </div></div>
      <div class="card"><div class="card-body">
        <div class="fieldset-title"><?= e(t('Payslips & verification')) ?></div>
        <div class="field"><label class="label"><?= e(t('Default payslip language')) ?></label><select class="select" name="payslip_lang"><option value="en" <?= $c['payslip_lang'] === 'en' ? 'selected' : '' ?>>English</option><option value="bn" <?= $c['payslip_lang'] === 'bn' ? 'selected' : '' ?>>বাংলা</option></select><span class="hint"><?= e(t('Anyone viewing a payslip can switch language.')) ?></span></div>
        <label class="check"><input type="checkbox" name="show_amounts" value="1" <?= (int)$c['show_amounts'] === 1 ? 'checked' : '' ?>> <span><b><?= e(t('Show amounts on verification')) ?></b><br><span class="small muted"><?= e(t('Lets banks and embassies confirm gross, deductions and net pay. Recommended.')) ?></span></span></label>
      </div></div>
    </div>
  </div>
  <?php $lbl = company_labels($c); $names = ['title' => t('Document title'), 'tax_id' => t('Company tax ID name'), 'basic' => t('Earning 1 (base pay)'), 'house_rent' => t('Earning 2'), 'medical' => t('Earning 3'), 'conveyance' => t('Earning 4'), 'other_allowance' => t('Earning 5'), 'overtime' => t('Overtime'), 'bonus' => t('Bonus'), 'tax' => t('Deduction 1 (tax)'), 'pf' => t('Deduction 2 (retirement)'), 'loan' => t('Loan / advance'), 'absence' => t('Unpaid leave'), 'other' => t('Deduction 3 (other)')]; ?>
  <div class="card" style="margin-top:18px">
    <div class="card-head"><h3><?= e(t('Payslip line names')) ?></h3><label class="check small"><input type="checkbox" name="reset_labels" value="1"> <?= e(t('Reset to local defaults')) ?></label></div>
    <div class="card-body"><p class="small muted" style="margin-top:0"><?= e(t('Rename any line to match your country or company. These names print on payslips issued from now on.')) ?></p>
      <div class="form-grid-3"><?php foreach ($names as $k => $nm): ?><div class="field"><label class="label"><?= e($nm) ?></label><input class="input" name="label_<?= e($k) ?>" value="<?= e($lbl[$k] ?? '') ?>" maxlength="60"></div><?php endforeach; ?></div>
    </div>
  </div>
  <div class="row" style="justify-content:flex-end;margin-top:18px"><button class="btn btn-primary" <?= $demo ? 'disabled' : '' ?>><?= icon('check') ?> <?= e(t('Save settings')) ?></button></div>
</form>
<style>@media (max-width:960px){[data-st]{grid-template-columns:1fr!important}}</style>

<?php $dom = $c['domain'] ?? ''; ?>
<div class="card" id="domain" style="margin-top:18px"><div class="card-body">
  <div class="fieldset-title"><?= icon('globe', 16) ?> <?= e(t('Verify your company domain')) ?></div>
  <p class="small muted"><?= e(t('Banks and embassies see "Owns the domain" next to your payslips and letters. It shows the company is real, not a fake account.')) ?></p>
  <?php if (!empty($c['domain_verified_at'])): ?>
    <div class="alert alert-success"><?= icon('check') ?><div><?= e(sprintf(t('%s verified on %s.'), $dom, date_fmt($c['domain_verified_at']))) ?></div></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/app/settings/domain')) ?>" class="row" style="gap:8px;flex-wrap:wrap"><?= csrf_field() ?>
    <input class="input" name="domain" value="<?= e($dom) ?>" placeholder="yourcompany.com" style="max-width:280px">
    <button class="btn btn-light" name="action" value="save"><?= e(t('Get TXT record')) ?></button>
    <?php if ($dom): ?><button class="btn btn-primary" name="action" value="check"><?= icon('search') ?> <?= e(t('Check now')) ?></button><?php endif; ?>
  </form>
  <?php if ($dom && empty($c['domain_verified_at'])): ?>
    <p class="small" style="margin-top:12px"><?= e(t('Add this TXT record at your domain provider (Cloudflare, Namecheap, cPanel zone editor). Host / name: @')) ?></p>
    <div class="row" style="gap:8px"><code class="mono" style="padding:8px 10px;background:var(--soft,#f4f6f8);border-radius:8px;word-break:break-all"><?= e(domain_token($c, $dom)) ?></code><button class="btn btn-light btn-sm" data-copy="<?= e(domain_token($c, $dom)) ?>" data-copied="✓"><?= icon('copy', 15) ?></button></div>
  <?php endif; ?>
</div></div>
