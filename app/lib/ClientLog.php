<?php
declare(strict_types=1);

/**
 * What client people do in the client portal: sign-ins (when and from where), galleries opened,
 * photos viewed, photos downloaded and download requests. Staff read it in the console under Clients.
 *
 * client_events holds one line per action; client_photo_log holds one line per photo viewed or downloaded,
 * so "how many different photos" can be counted and a single photo can show who took it.
 */
final class ClientLog
{
    public const ACTIONS = [
        'login' => 'Signed in', 'gallery' => 'Opened a gallery page', 'view' => 'Viewed a photo',
        'download' => 'Downloaded a photo', 'zip' => 'Downloaded photos (ZIP)', 'csv' => 'Downloaded the photo information sheet',
        'request' => 'Asked to download', 'logout' => 'Signed out',
    ];

    /** How long an approved download request can be used. */
    public const VALID_DAYS = 7;

    /** Two-letter country of the visitor's connection, looked up once per session and address ('' when unknown). */
    public static function country(): string
    {
        $ip = client_ip();
        $c = $_SESSION['client_where'] ?? null;
        if (is_array($c) && $c[0] === $ip) return $c[1];
        $r = Guard::ip($ip, true);
        $cc = is_array($r) && !isset($r['_failed']) ? substr((string) ($r['country_code'] ?? ''), 0, 2) : '';
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['client_where'] = [$ip, $cc];
        return $cc;
    }

    /**
     * Record one action. $photoIds are the photos it touched; $kind ('view' or 'download') says how.
     * Never stops the page: a failure to write the log is reported to the error log only.
     */
    public static function add(array $me, string $action, ?int $projectId = null, int $photos = 0, string $detail = '', array $photoIds = [], string $kind = ''): int
    {
        try {
            $id = Db::insert(
                'INSERT INTO client_events (client_id, client_user_id, project_id, action, photos, detail, ip, country, agent, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$me['client_id'], $me['id'], $projectId, $action, $photos, mb_substr($detail, 0, 255), client_ip(), self::country(),
                    mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), now()]
            );
            if ($kind !== '') {
                foreach (array_chunk(array_values(array_unique(array_map('intval', $photoIds))), 500) as $chunk) {
                    $rows = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?)'));
                    $args = [];
                    foreach ($chunk as $pid) array_push($args, $id, $me['id'], $pid, $kind, now());
                    Db::q("INSERT INTO client_photo_log (event_id, client_user_id, photo_id, kind, created_at) VALUES $rows", $args);
                }
            }
            return $id;
        } catch (Throwable $e) {
            error_log('Photowall client log: ' . $e->getMessage());
            return 0;
        }
    }

    /** One line per client person with their totals, newest sign-in first. */
    public static function people(): array
    {
        return Db::all("SELECT u.id, u.name, u.email, u.active, u.last_login_at, c.name AS client_name,
            (SELECT COUNT(*) FROM client_events e WHERE e.client_user_id = u.id AND e.action = 'login') AS logins,
            (SELECT CONCAT(e.ip, '|', e.country, '|', e.agent) FROM client_events e WHERE e.client_user_id = u.id AND e.action = 'login' ORDER BY e.id DESC LIMIT 1) AS last_from,
            (SELECT COUNT(*) FROM client_events e WHERE e.client_user_id = u.id AND e.action = 'gallery') AS galleries,
            (SELECT COUNT(*) FROM client_photo_log l WHERE l.client_user_id = u.id AND l.kind = 'view') AS views,
            (SELECT COUNT(DISTINCT l.photo_id) FROM client_photo_log l WHERE l.client_user_id = u.id AND l.kind = 'view') AS viewed,
            (SELECT COUNT(*) FROM client_photo_log l WHERE l.client_user_id = u.id AND l.kind = 'download') AS downloads,
            (SELECT COUNT(DISTINCT l.photo_id) FROM client_photo_log l WHERE l.client_user_id = u.id AND l.kind = 'download') AS downloaded,
            (SELECT COUNT(*) FROM download_requests r WHERE r.client_user_id = u.id) AS requests
            FROM client_users u JOIN clients c ON c.id = u.client_id
            ORDER BY u.last_login_at IS NULL, u.last_login_at DESC, u.name");
    }

    /** WHERE clause and values for the timeline filters (person, action, from and to day). */
    public static function filter(array $f): array
    {
        $where = '1 = 1';
        $args = [];
        if (($f['user'] ?? 0) > 0) { $where .= ' AND e.client_user_id = ?'; $args[] = (int) $f['user']; }
        if (isset(self::ACTIONS[$f['action'] ?? ''])) { $where .= ' AND e.action = ?'; $args[] = $f['action']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? ''))) { $where .= ' AND e.created_at >= ?'; $args[] = $f['from'] . ' 00:00:00'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? ''))) { $where .= ' AND e.created_at <= ?'; $args[] = $f['to'] . ' 23:59:59'; }
        return [$where, $args];
    }

    public static function count(array $f): int
    {
        [$where, $args] = self::filter($f);
        return (int) Db::val("SELECT COUNT(*) FROM client_events e WHERE $where", $args);
    }

    public static function rows(array $f, int $limit, int $offset): array
    {
        [$where, $args] = self::filter($f);
        return Db::all("SELECT e.*, u.name, u.email, c.name AS client_name, p.name AS project_name
            FROM client_events e JOIN client_users u ON u.id = e.client_user_id JOIN clients c ON c.id = e.client_id
            LEFT JOIN projects p ON p.id = e.project_id
            WHERE $where ORDER BY e.id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $args);
    }

    /** The whole filtered timeline as a CSV download. */
    public static function sendCsv(array $f): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="client-activity-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['when', 'company', 'person', 'email', 'action', 'project', 'photos', 'detail', 'ip_address', 'country', 'browser']);
        for ($offset = 0; $rows = self::rows($f, 2000, $offset); $offset += 2000) {
            foreach ($rows as $r) {
                fputcsv($out, [$r['created_at'], $r['client_name'], $r['name'], $r['email'], self::ACTIONS[$r['action']] ?? $r['action'], (string) $r['project_name'],
                    $r['photos'], $r['detail'], $r['ip'], countries()[$r['country']] ?? $r['country'], agent_label($r['agent'])]);
            }
        }
        exit;
    }

    /** How often clients viewed and downloaded one photo. */
    public static function photo(int $photoId): array
    {
        $r = Db::one("SELECT SUM(kind = 'view') AS views, SUM(kind = 'download') AS downloads, MAX(CASE WHEN kind = 'download' THEN created_at END) AS last_download
            FROM client_photo_log WHERE photo_id = ?", [$photoId]) ?: [];
        return ['views' => (int) ($r['views'] ?? 0), 'downloads' => (int) ($r['downloads'] ?? 0), 'last_download' => $r['last_download'] ?? null];
    }
}
