<?php
$map = ['valid' => ['badge-ok', t('Genuine')], 'revoked' => ['badge-warn', t('Revoked')], 'tampered' => ['badge-bad', t('Tampered')], 'not_found' => ['badge-bad', t('Not found')], 'issued' => ['badge-ok', t('Issued')], 'draft' => ['badge-info', t('Draft')], 'finalized' => ['badge-ok', t('Finalised')], 'active' => ['badge-ok', t('Active')], 'inactive' => ['', t('Inactive')], 'suspended' => ['badge-bad', t('Suspended')], 'invited' => ['badge-info', t('Invited')]];
[$cls, $lbl] = $map[$r] ?? ['', (string)$r];
?><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span>
