<?php
declare(strict_types=1);

/**
 * The tracker of a project: one line for every photo that reached us, kept or not,
 * with totals by category and by contributor. Used by the console page and its CSV downloads.
 */
final class Tracker
{
    public const STATUS = ['accepted' => 'Accepted', 'review' => 'In review', 'rejected' => 'Rejected'];

    /** Photos that were kept, and uploads that failed a check, as one list. */
    private const ROWS = "(
        SELECT 'photo' AS kind, ph.id, ph.orig_name, ph.category, ph.device_model, ph.taken_at, ph.respondent_id, ph.created_at,
               CASE ph.status WHEN 'approved' THEN 'accepted' WHEN 'pending' THEN 'review' ELSE 'rejected' END AS status,
               CASE WHEN ph.status = 'rejected' THEN ph.reject_reason ELSE '' END AS reason,
               CASE WHEN ph.status = 'rejected' THEN 'Staff' ELSE '' END AS rejected_by
          FROM photos ph WHERE ph.project_id = ? AND ph.status <> 'removed'
        UNION ALL
        SELECT 'turned', x.id, x.orig_name, x.category, x.device_model, x.taken_at, x.respondent_id, x.created_at,
               'rejected', x.message, 'Automatic check'
          FROM rejections x WHERE x.project_id = ?
    ) t LEFT JOIN respondents r ON r.id = t.respondent_id";

    /** The WHERE part and its values for the filters in use. */
    private static function where(array $f): array
    {
        $sql = '1 = 1';
        $params = [];
        if (isset(self::STATUS[$f['status'] ?? ''])) {
            $sql .= ' AND t.status = ?';
            $params[] = $f['status'];
        }
        if (($f['category'] ?? '') !== '') {
            $sql .= ' AND t.category = ?';
            $params[] = $f['category'] === '-' ? '' : $f['category'];
        }
        if ((int) ($f['person'] ?? 0) > 0) {
            $sql .= ' AND t.respondent_id = ?';
            $params[] = (int) $f['person'];
        }
        return [$sql, $params];
    }

    public static function count(array $p, array $f): int
    {
        [$w, $params] = self::where($f);
        return (int) Db::val('SELECT COUNT(*) FROM ' . self::ROWS . " WHERE $w", [$p['id'], $p['id'], ...$params]);
    }

    /** Newest first. $limit 0 means every row. */
    public static function rows(array $p, array $f, int $limit = 0, int $offset = 0): array
    {
        [$w, $params] = self::where($f);
        $sql = 'SELECT t.*, r.country FROM ' . self::ROWS . " WHERE $w ORDER BY t.created_at DESC, t.id DESC";
        if ($limit > 0) $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        return array_map([self::class, 'present'], Db::all($sql, [$p['id'], $p['id'], ...$params]));
    }

    /** Adds the IDs and wording people read. */
    private static function present(array $r): array
    {
        $r['image_id'] = $r['kind'] === 'photo' ? image_id($r['id']) : turned_id($r['id']);
        $r['contributor_id'] = contributor_id($r['respondent_id']);
        $r['status_label'] = self::STATUS[$r['status']];
        $r['device_model'] = preg_replace('/^Apple\s+/i', '', (string) $r['device_model']); // turned-away uploads store maker and model together
        $r['category_label'] = $r['category'] !== '' ? $r['category'] : 'Not set';
        return $r;
    }

    private const SUMS = "COUNT(*) AS total, SUM(t.status = 'accepted') AS accepted, SUM(t.status = 'review') AS review, SUM(t.status = 'rejected') AS rejected";

    public static function byCategory(array $p): array
    {
        $rows = Db::all('SELECT t.category, ' . self::SUMS . ' FROM ' . self::ROWS . ' GROUP BY t.category ORDER BY (t.category = \'\'), total DESC, t.category', [$p['id'], $p['id']]);
        foreach ($rows as &$r) $r['category_label'] = $r['category'] !== '' ? $r['category'] : 'Not set';
        return $rows;
    }

    public static function byContributor(array $p): array
    {
        $rows = Db::all('SELECT t.respondent_id, MAX(r.country) AS country, ' . self::SUMS . ' FROM ' . self::ROWS . ' GROUP BY t.respondent_id ORDER BY accepted DESC, total DESC, t.respondent_id', [$p['id'], $p['id']]);
        $reward = reward($p);
        foreach ($rows as &$r) {
            $r['contributor_id'] = contributor_id($r['respondent_id']);
            $r['earned'] = $reward ? reward_earned($reward, (int) $r['accepted']) : '';
        }
        return $rows;
    }

    /** Sends one of the three tables as a CSV file. */
    public static function sendCsv(array $p, string $which, array $f): never
    {
        if ($which === 'categories') {
            $head = ['category', 'accepted', 'in_review', 'rejected', 'total'];
            $lines = array_map(static fn($r) => [$r['category_label'], $r['accepted'], $r['review'], $r['rejected'], $r['total']], self::byCategory($p));
        } elseif ($which === 'contributors') {
            $head = ['contributor_id', 'country', 'accepted', 'in_review', 'rejected', 'total', 'reward_earned'];
            $lines = array_map(static fn($r) => [$r['contributor_id'], $r['country'], $r['accepted'], $r['review'], $r['rejected'], $r['total'], $r['earned']], self::byContributor($p));
        } else {
            $which = 'photos';
            $head = ['image_id', 'file_name', 'category', 'iphone_model', 'date_taken', 'country', 'contributor_id', 'status', 'reason', 'rejected_by', 'uploaded_at'];
            $lines = array_map(static fn($r) => [$r['image_id'], $r['orig_name'], $r['category_label'], $r['device_model'], $r['taken_at'], $r['country'],
                $r['contributor_id'], $r['status_label'], $r['reason'], $r['rejected_by'], $r['created_at']], self::rows($p, $f));
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tracker-' . $which . '-' . $p['slug'] . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head);
        foreach ($lines as $line) {
            // a cell that starts like a formula is written as text, so a spreadsheet does not run it
            fputcsv($out, array_map(static fn($v) => preg_match('/^[=+@]|^-[^\d.]/', (string) $v) ? "'" . $v : $v, $line));
        }
        exit;
    }
}
