<?php
declare(strict_types=1);

// Staff console: projects, photo review, respondents, clients, staff, downloads.

function ad_me(): ?array
{
    $id = $_SESSION['staff_id'] ?? null;
    $me = $id ? Db::one('SELECT * FROM staff WHERE id = ? AND active = 1', [$id]) : null;
    return $me;
}

function ad_need(bool $adminOnly = false): array
{
    $me = ad_me();
    if (!$me) redirect('/login');
    if ($adminOnly && $me['role'] !== 'admin') fail_page(403, 'Only an administrator can do this.');
    return $me;
}

function ad_nav(string $on): array
{
    $me = ad_me();
    $nav = [['Projects', '/', $on === 'projects']];
    if ($me && $me['role'] === 'admin') {
        $asked = (int) Db::val("SELECT COUNT(*) FROM download_requests WHERE status = 'pending'");
        $nav[] = ['Clients' . ($asked ? " ($asked)" : ''), $asked ? '/clients/requests' : '/clients', $on === 'clients'];
        $nav[] = ['Staff', '/staff', $on === 'staff'];
        $nav[] = ['Activity', '/activity', $on === 'activity'];
        $nav[] = ['Settings', '/settings', $on === 'settings'];
    }
    return $nav;
}

function ad_project(string $id): array
{
    $p = Db::one('SELECT * FROM projects WHERE id = ?', [(int) $id]);
    if (!$p) fail_page(404, 'We could not find that project.');
    return $p;
}

function ad_sign_in(array $staff): never
{
    session_regenerate_id(true);
    unset($_SESSION['pending_staff']);
    $_SESSION['staff_id'] = (int) $staff['id'];
    $_SESSION['who'] = ['type' => 'staff', 'id' => (int) $staff['id'], 'label' => $staff['email']];
    Db::q('UPDATE staff SET last_login_at = ? WHERE id = ?', [now(), $staff['id']]);
    audit('staff.login');
    redirect('/');
}

function ad_default_terms(): string
{
    $co = (string) cfg('company');
    $mail = (string) cfg('privacy_email');
    return <<<TXT
1. Who runs this project
This photo project is run by $co ("we"). We collect photos on behalf of a research client.

2. What you agree to give us
You will upload photos that you took yourself with your own iPhone. With each photo we receive the information your iPhone stores inside the file, including the phone model, camera settings, the date and time, and the location where the photo was taken if location was switched on for your camera. We also collect your email address, name, country and iPhone model.

3. Rules for the photos
The photos must be your own original, unedited photos. Do not upload screenshots, edited or filtered photos, or photos taken by someone else. Do not upload photos that show nudity, violence or anything illegal, photos of children, or photos of other people who have not agreed to be photographed for this project. Every photo is checked automatically and by our staff. Photos that break these rules are deleted and you may be removed from the project.

4. How the photos are used
Approved photos and the information inside the photo files are passed to our research client and may be used for research, analysis and the development and testing of products and technology. Your name, email address and phone number are not passed to the client. You keep ownership of your photos and give us and our client a non-exclusive, worldwide, royalty-free right to use them for these purposes.

5. Your choices
Taking part is voluntary. You can delete a photo while it is still in review, and you can withdraw or ask for your data to be deleted at any time by emailing $mail. Photos that were already passed to the client before your request may not be recoverable from work already completed.

6. Your confirmation
By ticking the box you confirm that you are 18 or older, that you took the photos yourself, and that you agree to these terms.
TXT;
}

function ad_project_input(?array $old): array
{
    $dt = static function (string $v): ?string {
        $t = $v !== '' ? strtotime($v) : false;
        return $t ? date('Y-m-d H:i:s', $t) : null;
    };
    $name = mb_substr(post('name'), 0, 160);
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('slug') !== '' ? post('slug') : $name), '-'));
    $clientId = (int) post('client_id');
    $data = [
        'name' => $name,
        'slug' => substr($slug, 0, 80),
        'client_id' => $clientId > 0 && Db::val('SELECT id FROM clients WHERE id = ?', [$clientId]) ? $clientId : null,
        'status' => in_array(post('status'), ['draft', 'open', 'closed'], true) ? post('status') : 'draft',
        'intro' => post('intro'),
        'wanted' => post('wanted'),
        'terms' => post('terms'),
        'photos_per_person' => max(0, min(100000, (int) post('photos_per_person'))),
        'daily_limit' => max(0, min(10000, (int) post('daily_limit'))),
        'target_photos' => max(0, min(100000000, (int) post('target_photos'))),
        'reward_amount' => max(0, min(100000, round((float) str_replace(',', '.', post('reward_amount')), 2))),
        'reward_photos' => max(1, min(10000, (int) post('reward_photos'))),
        'reward_symbol' => mb_substr(post('reward_symbol'), 0, 8) ?: '$',
        'opens_at' => $dt(post('opens_at')),
        'closes_at' => $dt(post('closes_at')),
        'min_iphone' => max(11, min(99, (int) post('min_iphone'))),
        'extra_models' => post('extra_models'),
        'strict_original' => post('strict_original') === '1' ? 1 : 0,
        'client_approval' => post('client_approval') === '1' ? 1 : 0,
        'client_bulk' => post('client_bulk') === '1' ? 1 : 0,
        'bulk_upload' => post('bulk_upload') === '1' ? 1 : 0,
        'min_megapixels' => max(0, min(200, (float) post('min_megapixels'))),
        'blur_min' => max(0, min(100, (float) post('blur_min'))),
        'dark_min' => max(0, min(255, (float) post('dark_min'))),
        'bright_max' => max(0, min(255, (float) post('bright_max'))),
        'dup_distance' => in_array((int) post('dup_distance'), [-1, 2, 4, 8], true) ? (int) post('dup_distance') : 4,
        'safety_block' => max(0.05, min(1, (float) post('safety_block'))),
        'safety_review' => max(0.01, min(1, (float) post('safety_review'))),
    ];
    $errors = [];
    if (mb_strlen($name) < 3) $errors[] = 'Give the project a name.';
    if ($data['slug'] === '') $errors[] = 'The link name can only use letters, numbers and dashes.';
    if (Db::val('SELECT id FROM projects WHERE slug = ? AND id <> ?', [$data['slug'], $old['id'] ?? 0])) $errors[] = 'Another project already uses that link name.';
    if (mb_strlen($data['terms']) < 50) $errors[] = 'The terms and conditions are missing.';
    if ($data['opens_at'] && $data['closes_at'] && $data['closes_at'] <= $data['opens_at']) $errors[] = 'The closing date must be after the opening date.';
    return [$data, $errors];
}

/* ---------- sign in ---------- */

route('GET', '/login', function () {
    if (ad_me()) redirect('/');
    view('admin/login', ['title' => 'Staff sign in']);
});

