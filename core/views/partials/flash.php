<?php $fl = take_flash(); if ($fl): ?>
<div class="flash-stack" role="status" aria-live="polite">
<?php foreach ($fl as $f): $ic = ['success' => 'check-circle', 'error' => 'x-circle', 'info' => 'alert', 'warn' => 'alert'][$f['type']] ?? 'alert'; ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= icon($ic) ?><div><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
