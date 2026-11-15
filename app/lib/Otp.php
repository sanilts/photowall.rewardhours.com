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
        $spaced = substr($code, 0, 3) . ' ' . substr($code, 3);
        $html = '<div style="margin:0;padding:24px;background:#f2f5fa;font-family:Arial,Helvetica,sans-serif;color:#0a1e3c">'
            . '<div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">'
            . '<div style="background:#0a1e3c;color:#fffef0;padding:18px 28px;font-size:18px;font-weight:bold">Photowall</div>'
            . '<div style="padding:28px">'
            . '<p style="margin:0 0 8px;font-size:16px">Your sign-in code</p>'
            . '<p style="margin:0 0 18px;font-size:38px;font-weight:bold;letter-spacing:4px">' . $spaced . '</p>'
            . '<p style="margin:0 0 12px;font-size:15px;line-height:1.5">Enter it on the page where you asked for it (' . e($context) . '). It works for ' . self::MINUTES . ' minutes and only once.</p>'
            . '<p style="margin:0;font-size:13px;line-height:1.5;color:#5a6780">If you did not ask for this code, you can ignore this email.</p>'
            . '</div></div>'
            . '<p style="max-width:480px;margin:14px auto 0;font-size:12px;color:#5a6780;text-align:center">' . e(cfg('company', 'Photowall')) . '</p></div>';
        if (!Mailer::send($email, "$code is your Photowall code", $text, $html, 'code')) {
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
