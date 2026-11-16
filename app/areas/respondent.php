<?php
declare(strict_types=1);

// Respondent site: terms, email code, basic details, upload.

function rp_project(string $slug): array
{
    $p = Db::one("SELECT * FROM projects WHERE slug = ? AND status <> 'draft'", [$slug]);
    if (!$p) fail_page(404, 'We could not find that project. Please check the link you were given.');
    return $p;
}

function rp_me(array $project): ?array
{
    $id = $_SESSION['rid'][$project['id']] ?? null;
    return $id ? Db::one('SELECT * FROM respondents WHERE id = ?', [$id]) : null;
}

function rp_need(array $project): array
{
    $me = rp_me($project);
    if (!$me) redirect('/p/' . $project['slug']);
    if ($me['full_name'] === '' || $me['country'] === '') {
        if (!str_ends_with((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/details')) redirect('/p/' . $project['slug'] . '/details');
    }
    return $me;
}

function rp_window(array $project): string
{
    $a = Intake::allowance($project, ['id' => 0, 'blocked' => 0]);
    return $a['state'];
}

route('GET', '/', function () {
    $open = Db::all("SELECT slug, name, intro, closes_at FROM projects WHERE status = 'open' AND (opens_at IS NULL OR opens_at <= ?) AND (closes_at IS NULL OR closes_at >= ?) ORDER BY id DESC", [now(), now()]);
    if (count($open) === 1) {
        // keep the tag of a shared link (?utm_source=facebook), so the Visits page can tell where people came from
        $tags = [];
        foreach (['utm_source', 'utm_campaign'] as $k) {
            if (is_string($_GET[$k] ?? null) && $_GET[$k] !== '') $tags[$k] = mb_substr($_GET[$k], 0, 80);
        }
        redirect('/p/' . $open[0]['slug'] . ($tags ? '?' . http_build_query($tags) : ''));
    }
    view('respondent/home', ['title' => 'Share your photos', 'projects' => $open, 'bare' => true,
        'description' => 'Photowall by Reward Hours. Take part in a photo project: share original photos taken on your iPhone and upload them straight from your phone.']);
});

route('GET', '/phone/{slug}', function (string $slug) {
    send_phone_picture($slug);
});

// The picture shown when a link to the site is shared.
route('GET', '/share.jpg', function () {
    session_write_close();
    Storage::send(Share::file(), 'image/jpeg', null, 'public');
});

// What search engines may read: the public pages, not the pages behind sign-in.
route('GET', '/robots.txt', function () {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo "User-agent: *\nAllow: /\nDisallow: /p/*/code\nDisallow: /p/*/upload\nDisallow: /p/*/details\nDisallow: /p/*/email\n\nSitemap: " . area_url('respondent', '/sitemap.xml') . "\n";
    exit;
});

route('GET', '/sitemap.xml', function () {
    $pages = [area_url('respondent', '/')];
    foreach (Db::all("SELECT slug FROM projects WHERE status = 'open' ORDER BY id") as $p) $pages[] = area_url('respondent', '/p/' . $p['slug']);
    $pages[] = area_url('respondent', '/privacy');
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $u) echo '  <url><loc>' . htmlspecialchars($u, ENT_XML1) . "</loc></url>\n";
    echo "</urlset>\n";
    exit;
});

route('GET', '/privacy', function () {
    view('respondent/privacy', ['title' => 'Privacy', 'description' => 'How Photowall by Reward Hours handles the photos and details of people who take part: what is collected, why, who sees it, and how to have it removed.']);
});

route('GET', '/p/{slug}', function (string $slug) {
    $project = rp_project($slug);
    if (rp_me($project)) redirect("/p/$slug/upload");
    view('respondent/landing', [
        'title' => $project['name'], 'project' => $project, 'state' => rp_window($project), 'email' => $_SESSION['pending']['email'] ?? '',
        'bare' => true, 'scripts' => ['phones.js'], 'headerLink' => ['Sign in', '#start'],
        'description' => 'Share photos taken on your iPhone ' . (int) $project['min_iphone'] . ' or newer. ' . mb_strimwidth((string) $project['intro'], 0, 140, '...'),
    ]);
});

route('POST', '/p/{slug}/start', function (string $slug) {
    $project = rp_project($slug);
    $email = clean_email(post('email'));
    if (!$email) {
        flash('Please enter a valid email address.', 'bad');
        redirect("/p/$slug#start");
    }
    if (post('agree') !== '1') {
        flash('Please read the terms and tick the box to agree before you continue.', 'bad');
        $_SESSION['pending'] = ['email' => $email];
        redirect("/p/$slug#start");
    }
    // Sign-in checks (switched on in the console): no VPN, proxy, Tor or data centre connections,
    // and a new person's email address has to be a real, permanent one.
    $conn = Guard::ip(client_ip());
    if ($conn && $conn['blocked']) {
        audit('signin.refused', "$email: connection is {$conn['why']} ({$conn['country_code']})");
        $_SESSION['pending'] = ['email' => $email];
        flash('We could not sign you in, because your connection is going through a VPN, proxy or similar service. Please switch it off and try again. On an iPhone, also switch off iCloud Private Relay (Settings, your name, iCloud, Private Relay).', 'bad');
        redirect("/p/$slug#start");
    }
    $known = Db::one('SELECT id FROM respondents WHERE project_id = ? AND email = ?', [$project['id'], $email]);
    if (!$known) {
        $mail = Guard::email($email);
        if ($mail && $mail['blocked']) {
            audit('signin.refused', "$email: email is {$mail['status']}" . ($mail['disposable'] ? ', temporary address' : ''));
            $_SESSION['pending'] = ['email' => $email];
            flash($mail['message'], 'bad');
            redirect("/p/$slug#start");
        }
    }
    if (!$known && rp_window($project) !== 'open') {
        flash('This project is not accepting new participants right now.', 'bad');
        redirect("/p/$slug#start");
    }
    $problem = Otp::issue('respondent', $email, (int) $project['id'], $project['name']);
    if ($problem) {
        flash($problem, 'bad');
        redirect("/p/$slug#start");
    }
    $_SESSION['pending'] = ['email' => $email, 'slug' => $slug, 'agreed' => true];
    redirect("/p/$slug/code");
});

route('GET', '/p/{slug}/code', function (string $slug) {
    $project = rp_project($slug);
    $pending = $_SESSION['pending'] ?? null;
    if (!$pending || ($pending['slug'] ?? '') !== $slug) redirect("/p/$slug");
    view('shared/code', ['title' => 'Enter your code', 'email' => $pending['email'], 'action' => "/p/$slug/code", 'resend' => "/p/$slug/resend", 'back' => "/p/$slug#start"]);
});

route('POST', '/p/{slug}/resend', function (string $slug) {
    $project = rp_project($slug);
    $pending = $_SESSION['pending'] ?? null;
    if (!$pending || ($pending['slug'] ?? '') !== $slug) redirect("/p/$slug");
    $problem = Otp::issue('respondent', $pending['email'], (int) $project['id'], $project['name']);
    flash($problem ?: 'We sent the code again.', $problem ? 'bad' : 'ok');
    redirect("/p/$slug/code");
});

route('POST', '/p/{slug}/code', function (string $slug) {
    $project = rp_project($slug);
    $pending = $_SESSION['pending'] ?? null;
    if (!$pending || ($pending['slug'] ?? '') !== $slug || empty($pending['agreed'])) redirect("/p/$slug");
    $email = $pending['email'];
    if (!throttle('otp:try:' . client_ip(), 40, 3600) || !Otp::verify('respondent', $email, (int) $project['id'], post('code'))) {
        flash('That code is not right or has expired. Please try again or ask for a new code.', 'bad');
        redirect("/p/$slug/code");
    }
    $me = Db::one('SELECT * FROM respondents WHERE project_id = ? AND email = ?', [$project['id'], $email]);
    if ($me) {
        // A new version of the terms records a fresh agreement; the same version keeps the first date.
        if ((int) $me['terms_version'] !== (int) $project['terms_version']) {
            Db::q('UPDATE respondents SET terms_version = ?, terms_agreed_at = ?, terms_ip = ? WHERE id = ?', [$project['terms_version'], now(), client_ip(), $me['id']]);
        }
        Db::q('UPDATE respondents SET last_login_at = ? WHERE id = ?', [now(), $me['id']]);
        $id = (int) $me['id'];
        if ($where = Guard::ip(client_ip())) Db::q('UPDATE respondents SET ip_country = ? WHERE id = ?', [substr($where['country_code'], 0, 2), $id]);
    } else {
        $id = Db::insert(
            'INSERT INTO respondents (project_id, email, age_confirmed, terms_version, terms_agreed_at, terms_ip, ip_country, created_at, last_login_at) VALUES (?,?,1,?,?,?,?,?,?)',
            [$project['id'], $email, $project['terms_version'], now(), client_ip(), substr((string) (Guard::ip(client_ip())['country_code'] ?? ''), 0, 2), now(), now()]
        );
    }
    session_regenerate_id(true);
    unset($_SESSION['pending']);
    $_SESSION['rid'][$project['id']] = $id;
    $_SESSION['who'] = ['type' => 'respondent', 'id' => $id, 'label' => $email];
    redirect("/p/$slug/upload");
});

route('GET', '/p/{slug}/details', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    view('respondent/details', ['title' => 'Your details', 'project' => $project, 'me' => $me, 'models' => PW_IPHONES,
        'here' => countries()[(string) ($me['ip_country'] ?? '')] ?? '']);
});

route('POST', '/p/{slug}/details', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $name = mb_substr(post('full_name'), 0, 120);
    $country = mb_substr(post('country'), 0, 80);
    $phone = mb_substr(preg_replace('/[^\d+ ()\-]/', '', post('phone')), 0, 40);
    $model = in_array(post('iphone_model'), PW_IPHONES, true) ? post('iphone_model') : '';
    if (mb_strlen($name) < 2 || mb_strlen($country) < 2 || $model === '') {
        flash('Please fill in your name, country and iPhone model.', 'bad');
        redirect("/p/$slug/details");
    }
    Db::q('UPDATE respondents SET full_name = ?, country = ?, phone = ?, iphone_model = ? WHERE id = ?', [$name, $country, $phone, $model, $me['id']]);
    // A different country from where the connection is: say so once, but accept it. People travel and move.
    $here = countries()[(string) ($me['ip_country'] ?? '')] ?? '';
    if ($here !== '' && country_code($country) !== '' && country_code($country) !== $me['ip_country']) {
        flash("Your details are saved. You chose $country, and your connection appears to be in $here. If $country is where you live, nothing more is needed. To change it, open Account and choose \"Edit your details\".");
    }
    redirect("/p/$slug/upload");
});

