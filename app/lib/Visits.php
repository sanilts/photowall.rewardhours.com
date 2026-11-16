<?php
declare(strict_types=1);

/**
 * Counts visits to the contributor site and the client portal, on our own server and without cookies.
 *
 * One line per page shown. A visitor is told apart for one day by a code worked out from the date, the
 * connection address and the browser; the code changes every day and cannot be turned back into either,
 * so nobody is followed from day to day. The address itself is kept only until its country has been
 * looked up (a few minutes, by cron), then blanked. Search engines, link previews and other robots are left out.
 */
final class Visits
{
    private const ROBOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|skype|teams|slack|discord|embedly|curl|wget|python|java\/|okhttp|go-http|httpclient|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|scan|validator|feed|archive|phantom|axios|node-fetch|libwww|ruby|perl|postman|insomnia/i';

    /** How long the lines are kept. */
    public const KEEP_DAYS = 400;

    /** Record the page being shown. Never stops the page. */
    public static function record(string $area): void
    {
        try {
            if (!in_array($area, ['respondent', 'client'], true) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || http_response_code() !== 200) return;
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($ua === '' || preg_match(self::ROBOTS, $ua)) return;
            if (stripos((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''), 'prefetch') !== false) return;
            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $path = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
            $path = (string) preg_replace('#^/photo/[A-Za-z0-9]+#', '/photo/*', $path); // one line for all photos, not one per photo
            $path = (string) preg_replace('#^/projects/\d+#', '/projects/*', $path);
            $project = null;
            if ($area === 'respondent' && preg_match('#^/p/([a-z0-9-]+)#', $path, $m)) $project = Db::val('SELECT id FROM projects WHERE slug = ?', [$m[1]]) ?: null;
            $ip = client_ip();
            // the country is free when sign-in or the client log has already looked this connection up
            $country = '';
            foreach ([$_SESSION['ipcheck'] ?? null, $_SESSION['client_where'] ?? null] as $known) {
                if (is_array($known) && isset($known['res']['country_code']) && ($known['ip'] ?? '') === $ip) $country = (string) $known['res']['country_code'];
                if (is_array($known) && isset($known[1]) && ($known[0] ?? '') === $ip) $country = (string) $known[1];
            }
            $ref = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
            $ref = (string) preg_replace('/^www\./', '', $ref);
            $own = array_map('strtolower', array_values((array) cfg('hosts', [])));
            if (in_array($ref, $own, true)) $ref = '';
            $clean = static fn(string $v, int $n): string => mb_substr((string) preg_replace('/[^\p{L}\p{N} ._-]/u', '', $v), 0, $n);
            Db::q('INSERT INTO visits (day, at, area, path, project_id, visitor, signed_in, referrer, source, campaign, country, device, browser, ip) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                date('Y-m-d'), now(), $area, mb_substr($path, 0, 190), $project,
                substr(hash_hmac('sha256', date('Y-m-d') . '|' . $ip . '|' . $ua, 'pw-visits|' . cfg('secret')), 0, 16),
                empty($_SESSION['rid']) && empty($_SESSION['client_user_id']) ? 0 : 1,
                mb_substr($ref, 0, 120), $clean((string) ($_GET['utm_source'] ?? ''), 60), $clean((string) ($_GET['utm_campaign'] ?? ''), 80),
                substr($country, 0, 2), self::device($ua), self::browser($ua),
                $country === '' && $ip !== '' && Guard::settings()['ip_key'] !== '' ? $ip : null,
            ]);
        } catch (Throwable $e) {
            error_log('Photowall visits: ' . $e->getMessage());
        }
    }

    public static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) return 'Tablet';
        return preg_match('/iPhone|iPod|Android|Mobile|Windows Phone/i', $ua) ? 'Phone' : 'Computer';
    }

    public static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox', str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari', default => 'Other',
        };
    }

    /**
     * Run by cron: fill in the country of new lines, then blank their connection addresses.
     * Addresses older than an hour are blanked whether or not a country was found.
     */
    public static function resolve(int $max = 150): int
    {
        $done = 0;
        if (Guard::settings()['ip_key'] !== '') {
            foreach (Db::all('SELECT ip FROM visits WHERE ip IS NOT NULL GROUP BY ip ORDER BY MIN(id) LIMIT ' . (int) $max) as $row) {
                $r = Guard::ip((string) $row['ip'], true);
                $cc = is_array($r) && !isset($r['_failed']) ? substr((string) ($r['country_code'] ?? ''), 0, 2) : null;
                if ($cc === null) continue; // the service did not answer: try again next time, within the hour
                Db::q('UPDATE visits SET country = ?, ip = NULL WHERE ip = ?', [$cc, $row['ip']]);
                $done++;
            }
        }
        Db::q('UPDATE visits SET ip = NULL WHERE ip IS NOT NULL AND at < ?', [date('Y-m-d H:i:s', time() - 3600)]);
        Db::q('DELETE FROM visits WHERE day < ?', [date('Y-m-d', time() - self::KEEP_DAYS * 86400)]);
        return $done;
    }

    /** WHERE clause for the console filters: number of days, which site, which project. */
    private static function where(array $f): array
    {
        $where = 'day >= ? AND area = ?';
        $args = [date('Y-m-d', time() - (max(1, (int) $f['days']) - 1) * 86400), $f['area'] === 'client' ? 'client' : 'respondent'];
        if (($f['project'] ?? 0) > 0) { $where .= ' AND project_id = ?'; $args[] = (int) $f['project']; }
        return [$where, $args];
    }

    /** Totals for the chosen days, and for today. */
    public static function totals(array $f): array
    {
        [$w, $a] = self::where($f);
        $t = Db::one("SELECT COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visitors FROM visits WHERE $w", $a) ?: [];
        $d = Db::one("SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE $w AND day = ?", [...$a, date('Y-m-d')]) ?: [];
        return ['views' => (int) ($t['views'] ?? 0), 'visitors' => (int) ($t['visitors'] ?? 0), 'today_views' => (int) ($d['views'] ?? 0), 'today_visitors' => (int) ($d['visitors'] ?? 0)];
    }

    /** One entry for every day of the range, oldest first, including days with nobody. */
    public static function byDay(array $f): array
    {
        [$w, $a] = self::where($f);
        $have = [];
        foreach (Db::all("SELECT day, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE $w GROUP BY day", $a) as $r) $have[$r['day']] = $r;
        $out = [];
        for ($i = max(1, (int) $f['days']) - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', time() - $i * 86400);
            $out[] = ['day' => $day, 'visitors' => (int) ($have[$day]['visitors'] ?? 0), 'views' => (int) ($have[$day]['views'] ?? 0)];
        }
        return $out;
    }

    /** Visitors and page views grouped by one column, most visitors first. */
    public static function top(array $f, string $column, int $limit = 10): array
    {
        if (!in_array($column, ['path', 'country', 'device', 'browser'], true)) return [];
        [$w, $a] = self::where($f);
        return Db::all("SELECT $column AS label, COUNT(*) AS views, COUNT(DISTINCT day, visitor) AS visitors FROM visits WHERE $w GROUP BY $column ORDER BY visitors DESC, views DESC LIMIT " . (int) $limit, $a);
    }

    /** Where visitors came from: a campaign tag first, else the site that linked here. '' is everyone else. */
    public static function sources(array $f, int $limit = 10): array
    {
        [$w, $a] = self::where($f);
        $rows = Db::all("SELECT CASE WHEN source <> '' THEN source ELSE referrer END AS label, MAX(source <> '') AS tagged, COUNT(DISTINCT day, visitor) AS visitors
            FROM visits WHERE $w AND (source <> '' OR referrer <> '') GROUP BY label ORDER BY visitors DESC LIMIT " . (int) $limit, $a);
        $known = (int) Db::val("SELECT COUNT(DISTINCT day, visitor) FROM visits WHERE $w AND (source <> '' OR referrer <> '')", $a);
        return [$rows, max(0, self::totals($f)['visitors'] - $known)];
    }

    /** For one project: how many visitors reached each step, from the project page to the upload page. */
    public static function steps(array $f, string $slug): array
    {
        [$w, $a] = self::where($f);
        $n = static fn(string $path): int => (int) Db::val("SELECT COUNT(DISTINCT day, visitor) FROM visits WHERE $w AND path = ?", [...$a, $path]);
        return ['Opened the project page' => $n("/p/$slug"), 'Asked for a sign-in code' => $n("/p/$slug/code"), 'Reached the upload page' => $n("/p/$slug/upload")];
    }

    /** Daily numbers as a CSV download. */
    public static function sendCsv(array $f): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="visits-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['day', 'visitors', 'page_views']);
        foreach (self::byDay($f) as $d) fputcsv($out, [$d['day'], $d['visitors'], $d['views']]);
        exit;
    }
}
