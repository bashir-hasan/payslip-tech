<?php
/** Signature block with optional signature and seal images. Expects $co (company snapshot) and $L. */
$img = function (?string $f): ?string {
    if (!$f) return null;
    $f = basename($f);
    return is_file(ROOT . '/uploads/logos/' . $f) ? url('uploads/logos/' . $f) : null;
};
$sig = $img($co['signature_img'] ?? null);
$seal = $img($co['stamp_img'] ?? null);
?>
<div class="doc-sign<?= $sig || $seal ? ' has-img' : '' ?>">
  <?php if ($seal): ?><img class="doc-seal" src="<?= e($seal) ?>" alt=""><?php endif; ?>
  <?php if ($sig): ?><img class="doc-sig" src="<?= e($sig) ?>" alt=""><?php endif; ?>
  <div class="line"><?= e(($co['signatory_name'] ?? '') ?: tp('Authorised Signatory', $L)) ?></div>
  <span><?= e(($co['signatory_title'] ?? '') ?: tp('Authorised Signatory', $L)) ?></span>
</div>
