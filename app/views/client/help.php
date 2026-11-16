<?php
// The Help button in the bottom right corner of every client portal page. Opens without a script.
$helpMail = (string) cfg('contact_email', 'contact@researchandmetric.com');
?>
<details class="help" id="help" data-pop>
  <summary><?= icon('help') ?><span>Help</span></summary>
  <div class="help-panel" role="region" aria-label="Help">
    <h2 class="help-title">How the client portal works</h2>
    <div class="help-list">
      <details open>
        <summary>Signing in<?= icon('chevron') ?></summary>
        <ol>
          <li>Enter the email address we registered for you.</li>
          <li>We email you a 6-digit code. It works for <?= (int) Otp::MINUTES ?> minutes and only once.</li>
          <li>Type the code in. There is no password to remember.</li>
        </ol>
        <p>No email after a minute or two? Check your spam folder, then use "Send the code again". If your address is not accepted, ask your contact at Research and Metric to add you.</p>
        <p>When you finish, use "Sign out" at the top right, above all on a shared computer.</p>
      </details>
      <details>
        <summary>Your projects<?= icon('chevron') ?></summary>
        <p>After signing in you see the projects set up for your company, with the number of approved photos in each. Choose a project to open its photos.</p>
      </details>
      <details>
        <summary>Looking at photos<?= icon('chevron') ?></summary>
        <p>You see only photos our team has checked and approved. New ones appear as they are approved.</p>
        <p>The lists at the top of a project show one phone model, or only the photos you have not downloaded yet.</p>
        <p>Open a photo for a larger view and its camera information: phone model, lens, date taken and more. The view on screen is a smaller copy made for your browser.</p>
      </details>
      <details>
        <summary>Downloading photos<?= icon('chevron') ?></summary>
        <p>A download is always the original file, exactly as it left the phone, with its camera information inside.</p>
        <ul>
          <li><strong>One photo:</strong> open it and choose "Download original".</li>
          <li><strong>Many photos:</strong> tick them, or choose all, and download them as one ZIP file. The ZIP includes a sheet that lists every photo.</li>
          <li><strong>The sheet alone:</strong> choose "Photo information (CSV)".</li>
        </ul>
        <p>Downloading many photos at once can be switched off for a project. Then photos are downloaded one at a time.</p>
      </details>
      <details>
        <summary>When a download needs our approval<?= icon('chevron') ?></summary>
        <p>Some projects need our approval before photos are downloaded. There the button says "Ask to download". You can add a note for our team.</p>
        <p>We email you when it is decided. An approved download waits under "Download requests" at the top of the page and can be used for <?= (int) ClientLog::VALID_DAYS ?> days.</p>
      </details>
      <details>
        <summary>Photos you have already downloaded<?= icon('chevron') ?></summary>
        <p>A photo you have downloaded is marked "Downloaded".</p>
        <ul>
          <li>To see only new photos, choose "Not downloaded by me" at the top of a project.</li>
          <li>To keep them out of a ZIP or a request, tick "Leave out photos I have already downloaded" before you press the button.</li>
        </ul>
        <p>This is kept for you alone. Photos a colleague downloaded are not marked for you.</p>
      </details>
      <details>
        <summary>What you see about contributors<?= icon('chevron') ?></summary>
        <p>Each photo shows a contributor ID and a country. Names, email addresses and phone numbers are never shown or included in a download.</p>
      </details>
      <details>
        <summary>Records we keep<?= icon('chevron') ?></summary>
        <p>For security we record sign-ins, the photos you open and what you download.</p>
      </details>
    </div>
    <p class="help-more">Still stuck? Email <a href="mailto:<?= e($helpMail) ?>"><?= e($helpMail) ?></a>.</p>
  </div>
</details>
