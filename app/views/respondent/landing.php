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
$reward = reward($project);
// Every model the project accepts, by name, so people can see at a glance whether theirs counts.
$extraModels = preg_split('/\R/', (string) $project['extra_models']) ?: [];
$phones = array_values(array_filter(PW_IPHONES, static fn($m) => str_starts_with($m, 'iPhone') && PhotoCheck::modelAllowed($m, $min, $extraModels)));
// The hero shows one phone per kind of photo wanted. An example photo saved in the console fills the phone's screen;
// without one, an icon stands in.
$slides = $subjects ?: [['label' => 'Your photos', 'icon' => 'camera', 'tone' => 'sky']];
$messages = take_flashes(); // shown beside the form they are about, not at the top of a long page
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-copy">
      <img class="hero-logo" src="<?= e(asset('logo-white.png')) ?>" alt="Reward Hours" width="94" height="40" loading="lazy">
      <p class="hero-project"><?= e($project['name']) ?></p>
      <h1><?= te('Share photos taken on your iPhone {min} or newer', ['min' => $min]) ?></h1>
      <?php if (trim((string) $project['intro']) !== ''): ?><p class="hero-lead"><?= nl2br(e($project['intro']), false) ?></p><?php endif; ?>
      <p class="hero-lead hero-both"><?= te('Photos already in your library or fresh captures. Both are welcome.') ?></p>
      <?php if ($reward && !$closed): ?>
        <p class="hero-reward"><?= icon('coin') ?><span><?= t('We pay {amount} for every {n} accepted photos.', ['amount' => '<strong>' . e($reward['amount']) . '</strong>', 'n' => '<strong>' . $reward['every'] . '</strong>']) ?><?= $perPerson ? '' : ' ' . te('There is no limit on how many you send.') ?></span></p>
      <?php endif; ?>
      <a class="btn btn-sun btn-big hero-cta" href="#start"><?= $closed ? te('See your photos') : te('Start uploading') ?></a>
    </div>

    <?php if ($phones): ?>
    <?php // One phone per kind of photo, sliding to the next. ?>
    <div class="slider" id="phones" role="group" aria-roledescription="carousel" aria-label="<?= te('Kinds of photo we are looking for') ?>">
      <div class="slider-window">
        <ul class="slider-track">
          <?php foreach ($slides as $i => $look): $pic = phone_picture($look['label'], 640); ?>
            <li class="slide" data-name="<?= te($look['label']) ?>">
              <div class="phone finish-<?= $i % 5 ?>">
                <div class="phone-screen tone-<?= e($look['tone']) ?><?= $pic ? ' has-photo' : '' ?>">
                  <?php if ($pic): ?>
                    <img class="phone-photo" src="<?= e($pic) ?>" srcset="<?= e(phone_srcset($look['label'])) ?>" sizes="270px" alt="<?= te('Example photo: {name}', ['name' => t($look['label'])]) ?>" decoding="async"<?= $i ? ' loading="lazy"' : ' fetchpriority="high"' ?>>
                  <?php else: ?>
                    <span class="phone-focus"><?= icon($look['icon']) ?></span>
                  <?php endif; ?>
                  <span class="phone-cam"></span>
                  <span class="phone-chip">HEIC</span>
                  <div class="phone-ui">
                    <p class="phone-modes"><span>Video</span><span class="on">Photo</span><span>Portrait</span></p>
                    <div class="phone-keys">
                      <?php if ($pic): ?><img class="phone-last" src="<?= e($pic) ?>" alt="" loading="lazy"><?php else: ?><span class="phone-last"></span><?php endif; ?>
                      <span class="phone-shutter"></span>
                      <span class="phone-flip"><?= icon('refresh') ?></span>
                    </div>
                  </div>
                </div>
              </div>
              <?php if (!$pic): ?><span class="visually-hidden"><?= te($look['label']) ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="slider-bar">
        <?php $many = count($slides) > 1; // a single phone stays still, so it needs no arrows ?>
        <?php if ($many): ?><button class="slider-btn slider-prev" type="button" aria-label="<?= te('Previous kind of photo') ?>"><?= icon('arrow') ?></button><?php endif; ?>
        <p class="slider-name"><?= te($slides[0]['label']) ?></p>
        <?php if ($many): ?><button class="slider-btn" type="button" data-next="1" aria-label="<?= te('Next kind of photo') ?>"><?= icon('arrow') ?></button><?php endif; ?>
      </div>
      <p class="slider-count"><?= te('{n} models accepted, and any newer iPhone', ['n' => count($phones)]) ?></p>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="band band-tint start-band" id="start">
  <div class="wrap start">
    <div class="start-side">
      <h2 class="band-title"><?= $closed ? te('Sign in to see your photos') : te('Take part') ?></h2>
      <?php if ($closed): ?>
        <p class="start-lead"><?= te('Use the email address you took part with. We email you a code, so there is no password to remember.') ?></p>
      <?php else: ?>
        <p class="start-lead"><?= te('Three short steps. You are on the first one.') ?></p>
        <ol class="steps">
          <li class="step step-now">
            <span class="step-n" aria-hidden="true">1</span>
            <div><p class="step-t"><?= te('Agree to the terms and enter your email') ?></p><p class="step-d"><?= te('Tick the box and tell us where to send your sign-in code.') ?></p></div>
          </li>
          <li class="step">
            <span class="step-n" aria-hidden="true">2</span>
            <div><p class="step-t"><?= te('Enter the code we email you') ?></p><p class="step-d"><?= te('Six digits, valid for 10 minutes. There is no password to remember.') ?></p></div>
          </li>
          <li class="step">
            <span class="step-n" aria-hidden="true">3</span>
            <div><p class="step-t"><?= te('Add your details and upload your photos') ?></p><p class="step-d"><?= $perPerson ? te('You can upload up to {n} photos.', ['n' => $perPerson]) : te('There is no limit for this project.') ?><?= $perDay ? ' ' . te('At most {n} per day.', ['n' => $perDay]) : '' ?> <?= $reward ? te('We pay {amount} for every {n} accepted photos.', ['amount' => $reward['amount'], 'n' => $reward['every']]) : te('Choose them from your library or take new ones.') ?></p></div>
          </li>
        </ol>
      <?php endif; ?>
      <?php if ($project['closes_at'] && !$closed): ?><p class="start-until"><?= icon('calendar') ?><span><?= t('Open until {date}', ['date' => '<strong>' . e(Lang::date($project['closes_at'], true)) . '</strong> (' . e(date('T')) . ')']) ?></span></p><?php endif; ?>
    </div>
    <div class="start-card">
      <h3 class="start-h"><?= $closed ? te('Sign in') : te('Start here') ?></h3>
      <?php foreach ($messages as [$type, $msg]): ?>
        <div class="note note-<?= e($type) ?>" role="<?= $type === 'bad' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <p class="terms-label" id="terms-label"><?= te('Terms and conditions') ?><?= Lang::code() === 'en' ? '' : ' <span class="muted">(' . te('written in English') . ')</span>' ?></p>
      <div class="terms" tabindex="0" role="region" aria-labelledby="terms-label" lang="en" dir="ltr"><?= nl2br(e($project['terms']), false) ?></div>
      <form method="post" action="/p/<?= e($slug) ?>/start" class="stack">
        <?= csrf_field() ?>
        <label class="check">
          <input type="checkbox" name="agree" value="1" required>
          <span><?= te('I am 18 or older, I have read these terms and I agree to them.') ?></span>
        </label>
        <label for="email"><?= te('Your email address') ?></label>
        <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" value="<?= e($email) ?>" placeholder="you@example.com">
        <button class="btn btn-main btn-big" type="submit"><?= icon('mail') ?> <?= te('Email me a sign-in code') ?></button>
      </form>
      <p class="start-safe"><?= icon('lock') ?><span><?= te('We send one email with a 6-digit code. It works for 10 minutes.') ?></span></p>
    </div>
  </div>
