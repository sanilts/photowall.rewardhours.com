<?php
/** @var array $project @var array $me @var array $allow @var array $photos */
$slug = $project['slug'];
$total = (int) $project['photos_per_person'];
$reward = reward($project);
$accepted = count(array_filter($photos, static fn($ph) => $ph['status'] === 'approved'));
$cats = project_categories($project);
$labels = ['pending' => 'In review', 'approved' => 'Accepted', 'rejected' => 'Not accepted'];
$count = array_count_values(array_column($photos, 'status')) + ['approved' => 0, 'pending' => 0, 'rejected' => 0];
$infoTab = $reward ? 'Reward' : 'Info';
?>
<div id="app">
<section class="up-head">
  <div>
    <p class="eyebrow"><?= e($project['name']) ?></p>
    <h1>Upload your photos</h1>
  </div>
  <p class="up-id">Contributor ID <strong><?= e(contributor_id($me['id'])) ?></strong></p>
</section>

<ul class="up-stats" aria-live="polite">
  <li>
    <span class="up-n"><strong id="used"><?= (int) $allow['used'] ?></strong><?= $total > 0 ? ' of ' . $total : '' ?></span>
    <span class="up-l">uploaded</span>
    <?php if ($total > 0): ?><progress id="used-bar" max="<?= $total ?>" value="<?= (int) $allow['used'] ?>"><?= (int) $allow['used'] ?> of <?= $total ?></progress><?php endif; ?>
  </li>
  <li class="st-ok"><span class="up-n"><?= (int) $count['approved'] ?></span><span class="up-l">accepted</span></li>
  <li class="st-wait"><span class="up-n" id="n-review"><?= (int) $count['pending'] ?></span><span class="up-l">in review</span></li>
  <li class="st-bad"><span class="up-n"><?= (int) $count['rejected'] ?></span><span class="up-l">not accepted</span></li>
</ul>

<div class="up">
<div class="up-main">
<section class="card screen" id="add" data-screen="add">
  <div id="uploader" data-url="/p/<?= e($slug) ?>/upload" data-csrf="<?= e(csrf_token()) ?>" data-can="<?= (int) $allow['can'] ?>" data-max-mb="<?= (int) cfg('max_upload_mb', 60) ?>" data-cats="<?= e(json_encode($cats, JSON_UNESCAPED_UNICODE)) ?>">
  <h2>Add photos</h2>
  <?php if ((int) $me['blocked']): ?>
    <div class="note note-bad">Your account cannot upload photos to this project. If you think this is a mistake, email <?= e(cfg('privacy_email')) ?>.</div>
  <?php elseif ($allow['state'] !== 'open'): ?>
    <div class="note note-warn"><?= !empty($allow['full']) ? 'This project has collected all the photos it needs and is now closed. Thank you for taking part. You can still see your photos and their status here.' : 'This project is ' . ($allow['state'] === 'closed' ? 'closed' : 'not open yet') . ', so photos cannot be added right now.' ?></div>
  <?php elseif ($allow['left'] === 0): ?>
    <div class="note note-ok">You have uploaded all <?= $total ?> photos. Thank you. If one is not accepted, you can come back and replace it.</div>
  <?php elseif ($allow['can'] <= 0): ?>
    <div class="note note-warn">You have reached today's limit. Please come back tomorrow to add more.</div>
  <?php endif; ?>

  <div id="picker"<?= $allow['can'] > 0 ? '' : ' hidden' ?>>
    <input class="visually-hidden" id="files" type="file" accept="image/*" multiple>
    <label class="drop" for="files" id="drop">
      <span class="drop-ico"><?= icon('upload') ?></span>
      <span class="drop-title">Upload your photos</span>
      <span class="drop-drag">Drag photos here, or</span>
      <span class="drop-btn">Choose photos</span>
      <span class="drop-sub">JPEG or HEIC, up to <?= (int) cfg('max_upload_mb', 60) ?> MB each. You can choose many at once.</span>
    </label>
  </div>

  <div id="results" hidden>
    <div class="run">
      <p class="run-text" id="run-text" role="status">Getting ready</p>
      <button class="btn btn-small" type="button" id="run-stop">Stop</button>
    </div>
    <progress class="run-bar" id="run-bar" max="1" value="0"></progress>
    <p class="run-keep muted" id="run-keep">Keep this page open until every photo is checked.</p>
    <ul id="queue" class="queue"></ul>
  </div>

  <div id="picker-more"<?= $allow['can'] > 0 ? '' : ' hidden' ?>>
    <div class="dos">
      <ul>
        <li class="do"><?= icon('check') ?><span>Taken with the iPhone <strong>Camera</strong> app on an iPhone <?= (int) $project['min_iphone'] ?> or newer</span></li>
        <li class="do"><?= icon('check') ?><span>The original photo, picked from Photo Library</span></li>
      </ul>
      <ul>
        <li class="dont"><?= icon('cross') ?><span>No edits, crops or filters</span></li>
        <li class="dont"><?= icon('cross') ?><span>No screenshots, and no photos received in WhatsApp or other apps</span></li>
      </ul>
    </div>
  </div>
  </div>
