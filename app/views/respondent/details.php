<?php /** @var array $project @var array $me @var array $models */ ?>
<section class="card narrow">
  <p class="eyebrow"><?= e($project['name']) ?></p>
  <h1>Your details</h1>
  <p>We only ask for what the project needs. Signed in as <strong><?= e($me['email']) ?></strong>.</p>
  <form method="post" action="/p/<?= e($project['slug']) ?>/details" class="stack">
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" autocomplete="name" required maxlength="120" value="<?= e($me['full_name']) ?>">
    <label for="country">Country you live in</label>
    <input id="country" name="country" autocomplete="country-name" required maxlength="80" value="<?= e($me['country']) ?>">
    <label for="phone">Phone number <span class="muted">(optional)</span></label>
    <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="<?= e($me['phone']) ?>">
    <label for="iphone_model">Which iPhone will you take the photos with?</label>
    <select id="iphone_model" name="iphone_model" required>
      <option value="">Choose your iPhone</option>
      <?php foreach ($models as $m): ?>
        <option<?= $me['iphone_model'] === $m ? ' selected' : '' ?>><?= e($m) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-main" type="submit">Save and continue</button>
  </form>
</section>
