<?php /** @var array $p @var array $photos @var array $models @var string $model @var int $total @var int $pages @var int $page */ ?>
<p class="crumbs"><a href="/">Your projects</a> / <?= e($p['name']) ?></p>
<div class="head">
  <h1><?= e($p['name']) ?> <span class="muted">(<?= (int) $total ?> photos)</span></h1>
  <form method="get" class="row">
    <label class="visually-hidden" for="model">Phone model</label>
    <select id="model" name="model">
      <option value="">All phone models</option>
      <?php foreach ($models as $m): ?>
        <option value="<?= e($m['device_model']) ?>"<?= $model === $m['device_model'] ? ' selected' : '' ?>><?= e($m['device_model']) ?> (<?= (int) $m['n'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Show</button>
  </form>
</div>

<?php if (!$photos): ?>
  <section class="card center">
    <h2>No approved photos yet</h2>
    <p>Photos appear here after our team has checked and approved them.</p>
  </section>
<?php else: ?>
<form method="post" action="/projects/<?= (int) $p['id'] ?>/zip">
  <?= csrf_field() ?>
  <div class="bulk">
    <label class="check"><input type="checkbox" data-check-all="photo"><span>Select all on this page</span></label>
    <span><strong data-count>0</strong> selected</span>
    <button class="btn" type="submit" name="which" value="selected">Download selected (ZIP)</button>
    <button class="btn btn-main" type="submit" name="which" value="all">Download all approved photos (ZIP)</button>
    <a class="btn" href="/projects/<?= (int) $p['id'] ?>/metadata.csv">Photo information (CSV)</a>
  </div>
  <p class="muted">Downloads contain the original files exactly as they left the phone, with all camera information intact, plus a sheet listing every photo.</p>
  <ul class="grid grid-review">
    <?php foreach ($photos as $ph): ?>
      <li class="shot">
        <a href="/photo/<?= e($ph['token']) ?>"><img src="/img/<?= e($ph['token']) ?>/thumb" alt="Photo <?= (int) $ph['id'] ?>" loading="lazy" width="240" height="240"></a>
        <div class="shot-info">
          <label class="check"><input type="checkbox" name="ids[]" value="<?= (int) $ph['id'] ?>" data-group="photo"><span>#<?= (int) $ph['id'] ?></span></label>
          <span><?= e($ph['device_model']) ?></span>
          <span class="muted"><?= e(contributor_id($ph['respondent_id'])) ?> &middot; <?= e(number_format($ph['width'] * $ph['height'] / 1e6, 1)) ?> MP &middot; <?= e(human_bytes((int) $ph['bytes'])) ?></span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</form>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
