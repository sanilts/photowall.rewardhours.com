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
        foreach ([self::original($photo), self::preview($photo), self::thumb($photo)] as $f) {
            if (is_file($f)) @unlink($f);
        }
    }

    /** Name given to a downloaded original: keeps the format, hides the person's own file name. */
    public static function downloadName(array $photo): string
    {
        return sprintf('P%06d_R%05d.%s', $photo['id'], $photo['respondent_id'], $photo['ext']);
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
