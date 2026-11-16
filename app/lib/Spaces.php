<?php
declare(strict_types=1);

/**
 * Stores original photos in a DigitalOcean Spaces bucket (or any S3-compatible store).
 * Small built-in client using the standard request signing (AWS Signature Version 4), no SDK.
 * Every object is uploaded private; people only ever get a link that expires in minutes.
 */
final class Spaces
{
    public static function on(): bool
    {
        return (bool) cfg('spaces.enabled', false) && cfg('spaces.key', '') !== '' && cfg('spaces.secret', '') !== '' && cfg('spaces.bucket', '') !== '';
    }

    /** Where a photo's original lives in the bucket. */
    public static function keyFor(array $photo): string
    {
        $prefix = trim((string) cfg('spaces.prefix', 'photowall'), '/');
        return ($prefix !== '' ? $prefix . '/' : '') . 'originals/' . (int) $photo['project_id'] . '/' . $photo['token'] . '.' . $photo['ext'];
    }

    /** Uploads a local file as a private object. The store verifies the bytes against the checksums we send. */
    public static function put(string $file, string $key, string $mime, ?string $sha256 = null): bool
    {
        $fh = fopen($file, 'rb');
        if (!$fh) return false;
        $headers = [
            'content-md5' => base64_encode((string) md5_file($file, true)),
            'content-type' => $mime !== '' ? $mime : 'application/octet-stream',
            'x-amz-acl' => 'private',
        ];
        [$url, $send] = self::signed('PUT', $key, $headers, $sha256 ?? (string) hash_file('sha256', $file));
        $send[] = 'Expect:';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => (int) filesize($file),
            CURLOPT_HTTPHEADER => $send, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 600,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($code !== 200) self::$lastError = self::explain($code, (string) $body, $err);
        return $code === 200;
    }

