<?php
/** @var array $p @var array $counts @var array $why @var int $people @var ?array $client @var string $state @var array $byCategory @var string $rewardTotal @var int $turned @var ?string $lastUpload @var int $collected */
$pid = (int) $p['id'];
$link = area_url('respondent', '/p/' . $p['slug']);
$isAdmin = (ad_me()['role'] ?? '') === 'admin';
$reasons = ['wrong_device' => 'Not an accepted iPhone', 'no_camera_data' => 'No camera information', 'edited' => 'Edited or re-saved', 'resized' => 'Resized or cropped',
    'format' => 'Wrong file type', 'screenshot' => 'Screenshot', 'broken' => 'Broken file', 'blurry' => 'Blurry', 'too_dark' => 'Too dark', 'too_bright' => 'Too bright', 'duplicate' => 'Duplicate', 'unsafe' => 'Unsafe content'];
$pending = (int) $counts['pending'];
$flagged = (int) $counts['flagged'];
$catMax = max(1, ...array_map(static fn($c) => (int) $c['total'], $byCategory ?: [['total' => 1]]));
$whyMax = max(1, ...array_map(static fn($w) => (int) $w['n'], $why ?: [['n' => 1]]));
$target = (int) ($p['target_photos'] ?? 0);
$reached = $target > 0 && $collected >= $target;
$tab = 'overview';
require PW_ROOT . '/app/views/admin/project_tabs.php';
?>
<h1 class="visually-hidden"><?= e($p['name']) ?>: overview</h1>

<?php if ($p['status'] === 'open' && $state !== 'open'): ?>
  <div class="note note-warn">This project is switched to Open, but it is outside its opening and closing dates, so uploads are not accepted right now.</div>
<?php endif; ?>

<?php if ($target > 0): ?>
<section class="goal<?= $reached ? ' goal-done' : '' ?>">
  <div class="goal-top">
    <p class="goal-n"><strong><?= number_format($collected) ?></strong> of <?= number_format($target) ?> photos collected</p>
    <p class="goal-sub"><?= $reached ? 'The target is reached, so the upload page now says the project is closed.' : number_format($target - $collected) . ' to go. Uploads close by themselves when the target is reached.' ?></p>
  </div>
  <progress max="<?= $target ?>" value="<?= min($collected, $target) ?>"><?= $collected ?> of <?= $target ?></progress>
</section>
<?php endif; ?>

<?php if (!empty($asked)): ?>
<div class="todo todo-ask">
  <div>
    <p class="todo-n"><?= (int) $asked ?> download <?= (int) $asked === 1 ? 'request is' : 'requests are' ?> waiting for an answer</p>
    <p class="todo-sub">The client asked to download photos of this project.</p>
  </div>
  <a class="btn btn-main" href="/clients/requests">Answer</a>
</div>
<?php endif; ?>

<div class="todo <?= $pending ? 'todo-go' : 'todo-done' ?>">
  <?php if ($pending): ?>
    <div>
      <p class="todo-n"><?= $pending ?> <?= $pending === 1 ? 'photo is' : 'photos are' ?> waiting for your review</p>
      <p class="todo-sub"><?= $flagged ? $flagged . ' of them ' . ($flagged === 1 ? 'was' : 'were') . ' flagged by the safety scan and should be looked at first.' : 'Contributors are told by email once you accept their photos.' ?></p>
    </div>
    <div class="row">
      <a class="btn btn-sun" href="/projects/<?= $pid ?>/photos">Review photos</a>
      <?php if ($flagged): ?><a class="btn btn-ghost" href="/projects/<?= $pid ?>/photos?flag=1">Flagged first</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div>
      <p class="todo-n">Nothing is waiting for review</p>
      <p class="todo-sub"><?= $lastUpload ? 'The last photo arrived on ' . e(nice_date($lastUpload)) . '.' : 'No photos have arrived yet. Share the link below to get started.' ?></p>
    </div>
  <?php endif; ?>
</div>

<div class="stats">
  <a class="stat st-wait" href="/projects/<?= $pid ?>/photos"><span class="stat-n"><?= $pending ?></span><span>Waiting for review</span></a>
  <a class="stat st-ok" href="/projects/<?= $pid ?>/photos?status=approved"><span class="stat-n"><?= (int) $counts['approved'] ?></span><span>Accepted (<?= e(human_bytes((int) $counts['approved_bytes'])) ?>)</span></a>
  <a class="stat st-bad" href="/projects/<?= $pid ?>/photos?status=rejected"><span class="stat-n"><?= (int) $counts['rejected'] ?></span><span>Rejected by staff</span></a>
  <a class="stat st-bad" href="/projects/<?= $pid ?>/rejected"><span class="stat-n"><?= (int) $turned ?></span><span>Turned away by checks</span></a>
  <a class="stat" href="/projects/<?= $pid ?>/respondents"><span class="stat-n"><?= (int) $people ?></span><span>Contributors</span></a>
  <?php if ($rewardTotal !== ''): ?><a class="stat st-pay" href="/projects/<?= $pid ?>/respondents?sort=reward"><span class="stat-n"><?= e($rewardTotal) ?></span><span>Reward earned so far</span></a><?php endif; ?>
</div>

