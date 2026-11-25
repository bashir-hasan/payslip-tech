<?php
$snap = $p['snapshot'];
$co = $snap['company'];
$em = $snap['employee'];
$lines = $p['breakdown'] ?: [];
$isDraft = $p['status'] === 'draft';
$isRevoked = $p['status'] === 'revoked';
$code = $p['code'] ?? null;
$vurl = $code ? verify_url($code) : '';
$m = fn($v) => money_fmt($v, true, $L);
$labels = snapshot_labels($snap);
$sym = currency_symbol();
$docTitle = $L === 'bn' ? tp('Payslip', $L) : ($labels['title'] ?? 'Payslip');
$back = $u ? ($u['role'] === 'employee' ? '/me' : ($u['role'] === 'admin' ? '/admin' : ($isDraft ? '/app/payroll/' . $p['run_id'] : '/app/payslips'))) : '/';
$self = url('/payslip/' . ($code ?: 'draft-' . $p['id']));
?><!doctype html>
<html lang="<?= e($L) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', ['title' => tp('Payslip', $L) . ' ' . ($em['name'] ?? '') . ' ' . period_label($p['period'], 'en'), 'noindex' => true, 'context' => 'doc']); ?></head>
<body class="doc-body">
<div class="doc-toolbar no-print">
  <div class="inner">
    <a class="back" href="<?= e(url($back)) ?>">← <?= e(t('Back')) ?></a>
    <div class="row" style="gap:8px">
      <?php partial('themetoggle'); ?>
      <span class="lang-switch"><a href="<?= e($self . '?lang=en') ?>" class="<?= $L === 'en' ? 'on' : '' ?>">EN</a><a href="<?= e($self . '?lang=bn') ?>" class="<?= $L === 'bn' ? 'on' : '' ?>">বাং</a></span>
      <?php if ($code && $u && in_array($u['role'], ['employee', 'employer'], true)): ?><a class="btn btn-light btn-sm" href="<?= e(url('/explain/' . $code)) ?>"><?= icon('zap', 15) ?> <?= e(t('Explain')) ?></a><?php endif; ?>
      <?php if ($code): ?>
        <button class="btn btn-light btn-sm" data-copy="<?= e($vurl) ?>" data-copied="✓ <?= e(t('Copied')) ?>"><?= icon('link', 15) ?> <?= e(t('Copy verify link')) ?></button>
        <button class="btn btn-light btn-sm" data-share="<?= e($vurl) ?>"><?= icon('send', 15) ?> <?= e(t('Share')) ?></button>
      <?php endif; ?>
      <button class="btn btn-primary btn-sm" data-print><?= icon('download', 15) ?> <?= e(t('Download PDF')) ?></button>
    </div>
  </div>
</div>

