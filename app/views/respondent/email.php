<?php /** @var array $project @var array $me */ ?>
<section class="card narrow">
  <p class="eyebrow"><?= e($project['name']) ?></p>
  <h1>Change your email</h1>
  <p>You are signed in as <strong><?= e($me['email']) ?></strong>. Enter the address you want to use from now on. We send a code to the new address to confirm it is yours. Your photos stay with you.</p>
  <form method="post" action="/p/<?= e($project['slug']) ?>/email" class="stack">
    <?= csrf_field() ?>
    <label for="email">New email address</label>
    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="190" autofocus placeholder="you@example.com">
    <button class="btn btn-main btn-big" type="submit">Send a code to the new address</button>
  </form>
  <p><a class="btn btn-link" href="/p/<?= e($project['slug']) ?>/upload">Keep my current email</a></p>
</section>
