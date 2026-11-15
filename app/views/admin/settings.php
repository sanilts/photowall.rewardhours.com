<?php
/** @var array $m @var array $me @var bool $hasPass @var bool $hasToken */
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

<section class="card">
  <h2>Where photos are kept</h2>
  <p><?= Spaces::on()
      ? 'Original photos are stored as private files in the Spaces bucket <strong>' . e(cfg('spaces.bucket')) . '</strong>, folder <strong>' . e(cfg('spaces.prefix')) . '</strong>.'
      : 'Original photos are stored on this server.' ?></p>
  <p class="muted">This is changed on the server with the command <code>pw-spaces setup</code>.</p>
</section>
