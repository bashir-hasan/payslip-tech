<?php $e = $edit ?: ['id' => '', 'question' => $prefill, 'answer' => '', 'keywords' => '', 'lang' => preg_match('/\p{Bengali}/u', $prefill) ? 'bn' : 'en', 'link' => '']; ?>
<div class="page-head"><div><a class="small" href="<?= e(url('/admin/chat')) ?>">← <?= e(t('Live chat')) ?></a><h1 style="margin-top:6px"><?= e(t('Train the chatbot')) ?></h1><p><?= e(t('The bot answers from these questions and answers. Add keywords people might use. Questions it could not answer appear on the right: answer them once and the bot learns.')) ?></p></div></div>
<div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:18px;align-items:start" data-kb>
  <div class="stack">
    <form class="card" method="post" action="<?= e(url('/admin/kb')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)$e['id']) ?>"><input type="hidden" name="from" value="<?= e((string)$fromId) ?>">
      <div class="card-head"><h3><?= e($e['id'] ? t('Edit answer') : t('Add an answer')) ?></h3></div>
      <div class="card-body">
        <div class="field"><label class="label"><?= e(t('Question')) ?></label><input class="input" name="question" value="<?= e($e['question']) ?>" maxlength="255" required></div>
        <div class="field"><label class="label"><?= e(t('Answer')) ?></label><textarea class="textarea" name="answer" rows="4" maxlength="3000" required><?= e($e['answer']) ?></textarea></div>
        <div class="field"><label class="label"><?= e(t('Keywords and other ways people ask')) ?></label><input class="input" name="keywords" value="<?= e((string)$e['keywords']) ?>" maxlength="500" placeholder="<?= e(t('e.g. refund money back cancel')) ?>"></div>
        <div class="form-grid">
          <div class="field"><label class="label"><?= e(t('Language')) ?></label><select class="select" name="lang"><option value="en" <?= $e['lang'] === 'en' ? 'selected' : '' ?>>English</option><option value="bn" <?= $e['lang'] === 'bn' ? 'selected' : '' ?>>বাংলা</option></select></div>
          <div class="field"><label class="label"><?= e(t('Link (optional)')) ?></label><input class="input mono" name="link" value="<?= e((string)$e['link']) ?>" placeholder="/verify"></div>
        </div>
      </div>
      <div class="card-foot row" style="justify-content:flex-end"><?php if ($e['id']): ?><a class="btn btn-ghost" href="<?= e(url('/admin/kb')) ?>"><?= e(t('Cancel')) ?></a><?php endif; ?><button class="btn btn-primary"><?= icon('check') ?> <?= e(t('Save answer')) ?></button></div>
    </form>
    <div class="card">
      <div class="toolbar"><form class="search" method="get"><?= icon('search', 16) ?><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Search answers…')) ?>"></form><span class="small muted"><?= e(sprintf(t('%d answers'), count($rows))) ?></span></div>
      <div class="table-wrap"><table class="table stack-m"><tbody>
        <?php foreach ($rows as $r): ?>
          <tr style="<?= $r['active'] ? '' : 'opacity:.5' ?>">
            <td class="first"><b><?= e($r['question']) ?></b> <span class="badge plain"><?= e(strtoupper($r['lang'])) ?></span><div class="small muted"><?= e(mb_substr($r['answer'], 0, 140)) ?></div></td>
            <td class="num small muted" data-l="<?= e(t('Used')) ?>"><?= e(num($r['hits'])) ?>×</td>
            <td class="num"><div class="row" style="justify-content:flex-end;gap:4px;flex-wrap:nowrap">
              <a class="btn btn-ghost btn-sm btn-icon" href="<?= e(url('/admin/kb?edit=' . $r['id'])) ?>"><?= icon('edit', 15) ?></a>
              <form method="post" action="<?= e(url('/admin/kb')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>"><button class="btn btn-ghost btn-sm"><?= e($r['active'] ? t('Off') : t('On')) ?></button></form>
              <form method="post" action="<?= e(url('/admin/kb')) ?>" data-confirm="<?= e(t('Delete this answer?')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>"><button class="btn btn-danger btn-sm btn-icon"><?= icon('trash', 15) ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </div>
  </div>
  <div class="card"><div class="card-head"><h3><?= e(t('Questions the bot could not answer')) ?></h3><span class="badge badge-warn plain"><?= e(num(count($unanswered))) ?></span></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($unanswered as $u): ?>
        <tr><td><?= e($u['body']) ?><div class="small muted"><?= e(time_ago($u['created_at'])) ?> · <?= e(strtoupper($u['lang'])) ?></div></td>
          <td class="num"><div class="row" style="justify-content:flex-end;gap:4px;flex-wrap:nowrap"><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/kb?from=' . $u['id'])) ?>"><?= e(t('Answer')) ?></a>
          <form method="post" action="<?= e(url('/admin/kb')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="dismiss"><input type="hidden" name="msg" value="<?= e((string)$u['id']) ?>"><button class="btn btn-ghost btn-sm btn-icon" title="<?= e(t('Dismiss')) ?>"><?= icon('x', 15) ?></button></form></div></td></tr>
      <?php endforeach; ?>
      <?php if (!$unanswered): ?><tr><td class="muted"><?= e(t('All caught up. New unanswered questions will appear here.')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<style>@media (max-width:960px){[data-kb]{grid-template-columns:1fr!important}}</style>
