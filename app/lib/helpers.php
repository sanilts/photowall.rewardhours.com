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