<article class="sheet" lang="<?= e($L) ?>">
  <?php if ($isDraft): ?><div class="watermark"><?= e(tp('DRAFT', $L)) ?></div><?php endif; ?>
  <?php if ($isRevoked): ?><div class="stamp-revoked"><?= e(tp('REVOKED', $L)) ?></div><?php endif; ?>

  <header class="doc-head">
    <div class="doc-co">
      <?php if (!empty($co['logo']) && is_file(ROOT . '/uploads/logos/' . basename($co['logo']))): ?><img src="<?= e(url('uploads/logos/' . basename($co['logo']))) ?>" alt=""><?php endif; ?>
      <div>
        <h2><?= e($co['name']) ?></h2>
        <p><?= e($co['address'] ?? '') ?></p>
        <p><?= e(implode(' · ', array_filter([$co['phone'] ?? '', $co['email'] ?? '', !empty($co['tin']) ? ($L === 'bn' ? tp('TIN', $L) : ($labels['tax_id'] ?? 'Tax ID')) . ': ' . $co['tin'] : '']))) ?></p>
      </div>
    </div>
    <div class="doc-title">
      <h1><?= e($docTitle) ?></h1>
      <span class="period"><?= e(period_label($p['period'], $L)) ?></span>
      <?php if ($code): ?><p class="mono" style="font-size:12px"><?= e($code) ?></p><?php endif; ?>
    </div>
  </header>

  <section class="doc-info">
    <div><span><?= e(tp('Employee name', $L)) ?></span><b><?= e($em['name']) ?></b></div>
    <div><span><?= e(tp('Employee ID', $L)) ?></span><b><?= e($em['emp_code']) ?></b></div>
    <div><span><?= e(tp('Designation', $L)) ?></span><b><?= e($em['designation'] ?: '—') ?></b></div>
    <div><span><?= e(tp('Department', $L)) ?></span><b><?= e($em['department'] ?: '—') ?></b></div>
    <div><span><?= e(tp('Joining date', $L)) ?></span><b><?= e(date_fmt($em['join_date'] ?? null, $L)) ?></b></div>
    <div><span><?= e(tp('Pay date', $L)) ?></span><b><?= e(date_fmt($p['pay_date'], $L)) ?></b></div>
    <div><span><?= e(tp('Payment method', $L)) ?></span><b><?= e(payment_method_label($em['payment_method'] ?? '', $L)) ?></b></div>
    <div><span><?= e(tp('Account', $L)) ?></span><b class="mono"><?= e(trim(($em['bank_name'] ?? '') . ' ' . ($em['account_masked'] ?? $em['account_no'] ?? '')) ?: '—') ?></b></div>
    <div><span><?= e(tp('Paid days', $L)) ?></span><b><?= e(num((string)(($lines['paid_days'] ?? 0) + 0), $L)) ?> / <?= e(num((string)($lines['working_days'] ?? 0), $L)) ?></b></div>
    <?php $cfs = $em['custom'] ?? []; foreach ($cfs as $cf): ?>
      <div><span><?= e($L === 'bn' && !empty($cf['label_bn']) ? $cf['label_bn'] : $cf['label']) ?></span><b><?= e($cf['type'] === 'date' ? date_fmt($cf['value'], $L) : $cf['value']) ?></b></div>
    <?php endforeach; for ($pad = (3 - (9 + count($cfs)) % 3) % 3; $pad > 0; $pad--): ?><div></div><?php endfor; ?>
  </section>

  <section class="doc-tables">
    <table class="doc-table">
      <thead><tr><th><?= e(tp('Earnings', $L)) ?></th><th><?= e(tp('Amount', $L)) ?> (<?= e(trim($sym)) ?>)</th></tr></thead>
      <tbody>
      <?php foreach (EARNING_KEYS as $k => $label): $val = (float)($lines['earnings'][$k] ?? 0); if ($val == 0 && !in_array($k, ['basic', 'house_rent'], true)) continue; ?>
        <tr><td><?= e(line_label($labels, $k, $L)) ?></td><td><?= e($m($val)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td><?= e(tp('Gross Earnings', $L)) ?></td><td><?= e($m($p['gross'])) ?></td></tr></tfoot>
    </table>
    <table class="doc-table">
      <thead><tr><th><?= e(tp('Deductions', $L)) ?></th><th><?= e(tp('Amount', $L)) ?> (<?= e(trim($sym)) ?>)</th></tr></thead>
      <tbody>
      <?php $any = false; foreach (DEDUCTION_KEYS as $k => $label): $val = (float)($lines['deductions'][$k] ?? 0); if ($val == 0) continue; $any = true; ?>
        <tr><td><?= e(line_label($labels, $k, $L)) ?></td><td><?= e($m($val)) ?></td></tr>
      <?php endforeach; if (!$any): ?><tr><td class="muted"><?= e(tp('No deductions', $L)) ?></td><td><?= e($m(0)) ?></td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><td><?= e(tp('Total Deductions', $L)) ?></td><td><?= e($m($p['total_deductions'])) ?></td></tr></tfoot>
    </table>
  </section>

  <section class="doc-net">
    <div><span><?= e(tp('Net Pay', $L)) ?></span><b><?= e($sym . $m($p['net'])) ?></b></div>
    <?php if ($L === 'en'): ?><div class="words"><?= e(amount_in_words((float)$p['net'], snapshot_currency($snap))) ?></div><?php endif; ?>
  </section>
  <?php if (!empty($lines['note'])): ?><p class="doc-note"><b><?= e(tp('Note', $L)) ?>:</b> <?= e($lines['note']) ?></p><?php endif; ?>

  <footer class="doc-foot">
    <?php if ($code): ?>
      <div class="doc-qr" data-qr="<?= e($vurl) ?>"></div>
      <div class="doc-verify">
        <b><?= e(tp('Scan to verify this payslip', $L)) ?></b>
        <p><?= e(tp('Or visit', $L)) ?> <span class="mono"><?= e(preg_replace('#^https?://#', '', abs_url('/verify'))) ?></span></p>
        <p><?= e(tp('Verification code', $L)) ?>: <b class="mono"><?= e($code) ?></b></p>
        <p><?= e(tp('Digital fingerprint', $L)) ?>: <span class="mono"><?= e(fingerprint_display($p['hash'])) ?></span></p>
        <p><?= e(tp('Issued', $L)) ?>: <?= e(date_fmt($p['issued_at'], $L, true)) ?></p>
      </div>
    <?php else: ?>
      <div class="doc-qr" style="border:2px dashed #e5b4b4;display:grid;place-items:center;color:#b91c1c;font-size:11px;text-align:center;padding:8px"><?= e(tp('QR appears after finalising', $L)) ?></div>
      <div class="doc-verify"><b><?= e(tp('Draft — not valid', $L)) ?></b><p><?= e(tp('This preview cannot be verified until payroll is finalised.', $L)) ?></p></div>
    <?php endif; ?>
    <?php partial('docsign', ['co' => $co, 'L' => $L]); ?>
  </footer>
  <p class="doc-legal"><?= e(tp('This is a digitally issued payslip and does not require a physical signature. Its authenticity can be confirmed by scanning the QR code.', $L)) ?><?= !empty($p['branding']) ? ' · Verified by PaySlip.tech' : '' ?></p>
</article>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
