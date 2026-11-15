<?php
declare(strict_types=1);

/**
 * Reads a photo's metadata with exiftool (runs on this server) and decides whether it is
 * an untouched camera file from an allowed iPhone. Nothing here changes the file.
 */
final class PhotoCheck
{
    /** Full metadata as "Group:Tag" => value, or null when the file cannot be read. */
    public static function readMeta(string $file): ?array
    {
        $cmd = [cfg('exiftool', 'exiftool'), '-j', '-G1', '-struct', '-c', '%+.7f', '-d', '%Y-%m-%d %H:%M:%S', '-api', 'largefilesupport=1', '--', $file];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) return null;
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $rows = json_decode((string) $out, true);
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return null;
        $meta = [];
        foreach ($rows[0] as $k => $v) {
            if ($k === 'SourceFile' || str_starts_with($k, 'System:') || str_starts_with($k, 'ExifTool:')) continue;
            $meta[$k] = $v;
        }
        return $meta;
    }

    /**
     * HEIC files are a chain of boxes that each state their own length. If a box claims more
     * bytes than the file has, the upload was cut short. JPEG files are checked when decoded.
     */
    public static function isCutShort(string $file): bool
    {
        $size = (int) filesize($file);
        $fh = fopen($file, 'rb');
        if (!$fh) return true;
        $head = (string) fread($fh, 12);
        if (strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
            fclose($fh);
            return false;
        }
        $pos = 0;
        $bad = false;
        while ($pos < $size) {
            fseek($fh, $pos);
            $hdr = (string) fread($fh, 16);
            if (strlen($hdr) < 8) { $bad = true; break; }
            $len = unpack('N', $hdr)[1];
            if ($len === 1) {
                if (strlen($hdr) < 16) { $bad = true; break; }
                $len = unpack('J', substr($hdr, 8, 8))[1];
            } elseif ($len === 0) {
                $len = $size - $pos;
            }
            if ($len < 8 || $pos + $len > $size) { $bad = true; break; }
            $pos += $len;
        }
        fclose($fh);
        return $bad;
    }

    /** First value of a tag, whatever group it is in. */
    public static function tag(array $meta, string $name): ?string
    {
        foreach ($meta as $k => $v) {
            if (substr($k, (int) strrpos($k, ':') + 1) === $name && !is_array($v)) return trim((string) $v);
        }
        return null;
    }

    public static function hasGroup(array $meta, string $group): bool
    {
        foreach ($meta as $k => $_) {
            if (str_starts_with($k, $group . ':')) return true;
        }
        return false;
    }

    public static function modelAllowed(string $model, int $minGeneration, array $extraNames): bool
    {
        if (preg_match('/^iPhone (\d{2,})/', $model, $m)) return (int) $m[1] >= $minGeneration;
        foreach ($extraNames as $x) {
            $x = trim($x);
            if ($x !== '' && (strcasecmp($x, $model) === 0 || stripos($model, $x . ' ') === 0)) return true;
        }
        return false;
    }

    /** The values the app keeps in its own columns. */
    public static function facts(array $meta): array
    {
        $lat = self::tag($meta, 'GPSLatitude');
        $lng = self::tag($meta, 'GPSLongitude');
        foreach ($meta as $k => $v) {
            if ($k === 'Composite:GPSLatitude') $lat = (string) $v;
            if ($k === 'Composite:GPSLongitude') $lng = (string) $v;
        }
        if (is_numeric($lat) && (float) $lat > 0 && stripos((string) self::tag($meta, 'GPSLatitudeRef'), 'S') === 0) $lat = (string) -(float) $lat;
        if (is_numeric($lng) && (float) $lng > 0 && stripos((string) self::tag($meta, 'GPSLongitudeRef'), 'W') === 0) $lng = (string) -(float) $lng;
        $taken = self::tag($meta, 'DateTimeOriginal');
        return [
            'file_type' => strtoupper((string) self::tag($meta, 'FileType')),
            'mime' => (string) self::tag($meta, 'MIMEType'),
            'make' => (string) self::tag($meta, 'Make'),
            'model' => (string) self::tag($meta, 'Model'),
            'lens' => (string) self::tag($meta, 'LensModel'),
            'software' => (string) self::tag($meta, 'Software'),
            'taken_at' => ($taken && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/', $taken, $m)) ? $m[0] : null,
            'exif_w' => (int) self::tag($meta, 'ExifImageWidth'),
            'exif_h' => (int) self::tag($meta, 'ExifImageHeight'),
            'gps_lat' => is_numeric($lat) ? round((float) $lat, 7) : null,
            'gps_lng' => is_numeric($lng) ? round((float) $lng, 7) : null,
        ];
    }

    /**
     * Device and "is this the original" rules. Returns null when the photo passes,
     * otherwise [code, message for the person].
     */
    public static function evaluate(array $meta, array $project): ?array
    {
        $f = self::facts($meta);
        $min = (int) $project['min_iphone'];
        $want = "iPhone $min or newer";
        $how = 'Please choose the original photo from the Photos app on your iPhone. Do not send it through WhatsApp, email or another app first.';

        if ($f['file_type'] === 'PNG' || stripos((string) self::tag($meta, 'UserComment'), 'screenshot') !== false) {
            return ['screenshot', 'This looks like a screenshot, not a photo taken with the camera. Screenshots are not accepted.'];
        }
        if (!in_array($f['file_type'], ['JPEG', 'HEIC', 'HEIF'], true)) {
            return ['format', 'This is not a camera photo file (HEIC or JPEG). Screenshots and other file types are not accepted.'];
        }
        if (strcasecmp($f['make'], 'Apple') !== 0 || $f['model'] === '') {
            if ($f['model'] !== '' || $f['make'] !== '') {
                return ['wrong_device', 'This photo was taken on ' . trim($f['make'] . ' ' . $f['model']) . ". Only photos taken on $want are accepted."];
            }
            return ['no_camera_data', "This file has no camera information, so we cannot confirm it was taken on $want. $how"];
        }
        $extra = preg_split('/\R/', (string) $project['extra_models']) ?: [];
        if (!self::modelAllowed($f['model'], $min, $extra)) {
            return ['wrong_device', "This photo was taken on {$f['model']}. Only photos taken on $want are accepted."];
        }
        if ($f['taken_at'] === null) {
            return ['no_camera_data', "This file is missing the date it was taken, so it is not an original camera photo. $how"];
        }

        // Signs that the file was saved again by an editing app.
        $edited = 'This photo looks edited or re-saved by another app. ' . $how;
        if ($f['software'] !== '' && !preg_match('/^\d+(\.\d+){0,3}$/', $f['software'])) {
            return ['edited', 'This photo was saved by "' . mb_substr($f['software'], 0, 40) . '", so it is not the original camera file. ' . $how];
        }
        // iPhone 16 shipped with iOS 18, so an older system version on a 16 or newer does not add up.
        if ($f['software'] !== '' && (int) $f['software'] < 18 && preg_match('/^iPhone (\d{2,})/', $f['model'], $g) && (int) $g[1] >= 16) {
            return ['edited', $edited];
        }
        $tool = self::tag($meta, 'CreatorTool');
        if ($tool !== null && $tool !== '' && !preg_match('/^(iOS ?)?\d+(\.\d+){0,3}$/i', $tool)) {
            return ['edited', 'This photo was saved by "' . mb_substr($tool, 0, 40) . '", so it is not the original camera file. ' . $how];
        }
        // Marks left by editing software. (Apple itself writes XMP-photoshop:DateCreated, so that one is fine.)
        foreach ($meta as $k => $_) {
            if (str_starts_with($k, 'XMP-crs:') || str_starts_with($k, 'XMP-xmpMM:History')
                || (str_starts_with($k, 'XMP-photoshop:') && $k !== 'XMP-photoshop:DateCreated')) {
                return ['edited', $edited];
            }
        }
        if ($f['lens'] !== '' && stripos($f['lens'], $f['model'] . ' ') !== 0) {
            return ['edited', $edited];
        }
        $hostComputer = self::tag($meta, 'HostComputer');
        if ($hostComputer !== null && $hostComputer !== '' && strcasecmp($hostComputer, $f['model']) !== 0) {
            return ['edited', $edited];
        }
        if ((int) $project['strict_original'] === 1) {
            if ($f['lens'] === '' || !self::hasGroup($meta, 'Apple')) {
                return ['edited', 'This file is missing the camera details an original iPhone photo always has. ' . $how];
            }
        }
        return null;
    }

    /** Size rules, checked after the picture has been decoded. */
    public static function evaluateSize(array $facts, int $width, int $height, array $project): ?array
    {
        $mp = $width * $height / 1e6;
        if ($mp < (float) $project['min_megapixels']) {
            return ['resized', 'This photo is smaller than a full camera photo (' . number_format($mp, 1) . ' megapixels), so it was resized or cropped. Please upload the original.'];
        }
        if ($facts['exif_w'] > 0 && $facts['exif_h'] > 0) {
            $a = [$facts['exif_w'], $facts['exif_h']];
            $b = [$width, $height];
            sort($a);
            sort($b);
            if (abs($a[0] - $b[0]) > 16 || abs($a[1] - $b[1]) > 16) {
                return ['resized', 'This photo was resized or cropped after it was taken. Please upload the original.'];
            }
        }
        return null;
    }
}
