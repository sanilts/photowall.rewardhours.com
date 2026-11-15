<?php
declare(strict_types=1);

function cfg(string $key, mixed $default = null): mixed
{
    $v = $GLOBALS['PW_CFG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Which part of the app this request is for, decided by the hostname. */
function area(): ?string
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    foreach ((array) cfg('hosts', []) as $area => $h) {
        if ($host === strtolower((string) $h)) return $area;
    }
    return null;
}

function area_url(string $area, string $path = '/'): string
{
    return (cfg('https', true) ? 'https://' : 'http://') . cfg('hosts.' . $area) . $path;
}

function start_session(string $area): void
{
    session_name('pw_' . $area);
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => (bool) cfg('https', true),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '43200');
    // Own session folder: the system's clean-up job would otherwise sign people out after about 24 minutes.
    $dir = rtrim((string) cfg('storage'), '/') . '/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_probability', '0'); // bin/cron.php removes old sessions
    }
    session_start();
    $idle = $area === 'respondent' ? 43200 : 14400;
    if (isset($_SESSION['seen']) && time() - (int) $_SESSION['seen'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (cfg('https', true)) header('Strict-Transport-Security: max-age=31536000');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(20));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        fail_page(403, 'This page expired. Please go back, reload the page and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Render a page inside the shared layout. */
function view(string $name, array $vars = []): never
{
    $vars += ['title' => 'Photowall', 'nav' => []];
    extract($vars, EXTR_SKIP);
    ob_start();
    require PW_ROOT . '/app/views/' . $name . '.php';
    $content = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    require PW_ROOT . '/app/views/shared/layout.php';
    exit;
}

function fail_page(int $code, string $message): never
{
    http_response_code($code);
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        json_out(['ok' => false, 'message' => $message], $code);
    }
    view('shared/error', ['title' => 'Something went wrong', 'message' => $message, 'code' => $code]);
}

/** Simple counter: true while the caller is under $max hits in $seconds. */
function throttle(string $bucket, int $max, int $seconds): bool
{
    $bucket = substr($bucket, 0, 190);
    $row = Db::one('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [$bucket]);
    if (!$row || strtotime($row['reset_at']) <= time()) {
        Db::q('REPLACE INTO rate_limits (bucket, hits, reset_at) VALUES (?, 1, ?)', [$bucket, date('Y-m-d H:i:s', time() + $seconds)]);
        return true;
    }
    if ((int) $row['hits'] >= $max) return false;
    Db::q('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
    return true;
}

function audit(string $action, string $detail = ''): void
{
    $who = $_SESSION['who'] ?? ['type' => 'system', 'id' => 0, 'label' => ''];
    Db::q(
        'INSERT INTO audit_log (actor_type, actor_id, actor_label, action, detail, ip, created_at) VALUES (?,?,?,?,?,?,?)',
        [$who['type'], $who['id'], $who['label'], $action, mb_substr($detail, 0, 500), client_ip(), now()]
    );
}

function clean_email(string $raw): ?string
{
    $email = strtolower(trim($raw));
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    return $email;
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function human_bytes(int|float $b): string
{
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
        if ($b < 1024 || $u === 'TB') return ($u === 'B' ? (string) $b : number_format($b, 1)) . ' ' . $u;
        $b /= 1024;
    }
    return '';
}

function nice_date(?string $dt, string $format = 'j M Y, H:i'): string
{
    return $dt ? date($format, strtotime($dt)) : '';
}

/* ---- tiny router ---- */

function route(string $method, string $pattern, callable $handler): void
{
    $GLOBALS['PW_ROUTES'][] = [$method, '#^' . preg_replace('#\{[a-z]+\}#', '([^/]+)', $pattern) . '$#', $handler];
}

function dispatch(): never
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'HEAD') $method = 'GET';
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($path !== '/') $path = rtrim($path, '/');
    $pathKnown = false;
    foreach ($GLOBALS['PW_ROUTES'] ?? [] as [$m, $re, $handler]) {
        if (!preg_match($re, $path, $args)) continue;
        $pathKnown = true;
        if ($m !== $method) continue;
        if ($method === 'POST') csrf_check();
        array_shift($args);
        $handler(...$args);
        exit;
    }
    fail_page($pathKnown ? 405 : 404, $pathKnown ? 'That action is not allowed here.' : 'We could not find that page.');
}

/** Page list helper: returns [offset, pages]. */
function paging(int $total, int $per, int $page): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return [($page - 1) * $per, $pages, $page];
}

