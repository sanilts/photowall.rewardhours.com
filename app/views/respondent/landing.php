<?php
/** @var array $project @var string $state @var string $email */
$min = (int) $project['min_iphone'];
$slug = $project['slug'];
$perPerson = (int) $project['photos_per_person'];
$perDay = (int) $project['daily_limit'];
$subjects = [];
foreach (preg_split('/\R/', (string) ($project['wanted'] ?? '')) ?: [] as $line) {
    if (trim($line) !== '') $subjects[] = ['label' => trim($line)] + subject_look($line);
}
$closed = $state === 'closed';
$messages = take_flashes(); // shown beside the form they are about, not at the top of a long page
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-copy">
      <p class="hero-project"><?= e($project['name']) ?></p>
      <h1>Share photos taken on your iPhone&nbsp;<?= $min ?> or newer</h1>
      <p class="hero-lead"><?= nl2br(e($project['intro'])) ?></p>
      <p class="hero-lead hero-both">Photos already in your library or fresh captures. Both are welcome.</p>
      <div class="hero-actions">
        <a class="btn btn-sun" href="#start"><?= $closed ? 'See your photos' : 'Start uploading' ?></a>
        <?php if ($subjects): ?><a class="btn btn-ghost" href="#subjects">What to photograph</a><?php endif; ?>
      </div>
      <ul class="hero-facts">
        <li><?= icon('phone') ?><span>iPhone <?= $min ?> or newer</span></li>
        <li><?= icon('image') ?><span><?= $perPerson ? 'Up to <strong>' . $perPerson . ' photos</strong> per person' : '<strong>As many photos as you like</strong>' ?><?= $perDay ? ', at most ' . $perDay . ' per day' : '' ?></span></li>
        <?php if ($project['closes_at']): ?><li><?= icon('calendar') ?><span>Open until <?= e(nice_date($project['closes_at'], 'j M Y, H:i')) ?> (<?= e(date('T')) ?>)</span></li><?php endif; ?>
        <li><?= icon('lock') ?><span>No password. We email you a code.</span></li>
      </ul>
    </div>

    <?php if ($subjects): ?>
    <div class="vf" id="viewfinder" aria-hidden="true">
      <div class="vf-screen">
        <span class="vf-corner vf-tl"></span><span class="vf-corner vf-tr"></span><span class="vf-corner vf-bl"></span><span class="vf-corner vf-br"></span>
        <div class="vf-status"><span class="vf-rec"></span><span>iPhone <?= $min ?></span><span class="vf-format">HEIC</span></div>
        <?php foreach ($subjects as $i => $s): ?>
          <div class="vf-slide tone-<?= e($s['tone']) ?><?= $i === 0 ? ' is-on' : '' ?>"><?= icon($s['icon']) ?></div>
        <?php endforeach; ?>
        <span class="vf-focus"></span>
        <span class="vf-flash"></span>
      </div>
      <div class="vf-modes">
        <div class="vf-track">
          <?php foreach ($subjects as $i => $s): ?><span class="vf-mode<?= $i === 0 ? ' is-on' : '' ?>"><?= e($s['short']) ?></span><?php endforeach; ?>
        </div>
      </div>
      <div class="vf-shutter"><span></span></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($closed): ?>
  <div class="wrap band-note"><div class="note note-bad" role="status">This project has closed and is no longer accepting new photos. If you already took part, you can still sign in below to see your photos.</div></div>
<?php elseif ($state === 'not_open'): ?>
  <div class="wrap band-note"><div class="note note-warn" role="status">This project has not opened yet<?= $project['opens_at'] ? '. It opens on ' . e(nice_date($project['opens_at'])) : '' ?>.</div></div>
<?php endif; ?>

<?php if ($subjects): ?>
<section class="band" id="subjects">
  <div class="wrap">
    <h2 class="band-title">What to photograph</h2>
    <p class="band-lead">Pick any of these, or all of them. Everyday scenes are exactly what we are looking for.</p>
    <ul class="sheet">
      <?php foreach ($subjects as $s): ?>
        <li class="frame tone-<?= e($s['tone']) ?>">
          <div class="frame-art"><?= icon($s['icon']) ?></div>
          <div class="frame-text">
            <h3><?= e($s['label']) ?></h3>
            <?php if ($s['hint'] !== ''): ?><p><?= e($s['hint']) ?></p><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="band band-tint" id="how">
  <div class="wrap">
    <h2 class="band-title">How it works</h2>
    <ol class="steps">
      <li><span class="step-ico"><?= icon('doc') ?></span><h3>Agree to the terms</h3><p>Read what the project does with your photos and tick the box.</p></li>
      <li><span class="step-ico"><?= icon('mail') ?></span><h3>Confirm your email</h3><p>We send a 6-digit code. There is no password to remember.</p></li>
      <li><span class="step-ico"><?= icon('person') ?></span><h3>Add a few details</h3><p>Your name, country and which iPhone you use.</p></li>
      <li><span class="step-ico"><?= icon('upload') ?></span><h3>Upload from your iPhone</h3><p>Choose photos from your library. Come back any time with the same email to add more.</p></li>
    </ol>
  </div>
