<?php
declare(strict_types=1);

/**
 * Sends the sign-in code emails. Methods: smtp, postmark, or log (writes to a file, for before email is set up).
 * The settings come from the console's Settings page, or from the settings file if the console has none.
 */
final class Mailer
{
    /** Why the last send failed, in words an administrator can act on. */
    public static string $lastError = '';

    /** What each kind of email is, for the sending log in the console. */
    public const KINDS = [
        'code' => 'Sign-in code', 'client_code' => 'Client sign-in code', 'accepted' => 'Photos accepted', 'invite' => 'Client invitation',
        'request' => 'Download request to staff', 'answer' => 'Answer to a download request', 'test' => 'Test email', 'test_client' => 'Test email (client sender)', '' => 'Other',
    ];

    /** The kinds of email that go to a client's people. They are sent from the client sender address when one is set. */
    public const TO_CLIENTS = ['client_code', 'invite', 'answer', 'test_client'];

    /** Sender used for client emails when Settings has never been saved with one. */
    public const CLIENT_FROM = 'support@researchandmetric.com';

    /**
     * The address and name client emails are sent from: [email, name]. An empty email means "same as every other email".
     * @param array $m the mail settings
     */
    public static function clientSender(array $m): array
    {
        $email = array_key_exists('client_from_email', $m) ? (string) $m['client_from_email'] : self::CLIENT_FROM;
        return [$email, (string) ($m['client_from_name'] ?? '') !== '' ? (string) $m['client_from_name'] : 'Research and Metric'];
    }

    /**
     * Send one email. $kind (a key of KINDS) only labels it in the sending log.
     * Every attempt is written to that log: sent, failed (with the mail server's reason), or only written
     * to the file because email is switched off.
     */
    public static function send(string $to, string $subject, string $text, ?string $html = null, string $kind = ''): bool
    {
        $m = Settings::mail();
        $driver = in_array((string) ($m['driver'] ?? 'log'), ['smtp', 'postmark'], true) ? (string) $m['driver'] : 'log';
        self::$lastError = '';
        $usual = $m;
        [$cFrom, $cName] = self::clientSender($m);
        if (in_array($kind, self::TO_CLIENTS, true) && $cFrom !== '' && $cFrom !== (string) ($m['from_email'] ?? '')) {
            $m['from_email'] = $cFrom;
            $m['from_name'] = $cName;
        }
        $go = static fn(array $with): bool => match ($driver) {
            'smtp' => self::smtp($with, $to, $subject, $text, $html),
            'postmark' => self::postmark($with, $to, $subject, $text, $html),
            default => self::log($to, $subject, $text),
        };
        $note = '';
        try {
            $ok = $go($m);
        } catch (Throwable $ex) {
            $ok = false;
            self::$lastError = $ex->getMessage();
            error_log('Photowall mail error: ' . $ex->getMessage());
            // The mail account may not be allowed to send as the client address. The email matters more than
            // its sender, so it goes out from the usual address instead, and the log says what happened.
            if ($m['from_email'] !== ($usual['from_email'] ?? '') && ($usual['from_email'] ?? '') !== '') {
                try {
                    $ok = $go($usual);
                    $note = "Sent from {$usual['from_email']} instead, because sending as {$m['from_email']} failed: " . self::$lastError;
                    $m = $usual;
                    self::$lastError = '';
                } catch (Throwable $again) {
                    self::$lastError = $again->getMessage();
                }
            }
        }
        self::record($to, (string) ($m['from_email'] ?? ''), $subject, $kind, $driver, $ok ? ($driver === 'log' ? 'file' : 'sent') : 'failed', $ok ? $note : self::$lastError);
        return $ok;
    }

    /** One line in the sending log. A sign-in code in the subject is blanked: the log must never show a working code. */
    private static function record(string $to, string $from, string $subject, string $kind, string $driver, string $result, string $error): void
    {
        try {
            Db::q('INSERT INTO mail_log (to_email, from_email, subject, kind, driver, result, error, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [mb_substr($to, 0, 190), mb_substr($from, 0, 190), mb_substr((string) preg_replace('/\b\d{6}\b/', '******', $subject), 0, 255), isset(self::KINDS[$kind]) ? $kind : '', $driver, $result, mb_substr($error, 0, 500), now()]);
        } catch (Throwable $e) {
            error_log('Photowall mail log: ' . $e->getMessage());
        }
    }

    /** Lines of the sending log, newest first. $only: '' for all, or 'sent', 'failed', 'file'. */
    public static function logRows(string $only, int $limit, int $offset = 0): array
    {
        $where = in_array($only, ['sent', 'failed', 'file'], true) ? 'WHERE result = ?' : '';
        return Db::all("SELECT * FROM mail_log $where ORDER BY id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $where !== '' ? [$only] : []);
    }

    /** How many emails were sent, failed, or only written to the file. $days 0 counts everything. */
    public static function logCounts(int $days = 0): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'file' => 0];
        $rows = $days > 0
            ? Db::all('SELECT result, COUNT(*) AS n FROM mail_log WHERE created_at >= ? GROUP BY result', [date('Y-m-d H:i:s', time() - $days * 86400)])
            : Db::all('SELECT result, COUNT(*) AS n FROM mail_log GROUP BY result');
        foreach ($rows as $r) $out[$r['result']] = (int) $r['n'];
        return $out + ['total' => array_sum($out)];
    }