    /** Downloads an object to a local file. */
    public static function get(string $key, string $toFile): bool
    {
        $fh = fopen($toFile, 'wb');
        if (!$fh) return false;
        [$url, $send] = self::signed('GET', $key, [], hash('sha256', ''));
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_HTTPHEADER => $send, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 600, CURLOPT_FAILONERROR => true]);
        $ok = curl_exec($ch) !== false && (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        if (!$ok) self::$lastError = self::explain((int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), '', curl_error($ch));
        curl_close($ch);
        fclose($fh);
        if (!$ok) @unlink($toFile);
        return $ok;
    }

    public static function delete(string $key): bool
    {
        [$url, $send] = self::signed('DELETE', $key, [], hash('sha256', ''));
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => $send, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code !== 204 && $code !== 200) self::$lastError = self::explain($code, (string) $body, curl_error($ch));
        curl_close($ch);
        return $code === 204 || $code === 200;
    }

    /** Size of an object in bytes, or null when it is not there. */
    public static function size(string $key): ?int
    {
        [$url, $send] = self::signed('HEAD', $key, [], hash('sha256', ''));
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_HTTPHEADER => $send, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $len = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);
        curl_close($ch);
        return $code === 200 ? $len : null;
    }

    /** A private download link that stops working after $seconds. */
    public static function link(string $key, int $seconds = 300, ?string $downloadName = null, ?string $mime = null, ?int $at = null): string
    {
        $at ??= time();
        [$scheme, $host, $path] = self::target($key);
        $day = gmdate('Ymd', $at);
        $scope = $day . '/' . cfg('spaces.region', 'us-east-1') . '/s3/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => cfg('spaces.key') . '/' . $scope,
            'X-Amz-Date' => gmdate('Ymd\THis\Z', $at),
            'X-Amz-Expires' => (string) $seconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        if ($downloadName !== null) $query['response-content-disposition'] = 'attachment; filename="' . $downloadName . '"';
        if ($mime !== null) $query['response-content-type'] = $mime;
        $canonQuery = self::query($query);
        $request = "GET\n$path\n$canonQuery\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
        $toSign = "AWS4-HMAC-SHA256\n" . $query['X-Amz-Date'] . "\n$scope\n" . hash('sha256', $request);
        return "$scheme://$host$path?$canonQuery&X-Amz-Signature=" . hash_hmac('sha256', $toSign, self::signingKey($day));
    }

    /** Uploads, reads back and deletes a tiny test object. Returns null when all three work, else what went wrong. */
    public static function selfTest(): ?string
    {
        $key = trim((string) cfg('spaces.prefix', 'photowall'), '/') . '/connection-test-' . bin2hex(random_bytes(6)) . '.txt';
        $tmp = tempnam(sys_get_temp_dir(), 'pwsp');
        $text = 'Photowall connection test ' . date('c');
        file_put_contents($tmp, $text);
        try {
            if (!self::put($tmp, $key, 'text/plain')) return 'Upload failed: ' . self::$lastError;
            if (self::size($key) !== strlen($text)) return 'The uploaded test file could not be found again.';
            $back = $tmp . '.back';
            if (!self::get($key, $back) || file_get_contents($back) !== $text) return 'Download failed: ' . self::$lastError;
            @unlink($back);
            $ch = curl_init(explode('?', self::link($key))[0]);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 30]);
            $public = curl_exec($ch);
            curl_close($ch);
            if ($public === $text) return 'The test file could be read without a private link. Files in this bucket are public, so photos must not go here.';
            if (!self::delete($key)) return 'Delete failed: ' . self::$lastError;
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    public static string $lastError = '';

    /* ---------- request signing ---------- */

    /** The bucket's own address (scheme and host), or '' when photos are kept on this server. */
    public static function origin(): string
    {
        if (!self::on()) return '';
        [$scheme, $host] = self::target('');
        return $host !== '' && preg_match('/^[a-z0-9.:-]+$/i', $host) ? $scheme . '://' . $host : '';
    }

    /** @return array{0:string,1:string,2:string} scheme, host, path */
    private static function target(string $key): array
    {
        $u = parse_url((string) cfg('spaces.endpoint', ''));
        $scheme = $u['scheme'] ?? 'https';
        $host = ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
        $path = '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        $bucket = (string) cfg('spaces.bucket');
        return cfg('spaces.path_style', false) ? [$scheme, $host, '/' . rawurlencode($bucket) . $path] : [$scheme, $bucket . '.' . $host, $path];
    }

    /** Builds the URL and the signed headers for one request. Public so the signing can be tested. */
    public static function signed(string $method, string $key, array $headers, string $payloadHash, ?int $at = null): array
    {
        $at ??= time();
        [$scheme, $host, $path] = self::target($key);
        $stamp = gmdate('Ymd\THis\Z', $at);
        $day = gmdate('Ymd', $at);
        $headers += ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $stamp];
        ksort($headers);
        $canon = '';
        foreach ($headers as $k => $v) $canon .= $k . ':' . trim((string) $v) . "\n";
        $names = implode(';', array_keys($headers));
        $scope = $day . '/' . cfg('spaces.region', 'us-east-1') . '/s3/aws4_request';
        $request = "$method\n$path\n\n$canon\n$names\n$payloadHash";
        $toSign = "AWS4-HMAC-SHA256\n$stamp\n$scope\n" . hash('sha256', $request);
        $auth = 'AWS4-HMAC-SHA256 Credential=' . cfg('spaces.key') . "/$scope, SignedHeaders=$names, Signature=" . hash_hmac('sha256', $toSign, self::signingKey($day));
        $send = ['Authorization: ' . $auth];
        foreach ($headers as $k => $v) {
            if ($k !== 'host') $send[] = $k . ': ' . $v;
        }
        return ["$scheme://$host$path", $send];
    }

    private static function signingKey(string $day): string
    {
        $k = hash_hmac('sha256', $day, 'AWS4' . cfg('spaces.secret'), true);
        $k = hash_hmac('sha256', (string) cfg('spaces.region', 'us-east-1'), $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        return hash_hmac('sha256', 'aws4_request', $k, true);
    }

    private static function query(array $q): string
    {
        ksort($q);
        $parts = [];
        foreach ($q as $k => $v) $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        return implode('&', $parts);
    }

    private static function explain(int $code, string $body, string $curlError): string
    {
        if ($curlError !== '') return 'could not connect (' . $curlError . ')';
        $reason = preg_match('#<Code>([^<]+)</Code>#', $body, $m) ? $m[1] : '';
        return match (true) {
            $reason === 'InvalidAccessKeyId' => 'the access key is not recognised',
            $reason === 'SignatureDoesNotMatch' => 'the secret key does not match the access key',
            $reason === 'NoSuchBucket' => 'the bucket name or region is wrong',
            $code === 403 => 'access was refused (the key may not have permission for this bucket)',
            default => "the store answered $code" . ($reason !== '' ? " ($reason)" : ''),
        };
    }
}
