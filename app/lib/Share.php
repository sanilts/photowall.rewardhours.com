<?php
declare(strict_types=1);

/**
 * The picture shown when a link to the site is shared (WhatsApp, Teams, LinkedIn and so on): 1200 by 630.
 * When at least four example photos are set in the console, they are placed behind a ready-made frame that
 * carries the logo and the words. Otherwise, or if anything goes wrong, a ready-made picture is used.
 * Never a contributor's photo: only the example photos staff chose for the public page.
 */
final class Share
{
    /** Top left corner of each of the four windows in the frame, and their size. */
    private const WINDOWS = [[628, 49], [902, 49], [628, 323], [902, 323]];
    private const SIZE = 258;

    /** The first four example photos, as files. */
    private static function pictures(): array
    {
        $files = [];
        foreach (picture_subjects() as $label) {
            $f = phone_picture_file(phone_slug($label));
            if ($f !== null) $files[] = $f;
            if (count($files) === 4) break;
        }
        return $files;
    }

    /** Changes whenever the picture would: used in the address so previews pick up a new one. */
    public static function version(): string
    {
        $parts = '';
        foreach (self::pictures() as $f) $parts .= $f . '|' . @filemtime($f) . ';';
        return substr(sha1($parts . '|' . @filemtime(PW_ROOT . '/public/assets/share-frame.png')), 0, 12);
    }

    /** The JPEG file to send. */
    public static function file(): string
    {
        $ready = PW_ROOT . '/public/assets/share.jpg';
        $pics = self::pictures();
        if (count($pics) < 4 || !function_exists('imagecreatetruecolor')) return $ready;
        $dir = rtrim((string) cfg('storage'), '/') . '/share';
        $out = $dir . '/share-' . self::version() . '.jpg';
        if (is_file($out)) return $out;
        try {
            if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return $ready;
            $canvas = imagecreatetruecolor(1200, 630);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 10, 30, 60));
            foreach ($pics as $i => $f) {
                $src = @imagecreatefromstring((string) file_get_contents($f));
                if (!$src) return $ready;
                $w = imagesx($src);
                $h = imagesy($src);
                $side = min($w, $h); // the middle square of the photo fills the window
                imagecopyresampled($canvas, $src, self::WINDOWS[$i][0], self::WINDOWS[$i][1], intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
                imagedestroy($src);
            }
            $frame = @imagecreatefrompng(PW_ROOT . '/public/assets/share-frame.png');
            if (!$frame) return $ready;
            imagealphablending($canvas, true);
            imagecopy($canvas, $frame, 0, 0, 0, 0, 1200, 630);
            foreach (glob($dir . '/share-*.jpg') ?: [] as $old) @unlink($old);
            $ok = imagejpeg($canvas, $out, 86);
            imagedestroy($frame);
            imagedestroy($canvas);
            return $ok && is_file($out) ? $out : $ready;
        } catch (Throwable $e) {
            error_log('Photowall share picture: ' . $e->getMessage());
            return $ready;
        }
    }
}
