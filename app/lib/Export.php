<?php
declare(strict_types=1);

/** Downloads for staff and clients: metadata sheet and ZIP of untouched originals. */
final class Export
{
    private const LIST_SQL = 'SELECT p.id, p.project_id, p.respondent_id, p.token, p.ext, p.mime, p.bytes, p.sha256, p.crc32, p.width, p.height,
        p.device_model, p.lens_model, p.software, p.taken_at, p.gps_lat, p.gps_lng, p.status, p.created_at, p.orig_name, p.category, p.store, p.store_key,
        r.full_name, r.email, r.phone, r.country
        FROM photos p JOIN respondents r ON r.id = p.respondent_id';

    /** Photos of a project, optionally only some ids. $statuses e.g. ['approved']. */
    public static function photos(int $projectId, array $statuses, ?array $ids = null): array
    {
        $sql = self::LIST_SQL . ' WHERE p.project_id = ? AND p.status IN (' . rtrim(str_repeat('?,', count($statuses)), ',') . ')';
        $params = [$projectId, ...$statuses];
        if ($ids !== null) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (!$ids) return [];
            $sql .= ' AND p.id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')';
            array_push($params, ...$ids);
        }
        return Db::all($sql . ' ORDER BY p.id', $params);
    }

    /** One row per photo. Clients get a respondent code and country, never name, email or phone. */
    public static function csv(array $photos, bool $withPerson): string
    {
        $head = ['file', 'image_id', 'category', 'contributor_id', 'country', 'device_model', 'lens_model', 'ios_version', 'taken_at',
            'width', 'height', 'megapixels', 'bytes', 'sha256', 'gps_latitude', 'gps_longitude', 'uploaded_at', 'status'];
        if ($withPerson) array_push($head, 'respondent_name', 'respondent_email', 'respondent_phone', 'original_file_name');
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $head);
        foreach ($photos as $p) {
            $row = [
                Storage::downloadName($p), image_id($p['id']), $p['category'], contributor_id($p['respondent_id']), $p['country'], $p['device_model'], $p['lens_model'],
                $p['software'], $p['taken_at'], $p['width'], $p['height'], number_format($p['width'] * $p['height'] / 1e6, 1, '.', ''),
                $p['bytes'], $p['sha256'], $p['gps_lat'], $p['gps_lng'], $p['created_at'], $p['status'],
            ];
            if ($withPerson) array_push($row, $p['full_name'], $p['email'], $p['phone'], $p['orig_name']);
            fputcsv($fh, array_map([self::class, 'safeCell'], $row));
        }
        rewind($fh);
        return (string) stream_get_contents($fh);
    }

    /** Stops a spreadsheet from treating text as a formula. */
    private static function safeCell(mixed $v): string
    {
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], '=+@') !== false) || preg_match('/^-[^\d.]/', $v) ? "'" . $v : $v;
    }

    public static function sendCsv(array $photos, bool $withPerson, string $name): never
    {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        echo self::csv($photos, $withPerson);
        exit;
    }

    /** ZIP with every original exactly as uploaded, plus metadata.csv and the full metadata as JSON. */
    public static function sendZip(array $project, array $photos, bool $withPerson): never
    {
        session_write_close();
        $zip = new ZipStream('photowall-' . $project['slug'] . '-' . date('Ymd-His') . '.zip');
        $zip->addString('metadata.csv', self::csv($photos, $withPerson));
        $full = tempnam(Storage::dir('tmp'), 'meta');
        $fh = fopen($full, 'wb');
        fwrite($fh, "[\n");
        $first = true;
        foreach ($photos as $p) {
            $meta = Db::val('SELECT meta_json FROM photos WHERE id = ?', [$p['id']]);
            fwrite($fh, ($first ? '' : ",\n") . json_encode(['file' => Storage::downloadName($p), 'metadata' => json_decode((string) $meta, true)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $first = false;
            [$path, $temp] = Storage::localOriginal($p);
            if ($path !== null) $zip->addFile('photos/' . Storage::downloadName($p), $path, (int) $p['crc32'], (int) $p['bytes']);
            if ($temp) @unlink($path);
        }
        fwrite($fh, "\n]\n");
        fclose($fh);
        $zip->addFile('metadata_full.json', $full);
        @unlink($full);
        $zip->finish();
        exit;
    }
}
