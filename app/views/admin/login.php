<section class="card narrow">
  <h1>Staff sign in</h1>
  <form method="post" action="/login" class="stack">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn btn-main" type="submit">Sign in</button>
  </form>
  <?php if (cfg('admin_otp', true)): ?><p class="muted">After your password we email you a 6-digit code as a second step.</p><?php endif; ?>
</section>
