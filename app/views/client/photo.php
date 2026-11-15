<?php /** @var array $p @var array $photo @var array $meta @var string $state */ ?>
<p class="crumbs"><a href="/">Your projects</a> / <a href="/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a> / <?= e(image_id($photo['id'])) ?></p>
<div class="head">
  <h1>Photo <?= e(image_id($photo['id'])) ?></h1>
  <?php if ($state === 'free' || $state === 'approved'): ?>
    <a class="btn btn-main" href="/img/<?= e($photo['token']) ?>/original">Download original (<?= e(strtoupper($photo['ext'])) ?>, <?= e(human_bytes((int) $photo['bytes'])) ?>)</a>
  <?php elseif ($state === 'waiting'): ?>
    <span class="chip chip-pending">Download request waiting for our answer</span>
  <?php else: ?>
    <form method="post" action="/photo/<?= e($photo['token']) ?>/ask" class="row">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="note">Note for our team (optional)</label>
      <input id="note" name="note" maxlength="500" placeholder="Note for our team (optional)">
      <button class="btn btn-main" type="submit">Ask to download this photo</button>
    </form>
  <?php endif; ?>
</div>
<?php if ($state === 'ask'): ?><p class="muted">Downloads from this project need our approval. We email you when it is decided.</p><?php endif; ?>
<div class="cols cols-wide">
  <div class="card">
    <div class="viewer"><img src="/img/<?= e($photo['token']) ?>/preview" alt="Photo <?= e(image_id($photo['id'])) ?>"></div>
    <p class="muted">This is a smaller preview for your browser. The download is the untouched original file.</p>
  </div>
  <section class="card">
    <h2>Photo information</h2>
    <dl class="facts"><div><dt>Image ID</dt><dd><?= e(image_id($photo['id'])) ?></dd></div><div><dt>Category</dt><dd><?= e(($photo['category'] ?? '') !== '' ? $photo['category'] : 'Not set') ?></dd></div><div><dt>Contributor ID</dt><dd><?= e(contributor_id($photo['respondent_id'])) ?></dd></div></dl>
    <?php require PW_ROOT . '/app/views/shared/meta.php'; ?>
  </section>
</div>
