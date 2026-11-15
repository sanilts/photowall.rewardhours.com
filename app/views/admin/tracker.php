<?php
/** @var array $p @var array $rows @var array $f @var int $total @var int $pages @var int $page @var array $byCategory @var array $byContributor @var array $cats */
$base = '/projects/' . (int) $p['id'] . '/tracker';
$qs = static fn(array $over) => '?' . http_build_query(array_filter($over + $f, static fn($v) => $v !== '' && $v !== 0));
$chip = ['accepted' => 'approved', 'review' => 'pending', 'rejected' => 'rejected'];
?>
<?php $tab = 'tracker'; require PW_ROOT . '/app/views/admin/project_tabs.php'; ?>
<div class="head">
  <h1>Tracker <span class="muted">(<?= (int) $total ?> photos)</span></h1>
  <a class="btn" href="<?= e($base . '.csv' . $qs([])) ?>"><?= icon('doc') ?> Download as CSV</a>
</div>
<p class="muted">Every photo that reached this project: those waiting for review, those accepted, and those rejected by staff or turned away by an automatic check. Image IDs that start with RM-X are uploads that failed a check, so the file itself was not kept.</p>

<form method="get" action="<?= e($base) ?>" class="row filters">
  <label class="visually-hidden" for="f-status">Status</label>
  <select id="f-status" name="status">
    <option value="">Every status</option>
    <?php foreach (Tracker::STATUS as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <label class="visually-hidden" for="f-category">Category</label>
  <select id="f-category" name="category">
    <option value="">Every category</option>
    <?php foreach ($cats as $c): ?><option<?= $f['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    <option value="-"<?= $f['category'] === '-' ? ' selected' : '' ?>>Not set</option>
  </select>
  <?php if ($f['person']): ?><input type="hidden" name="person" value="<?= (int) $f['person'] ?>"><?php endif; ?>
  <button class="btn" type="submit">Show</button>
  <?php if ($f['status'] !== '' || $f['category'] !== '' || $f['person']): ?><a class="btn btn-quiet" href="<?= e($base) ?>">Clear<?= $f['person'] ? ' (showing ' . e(contributor_id($f['person'])) . ' only)' : '' ?></a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <section class="card center"><p>No photos match.</p></section>
<?php else: ?>
<div class="table-wrap">
<table class="table table-track">
  <thead><tr><th scope="col">Image ID</th><th scope="col">File name</th><th scope="col">Category</th><th scope="col">iPhone model</th><th scope="col">Date taken</th><th scope="col">Country</th><th scope="col">Contributor ID</th><th scope="col">Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row"><?php if ($r['kind'] === 'photo'): ?><a href="/photos/<?= (int) $r['id'] ?>"><?= e($r['image_id']) ?></a><?php else: ?><?= e($r['image_id']) ?><?php endif; ?></th>
      <td class="wrap-any"><?= e($r['orig_name']) ?></td>
      <td><?= e($r['category_label']) ?><?php if ((int) $r['category_auto']): ?><br><span class="muted">detected</span><?php endif; ?></td>
      <td><?= e($r['device_model'] !== '' ? $r['device_model'] : 'Not in the file') ?></td>
      <td><?= $r['taken_at'] ? e(nice_date($r['taken_at'], 'j M Y, H:i')) : '<span class="muted">Not in the file</span>' ?></td>
      <td><?= e((string) $r['country']) ?></td>
      <td class="nowrap"><a href="<?= e($base . $qs(['person' => (int) $r['respondent_id']])) ?>"><?= e($r['contributor_id']) ?></a></td>
      <td><span class="chip chip-<?= $chip[$r['status']] ?>"><?= e($r['status_label']) ?></span><?php if ($r['status'] === 'rejected'): ?><br><span class="muted"><?= e($r['reason'] !== '' ? $r['reason'] : 'No reason given') ?> (<?= e($r['rejected_by']) ?>)</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-small" href="<?= e($base . $qs(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
    <span class="muted">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-small" href="<?= e($base . $qs(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>

<div class="cols">
  <section>
    <div class="head head-sub">
      <h2>Totals by category</h2>
      <a class="btn btn-small" href="<?= e($base) ?>.csv?table=categories">CSV</a>
    </div>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Category</th><th scope="col" class="num">Accepted</th><th scope="col" class="num">In review</th><th scope="col" class="num">Rejected</th><th scope="col" class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($byCategory as $r): ?>
        <tr><th scope="row"><a href="<?= e($base . '?' . http_build_query(['category' => $r['category'] !== '' ? $r['category'] : '-'])) ?>"><?= e($r['category_label']) ?></a></th>
          <td class="num"><?= (int) $r['accepted'] ?></td><td class="num"><?= (int) $r['review'] ?></td><td class="num"><?= (int) $r['rejected'] ?></td><td class="num"><?= (int) $r['total'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byCategory): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </section>
  <section>
    <div class="head head-sub">
      <h2>Totals by contributor</h2>
      <a class="btn btn-small" href="<?= e($base) ?>.csv?table=contributors">CSV</a>
    </div>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Contributor ID</th><th scope="col">Country</th><th scope="col" class="num">Accepted</th><th scope="col" class="num">In review</th><th scope="col" class="num">Rejected</th><th scope="col" class="num">Total</th><?php if (reward($p)): ?><th scope="col" class="num">Reward earned</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($byContributor as $r): ?>
        <tr><th scope="row"><a href="<?= e($base . '?person=' . (int) $r['respondent_id']) ?>"><?= e($r['contributor_id']) ?></a></th><td><?= e((string) $r['country']) ?></td>
          <td class="num"><?= (int) $r['accepted'] ?></td><td class="num"><?= (int) $r['review'] ?></td><td class="num"><?= (int) $r['rejected'] ?></td><td class="num"><?= (int) $r['total'] ?></td><?php if (reward($p)): ?><td class="num"><?= e($r['earned']) ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      <?php if (!$byContributor): ?><tr><td colspan="7" class="muted">Nothing yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </section>
</div>
