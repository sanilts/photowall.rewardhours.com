<?php
/** @var array $m @var array $me @var bool $hasPass @var bool $hasToken @var array $models @var array $gemini @var array $made */
$driver = (string) ($m['driver'] ?? 'log');
if ($driver === 'log' && ($m['source'] ?? '') === 'file') $driver = 'smtp'; // nothing chosen yet: offer the usual choice
$smtp = (array) ($m['smtp'] ?? []);
$secure = (string) ($smtp['secure'] ?? 'tls');
?>
<div class="head"><h1>Settings</h1></div>

<?php if (!Mailer::isSetUp()): ?>
  <div class="note note-warn" role="status">Email is not set up, so respondents and clients cannot receive their sign-in codes. Fill in the form below, save, and send a test.</div>
<?php endif; ?>

<form method="post" action="/settings/mail" class="form">
  <?= csrf_field() ?>
  <fieldset class="card">
    <legend>Email for sign-in codes</legend>
    <p class="muted">Respondents and clients sign in with a 6-digit code sent by email. Choose how those emails are sent.</p>

    <div class="grid2">
      <div>
        <label for="driver">How to send</label>
        <select id="driver" name="driver">
          <option value="smtp"<?= $driver === 'smtp' ? ' selected' : '' ?>>SMTP (any mailbox or mail service)</option>
          <option value="postmark"<?= $driver === 'postmark' ? ' selected' : '' ?>>Postmark</option>
          <option value="log"<?= $driver === 'log' ? ' selected' : '' ?>>Off (codes are only written to a file on the server)</option>
        </select>
      </div>
      <div></div>
      <div>
        <label for="from_email">Send from this address</label>
        <input id="from_email" name="from_email" type="email" maxlength="190" value="<?= e(($m['from_email'] ?? '') === 'no-reply@example.com' ? '' : ($m['from_email'] ?? '')) ?>" placeholder="no-reply@yourdomain.com">
      </div>
      <div>
        <label for="from_name">Sender name people see</label>
        <input id="from_name" name="from_name" maxlength="80" value="<?= e($m['from_name'] ?? 'Photowall') ?>">
      </div>
    </div>

    <h2 class="sub">SMTP details</h2>
    <div class="grid2">
      <div>
        <label for="smtp_host">Server address</label>
        <input id="smtp_host" name="smtp_host" maxlength="190" value="<?= e($smtp['host'] ?? '') ?>" placeholder="smtp.yourprovider.com" autocomplete="off">
      </div>
      <div>
        <label for="smtp_secure">Security and port</label>
        <div class="row">
          <select id="smtp_secure" name="smtp_secure">
            <option value="tls"<?= $secure === 'tls' ? ' selected' : '' ?>>STARTTLS (usually port 587)</option>
            <option value="ssl"<?= $secure === 'ssl' ? ' selected' : '' ?>>SSL (usually port 465)</option>
            <option value="none"<?= $secure === 'none' ? ' selected' : '' ?>>None (not recommended)</option>
          </select>
          <label class="visually-hidden" for="smtp_port">Port</label>
          <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?= (int) ($smtp['port'] ?? 587) ?>">
        </div>
      </div>
      <div>
        <label for="smtp_user">Username</label>
        <input id="smtp_user" name="smtp_user" maxlength="190" value="<?= e($smtp['user'] ?? '') ?>" autocomplete="off" placeholder="Often the full email address">
      </div>
      <div>
        <label for="smtp_pass">Password <?= $hasPass ? '<span class="muted">(saved; leave empty to keep it)</span>' : '' ?></label>
        <input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= $hasPass ? 'Saved' : '' ?>">
      </div>
    </div>
    <p class="muted hint">Common servers: Microsoft 365 uses smtp.office365.com with STARTTLS on 587 (SMTP sign-in must be allowed for that mailbox). Gmail and Google Workspace use smtp.gmail.com with STARTTLS on 587 and an app password. Zoho uses smtp.zoho.com with SSL on 465. The "send from" address normally has to be the mailbox you sign in with.</p>

    <h2 class="sub">Postmark details</h2>
    <label for="postmark_token">Server token <?= $hasToken ? '<span class="muted">(saved; leave empty to keep it)</span>' : '' ?></label>
    <input id="postmark_token" name="postmark_token" type="password" autocomplete="new-password" placeholder="<?= $hasToken ? 'Saved' : 'Only needed when sending with Postmark' ?>">

    <div class="row form-end">
      <button class="btn btn-main" type="submit">Save email settings</button>
      <span class="muted">Passwords are stored encrypted.<?= ($m['source'] ?? '') === 'file' ? ' These values currently come from the settings file on the server; saving here takes over from it.' : '' ?></span>
    </div>
  </fieldset>
