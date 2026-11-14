<section class="hero">
  <p class="eyebrow">Photowall</p>
  <h1>Share photos from your iPhone for research</h1>
  <p class="lead">Pick a project below to read what it is about and take part.</p>
</section>
<?php if (!$projects): ?>
  <section class="card narrow center">
    <h2>No project is open right now</h2>
    <p>If someone gave you a link, please open that exact link. Otherwise, check back soon.</p>
  </section>
<?php else: ?>
  <ul class="tiles">
    <?php foreach ($projects as $p): ?>
      <li class="card">
        <h2><?= e($p['name']) ?></h2>
        <p><?= e(mb_strimwidth($p['intro'], 0, 180, '...')) ?></p>
        <?php if ($p['closes_at']): ?><p class="muted">Open until <?= e(nice_date($p['closes_at'])) ?></p><?php endif; ?>
        <a class="btn btn-main" href="/p/<?= e($p['slug']) ?>">Take part</a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
