<?php /** @var array $p @var array $rows @var string $q @var int $total @var int $pages @var int $page */ ?>
<p class="crumbs"><a href="/">Projects</a> / <a href="/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a> / Respondents</p>
<div class="head">
  <h1>Respondents <span class="muted">(<?= (int) $total ?>)</span></h1>
  <form method="get" class="row" role="search">
    <label class="visually-hidden" for="q">Search by name or email</label>
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="Name or email">
    <button class="btn" type="submit">Search</button>
  </form>
</div>
<?php if (!$rows): ?>
  <section class="card center"><p>Nobody has signed in to this project yet.</p></section>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th scope="col">Person</th><th scope="col">Country</th><th scope="col">iPhone</th><th scope="col">Joined</th>
    <th scope="col" class="num">In review</th><th scope="col" class="num">Approved</th><th scope="col" class="num">Rejected</th><th scope="col" class="num">Turned away</th><th scope="col">Uploads</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row"><?= e($r['full_name'] !== '' ? $r['full_name'] : 'Details not filled in') ?><br><span class="muted"><?= e(sprintf('R%05d', $r['id'])) ?> &middot; <?= e($r['email']) ?></span></th>
      <td><?= e($r['country']) ?></td>
      <td><?= e($r['iphone_model']) ?></td>
      <td><?= e(nice_date($r['created_at'], 'j M Y')) ?></td>
      <td class="num"><a href="/projects/<?= (int) $p['id'] ?>/photos?person=<?= (int) $r['id'] ?>"><?= (int) $r['n_pending'] ?></a></td>
      <td class="num"><a href="/projects/<?= (int) $p['id'] ?>/photos?person=<?= (int) $r['id'] ?>&amp;status=approved"><?= (int) $r['n_approved'] ?></a></td>
      <td class="num"><?= (int) $r['n_rejected'] ?></td>
      <td class="num"><?= (int) $r['n_discarded'] ?></td>
      <td>
        <form method="post" action="/respondents/<?= (int) $r['id'] ?>/block"<?= (int) $r['blocked'] ? '' : ' data-confirm="Stop this person from uploading to this project?"' ?>>
          <?= csrf_field() ?>
          <button class="btn btn-small<?= (int) $r['blocked'] ? '' : ' btn-quiet' ?>" type="submit"><?= (int) $r['blocked'] ? 'Blocked. Allow again' : 'Block' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
