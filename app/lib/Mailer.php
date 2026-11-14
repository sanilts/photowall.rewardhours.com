<?php
declare(strict_types=1);

/** Sends plain emails (one-time codes). Drivers: log, smtp, postmark. */
final class Mailer
{
    public static function send(string $to, string $subject, string $text): bool
    {
        $driver = (string) cfg('mail.driver', 'log');
        try {
            return match ($driver) {
                'smtp' => self::smtp($to, $subject, $text),
                'postmark' => self::postmark($to, $subject, $text),
                default => self::log($to, $subject, $text),
            };
        } catch (Throwable $ex) {
            error_log('Photowall mail error: ' . $ex->getMessage());
            return false;
        }
    }

    private static function log(string $to, string $subject, string $text): bool
    {
        $dir = cfg('storage') . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $line = '[' . now() . "] To: $to | Subject: $subject\n$text\n\n";
        return file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function postmark(string $to, string $subject, string $text): bool
    {
        $ch = curl_init('https://api.postmarkapp.com/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-Postmark-Server-Token: ' . cfg('mail.postmark_token')],
            CURLOPT_POSTFIELDS => json_encode([
                'From' => cfg('mail.from_name') . ' <' . cfg('mail.from_email') . '>',
                'To' => $to, 'Subject' => $subject, 'TextBody' => $text, 'MessageStream' => 'outbound',
            ]),
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $code === 200;
    }

    private static function smtp(string $to, string $subject, string $text): bool
    {
        $s = (array) cfg('mail.smtp');
        $secure = strtolower((string) ($s['secure'] ?? 'tls'));
        $port = (int) ($s['port'] ?? 587);
        $fp = stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $port, $errno, $errstr, 15);
        if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $out .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return $out;
        };
        $cmd = static function (string $line, string $expect) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            $reply = $read();
            if (!str_starts_with($reply, $expect)) throw new RuntimeException('SMTP said: ' . trim($reply));
            return $reply;
        };
        $hello = gethostname() ?: 'localhost';
        if (!str_starts_with($read(), '220')) throw new RuntimeException('SMTP greeting failed');
        $cmd('EHLO ' . $hello, '250');
        if ($secure === 'tls') {
            $cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed');
            $cmd('EHLO ' . $hello, '250');
        }
        if (($s['user'] ?? '') !== '') {
            $cmd('AUTH LOGIN', '334');
            $cmd(base64_encode((string) $s['user']), '334');
            $cmd(base64_encode((string) $s['pass']), '235');
        }
        $from = (string) cfg('mail.from_email');
        $cmd('MAIL FROM:<' . $from . '>', '250');
        $cmd('RCPT TO:<' . $to . '>', '25');
        $cmd('DATA', '354');
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader((string) cfg('mail.from_name')) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@') ?: '@localhost', 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = chunk_split(base64_encode($text), 76, "\r\n");
        $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", '250');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
