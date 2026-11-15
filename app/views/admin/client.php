<?php /** @var array $c @var array $people @var array $projects @var array $sum @var array $recent */
$cid = (int) $c['id'];
$on = (int) $c['active'] === 1;
$names = countries();
?>
<p class="crumbs"><a href="/clients">Clients</a> / <?= e($c['name']) ?></p>
<div class="head client-head">
  <div class="who-link">
    <span class="avatar avatar-big"><?= e(initials($c['name'])) ?></span>
    <div>
      <h1><?= e($c['name']) ?> <?= $on ? '<span class="chip chip-approved">Active</span>' : '<span class="chip chip-rejected">Switched off</span>' ?></h1>
      <p class="muted">Client since <?= e(nice_date($c['created_at'], 'j M Y')) ?><?= $sum['last_login'] ? '. Last sign-in ' . e(nice_date($sum['last_login'])) : '. Nobody has signed in yet' ?>.</p>
    </div>
  </div>
  <a class="btn" href="#manage"><?= icon('pencil') ?> Manage this client</a>
</div>

<?php if (!$on): ?><div class="note note-warn" role="status">This client is switched off. Nobody from it can sign in to the client portal. Switch it on again under "Manage this client".</div><?php endif; ?>

<div class="stats">
  <div class="stat"><span class="stat-n"><?= count($people) ?></span><span><?= count($people) === 1 ? 'Person' : 'People' ?> with access</span></div>
  <div class="stat"><span class="stat-n"><?= count($projects) ?></span><span><?= count($projects) === 1 ? 'Project' : 'Projects' ?> linked</span></div>
  <a class="stat" href="/clients/activity?client=<?= $cid ?>&amp;action=login#timeline"><span class="stat-n"><?= (int) $sum['logins'] ?></span><span>Sign-ins</span></a>
  <a class="stat" href="/clients/activity?client=<?= $cid ?>&amp;action=view#timeline"><span class="stat-n"><?= (int) $sum['viewed'] ?></span><span>Photos viewed</span></a>
  <a class="stat st-ok" href="/clients/activity?client=<?= $cid ?>#timeline"><span class="stat-n"><?= (int) $sum['downloaded'] ?></span><span>Photos downloaded</span></a>
  <a class="stat <?= $sum['waiting'] ? 'st-wait' : '' ?>" href="/clients/requests"><span class="stat-n"><?= (int) $sum['waiting'] ?></span><span>Requests waiting</span></a>
</div>

<section class="card" id="people">
  <div class="head head-sub"><h2>People who can sign in</h2></div>
  <?php if (!$people): ?><p class="muted">Nobody from this client can sign in yet. Add the first person below.</p><?php else: ?>
  <div class="table-wrap table-flat" tabindex="0">
  <table class="table">
    <thead><tr><th scope="col">Person</th><th scope="col">Status</th><th scope="col">Last sign-in</th><th scope="col" class="num">Sign-ins</th><th scope="col" class="num">Viewed</th><th scope="col" class="num">Downloaded</th><th scope="col">Manage</th></tr></thead>
    <tbody>
    <?php foreach ($people as $u): $uid = (int) $u['id']; ?>
      <tr<?= (int) $u['active'] ? '' : ' class="row-off"' ?>>
        <th scope="row"><span class="who-link"><span class="avatar avatar-soft"><?= e(initials($u['name'])) ?></span><span><?= e($u['name']) ?><br><span class="muted"><?= e($u['email']) ?></span></span></span></th>
        <td><?= (int) $u['active'] ? '<span class="chip chip-approved">Can sign in</span>' : '<span class="chip chip-rejected">Switched off</span>' ?></td>
        <td class="nowrap"><?= e($u['last_login_at'] ? nice_date($u['last_login_at']) : 'Never') ?><br><span class="muted"><?= $u['invited_at'] ? 'Invited ' . e(nice_date($u['invited_at'], 'j M Y')) : 'No invitation sent' ?></span></td>
        <td class="num"><a href="/clients/activity?user=<?= $uid ?>#timeline"><?= (int) $u['logins'] ?></a></td>
        <td class="num"><?= (int) $u['viewed'] ?></td>
        <td class="num"><?= (int) $u['downloaded'] ?></td>
        <td>
          <div class="row acts">
            <form method="post" action="/client-users/<?= $uid ?>/invite">
              <?= csrf_field() ?>
              <button class="btn btn-small" type="submit"><?= icon('mail') ?> <?= $u['invited_at'] ? 'Send invitation again' : 'Send invitation' ?></button>
            </form>
            <form method="post" action="/client-users/<?= $uid ?>/toggle">
              <?= csrf_field() ?>
              <button class="btn btn-small" type="submit"><?= (int) $u['active'] ? 'Switch off' : 'Switch on' ?></button>
            </form>
            <form method="post" action="/client-users/<?= $uid ?>/delete">
              <?= csrf_field() ?>
              <button class="btn btn-small btn-danger" type="submit" data-confirm="Remove <?= e($u['name']) ?> (<?= e($u['email']) ?>)? Their download requests and activity are deleted too. To only stop them signing in, use Switch off.">Remove</button>
            </form>
          </div>
          <details class="edit">
            <summary>Change name or email</summary>
            <form method="post" action="/client-users/<?= $uid ?>/edit" class="row">
              <?= csrf_field() ?>
              <label class="visually-hidden" for="en<?= $uid ?>">Name</label>
              <input id="en<?= $uid ?>" name="name" required maxlength="120" value="<?= e($u['name']) ?>">
              <label class="visually-hidden" for="ee<?= $uid ?>">Email</label>
              <input id="ee<?= $uid ?>" name="email" type="email" required maxlength="190" value="<?= e($u['email']) ?>">
              <button class="btn btn-small btn-main" type="submit">Save</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
  <h3 class="sub">Give someone access</h3>
  <form method="post" action="/clients/<?= $cid ?>/users" class="row">
    <?= csrf_field() ?>
    <label class="visually-hidden" for="un">Name</label>
    <input id="un" name="name" required maxlength="120" placeholder="Name">
    <label class="visually-hidden" for="ue">Email</label>
    <input id="ue" name="email" type="email" required maxlength="190" placeholder="Work email">
    <button class="btn btn-main" type="submit"><?= icon('plus') ?> Give access</button>
  </form>
  <p class="muted hint">They get an email with the link to the client portal. They sign in with this email and a 6-digit code we send them. No password is set.</p>
