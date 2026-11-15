<?php /** @var array $me @var array $projects */ ?>
<div class="head"><h1><?= e($me['client_name']) ?></h1></div>
<?php if (!$projects): ?>
  <section class="card center">
    <h2>No project is shared with you yet</h2>
    <p>Projects appear here as soon as they are linked to your company.</p>
  </section>
<?php else: ?>
<ul class="tiles">
  <?php foreach ($projects as $p): ?>
    <li class="card">
      <h2><?= e($p['name']) ?> <span class="chip chip-<?= e($p['status']) ?>"><?= $p['status'] === 'open' ? 'Collecting' : 'Closed' ?></span></h2>
      <p class="big-n"><?= (int) $p['n_approved'] ?> <span>approved photos</span></p>
      <p class="muted">From <?= (int) $p['n_people'] ?> <?= (int) $p['n_people'] === 1 ? 'person' : 'people' ?> &middot; <?= e(human_bytes((int) $p['approved_bytes'])) ?><?= $p['closes_at'] ? ' &middot; collection ends ' . e(nice_date($p['closes_at'], 'j M Y')) : '' ?></p>
      <?php if ((int) $p['client_approval']): ?><p class="muted">Downloads need our approval.</p><?php endif; ?>
      <a class="btn btn-main" href="/projects/<?= (int) $p['id'] ?>">Open</a>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
