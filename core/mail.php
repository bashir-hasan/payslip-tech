<?php
declare(strict_types=1);

/**
 * Email sending. Uses SMTP when configured in Admin → Settings (recommended: a cPanel mailbox,
 * Gmail/Workspace, Zoho, Brevo, Resend…), otherwise PHP mail().
 * The last error is kept in mail_last_error() for the "Send test email" button.
 */

function mail_last_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

function send_mail(string $to, string $subject, string $body, ?string $actionUrl = null, ?string $actionLabel = null): bool
{
    mail_last_error('');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Invalid recipient address.');
        return false;
    }
    $host = preg_replace('/^www\./', '', (string)parse_url(abs_url('/'), PHP_URL_HOST));
    $from = (string)(setting('mail_from') ?: 'no-reply@' . ($host ?: 'payslip.tech'));
    $name = site_name();
    $boundary = 'b' . bin2hex(random_bytes(8));

    $text = $body . ($actionUrl ? "\n\n" . ($actionLabel ?: 'Open') . ': ' . $actionUrl : '') . "\n\n— " . $name;
    $html = '<!doctype html><html><body style="margin:0;background:#f6f8f7"><div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto;padding:28px 24px;color:#0b1f1c">'
        . '<div style="font-weight:800;font-size:18px;margin-bottom:18px">' . e($name) . '</div>'
        . '<div style="background:#fff;border:1px solid #e3e9e7;border-radius:14px;padding:22px;font-size:15px;line-height:1.6">' . nl2br(e($body))
        . ($actionUrl ? '<p style="margin:22px 0 6px"><a href="' . e($actionUrl) . '" style="background:#0f766e;color:#fff;padding:12px 20px;border-radius:9px;text-decoration:none;font-weight:700;display:inline-block">' . e($actionLabel ?: 'Open') . '</a></p><p style="font-size:12px;color:#64736f;word-break:break-all">' . e($actionUrl) . '</p>' : '')
        . '</div><p style="font-size:12px;color:#64736f;margin-top:16px">' . e($name) . ' · ' . e(abs_url('/')) . '</p></div></body></html>';

    $msgId = '<' . bin2hex(random_bytes(12)) . '@' . ($host ?: 'payslip.tech') . '>';
    $headers = [
        'From' => mb_encode_mimeheader($name, 'UTF-8', 'Q') . ' <' . $from . '>',
        'Reply-To' => (string)(setting('support_email') ?: $from),
        'Message-ID' => $msgId,
        'Date' => date('r'),
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer' => 'PaySlip.tech',
    ];
    $msg = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $encSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');

    if (setting('mail_driver', 'mail') === 'smtp' && setting('smtp_host')) {
        return smtp_send($from, $to, $encSubject, $headers, $msg);
    }
    try {
        $hdr = '';
        foreach ($headers as $k => $v) $hdr .= "$k: $v\r\n";
        $ok = @mail($to, $encSubject, $msg, rtrim($hdr), '-f' . $from);
        if (!$ok) mail_last_error('PHP mail() is disabled or rejected by this server. Switch to SMTP in Admin → Settings.');
        return $ok;
    } catch (Throwable $e) {
        mail_last_error($e->getMessage());
        return false;
    }
}

/** Minimal SMTP client: SSL (465), STARTTLS (587) or plain; AUTH LOGIN/PLAIN. */
function smtp_send(string $from, string $to, string $subject, array $headers, string $body): bool
{
    $host = (string)setting('smtp_host');
    $port = (int)setting('smtp_port', '465');
    $secure = (string)setting('smtp_secure', 'ssl');
    $user = (string)setting('smtp_user', '');
    $pass = decrypt_str((string)setting('smtp_pass')) ?? '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        mail_last_error("Could not connect to $host:$port ($errstr). Check host, port and encryption.");
        return false;
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): ?string {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        return in_array((int)substr($r, 0, 3), $ok, true) ? $r : null;
    };
    $fail = function (string $msg) use ($fp): bool {
        mail_last_error($msg);
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return false;
    };
    if ((int)substr($read(), 0, 3) !== 220) return $fail('SMTP server did not greet correctly.');
    $ehloHost = (string)(parse_url(abs_url('/'), PHP_URL_HOST) ?: 'localhost');
    if (!$cmd("EHLO $ehloHost", [250])) return $fail('EHLO was rejected.');
    if ($secure === 'tls') {
        if (!$cmd('STARTTLS', [220])) return $fail('STARTTLS was rejected. Try SSL on port 465.');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) return $fail('TLS handshake failed.');
        if (!$cmd("EHLO $ehloHost", [250])) return $fail('EHLO after STARTTLS was rejected.');
    }
    if ($user !== '') {
        if ($cmd('AUTH LOGIN', [334])) {
            if (!$cmd(base64_encode($user), [334]) || !$cmd(base64_encode($pass), [235])) return $fail('SMTP login failed: wrong username or password.');
        } elseif (!$cmd('AUTH PLAIN ' . base64_encode("\0$user\0$pass"), [235])) {
            return $fail('SMTP login failed: wrong username or password.');
        }
    }
    if (!$cmd("MAIL FROM:<$from>", [250])) return $fail("Server refused sender $from. It usually must be the same as the SMTP username.");
    if (!$cmd("RCPT TO:<$to>", [250, 251])) return $fail("Server refused recipient $to.");
    if (!$cmd('DATA', [354])) return $fail('DATA was rejected.');
    $hdr = 'To: <' . $to . ">\r\nSubject: $subject\r\n";
    foreach ($headers as $k => $v) $hdr .= "$k: $v\r\n";
    $data = $hdr . "\r\n" . preg_replace('/^\./m', '..', $body);
    if (!$cmd($data . "\r\n.", [250])) return $fail('The message was rejected by the SMTP server.');
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}
