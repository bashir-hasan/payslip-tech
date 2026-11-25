<?php
declare(strict_types=1);

function chat_enabled(): bool
{
    return setting('chat_enabled', '1') === '1';
}

function chat_payload(array $s, int $after = 0): array
{
    $msgs = db_all('SELECT m.id, m.sender, m.body, m.kb_id, k.link FROM chat_messages m LEFT JOIN kb_entries k ON k.id = m.kb_id WHERE m.session_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100', [$s['id'], $after]);
    return [
        'ok' => true,
        'status' => $s['status'],
        'messages' => array_map(fn($m) => ['id' => (int)$m['id'], 'from' => $m['sender'], 'text' => $m['body'], 'link' => $m['link'] ? url($m['link']) : null], $msgs),
        'whatsapp' => whatsapp_link(sprintf('Hi! I need help with %s. (chat #%d)', site_name(), $s['id'])),
    ];
}

/** GET /chat/poll?after=ID — returns new messages for the visitor's own chat. */
function chat_poll(): void
{
    if (!chat_enabled()) json_out(['ok' => false], 404);
    $s = chat_session(false);
    if (!$s) {
        json_out(['ok' => true, 'status' => 'new', 'messages' => [], 'greeting' => (string)setting('chat_greeting', ''), 'whatsapp' => whatsapp_link(sprintf('Hi! I need help with %s.', site_name())), 'suggestions' => chat_starters()]);
    }
    json_out(chat_payload($s, max(0, (int)input('after'))));
}

function chat_starters(): array
{
    return lang() === 'bn'
        ? ['বেতন স্লিপ কীভাবে যাচাই করব?', 'খরচ কত?', 'কীভাবে শুরু করব?', 'মানুষের সাথে কথা বলতে চাই']
        : ['How do I verify a payslip?', 'How much does it cost?', 'How do I get started?', 'Talk to a human'];
}

/** POST /chat/send {message, name?, email?} with X-CSRF-Token header. */
function chat_send(): void
{
    if (!chat_enabled()) json_out(['ok' => false], 404);
    if (!rate_limit('chat:' . client_ip(), 40, 600)) json_out(['ok' => false, 'error' => t('You are sending messages too fast. Please wait a moment.')], 429);
    $text = trim(mb_substr(strip_tags((string)input('message')), 0, 1000));
    if ($text === '') json_out(['ok' => false, 'error' => 'empty'], 422);
    $s = chat_session(true);
    $after = max(0, (int)input('after'));
    $email = trim((string)input('email'));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) db_update('chat_sessions', ['visitor_email' => mb_substr($email, 0, 190)], 'id = ?', [$s['id']]);
    $name = trim(strip_tags((string)input('name')));
    if ($name !== '') db_update('chat_sessions', ['visitor_name' => mb_substr($name, 0, 120)], 'id = ?', [$s['id']]);
    chat_add((int)$s['id'], 'visitor', $text);

    if ($s['status'] === 'human') {
        // A person is handling this chat; just notify
        chat_notify_admin($s, $text, false);
    } elseif (kb_wants_human($text)) {
        db_update('chat_sessions', ['status' => 'human'], 'id = ?', [$s['id']]);
        $wa = whatsapp_link(sprintf('Hi! I need help with %s. (chat #%d)', site_name(), $s['id']));
        $msg = lang() === 'bn'
            ? 'আমি আমাদের টিমকে জানিয়েছি। এখানেই উত্তর পাবেন' . ($wa ? ', অথবা দ্রুত সাহায্যের জন্য নিচের WhatsApp বাটনে চাপ দিন।' : '।') . ' আপনার ইমেইল দিলে উত্তর মিস করবেন না।'
            : "I've let our team know. A person will reply here" . ($wa ? ', or tap the WhatsApp button below for a faster answer.' : '.') . ' Leave your email so you do not miss the reply.';
        chat_add((int)$s['id'], 'bot', $msg);
        chat_notify_admin($s, $text, true);
    } else {
        $m = kb_match($text, lang());
        if ($m['entry']) {
            db_exec('UPDATE kb_entries SET hits = hits + 1 WHERE id = ?', [$m['entry']['id']]);
            $reply = $m['entry']['answer'];
            if ($m['suggestions']) $reply .= "\n\n" . (lang() === 'bn' ? 'আরও জানতে পারেন: ' : 'You may also ask: ') . implode(' · ', $m['suggestions']);
            chat_add((int)$s['id'], 'bot', $reply, (int)$m['entry']['id']);
        } else {
            $reply = lang() === 'bn'
                ? 'দুঃখিত, এর উত্তর আমার জানা নেই।' . ($m['suggestions'] ? ' হয়তো জানতে চাইছেন: ' . implode(' · ', $m['suggestions']) . '।' : '') . ' "মানুষের সাথে কথা" লিখলে আমাদের টিম উত্তর দেবে।'
                : "Sorry, I don't know that one yet." . ($m['suggestions'] ? ' Did you mean: ' . implode(' · ', $m['suggestions']) . '?' : '') . ' Type "talk to a human" and our team will reply.';
            db_exec('UPDATE chat_messages SET unanswered = 1 WHERE session_id = ? AND sender = ? ORDER BY id DESC LIMIT 1', [$s['id'], 'visitor']);
            chat_add((int)$s['id'], 'bot', $reply);
        }
    }
    $s = db_one('SELECT * FROM chat_sessions WHERE id = ?', [$s['id']]);
    json_out(chat_payload($s, $after));
}