</section>

<section class="card screen" id="mine" data-screen="mine">
  <div class="head head-sub">
    <h2>Your photos</h2>
    <p class="mine-sum" id="filters">
      <button type="button" class="chip chip-all" data-filter="" aria-pressed="true">All <span id="sum-all"><?= count($photos) ?></span></button>
      <button type="button" class="chip chip-approved" data-filter="approved" aria-pressed="false"><?= (int) $count['approved'] ?> accepted</button>
      <button type="button" class="chip chip-pending" data-filter="pending" aria-pressed="false"><span id="sum-review"><?= (int) $count['pending'] ?></span> in review</button>
      <button type="button" class="chip chip-rejected" data-filter="rejected" aria-pressed="false"><?= (int) $count['rejected'] ?> not accepted</button>
    </p>
  </div>
  <?php if (!$photos): ?>
    <div class="empty" id="none-yet"><?= icon('image') ?><p><strong>Nothing uploaded yet.</strong><br>Your photos appear here with their status as soon as they pass the checks.</p></div>
  <?php endif; ?>
  <ul class="grid" id="mine-list">
    <?php foreach ($photos as $p): ?>
      <li class="shot" data-status="<?= e($p['status']) ?>">
        <?php if ($p['status'] === 'rejected'): ?>
          <div class="shot-gone">Photo removed</div>
        <?php else: ?>
          <img src="/p/<?= e($slug) ?>/thumb/<?= e($p['token']) ?>" alt="Your uploaded photo" loading="lazy" width="240" height="240">
        <?php endif; ?>
        <span class="chip chip-<?= e($p['status']) ?>"><?= e($labels[$p['status']] ?? $p['status']) ?><?= $p['status'] === 'approved' && $p['reviewed_at'] ? ' ' . e(nice_date($p['reviewed_at'], 'j M')) : '' ?></span>
        <span class="shot-id"><strong><?= e(image_id($p['id'])) ?></strong><br><?= e($p['orig_name']) ?></span>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="post" action="/p/<?= e($slug) ?>/photo/<?= e($p['token']) ?>/category" class="shot-cat">
            <?= csrf_field() ?>
            <select name="category" aria-label="Category of this photo">
              <?php if (!in_array($p['category'], $cats, true)): ?><option value="" selected>Choose a category</option><?php endif; ?>
              <?php foreach ($cats as $c): ?><option<?= $p['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-small" type="submit">Save</button>
          </form>
        <?php elseif ($p['category'] !== ''): ?>
          <span class="shot-kind"><?= e($p['category']) ?></span>
        <?php endif; ?>
        <?php if ($p['status'] === 'rejected' && $p['reject_reason'] !== ''): ?><span class="shot-why"><?= e($p['reject_reason']) ?></span><?php endif; ?>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="post" action="/p/<?= e($slug) ?>/photo/<?= e($p['token']) ?>/delete" data-confirm="Delete this photo? This cannot be undone.">
            <?= csrf_field() ?>
            <button class="btn btn-link btn-small" type="submit">Delete</button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="muted mine-note"><?= icon('mail') ?> We email you when photos are accepted.</p>
</section>
</div>

<aside class="up-side">
  <?php if ($reward): $done = $accepted % $reward['every']; $toNext = $reward['every'] - $done; ?>
  <section class="card earn-card screen" id="info" data-screen="info">
    <h2><?= icon('coin') ?> Your reward</h2>
    <p class="earn-n"><strong><?= e(reward_earned($reward, $accepted)) ?> earned so far.</strong></p>
    <?php if ($reward['every'] <= 12): ?>
      <p class="pips" role="img" aria-label="<?= $done ?> of <?= $reward['every'] ?> accepted photos towards the next <?= e($reward['amount']) ?>"><?php for ($i = 0; $i < $reward['every']; $i++): ?><span class="pip<?= $i < $done ? ' on' : '' ?>"></span><?php endfor; ?></p>
    <?php endif; ?>
    <p><?= $accepted ?> accepted, <?= $toNext ?> more for the next <?= e($reward['amount']) ?>.</p>
    <p class="muted">We pay <?= e($reward['amount']) ?> for every <?= $reward['every'] ?> accepted photos. A photo counts once our team has accepted it.</p>
  </section>
  <?php endif; ?>

  <section class="card screen"<?= $reward ? '' : ' id="info"' ?> data-screen="info">
    <h2>Good to know</h2>
    <ul class="facts-list">
      <li><?= icon('shield') ?><span>Each photo is checked as it uploads. If one is turned away you see why straight away, and you can pick another.</span></li>
      <li><?= icon('image') ?><span><?= $total > 0 ? 'You can upload up to ' . $total . ' photos.' : 'There is no limit for this project.' ?><?php if ($allow['today_left'] !== null): ?> Today you can still add <strong id="today-left"><?= (int) $allow['today_left'] ?></strong> (limit <?= (int) $project['daily_limit'] ?> per day).<?php endif; ?></span></li>
      <?php if ($project['closes_at']): ?><li><?= icon('calendar') ?><span>Uploads close on <?= e(nice_date($project['closes_at'])) ?> (<?= e(date('T')) ?>).</span></li><?php endif; ?>
    </ul>
  </section>

  <section class="card screen" id="account" data-screen="account">
    <h2>Your account</h2>
    <p class="acct">Signed in as<br><strong><?= e($me['email']) ?></strong></p>
    <p class="acct">Contributor ID <strong><?= e(contributor_id($me['id'])) ?></strong></p>
    <p><a class="btn btn-small" href="/p/<?= e($slug) ?>/email"><?= icon('pencil') ?> Change email</a></p>
    <p class="muted">To come back later, open the same link and enter this email again.
       To withdraw from the project or have your data removed, email <a href="mailto:<?= e(cfg('privacy_email')) ?>"><?= e(cfg('privacy_email')) ?></a>.</p>
  </section>
</aside>
</div>

<nav class="tabbar" id="tabbar" aria-label="Sections of this page">
  <a href="#add" data-tab="add"><?= icon('upload') ?><span>Upload</span></a>
  <a href="#mine" data-tab="mine"><?= icon('tiles') ?><span>Photos</span><b class="tab-n" id="tab-n"<?= $photos ? '' : ' hidden' ?>><?= count($photos) ?></b></a>
  <a href="#info" data-tab="info"><?= icon($reward ? 'coin' : 'info') ?><span><?= e($infoTab) ?></span></a>
  <a href="#account" data-tab="account"><?= icon('user') ?><span>Account</span></a>
</nav>
</div>
<script src="/assets/upload.js?v=<?= e((string) @filemtime(PW_ROOT . '/public/assets/upload.js')) ?>" defer></script>
