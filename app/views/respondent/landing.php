<?php
/** @var array $project @var string $state @var string $email */
$min = (int) $project['min_iphone'];
?>
<section class="hero">
  <p class="eyebrow">Photo project</p>
  <h1><?= e($project['name']) ?></h1>
  <p class="lead"><?= nl2br(e($project['intro'])) ?></p>
</section>

<?php if ($state === 'closed'): ?>
  <div class="note note-bad" role="status">This project has closed and is no longer accepting new photos. If you already took part, you can still sign in below to see your photos.</div>
<?php elseif ($state === 'not_open'): ?>
  <div class="note note-warn" role="status">This project has not opened yet<?= $project['opens_at'] ? '. It opens on ' . e(nice_date($project['opens_at'])) : '' ?>.</div>
<?php endif; ?>

<div class="cols">
  <section class="card">
    <h2>What you need</h2>
    <ul class="checks">
      <li>An <strong>iPhone <?= $min ?> or newer</strong>. Photos from older iPhones or other phones are not accepted.</li>
      <li><strong>Original photos</strong> taken with the iPhone Camera app. No screenshots, no edited or filtered photos, nothing forwarded through WhatsApp or other apps.</li>
      <li>Up to <strong><?= (int) $project['photos_per_person'] ?> photos</strong> per person<?= (int) $project['daily_limit'] ? ', at most ' . (int) $project['daily_limit'] . ' per day' : '' ?>.</li>
      <?php if ($project['closes_at']): ?><li>Uploads close on <strong><?= e(nice_date($project['closes_at'])) ?></strong> (<?= e(date('T')) ?>).</li><?php endif; ?>
      <li>Clear, sharp photos. Blurry, very dark and duplicate photos are turned away automatically.</li>
    </ul>
    <h2>How it works</h2>
    <ol class="steps">
      <li>Read the terms and agree to them.</li>
      <li>Enter your email. We send you a 6-digit code, no password needed.</li>
      <li>Fill in a few basic details.</li>
      <li>Upload your photos from this iPhone. You can come back later with the same email to add more.</li>
    </ol>
  </section>

  <section class="card">
    <h2>Terms and conditions</h2>
    <div class="terms" tabindex="0" role="region" aria-label="Terms and conditions"><?= nl2br(e($project['terms'])) ?></div>
    <form method="post" action="/p/<?= e($project['slug']) ?>/start" class="stack">
      <?= csrf_field() ?>
      <label class="check">
        <input type="checkbox" name="agree" value="1" required>
        <span>I am 18 or older, I have read these terms and I agree to them.</span>
      </label>
      <label for="email">Your email address</label>
      <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" value="<?= e($email) ?>" placeholder="you@example.com">
      <button class="btn btn-main" type="submit">Email me a sign-in code</button>
      <p class="muted">Already taking part? Enter the same email to continue where you left off.</p>
    </form>
  </section>
</div>