route('POST', '/login', function () {
    $email = clean_email(post('email')) ?? '';
    $staff = Db::one('SELECT * FROM staff WHERE email = ? AND active = 1', [$email]);
    $ok = throttle('login:' . client_ip(), 20, 900) && throttle('login:' . $email, 8, 900)
        && $staff && password_verify((string) ($_POST['password'] ?? ''), $staff['pass_hash']);
    if (!$ok) {
        flash('That email and password do not match, or there were too many tries. Please try again.', 'bad');
        redirect('/login');
    }
    if (!cfg('admin_otp', true)) ad_sign_in($staff);
    $problem = Otp::issue('admin', $staff['email'], 0, 'staff console');
    if ($problem) {
        flash($problem, 'bad');
        redirect('/login');
    }
    $_SESSION['pending_staff'] = (int) $staff['id'];
    redirect('/login/code');
});

route('GET', '/login/code', function () {
    $staff = Db::one('SELECT * FROM staff WHERE id = ?', [$_SESSION['pending_staff'] ?? 0]);
    if (!$staff) redirect('/login');
    view('shared/code', ['title' => 'Enter your code', 'email' => $staff['email'], 'action' => '/login/code', 'resend' => '/login/resend', 'back' => '/login']);
});

route('POST', '/login/resend', function () {
    $staff = Db::one('SELECT * FROM staff WHERE id = ? AND active = 1', [$_SESSION['pending_staff'] ?? 0]);
    if (!$staff) redirect('/login');
    $problem = Otp::issue('admin', $staff['email'], 0, 'staff console');
    flash($problem ?: 'We sent you a new code.', $problem ? 'bad' : 'ok');
    redirect('/login/code');
});

route('POST', '/login/code', function () {
    $staff = Db::one('SELECT * FROM staff WHERE id = ? AND active = 1', [$_SESSION['pending_staff'] ?? 0]);
    if (!$staff) redirect('/login');
    if (!throttle('otp:try:' . client_ip(), 40, 3600) || !Otp::verify('admin', $staff['email'], 0, post('code'))) {
        flash('That code is not right or has expired.', 'bad');
        redirect('/login/code');
    }
    ad_sign_in($staff);
});

route('POST', '/logout', function () {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/login');
});

/* ---------- projects ---------- */

route('GET', '/', function () {
    ad_need();
    $projects = Db::all("SELECT p.*, c.name AS client_name,
        (SELECT COUNT(*) FROM photos WHERE project_id = p.id AND status = 'pending') AS n_pending,
        (SELECT COUNT(*) FROM photos WHERE project_id = p.id AND status = 'approved') AS n_approved,
        (SELECT COUNT(*) FROM photos WHERE project_id = p.id AND status = 'rejected') AS n_rejected,
        (SELECT COUNT(*) FROM rejections WHERE project_id = p.id) AS n_discarded,
        (SELECT COUNT(*) FROM respondents WHERE project_id = p.id) AS n_people
        FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.id DESC");
    $warnings = [];
    if (!Mailer::isSetUp()) $warnings[] = 'Email is not set up yet, so respondents and clients cannot receive their sign-in codes. Open Settings to add your email (SMTP) details.';
    if (!is_file((string) cfg('python'))) $warnings[] = 'The photo scanner is not installed (Python was not found). Uploads will fail until it is.';
    elseif (!is_file(cfg('models') . '/resnet_50_1by2_nsfw.caffemodel')) $warnings[] = 'One of the two safety models is missing, so the safety scan is weaker than it should be. Run pw-deploy again to fetch it.';
    view('admin/projects', ['title' => 'Projects', 'nav' => ad_nav('projects'), 'projects' => $projects, 'warnings' => $warnings]);
});

route('GET', '/projects/new', function () {
    ad_need(true);
    $blank = ['id' => 0, 'name' => '', 'slug' => '', 'client_id' => null, 'status' => 'draft', 'terms' => ad_default_terms(),
        'intro' => 'We are collecting everyday photos for a research project. Choose from the subjects below and upload them straight from your iPhone.',
        'wanted' => "Portraits and human activities\nOutdoor landscapes\nIndoor environments\nAnimals and pets\nFood and dining\nStill life\nMacro and texture photography",
        'photos_per_person' => 0, 'daily_limit' => 0, 'target_photos' => 0, 'reward_amount' => 1, 'reward_photos' => 5, 'reward_symbol' => '$', 'opens_at' => null, 'closes_at' => null, 'min_iphone' => 16,
        'extra_models' => "iPhone Air\niPhone Duo", 'strict_original' => 1, 'min_megapixels' => 8, 'blur_min' => 25, 'dark_min' => 25,
        'bright_max' => 240, 'dup_distance' => 4, 'safety_block' => 0.85, 'safety_review' => 0.30, 'terms_version' => 1,
        'client_approval' => 0, 'client_bulk' => 1, 'bulk_upload' => 1];
    view('admin/project_form', ['title' => 'New project', 'nav' => ad_nav('projects'), 'p' => $blank, 'clients' => Db::all('SELECT id, name FROM clients ORDER BY name')]);
});

route('POST', '/projects/new', function () {
    ad_need(true);
    [$data, $errors] = ad_project_input(null);
    if ($errors) {
        flash(implode(' ', $errors), 'bad');
        view('admin/project_form', ['title' => 'New project', 'nav' => ad_nav('projects'), 'p' => $data + ['id' => 0, 'terms_version' => 1], 'clients' => Db::all('SELECT id, name FROM clients ORDER BY name')]);
    }
    $data['created_at'] = now();
    $cols = array_keys($data);
    $id = Db::insert('INSERT INTO projects (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')', array_values($data));
    audit('project.create', $data['name']);
    flash('Project created.');
    redirect("/projects/$id");
});

/** What every contributor of a project has earned together, as text ("$12"), or '' when the project pays nothing. */
function ad_reward_total(array $p): string
{
    $rw = reward($p);
    if (!$rw) return '';
    $groups = 0;
    foreach (Db::all("SELECT COUNT(*) AS n FROM photos WHERE project_id = ? AND status = 'approved' GROUP BY respondent_id", [$p['id']]) as $row) {
        $groups += intdiv((int) $row['n'], $rw['every']);
    }
    return money($rw['symbol'], $groups * $rw['value']);
}

route('GET', '/projects/{id}', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $counts = Db::one("SELECT COALESCE(SUM(status = 'pending'), 0) AS pending, COALESCE(SUM(status = 'approved'), 0) AS approved, COALESCE(SUM(status = 'rejected'), 0) AS rejected,
        COALESCE(SUM(status = 'pending' AND safety = 'review'), 0) AS flagged, COALESCE(SUM(IF(status = 'approved', bytes, 0)), 0) AS approved_bytes
        FROM photos WHERE project_id = ?", [$p['id']]);
    $why = Db::all('SELECT code, COUNT(*) AS n FROM rejections WHERE project_id = ? GROUP BY code ORDER BY n DESC', [$p['id']]);
    $people = (int) Db::val('SELECT COUNT(*) FROM respondents WHERE project_id = ?', [$p['id']]);
    $client = $p['client_id'] ? Db::one('SELECT * FROM clients WHERE id = ?', [$p['client_id']]) : null;
    view('admin/project', ['title' => $p['name'], 'nav' => ad_nav('projects'), 'p' => $p, 'counts' => $counts, 'why' => $why, 'people' => $people, 'client' => $client,
        'state' => Intake::allowance($p, ['id' => 0, 'blocked' => 0])['state'], 'byCategory' => Tracker::byCategory($p), 'rewardTotal' => ad_reward_total($p),
        'turned' => array_sum(array_column($why, 'n')), 'collected' => Intake::collected($p),
        'lastUpload' => Db::val("SELECT MAX(created_at) FROM photos WHERE project_id = ? AND status <> 'removed'", [$p['id']]),
        'asked' => (int) Db::val("SELECT COUNT(*) FROM download_requests WHERE project_id = ? AND status = 'pending'", [$p['id']])]);
});

route('GET', '/projects/{id}/edit', function (string $id) {
    ad_need(true);
    view('admin/project_form', ['title' => 'Project settings', 'nav' => ad_nav('projects'), 'p' => ad_project($id), 'clients' => Db::all('SELECT id, name FROM clients ORDER BY name')]);
});

route('POST', '/projects/{id}/edit', function (string $id) {
    ad_need(true);
    $p = ad_project($id);
    [$data, $errors] = ad_project_input($p);
    if ($errors) {
        flash(implode(' ', $errors), 'bad');
        view('admin/project_form', ['title' => 'Project settings', 'nav' => ad_nav('projects'), 'p' => $data + ['id' => $p['id'], 'terms_version' => $p['terms_version']], 'clients' => Db::all('SELECT id, name FROM clients ORDER BY name')]);
    }
    if (post('terms_changed') === '1') $data['terms_version'] = (int) $p['terms_version'] + 1;
    $set = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($data)));
    Db::q("UPDATE projects SET $set WHERE id = ?", [...array_values($data), $p['id']]);
    audit('project.update', $data['name']);
    flash('Project saved.');
    redirect('/projects/' . $p['id']);
});