</form>

<section class="card">
  <h2>Send a test email</h2>
  <p class="muted">Uses the saved settings. If it fails, the reason from the mail server is shown here.</p>
  <form method="post" action="/settings/mail/test" class="row">
    <?= csrf_field() ?>
    <label class="visually-hidden" for="to">Send the test to</label>
    <input id="to" name="to" type="email" required maxlength="190" value="<?= e($me['email']) ?>">
    <button class="btn" type="submit"><?= icon('mail') ?> Send test email</button>
  </form>
</section>

<section class="card" id="phones">
  <h2>Photos inside the phones on the project page</h2>
  <p class="muted">The project page shows a phone for each kind of photo your projects ask for (the "What to photograph" lines). Save an example photo for each kind and it fills that phone's screen (PNG, JPG or WebP, under 25 MB; HEIC is not accepted here, so export it as JPG first). Upright photos fit best: the screen is tall, so the sides of a wide photo are cut off and the middle stays visible. Without a photo, the phone shows an icon. Use photos you have the right to use.</p>
  <form method="post" action="/settings/phones" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <ul class="pics">
      <?php foreach ($models as $model): $slug = phone_slug($model); $pic = phone_picture($model); ?>
        <li>
          <span class="pic-thumb"><?php if ($pic): ?><img src="<?= e($pic) ?>" alt="" loading="lazy"><?php else: ?><?= icon(subject_look($model)['icon']) ?><?php endif; ?></span>
          <label for="pic-<?= e($slug) ?>"><?= e($model) ?></label>
          <input id="pic-<?= e($slug) ?>" type="file" name="pic[<?= e($slug) ?>]" accept="image/png,image/jpeg,image/webp">
          <?php if ($pic): ?><label class="check"><input type="checkbox" name="remove[<?= e($slug) ?>]" value="1"><span>Remove</span></label><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <button class="btn btn-main" type="submit">Save photos</button>
  </form>
</section>

