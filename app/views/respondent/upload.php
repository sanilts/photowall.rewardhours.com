<?php
/** @var array $project @var array $me @var array $allow @var array $photos @var array $invite */
$slug = $project['slug'];
$total = (int) $project['photos_per_person'];
$reward = reward($project);
$accepted = count(array_filter($photos, static fn($ph) => $ph['status'] === 'approved'));
$cats = project_categories($project);
$labels = ['pending' => t('In review'), 'approved' => t('Accepted'), 'rejected' => t('Not accepted')];
$count = array_count_values(array_column($photos, 'status')) + ['approved' => 0, 'pending' => 0, 'rejected' => 0];
$infoTab = $reward ? t('Reward') : t('Info');
// words the upload script shows while photos are being checked
$words = [
    'checking' => t('Checking photo {i} of {n}. Accepted: {ok}. Not accepted: {bad}.'), 'done' => t('Photos checked: {n}. Accepted: {ok}. Not accepted: {bad}.'),
    'waiting' => t('Waiting'), 'uploading' => t('Uploading'), 'looking' => t('Checking the photo'), 'wrong' => t('Something went wrong. Please try this photo again.'),
    'filed' => t('Accepted for review. Filed under {category}.'), 'limit' => t('Not uploaded: you have reached your limit for now.'),
    'big' => t('This file is too large. The limit is {mb} MB per photo.'), 'dropped' => t('The connection dropped. Please try this photo again.'),
    'alt' => t('Your uploaded photo'), 'review' => t('In review'), 'cat' => t('Category of this photo'),
];
$catNames = [];
foreach ($cats as $c) $catNames[$c] = t($c);
?>
<div id="app">
<section class="up-head">
  <div>
    <p class="eyebrow"><?= e($project['name']) ?></p>
    <h1><?= te('Upload your photos') ?></h1>
  </div>
  <p class="up-id"><?= te('Contributor ID') ?> <strong><?= e(contributor_id($me['id'])) ?></strong></p>
</section>

<ul class="up-stats" aria-live="polite">
  <li>
    <span class="up-n"><?= $total > 0 ? t('{used} of {total}', ['used' => '<strong id="used">' . (int) $allow['used'] . '</strong>', 'total' => $total]) : '<strong id="used">' . (int) $allow['used'] . '</strong>' ?></span>
    <span class="up-l"><?= te('Uploaded') ?></span>
    <?php if ($total > 0): ?><progress id="used-bar" max="<?= $total ?>" value="<?= (int) $allow['used'] ?>"><?= te('{used} of {total}', ['used' => (int) $allow['used'], 'total' => $total]) ?></progress><?php endif; ?>
  </li>
  <li class="st-ok"><span class="up-n"><?= (int) $count['approved'] ?></span><span class="up-l"><?= te('Accepted') ?></span></li>
  <li class="st-wait"><span class="up-n" id="n-review"><?= (int) $count['pending'] ?></span><span class="up-l"><?= te('In review') ?></span></li>
  <li class="st-bad"><span class="up-n"><?= (int) $count['rejected'] ?></span><span class="up-l"><?= te('Not accepted') ?></span></li>
</ul>