/* ---------- photo review ---------- */

route('GET', '/projects/{id}/photos', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
    $where = 'ph.project_id = ? AND ph.status = ?';
    $params = [$p['id'], $status];
    if (($_GET['flag'] ?? '') === '1') $where .= " AND ph.safety = 'review'";
    if (($rid = (int) ($_GET['person'] ?? 0)) > 0) {
        $where .= ' AND ph.respondent_id = ?';
        $params[] = $rid;
    }
    $total = (int) Db::val("SELECT COUNT(*) FROM photos ph WHERE $where", $params);
    [$offset, $pages, $page] = paging($total, 48, (int) ($_GET['page'] ?? 1));
    $photos = Db::all("SELECT ph.*, r.email, r.full_name FROM photos ph JOIN respondents r ON r.id = ph.respondent_id WHERE $where ORDER BY ph.safety = 'review' DESC, ph.id LIMIT 48 OFFSET $offset", $params);
    view('admin/photos', ['title' => 'Review photos', 'nav' => ad_nav('projects'), 'p' => $p, 'photos' => $photos, 'status' => $status, 'total' => $total, 'pages' => $pages, 'page' => $page]);
});

function ad_decide(array $photo, string $action, string $reason, array $me): void
{
    if ($action === 'approve' && $photo['status'] === 'pending') {
        Db::q("UPDATE photos SET status = 'approved', reject_reason = '', reviewed_by = ?, reviewed_at = ? WHERE id = ?", [$me['id'], now(), $photo['id']]);
    } elseif ($action === 'reject' && in_array($photo['status'], ['pending', 'approved'], true)) {
        Storage::removeFiles($photo);
        Db::q("UPDATE photos SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?",
            [mb_substr($reason !== '' ? $reason : 'Did not meet the project rules', 0, 255), $me['id'], now(), $photo['id']]);
    } elseif ($action === 'pending' && $photo['status'] === 'approved') {
        Db::q("UPDATE photos SET status = 'pending', reviewed_by = ?, reviewed_at = ? WHERE id = ?", [$me['id'], now(), $photo['id']]);
    }
}

route('POST', '/projects/{id}/photos/bulk', function (string $id) {
    $me = ad_need();
    $p = ad_project($id);
    $ids = array_slice(array_map('intval', (array) ($_POST['ids'] ?? [])), 0, 500);
    $action = post('action');
    if (!$ids || !in_array($action, ['approve', 'reject'], true)) {
        flash('Select at least one photo first.', 'bad');
        redirect("/projects/{$p['id']}/photos" . (post('back') !== '' ? '?' . ltrim(post('back'), '?') : ''));
    }
    $n = 0;
    foreach ($ids as $pid) {
        $photo = Db::one('SELECT * FROM photos WHERE id = ? AND project_id = ?', [$pid, $p['id']]);
        if (!$photo) continue;
        ad_decide($photo, $action, post('reason'), $me);
        $n++;
    }
    audit('photo.' . $action, "$n photos in {$p['name']}");
    flash($n . ($n === 1 ? ' photo ' : ' photos ') . ($action === 'approve' ? 'approved.' : 'rejected and deleted.'));
    redirect("/projects/{$p['id']}/photos" . (post('back') !== '' ? '?' . ltrim(post('back'), '?') : ''));
});

route('GET', '/photos/{id}', function (string $id) {
    ad_need();
    $photo = Db::one('SELECT ph.*, r.email, r.full_name, r.country, r.phone, r.iphone_model FROM photos ph JOIN respondents r ON r.id = ph.respondent_id WHERE ph.id = ?', [(int) $id]);
    if (!$photo) fail_page(404, 'We could not find that photo.');
    $p = ad_project((string) $photo['project_id']);
    $next = Db::val("SELECT id FROM photos WHERE project_id = ? AND status = 'pending' AND id > ? ORDER BY id LIMIT 1", [$p['id'], $photo['id']]);
    view('admin/photo', ['title' => 'Photo ' . $photo['id'], 'nav' => ad_nav('projects'), 'p' => $p, 'photo' => $photo, 'meta' => json_decode($photo['meta_json'], true) ?: [], 'next' => $next,
        'seen' => ClientLog::photo((int) $photo['id'])]);
});

route('POST', '/photos/{id}/decide', function (string $id) {
    $me = ad_need();
    $photo = Db::one('SELECT * FROM photos WHERE id = ?', [(int) $id]);
    if (!$photo) fail_page(404, 'We could not find that photo.');
    $action = post('action');
    ad_decide($photo, $action, post('reason'), $me);
    audit('photo.' . $action, 'photo ' . $photo['id']);
    $next = Db::val("SELECT id FROM photos WHERE project_id = ? AND status = 'pending' AND id > ? ORDER BY id LIMIT 1", [$photo['project_id'], $photo['id']]);
    flash($action === 'approve' ? 'Photo approved.' : ($action === 'reject' ? 'Photo rejected and deleted.' : 'Photo moved back to review.'));
    redirect($next && $action !== 'pending' ? "/photos/$next" : "/projects/{$photo['project_id']}/photos");
});

