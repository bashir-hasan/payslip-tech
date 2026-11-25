<?php if (setting('chat_enabled', '1') === '1'): $wa = whatsapp_link(sprintf('Hi! I need help with %s.', site_name())); ?>
<div class="cw" data-chat data-poll="<?= e(url('/chat/poll')) ?>" data-send="<?= e(url('/chat/send')) ?>">
  <button type="button" class="cw-fab" data-chat-toggle aria-label="<?= e(t('Chat with us')) ?>"><?= icon('chat', 24) ?><span class="cw-dot" hidden></span></button>
  <section class="cw-panel" hidden aria-label="<?= e(t('Live chat')) ?>">
    <header class="cw-head">
      <div class="row" style="gap:10px;flex-wrap:nowrap"><span class="brand-mark" style="width:34px;height:34px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v13H7z"/><path d="m10 14 2 2 4-4"/></svg></span><div><b><?= e(site_name()) ?></b><span class="cw-status"><?= e(t('Assistant · usually replies instantly')) ?></span></div></div>
      <button type="button" class="btn btn-ghost btn-icon btn-sm" data-chat-toggle aria-label="<?= e(t('Close')) ?>"><?= icon('x', 18) ?></button>
    </header>
    <div class="cw-body" data-chat-body></div>
    <div class="cw-chips" data-chat-chips></div>
    <form class="cw-form" data-chat-form>
      <input class="input" name="message" maxlength="1000" autocomplete="off" placeholder="<?= e(t('Type your question…')) ?>" aria-label="<?= e(t('Message')) ?>">
      <button class="btn btn-primary btn-icon" aria-label="<?= e(t('Send')) ?>"><?= icon('send', 16) ?></button>
    </form>
    <div class="cw-foot">
      <?php if ($wa): ?><a class="cw-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" data-chat-wa><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5 1.9.8 2.6.9 3.6.7.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg> <?= e(t('Chat on WhatsApp')) ?></a><?php endif; ?>
      <span class="small muted"><?= e(t('Ask "talk to a human" anytime')) ?></span>
    </div>
  </section>
</div>
<?php endif; ?>
