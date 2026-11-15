<?php /** @var array $p @var array $rows @var array $codes @var string $code @var int $total @var int $pages @var int $page */ ?>
<?php $tab = 'turned'; require PW_ROOT . '/app/views/admin/project_tabs.php'; ?>
<div class="head">
  <h1>Uploads turned away automatically <span class="muted">(<?= (int) $total ?>)</span></h1>
  <div class="tabs">
    <a href="/projects/<?= (int) $p['id'] ?>/rejected"<?= $code === '' ? ' aria-current="page"' : '' ?>>All</a>
    <?php foreach ($codes as $c): ?>
      <a href="?code=<?= e($c['code']) ?>"<?= $code === $c['code'] ? ' aria-current="page"' : '' ?>><?= e(str_replace('_', ' ', $c['code'])) ?> (<?= (int) $c['n'] ?>)</a>
    <?php endforeach; ?>
  </div>
</div>
<p class="muted">These files failed a check and were deleted straight away. Only the reason and the information read from the file are kept, so you can see why a photo was turned away and adjust the project settings if a rule is too strict.</p>
<?php if (!$rows): ?>
  <section class="card center"><p>Nothing has been turned away.</p></section>
<?php else: ?>
<div class="table-wrap" tabindex="0">
<table class="table">
  <thead><tr><th scope="col">When</th><th scope="col">Person</th><th scope="col">File</th><th scope="col">Phone in the file</th><th scope="col">Why</th><th scope="col">Details</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $meta = json_decode($r['meta_json'], true) ?: []; ?>
    <tr>
      <td><?= e(nice_date($r['created_at'])) ?></td>
      <td><?= e($r['email']) ?></td>
      <td><?= e($r['orig_name']) ?><br><span class="muted"><?= e(human_bytes((int) $r['bytes'])) ?></span></td>
      <td><?= e($r['device_model'] !== '' ? $r['device_model'] : 'None') ?></td>
      <td><span class="chip chip-rejected"><?= e(str_replace('_', ' ', $r['code'])) ?></span><br><?= e($r['message']) ?></td>
      <td>
        <?php if ($meta): ?>
        <details><summary><?= count($meta) ?> items</summary>
          <table class="table table-small"><tbody>
            <?php foreach ($meta as $k => $v): ?><tr><th scope="row"><?= e($k) ?></th><td><?= e(is_array($v) ? json_encode($v) : $v) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
