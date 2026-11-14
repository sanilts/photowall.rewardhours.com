<?php /** @var array $p @var array $photo @var array $meta */ ?>
<p class="crumbs"><a href="/">Your projects</a> / <a href="/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a> / #<?= (int) $photo['id'] ?></p>
<div class="head">
  <h1>Photo #<?= (int) $photo['id'] ?></h1>
  <a class="btn btn-main" href="/img/<?= e($photo['token']) ?>/original">Download original (<?= e(strtoupper($photo['ext'])) ?>, <?= e(human_bytes((int) $photo['bytes'])) ?>)</a>
</div>
<div class="cols cols-wide">
  <section class="card">
    <div class="viewer"><img src="/img/<?= e($photo['token']) ?>/preview" alt="Photo <?= (int) $photo['id'] ?>"></div>
    <p class="muted">This is a smaller preview for your browser. The download is the untouched original file.</p>
  </section>
  <section class="card">
    <h2>Photo information</h2>
    <dl class="facts"><div><dt>Participant</dt><dd><?= e(sprintf('R%05d', $photo['respondent_id'])) ?></dd></div></dl>
    <?php require PW_ROOT . '/app/views/shared/meta.php'; ?>
  </section>
</div>