function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, static fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
}

/* ---- IDs shown to people, and the categories a photo can be filed under ---- */

/**
 * Contributor ID, given automatically when someone joins a project: RM-C758947.
 * It is worked out from the person's row number, so it never changes and never repeats, but it is six digits
 * long and the numbers do not run in order: an ID does not show how many people joined before.
 * (387119 shares no factor with 900000, so every row number up to 900000 gets its own ID.)
 */
function contributor_id(int|string $id): string
{
    return 'RM-C' . (100000 + ((int) $id * 387119 + 271828) % 900000);
}

/** The row number behind a contributor ID that staff typed ("RM-C758947" or "758947"), or null when it is not one. */
function contributor_from_id(string $text): ?int
{
    if (!preg_match('/^(?:RM-?C)?(\d{6})$/i', trim($text), $m)) return null;
    $n = (int) $m[1] - 100000 - 271828;
    $n = (($n % 900000) + 900000) % 900000;
    return ($n * 30479) % 900000; // 30479 undoes the multiplication by 387119
}

/** Image ID of a photo that was kept: RM-I00001. */
function image_id(int|string $id): string
{
    return sprintf('RM-I%05d', (int) $id);
}

/** ID of an upload that failed a check and was not kept: RM-X00001. */
function turned_id(int|string $id): string
{
    return sprintf('RM-X%05d', (int) $id);
}

/** The categories of a project: its "what to photograph" lines, then Other. */
function project_categories(array $project): array
{
    $list = [];
    foreach (preg_split('/\R/', (string) ($project['wanted'] ?? '')) ?: [] as $line) {
        $line = mb_substr(trim($line), 0, 120);
        if ($line !== '' && strcasecmp($line, 'Other') !== 0 && !in_array($line, $list, true)) $list[] = $line;
    }
    $list[] = 'Other';
    return $list;
}

/**
 * Turns what the scanner thinks a photo shows (portrait, landscape, indoor, animal, food, still, macro, or
 * nothing) into one of the project's own categories. The project's lines are matched by meaning, the same
 * way their icons are chosen, so renamed lines still work. Unsure, or no matching line: Other.
 */
function category_for_key(array $project, ?string $key): string
{
    $icon = ['portrait' => 'person', 'landscape' => 'mountain', 'indoor' => 'sofa', 'animal' => 'paw', 'food' => 'food',
        'still' => 'vase', 'macro' => 'flower'][(string) $key] ?? null;
    if ($icon) {
        foreach (project_categories($project) as $c) {
            if ($c !== 'Other' && subject_look($c)['icon'] === $icon) return $c;
        }
    }
    return 'Other';
}

/* ---- countries: the list people choose from, and matching a choice to a connection ---- */