/* ---------- changing the email address after signing in ---------- */

route('GET', '/p/{slug}/email', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    view('respondent/email', ['title' => 'Change your email', 'project' => $project, 'me' => $me]);
});

route('POST', '/p/{slug}/email', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $new = clean_email(post('email'));
    if (!$new) {
        flash('Please enter a valid email address.', 'bad');
    } elseif ($new === $me['email']) {
        flash('That is already your email address.', 'bad');
    } elseif (Db::val('SELECT id FROM respondents WHERE project_id = ? AND email = ?', [$project['id'], $new])) {
        flash('That email address is already taking part in this project. Sign out and sign in with it instead.', 'bad');
    } elseif ($problem = Otp::issue('respondent', $new, (int) $project['id'], 'changing your email for ' . $project['name'])) {
        flash($problem, 'bad');
    } else {
        $_SESSION['new_email'][$project['id']] = $new;
        redirect("/p/$slug/email/code");
    }
    redirect("/p/$slug/email");
});

route('GET', '/p/{slug}/email/code', function (string $slug) {
    $project = rp_project($slug);
    rp_need($project);
    $new = $_SESSION['new_email'][$project['id']] ?? null;
    if (!$new) redirect("/p/$slug/email");
    view('shared/code', ['title' => 'Confirm your new email', 'heading' => 'Confirm your new email', 'email' => $new, 'action' => "/p/$slug/email/code", 'resend' => "/p/$slug/email/resend", 'back' => "/p/$slug/email"]);
});

