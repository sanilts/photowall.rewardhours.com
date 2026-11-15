<?php /** @var array $tiles @var string $contact */ ?>
<div class="signin">
  <section class="card signin-form">
    <h1>Client sign in</h1>
    <p>Enter your work email. If it has access, we send a 6-digit code to sign you in. No password is needed.</p>
    <form method="post" action="/login" class="stack">
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required autofocus maxlength="190" placeholder="name@company.com">
      <button class="btn btn-main" type="submit">Email me a code</button>
    </form>
    <ul class="signin-notes">
      <li><?= icon('lock') ?><span>Only people your company has named can sign in. The code works once and for 10 minutes.</span></li>
      <li><?= icon('mail') ?><span>No access yet? Write to <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</span></li>
    </ul>
  </section>

  <section class="signin-show" aria-labelledby="signin-about">
    <ul class="mosaic" aria-label="Examples of the kinds of photos we collect">
      <?php foreach ($tiles as $t): ?>
      <li class="tone-<?= e($t['tone']) ?>">
        <?php if ($t['pic']): ?><img src="<?= e($t['pic']) ?>" alt="Example photo: <?= e($t['label']) ?>">
        <?php else: ?><?= icon($t['icon']) ?><?php endif; ?>
        <span><?= e($t['short']) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <h2 id="signin-about">Original iPhone photos, checked before they reach you</h2>
    <ul class="signin-points">
      <li><?= icon('shield') ?><span>Every photo comes straight from the contributor's iPhone and is checked for edits, screenshots and duplicates.</span></li>
      <li><?= icon('eye') ?><span>You see only the photos our team has accepted, each with its camera information.</span></li>
      <li><?= icon('doc') ?><span>The original files come with a sheet that lists every photo, its category and its contributor ID.</span></li>
    </ul>
  </section>
</div>