<div class="up">
<div class="up-main">
<section class="card screen" id="add" data-screen="add">
  <div id="uploader" data-url="/p/<?= e($slug) ?>/upload" data-csrf="<?= e(csrf_token()) ?>" data-can="<?= (int) $allow['can'] ?>" data-max-mb="<?= (int) cfg('max_upload_mb', 60) ?>" data-cats="<?= e(json_encode($cats, JSON_UNESCAPED_UNICODE)) ?>" data-cat-names="<?= e(json_encode($catNames, JSON_UNESCAPED_UNICODE)) ?>" data-words="<?= e(json_encode($words, JSON_UNESCAPED_UNICODE)) ?>">
  <h2><?= te('Add photos') ?></h2>
  <?php if ((int) $me['blocked']): ?>
    <div class="note note-bad"><?= te('Your account cannot upload photos to this project. If you think this is a mistake, email {email}.', ['email' => cfg('privacy_email')]) ?></div>
  <?php elseif ($allow['state'] !== 'open'): ?>
    <div class="note note-warn"><?= !empty($allow['full']) ? te('This project has collected all the photos it needs and is now closed. Thank you for taking part. You can still see your photos and their status here.') : ($allow['state'] === 'closed' ? te('This project is closed, so photos cannot be added right now.') : te('This project is not open yet, so photos cannot be added right now.')) ?></div>
  <?php elseif ($allow['left'] === 0): ?>
    <div class="note note-ok"><?= te('You have uploaded all {n} photos. Thank you. If one is not accepted, you can come back and replace it.', ['n' => $total]) ?></div>
  <?php elseif ($allow['can'] <= 0): ?>
    <div class="note note-warn"><?= te('You have reached today\'s limit. Please come back tomorrow to add more.') ?></div>
  <?php endif; ?>

  <?php $many = (int) ($project['bulk_upload'] ?? 1) === 1; ?>
  <div id="picker"<?= $allow['can'] > 0 ? '' : ' hidden' ?> data-many="<?= $many ? 1 : 0 ?>">
    <input class="visually-hidden" id="files" type="file" accept="image/*"<?= $many ? ' multiple' : '' ?>>
    <label class="drop" for="files" id="drop">
      <span class="drop-ico"><?= icon('upload') ?></span>
      <span class="drop-title"><?= $many ? te('Upload your photos') : te('Upload a photo') ?></span>
      <span class="drop-drag"><?= $many ? te('Drag photos here, or') : te('Drag a photo here, or') ?></span>
      <span class="drop-btn"><?= $many ? te('Choose photos') : te('Choose a photo') ?></span>
      <span class="drop-sub"><?= te('JPEG or HEIC, up to {mb} MB each.', ['mb' => (int) cfg('max_upload_mb', 60)]) ?> <?= $many ? te('You can choose many at once.') : te('Add one photo at a time.') ?></span>
    </label>
    <p class="note note-bad" id="one-note" role="alert" hidden><?= te('Please add one photo at a time. Only the first photo was taken.') ?></p>
  </div>

  <div id="results" hidden>
    <div class="run">
      <p class="run-text" id="run-text" role="status"><?= te('Getting ready') ?></p>
      <button class="btn btn-small" type="button" id="run-stop"><?= te('Stop') ?></button>
    </div>
    <progress class="run-bar" id="run-bar" max="1" value="0"></progress>
    <p class="run-keep muted" id="run-keep"><?= te('Keep this page open until every photo is checked.') ?></p>
    <ul id="queue" class="queue"></ul>
  </div>

  <div id="picker-more"<?= $allow['can'] > 0 ? '' : ' hidden' ?>>
    <div class="dos">
      <ul>
        <li class="do"><?= icon('check') ?><span><?= t('Taken with the iPhone <strong>Camera</strong> app on an iPhone {min} or newer', ['min' => (int) $project['min_iphone']]) ?></span></li>
        <li class="do"><?= icon('check') ?><span><?= te('The original photo, picked from Photo Library') ?></span></li>
      </ul>
      <ul>
        <li class="dont"><?= icon('cross') ?><span><?= te('No edits, crops or filters') ?></span></li>
        <li class="dont"><?= icon('cross') ?><span><?= te('No screenshots, and no photos received in WhatsApp or other apps') ?></span></li>
      </ul>
    </div>
  </div>
  </div>
</section>

