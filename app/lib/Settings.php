<?php
declare(strict_types=1);

/**
 * Settings that staff change in the console (currently: email sending).
 * They live in the database and take priority over the settings file.
 * Passwords are stored encrypted with the app's secret.
 */
final class Settings
{
    private static array $cache = [];

    public static function get(string $name): ?array
    {
        if (!array_key_exists($name, self::$cache)) {
            try {
                $raw = Db::val('SELECT value FROM settings WHERE name = ?', [$name]);
            } catch (Throwable $ex) {
                $raw = null; // table not there yet (before the database update has run)
            }
            $val = $raw !== null ? json_decode((string) $raw, true) : null;
            self::$cache[$name] = is_array($val) ? $val : null;
        }
        return self::$cache[$name];
    }

    public static function set(string $name, array $value): void
    {
        Db::q('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        self::$cache[$name] = $value;
    }

    /** Email settings in use: the console's, or the settings file when the console has none. Secrets are decrypted. */
    public static function mail(): array
    {
        $m = self::get('mail');
        if ($m === null) return (array) cfg('mail', []) + ['source' => 'file'];
        $m['smtp']['pass'] = self::open((string) ($m['smtp']['pass'] ?? ''));
        $m['postmark_token'] = self::open((string) ($m['postmark_token'] ?? ''));
        return $m + ['source' => 'console'];
    }

    public static function seal(string $plain): string
    {
        if ($plain === '') return '';
        $key = hash('sha256', 'pw-settings|' . cfg('secret'), true);
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function open(string $sealed): string
    {
        if (!str_starts_with($sealed, 'v1:')) return '';
        $bin = base64_decode(substr($sealed, 3), true);
        if ($bin === false || strlen($bin) < 29) return '';
        $key = hash('sha256', 'pw-settings|' . cfg('secret'), true);
        $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
