<?php /** @var string $ctab */
$waiting = (int) Db::val("SELECT COUNT(*) FROM download_requests WHERE status = 'pending'");
$ctabs = ['people' => ['Companies and people', '/clients', null], 'requests' => ['Download requests', '/clients/requests', $waiting], 'activity' => ['Activity', '/clients/activity', null]];
?>
<div class="head"><h1>Clients</h1></div>
<nav class="ptabs" aria-label="Client pages">
  <?php foreach ($ctabs as $key => [$label, $href, $n]): ?>
    <a href="<?= e($href) ?>"<?= $ctab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n): ?> <span class="ptab-n ptab-hot"><?= (int) $n ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
