<?php
// mailer.php - email bhejna (log / smtp / mail) + token helpers for verify & reset

function mail_log(string $line): void {
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    @file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function mail_template(string $heading, string $intro, string $btnLabel, string $link, string $footer): string {
    $dir = is_rtl() ? 'rtl' : 'ltr';
    $h = escapeHtml($heading); $i = escapeHtml($intro); $b = escapeHtml($btnLabel); $l = escapeHtml($link); $f = escapeHtml($footer);
    return <<<HTML
<!DOCTYPE html><html dir="$dir"><body style="margin:0;padding:0;background:#F5F3EF;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F3EF;padding:32px 12px;"><tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border:1px solid #D8CBAF;border-radius:16px;overflow:hidden;">
<tr><td style="background:#171717;padding:22px 28px;color:#C5A253;font-size:20px;font-weight:bold;letter-spacing:.5px;">PromiseEdger</td></tr>
<tr><td style="padding:30px 28px 10px;color:#171717;"><h2 style="margin:0 0 12px;font-size:22px;">$h</h2><p style="margin:0 0 24px;line-height:1.6;color:#4a4a4a;">$i</p>
<a href="$l" style="display:inline-block;background:#C5A253;color:#171717;text-decoration:none;font-weight:bold;padding:13px 26px;border-radius:10px;">$b</a>
<p style="margin:24px 0 0;font-size:12px;color:#8a8576;word-break:break-all;">$l</p></td></tr>
<tr><td style="padding:20px 28px 28px;color:#8a8576;font-size:12px;line-height:1.5;">$f</td></tr>
</table></td></tr></table></body></html>
HTML;
}

/**
 * Email bhejo. $devLink local testing me screen par dikhane ke liye.
 */
function sendMail(string $to, string $subject, string $html, string $text = '', ?string $devLink = null): bool {
    $text = $text !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $html))));
    $boundary = 'pe_' . bin2hex(random_bytes(8));
    $fromName = '=?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?=';
    $headers = [
        'From: ' . $fromName . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PromiseEdger',
    ];
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    if (MAIL_DRIVER === 'smtp') {
        $ok = smtp_send($to, $encSubject, $headers, $body);
        if (!$ok) mail_log("SMTP FAILED to=$to subject=$subject");
        return $ok;
    }
    if (MAIL_DRIVER === 'mail') {
        $ok = @mail($to, $encSubject, $body, implode("\r\n", $headers));
        if (!$ok) mail_log("mail() FAILED to=$to subject=$subject");
        return $ok;
    }
    // log driver
    mail_log("TO=$to | SUBJECT=$subject | LINK=" . ($devLink ?? '-'));
    if (APP_DEBUG && $devLink) {
        $_SESSION['dev_mail'] = ['to' => $to, 'subject' => $subject, 'link' => $devLink];
    }
    return true;
}

function smtp_send(string $to, string $encSubject, array $headers, string $body): bool {
    $secure = MAIL_ENCRYPTION === 'ssl';
    $fp = @stream_socket_client(($secure ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . MAIL_PORT, $errno, $errstr, 12);
    if (!$fp) { mail_log("SMTP connect error: $errstr"); return false; }
    stream_set_timeout($fp, 12);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function (?string $cmd, array $ok) use ($fp, $read): bool {
        if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
        $r = $read();
        foreach ($ok as $code) if (strncmp($r, (string)$code, 3) === 0) return true;
        mail_log('SMTP unexpected reply: ' . trim($r));
        return false;
    };

    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $ok = $send(null, [220]) && $send("EHLO $host", [250]);
    if ($ok && MAIL_ENCRYPTION === 'tls') {
        $ok = $send('STARTTLS', [220]) && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $send("EHLO $host", [250]);
    }
    if ($ok && MAIL_USER !== '') {
        $ok = $send('AUTH LOGIN', [334]) && $send(base64_encode(MAIL_USER), [334]) && $send(base64_encode(MAIL_PASS), [235]);
    }
    $ok = $ok && $send('MAIL FROM:<' . MAIL_FROM . '>', [250]) && $send("RCPT TO:<$to>", [250, 251]) && $send('DATA', [354]);
    if ($ok) {
        $msg = 'To: <' . $to . ">\r\nSubject: $encSubject\r\nDate: " . date('r') . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $msg));
        $msg = str_replace("\n", "\r\n", $msg);
        fwrite($fp, $msg . "\r\n.\r\n");
        $ok = $send(null, [250]);
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

// ========== TOKENS (verify / reset) ==========

/** Naya token banao. Plain token return hota hai, DB me sirf hash jata hai. */
function createToken(mysqli $c, int $uid, string $type, int $ttlMinutes): string {
    db_exec($c, "DELETE FROM user_tokens WHERE user_id = ? AND type = ?", "is", [$uid, $type]);
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    db_exec($c, "INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL $ttlMinutes MINUTE)", "iss", [$uid, $type, $hash]);
    return $token;
}

/** Token valid hai? user row return karo (used/expired = null). */
function findToken(mysqli $c, string $token, string $type): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return db_one($c, "SELECT t.id AS token_id, t.user_id, u.name, u.email FROM user_tokens t JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? AND t.type = ? AND t.used_at IS NULL AND t.expires_at > NOW()", "ss", [hash('sha256', $token), $type]);
}

function recentTokenExists(mysqli $c, int $uid, string $type, int $seconds = 60): bool {
    return (bool)db_val($c, "SELECT 1 FROM user_tokens WHERE user_id = ? AND type = ? AND created_at > NOW() - INTERVAL $seconds SECOND LIMIT 1", "is", [$uid, $type]);
}

function sendVerificationEmail(mysqli $c, array $user): bool {
    $token = createToken($c, (int)$user['id'], 'verify', 60 * 24);
    $link = appUrl('verify_email.php?token=' . $token);
    $html = mail_template(t('mail_verify_heading'), t('mail_verify_intro', $user['name']), t('mail_verify_btn'), $link, t('mail_ignore'));
    return sendMail($user['email'], t('mail_verify_subject'), $html, '', $link);
}

function sendResetEmail(mysqli $c, array $user): bool {
    $token = createToken($c, (int)$user['id'], 'reset', 60);
    $link = appUrl('reset_password.php?token=' . $token);
    $html = mail_template(t('mail_reset_heading'), t('mail_reset_intro', $user['name']), t('mail_reset_btn'), $link, t('mail_reset_footer'));
    return sendMail($user['email'], t('mail_reset_subject'), $html, '', $link);
}
