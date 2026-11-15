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
<?php $bulk = (int) $p['client_bulk'] === 1; $ask = (int) $p['client_approval'] === 1; ?>
<form method="post" action="/projects/<?= (int) $p['id'] ?>/zip">
  <?= csrf_field() ?>
  <div class="bulk">
    <?php if ($bulk): ?>
    <label class="check"><input type="checkbox" data-check-all="photo"><span>Select all on this page</span></label>
    <span><strong data-count>0</strong> selected</span>
    <button class="btn" type="submit" name="which" value="selected"><?= $ask ? 'Ask to download selected' : 'Download selected (ZIP)' ?></button>
    <button class="btn btn-main" type="submit" name="which" value="all"><?= $ask ? 'Ask to download all ' . (int) $total . ' photos' : 'Download all approved photos (ZIP)' ?></button>
    <?php endif; ?>
    <a class="btn" href="/projects/<?= (int) $p['id'] ?>/metadata.csv">Photo information (CSV)</a>
  </div>
  <?php if ($ask && $bulk): ?>
  <label for="note">Note for our team <span class="muted">(optional)</span></label>
  <input id="note" name="note" maxlength="500" placeholder="For example what the photos are needed for">
  <p class="muted">Downloads from this project need our approval. Choose the photos and ask; we email you when it is decided, and the download then waits under <a href="/requests">Download requests</a>.</p>
  <?php elseif ($ask): ?>
  <p class="muted">Downloads from this project need our approval. Open a photo and ask to download it; we email you when it is decided.</p>
  <?php elseif (!$bulk): ?>
  <p class="muted">Open a photo to download the original file. Downloading many photos at once is switched off for this project.</p>
  <?php else: ?>
  <p class="muted">Downloads contain the original files exactly as they left the phone, with all camera information intact, plus a sheet listing every photo.</p>
  <?php endif; ?>
  <ul class="grid grid-review">
    <?php foreach ($photos as $ph): ?>
      <li class="shot">
        <a href="/photo/<?= e($ph['token']) ?>"><img src="/img/<?= e($ph['token']) ?>/thumb" alt="Photo <?= e(image_id($ph['id'])) ?>" loading="lazy" width="240" height="240"></a>
        <div class="shot-info">
          <?php if ($bulk): ?><label class="check"><input type="checkbox" name="ids[]" value="<?= (int) $ph['id'] ?>" data-group="photo"><span><?= e(image_id($ph['id'])) ?></span></label>
          <?php else: ?><strong><?= e(image_id($ph['id'])) ?></strong><?php endif; ?>
          <span><?= e($ph['device_model']) ?></span>
          <span class="muted"><?= e(contributor_id($ph['respondent_id'])) ?> &middot; <?= e(number_format($ph['width'] * $ph['height'] / 1e6, 1)) ?> MP &middot; <?= e(human_bytes((int) $ph['bytes'])) ?></span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</form>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
