<section class="card narrow">
  <h1>Client sign in</h1>
  <p>Enter your work email. If it has access, we send a 6-digit code to sign you in. No password is needed.</p>
  <form method="post" action="/login" class="stack">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="email" required autofocus maxlength="190">
    <button class="btn btn-main" type="submit">Email me a code</button>
  </form>
</section>