function chat_notify_admin(array $s, string $text, bool $first): void
{
    if (!rate_limit('chat-mail:' . $s['id'], $first ? 3 : 2, 1800)) return;
    $to = (string)(setting('chat_notify_email') ?: setting('support_email'));
    if (!$to) return;
    send_mail($to, ($first ? 'New live chat request' : 'New chat message') . ' #' . $s['id'] . ' — ' . site_name(),
        'A visitor' . ($s['visitor_email'] ? ' (' . $s['visitor_email'] . ')' : '') . " wrote:\n\n\"" . mb_substr($text, 0, 500) . "\"\n\nReply from the admin chat inbox.",
        abs_url('/admin/chat/' . $s['id']), 'Open chat');
}

/* ---------------- Admin: inbox ---------------- */

function admin_chat(): void
{
    require_role('admin');
    $f = (string)input('f', 'human');
    $where = $f === 'all' ? '1=1' : ($f === 'bot' ? "status = 'bot'" : ($f === 'closed' ? "status = 'closed'" : "status = 'human'"));
    $rows = db_all("SELECT s.*, (SELECT body FROM chat_messages m WHERE m.session_id = s.id ORDER BY m.id DESC LIMIT 1) AS last_msg, (SELECT COUNT(*) FROM chat_messages m WHERE m.session_id = s.id) AS msgs FROM chat_sessions s WHERE $where ORDER BY s.updated_at DESC LIMIT 200");
    $counts = db_one("SELECT SUM(status='human') h, SUM(status='bot') b, SUM(status='closed') c, COUNT(*) a FROM chat_sessions");
    $unanswered = (int)db_val('SELECT COUNT(*) FROM chat_messages WHERE unanswered = 1');
    render('admin/chat', ['title' => t('Live chat'), 'nav' => 'admin-chat', 'rows' => $rows, 'f' => $f, 'counts' => $counts, 'unanswered' => $unanswered], 'app');
}

function admin_chat_view(string $id): void
{
    require_role('admin');
    $s = db_one('SELECT * FROM chat_sessions WHERE id = ?', [(int)$id]);
    if (!$s) abort(404);
    db_update('chat_sessions', ['unread_admin' => 0], 'id = ?', [$s['id']]);
    $msgs = db_all('SELECT * FROM chat_messages WHERE session_id = ? ORDER BY id', [$s['id']]);
    render('admin/chat_view', ['title' => sprintf(t('Chat #%d'), $s['id']), 'nav' => 'admin-chat', 's' => $s, 'msgs' => $msgs], 'app');
}

function admin_chat_reply(string $id): void
{
    $u = require_role('admin');
    $s = db_one('SELECT * FROM chat_sessions WHERE id = ?', [(int)$id]);
    if (!$s) abort(404);
    $action = (string)input('action');
    if ($action === 'close') {
        db_update('chat_sessions', ['status' => 'closed'], 'id = ?', [$s['id']]);
        chat_add((int)$s['id'], 'bot', lang() === 'bn' ? 'চ্যাটটি বন্ধ করা হয়েছে। ধন্যবাদ!' : 'This chat has been closed. Thank you!');
        redirect('/admin/chat');
    }
    $text = trim(mb_substr(strip_tags((string)input('message')), 0, 2000));
    if ($text !== '') {
        chat_add((int)$s['id'], 'agent', $text);
        db_update('chat_sessions', ['status' => 'human'], 'id = ?', [$s['id']]);
        if (input('email_copy') && $s['visitor_email']) {
            send_mail($s['visitor_email'], 'Reply from ' . site_name(), $text, abs_url('/'), 'Open ' . site_name());
        }
    }
    redirect('/admin/chat/' . $s['id'] . '#bottom');
}