route('GET', '/img/{token}/{size}', function (string $token, string $size) {
    ad_need();
    $photo = Db::one('SELECT * FROM photos WHERE token = ?', [$token]);
    if (!$photo) fail_page(404, 'We could not find that photo.');
    session_write_close();
    if ($size === 'thumb') Storage::send(Storage::thumb($photo), 'image/jpeg');
    if ($size === 'preview') Storage::send(Storage::preview($photo), 'image/jpeg');
    if ($size === 'original') {
        audit('photo.download', 'photo ' . $photo['id']);
        Storage::sendOriginal($photo);
    }
    fail_page(404, 'We could not find that file.');
});

/* ---------- tracker: every photo with its status, totals, CSV ---------- */

function ad_tracker_filters(): array
{
    return ['status' => (string) ($_GET['status'] ?? ''), 'category' => trim((string) ($_GET['category'] ?? '')), 'person' => (int) ($_GET['person'] ?? 0)];
}

route('GET', '/projects/{id}/tracker', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $f = ad_tracker_filters();
    $total = Tracker::count($p, $f);
    [$offset, $pages, $page] = paging($total, 100, (int) ($_GET['page'] ?? 1));
    view('admin/tracker', ['title' => 'Tracker', 'nav' => ad_nav('projects'), 'p' => $p, 'f' => $f, 'total' => $total, 'pages' => $pages, 'page' => $page,
        'rows' => Tracker::rows($p, $f, 100, $offset), 'byCategory' => Tracker::byCategory($p), 'byContributor' => Tracker::byContributor($p),
        'cats' => project_categories($p)]);
});

route('GET', '/projects/{id}/tracker.csv', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $which = (string) ($_GET['table'] ?? 'photos');
    audit('tracker.export', $p['name'] . ' (' . $which . ')');
    Tracker::sendCsv($p, $which, ad_tracker_filters());
});

// Staff can file a photo under another category, for example one uploaded before categories existed.
route('POST', '/photos/{id}/category', function (string $id) {
    ad_need();
    $photo = Db::one('SELECT id, project_id FROM photos WHERE id = ?', [(int) $id]);
    if (!$photo) fail_page(404, 'We could not find that photo.');
    $category = trim((string) ($_POST['category'] ?? ''));
    if (in_array($category, project_categories(ad_project((string) $photo['project_id'])), true)) {
        Db::q('UPDATE photos SET category = ?, category_auto = 0 WHERE id = ?', [$category, $photo['id']]);
        audit('photo.category', 'photo ' . $photo['id'] . ': ' . $category);
        flash('Category saved.');
    } else {
        flash('Choose one of the categories of this project.', 'bad');
    }
    redirect('/photos/' . $photo['id']);
});

/* ---------- respondents, discarded uploads, downloads ---------- */

