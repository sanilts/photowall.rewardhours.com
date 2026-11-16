<?php
declare(strict_types=1);

/**
 * Checks made when someone signs in to take part:
 *   - IPzentra says where the connection is and whether it is a VPN, proxy, Tor or data centre address;
 *   - BounceBell says whether an email address exists or is a throwaway one.
 * Both are switched on and given their API keys in the console (Settings). When a service is off, has no
 * key or does not answer, the check is skipped and sign-in carries on, so an outage never locks people out.
 */
final class Guard
{
    /** Saved switches and keys (keys decrypted). */
    public static function settings(): array
    {
        $g = Settings::get('guard') ?? [];
        return [
            'ip_on' => !empty($g['ip_on']), 'ip_key' => Settings::open((string) ($g['ip_key'] ?? '')),
            'mail_on' => !empty($g['mail_on']), 'mail_key' => Settings::open((string) ($g['mail_key'] ?? '')),
        ];
    }

    private static function get(string $url, array $headers, int $timeout): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => $headers + [99 => 'Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $json = is_string($body) ? json_decode($body, true) : null;
        if ($code !== 200 || !is_array($json)) {
            error_log('Photowall sign-in check failed (' . parse_url($url, PHP_URL_HOST) . "): status $code " . ($err ?: (is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') : '')));
            return is_array($json) ? ['_failed' => $code, '_message' => (string) ($json['message'] ?? $json['error'] ?? '')] : ['_failed' => $code, '_message' => $err];
        }
        return $json;
    }

    /**
     * Where a connection is and what kind it is. Null when the check is off or gave no answer.
     * 'blocked' is true for a VPN, proxy, Tor or data centre address; 'why' names which.
     */
    public static function ip(string $ip, bool $force = false): ?array
    {
        $s = self::settings();
        if ((!$s['ip_on'] && !$force) || $s['ip_key'] === '' || $ip === '') return null;
        $cached = $_SESSION['ipcheck'] ?? null;
        if (!$force && is_array($cached) && $cached['ip'] === $ip && $cached['at'] > time() - 600) return $cached['res'];
        $base = rtrim((string) cfg('ipzentra.endpoint', 'https://api.ipzentra.com/v1'), '/');
        $r = self::get($base . '/' . rawurlencode($ip), ['X-Api-Key: ' . $s['ip_key']], 6);
        if ($r === null || isset($r['_failed'])) return $force ? $r : null;
        $kinds = array_keys(array_filter([
            'a VPN' => !empty($r['is_vpn']), 'a proxy' => !empty($r['is_proxy']), 'Tor' => !empty($r['is_tor']), 'a data centre' => !empty($r['is_hosting']),
        ]));
        $res = [
            'country_code' => strtoupper((string) ($r['country_code'] ?? '')), 'country_name' => (string) ($r['country_name'] ?? ''),
            // the free IPzentra plan sends no VPN, proxy, Tor or hosting fields: then nothing can be blocked
            'signals' => array_key_exists('is_vpn', $r) || array_key_exists('is_tor', $r) || array_key_exists('is_hosting', $r),
            'blocked' => (bool) $kinds, 'why' => implode(', ', $kinds),
        ];
        if (!$force && session_status() === PHP_SESSION_ACTIVE) $_SESSION['ipcheck'] = ['ip' => $ip, 'at' => time(), 'res' => $res];
        return $res;
    }

    /**
     * Whether an email address can be used. Null when the check is off or gave no answer.
     * 'blocked' is true for an address that does not exist or is a throwaway one; 'message' is for the person.
     */
    public static function email(string $email, bool $force = false): ?array
    {
        $s = self::settings();
        if ((!$s['mail_on'] && !$force) || $s['mail_key'] === '') return null;
        $base = rtrim((string) cfg('bouncebell.endpoint', 'https://bouncebell.com/api/v1'), '/');
        $r = self::get($base . '/verify?' . http_build_query(['email' => $email, 'timeout' => 8]), ['Authorization: Bearer ' . $s['mail_key']], 14);
        if ($r === null || isset($r['_failed'])) return $force ? $r : null;
        $status = (string) ($r['status'] ?? 'unknown');
        $throwaway = !empty($r['disposable']) || $status === 'disposable';
        $hint = (string) ($r['did_you_mean'] ?? '');
        $message = '';
        if ($status === 'invalid') {
            $message = t('This email address does not seem to exist. Please check it for typing mistakes.') . ($hint !== '' ? ' ' . t('Did you mean {hint}?', ['hint' => $hint]) : '');
        } elseif ($throwaway) {
            $message = t('Please use your own permanent email address, not a temporary one. We need it to tell you about your photos and your reward.');
        }
        return ['status' => $status, 'disposable' => $throwaway, 'blocked' => $message !== '', 'message' => $message];
    }
}
