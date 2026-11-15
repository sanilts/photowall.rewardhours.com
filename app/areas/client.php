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

/** Header links. "Download requests" appears once it is of use: a project asks for approval, or the person has asked before. */
function cl_nav(array $me, string $on): array
{
    $nav = [['Projects', '/', $on === 'projects']];
    if (Db::val("SELECT 1 FROM projects WHERE client_id = ? AND client_approval = 1 AND status <> 'draft' LIMIT 1", [$me['client_id']])
        || Db::val('SELECT 1 FROM download_requests WHERE client_user_id = ? LIMIT 1', [$me['id']])) {
        $nav[] = ['Download requests', '/requests', $on === 'requests'];
    }
    return $nav;
}

/** An approved request of this person that still runs and includes the photo, or null. */
function cl_cover(array $me, int $photoId): ?array
{
    return Db::one("SELECT r.* FROM download_requests r JOIN download_request_photos d ON d.request_id = r.id
        WHERE r.client_user_id = ? AND d.photo_id = ? AND r.status = 'approved' AND r.expires_at > ? ORDER BY r.id DESC LIMIT 1", [$me['id'], $photoId, now()]);
}

/** Whether this person already has a request for the photo waiting for our answer. */
function cl_waiting(array $me, int $photoId): bool
{
    return (bool) Db::val("SELECT 1 FROM download_requests r JOIN download_request_photos d ON d.request_id = r.id
        WHERE r.client_user_id = ? AND d.photo_id = ? AND r.status = 'pending' LIMIT 1", [$me['id'], $photoId]);
}

/**
 * Save a request to download photos and tell our administrators by email.
 * $ids null means every approved photo of the project as it stands now.
 */
function cl_ask(array $me, array $p, ?array $ids, string $note): ?int
{
    if ((int) Db::val("SELECT COUNT(*) FROM download_requests WHERE client_user_id = ? AND status = 'pending'", [$me['id']]) >= 20) {
        flash('You already have 20 requests waiting for our answer. Please wait for those first.', 'bad');
        return null;
    }
    $rid = Db::insert('INSERT INTO download_requests (project_id, client_id, client_user_id, photos, note, created_at) VALUES (?,?,?,0,?,?)',
        [$p['id'], $me['client_id'], $me['id'], mb_substr(trim($note), 0, 500), now()]);
    if ($ids === null) {
        Db::q("INSERT INTO download_request_photos (request_id, photo_id) SELECT ?, id FROM photos WHERE project_id = ? AND status = 'approved'", [$rid, $p['id']]);
    } else {
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 500) as $chunk) {
            Db::q("INSERT INTO download_request_photos (request_id, photo_id) SELECT ?, id FROM photos WHERE project_id = ? AND status = 'approved' AND id IN ("
                . rtrim(str_repeat('?,', count($chunk)), ',') . ')', [$rid, $p['id'], ...$chunk]);
        }
    }
    $sum = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(ph.bytes), 0) AS bytes FROM download_request_photos d JOIN photos ph ON ph.id = d.photo_id WHERE d.request_id = ?', [$rid]);
    if ((int) $sum['n'] === 0) {
        Db::q('DELETE FROM download_requests WHERE id = ?', [$rid]);
        return null;
    }
    Db::q('UPDATE download_requests SET photos = ?, bytes = ? WHERE id = ?', [$sum['n'], $sum['bytes'], $rid]);
    $n = (int) $sum['n'];
    ClientLog::add($me, 'request', (int) $p['id'], $n, "request $rid");
    audit('download.request', "$n photos from {$p['name']} (request $rid)");
    $link = area_url('admin', '/clients/requests');
    $what = $n === 1 ? '1 photo' : "$n photos";
    $text = "{$me['name']} ({$me['email']}) of {$me['client_name']} asks to download $what from \"{$p['name']}\"."
        . (trim($note) !== '' ? "\n\nTheir note: " . mb_substr(trim($note), 0, 500) : '') . "\n\nApprove or decline it here:\n$link\n";
    foreach (Db::all("SELECT email FROM staff WHERE active = 1 AND role = 'admin'") as $s) {
        try { Mailer::send($s['email'], "Download request: {$me['client_name']}, $what", $text); } catch (Throwable $e) { error_log('Photowall request mail: ' . $e->getMessage()); }
    }
    return $rid;
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
    ClientLog::add($user, 'login');
    redirect('/');
});

