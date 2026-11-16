<?php /** @var array $project @var array $me @var array $models @var string $here */
$names = countries();
$chosen = $me['country'] !== '' ? $me['country'] : $here;
?>
<section class="card narrow">
  <p class="eyebrow"><?= e($project['name']) ?></p>
  <h1><?= te('Your details') ?></h1>
  <p><?= t('We only ask for what the project needs. Signed in as <strong>{email}</strong>.', ['email' => e($me['email'])]) ?></p>
  <form method="post" action="/p/<?= e($project['slug']) ?>/details" class="stack">
    <?= csrf_field() ?>
    <label for="full_name"><?= te('Full name') ?></label>
    <input id="full_name" name="full_name" autocomplete="name" required maxlength="120" value="<?= e($me['full_name']) ?>">
    <label for="country"><?= te('Country you live in') ?></label>
    <select id="country" name="country" autocomplete="country-name" required data-here="<?= e($here) ?>">
      <option value=""><?= te('Choose your country') ?></option>
      <?php if ($chosen !== '' && !in_array($chosen, $names, true)): ?><option selected><?= e($chosen) ?></option><?php endif; ?>
      <?php foreach ($names as $n): ?><option value="<?= e($n) ?>"<?= $chosen === $n ? ' selected' : '' ?>><?= e(Lang::country($n)) ?></option><?php endforeach; ?>
    </select>
    <p class="muted hint" id="country-note" hidden><?= te('Your connection appears to be in {country}. That is fine if you live somewhere else: choose the country you live in.', ['country' => Lang::country($here)]) ?></p>
    <label for="phone"><?= te('Phone number') ?> <span class="muted"><?= te('(optional)') ?></span></label>
    <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="<?= e($me['phone']) ?>">
    <label for="iphone_model"><?= te('Which iPhone will you take the photos with?') ?></label>
    <select id="iphone_model" name="iphone_model" required>
      <option value=""><?= te('Choose your iPhone') ?></option>
      <?php foreach ($models as $m): ?>
        <option value="<?= e($m) ?>"<?= $me['iphone_model'] === $m ? ' selected' : '' ?>><?= te($m) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-main" type="submit"><?= te('Save and continue') ?></button>
  </form>
</section>