/* ---------------- Admin: knowledge base (train the bot) ---------------- */

function admin_kb(): void
{
    require_role('admin');
    $q = (string)input('q');
    $rows = $q !== '' ? db_all('SELECT * FROM kb_entries WHERE question LIKE ? OR answer LIKE ? OR keywords LIKE ? ORDER BY id DESC', ["%$q%", "%$q%", "%$q%"]) : db_all('SELECT * FROM kb_entries ORDER BY lang, id DESC');
    $unanswered = db_all('SELECT m.id, m.body, m.created_at, s.lang FROM chat_messages m JOIN chat_sessions s ON s.id = m.session_id WHERE m.unanswered = 1 ORDER BY m.id DESC LIMIT 50');
    $edit = input('edit') ? db_one('SELECT * FROM kb_entries WHERE id = ?', [(int)input('edit')]) : null;
    $prefill = input('from') ? db_one('SELECT body FROM chat_messages WHERE id = ?', [(int)input('from')]) : null;
    render('admin/kb', ['title' => t('Train the chatbot'), 'nav' => 'admin-kb', 'rows' => $rows, 'q' => $q, 'unanswered' => $unanswered, 'edit' => $edit, 'prefill' => $prefill['body'] ?? '', 'fromId' => (int)input('from')], 'app');
}

function admin_kb_save(): void
{
    require_role('admin');
    $action = (string)input('action');
    $id = (int)input('id');
    if ($action === 'delete' && $id) {
        db_exec('DELETE FROM kb_entries WHERE id = ?', [$id]);
        flash('success', t('Answer deleted.'));
        redirect('/admin/kb');
    }
    if ($action === 'toggle' && $id) {
        db_exec('UPDATE kb_entries SET active = 1 - active WHERE id = ?', [$id]);
        redirect('/admin/kb');
    }
    if ($action === 'dismiss') {
        db_exec('UPDATE chat_messages SET unanswered = 0 WHERE id = ?', [(int)input('msg')]);
        redirect('/admin/kb');
    }
    $data = [
        'question' => mb_substr(trim(strip_tags((string)input('question'))), 0, 255),
        'answer' => mb_substr(trim(strip_tags((string)input('answer'))), 0, 3000),
        'keywords' => mb_substr(trim(strip_tags((string)input('keywords'))), 0, 500) ?: null,
        'lang' => input('lang') === 'bn' ? 'bn' : 'en',
        'link' => preg_match('#^/[\w\-/\#?=&.]*$#', (string)input('link')) ? (string)input('link') : null,
    ];
    if ($data['question'] === '' || $data['answer'] === '') {
        flash('error', t('Question and answer are required.'));
        redirect('/admin/kb');
    }
    if ($id) {
        db_update('kb_entries', $data, 'id = ?', [$id]);
    } else {
        db_insert('kb_entries', $data + ['created_at' => now()]);
    }
    if ($from = (int)input('from')) db_exec('UPDATE chat_messages SET unanswered = 0 WHERE id = ?', [$from]);
    audit('Chatbot knowledge base updated');
    flash('success', t('Saved. The chatbot will use this answer immediately.'));
    redirect('/admin/kb');
}

function admin_chat_settings_save(): void
{
    require_role('admin');
    set_setting('chat_enabled', input('chat_enabled') ? '1' : '0');
    set_setting('chat_in_app', input('chat_in_app') ? '1' : '0');
    $wa = preg_replace('/\D/', '', (string)input('whatsapp_number'));
    set_setting('whatsapp_number', strlen($wa) >= 8 ? $wa : null);
    $ne = (string)input('chat_notify_email');
    set_setting('chat_notify_email', filter_var($ne, FILTER_VALIDATE_EMAIL) ? $ne : null);
    set_setting('chat_greeting', mb_substr(trim(strip_tags((string)input('chat_greeting'))), 0, 300) ?: null);
    flash('success', t('Chat settings saved.'));
    redirect('/admin/chat');
}
