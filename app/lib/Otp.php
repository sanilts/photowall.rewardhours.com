<?php
declare(strict_types=1);

/** Six-digit one-time codes sent by email. Stored only as a keyed hash. */
final class Otp
{
    public const MINUTES = 10;
    public const MAX_TRIES = 5;

    /** Returns null on success, or a message to show the person. */
    public static function issue(string $area, string $email, int $projectId, string $context): ?string
    {
        if (!throttle("otp:mail:$area:$email", 5, 3600) || !throttle('otp:ip:' . client_ip(), 30, 3600)) {
            return 'Too many codes were requested. Please wait a while and try again.';
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Db::q('UPDATE otps SET used_at = ? WHERE area = ? AND email = ? AND project_id = ? AND used_at IS NULL', [now(), $area, $email, $projectId]);
        Db::q(
            'INSERT INTO otps (area, email, project_id, code_hash, expires_at, ip, created_at) VALUES (?,?,?,?,?,?,?)',
            [$area, $email, $projectId, self::hash($email, $code), date('Y-m-d H:i:s', time() + self::MINUTES * 60), client_ip(), now()]
        );
        $text = "Your Photowall code is: $code\n\n"
            . "Enter it on the page where you asked for it ($context). It works for " . self::MINUTES . " minutes and only once.\n\n"
            . "If you did not ask for this code, you can ignore this email.\n\n"
            . cfg('company', 'Photowall');
        if (!Mailer::send($email, "$code is your Photowall code", $text)) {
            return 'We could not send the email right now. Please try again in a minute.';
        }
        return null;
    }

    public static function verify(string $area, string $email, int $projectId, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        $row = Db::one(
            'SELECT * FROM otps WHERE area = ? AND email = ? AND project_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [$area, $email, $projectId]
        );
        if (!$row || strtotime($row['expires_at']) < time() || (int) $row['attempts'] >= self::MAX_TRIES) return false;
        if (!hash_equals($row['code_hash'], self::hash($email, (string) $code))) {
            Db::q('UPDATE otps SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            return false;
        }
        Db::q('UPDATE otps SET used_at = ? WHERE id = ?', [now(), $row['id']]);
        return true;
    }

    private static function hash(string $email, string $code): string
    {
        return hash_hmac('sha256', $email . '|' . $code, (string) cfg('secret'));
    }
}