route('GET', '/projects/{id}/respondents', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $q = trim((string) ($_GET['q'] ?? ''));
    $show = in_array($_GET['show'] ?? '', ['review', 'accepted', 'blocked'], true) ? (string) $_GET['show'] : '';
    $orders = ['new' => 'r.id DESC', 'accepted' => 'n_approved DESC, r.id DESC', 'review' => 'n_pending DESC, r.id DESC', 'name' => "r.full_name = '', r.full_name, r.id",
        'reward' => 'n_approved DESC, r.id DESC'];
    $sort = isset($orders[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : 'new';
    $where = 'r.project_id = ?';
    $params = [$p['id']];
    if ($q !== '') {
        $where .= ' AND (r.email LIKE ? OR r.full_name LIKE ?';
        array_push($params, "%$q%", "%$q%");
        if (($byId = contributor_from_id($q)) !== null) { // a contributor ID, with or without its prefix
            $where .= ' OR r.id = ?';
            $params[] = $byId;
        }
        $where .= ')';
    }
    if ($show === 'review') $where .= " AND EXISTS (SELECT 1 FROM photos WHERE respondent_id = r.id AND status = 'pending')";
    if ($show === 'accepted') $where .= " AND EXISTS (SELECT 1 FROM photos WHERE respondent_id = r.id AND status = 'approved')";
    if ($show === 'blocked') $where .= ' AND r.blocked = 1';
    $total = (int) Db::val("SELECT COUNT(*) FROM respondents r WHERE $where", $params);
    [$offset, $pages, $page] = paging($total, 50, (int) ($_GET['page'] ?? 1));
    $rows = Db::all("SELECT r.*,
        (SELECT COUNT(*) FROM photos WHERE respondent_id = r.id AND status = 'pending') AS n_pending,
        (SELECT COUNT(*) FROM photos WHERE respondent_id = r.id AND status = 'approved') AS n_approved,
        (SELECT COUNT(*) FROM photos WHERE respondent_id = r.id AND status = 'rejected') AS n_rejected,
        (SELECT COUNT(*) FROM rejections WHERE respondent_id = r.id) AS n_discarded
        FROM respondents r WHERE $where ORDER BY {$orders[$sort]} LIMIT 50 OFFSET $offset", $params);
    $sum = [
        'people' => (int) Db::val('SELECT COUNT(*) FROM respondents WHERE project_id = ?', [$p['id']]),
        'with_accepted' => (int) Db::val("SELECT COUNT(DISTINCT respondent_id) FROM photos WHERE project_id = ? AND status = 'approved'", [$p['id']]),
        'with_review' => (int) Db::val("SELECT COUNT(DISTINCT respondent_id) FROM photos WHERE project_id = ? AND status = 'pending'", [$p['id']]),
        'reward' => ad_reward_total($p),
    ];
    view('admin/respondents', ['title' => 'Contributors', 'nav' => ad_nav('projects'), 'p' => $p, 'rows' => $rows, 'q' => $q, 'sort' => $sort, 'show' => $show,
        'total' => $total, 'pages' => $pages, 'page' => $page, 'sum' => $sum]);
});

route('GET', '/projects/{id}/respondents.csv', function (string $id) {
    ad_need(true);
    $p = ad_project($id);
    $rows = Db::all("SELECT r.id, r.email, r.full_name, r.country, r.phone, r.iphone_model, r.terms_version, r.terms_agreed_at, r.created_at, r.blocked,
        (SELECT COUNT(*) FROM photos WHERE respondent_id = r.id AND status = 'approved') AS approved,
        (SELECT COUNT(*) FROM photos WHERE respondent_id = r.id AND status = 'pending') AS in_review
        FROM respondents r WHERE r.project_id = ? ORDER BY r.id", [$p['id']]);
    audit('respondents.export', $p['name']);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="respondents-' . $p['slug'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_keys($rows[0] ?? ['id' => 0]));
    foreach ($rows as $r) {
        $r['id'] = contributor_id($r['id']);
        fputcsv($out, array_map(static fn($v) => preg_match('/^[=+@]/', (string) $v) ? "'" . $v : $v, $r));
    }
    exit;
});

route('POST', '/respondents/{id}/block', function (string $id) {
    ad_need();
    $r = Db::one('SELECT * FROM respondents WHERE id = ?', [(int) $id]);
    if (!$r) fail_page(404, 'We could not find that person.');
    Db::q('UPDATE respondents SET blocked = 1 - blocked WHERE id = ?', [$r['id']]);
    audit($r['blocked'] ? 'respondent.unblock' : 'respondent.block', $r['email']);
    flash($r['blocked'] ? 'This person can upload again.' : 'This person can no longer upload.');
    redirect("/projects/{$r['project_id']}/respondents");
});

route('GET', '/projects/{id}/rejected', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $where = 'x.project_id = ?';
    $params = [$p['id']];
    if (($code = (string) ($_GET['code'] ?? '')) !== '') {
        $where .= ' AND x.code = ?';
        $params[] = $code;
    }
    $total = (int) Db::val("SELECT COUNT(*) FROM rejections x WHERE $where", $params);
    [$offset, $pages, $page] = paging($total, 50, (int) ($_GET['page'] ?? 1));
    $rows = Db::all("SELECT x.*, r.email FROM rejections x JOIN respondents r ON r.id = x.respondent_id WHERE $where ORDER BY x.id DESC LIMIT 50 OFFSET $offset", $params);
    $codes = Db::all('SELECT code, COUNT(*) AS n FROM rejections WHERE project_id = ? GROUP BY code ORDER BY n DESC', [$p['id']]);
    view('admin/rejected', ['title' => 'Turned away', 'nav' => ad_nav('projects'), 'p' => $p, 'rows' => $rows, 'codes' => $codes, 'code' => $code, 'total' => $total, 'pages' => $pages, 'page' => $page]);
});

route('GET', '/projects/{id}/metadata.csv', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $statuses = ($_GET['status'] ?? '') === 'all' ? ['pending', 'approved'] : ['approved'];
    audit('metadata.export', $p['name']);
    Export::sendCsv(Export::photos((int) $p['id'], $statuses), true, 'metadata-' . $p['slug'] . '.csv');
});

route('POST', '/projects/{id}/zip', function (string $id) {
    ad_need();
    $p = ad_project($id);
    $photos = Export::photos((int) $p['id'], ['approved']);
    if (!$photos) {
        flash('There are no approved photos to download yet.', 'bad');
        redirect('/projects/' . $p['id']);
    }
    audit('zip.download', count($photos) . " photos from {$p['name']}");
    Export::sendZip($p, $photos, true);
});

/* ---------- clients ---------- */

route('GET', '/clients', function () {
    ad_need(true);
    $clients = Db::all('SELECT c.*, (SELECT COUNT(*) FROM projects WHERE client_id = c.id) AS n_projects FROM clients c ORDER BY c.name');
    $users = [];
    foreach (Db::all('SELECT * FROM client_users ORDER BY email') as $u) $users[$u['client_id']][] = $u;
    view('admin/clients', ['title' => 'Clients', 'nav' => ad_nav('clients'), 'clients' => $clients, 'users' => $users]);
});

route('POST', '/clients', function () {
    ad_need(true);
    $name = mb_substr(post('name'), 0, 160);
    if (mb_strlen($name) < 2) {
        flash('Give the client a name.', 'bad');
    } else {
        Db::q('INSERT INTO clients (name, created_at) VALUES (?, ?)', [$name, now()]);
        audit('client.create', $name);
        flash('Client added. Now add the people who may sign in, and link the client to a project in the project settings.');
    }
    redirect('/clients');
});

route('POST', '/clients/{id}/users', function (string $id) {
    ad_need(true);
    $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $id]);
    $email = clean_email(post('email'));
    $name = mb_substr(post('name'), 0, 120);
    if (!$client || !$email || $name === '') {
        flash('Enter a name and a valid email address.', 'bad');
    } elseif (Db::val('SELECT id FROM client_users WHERE email = ?', [$email])) {
        flash('That email address already has client access.', 'bad');
    } else {
        Db::q('INSERT INTO client_users (client_id, email, name, created_at) VALUES (?,?,?,?)', [$client['id'], $email, $name, now()]);
        audit('client.user.add', "$email for {$client['name']}");
        flash("$email can now sign in at " . cfg('hosts.client') . ' with an emailed code.');
    }
    redirect('/clients');
});

route('POST', '/client-users/{id}/toggle', function (string $id) {
    ad_need(true);
    $u = Db::one('SELECT * FROM client_users WHERE id = ?', [(int) $id]);
    if ($u) {
        Db::q('UPDATE client_users SET active = 1 - active WHERE id = ?', [$u['id']]);
        audit($u['active'] ? 'client.user.disable' : 'client.user.enable', $u['email']);
        flash($u['active'] ? 'Access switched off.' : 'Access switched on.');
    }
    redirect('/clients');
});

/* ---------- clients: download requests ---------- */

