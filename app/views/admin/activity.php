<?php /** @var array $rows @var int $pages @var int $page @var array $groups @var string $kind @var int $total */
$words = activity_words();
$who = ['staff' => 'Staff', 'client' => 'Client', 'respondent' => 'Contributor', 'system' => 'System'];
$tone = ['signin' => 'chip-open', 'photos' => 'chip-pending', 'downloads' => 'chip-dl', 'settings' => '', 'people' => 'chip-people'];
?>
<div class="head"><h1>Activity <span class="muted">(<?= (int) $total ?>)</span></h1></div>
<p class="muted">Who signed in, reviewed photos, changed settings or downloaded files, in the console and in the client portal. What clients viewed is under <a href="/clients/activity">Clients, Activity</a>.</p>
<nav class="pills" aria-label="Kind of activity">
  <a class="pill" href="/activity"<?= $kind === '' ? ' aria-current="page"' : '' ?>>Everything</a>
  <?php foreach ($groups as $k => $label): ?><a class="pill" href="/activity?kind=<?= e($k) ?>"<?= $kind === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php if (!$rows): ?><section class="card center"><h2>Nothing of this kind yet</h2></section><?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">What</th><th scope="col">Details</th><th scope="col">IP address</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): [$label, $group] = $words[$r['action']] ?? [$r['action'], 'settings']; ?>
    <tr>
      <td><?= e(nice_date($r['created_at'], 'j M Y, H:i:s')) ?></td>
      <td><?= e($r['actor_label'] !== '' ? $r['actor_label'] : ($who[$r['actor_type']] ?? $r['actor_type'])) ?><br><span class="muted"><?= e($who[$r['actor_type']] ?? $r['actor_type']) ?></span></td>
      <td><span class="chip <?= e($tone[$group]) ?>" title="<?= e($r['action']) ?>"><?= e($label) ?></span></td>
      <td><?= e($r['detail']) ?></td>
      <td><?= e($r['ip']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
