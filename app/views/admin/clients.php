<?php /** @var array $clients @var array $users */ ?>
<?php $ctab = 'people'; require PW_ROOT . '/app/views/admin/client_tabs.php'; ?>
<section class="card">
  <h2>Link for clients</h2>
  <div class="copy">
    <input id="client-link" value="<?= e(area_url('client', '/login')) ?>" readonly aria-label="Sign-in link for clients">
    <button class="btn btn-main" type="button" data-copy="client-link">Copy</button>
  </div>
  <p class="muted">A client signs in here with their email and a code we send them. They only see approved photos of the projects linked to their company. Everything they do is listed under Activity.</p>
</section>

<section class="card">
  <h2>Add a client company</h2>
  <form method="post" action="/clients" class="row">
    <?= csrf_field() ?>
    <label class="visually-hidden" for="cname">Company name</label>
    <input id="cname" name="name" required maxlength="160" placeholder="Company name">
    <button class="btn btn-main" type="submit">Add client</button>
  </form>
</section>

<?php foreach ($clients as $c): ?>
<section class="card">
  <h2><?= e($c['name']) ?> <span class="muted">(<?= (int) $c['n_projects'] ?> <?= (int) $c['n_projects'] === 1 ? 'project' : 'projects' ?>)</span></h2>
  <?php if (!empty($users[$c['id']])): ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th scope="col">Person</th><th scope="col">Email</th><th scope="col">Last sign-in</th><th scope="col">Access</th></tr></thead>
    <tbody>
    <?php foreach ($users[$c['id']] as $u): ?>
      <tr>
        <th scope="row"><?= e($u['name']) ?></th>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['last_login_at'] ? nice_date($u['last_login_at']) : 'Never') ?></td>
        <td>
          <form method="post" action="/client-users/<?= (int) $u['id'] ?>/toggle">
            <?= csrf_field() ?>
            <button class="btn btn-small btn-quiet" type="submit"><?= (int) $u['active'] ? 'On. Switch off' : 'Off. Switch on' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="muted">Nobody from this client can sign in yet.</p><?php endif; ?>
  <form method="post" action="/clients/<?= (int) $c['id'] ?>/users" class="row">
    <?= csrf_field() ?>
    <label class="visually-hidden" for="un<?= (int) $c['id'] ?>">Name</label>
    <input id="un<?= (int) $c['id'] ?>" name="name" required maxlength="120" placeholder="Name">
    <label class="visually-hidden" for="ue<?= (int) $c['id'] ?>">Email</label>
    <input id="ue<?= (int) $c['id'] ?>" name="email" type="email" required maxlength="190" placeholder="Email">
    <button class="btn" type="submit">Give access</button>
  </form>
</section>
<?php endforeach; ?>