route('GET', '/clients/requests', function () {
    ad_need(true);
    $rows = Db::all("SELECT r.*, p.name AS project_name, u.name AS user_name, u.email AS user_email, c.name AS client_name, s.name AS staff_name
        FROM download_requests r JOIN projects p ON p.id = r.project_id JOIN client_users u ON u.id = r.client_user_id JOIN clients c ON c.id = r.client_id
        LEFT JOIN staff s ON s.id = r.decided_by
        ORDER BY r.status = 'pending' DESC, r.id DESC LIMIT 200");
    foreach ($rows as &$r) {
        $r['thumbs'] = Db::all('SELECT ph.id, ph.token, ph.status FROM download_request_photos d JOIN photos ph ON ph.id = d.photo_id WHERE d.request_id = ? ORDER BY ph.id LIMIT 10', [$r['id']]);
    }
    unset($r);
    view('admin/client_requests', ['title' => 'Download requests', 'nav' => ad_nav('clients'), 'rows' => $rows, 'days' => ClientLog::VALID_DAYS]);
});

route('POST', '/clients/requests/{id}/decide', function (string $id) {
    $me = ad_need(true);
    $r = Db::one('SELECT r.*, p.name AS project_name, u.email AS user_email, u.name AS user_name FROM download_requests r JOIN projects p ON p.id = r.project_id JOIN client_users u ON u.id = r.client_user_id WHERE r.id = ?', [(int) $id]);
    $do = post('do');
    $reply = mb_substr(trim(post('reply')), 0, 500);
    if (!$r || !in_array($do, ['approve', 'decline'], true) || ($do === 'approve' && $r['status'] !== 'pending') || ($do === 'decline' && $r['status'] === 'declined')) {
        flash('That request has already been decided.', 'bad');
        redirect('/clients/requests');
    }
    $what = (int) $r['photos'] === 1 ? '1 photo' : $r['photos'] . ' photos';
    $link = area_url('client', '/requests');
    if ($do === 'approve') {
        $until = date('Y-m-d H:i:s', time() + ClientLog::VALID_DAYS * 86400);
        Db::q("UPDATE download_requests SET status = 'approved', reply = ?, decided_by = ?, decided_at = ?, expires_at = ? WHERE id = ?", [$reply, $me['id'], now(), $until, $r['id']]);
        audit('download.approve', "$what from {$r['project_name']} for {$r['user_email']} (request {$r['id']})");
        $sent = Mailer::send($r['user_email'], "Your download is approved: $what from {$r['project_name']}",
            "Hello {$r['user_name']},\n\nYour request to download $what from \"{$r['project_name']}\" is approved." . ($reply !== '' ? "\n\n$reply" : '')
            . "\n\nThe download waits for you here until " . nice_date($until) . ":\n$link\n\n" . cfg('company', 'Research and Metric Corporation'));
        flash("Approved. {$r['user_email']} can download $what for " . ClientLog::VALID_DAYS . ' days' . ($sent ? ' and was told by email.' : '. The email could not be sent, so please tell them yourself.'), $sent ? 'ok' : 'bad');
    } else {
        $was = $r['status'];
        Db::q("UPDATE download_requests SET status = 'declined', reply = ?, decided_by = ?, decided_at = ?, expires_at = NULL WHERE id = ?", [$reply, $me['id'], now(), $r['id']]);
        audit($was === 'approved' ? 'download.withdraw' : 'download.decline', "$what from {$r['project_name']} for {$r['user_email']} (request {$r['id']})");
        $sent = Mailer::send($r['user_email'], ($was === 'approved' ? 'Your download is no longer available' : 'Your download request was declined') . ": $what from {$r['project_name']}",
            "Hello {$r['user_name']},\n\n" . ($was === 'approved' ? "The approved download of $what from \"{$r['project_name']}\" has been withdrawn." : "Your request to download $what from \"{$r['project_name']}\" was declined.")
            . ($reply !== '' ? "\n\n$reply" : '') . "\n\nYour requests are listed here:\n$link\n\n" . cfg('company', 'Research and Metric Corporation'));
        flash(($was === 'approved' ? 'The approval is withdrawn.' : 'Declined.') . ($sent ? " {$r['user_email']} was told by email." : ' The email could not be sent, so please tell them yourself.'), $sent ? 'ok' : 'bad');
    }
    redirect('/clients/requests');
});

/* ---------- clients: what they did in the portal ---------- */

function ad_activity_filter(): array
{
    return ['user' => (int) ($_GET['user'] ?? 0), 'action' => (string) ($_GET['action'] ?? ''), 'from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? '')];
}

route('GET', '/clients/activity', function () {
    ad_need(true);
    $f = ad_activity_filter();
    $total = ClientLog::count($f);
    [$offset, $pages, $page] = paging($total, 100, (int) ($_GET['page'] ?? 1));
    view('admin/client_activity', ['title' => 'Client activity', 'nav' => ad_nav('clients'), 'people' => ClientLog::people(), 'f' => $f,
        'rows' => ClientLog::rows($f, 100, $offset), 'total' => $total, 'pages' => $pages, 'page' => $page, 'located' => Guard::settings()['ip_key'] !== '']);
});

route('GET', '/clients/activity.csv', function () {
    ad_need(true);
    audit('client.activity.export');
    ClientLog::sendCsv(ad_activity_filter());
});

/* ---------- staff ---------- */

route('GET', '/staff', function () {
    ad_need(true);
    view('admin/staff', ['title' => 'Staff', 'nav' => ad_nav('staff'), 'staff' => Db::all('SELECT * FROM staff ORDER BY email')]);
});

route('POST', '/staff', function () {
    ad_need(true);
    $email = clean_email(post('email'));
    $name = mb_substr(post('name'), 0, 120);
    $pass = (string) ($_POST['password'] ?? '');
    if (!$email || $name === '' || strlen($pass) < 10) {
        flash('Enter a name, a valid email and a password of at least 10 characters.', 'bad');
    } elseif (Db::val('SELECT id FROM staff WHERE email = ?', [$email])) {
        flash('That email address is already a staff account.', 'bad');
    } else {
        Db::q('INSERT INTO staff (email, name, pass_hash, role, created_at) VALUES (?,?,?,?,?)',
            [$email, $name, password_hash($pass, PASSWORD_DEFAULT), post('role') === 'reviewer' ? 'reviewer' : 'admin', now()]);
        audit('staff.create', $email);
        flash('Staff account created.');
    }
    redirect('/staff');
});

route('POST', '/staff/{id}/password', function (string $id) {
    ad_need(true);
    $pass = (string) ($_POST['password'] ?? '');
    $s = Db::one('SELECT * FROM staff WHERE id = ?', [(int) $id]);
    if (!$s || strlen($pass) < 10) {
        flash('The new password must have at least 10 characters.', 'bad');
    } else {
        Db::q('UPDATE staff SET pass_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $s['id']]);
        audit('staff.password', $s['email']);
        flash('Password changed for ' . $s['email'] . '.');
    }
    redirect('/staff');
});

route('POST', '/staff/{id}/toggle', function (string $id) {
    $me = ad_need(true);
    $s = Db::one('SELECT * FROM staff WHERE id = ?', [(int) $id]);
    if ($s && (int) $s['id'] !== (int) $me['id']) {
        Db::q('UPDATE staff SET active = 1 - active WHERE id = ?', [$s['id']]);
        audit($s['active'] ? 'staff.disable' : 'staff.enable', $s['email']);
        flash($s['active'] ? 'Account switched off.' : 'Account switched on.');
    } else {
        flash('You cannot switch off your own account.', 'bad');
    }
    redirect('/staff');
});

/* ---------- settings: email for sign-in codes ---------- */

route('GET', '/settings', function () {
    $me = ad_need(true);
    $m = Settings::mail();
    view('admin/settings', ['title' => 'Settings', 'nav' => ad_nav('settings'), 'm' => $m, 'me' => $me,
        'hasPass' => ($m['smtp']['pass'] ?? '') !== '', 'hasToken' => ($m['postmark_token'] ?? '') !== '',
        'models' => picture_subjects(),
        'gemini' => Gemini::settings(), 'made' => $_SESSION['made'] ?? [], 'guard' => Guard::settings(),
        'invite' => (Settings::get('invite') ?? []) + ['on' => true, 'title' => 'Join Reward Hours', 'text' => 'Reward Hours is our research community. Join to be invited to more studies from Research and Metric.', 'button' => 'Visit Reward Hours', 'link' => 'https://rewardhours.com']]);
});

/* ---------- settings: checks at sign-in (IPzentra, BounceBell) and the Reward Hours invitation ---------- */

route('POST', '/settings/guard', function () {
    ad_need(true);
    $old = Guard::settings();
    $ipKey = trim((string) ($_POST['ip_key'] ?? ''));
    $mailKey = trim((string) ($_POST['mail_key'] ?? ''));
    $new = [
        'ip_on' => !empty($_POST['ip_on']), 'ip_key' => Settings::seal(!empty($_POST['ip_forget']) ? '' : ($ipKey !== '' ? $ipKey : $old['ip_key'])),
        'mail_on' => !empty($_POST['mail_on']), 'mail_key' => Settings::seal(!empty($_POST['mail_forget']) ? '' : ($mailKey !== '' ? $mailKey : $old['mail_key'])),
    ];
    Settings::set('guard', $new);
    unset($_SESSION['ipcheck']);
    audit('settings.guard', 'connection check ' . ($new['ip_on'] ? 'on' : 'off') . ', email check ' . ($new['mail_on'] ? 'on' : 'off'));
    $now = Guard::settings();
    // Try each service straight away, so a wrong key shows now and not when the first contributor signs in.
    if ($now['ip_on'] && $now['ip_key'] === '') flash('The connection check is switched on but has no IPzentra API key, so it does nothing yet.', 'bad');
    elseif ($now['ip_key'] !== '') {
        $r = Guard::ip(client_ip(), true);
        if ($r === null || isset($r['_failed'])) flash('IPzentra did not accept the request' . (($r['_message'] ?? '') !== '' ? ': ' . $r['_message'] : '') . '. Until this works, the connection check is skipped.', 'bad');
        elseif (!$r['signals']) flash('IPzentra answered, but this key\'s plan does not include VPN, proxy, Tor and data centre detection, so nobody can be refused. The country is still read.', 'bad');
        else flash('IPzentra works. Your own connection: ' . ($r['country_name'] ?: 'country unknown') . ($r['blocked'] ? ', seen as ' . $r['why'] . ' (a contributor on this connection would be refused).' : ', an ordinary connection.'));
    }
    if ($now['mail_on'] && $now['mail_key'] === '') flash('The email check is switched on but has no BounceBell API key, so it does nothing yet.', 'bad');
    elseif ($now['mail_key'] !== '') {
        $ch = curl_init(rtrim((string) cfg('bouncebell.endpoint', 'https://bouncebell.com/api/v1'), '/') . '/credits');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $now['mail_key'], 'Accept: application/json']]);
        $j = json_decode((string) curl_exec($ch), true);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code === 200 && is_array($j)) flash('BounceBell works. ' . (!empty($j['unlimited']) ? 'This key has unlimited checks.' : 'Credits left: ' . (int) ($j['credits_remaining'] ?? 0) . '.'));
        else flash('BounceBell did not accept the key' . (is_array($j) && ($j['message'] ?? '') !== '' ? ': ' . $j['message'] : " (status $code)") . '. Until this works, the email check is skipped.', 'bad');
    }
    redirect('/settings#checks');
});

