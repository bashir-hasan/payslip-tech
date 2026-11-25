<?php
$snap = $d['snapshot'];
$co = $snap['company'];
$em = $snap['employee'];
$code = $d['code'];
$vurl = verify_url($code);
$isRevoked = $d['status'] === 'revoked';
$m = fn($v) => money_fmt($v, true, $L);
$sym = currency_symbol();
$labels = snapshot_labels($snap);
$back = $u['role'] === 'employee' ? '/me/documents' : ($u['role'] === 'admin' ? '/admin' : '/app/documents');
$self = url('/document/' . $code);
$title = doc_type_label($d['type'], $L);
$current = ($em['status'] ?? 'active') === 'active';
?><!doctype html>
<html lang="<?= e($L) ?>" data-theme="<?= e(theme()) ?>">
<head><?php partial('head', ['title' => $title . ' ' . $em['name'], 'noindex' => true, 'context' => 'doc']); ?></head>
<body class="doc-body">
<div class="doc-toolbar no-print">
  <div class="inner">
    <a class="back" href="<?= e(url($back)) ?>">← <?= e(t('Back')) ?></a>
    <div class="row" style="gap:8px">
      <?php partial('themetoggle'); ?>
      <span class="lang-switch"><a href="<?= e($self . '?lang=en') ?>" class="<?= $L === 'en' ? 'on' : '' ?>">EN</a><a href="<?= e($self . '?lang=bn') ?>" class="<?= $L === 'bn' ? 'on' : '' ?>">বাং</a></span>
      <button class="btn btn-light btn-sm" data-copy="<?= e($vurl) ?>" data-copied="✓ <?= e(t('Copied')) ?>"><?= icon('link', 15) ?> <?= e(t('Copy verify link')) ?></button>
      <button class="btn btn-primary btn-sm" data-print><?= icon('download', 15) ?> <?= e(t('Download PDF')) ?></button>
    </div>
  </div>