</section>

<?php if ($closed): ?>
  <div class="wrap band-note"><div class="note note-bad" role="status"><?= te('This project has closed and is no longer accepting new photos. If you already took part, you can still sign in below to see your photos.') ?></div></div>
<?php elseif ($state === 'not_open'): ?>
  <div class="wrap band-note"><div class="note note-warn" role="status"><?= te('This project has not opened yet.') ?><?= $project['opens_at'] ? ' ' . te('It opens on {date}.', ['date' => Lang::date($project['opens_at'], true)]) : '' ?></div></div>
<?php endif; ?>

<section class="band details" id="details">
  <div class="wrap">
    <?php if ($subjects): ?>
    <h2 class="band-title" id="subjects"><?= te('What to photograph') ?></h2>
    <ul class="sheet">
      <?php foreach ($subjects as $s): ?>
        <li class="frame tone-<?= e($s['tone']) ?>">
          <div class="frame-art"><?= icon($s['icon']) ?></div>
          <h3><?= te($s['label']) ?></h3>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <h2 class="band-title details-title"><?= te('Details') ?></h2>
    <div class="faq">
      <details>
        <summary><?= te('How it works') ?><?= icon('chevron') ?></summary>
        <ol class="plain-steps">
          <li><?= t('<strong>Agree to the terms.</strong> Read what the project does with your photos and tick the box.') ?></li>
          <li><?= t('<strong>Confirm your email.</strong> We send a 6-digit code. There is no password to remember.') ?></li>
          <li><?= t('<strong>Add a few details.</strong> Your name, country and which iPhone you use.') ?></li>
          <li><?= t('<strong>Upload from your iPhone.</strong> Choose photos from your library. Come back any time with the same email to add more.') ?></li>
        </ol>
      </details>
      <details>
        <summary><?= te('Which photos are accepted') ?><?= icon('chevron') ?></summary>
        <div class="rules">
          <div class="rule rule-yes">
            <h3><?= icon('check') ?> <?= te('We accept') ?></h3>
            <ul>
              <li><?= icon('phone') ?><span><?= t('Photos taken on an <strong>iPhone {min} or newer</strong>', ['min' => $min]) ?></span></li>
              <li><?= icon('camera') ?><span><?= te('The original file, exactly as the Camera app saved it') ?></span></li>
              <li><?= icon('image') ?><span><?= te('Photos you took before, and photos you take today') ?></span></li>
              <li><?= icon('focus') ?><span><?= te('Clear, sharp, well lit pictures') ?></span></li>
            </ul>
          </div>
          <div class="rule rule-no">
            <h3><?= icon('cross') ?> <?= te('We turn away') ?></h3>
            <ul>
              <li><?= icon('phone') ?><span><?= te('Photos from older iPhones or other phones') ?></span></li>
              <li><?= icon('pencil') ?><span><?= te('Edited, filtered or cropped photos, and screenshots') ?></span></li>
              <li><?= icon('mail') ?><span><?= te('Photos that were sent through WhatsApp, email or social apps') ?></span></li>
              <li><?= icon('copies') ?><span><?= te('Blurry, very dark or duplicate photos') ?></span></li>
              <li><?= icon('shield') ?><span><?= te('Nudity, violence, children, or people who have not agreed to be photographed') ?></span></li>
            </ul>
          </div>
        </div>
      </details>
      <details><summary><?= te('Can I use photos I took a while ago?') ?><?= icon('chevron') ?></summary><p><?= t('Yes. Any photo in your library works, as long as it was taken on an iPhone {min} or newer and is the untouched original.', ['min' => $min]) ?></p></details>
      <details><summary><?= te('Why was my photo turned away?') ?><?= icon('chevron') ?></summary><p><?= t('The usual reasons: it was taken on a different phone, it was edited or cropped, it reached your phone through a chat app (those apps shrink photos and remove the camera details), or it is blurry or too dark. The upload screen tells you which one applies.') ?></p></details>
      <details><summary><?= te('What happens to my photos?') ?><?= icon('chevron') ?></summary><p><?= t('Each photo is checked automatically on our own server, then looked at by our team before anyone else sees it. Approved photos, together with the information the iPhone stores inside the file (including the place, if location was on for your Camera), are passed to the research client. Your name, email and phone number are not. The <a href="/privacy">privacy page</a> has the details.') ?></p></details>
      <details><summary><?= te('Can I come back later, or change my email?') ?><?= icon('chevron') ?></summary><p><?= t('Yes. Open this same link, enter the same email, and type the new code we send you. Your earlier uploads will be there. If you typed the wrong email, choose "Change email address" on the code screen. After you have signed in, use "Change email" on the upload screen.') ?></p></details>
    </div>
  </div>
</section>

<?php // on a phone the main button stays within reach along the bottom, and steps aside while the form is on screen ?>
<div class="cta-bar" data-cta><a class="btn btn-main" href="#start"><?= $closed ? te('See your photos') : te('Start uploading') ?></a></div>