route('POST', '/settings/invite', function () {
    ad_need(true);
    $link = trim(post('link'));
    if (!empty($_POST['on']) && !preg_match('~^https://[^\s"<>]+$~', $link)) {
        flash('The link must start with https:// for the invitation to be shown. Nothing was saved.', 'bad');
        redirect('/settings#invite');
    }
    Settings::set('invite', ['on' => !empty($_POST['on']), 'title' => mb_substr(post('title'), 0, 80), 'text' => mb_substr(post('text'), 0, 400),
        'button' => mb_substr(post('button'), 0, 40), 'link' => mb_substr($link, 0, 300)]);
    audit('settings.invite', !empty($_POST['on']) ? 'shown' : 'hidden');
    flash(!empty($_POST['on']) ? 'The invitation is shown on the contributor page and in the accepted-photos email.' : 'The invitation is hidden.');
    redirect('/settings#invite');
});

/* ---------- settings: phone pictures created with Gemini ---------- */

route('GET', '/phone-draft/{slug}', function (string $slug) {
    ad_need(true);
    $f = Gemini::draftFile($slug);
    if (!$f) fail_page(404, 'We could not find that picture.');
    session_write_close();
    Storage::send($f, ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)], null, false);
});

route('POST', '/settings/gemini', function () {
    ad_need(true);
    $key = trim((string) ($_POST['gemini_key'] ?? ''));
    $model = preg_replace('/[^A-Za-z0-9._-]/', '', post('gemini_model')) ?: Gemini::DEFAULT_MODEL;
    if (!empty($_POST['forget_key'])) {
        Settings::set('gemini', ['key' => '', 'model' => $model]);
        audit('settings.gemini', 'key removed');
        flash('The Gemini API key was removed.');
    } elseif ($key === '' && !Gemini::isSetUp()) {
        flash('Paste the Gemini API key first. Nothing was saved.', 'bad');
    } else {
        Settings::set('gemini', ['key' => Settings::seal($key !== '' ? $key : Gemini::settings()['key']), 'model' => $model]);
        audit('settings.gemini', $model);
        flash('Gemini settings saved. You can create a picture now.');
    }
    redirect('/settings#make');
});

route('POST', '/settings/phones/make', function () {
    ad_need(true);
    $models = picture_subjects();
    $slugs = array_map('phone_slug', $models);
    $slug = post('slug');
    $prompt = mb_substr(post('prompt'), 0, 2000);
    $_SESSION['made'] = ['slug' => $slug, 'prompt' => $prompt]; // so the description is still there for another try
    $ok = false;
    if (!in_array($slug, $slugs, true)) {
        $msg = 'Choose what the picture is for.';
    } elseif (mb_strlen($prompt) < 10) {
        $msg = 'Describe the picture you want in a sentence or two.';
    } elseif (!Gemini::isSetUp()) {
        $msg = 'Save the Gemini API key first.';
    } elseif (!throttle('gemini:' . client_ip(), 120, 3600)) {
        $msg = 'Too many pictures in a short time. Please wait a little.';
    } else {
        $model = $models[array_search($slug, $slugs, true)];
        session_write_close(); // the request to Google can take a minute; do not hold up other pages meanwhile
        @set_time_limit(180);
        // {subject} in the description stands for the chosen kind of photo
        $pic = Gemini::picture(str_ireplace('{subject}', $model, $prompt));
        session_start();
        if ($pic) {
            Gemini::saveDraft($slug, $pic);
            audit('settings.phones.make', $slug);
            $ok = true;
            $msg = "Picture created for \"$model\". Check it below, then use it or discard it.";
        } else {
            $msg = 'No picture was created. ' . Gemini::$lastError;
        }
    }
    // "Create for every phone" sends one of these per phone from the page and reads the answer itself
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $msg]);
        exit;
    }
    flash($msg, $ok ? 'ok' : 'bad');
    redirect('/settings#make');
});