route('POST', '/p/{slug}/email/resend', function (string $slug) {
    $project = rp_project($slug);
    rp_need($project);
    $new = $_SESSION['new_email'][$project['id']] ?? null;
    if (!$new) redirect("/p/$slug/email");
    $problem = Otp::issue('respondent', $new, (int) $project['id'], 'changing your email for ' . $project['name']);
    flash($problem ?: 'We sent the code again.', $problem ? 'bad' : 'ok');
    redirect("/p/$slug/email/code");
});

route('POST', '/p/{slug}/email/code', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $new = $_SESSION['new_email'][$project['id']] ?? null;
    if (!$new) redirect("/p/$slug/email");
    if (!throttle('otp:try:' . client_ip(), 40, 3600) || !Otp::verify('respondent', $new, (int) $project['id'], post('code'))) {
        flash('That code is not right or has expired. Please try again or send the code again.', 'bad');
        redirect("/p/$slug/email/code");
    }
    if (Db::val('SELECT id FROM respondents WHERE project_id = ? AND email = ?', [$project['id'], $new])) {
        flash('That email address is already taking part in this project.', 'bad');
        redirect("/p/$slug/email");
    }
    Db::q('UPDATE respondents SET email = ? WHERE id = ?', [$new, $me['id']]);
    unset($_SESSION['new_email'][$project['id']]);
    $_SESSION['who']['label'] = $new;
    flash('Your email is now ' . $new . '. Use it the next time you sign in.');
    redirect("/p/$slug/upload");
});