<section class="card screen" id="mine" data-screen="mine">
  <div class="head head-sub">
    <h2><?= te('Your photos') ?></h2>
    <p class="mine-sum" id="filters">
      <button type="button" class="chip chip-all" data-filter="" aria-pressed="true"><?= te('All') ?> <span id="sum-all"><?= count($photos) ?></span></button>
      <button type="button" class="chip chip-approved" data-filter="approved" aria-pressed="false"><?= te('Accepted') ?> <?= (int) $count['approved'] ?></button>
      <button type="button" class="chip chip-pending" data-filter="pending" aria-pressed="false"><?= te('In review') ?> <span id="sum-review"><?= (int) $count['pending'] ?></span></button>
      <button type="button" class="chip chip-rejected" data-filter="rejected" aria-pressed="false"><?= te('Not accepted') ?> <?= (int) $count['rejected'] ?></button>
    </p>
  </div>
  <?php if (!$photos): ?>
    <div class="empty" id="none-yet"><?= icon('image') ?><p><strong><?= te('Nothing uploaded yet.') ?></strong><br><?= te('Your photos appear here with their status as soon as they pass the checks.') ?></p></div>
  <?php endif; ?>
  <ul class="grid" id="mine-list">
    <?php foreach ($photos as $p): ?>
      <li class="shot" data-status="<?= e($p['status']) ?>">
        <?php if ($p['status'] === 'rejected'): ?>
          <div class="shot-gone"><?= te('Photo removed') ?></div>
        <?php else: ?>
          <img src="/p/<?= e($slug) ?>/thumb/<?= e($p['token']) ?>" alt="<?= te('Your uploaded photo') ?>" loading="lazy" width="240" height="240">
        <?php endif; ?>
        <span class="chip chip-<?= e($p['status']) ?>"><?= e($labels[$p['status']] ?? $p['status']) ?><?= $p['status'] === 'approved' && $p['reviewed_at'] ? ' ' . e(nice_date($p['reviewed_at'], 'j.n.')) : '' ?></span>
        <span class="shot-id"><strong><?= e(image_id($p['id'])) ?></strong><br><?= e($p['orig_name']) ?></span>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="post" action="/p/<?= e($slug) ?>/photo/<?= e($p['token']) ?>/category" class="shot-cat">
            <?= csrf_field() ?>
            <select name="category" aria-label="<?= te('Category of this photo') ?>">
              <?php if (!in_array($p['category'], $cats, true)): ?><option value="" selected><?= te('Choose a category') ?></option><?php endif; ?>
              <?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"<?= $p['category'] === $c ? ' selected' : '' ?>><?= te($c) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-small" type="submit"><?= te('Save') ?></button>
          </form>
        <?php elseif ($p['category'] !== ''): ?>
          <span class="shot-kind"><?= te($p['category']) ?></span>
        <?php endif; ?>
        <?php if ($p['status'] === 'rejected' && $p['reject_reason'] !== ''): ?><span class="shot-why"><?= e(Lang::reason($p['reject_reason'])) ?></span><?php endif; ?>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="post" action="/p/<?= e($slug) ?>/photo/<?= e($p['token']) ?>/delete" data-confirm="<?= te('Delete this photo? This cannot be undone.') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-link btn-small" type="submit"><?= te('Delete') ?></button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="muted mine-note"><?= icon('mail') ?> <?= te('We email you when photos are accepted.') ?></p>
</section>
</div>