/** Two-letter code => English name, in A to Z order. */
function countries(): array
{
    return [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola', 'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
        'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize',
        'BJ' => 'Benin', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil',
        'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
        'CA' => 'Canada', 'CV' => 'Cape Verde', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (DRC)', 'CR' => 'Costa Rica', 'CI' => "Cote d'Ivoire",
        'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
        'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia', 'FJ' => 'Fiji', 'FI' => 'Finland',
        'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
        'GR' => 'Greece', 'GD' => 'Grenada', 'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
        'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India',
        'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy',
        'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati',
        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho',
        'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao',
        'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta',
        'MH' => 'Marshall Islands', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova',
        'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar',
        'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
        'NE' => 'Niger', 'NG' => 'Nigeria', 'KP' => 'North Korea', 'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman',
        'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
        'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar',
        'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia',
        'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands',
        'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
        'SD' => 'Sudan', 'SR' => 'Suriname', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 'UG' => 'Uganda',
        'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
}

/** The two-letter code of a country name as a contributor chose or typed it, or '' when it is not on the list. */
function country_code(string $name): string
{
    static $byName = null;
    $byName ??= array_change_key_case(array_flip(countries()), CASE_LOWER);
    return (string) ($byName[strtolower(trim($name))] ?? '');
}

/** A browser's long description in a few words, for example "Chrome on Windows". */
function agent_label(string $ua): string
{
    if ($ua === '') return '';
    $os = match (true) {
        str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS X') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => '',
    };
    $browser = match (true) {
        str_contains($ua, 'Edg') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
        str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => '',
    };
    return $browser !== '' && $os !== '' ? "$browser on $os" : ($browser . $os !== '' ? $browser . $os : 'Other');
}

/**
 * The stored codes of the activity list in plain words, each with the group it belongs to.
 * @return array<string, array{0:string, 1:string}> code => [words, group]
 */
function activity_words(): array
{
    return [
        'staff.login' => ['Staff signed in', 'signin'], 'client.login' => ['Client signed in', 'signin'], 'signin.refused' => ['Sign-in refused', 'signin'],
        'photo.approve' => ['Accepted photos', 'photos'], 'photo.reject' => ['Rejected photos', 'photos'], 'photo.pending' => ['Put photos back for review', 'photos'],
        'photo.category' => ['Changed a photo category', 'photos'],
        'photo.download' => ['Downloaded a photo', 'downloads'], 'zip.download' => ['Downloaded photos (ZIP)', 'downloads'], 'metadata.export' => ['Downloaded the photo information sheet', 'downloads'],
        'tracker.export' => ['Downloaded the tracker', 'downloads'], 'respondents.export' => ['Downloaded the contributor list', 'downloads'], 'client.activity.export' => ['Downloaded client activity', 'downloads'],
        'download.request' => ['Client asked to download', 'downloads'], 'download.approve' => ['Approved a download request', 'downloads'],
        'download.decline' => ['Declined a download request', 'downloads'], 'download.withdraw' => ['Withdrew a download approval', 'downloads'],
        'project.create' => ['Created a project', 'settings'], 'project.update' => ['Changed project settings', 'settings'],
        'settings.mail' => ['Changed email settings', 'settings'], 'settings.guard' => ['Changed sign-in checks', 'settings'], 'settings.invite' => ['Changed the invitation', 'settings'],
        'settings.gemini' => ['Changed Gemini settings', 'settings'], 'settings.phones' => ['Changed example photos', 'settings'],
        'settings.phones.make' => ['Created an example photo', 'settings'], 'settings.phones.use' => ['Put example photos live', 'settings'],
        'staff.create' => ['Added a staff account', 'people'], 'staff.password' => ['Changed a staff password', 'people'], 'staff.disable' => ['Switched a staff account off', 'people'],
        'staff.enable' => ['Switched a staff account on', 'people'], 'client.create' => ['Added a client company', 'people'], 'client.user.add' => ['Gave a client person access', 'people'],
        'client.user.disable' => ['Switched client access off', 'people'], 'client.user.enable' => ['Switched client access on', 'people'],
        'client.rename' => ['Renamed a client company', 'people'], 'client.disable' => ['Switched a client company off', 'people'], 'client.enable' => ['Switched a client company on', 'people'],
        'client.delete' => ['Deleted a client company', 'people'], 'client.user.edit' => ['Changed a client person', 'people'], 'client.user.delete' => ['Removed a client person', 'people'],
        'respondent.block' => ['Blocked a contributor', 'people'], 'respondent.unblock' => ['Unblocked a contributor', 'people'],
    ];
}

/**
 * A saved API key as it may be shown on screen: its first four and last three characters with **** between.
 * A key too short for that to hide most of it shows as **** only.
 */
function mask_key(string $key): string
{
    if ($key === '') return '';
    return strlen($key) >= 12 ? substr($key, 0, 4) . '****' . substr($key, -3) : '****';
}

/** Up to two capital letters to stand for a name, for the small coloured tile beside it. */
function initials(string $name): string
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = mb_substr($words[0] ?? '?', 0, 1);
    return mb_strtoupper($first . (count($words) > 1 ? mb_substr($words[1], 0, 1) : ''));
}

