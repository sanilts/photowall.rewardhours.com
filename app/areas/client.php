<?php
declare(strict_types=1);

// Client portal: approved photos of the client's own projects, to view and download.

function cl_me(): ?array
{
    $id = $_SESSION['client_user_id'] ?? null;
    return $id ? Db::one('SELECT u.*, c.name AS client_name FROM client_users u JOIN clients c ON c.id = u.client_id WHERE u.id = ? AND u.active = 1', [$id]) : null;
}

function cl_need(): array
{
    $me = cl_me();
    if (!$me) redirect('/login');
    return $me;
}

function cl_project(array $me, string $id): array
{
    $p = Db::one("SELECT * FROM projects WHERE id = ? AND client_id = ? AND status <> 'draft'", [(int) $id, $me['client_id']]);
    if (!$p) fail_page(404, 'We could not find that project.');
    return $p;
}

function cl_photo(array $me, string $token): array
{
    $photo = Db::one("SELECT ph.* FROM photos ph JOIN projects p ON p.id = ph.project_id
        WHERE ph.token = ? AND ph.status = 'approved' AND p.client_id = ? AND p.status <> 'draft'", [$token, $me['client_id']]);
    if (!$photo) fail_page(404, 'We could not find that photo.');
    return $photo;
}

route('GET', '/login', function () {
    if (cl_me()) redirect('/');
    view('client/login', ['title' => 'Client sign in']);
});

route('POST', '/login', function () {
    $email = clean_email(post('email'));
    if (!$email) {
        flash('Please enter a valid email address.', 'bad');
        redirect('/login');
    }
    // The same answer is given whether or not the address has access, so the form cannot be used to test addresses.
    $user = Db::one('SELECT * FROM client_users WHERE email = ? AND active = 1', [$email]);
    if ($user) {
        $problem = Otp::issue('client', $email, 0, 'client portal');
        if ($problem) {
            flash($problem, 'bad');
            redirect('/login');
        }
    } else {
        throttle('otp:ip:' . client_ip(), 30, 3600);
    }
    $_SESSION['pending_client'] = $email;
    redirect('/login/code');
});

route('GET', '/login/code', function () {
    $email = $_SESSION['pending_client'] ?? null;
    if (!$email) redirect('/login');
    view('shared/code', ['title' => 'Enter your code', 'email' => $email, 'action' => '/login/code', 'resend' => '/login/resend', 'back' => '/login']);
});

route('POST', '/login/resend', function () {
    $email = $_SESSION['pending_client'] ?? null;
    if (!$email) redirect('/login');
    $problem = Db::val('SELECT id FROM client_users WHERE email = ? AND active = 1', [$email]) ? Otp::issue('client', $email, 0, 'client portal') : null;
    flash($problem ?: 'If this address has access, a new code is on its way.', $problem ? 'bad' : 'ok');
    redirect('/login/code');
});

route('POST', '/login/code', function () {
    $email = $_SESSION['pending_client'] ?? null;
    if (!$email) redirect('/login');
    $user = Db::one('SELECT * FROM client_users WHERE email = ? AND active = 1', [$email]);
    if (!$user || !throttle('otp:try:' . client_ip(), 40, 3600) || !Otp::verify('client', $email, 0, post('code'))) {
        flash('That code is not right or has expired.', 'bad');
        redirect('/login/code');
    }
    session_regenerate_id(true);
    unset($_SESSION['pending_client']);
    $_SESSION['client_user_id'] = (int) $user['id'];
    $_SESSION['who'] = ['type' => 'client', 'id' => (int) $user['id'], 'label' => $user['email']];
    Db::q('UPDATE client_users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
    audit('client.login');
    redirect('/');
});

route('POST', '/logout', function () {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/login');
});

route('GET', '/', function () {
    $me = cl_need();
    $projects = Db::all("SELECT p.*,
        (SELECT COUNT(*) FROM photos WHERE project_id = p.id AND status = 'approved') AS n_approved,
        (SELECT COALESCE(SUM(bytes), 0) FROM photos WHERE project_id = p.id AND status = 'approved') AS approved_bytes,
        (SELECT COUNT(DISTINCT respondent_id) FROM photos WHERE project_id = p.id AND status = 'approved') AS n_people
        FROM projects p WHERE p.client_id = ? AND p.status <> 'draft' ORDER BY p.id DESC", [$me['client_id']]);
    view('client/projects', ['title' => 'Your projects', 'me' => $me, 'projects' => $projects]);
});

route('GET', '/projects/{id}', function (string $id) {
    $me = cl_need();
    $p = cl_project($me, $id);
    $where = "ph.project_id = ? AND ph.status = 'approved'";
    $params = [$p['id']];
    $model = (string) ($_GET['model'] ?? '');
    if ($model !== '') {
        $where .= ' AND ph.device_model = ?';
        $params[] = $model;
    }
    $total = (int) Db::val("SELECT COUNT(*) FROM photos ph WHERE $where", $params);
    [$offset, $pages, $page] = paging($total, 60, (int) ($_GET['page'] ?? 1));
    $photos = Db::all("SELECT ph.id, ph.token, ph.respondent_id, ph.device_model, ph.width, ph.height, ph.bytes, ph.taken_at FROM photos ph WHERE $where ORDER BY ph.id LIMIT 60 OFFSET $offset", $params);
    $models = Db::all("SELECT device_model, COUNT(*) AS n FROM photos WHERE project_id = ? AND status = 'approved' GROUP BY device_model ORDER BY device_model", [$p['id']]);
    view('client/gallery', ['title' => $p['name'], 'p' => $p, 'photos' => $photos, 'models' => $models, 'model' => $model, 'total' => $total, 'pages' => $pages, 'page' => $page]);
});

route('GET', '/photo/{token}', function (string $token) {
    $me = cl_need();
    $photo = cl_photo($me, $token);
    $p = cl_project($me, (string) $photo['project_id']);
    view('client/photo', ['title' => 'Photo ' . $photo['id'], 'p' => $p, 'photo' => $photo, 'meta' => json_decode($photo['meta_json'], true) ?: []]);
});

route('GET', '/img/{token}/{size}', function (string $token, string $size) {
    $me = cl_need();
    $photo = cl_photo($me, $token);
    session_write_close();
    if ($size === 'thumb') Storage::send(Storage::thumb($photo), 'image/jpeg');
    if ($size === 'preview') Storage::send(Storage::preview($photo), 'image/jpeg');
    if ($size === 'original') {
        audit('photo.download', 'photo ' . $photo['id']);
        Storage::send(Storage::original($photo), $photo['mime'], Storage::downloadName($photo), false);
    }
    fail_page(404, 'We could not find that file.');
});

route('GET', '/projects/{id}/metadata.csv', function (string $id) {
    $me = cl_need();
    $p = cl_project($me, $id);
    audit('metadata.export', $p['name']);
    Export::sendCsv(Export::photos((int) $p['id'], ['approved']), false, 'metadata-' . $p['slug'] . '.csv');
});

route('POST', '/projects/{id}/zip', function (string $id) {
    $me = cl_need();
    $p = cl_project($me, $id);
    $ids = post('which') === 'all' ? null : array_slice((array) ($_POST['ids'] ?? []), 0, 5000);
    $photos = Export::photos((int) $p['id'], ['approved'], $ids);
    if (!$photos) {
        flash('Select at least one photo, or choose "Download all".', 'bad');
        redirect('/projects/' . $p['id']);
    }
    audit('zip.download', count($photos) . " photos from {$p['name']}");
    Export::sendZip($p, $photos, false);
});

dispatch();
