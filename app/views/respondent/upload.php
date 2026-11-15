<?php
/** @var array $project @var array $me @var array $allow @var array $photos */
$slug = $project['slug'];
$total = (int) $project['photos_per_person'];
$reward = reward($project);
$accepted = count(array_filter($photos, static fn($ph) => $ph['status'] === 'approved'));
$labels = ['pending' => 'Received, in review', 'approved' => 'Approved', 'rejected' => 'Not accepted'];
?>
<section class="page-head">
  <p class="eyebrow"><?= e($project['name']) ?></p>
  <h1>Upload your photos</h1>
</section>

<section class="card" id="uploader" data-url="/p/<?= e($slug) ?>/upload" data-csrf="<?= e(csrf_token()) ?>" data-can="<?= (int) $allow['can'] ?>" data-max-mb="<?= (int) cfg('max_upload_mb', 60) ?>">
  <div class="meter" aria-live="polite">
    <?php if ($total > 0): ?>
      <div><strong id="used"><?= (int) $allow['used'] ?></strong> of <?= $total ?> photos uploaded</div>
      <progress id="used-bar" max="<?= $total ?>" value="<?= (int) $allow['used'] ?>"><?= (int) $allow['used'] ?> of <?= $total ?></progress>
    <?php else: ?>
      <div><strong id="used"><?= (int) $allow['used'] ?></strong> photos uploaded. There is no limit for this project.</div>
    <?php endif; ?>
    <?php if ($allow['today_left'] !== null): ?>
      <div class="muted">Today you can still add <strong id="today-left"><?= (int) $allow['today_left'] ?></strong> (limit <?= (int) $project['daily_limit'] ?> per day).</div>
    <?php endif; ?>
    <?php if ($reward): $toNext = $reward['every'] - $accepted % $reward['every']; ?>
      <div class="earn">
        <?= icon('coin') ?>
        <div>
          <strong><?= e(reward_earned($reward, $accepted)) ?> earned so far.</strong> We pay <?= e($reward['amount']) ?> for every <?= $reward['every'] ?> accepted photos.
          <span class="muted"><?= $accepted ?> accepted, <?= $toNext ?> more for the next <?= e($reward['amount']) ?>. A photo counts once our team has accepted it.</span>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($project['closes_at']): ?><div class="muted">Uploads close on <?= e(nice_date($project['closes_at'])) ?> (<?= e(date('T')) ?>).</div><?php endif; ?>
  </div>

  <?php if ((int) $me['blocked']): ?>
    <div class="note note-bad">Your account cannot upload photos to this project. If you think this is a mistake, email <?= e(cfg('privacy_email')) ?>.</div>
  <?php elseif ($allow['state'] !== 'open'): ?>
    <div class="note note-warn">This project is <?= $allow['state'] === 'closed' ? 'closed' : 'not open yet' ?>, so photos cannot be added right now.</div>
  <?php elseif ($allow['left'] === 0): ?>
    <div class="note note-ok">You have uploaded all <?= $total ?> photos. Thank you. If one is not accepted, you can come back and replace it.</div>
  <?php elseif ($allow['can'] <= 0): ?>
    <div class="note note-warn">You have reached today's limit. Please come back tomorrow to add more.</div>
  <?php endif; ?>

  <div id="picker"<?= $allow['can'] > 0 ? '' : ' hidden' ?>>
    <input class="visually-hidden" id="files" type="file" accept="image/*" multiple>
    <label class="drop" for="files">
      <?= icon('upload') ?>
      <span class="drop-title">Choose photos</span>
      <span class="muted">Tap here, choose <strong>Photo Library</strong>, then pick photos you took with this iPhone.</span>
    </label>
    <ul class="tips">
      <li>Take the photo with the iPhone <strong>Camera</strong> app first, then choose it from Photo Library.</li>
      <li>Do not edit, crop or add filters. Do not use photos you received in WhatsApp or other apps.</li>
      <li>Each photo is checked as it uploads. If one is turned away you will see why, and you can pick another.</li>
    </ul>
  </div>
  <ul id="queue" class="queue" aria-live="polite"></ul>
</section>

<section class="card">
  <h2>Your photos</h2>
  <?php if (!$photos): ?><p class="muted" id="none-yet">Nothing uploaded yet.</p><?php endif; ?>
  <ul class="grid" id="mine">
    <?php foreach ($photos as $p): ?>
      <li class="shot">
        <?php if ($p['status'] === 'rejected'): ?>
          <div class="shot-gone">Photo removed</div>
        <?php else: ?>
          <img src="/p/<?= e($slug) ?>/thumb/<?= e($p['token']) ?>" alt="Your uploaded photo" loading="lazy" width="240" height="240">
        <?php endif; ?>
        <span class="chip chip-<?= e($p['status']) ?>"><?= e($labels[$p['status']] ?? $p['status']) ?></span>
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
  <div class="account">
    <span>Signed in as <strong><?= e($me['email']) ?></strong></span>
    <a class="btn btn-small" href="/p/<?= e($slug) ?>/email"><?= icon('pencil') ?> Change email</a>
  </div>
  <p class="muted">To come back later, open the same link and enter this email again.
     To withdraw from the project or have your data removed, email <a href="mailto:<?= e(cfg('privacy_email')) ?>"><?= e(cfg('privacy_email')) ?></a>.</p>
</section>
<script src="/assets/upload.js?v=<?= e((string) @filemtime(PW_ROOT . '/public/assets/upload.js')) ?>" defer></script>
