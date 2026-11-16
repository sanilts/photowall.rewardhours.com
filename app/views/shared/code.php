<?php /** One-time code entry, shared by all three areas. @var string $email @var string $action @var string $resend */ ?>
<section class="card narrow">
  <h1><?= te($heading ?? 'Enter your code') ?></h1>
  <p><?= t('We sent a 6-digit code to <strong>{email}</strong>. It works for {n} minutes. Check your spam folder if you do not see it.', ['email' => e($email), 'n' => Otp::MINUTES]) ?></p>
  <form method="post" action="<?= e($action) ?>" class="stack">
    <?= csrf_field() ?>
    <label for="code"><?= te('Code from the email') ?></label>
    <input class="code-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" maxlength="8" required autofocus>
    <button class="btn btn-main btn-big" type="submit"><?= te('Continue') ?></button>
  </form>
  <div class="code-help">
    <p class="muted"><?= te('No email after a minute or two?') ?></p>
    <form method="post" action="<?= e($resend) ?>">
      <?= csrf_field() ?>
      <button class="btn" type="submit"><?= icon('refresh') ?> <?= te('Send the code again') ?></button>
    </form>
    <a class="btn" href="<?= e($back ?? '/') ?>"><?= icon('pencil') ?> <?= te('Change email address') ?></a>
  </div>
</section>