</div>
<article class="sheet letter" lang="<?= e($L) ?>">
  <?php if ($isRevoked): ?><div class="stamp-revoked"><?= e(tp('REVOKED', $L)) ?></div><?php endif; ?>
  <header class="doc-head">
    <div class="doc-co">
      <?php if (!empty($co['logo']) && is_file(ROOT . '/uploads/logos/' . basename($co['logo']))): ?><img src="<?= e(url('uploads/logos/' . basename($co['logo']))) ?>" alt=""><?php endif; ?>
      <div><h2><?= e($co['name']) ?></h2><p><?= e($co['address'] ?? '') ?></p><p><?= e(implode(' · ', array_filter([$co['phone'] ?? '', $co['email'] ?? '', $co['website'] ?? '']))) ?></p></div>
    </div>
    <div class="doc-title"><h1><?= e($title) ?></h1><p class="mono" style="font-size:12px"><?= e($code) ?></p><p style="font-size:12px"><?= e(tp('Date', $L)) ?>: <?= e(date_fmt($d['issued_at'], $L)) ?></p></div>
  </header>

  <section class="letter-body" style="margin:22px 0;line-height:1.7;font-size:14.5px">
    <p><b><?= e($snap['addressed_to'] ?: tp('To whom it may concern', $L)) ?></b></p>
    <?php if ($snap['purpose']): ?><p><b><?= e(tp('Subject', $L)) ?>:</b> <?= e($title) ?> — <?= e($snap['purpose']) ?></p><?php endif; ?>
    <?php if ($d['type'] === 'income_statement'): ?>
      <p><?= e(sprintf(tp('This statement lists the salary paid by %1$s to %2$s (Employee ID %3$s, %4$s) for the year %5$s, taken from payslips issued and verified through PaySlip.tech.', $L), $co['name'], $em['name'], $em['emp_code'], $em['designation'] ?: '—', num($snap['year'], $L))) ?></p>
    <?php else: ?>
      <p><?= e(sprintf($current ? tp('This is to certify that %1$s (Employee ID %2$s) has been employed by %3$s since %4$s and currently holds the position of %5$s%6$s.', $L) : tp('This is to certify that %1$s (Employee ID %2$s) was employed by %3$s from %4$s as %5$s%6$s.', $L), $em['name'], $em['emp_code'], $co['name'], $em['join_date'] ? date_fmt($em['join_date'], $L) : '—', $em['designation'] ?: '—', $em['department'] ? sprintf(tp(' in the %s department', $L), $em['department']) : '')) ?></p>
    <?php endif; ?>
  </section>

  <?php if ($d['type'] === 'salary_certificate' && !empty($snap['salary'])): $sal = $snap['salary']; ?>
  <section class="doc-tables">
    <table class="doc-table">
      <thead><tr><th><?= e(tp('Monthly earnings', $L)) ?></th><th><?= e(tp('Amount', $L)) ?> (<?= e(trim($sym)) ?>)</th></tr></thead>
      <tbody><?php foreach ($sal['earnings'] as $k => $v): ?><tr><td><?= e(line_label($labels, $k, $L)) ?></td><td><?= e($m($v)) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><td><?= e(tp('Gross Earnings', $L)) ?></td><td><?= e($m($sal['gross'])) ?></td></tr></tfoot>
    </table>
    <table class="doc-table">
      <thead><tr><th><?= e(tp('Monthly deductions', $L)) ?></th><th><?= e(tp('Amount', $L)) ?> (<?= e(trim($sym)) ?>)</th></tr></thead>
      <tbody><?php if (!$sal['deductions']): ?><tr><td class="muted"><?= e(tp('No deductions', $L)) ?></td><td><?= e($m(0)) ?></td></tr><?php endif; foreach ($sal['deductions'] as $k => $v): ?><tr><td><?= e(line_label($labels, $k, $L)) ?></td><td><?= e($m($v)) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><td><?= e(tp('Total Deductions', $L)) ?></td><td><?= e($m($sal['total_deductions'])) ?></td></tr></tfoot>
    </table>
  </section>
  <section class="doc-net"><div><span><?= e(tp('Monthly Net Pay', $L)) ?></span><b><?= e($sym . $m($sal['net'])) ?></b></div><?php if ($L === 'en'): ?><div class="words"><?= e(amount_in_words((float)$sal['net'], snapshot_currency($snap))) ?></div><?php endif; ?></section>
  <?php endif; ?>

  <?php if ($d['type'] === 'income_statement'): $tot = $snap['totals']; ?>
  <table class="doc-table" style="width:100%;margin-top:6px">
    <thead><tr><th><?= e(tp('Month', $L)) ?></th><th><?= e(tp('Payslip code', $L)) ?></th><th><?= e(tp('Gross', $L)) ?></th><th><?= e(tp('Deductions', $L)) ?></th><th><?= e(tp('Net pay', $L)) ?></th></tr></thead>
    <tbody><?php foreach ($snap['months'] as $r): ?><tr><td><?= e(period_label($r['period'], $L)) ?></td><td class="mono" style="font-size:12px"><?= e($r['code']) ?></td><td><?= e($m($r['gross'])) ?></td><td><?= e($m($r['deductions'])) ?></td><td><?= e($m($r['net'])) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><td colspan="2"><?= e(sprintf(tp('Total (%s months)', $L), num(count($snap['months']), $L))) ?></td><td><?= e($m($tot['gross'])) ?></td><td><?= e($m($tot['deductions'])) ?></td><td><?= e($m($tot['net'])) ?></td></tr></tfoot>
  </table>
  <?php if ($tot['tax'] > 0): ?><p class="small" style="margin-top:8px"><?= e(sprintf(tp('Income tax deducted at source during the year: %s', $L), $sym . $m($tot['tax']))) ?></p><?php endif; ?>
  <?php endif; ?>

  <p style="margin-top:18px;font-size:14px;line-height:1.7"><?= e(tp('This document is issued at the request of the employee for the stated purpose and does not create any liability on the part of the company.', $L)) ?></p>

  <footer class="doc-foot">
    <div class="doc-qr" data-qr="<?= e($vurl) ?>"></div>
    <div class="doc-verify">
      <b><?= e(tp('Scan to verify this document', $L)) ?></b>
      <p><?= e(tp('Or visit', $L)) ?> <span class="mono"><?= e(preg_replace('#^https?://#', '', abs_url('/verify'))) ?></span></p>
      <p><?= e(tp('Verification code', $L)) ?>: <b class="mono"><?= e($code) ?></b></p>
      <p><?= e(tp('Digital fingerprint', $L)) ?>: <span class="mono"><?= e(fingerprint_display($d['hash'])) ?></span></p>
    </div>
    <div class="doc-sign"><div class="line"><?= e($co['signatory_name'] ?: tp('Authorised Signatory', $L)) ?></div><span><?= e($co['signatory_title'] ?: tp('Authorised Signatory', $L)) ?></span></div>
  </footer>
  <p class="doc-legal"><?= e(tp('This document was issued digitally and does not need a physical signature. Scan the QR code to confirm it is genuine and has not been changed.', $L)) ?><?= $branding ? ' · Verified by PaySlip.tech' : '' ?></p>
</article>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