    public static function isSetUp(): bool
    {
        return in_array((string) (Settings::mail()['driver'] ?? 'log'), ['smtp', 'postmark'], true);
    }

    private static function log(string $to, string $subject, string $text): bool
    {
        $dir = cfg('storage') . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $line = '[' . now() . "] To: $to | Subject: $subject\n$text\n\n";
        return file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function postmark(array $m, string $to, string $subject, string $text, ?string $html): bool
    {
        $body = ['From' => ($m['from_name'] ?? 'Photowall') . ' <' . ($m['from_email'] ?? '') . '>', 'To' => $to, 'Subject' => $subject, 'TextBody' => $text, 'MessageStream' => 'outbound'];
        if ($html !== null) $body['HtmlBody'] = $html;
        $ch = curl_init('https://api.postmarkapp.com/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-Postmark-Server-Token: ' . ($m['postmark_token'] ?? '')],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $reply = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code !== 200) {
            throw new RuntimeException($err !== '' ? "Could not reach Postmark: $err" : 'Postmark refused the email: ' . (json_decode($reply, true)['Message'] ?? "answer $code"));
        }
        return true;
    }

    private static function smtp(array $m, string $to, string $subject, string $text, ?string $html): bool
    {
        $s = (array) ($m['smtp'] ?? []);
        $host = trim((string) ($s['host'] ?? ''));
        if ($host === '') throw new RuntimeException('No SMTP server address is set.');
        $secure = strtolower((string) ($s['secure'] ?? 'tls'));
        $port = (int) ($s['port'] ?? 587);
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
        if (!$fp) throw new RuntimeException("Could not connect to $host on port $port" . ($errstr !== '' ? " ($errstr)" : '') . '. Check the server address, port and security setting.');
        stream_set_timeout($fp, 20);
        $read = static function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $out .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return $out;
        };
        $cmd = static function (string $line, string $expect, string $doing) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            $reply = $read();
            if (!str_starts_with($reply, $expect)) {
                throw new RuntimeException("The mail server refused $doing: " . (trim($reply) !== '' ? trim(preg_replace('/\s+/', ' ', $reply)) : 'no answer'));
            }
            return $reply;
        };
        try {
            $hello = gethostname() ?: 'localhost';
            if (!str_starts_with($read(), '220')) throw new RuntimeException("$host did not answer like a mail server. Check the port and security setting.");
            $cmd('EHLO ' . $hello, '250', 'the greeting');
            if ($secure === 'tls') {
                $cmd('STARTTLS', '220', 'a secure connection (STARTTLS)');
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('The secure connection (STARTTLS) could not be set up. Try the SSL setting with port 465.');
                $cmd('EHLO ' . $hello, '250', 'the greeting');
            }
            if (($s['user'] ?? '') !== '') {
                $cmd('AUTH LOGIN', '334', 'signing in');
                $cmd(base64_encode((string) $s['user']), '334', 'the username');
                $cmd(base64_encode((string) ($s['pass'] ?? '')), '235', 'the username or password');
            }
            $from = (string) ($m['from_email'] ?? '');
            $cmd('MAIL FROM:<' . $from . '>', '250', "the sender address $from");
            $cmd('RCPT TO:<' . $to . '>', '25', "the recipient $to");
            $cmd('DATA', '354', 'the message');
            $headers = [
                'Date: ' . date('r'),
                'From: ' . self::encodeHeader((string) ($m['from_name'] ?? 'Photowall')) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@') ?: '@localhost', 1) . '>',
                'MIME-Version: 1.0',
            ];
            $part = static fn(string $type, string $content): string => "Content-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content), 76, "\r\n");
            if ($html === null) {
                $message = implode("\r\n", $headers) . "\r\n" . $part('text/plain', $text);
            } else {
                $b = 'pw' . bin2hex(random_bytes(10));
                $message = implode("\r\n", $headers) . "\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
                    . "--$b\r\n" . $part('text/plain', $text) . "--$b\r\n" . $part('text/html', $html) . "--$b--\r\n";
            }
            $cmd($message . "\r\n.", '250', 'the message');
            fwrite($fp, "QUIT\r\n");
            return true;
        } finally {
            fclose($fp);
        }
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