<aside class="up-side">
  <?php if ($reward): $done = $accepted % $reward['every']; $toNext = $reward['every'] - $done; ?>
  <section class="card earn-card screen" id="info" data-screen="info">
    <h2><?= icon('coin') ?> <?= te('Your reward') ?></h2>
    <p class="earn-n"><strong><?= te('{amount} earned so far.', ['amount' => reward_earned($reward, $accepted)]) ?></strong></p>
    <?php if ($reward['every'] <= 12): ?>
      <p class="pips" role="img" aria-label="<?= te('{done} of {every} accepted photos towards the next {amount}', ['done' => $done, 'every' => $reward['every'], 'amount' => $reward['amount']]) ?>"><?php for ($i = 0; $i < $reward['every']; $i++): ?><span class="pip<?= $i < $done ? ' on' : '' ?>"></span><?php endfor; ?></p>
    <?php endif; ?>
    <p><?= te('Accepted so far: {accepted}. {n} more to reach the next {amount}.', ['accepted' => $accepted, 'n' => $toNext, 'amount' => $reward['amount']]) ?></p>
    <p class="muted"><?= te('We pay {amount} for every {n} accepted photos.', ['amount' => $reward['amount'], 'n' => $reward['every']]) ?> <?= te('A photo counts once our team has accepted it.') ?></p>
  </section>
  <?php endif; ?>

  <section class="card screen"<?= $reward ? '' : ' id="info"' ?> data-screen="info">
    <h2><?= te('Good to know') ?></h2>
    <ul class="facts-list">
      <li><?= icon('shield') ?><span><?= te('Each photo is checked as it uploads. If one is turned away you see why straight away, and you can pick another.') ?></span></li>
      <li><?= icon('image') ?><span><?= $total > 0 ? te('You can upload up to {n} photos.', ['n' => $total]) : te('There is no limit for this project.') ?><?php if ($allow['today_left'] !== null): ?> <?= t('Today you can still add {n} (limit {limit} per day).', ['n' => '<strong id="today-left">' . (int) $allow['today_left'] . '</strong>', 'limit' => (int) $project['daily_limit']]) ?><?php endif; ?></span></li>
      <?php if ($project['closes_at']): ?><li><?= icon('calendar') ?><span><?= te('Uploads close on {date}.', ['date' => Lang::date($project['closes_at'], true) . ' (' . date('T') . ')']) ?></span></li><?php endif; ?>
    </ul>
  </section>

  <?php if (!empty($invite['on']) && preg_match('~^https://~', (string) $invite['link'])): ?>
  <section class="card invite screen" data-screen="info">
    <h2><?= te((string) $invite['title']) ?></h2>
    <p><?= te((string) $invite['text']) ?></p>
    <p><a class="btn btn-main" href="<?= e($invite['link']) ?>" target="_blank" rel="noopener"><?= te((string) $invite['button']) ?> <?= icon('arrow') ?></a></p>
  </section>
  <?php endif; ?>

  <section class="card screen" id="account" data-screen="account">
    <h2><?= te('Your account') ?></h2>
    <p class="acct"><?= te('Signed in as') ?><br><strong><?= e($me['email']) ?></strong></p>
    <p class="acct"><?= te('Contributor ID') ?> <strong><?= e(contributor_id($me['id'])) ?></strong></p>
    <p class="acct"><?= e($me['full_name']) ?><br><span class="muted"><?= e(Lang::country((string) $me['country'])) ?><?= $me['iphone_model'] !== '' ? ', ' . te((string) $me['iphone_model']) : '' ?></span></p>
    <p class="row"><a class="btn btn-small" href="/p/<?= e($slug) ?>/details"><?= icon('user') ?> <?= te('Edit your details') ?></a> <a class="btn btn-small" href="/p/<?= e($slug) ?>/email"><?= icon('pencil') ?> <?= te('Change email') ?></a></p>
    <p class="muted"><?= te('To come back later, open the same link and enter this email again.') ?>
       <?= t('To withdraw from the project or have your data removed, email {email}.', ['email' => '<a href="mailto:' . e(cfg('privacy_email')) . '">' . e(cfg('privacy_email')) . '</a>']) ?></p>
  </section>
</aside>
</div>

<nav class="tabbar" id="tabbar" aria-label="<?= te('Sections of this page') ?>">
  <a href="#add" data-tab="add"><?= icon('upload') ?><span><?= te('Upload') ?></span></a>
  <a href="#mine" data-tab="mine"><?= icon('tiles') ?><span><?= te('Photos') ?></span><b class="tab-n" id="tab-n"<?= $photos ? '' : ' hidden' ?>><?= count($photos) ?></b></a>
  <a href="#info" data-tab="info"><?= icon($reward ? 'coin' : 'info') ?><span><?= e($infoTab) ?></span></a>
  <a href="#account" data-tab="account"><?= icon('user') ?><span><?= te('Account') ?></span></a>
</nav>
</div>
<script src="/assets/upload.js?v=<?= e((string) @filemtime(PW_ROOT . '/public/assets/upload.js')) ?>" defer></script>