<section class="card" id="make">
  <h2>Create the photos with Gemini</h2>
  <p class="muted">Instead of uploading files, you can have Google's Gemini create the example photos from a description. This needs your own Gemini API key from Google AI Studio with billing switched on, and Google charges that key for every picture.</p>

  <form method="post" action="/settings/gemini" class="form">
    <?= csrf_field() ?>
    <div class="grid2">
      <div>
        <label for="gemini_key">Gemini API key <?= $gemini['key'] !== '' ? '<span class="muted">(saved; leave empty to keep it)</span>' : '' ?></label>
        <input id="gemini_key" name="gemini_key" type="password" autocomplete="new-password" placeholder="<?= $gemini['key'] !== '' ? 'Saved' : '' ?>">
      </div>
      <div>
        <label for="gemini_model">Image model</label>
        <input id="gemini_model" name="gemini_model" maxlength="80" value="<?= e($gemini['model']) ?>" autocomplete="off">
      </div>
    </div>
    <p class="muted hint">The key is stored encrypted. Change the model name only if Google renames or retires it.</p>
    <div class="row form-end">
      <button class="btn" type="submit">Save Gemini settings</button>
      <?php if ($gemini['key'] !== ''): ?><button class="btn btn-quiet" type="submit" name="forget_key" value="1" data-confirm="Remove the saved Gemini API key?">Remove the key</button><?php endif; ?>
    </div>
  </form>

  <?php if ($gemini['key'] !== ''): ?>
  <h3 class="sub">New picture</h3>
  <form method="post" action="/settings/phones/make" class="form" id="make-form" data-busy="Creating the picture. This can take a minute.">
    <?= csrf_field() ?>
    <label for="make_slug">Picture for</label>
    <select id="make_slug" name="slug">
      <?php foreach ($models as $model): $slug = phone_slug($model); ?>
        <option value="<?= e($slug) ?>"<?= ($made['slug'] ?? '') === $slug ? ' selected' : '' ?><?= Gemini::draftFile($slug) ? ' data-draft="1"' : '' ?>><?= e($model) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="make_prompt">Describe the picture</label>
    <textarea id="make_prompt" name="prompt" rows="5" required minlength="10" maxlength="2000"><?= e(($made['prompt'] ?? '') !== '' ? $made['prompt'] : Gemini::DEFAULT_PROMPT) ?></textarea>
    <p class="muted hint">A starting description is filled in; change any of it. <code>{subject}</code> is replaced by the kind of photo chosen above, so one description works for all of them. Apart from that your words are sent to Google exactly as written, and the picture comes back upright (9:16). Nothing appears on the project page until you press "Use this picture".</p>
    <div class="row">
      <button class="btn btn-main" type="submit">Create picture</button>
      <button class="btn" type="button" id="make-all">Create all <?= count($models) ?> photos</button>
    </div>
    <p class="muted hint">"Create all" makes one picture for each kind of photo with the same description, one after the other, and skips those that already have a picture waiting below. Google charges for each picture. Keep the page open until it finishes.</p>
    <p id="make-progress" class="note note-ok make-progress" role="status" hidden></p>
  </form>
  <?php endif; ?>

  <?php $drafts = array_values(array_filter($models, static fn($x) => Gemini::draftFile(phone_slug($x)) !== null)); ?>
  <?php if ($drafts): ?>
  <h3 class="sub">Waiting for your decision</h3>
  <?php if (count($drafts) > 1): ?>
  <form method="post" action="/settings/phones/draft" class="row drafts-all">
    <?= csrf_field() ?>
    <button class="btn btn-main" type="submit" name="action" value="use_all" data-confirm="Show all <?= count($drafts) ?> waiting pictures on the project page?">Use all <?= count($drafts) ?> pictures</button>
    <span class="muted">Look at each one first. Discard any that are wrong before you press this.</span>
  </form>
  <?php endif; ?>
  <ul class="drafts">
    <?php foreach ($drafts as $model): $slug = phone_slug($model); ?>
      <li>
        <img src="/phone-draft/<?= e($slug) ?>?v=<?= filemtime(Gemini::draftFile($slug)) ?>" alt="New picture for <?= e($model) ?>">
        <p><strong><?= e($model) ?></strong><?= phone_picture($model) ? '<br><span class="muted">Replaces the current picture</span>' : '' ?></p>
        <form method="post" action="/settings/phones/draft" class="row">
          <?= csrf_field() ?>
          <input type="hidden" name="slug" value="<?= e($slug) ?>">
          <button class="btn btn-main" type="submit" name="action" value="use">Use this picture</button>
          <button class="btn btn-quiet" type="submit" name="action" value="discard">Discard</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Where photos are kept</h2>
  <p><?= Spaces::on()
      ? 'Original photos are stored as private files in the Spaces bucket <strong>' . e(cfg('spaces.bucket')) . '</strong>, folder <strong>' . e(cfg('spaces.prefix')) . '</strong>.'
      : 'Original photos are stored on this server.' ?></p>
  <p class="muted">This is changed on the server with the command <code>pw-spaces setup</code>.</p>
</section>