route('GET', '/p/{slug}/upload', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $invite = (Settings::get('invite') ?? []) + ['on' => true, 'title' => 'Join Reward Hours', 'text' => 'Reward Hours is our research community. Join to be invited to more studies from Research and Metric.', 'button' => 'Visit Reward Hours', 'link' => 'https://rewardhours.com'];
    $photos = Db::all("SELECT id, token, orig_name, status, category, category_auto, reject_reason, device_model, created_at, reviewed_at FROM photos WHERE respondent_id = ? AND status <> 'removed' ORDER BY id DESC", [$me['id']]);
    view('respondent/upload', ['title' => $project['name'], 'project' => $project, 'me' => $me, 'allow' => Intake::allowance($project, $me), 'photos' => $photos, 'invite' => $invite]);
});

route('POST', '/p/{slug}/upload', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_me($project);
    if (!$me) json_out(['ok' => false, 'code' => 'signin', 'message' => 'Your session ended. Please reload the page and sign in again.'], 401);
    session_write_close();
    if (empty($_FILES['photo'])) {
        $res = ['ok' => false, 'code' => 'too_big', 'message' => 'This file is too large. The limit is ' . cfg('max_upload_mb', 60) . ' MB per photo.'];
    } else {
        $res = Intake::handle($project, $me, $_FILES['photo'], trim((string) ($_POST['category'] ?? '')));
    }
    $out = ['ok' => $res['ok'], 'code' => $res['code'], 'message' => $res['message'], 'allow' => Intake::allowance($project, $me)];
    if ($res['ok']) $out['photo'] = ['token' => $res['photo']['token'], 'thumb' => "/p/$slug/thumb/" . $res['photo']['token'], 'category' => $res['photo']['category'], 'auto' => (bool) $res['photo']['category_auto'],
        'image_id' => image_id($res['photo']['id']), 'name' => $res['photo']['orig_name']];
    json_out($out);
});

route('GET', '/p/{slug}/thumb/{token}', function (string $slug, string $token) {
    $project = rp_project($slug);
    $me = rp_me($project);
    $photo = $me ? Db::one("SELECT * FROM photos WHERE token = ? AND respondent_id = ? AND status <> 'removed'", [$token, $me['id']]) : null;
    if (!$photo) fail_page(404, 'We could not find that photo.');
    session_write_close();
    Storage::send(Storage::thumb($photo), 'image/jpeg');
});

// The contributor can move a photo to another category until staff have looked at it.
route('POST', '/p/{slug}/photo/{token}/category', function (string $slug, string $token) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $category = trim((string) ($_POST['category'] ?? ''));
    $photo = Db::one("SELECT id FROM photos WHERE token = ? AND respondent_id = ? AND status = 'pending'", [$token, $me['id']]);
    $ok = $photo && in_array($category, project_categories($project), true);
    if ($ok) Db::q('UPDATE photos SET category = ?, category_auto = 0 WHERE id = ?', [$category, $photo['id']]);
    $msg = $ok ? 'Category saved.' : 'The category of this photo can no longer be changed here.';
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') json_out(['ok' => (bool) $ok, 'message' => $msg], $ok ? 200 : 409);
    flash($msg, $ok ? 'ok' : 'bad');
    redirect("/p/$slug/upload");
});

route('POST', '/p/{slug}/photo/{token}/delete', function (string $slug, string $token) {
    $project = rp_project($slug);
    $me = rp_need($project);
    $photo = Db::one("SELECT * FROM photos WHERE token = ? AND respondent_id = ? AND status = 'pending'", [$token, $me['id']]);
    if ($photo) {
        Storage::removeFiles($photo);
        Db::q("UPDATE photos SET status = 'removed', reviewed_at = ? WHERE id = ?", [now(), $photo['id']]);
        flash('The photo was deleted.');
    } else {
        flash('This photo can no longer be deleted here. To withdraw it, email ' . cfg('privacy_email') . '.', 'bad');
    }
    redirect("/p/$slug/upload");
});

route('POST', '/logout', function () {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/');
});

dispatch();