route('POST', '/settings/phones/draft', function () {
    ad_need(true);
    $slug = post('slug');
    if (post('action') === 'use_all') {
        $n = 0;
        foreach (picture_subjects() as $model) {
            if (Gemini::useDraft(phone_slug($model))) $n++;
        }
        if ($n) audit('settings.phones.use', "$n pictures");
        flash($n ? "$n " . ($n === 1 ? 'picture is' : 'pictures are') . ' now shown on the project page.' : 'There were no pictures waiting.', $n ? 'ok' : 'bad');
        redirect('/settings#make');
    }
    if (post('action') === 'use' && Gemini::useDraft($slug)) {
        audit('settings.phones.use', $slug);
        flash('The picture is now shown on the project page.');
    } elseif (Gemini::draftFile($slug)) {
        Gemini::dropDraft($slug);
        flash('The picture was discarded.');
    } else {
        flash('That picture is no longer there.', 'bad');
    }
    redirect('/settings#make');
});

route('GET', '/phone/{slug}', function (string $slug) {
    ad_need();
    send_phone_picture($slug);
});

route('POST', '/settings/phones', function () {
    ad_need(true);
    $dir = Storage::dir('phones');
    $saved = $removed = 0;
    $problems = [];
    foreach (picture_subjects() as $model) {
        $slug = phone_slug($model);
        $err = $_FILES['pic']['error'][$slug] ?? UPLOAD_ERR_NO_FILE;
        if (!empty($_POST['remove'][$slug]) && $err === UPLOAD_ERR_NO_FILE) {
            if ($f = phone_picture_file($slug)) {
                unlink($f);
                $removed++;
            }
            continue;
        }
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $tmp = (string) ($_FILES['pic']['tmp_name'][$slug] ?? '');
        $info = $err === UPLOAD_ERR_OK && is_uploaded_file($tmp) ? @getimagesize($tmp) : false;
        $ext = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
        if (!$ext || filesize($tmp) > 25 * 1048576 || $info[0] > 12000 || $info[1] > 12000) {
            $problems[] = $model;
            continue;
        }
        $pic = Gemini::shrink((string) file_get_contents($tmp), $ext); // a full-size camera photo is far more than the page needs
        foreach (['png', 'jpg', 'webp'] as $old) @unlink("$dir/$slug.$old");
        if (file_put_contents("$dir/$slug.{$pic['ext']}", $pic['bytes'], LOCK_EX) !== false) {
            @chmod("$dir/$slug.{$pic['ext']}", 0640);
            $saved++;
        } else {
            $problems[] = $model;
        }
    }
    if ($saved || $removed) audit('settings.phones', "$saved saved, $removed removed");
    if ($problems) flash('These pictures were not saved (use a PNG, JPG or WebP file under 25 MB): ' . implode(', ', $problems) . '.', 'bad');
    if ($saved || $removed) flash(trim(($saved ? "$saved " . ($saved === 1 ? 'picture' : 'pictures') . ' saved. ' : '') . ($removed ? "$removed removed." : '')));
    elseif (!$problems) flash('Choose at least one picture first.', 'bad');
    redirect('/settings#phones');
});

route('POST', '/settings/mail', function () {
    ad_need(true);
    $cur = Settings::mail();
    $driver = in_array(post('driver'), ['log', 'smtp', 'postmark'], true) ? post('driver') : 'log';
    $from = clean_email(post('from_email'));
    $pass = (string) ($_POST['smtp_pass'] ?? '');
    $token = trim((string) ($_POST['postmark_token'] ?? ''));
    $m = [
        'driver' => $driver,
        'from_email' => $from ?? '',
        'from_name' => mb_substr(post('from_name'), 0, 80) ?: 'Photowall',
        'smtp' => [
            'host' => mb_substr(preg_replace('#^\w+://#', '', post('smtp_host')), 0, 190),
            'port' => max(1, min(65535, (int) post('smtp_port') ?: 587)),
            'user' => mb_substr(post('smtp_user'), 0, 190),
            'pass' => Settings::seal($pass !== '' ? $pass : (string) ($cur['smtp']['pass'] ?? '')),
            'secure' => in_array(post('smtp_secure'), ['tls', 'ssl', 'none'], true) ? post('smtp_secure') : 'tls',
        ],
        'postmark_token' => Settings::seal($token !== '' ? $token : (string) ($cur['postmark_token'] ?? '')),
    ];
    $problem = null;
    if ($driver !== 'log' && !$from) $problem = 'Enter the email address the codes should be sent from.';
    elseif ($driver === 'smtp' && $m['smtp']['host'] === '') $problem = 'Enter the SMTP server address.';
    elseif ($driver === 'postmark' && $m['postmark_token'] === '') $problem = 'Enter the Postmark server token.';
    if ($problem) {
        flash($problem . ' Nothing was saved.', 'bad');
    } else {
        Settings::set('mail', $m);
        audit('settings.mail', $driver);
        flash($driver === 'log' ? 'Saved. Email sending is switched off, so nobody can receive a sign-in code.' : 'Email settings saved. Send a test email below to make sure they work.');
    }
    redirect('/settings');
});

route('POST', '/settings/mail/test', function () {
    ad_need(true);
    $to = clean_email(post('to'));
    if (!$to) {
        flash('Enter the address the test email should go to.', 'bad');
    } elseif (!Mailer::isSetUp()) {
        flash('Email sending is not set up yet. Choose SMTP or Postmark, save, then test.', 'bad');
    } elseif (!throttle('mailtest:' . client_ip(), 20, 3600)) {
        flash('Too many test emails in a short time. Please wait a little.', 'bad');
    } elseif (Mailer::send($to, 'Photowall test email', "This is a test from the Photowall console.\n\nIf you are reading it, sign-in codes can be delivered.\n")) {
        flash("The test email was accepted for $to. Check that inbox (and its spam folder).");
    } else {
        flash('The test email could not be sent. ' . Mailer::$lastError, 'bad');
    }
    redirect('/settings');
});

route('GET', '/activity', function () {
    ad_need(true);
    $groups = ['signin' => 'Sign-ins', 'photos' => 'Photo review', 'downloads' => 'Downloads', 'settings' => 'Settings', 'people' => 'People and access'];
    $kind = isset($groups[$_GET['kind'] ?? '']) ? (string) $_GET['kind'] : '';
    $where = '1 = 1';
    $args = [];
    if ($kind !== '') {
        $codes = array_keys(array_filter(activity_words(), static fn(array $w): bool => $w[1] === $kind));
        $where = 'action IN (' . rtrim(str_repeat('?,', count($codes)), ',') . ')';
        $args = $codes;
    }
    $total = (int) Db::val("SELECT COUNT(*) FROM audit_log WHERE $where", $args);
    [$offset, $pages, $page] = paging($total, 100, (int) ($_GET['page'] ?? 1));
    view('admin/activity', ['title' => 'Activity', 'nav' => ad_nav('activity'), 'rows' => Db::all("SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT 100 OFFSET $offset", $args),
        'pages' => $pages, 'page' => $page, 'groups' => $groups, 'kind' => $kind, 'total' => $total]);
});

dispatch();
