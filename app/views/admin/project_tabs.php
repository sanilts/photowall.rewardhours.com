<?php
/**
 * The top of every page of one project: its name and state, the public link, and tabs to its other pages.
 * @var array $p @var string $tab  which tab is open: overview, review, tracker, people, turned or settings
 */
$pid = (int) $p['id'];
$isAdminTab = (ad_me()['role'] ?? '') === 'admin';
$nWait = (int) Db::val("SELECT COUNT(*) FROM photos WHERE project_id = ? AND status = 'pending'", [$pid]);
$nPeople = (int) Db::val('SELECT COUNT(*) FROM respondents WHERE project_id = ?', [$pid]);
$ptabs = [
    'overview' => ['Overview', "/projects/$pid", null],
    'review' => ['Review photos', "/projects/$pid/photos", $nWait],
    'tracker' => ['Tracker', "/projects/$pid/tracker", null],
    'people' => ['Contributors', "/projects/$pid/respondents", $nPeople],
    'turned' => ['Turned away', "/projects/$pid/rejected", null],
];
if ($isAdminTab) $ptabs['settings'] = ['Settings', "/projects/$pid/edit", null];
?>
<p class="crumbs"><a href="/">Projects</a> / <?= e($p['name']) ?></p>
<div class="phead">
  <p class="phead-name"><?= e($p['name']) ?> <span class="chip chip-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></p>
  <?php if ($p['status'] !== 'draft'): ?><a class="btn btn-small" href="<?= e(area_url('respondent', '/p/' . $p['slug'])) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Open public page</a><?php endif; ?>
</div>
<nav class="ptabs" aria-label="Pages of this project">
  <?php foreach ($ptabs as $key => [$label, $href, $n]): ?>
    <a href="<?= e($href) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n !== null): ?> <span class="ptab-n<?= $key === 'review' && $n > 0 ? ' ptab-hot' : '' ?>"><?= (int) $n ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