route('POST', '/logout', function () {
    if ($me = cl_me()) ClientLog::add($me, 'logout');
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
    view('client/projects', ['title' => 'Your projects', 'nav' => cl_nav($me, 'projects'), 'me' => $me, 'projects' => $projects]);
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
    ClientLog::add($me, 'gallery', (int) $p['id'], count($photos), "page $page of " . max(1, $pages) . ($model !== '' ? ", $model" : ''));
    view('client/gallery', ['title' => $p['name'], 'nav' => cl_nav($me, 'projects'), 'p' => $p, 'photos' => $photos, 'models' => $models, 'model' => $model, 'total' => $total, 'pages' => $pages, 'page' => $page]);
});

route('GET', '/photo/{token}', function (string $token) {
    $me = cl_need();
    $photo = cl_photo($me, $token);
    $p = cl_project($me, (string) $photo['project_id']);
    ClientLog::add($me, 'view', (int) $p['id'], 1, image_id($photo['id']), [(int) $photo['id']], 'view');
    $state = !$p['client_approval'] ? 'free' : (cl_cover($me, (int) $photo['id']) ? 'approved' : (cl_waiting($me, (int) $photo['id']) ? 'waiting' : 'ask'));
    view('client/photo', ['title' => 'Photo ' . image_id($photo['id']), 'nav' => cl_nav($me, 'projects'), 'p' => $p, 'photo' => $photo, 'meta' => json_decode($photo['meta_json'], true) ?: [], 'state' => $state]);
});

route('POST', '/photo/{token}/ask', function (string $token) {
    $me = cl_need();
    $photo = cl_photo($me, $token);
    $p = cl_project($me, (string) $photo['project_id']);
    if (!$p['client_approval'] || cl_cover($me, (int) $photo['id'])) redirect('/photo/' . $photo['token']);
    if (cl_waiting($me, (int) $photo['id'])) {
        flash('You have already asked for this photo. We email you when it is decided.');
    } elseif (cl_ask($me, $p, [(int) $photo['id']], post('note'))) {
        flash('Your request was sent. We email you as soon as it is decided.');
    }
    redirect('/photo/' . $photo['token']);
});

route('GET', '/img/{token}/{size}', function (string $token, string $size) {
    $me = cl_need();
    $photo = cl_photo($me, $token);
    if ($size === 'original') {
        $p = cl_project($me, (string) $photo['project_id']);
        $cover = $p['client_approval'] ? cl_cover($me, (int) $photo['id']) : null;
        if ($p['client_approval'] && !$cover) {
            flash('Downloads from this project need our approval first. Use "Ask to download this photo".', 'bad');
            redirect('/photo/' . $photo['token']);
        }
        audit('photo.download', 'photo ' . $photo['id']);
        ClientLog::add($me, 'download', (int) $p['id'], 1, image_id($photo['id']) . ($cover ? ", request {$cover['id']}" : ''), [(int) $photo['id']], 'download');
        if ($cover) Db::q('UPDATE download_requests SET downloads = downloads + 1, last_download_at = ? WHERE id = ?', [now(), $cover['id']]);
        session_write_close();
        Storage::sendOriginal($photo);
    }
    session_write_close();
    if ($size === 'thumb') Storage::send(Storage::thumb($photo), 'image/jpeg');
    if ($size === 'preview') Storage::send(Storage::preview($photo), 'image/jpeg');
    fail_page(404, 'We could not find that file.');
});

