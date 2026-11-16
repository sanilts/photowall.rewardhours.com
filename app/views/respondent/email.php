<?php /** @var array $project @var array $me */ ?>
<section class="card narrow">
  <p class="eyebrow"><?= e($project['name']) ?></p>
  <h1><?= te('Change your email') ?></h1>
  <p><?= t('You are signed in as <strong>{email}</strong>. Enter the address you want to use from now on. We send a code to the new address to confirm it is yours. Your photos stay with you.', ['email' => e($me['email'])]) ?></p>
  <form method="post" action="/p/<?= e($project['slug']) ?>/email" class="stack">
    <?= csrf_field() ?>
    <label for="email"><?= te('New email address') ?></label>
    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" autofocus placeholder="you@example.com">
    <button class="btn btn-main btn-big" type="submit"><?= te('Send a code to the new address') ?></button>
  </form>
  <p><a class="btn btn-link" href="/p/<?= e($project['slug']) ?>/upload"><?= te('Keep my current email') ?></a></p>
</section>