/* ---- reward paid to people who take part ---- */

/** "$1", "$2.50", "Rs 50": an amount as people read it. */
function money(string $symbol, float $amount): string
{
    $n = number_format($amount, 2, '.', ',');
    return $symbol . (str_ends_with($n, '.00') ? substr($n, 0, -3) : $n);
}

/**
 * The reward of a project: null when it pays nothing, otherwise
 * ['amount' => "$1", 'every' => 5, 'value' => 1.0, 'symbol' => '$'].
 * It is paid for every full group of accepted photos (approved by staff).
 */
function reward(array $project): ?array
{
    $value = (float) ($project['reward_amount'] ?? 0);
    $every = (int) ($project['reward_photos'] ?? 0);
    if ($value <= 0 || $every < 1) return null;
    $symbol = (string) ($project['reward_symbol'] ?? '$');
    return ['amount' => money($symbol, $value), 'every' => $every, 'value' => $value, 'symbol' => $symbol];
}

/** What a number of accepted photos has earned, as text ("$3"). */
function reward_earned(array $reward, int $accepted): string
{
    return money($reward['symbol'], intdiv($accepted, $reward['every']) * $reward['value']);
}

/* ---- accepted phone models, and the example photos shown inside the phones on the project page ---- */

const PW_IPHONES = ['iPhone 16', 'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e', 'iPhone 17', 'iPhone 17 Pro',
    'iPhone 17 Pro Max', 'iPhone Air', 'iPhone 17e', 'iPhone 18 Pro', 'iPhone 18 Pro Max', 'iPhone Duo', 'Another newer iPhone'];

const PW_SUBJECTS = ['Portraits and human activities', 'Outdoor landscapes', 'Indoor environments', 'Animals and pets',
    'Food and dining', 'Still life', 'Macro and texture photography'];

/** Short name for files and addresses, made from a "what to photograph" line. */
function phone_slug(string $model): string
{
    return trim(substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($model)), '-'), 0, 60), '-');
}

/**
 * Every "what to photograph" line used by any project, in order of first use.
 * The project page shows one phone per line, and the example photo inside it is kept per line.
 */
function picture_subjects(): array
{
    $all = [];
    foreach (Db::all('SELECT wanted FROM projects ORDER BY id') as $row) {
        foreach (preg_split('/\R/', (string) $row['wanted']) ?: [] as $line) {
            $slug = phone_slug($line);
            if ($slug !== '') $all[$slug] ??= trim($line);
        }
    }
    return array_values($all) ?: PW_SUBJECTS;
}

/** Path of the example photo staff saved for one kind of photo, or null when there is none. */
function phone_picture_file(string $slug): ?string
{
    if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) return null;
    foreach (['png', 'jpg', 'webp'] as $ext) {
        $f = rtrim((string) cfg('storage'), '/') . '/phones/' . $slug . '.' . $ext;
        if (is_file($f)) return $f;
    }
    return null;
}

/** Address of that picture for use in a page, or null. */
function phone_picture(string $model): ?string
{
    $slug = phone_slug($model);
    $f = phone_picture_file($slug);
    return $f ? '/phone/' . $slug . '?v=' . filemtime($f) : null;
}

function send_phone_picture(string $slug): never
{
    $f = phone_picture_file($slug);
    if (!$f) fail_page(404, 'We could not find that picture.');
    $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)];
    session_write_close();
    Storage::send($f, $mime);
}
