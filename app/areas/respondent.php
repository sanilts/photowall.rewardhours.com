<?php
declare(strict_types=1);

// Respondent site: terms, email code, basic details, upload.

const PW_IPHONES = ['iPhone 16', 'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e', 'iPhone 17', 'iPhone 17 Pro',
    'iPhone 17 Pro Max', 'iPhone Air', 'iPhone 17e', 'iPhone 18 Pro', 'iPhone 18 Pro Max', 'iPhone Duo', 'Another newer iPhone'];

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
    if (count($open) === 1) redirect('/p/' . $open[0]['slug']);
    view('respondent/home', ['title' => 'Share your photos', 'projects' => $open, 'bare' => true]);
});

route('GET', '/privacy', function () {
    view('respondent/privacy', ['title' => 'Privacy']);
});

route('GET', '/p/{slug}', function (string $slug) {
    $project = rp_project($slug);
    if (rp_me($project)) redirect("/p/$slug/upload");
    view('respondent/landing', [
        'title' => $project['name'], 'project' => $project, 'state' => rp_window($project), 'email' => $_SESSION['pending']['email'] ?? '',
        'bare' => true, 'headerLink' => ['Sign in', '#start'],
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
    $known = Db::one('SELECT id FROM respondents WHERE project_id = ? AND email = ?', [$project['id'], $email]);
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
    } else {
        $id = Db::insert(
            'INSERT INTO respondents (project_id, email, age_confirmed, terms_version, terms_agreed_at, terms_ip, created_at, last_login_at) VALUES (?,?,1,?,?,?,?,?)',
            [$project['id'], $email, $project['terms_version'], now(), client_ip(), now(), now()]
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
    view('respondent/details', ['title' => 'Your details', 'project' => $project, 'me' => $me, 'models' => PW_IPHONES]);
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
    $photos = Db::all("SELECT token, status, reject_reason, device_model, created_at FROM photos WHERE respondent_id = ? AND status <> 'removed' ORDER BY id DESC", [$me['id']]);
    view('respondent/upload', ['title' => $project['name'], 'project' => $project, 'me' => $me, 'allow' => Intake::allowance($project, $me), 'photos' => $photos]);
});

route('POST', '/p/{slug}/upload', function (string $slug) {
    $project = rp_project($slug);
    $me = rp_me($project);
    if (!$me) json_out(['ok' => false, 'code' => 'signin', 'message' => 'Your session ended. Please reload the page and sign in again.'], 401);
    session_write_close();
    if (empty($_FILES['photo'])) {
        $res = ['ok' => false, 'code' => 'too_big', 'message' => 'This file is too large. The limit is ' . cfg('max_upload_mb', 60) . ' MB per photo.'];
    } else {
        $res = Intake::handle($project, $me, $_FILES['photo']);
    }
    $out = ['ok' => $res['ok'], 'code' => $res['code'], 'message' => $res['message'], 'allow' => Intake::allowance($project, $me)];
    if ($res['ok']) $out['photo'] = ['token' => $res['photo']['token'], 'thumb' => "/p/$slug/thumb/" . $res['photo']['token']];
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
