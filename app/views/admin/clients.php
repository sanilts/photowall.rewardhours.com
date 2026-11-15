<?php /** @var array $clients */ $ctab = 'people'; require PW_ROOT . '/app/views/admin/client_tabs.php'; ?>
<div class="cols cols-side">
  <section class="card">
    <h2>Add a client company</h2>
    <form method="post" action="/clients" class="row">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="cname">Company name</label>
      <input id="cname" name="name" required maxlength="160" placeholder="Company name">
      <button class="btn btn-main" type="submit"><?= icon('plus') ?> Add client</button>
    </form>
    <p class="muted hint">Next you add the people who may sign in, and link the client to a project in that project's settings.</p>
  </section>
  <section class="card">
    <h2>Link for clients</h2>
    <div class="copy">
      <input id="client-link" value="<?= e(area_url('client', '/login')) ?>" readonly aria-label="Sign-in link for clients">
      <button class="btn btn-main" type="button" data-copy="client-link">Copy</button>
    </div>
    <p class="muted hint">A client signs in here with their email and a code we send them. They only see approved photos of the projects linked to their company.</p>
  </section>
</div>

<?php if (!$clients): ?>
  <section class="card center"><h2>No clients yet</h2><p>Add the first client company with the form on this page.</p></section>
<?php else: ?>
<div class="table-wrap">
<table class="table table-clients">
  <thead><tr><th scope="col">Client</th><th scope="col">Status</th><th scope="col" class="num">People</th><th scope="col" class="num">Projects</th><th scope="col">Last sign-in</th><th scope="col">Download requests</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
  <tbody>
  <?php foreach ($clients as $c): ?>
    <tr<?= (int) $c['active'] ? '' : ' class="row-off"' ?>>
      <th scope="row"><a class="who-link" href="/clients/<?= (int) $c['id'] ?>"><span class="avatar"><?= e(initials($c['name'])) ?></span><span><?= e($c['name']) ?><br><span class="muted">Client since <?= e(nice_date($c['created_at'], 'j M Y')) ?></span></span></a></th>
      <td><?= (int) $c['active'] ? '<span class="chip chip-approved">Active</span>' : '<span class="chip chip-rejected">Switched off</span>' ?></td>
      <td class="num"><?= (int) $c['n_people'] ?><?php if ((int) $c['n_active'] < (int) $c['n_people']): ?><br><span class="muted"><?= (int) $c['n_people'] - (int) $c['n_active'] ?> off</span><?php endif; ?></td>
      <td class="num"><?= (int) $c['n_projects'] ?></td>
      <td><?= e($c['last_login'] ? nice_date($c['last_login']) : 'Never') ?></td>
      <td><?= (int) $c['n_waiting'] ? '<a class="chip chip-pending" href="/clients/requests">' . (int) $c['n_waiting'] . ' waiting</a>' : '<span class="muted">None waiting</span>' ?></td>
      <td class="num"><a class="btn btn-small" href="/clients/<?= (int) $c['id'] ?>">Manage</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
