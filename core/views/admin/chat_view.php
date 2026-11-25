<div class="page-head"><div><a class="small" href="<?= e(url('/admin/chat')) ?>">← <?= e(t('Live chat')) ?></a><h1 style="margin-top:6px"><?= e(sprintf(t('Chat #%d'), $s['id'])) ?></h1>
  <p><?= e($s['visitor_name'] ?: t('Visitor')) ?><?= $s['visitor_email'] ? ' · ' . e($s['visitor_email']) : '' ?> · <?= e($s['country'] ?: '—') ?> · <?= e(date_fmt($s['created_at'], null, true)) ?><?= $s['page'] ? ' · ' . e(parse_url($s['page'], PHP_URL_PATH) ?: '') : '' ?></p></div>
  <?php if ($s['status'] !== 'closed'): ?><form method="post" action="<?= e(url('/admin/chat/' . $s['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="close"><button class="btn btn-light"><?= icon('check') ?> <?= e(t('Close chat')) ?></button></form><?php endif; ?>
</div>
<div class="card" style="max-width:860px">
  <div class="card-body chat-log">
    <?php foreach ($msgs as $m): ?>
      <div class="msg <?= $m['sender'] === 'visitor' ? 'them' : ($m['sender'] === 'agent' ? 'me' : 'bot') ?>"><div class="bubble"><span class="who"><?= e(['visitor' => t('Visitor'), 'bot' => t('Bot'), 'agent' => t('You')][$m['sender']] ?? '') ?> · <?= e(date('H:i', strtotime($m['created_at']))) ?></span><?= nl2br(e($m['body'])) ?><?php if ($m['unanswered']): ?><div><a class="small" href="<?= e(url('/admin/kb?from=' . $m['id'])) ?>"><?= e(t('Teach the bot an answer for this')) ?> →</a></div><?php endif; ?></div></div>
    <?php endforeach; ?>
    <span id="bottom"></span>
  </div>
  <form class="card-foot" method="post" action="<?= e(url('/admin/chat/' . $s['id'])) ?>">
    <?= csrf_field() ?>
    <div class="row" style="flex-wrap:nowrap"><textarea class="textarea" name="message" rows="2" required placeholder="<?= e(t('Write a reply… the visitor sees it in the chat window.')) ?>" data-enter-submit></textarea><button class="btn btn-primary" style="height:52px"><?= icon('send') ?></button></div>
    <?php if ($s['visitor_email']): ?><label class="check small" style="margin-top:8px"><input type="checkbox" name="email_copy" value="1" checked> <?= e(sprintf(t('Also email this reply to %s'), $s['visitor_email'])) ?></label><?php endif; ?>
  </form>
</div>
