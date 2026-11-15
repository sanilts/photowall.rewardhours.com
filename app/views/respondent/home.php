<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-copy">
      <h1>Share photos from your iPhone</h1>
      <p class="hero-lead"><?= $projects ? 'Choose a project below to see what it needs and take part.' : 'No project is open right now. If someone gave you a link, open that exact link. Otherwise, please check back soon.' ?></p>
    </div>
  </div>
</section>
<?php if ($projects): ?>
<section class="band">
  <div class="wrap">
    <h2 class="band-title">Open projects</h2>
    <ul class="tiles">
      <?php foreach ($projects as $p): ?>
        <li class="card">
          <h3><?= e($p['name']) ?></h3>
          <p><?= e(mb_strimwidth($p['intro'], 0, 180, '...')) ?></p>
          <?php if ($p['closes_at']): ?><p class="muted">Open until <?= e(nice_date($p['closes_at'])) ?></p><?php endif; ?>
          <a class="btn btn-main" href="/p/<?= e($p['slug']) ?>">Take part</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
