<?php /** One-time code entry, shared by all three areas. @var string $email @var string $action @var string $resend */ ?>
<section class="card narrow">
  <h1>Enter your code</h1>
  <p>We sent a 6-digit code to <strong><?= e($email) ?></strong>. It works for <?= Otp::MINUTES ?> minutes. Check your spam folder if you do not see it.</p>
  <form method="post" action="<?= e($action) ?>" class="stack">
    <?= csrf_field() ?>
    <label for="code">Code from the email</label>
    <input class="code-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" maxlength="8" required autofocus>
    <button class="btn btn-main" type="submit">Continue</button>
  </form>
  <form method="post" action="<?= e($resend) ?>" class="inline-form">
    <?= csrf_field() ?>
    <button class="btn btn-link" type="submit">Send a new code</button>
    <a class="btn btn-link" href="<?= e($back ?? '/') ?>">Use a different email</a>
  </form>
</section>