</section>

<section class="band" id="rules">
  <div class="wrap">
    <h2 class="band-title">Which photos are accepted</h2>
    <p class="band-lead">Every photo is checked the moment it uploads. If one is turned away you see why straight away and can pick another.</p>
    <div class="rules">
      <div class="rule rule-yes">
        <h3><?= icon('check') ?> We accept</h3>
        <ul>
          <li><?= icon('phone') ?><span>Photos taken on an <strong>iPhone <?= $min ?> or newer</strong></span></li>
          <li><?= icon('camera') ?><span>The original file, exactly as the Camera app saved it</span></li>
          <li><?= icon('image') ?><span>Photos you took before, and photos you take today</span></li>
          <li><?= icon('focus') ?><span>Clear, sharp, well lit pictures</span></li>
        </ul>
      </div>
      <div class="rule rule-no">
        <h3><?= icon('cross') ?> We turn away</h3>
        <ul>
          <li><?= icon('phone') ?><span>Photos from older iPhones or other phones</span></li>
          <li><?= icon('pencil') ?><span>Edited, filtered or cropped photos, and screenshots</span></li>
          <li><?= icon('mail') ?><span>Photos that were sent through WhatsApp, email or social apps</span></li>
          <li><?= icon('copies') ?><span>Blurry, very dark or duplicate photos</span></li>
          <li><?= icon('shield') ?><span>Nudity, violence, children, or people who have not agreed to be photographed</span></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="band band-navy" id="start">
  <div class="wrap start">
    <div class="start-copy">
      <h2 class="band-title"><?= $closed ? 'Sign in to see your photos' : 'Ready when you are' ?></h2>
      <p>Open this page on the iPhone that holds the photos. It takes about two minutes to get to the upload screen.</p>
      <ul class="start-points">
        <li><?= icon('shield') ?><span>Your name, email and phone number are never passed to the research client.</span></li>
        <li><?= icon('eye') ?><span>Our team looks at every photo before anyone else sees it.</span></li>
        <li><?= icon('refresh') ?><span>Already started? Enter the same email to pick up where you left off.</span></li>
      </ul>
    </div>
    <div class="start-card">
      <?php foreach ($messages as [$type, $msg]): ?>
        <div class="note note-<?= e($type) ?>" role="<?= $type === 'bad' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <h3>Terms and conditions</h3>
      <div class="terms" tabindex="0" role="region" aria-label="Terms and conditions"><?= nl2br(e($project['terms'])) ?></div>
      <form method="post" action="/p/<?= e($slug) ?>/start" class="stack">
        <?= csrf_field() ?>
        <label class="check">
          <input type="checkbox" name="agree" value="1" required>
          <span>I am 18 or older, I have read these terms and I agree to them.</span>
        </label>
        <label for="email">Your email address</label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" value="<?= e($email) ?>" placeholder="you@example.com">
        <button class="btn btn-main btn-big" type="submit">Email me a sign-in code</button>
      </form>
    </div>
  </div>
</section>

<section class="band" id="questions">
  <div class="wrap narrow-wrap">
    <h2 class="band-title">Questions people ask</h2>
    <div class="faq">
      <details><summary>Can I use photos I took a while ago?<?= icon('chevron') ?></summary><p>Yes. Any photo in your library works, as long as it was taken on an iPhone <?= $min ?> or newer and is the untouched original.</p></details>
      <details><summary>Why was my photo turned away?<?= icon('chevron') ?></summary><p>The usual reasons: it was taken on a different phone, it was edited or cropped, it reached your phone through a chat app (those apps shrink photos and remove the camera details), or it is blurry or too dark. The upload screen tells you which one applies.</p></details>
      <details><summary>How do I pick the right file on my iPhone?<?= icon('chevron') ?></summary><p>Take the picture with the Camera app. On the upload screen tap "Choose photos", then "Photo Library", and select it. Do not edit it first.</p></details>
      <details><summary>What happens to my photos?<?= icon('chevron') ?></summary><p>Each photo is checked automatically on our own server, then looked at by our team. Approved photos, together with the information the iPhone stores inside the file, are passed to the research client. Your name, email and phone number are not. The <a href="/privacy">privacy page</a> has the details.</p></details>
      <details><summary>Do my photos show where they were taken?<?= icon('chevron') ?></summary><p>If location was switched on for your Camera when you took the photo, the place is stored inside the file and stays there. To leave it out of new photos, switch off location for Camera in your iPhone settings before you take them.</p></details>
      <details><summary>Can I stop halfway and come back?<?= icon('chevron') ?></summary><p>Yes. Open this same link, enter the same email, and type the new code we send you. Your earlier uploads will be there.</p></details>
      <details><summary>I typed the wrong email, or I want to change it<?= icon('chevron') ?></summary><p>If the code has not arrived, choose "Change email address" on the code screen and enter the right one. After you have signed in, use "Change email" on the upload screen.</p></details>
    </div>
  </div>
</section>