route('GET', '/projects/{id}/metadata.csv', function (string $id) {
    $me = cl_need();
    $p = cl_project($me, $id);
    audit('metadata.export', $p['name']);
    $rows = Export::photos((int) $p['id'], ['approved']);
    ClientLog::add($me, 'csv', (int) $p['id'], count($rows));
    Export::sendCsv($rows, false, 'metadata-' . $p['slug'] . '.csv');
});

route('POST', '/projects/{id}/zip', function (string $id) {
    $me = cl_need();
    $p = cl_project($me, $id);
    if (!$p['client_bulk']) {
        flash('Downloading many photos at once is switched off for this project. Open a photo to download it.', 'bad');
        redirect('/projects/' . $p['id']);
    }
    $ids = post('which') === 'all' ? null : array_slice((array) ($_POST['ids'] ?? []), 0, 5000);
    if ($ids !== null && !$ids) {
        flash('Select at least one photo, or choose all photos.', 'bad');
        redirect('/projects/' . $p['id']);
    }
    if ($p['client_approval']) {
        // Downloads need our approval: this becomes a request, and the files follow once a member of staff agrees.
        $rid = cl_ask($me, $p, $ids, post('note'));
        if ($rid) flash('Your request was sent. We email you as soon as it is decided, and the download then waits for you here.');
        elseif (empty($_SESSION['flash'])) flash('There are no approved photos to ask for yet.', 'bad');
        redirect($rid ? '/requests' : '/projects/' . $p['id']);
    }
    $photos = Export::photos((int) $p['id'], ['approved'], $ids);
    if (!$photos) {
        flash('Select at least one photo, or choose all photos.', 'bad');
        redirect('/projects/' . $p['id']);
    }
    audit('zip.download', count($photos) . " photos from {$p['name']}");
    ClientLog::add($me, 'zip', (int) $p['id'], count($photos), '', array_column($photos, 'id'), 'download');
    Export::sendZip($p, $photos, false);
});

/* ---------- download requests: asked here, decided by our staff in the console ---------- */

route('GET', '/requests', function () {
    $me = cl_need();
    $rows = Db::all('SELECT r.*, p.name AS project_name FROM download_requests r JOIN projects p ON p.id = r.project_id WHERE r.client_user_id = ? ORDER BY r.id DESC LIMIT 200', [$me['id']]);
    foreach ($rows as &$r) {
        $r['thumbs'] = Db::all("SELECT ph.token FROM download_request_photos d JOIN photos ph ON ph.id = d.photo_id AND ph.status = 'approved' WHERE d.request_id = ? ORDER BY ph.id LIMIT 6", [$r['id']]);
    }
    unset($r);
    view('client/requests', ['title' => 'Download requests', 'nav' => cl_nav($me, 'requests'), 'rows' => $rows, 'days' => ClientLog::VALID_DAYS]);
});

route('POST', '/requests/{id}/download', function (string $id) {
    $me = cl_need();
    $r = Db::one("SELECT * FROM download_requests WHERE id = ? AND client_user_id = ? AND status = 'approved' AND expires_at > ?", [(int) $id, $me['id'], now()]);
    if (!$r) {
        flash('That download is not available. It may have run out, or it has not been approved.', 'bad');
        redirect('/requests');
    }
    $p = cl_project($me, (string) $r['project_id']);
    $photos = Export::requestPhotos((int) $r['id']);
    if (!$photos) {
        flash('The photos of that request are no longer available.', 'bad');
        redirect('/requests');
    }
    Db::q('UPDATE download_requests SET downloads = downloads + 1, last_download_at = ? WHERE id = ?', [now(), $r['id']]);
    audit(count($photos) === 1 ? 'photo.download' : 'zip.download', count($photos) . " photos from {$p['name']} (request {$r['id']})");
    ClientLog::add($me, count($photos) === 1 ? 'download' : 'zip', (int) $p['id'], count($photos), "request {$r['id']}", array_column($photos, 'id'), 'download');
    if (count($photos) === 1) {
        session_write_close();
        Storage::sendOriginal(Db::one('SELECT * FROM photos WHERE id = ?', [$photos[0]['id']]));
    }
    Export::sendZip($p, $photos, false);
});

dispatch();