</section>

<div class="cols">
  <section class="card">
    <h2>Projects linked to this client</h2>
    <?php if (!$projects): ?><p class="muted">No project is linked yet. Open a project, go to Settings and choose this client under "Client who may download".</p><?php else: ?>
    <ul class="plain-list">
      <?php foreach ($projects as $p): ?>
        <li>
          <div><a href="/projects/<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a> <span class="chip chip-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span><br>
            <span class="muted"><?= (int) $p['n_approved'] ?> accepted <?= (int) $p['n_approved'] === 1 ? 'photo' : 'photos' ?>. Downloads <?= (int) $p['client_approval'] ? 'need our approval' : 'without asking' ?>, <?= (int) $p['client_bulk'] ? 'many at once allowed' : 'one photo at a time' ?>.</span></div>
          <a class="btn btn-small" href="/projects/<?= (int) $p['id'] ?>/edit">Settings</a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="head head-sub"><h2>Latest activity</h2><a class="btn btn-small" href="/clients/activity?client=<?= $cid ?>#timeline">See all</a></div>
    <?php if (!$recent): ?><p class="muted">Nothing yet. Sign-ins, photos viewed and downloads appear here.</p><?php else: ?>
    <ul class="plain-list">
      <?php foreach ($recent as $r): ?>
        <li><div><strong><?= e(ClientLog::ACTIONS[$r['action']] ?? $r['action']) ?></strong><?= (int) $r['photos'] > 1 ? ' (' . (int) $r['photos'] . ' photos)' : '' ?><br>
          <span class="muted"><?= e($r['name']) ?>, <?= e(nice_date($r['created_at'])) ?><?= $r['country'] !== '' ? ', ' . e($names[$r['country']] ?? $r['country']) : '' ?><?= (string) $r['project_name'] !== '' ? ', ' . e($r['project_name']) : '' ?></span></div></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card" id="manage">
  <h2>Manage this client</h2>
  <div class="manage">
    <div class="manage-row">
      <div><h3>Name</h3><p class="muted">Shown to the client's people in the portal and in emails.</p></div>
      <form method="post" action="/clients/<?= $cid ?>/edit" class="row">
        <?= csrf_field() ?>
        <label class="visually-hidden" for="rename">Client name</label>
        <input id="rename" name="name" required maxlength="160" value="<?= e($c['name']) ?>">
        <button class="btn" type="submit">Save name</button>
      </form>
    </div>
    <div class="manage-row">
      <div><h3><?= $on ? 'Switch this client off' : 'Switch this client on' ?></h3>
        <p class="muted"><?= $on ? 'Nobody from this client can sign in while it is off, and anyone signed in is signed out. Nothing is deleted, and you can switch it on again.' : 'The people marked "Can sign in" can use the client portal again.' ?></p></div>
      <form method="post" action="/clients/<?= $cid ?>/toggle">
        <?= csrf_field() ?>
        <button class="btn<?= $on ? ' btn-danger' : ' btn-main' ?>" type="submit"<?= $on ? ' data-confirm="Switch ' . e($c['name']) . ' off? Nobody from this client can sign in until you switch it on again."' : '' ?>><?= $on ? 'Switch off' : 'Switch on' ?></button>
      </form>
    </div>
    <div class="manage-row manage-danger">
      <div><h3>Delete this client</h3>
        <p class="muted">Removes the company, its <?= count($people) ?> <?= count($people) === 1 ? 'person' : 'people' ?>, their download requests and their activity. <?= count($projects) ? 'Its ' . count($projects) . (count($projects) === 1 ? ' project stays' : ' projects stay') . ' with all photos, but without a client.' : '' ?> This cannot be undone. To keep the records, switch the client off instead.</p></div>
      <form method="post" action="/clients/<?= $cid ?>/delete" class="row">
        <?= csrf_field() ?>
        <label class="visually-hidden" for="confirm-name">Type the client name to confirm</label>
        <input id="confirm-name" name="confirm" required autocomplete="off" placeholder="Type the name: <?= e($c['name']) ?>">
        <button class="btn btn-danger" type="submit">Delete client</button>
      </form>
    </div>
  </div>
</section>
