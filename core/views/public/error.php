<div class="page-narrow center" style="padding:90px 20px">
  <div class="empty">
    <div class="ic-wrap"><?= icon(($code ?? 404) === 404 ? 'search' : 'alert', 26) ?></div>
    <p class="mono muted" style="margin:0 0 6px"><?= e((string)($code ?? 404)) ?></p>
    <h1 style="font-size:28px"><?= e($title ?? '') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <a class="btn btn-dark" href="<?= e(url('/')) ?>"><?= e(t('Go to home page')) ?></a>
  </div>
</div>
