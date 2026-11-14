<?php /** @var array $rows @var int $pages @var int $page */ ?>
<div class="head"><h1>Activity</h1></div>
<p class="muted">Who signed in, reviewed photos, changed settings or downloaded files, in the console and in the client portal.</p>
<div class="table-wrap">
<table class="table">
  <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Details</th><th scope="col">IP address</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(nice_date($r['created_at'], 'j M Y, H:i:s')) ?></td>
      <td><?= e($r['actor_label'] !== '' ? $r['actor_label'] : $r['actor_type']) ?> <span class="muted">(<?= e($r['actor_type']) ?>)</span></td>
      <td><?= e($r['action']) ?></td>
      <td><?= e($r['detail']) ?></td>
      <td><?= e($r['ip']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
