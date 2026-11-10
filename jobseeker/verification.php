<?php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'gerald.campos1215@gmail.com');
define('SMTP_PASS', 'oksgfbirftxdlxnx');   // 16 letters, no spaces
define('SMTP_VERIFY_SSL', true);
define('SHOW_CODE_ON_SCREEN', false);
const VERIFY_MINUTES        = 15;   // code lifetime
const VERIFY_MAX_TRIES      = 5;    // wrong attempts before a new code is required
const VERIFY_RESEND_SECONDS = 60;   // cooldown between resends

/* ---------- minimal SMTP client (no libraries needed) ---------- */
function smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;   // last line of a multi-line reply
    }
    return $data;
}
function smtp_cmd($fp, string $cmd, array $ok): string {
    if ($cmd !== '') fwrite($fp, $cmd . "\r\n");
    $res = smtp_read($fp);
    if (!in_array((int)substr($res, 0, 3), $ok, true)) throw new Exception(trim($res) ?: 'No response from SMTP server');
    return $res;
}

/** Sends a plain-text email through SMTP. Returns true on success; $error holds the reason on failure. */
function smtp_send(string $to, string $subject, string $body, string &$error = ''): bool {
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $verify = defined('SMTP_VERIFY_SSL') ? (bool)SMTP_VERIFY_SSL : true;

    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
    ]]);
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $error = "Cannot connect to $host:$port – $errstr ($errno)"; return false; }
    stream_set_timeout($fp, 15);

    try {
        smtp_cmd($fp, '', [220]);
        smtp_cmd($fp, 'EHLO localhost', [250]);
        if ($port !== 465) {                                   // STARTTLS for port 587
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('STARTTLS failed');
            smtp_cmd($fp, 'EHLO localhost', [250]);
        }
        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode($user), [334]);
        smtp_cmd($fp, base64_encode($pass), [235]);
        smtp_cmd($fp, 'MAIL FROM:<' . $user . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);          // dot-stuffing
        $msg  = 'Date: ' . date('r') . "\r\n"
              . 'From: JobSeeker <' . $user . ">\r\n"
              . 'To: <' . $to . ">\r\n"
              . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
              . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@jobseeker.local>' . "\r\n"
              . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
              . $body;
        fwrite($fp, $msg . "\r\n.\r\n");
        smtp_cmd($fp, '', [250]);
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        @fclose($fp);
        return false;
    }
}

/** Generates a new 6-digit code, saves its hash + expiry, emails it. Returns the plain code. */
function send_verification_code(string $email, string $name): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q('UPDATE pending_registrations SET code_hash=?, expires_at=?, attempts=0, last_sent=? WHERE email=?',
      [hash('sha256', $code), date('Y-m-d H:i:s', time() + VERIFY_MINUTES * 60), date('Y-m-d H:i:s'), $email]);

    $subject = 'Your JobSeeker verification code';
    $body = "Hi $name,\r\n\r\nYour verification code is: $code\r\n\r\n"
          . 'This code expires in ' . VERIFY_MINUTES . " minutes.\r\n"
          . 'If you did not create a JobSeeker account, ignore this email.';

    if (defined('SMTP_USER') && SMTP_USER !== '') {
        $err = '';
        if (!smtp_send($email, $subject, $body, $err)) error_log('[JobSeeker] SMTP failed: ' . $err);   // see apache\logs\error.log
    } else {
        @mail($email, $subject, $body, 'From: ' . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost'));   // fallback (does not work on plain XAMPP)
    }
    return $code;
}
