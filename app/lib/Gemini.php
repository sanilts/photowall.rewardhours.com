<?php
declare(strict_types=1);

/**
 * Creates a picture from a written description with Google's Gemini API.
 * Staff use it in the console to make the example photos shown inside the phones on the project page.
 * The API key is entered in the console and stored encrypted. A new picture is kept
 * as a draft until a member of staff chooses to use it.
 */
final class Gemini
{
    public const DEFAULT_MODEL = 'gemini-3.1-flash-image';
    /** Offered in the console as a starting point; staff can change every word. */
    public const DEFAULT_PROMPT = 'A photorealistic photograph, as taken on a modern phone, for this theme: {subject}. '
        . 'Bright natural light, vivid colours, sharp focus, modern and cheerful. '
        . 'Upright picture that fills the whole frame from edge to edge, with the main subject in the middle. '
        . 'No text, no logos, no borders, and no phone or camera in the picture.';
    public static string $lastError = '';

    /** Key (decrypted) and model name in use. */
    public static function settings(): array
    {
        $g = Settings::get('gemini') ?? [];
        return [
            'key' => Settings::open((string) ($g['key'] ?? '')),
            'model' => (string) ($g['model'] ?? '') ?: self::DEFAULT_MODEL,
        ];
    }

    public static function isSetUp(): bool
    {
        return self::settings()['key'] !== '';
    }

    /**
     * Asks Gemini for one upright picture. Returns ['bytes' => ..., 'ext' => 'png'|'jpg'|'webp'],
     * or null with the reason in $lastError.
     */
    public static function picture(string $prompt): ?array
    {
        self::$lastError = '';
        $s = self::settings();
        if ($s['key'] === '') {
            self::$lastError = 'The Gemini API key has not been saved yet.';
            return null;
        }
        $base = rtrim((string) cfg('gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $ch = curl_init($base . '/models/' . rawurlencode($s['model']) . ':generateContent');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 150,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $s['key']],
            CURLOPT_POSTFIELDS => json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseModalities' => ['IMAGE'], 'imageConfig' => ['aspectRatio' => '9:16']],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $reply = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($reply === false) {
            self::$lastError = 'Google could not be reached: ' . $err;
            return null;
        }
        $j = json_decode((string) $reply, true);
        if ($code !== 200 || !is_array($j)) {
            $why = is_array($j) ? (string) ($j['error']['message'] ?? '') : '';
            self::$lastError = "Google refused the request (status $code)." . ($why !== '' ? ' ' . mb_substr($why, 0, 400) : '');
            return null;
        }
        $said = '';
        foreach ((array) ($j['candidates'][0]['content']['parts'] ?? []) as $part) {
            $img = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($img) && isset($img['data'])) {
                $bytes = base64_decode((string) $img['data'], true);
                $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                $ext = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
                if ($ext) return self::shrink($bytes, $ext);
            } elseif (isset($part['text'])) {
                $said .= ' ' . $part['text'];
            }
        }
        $reason = (string) ($j['promptFeedback']['blockReason'] ?? $j['candidates'][0]['finishReason'] ?? '');
        self::$lastError = 'Google did not send a picture back.'
            . ($reason !== '' && $reason !== 'STOP' ? " Reason given: $reason." : '')
            . (trim($said) !== '' ? ' It said: ' . mb_substr(trim($said), 0, 300) : '')
            . ' Try wording the description differently.';
        return null;
    }

    /**
     * Created and uploaded pictures are far larger than the page needs: bring them down to 1200 px tall.
     * A camera photo that is stored sideways (with a turn note in its data) is turned upright first.
     */
    public static function shrink(string $bytes, string $ext): array
    {
        $keep = ['bytes' => $bytes, 'ext' => $ext];
        if (!function_exists('imagecreatefromstring')) return $keep;
        $turn = 0;
        if ($ext === 'jpg') {
            if (!function_exists('exif_read_data')) return $keep; // cannot tell which way up it is: leave it as it is
            $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
            $turn = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        }
        if (!$turn && strlen($bytes) <= 900 * 1024) {
            $size = @getimagesizefromstring($bytes);
            if ($size && $size[1] <= 1200) return $keep;
        }
        @ini_set('memory_limit', '768M'); // a 48 megapixel photo needs about 200 MB while it is open
        $src = @imagecreatefromstring($bytes);
        if (!$src) return $keep;
        if ($turn) {
            $turned = imagerotate($src, $turn, 0);
            if ($turned) $src = $turned;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $nh = min($h, 1200);
        $nw = max(1, (int) round($w * $nh / $h));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        $webp = function_exists('imagewebp');
        $ok = $webp ? imagewebp($dst, null, 88) : imagepng($dst, null, 6);
        $out = (string) ob_get_clean();
        return $ok && $out !== '' ? ['bytes' => $out, 'ext' => $webp ? 'webp' : 'png'] : $keep;
    }

    /* ---------- drafts: created pictures waiting for a member of staff to accept them ---------- */

    public static function draftFile(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) return null;
        foreach (['png', 'jpg', 'webp'] as $ext) {
            $f = rtrim((string) cfg('storage'), '/') . '/phone-drafts/' . $slug . '.' . $ext;
            if (is_file($f)) return $f;
        }
        return null;
    }

    public static function saveDraft(string $slug, array $pic): void
    {
        $dir = Storage::dir('phone-drafts');
        self::dropDraft($slug);
        file_put_contents("$dir/$slug.{$pic['ext']}", $pic['bytes'], LOCK_EX);
        @chmod("$dir/$slug.{$pic['ext']}", 0640);
    }

    public static function dropDraft(string $slug): void
    {
        while ($f = self::draftFile($slug)) unlink($f);
    }

    /** Makes the draft the picture shown on the project page. */
    public static function useDraft(string $slug): bool
    {
        $f = self::draftFile($slug);
        if (!$f) return false;
        $dir = Storage::dir('phones');
        foreach (['png', 'jpg', 'webp'] as $old) @unlink("$dir/$slug.$old");
        return rename($f, "$dir/$slug." . pathinfo($f, PATHINFO_EXTENSION));
    }
}