<div class="pcols">
  <div>
    <section class="card">
      <div class="head head-sub">
        <h2>Photos by category</h2>
        <a class="btn btn-small" href="/projects/<?= $pid ?>/tracker">Open the tracker</a>
      </div>
      <?php if (!$byCategory): ?>
        <p class="muted">No photos yet.</p>
      <?php else: ?>
        <ul class="bars">
          <?php foreach ($byCategory as $c): ?>
            <li>
              <a href="/projects/<?= $pid ?>/tracker?<?= e(http_build_query(['category' => $c['category'] !== '' ? $c['category'] : '-'])) ?>"><?= e($c['category_label']) ?></a>
              <span class="bar"><meter min="0" max="<?= $catMax ?>" value="<?= (int) $c['total'] ?>"><?= (int) $c['total'] ?></meter></span>
              <span class="bars-n"><strong><?= (int) $c['accepted'] ?></strong> accepted, <?= (int) $c['review'] ?> in review, <?= (int) $c['rejected'] ?> rejected</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="head head-sub">
        <h2>Why uploads were turned away</h2>
        <?php if ($why): ?><a class="btn btn-small" href="/projects/<?= $pid ?>/rejected">See all <?= (int) $turned ?></a><?php endif; ?>
      </div>
      <?php if (!$why): ?>
        <p class="muted">No upload has been turned away so far.</p>
      <?php else: ?>
        <ul class="bars bars-bad">
          <?php foreach ($why as $w): ?>
            <li>
              <a href="/projects/<?= $pid ?>/rejected?code=<?= e($w['code']) ?>"><?= e($reasons[$w['code']] ?? ucfirst(str_replace('_', ' ', $w['code']))) ?></a>
              <span class="bar"><meter min="0" max="<?= $whyMax ?>" value="<?= (int) $w['n'] ?>"><?= (int) $w['n'] ?></meter></span>
              <span class="bars-n"><strong><?= (int) $w['n'] ?></strong></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="muted">These files failed a check and were deleted. If one reason is very common, a rule in Settings may be too strict.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="head head-sub">
        <h2>Rules in force</h2>
        <?php if ($isAdmin): ?><a class="btn btn-small" href="/projects/<?= $pid ?>/edit">Change</a><?php endif; ?>
      </div>
      <dl class="facts">
        <div><dt>Photos per person</dt><dd><?= (int) $p['photos_per_person'] ?: 'No limit' ?></dd></div>
        <div><dt>Per day</dt><dd><?= (int) $p['daily_limit'] ?: 'No daily limit' ?></dd></div>
        <div><dt>Target</dt><dd><?= $target > 0 ? 'Closes after ' . number_format($target) . ' photos' : 'No target' ?></dd></div>
        <div><dt>Reward</dt><dd><?= ($rw = reward($p)) ? e($rw['amount']) . ' for every ' . $rw['every'] . ' accepted photos' : 'None' ?></dd></div>
        <div><dt>Opens</dt><dd><?= e($p['opens_at'] ? nice_date($p['opens_at']) : 'As soon as it is Open') ?></dd></div>
        <div><dt>Closes</dt><dd><?= e($p['closes_at'] ? nice_date($p['closes_at']) : 'No closing date') ?></dd></div>
        <div><dt>Accepted phones</dt><dd>iPhone <?= (int) $p['min_iphone'] ?> or newer<?= trim($p['extra_models']) !== '' ? ', plus ' . e(implode(', ', array_filter(array_map('trim', preg_split('/\R/', $p['extra_models']))))) : '' ?></dd></div>
        <div><dt>Client</dt><dd><?= e($client['name'] ?? 'Not linked yet') ?></dd></div>
        <div><dt>Client downloads</dt><dd><?= (int) $p['client_approval'] ? 'Need our approval' : 'Without asking' ?>, <?= (int) $p['client_bulk'] ? 'many at once allowed' : 'one photo at a time' ?></dd></div>
        <div><dt>Contributor uploads</dt><dd><?= (int) $p['bulk_upload'] ? 'Many photos at once' : 'One photo at a time' ?></dd></div>
      </dl>
    </section>
  </div>

  <div>
    <section class="card">
      <h2>Link for contributors</h2>
      <div class="copy">
        <input id="share-link" value="<?= e($link) ?>" readonly aria-label="Link for contributors">
        <button class="btn btn-main" type="button" data-copy="share-link">Copy</button>
      </div>
      <p class="muted"><?= $p['status'] === 'draft' ? 'The link does not work while the project is a draft. Switch it to Open in Settings.' : 'Share this link on social media or anywhere else. Anyone who opens it must agree to the terms and confirm their email before uploading.' ?></p>
    </section>

    <section class="card">
      <h2>Downloads</h2>
      <div class="dl">
        <form method="post" action="/projects/<?= $pid ?>/zip">
          <?= csrf_field() ?>
          <button class="btn btn-main" type="submit">Approved originals (ZIP)</button>
        </form>
        <a class="btn" href="/projects/<?= $pid ?>/metadata.csv">Photo information (CSV)</a>
        <a class="btn" href="/projects/<?= $pid ?>/tracker.csv">Tracker (CSV)</a>
        <?php if ($isAdmin): ?><a class="btn" href="/projects/<?= $pid ?>/respondents.csv">Respondent list (CSV)</a><?php endif; ?>
      </div>
      <p class="muted">The ZIP holds every approved photo exactly as it was uploaded, with all of its information intact. Clients get the same files from their own portal, without names or email addresses.</p>
    </section>

  </div>
</div>
