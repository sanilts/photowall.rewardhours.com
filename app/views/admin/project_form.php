<?php
/** @var array $p @var array $clients */
$new = (int) $p['id'] === 0;
$action = $new ? '/projects/new' : '/projects/' . (int) $p['id'] . '/edit';
$dt = static fn(?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
?>
<p class="crumbs"><a href="/">Projects</a> / <?= $new ? 'New project' : '<a href="/projects/' . (int) $p['id'] . '">' . e($p['name']) . '</a> / Settings' ?></p>
<h1><?= $new ? 'New project' : 'Project settings' ?></h1>

<form method="post" action="<?= e($action) ?>" class="form">
  <?= csrf_field() ?>

  <fieldset class="card">
    <legend>Basics</legend>
    <label for="name">Project name</label>
    <input id="name" name="name" required maxlength="160" value="<?= e($p['name']) ?>">
    <label for="slug">Link name <span class="muted">(letters, numbers and dashes; leave empty to make it from the project name)</span></label>
    <input id="slug" name="slug" maxlength="80" value="<?= e($p['slug']) ?>" placeholder="for example: street-photos">
    <div class="grid2">
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['draft' => 'Draft (link does not work)', 'open' => 'Open (accepting photos)', 'closed' => 'Closed (no new photos)'] as $k => $label): ?>
            <option value="<?= $k ?>"<?= $p['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="client_id">Client who may download</label>
        <select id="client_id" name="client_id">
          <option value="0">No client linked yet</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (int) $p['client_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label for="intro">Short description shown to respondents</label>
    <textarea id="intro" name="intro" rows="4" required><?= e($p['intro']) ?></textarea>
    <label for="wanted">What to photograph <span class="muted">(one per line; each line is shown on the project page with a matching icon)</span></label>
    <textarea id="wanted" name="wanted" rows="7"><?= e($p['wanted'] ?? '') ?></textarea>
  </fieldset>

  <fieldset class="card">
    <legend>Limits</legend>
    <div class="grid2">
      <div>
        <label for="photos_per_person">Photos per person <span class="muted">(0 means no limit)</span></label>
        <input id="photos_per_person" name="photos_per_person" type="number" min="0" max="100000" required value="<?= (int) $p['photos_per_person'] ?>">
      </div>
      <div>
        <label for="daily_limit">Photos per person per day <span class="muted">(0 means no daily limit)</span></label>
        <input id="daily_limit" name="daily_limit" type="number" min="0" max="10000" value="<?= (int) $p['daily_limit'] ?>">
      </div>
      <div>
        <label for="opens_at">Opens on <span class="muted">(optional)</span></label>
        <input id="opens_at" name="opens_at" type="datetime-local" value="<?= e($dt($p['opens_at'])) ?>">
      </div>
      <div>
        <label for="closes_at">Last day and time to upload <span class="muted">(optional)</span></label>
        <input id="closes_at" name="closes_at" type="datetime-local" value="<?= e($dt($p['closes_at'])) ?>">
      </div>
    </div>
    <p class="muted">Dates and the daily limit use the server's time zone: <?= e(date_default_timezone_get()) ?>.</p>
  </fieldset>

  <fieldset class="card">
    <legend>Terms and conditions</legend>
    <label for="terms">Shown to every respondent before they can continue. Please have this text reviewed for your project before you open it.</label>
    <textarea id="terms" name="terms" rows="16" required><?= e($p['terms']) ?></textarea>
    <?php if (!$new): ?>
      <label class="check"><input type="checkbox" name="terms_changed" value="1"><span>I changed the terms in a way that matters. Record a new agreement from everyone the next time they sign in. (Current version: <?= (int) $p['terms_version'] ?>)</span></label>
    <?php endif; ?>
  </fieldset>

  <fieldset class="card">
    <legend>Which phones are accepted</legend>
    <div class="grid2">
      <div>
        <label for="min_iphone">Oldest iPhone number accepted</label>
        <input id="min_iphone" name="min_iphone" type="number" min="11" max="99" required value="<?= (int) $p['min_iphone'] ?>">
        <p class="muted">16 accepts iPhone 16, 16e, 17, 18 Pro and every later number.</p>
      </div>
      <div>
        <label for="extra_models">Also accept these models <span class="muted">(one per line, for iPhones without a number)</span></label>
        <textarea id="extra_models" name="extra_models" rows="3"><?= e($p['extra_models']) ?></textarea>
      </div>
    </div>
    <label class="check"><input type="checkbox" name="strict_original" value="1"<?= (int) $p['strict_original'] ? ' checked' : '' ?>><span>Strict original check: the file must carry the hidden Apple camera notes and lens details that a real, untouched iPhone photo has. Recommended.</span></label>
  </fieldset>

  <fieldset class="card">
    <legend>Quality and safety checks</legend>
    <div class="grid2">
      <div>
        <label for="min_megapixels">Smallest photo accepted (megapixels)</label>
        <input id="min_megapixels" name="min_megapixels" type="number" step="0.1" min="0" max="200" value="<?= e((float) $p['min_megapixels']) ?>">
      </div>
      <div>
        <label for="blur_min">Sharpness needed (0 to 100)</label>
        <input id="blur_min" name="blur_min" type="number" step="1" min="0" max="100" value="<?= e((float) $p['blur_min']) ?>">
        <p class="muted">Sharp photos usually score 35 to 75. Raise this to be stricter about blur, or set 0 to switch the check off.</p>
      </div>
      <div>
        <label for="dark_min">Too dark below (brightness 0 to 255)</label>
        <input id="dark_min" name="dark_min" type="number" step="1" min="0" max="255" value="<?= e((float) $p['dark_min']) ?>">
      </div>
      <div>
        <label for="bright_max">Too bright above (brightness 0 to 255)</label>
        <input id="bright_max" name="bright_max" type="number" step="1" min="0" max="255" value="<?= e((float) $p['bright_max']) ?>">
      </div>
      <div>
        <label for="dup_distance">Duplicate photos</label>
        <select id="dup_distance" name="dup_distance">
          <?php foreach ([-1 => 'Only block the exact same file', 2 => 'Also block near-identical pictures (careful)', 4 => 'Also block near-identical pictures (normal)', 8 => 'Also block similar pictures (strict)'] as $k => $label): ?>
            <option value="<?= $k ?>"<?= (int) $p['dup_distance'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div></div>
      <div>
        <label for="safety_block">Safety scan: discard at score (0 to 1)</label>
        <input id="safety_block" name="safety_block" type="number" step="0.01" min="0.05" max="1" value="<?= e((float) $p['safety_block']) ?>">
      </div>
      <div>
        <label for="safety_review">Safety scan: flag for careful review at score (0 to 1)</label>
        <input id="safety_review" name="safety_review" type="number" step="0.01" min="0.01" max="1" value="<?= e((float) $p['safety_review']) ?>">
      </div>
    </div>
    <p class="muted">The safety scan runs on this server and looks for nudity and sexual content. It cannot recognise every kind of unsuitable photo, which is why nothing reaches the client until a member of staff approves it.</p>
  </fieldset>

  <div class="row">
    <button class="btn btn-main" type="submit"><?= $new ? 'Create project' : 'Save changes' ?></button>
    <a class="btn btn-quiet" href="<?= $new ? '/' : '/projects/' . (int) $p['id'] ?>">Cancel</a>
  </div>
</form>
