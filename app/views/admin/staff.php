<?php /** @var array $staff */ $meId = (int) (ad_me()['id'] ?? 0); ?>
<div class="head"><h1>Staff</h1></div>
<div class="table-wrap">
<table class="table">
  <thead><tr><th scope="col">Person</th><th scope="col">Role</th><th scope="col">Last sign-in</th><th scope="col">New password</th><th scope="col">Account</th></tr></thead>
  <tbody>
  <?php foreach ($staff as $s): ?>
    <tr>
      <th scope="row"><?= e($s['name']) ?><br><span class="muted"><?= e($s['email']) ?></span></th>
      <td><?= $s['role'] === 'admin' ? 'Administrator' : 'Reviewer' ?></td>
      <td><?= e($s['last_login_at'] ? nice_date($s['last_login_at']) : 'Never') ?></td>
      <td>
        <form method="post" action="/staff/<?= (int) $s['id'] ?>/password" class="row">
          <?= csrf_field() ?>
          <label class="visually-hidden" for="pw<?= (int) $s['id'] ?>">New password for <?= e($s['email']) ?></label>
          <input id="pw<?= (int) $s['id'] ?>" name="password" type="password" minlength="10" required autocomplete="new-password" placeholder="At least 10 characters">
          <button class="btn btn-small" type="submit">Change</button>
        </form>
      </td>
      <td>
        <?php if ((int) $s['id'] !== $meId): ?>
        <form method="post" action="/staff/<?= (int) $s['id'] ?>/toggle">
          <?= csrf_field() ?>
          <button class="btn btn-small btn-quiet" type="submit"><?= (int) $s['active'] ? 'On. Switch off' : 'Off. Switch on' ?></button>
        </form>
        <?php else: ?>You<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<section class="card">
  <h2>Add a staff account</h2>
  <form method="post" action="/staff" class="form">
    <?= csrf_field() ?>
    <div class="grid2">
      <div><label for="sname">Name</label><input id="sname" name="name" required maxlength="120"></div>
      <div><label for="semail">Email</label><input id="semail" name="email" type="email" required maxlength="190"></div>
      <div><label for="spass">Password <span class="muted">(at least 10 characters)</span></label><input id="spass" name="password" type="password" minlength="10" required autocomplete="new-password"></div>
      <div><label for="srole">Role</label>
        <select id="srole" name="role">
          <option value="reviewer">Reviewer: can review photos and download</option>
          <option value="admin">Administrator: can also change projects, clients and staff</option>
        </select></div>
    </div>
    <div class="row form-end"><button class="btn btn-main" type="submit">Add staff account</button></div>
  </form>
</section>
