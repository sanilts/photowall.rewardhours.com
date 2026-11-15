<?php
declare(strict_types=1);

/** Where files live. Originals are stored byte for byte as uploaded and never rewritten. */
final class Storage
{
    public static function dir(string $kind, int $projectId = 0): string
    {
        $d = rtrim((string) cfg('storage'), '/') . '/' . $kind . ($projectId ? '/' . $projectId : '');
        if (!is_dir($d) && !@mkdir($d, 0770, true) && !is_dir($d)) {
            throw new RuntimeException('Cannot create storage folder ' . $d);
        }
        return $d;
    }

    public static function original(array $photo): string
    {
        return self::dir('originals', (int) $photo['project_id']) . '/' . $photo['token'] . '.' . $photo['ext'];
    }

    public static function preview(array $photo): string
    {
        return self::dir('previews', (int) $photo['project_id']) . '/' . $photo['token'] . '.jpg';
    }

    public static function thumb(array $photo): string
    {
        return self::dir('thumbs', (int) $photo['project_id']) . '/' . $photo['token'] . '.jpg';
    }

    public static function removeFiles(array $photo): void
    {
        if (($photo['store'] ?? 'local') === 'spaces') {
            if (!Spaces::delete((string) $photo['store_key'])) error_log('Photowall: could not delete ' . $photo['store_key'] . ' from the bucket: ' . Spaces::$lastError);
        }
        foreach ([self::original($photo), self::preview($photo), self::thumb($photo)] as $f) {
            if (is_file($f)) @unlink($f);
        }
    }

    /** Sends the untouched original: from this server's disk, or through a private link to the bucket that expires in 5 minutes. */
    public static function sendOriginal(array $photo): never
    {
        if (($photo['store'] ?? 'local') === 'spaces') {
            header('Cache-Control: no-store');
            header('Location: ' . Spaces::link((string) $photo['store_key'], 300, self::downloadName($photo), (string) $photo['mime']), true, 302);
            exit;
        }
        self::send(self::original($photo), (string) $photo['mime'], self::downloadName($photo), false);
    }

    /**
     * A local copy of the original for building a ZIP. Returns [path, isTemporary];
     * path is null when the file cannot be fetched. Delete temporary copies after use.
     */
    public static function localOriginal(array $photo): array
    {
        if (($photo['store'] ?? 'local') !== 'spaces') return [is_file(self::original($photo)) ? self::original($photo) : null, false];
        $tmp = self::dir('tmp') . '/dl-' . bin2hex(random_bytes(8));
        return Spaces::get((string) $photo['store_key'], $tmp) ? [$tmp, true] : [null, false];
    }

    /** Name given to a downloaded original: keeps the format, hides the person's own file name. */
    public static function downloadName(array $photo): string
    {
        return image_id($photo['id']) . '_' . contributor_id($photo['respondent_id']) . '.' . $photo['ext'];
    }

    public static function send(string $file, string $mime, ?string $downloadName = null, bool $cache = true): never
    {
        if (!is_file($file)) fail_page(404, 'This file is no longer available.');
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($cache ? 'private, max-age=86400' : 'no-store'));
        if ($downloadName !== null) header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        readfile($file);
        exit;
    }
}
