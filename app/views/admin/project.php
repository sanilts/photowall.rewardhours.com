<?php
/** @var array $p @var array $counts @var array $why @var int $people @var ?array $client @var string $state */
$link = area_url('respondent', '/p/' . $p['slug']);
$isAdmin = (ad_me()['role'] ?? '') === 'admin';
$reasons = ['wrong_device' => 'Not an accepted iPhone', 'no_camera_data' => 'No camera information', 'edited' => 'Edited or re-saved', 'resized' => 'Resized or cropped',
    'format' => 'Wrong file type', 'broken' => 'Broken file', 'blurry' => 'Blurry', 'too_dark' => 'Too dark', 'too_bright' => 'Too bright', 'duplicate' => 'Duplicate', 'unsafe' => 'Unsafe content'];
?>
<p class="crumbs"><a href="/">Projects</a> / <?= e($p['name']) ?></p>
<div class="head">
  <h1><?= e($p['name']) ?> <span class="chip chip-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></h1>
  <?php if ($isAdmin): ?><a class="btn" href="/projects/<?= (int) $p['id'] ?>/edit">Settings</a><?php endif; ?>
</div>

<?php if ($p['status'] === 'open' && $state !== 'open'): ?>
  <div class="note note-warn">This project is switched to Open, but it is outside its opening and closing dates, so uploads are not accepted right now.</div>
<?php endif; ?>

<section class="card">
  <h2>Link for respondents</h2>
  <p><a href="<?= e($link) ?>"><?= e($link) ?></a></p>
  <p class="muted"><?= $p['status'] === 'draft' ? 'The link does not work while the project is a draft. Switch it to Open in Settings.' : 'Share this link on social media or anywhere else. Anyone who opens it must agree to the terms and confirm their email before uploading.' ?></p>
</section>

<div class="stats">
  <a class="stat" href="/projects/<?= (int) $p['id'] ?>/photos"><span class="stat-n"><?= (int) $counts['pending'] ?></span><span>Waiting for review</span><?php if ((int) $counts['flagged']): ?><span class="chip chip-rejected"><?= (int) $counts['flagged'] ?> flagged by safety scan</span><?php endif; ?></a>
  <a class="stat" href="/projects/<?= (int) $p['id'] ?>/photos?status=approved"><span class="stat-n"><?= (int) $counts['approved'] ?></span><span>Approved (<?= e(human_bytes((int) $counts['approved_bytes'])) ?>)</span></a>
  <a class="stat" href="/projects/<?= (int) $p['id'] ?>/photos?status=rejected"><span class="stat-n"><?= (int) $counts['rejected'] ?></span><span>Rejected by staff</span></a>
  <a class="stat" href="/projects/<?= (int) $p['id'] ?>/respondents"><span class="stat-n"><?= (int) $people ?></span><span>Respondents</span></a>
</div>

<div class="cols">
  <section class="card">
    <h2>Rules in force</h2>
    <dl class="facts">
      <div><dt>Photos per person</dt><dd><?= (int) $p['photos_per_person'] ?: 'No limit' ?></dd></div>
      <div><dt>Per day</dt><dd><?= (int) $p['daily_limit'] ?: 'No daily limit' ?></dd></div>
      <div><dt>Opens</dt><dd><?= e($p['opens_at'] ? nice_date($p['opens_at']) : 'As soon as it is Open') ?></dd></div>
      <div><dt>Closes</dt><dd><?= e($p['closes_at'] ? nice_date($p['closes_at']) : 'No closing date') ?></dd></div>
      <div><dt>Accepted phones</dt><dd>iPhone <?= (int) $p['min_iphone'] ?> or newer<?= trim($p['extra_models']) !== '' ? ', plus ' . e(implode(', ', array_filter(array_map('trim', preg_split('/\R/', $p['extra_models']))))) : '' ?></dd></div>
      <div><dt>Client</dt><dd><?= e($client['name'] ?? 'Not linked yet') ?></dd></div>
    </dl>
  </section>

  <section class="card">
    <h2>Turned away automatically</h2>
    <?php if (!$why): ?>
      <p class="muted">No upload has been turned away so far.</p>
    <?php else: ?>
      <ul class="plain">
        <?php foreach ($why as $w): ?>
          <li><a href="/projects/<?= (int) $p['id'] ?>/rejected?code=<?= e($w['code']) ?>"><?= e($reasons[$w['code']] ?? $w['code']) ?></a> <strong><?= (int) $w['n'] ?></strong></li>
        <?php endforeach; ?>
      </ul>
      <p><a href="/projects/<?= (int) $p['id'] ?>/rejected">See every upload that was turned away</a></p>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Downloads</h2>
  <div class="row">
    <form method="post" action="/projects/<?= (int) $p['id'] ?>/zip">
      <?= csrf_field() ?>
      <button class="btn btn-main" type="submit">Approved originals (ZIP)</button>
    </form>
    <a class="btn" href="/projects/<?= (int) $p['id'] ?>/metadata.csv">Photo information (CSV)</a>
    <?php if ($isAdmin): ?><a class="btn" href="/projects/<?= (int) $p['id'] ?>/respondents.csv">Respondent list (CSV)</a><?php endif; ?>
  </div>
  <p class="muted">The ZIP holds every approved photo exactly as it was uploaded, with all of its information intact. Clients get the same files from their own portal, without names or email addresses.</p>
</section>
