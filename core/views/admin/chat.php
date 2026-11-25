<?php $tabs = ['human' => [t('Waiting for you'), (int)$counts['h']], 'bot' => [t('Bot only'), (int)$counts['b']], 'closed' => [t('Closed'), (int)$counts['c']], 'all' => [t('All'), (int)$counts['a']]]; ?>
<div class="page-head"><div><h1><?= e(t('Live chat')) ?></h1><p><?= e(t('The bot answers first. Chats where a visitor asked for a person appear under "Waiting for you".')) ?></p></div>
  <a class="btn btn-light" href="<?= e(url('/admin/kb')) ?>"><?= icon('edit') ?> <?= e(t('Train the chatbot')) ?><?php if ($unanswered): ?> <span class="badge badge-warn plain"><?= e(num($unanswered)) ?></span><?php endif; ?></a>
</div>
<div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px;align-items:start" data-chat-adm>
  <div class="card">
    <div class="toolbar"><div class="tabs"><?php foreach ($tabs as $k => [$l, $n]): ?><a href="<?= e(url('/admin/chat?f=' . $k)) ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= e($l) ?> (<?= e(num($n)) ?>)</a><?php endforeach; ?></div></div>
    <div class="table-wrap"><table class="table stack-m"><tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="first"><a href="<?= e(url('/admin/chat/' . $r['id'])) ?>" style="color:inherit"><b>#<?= e((string)$r['id']) ?> <?= e($r['visitor_name'] ?: ($r['visitor_email'] ?: t('Visitor'))) ?></b><?php if ($r['unread_admin']): ?> <span class="badge badge-bad plain"><?= e(num($r['unread_admin'])) ?></span><?php endif; ?><div class="small muted"><?= e(mb_substr((string)$r['last_msg'], 0, 90)) ?></div></a></td>
          <td data-l="<?= e(t('Status')) ?>"><?php partial('result_badge', ['r' => $r['status'] === 'human' ? 'invited' : ($r['status'] === 'closed' ? 'inactive' : 'active')]); ?></td>
          <td class="small muted" data-l="<?= e(t('Country')) ?>"><?= e($r['country'] ?: '') ?> <?= e(strtoupper($r['lang'])) ?></td>
          <td class="num small muted" data-l="<?= e(t('When')) ?>"><?= e(time_ago($r['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td class="muted center"><?= e(t('No chats here yet.')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <form class="card" method="post" action="<?= e(url('/admin/chat/settings')) ?>">
    <?= csrf_field() ?>
    <div class="card-head"><h3><?= e(t('Chat settings')) ?></h3></div>
    <div class="card-body">
      <label class="check" style="margin-bottom:10px"><input type="checkbox" name="chat_enabled" value="1" <?= setting('chat_enabled', '1') === '1' ? 'checked' : '' ?>> <b><?= e(t('Show the chat on the website')) ?></b></label>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="chat_in_app" value="1" <?= setting('chat_in_app', '0') === '1' ? 'checked' : '' ?>> <?= e(t('Also show it inside employer and employee dashboards')) ?></label>
      <div class="field"><label class="label"><?= e(t('WhatsApp number (with country code)')) ?></label><input class="input mono" name="whatsapp_number" value="<?= e(setting('whatsapp_number', '')) ?>" placeholder="8801XXXXXXXXX"><span class="hint"><?= e(t('Visitors tap "Chat on WhatsApp" to message you directly. Use your WhatsApp Business number.')) ?></span></div>
      <div class="field"><label class="label"><?= e(t('Email me new chat requests at')) ?></label><input class="input" type="email" name="chat_notify_email" value="<?= e(setting('chat_notify_email', '')) ?>" placeholder="<?= e(setting('support_email', '')) ?>"></div>
      <div class="field"><label class="label"><?= e(t('Greeting message')) ?></label><textarea class="textarea" name="chat_greeting" rows="3" maxlength="300"><?= e(setting('chat_greeting', '')) ?></textarea></div>
    </div>
    <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= e(t('Save')) ?></button></div>
  </form>
</div>
<style>@media (max-width:960px){[data-chat-adm]{grid-template-columns:1fr!important}}</style>
