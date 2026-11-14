<?php $mail = (string) cfg('privacy_email'); $co = (string) cfg('company'); ?>
<article class="card prose">
  <h1>Privacy</h1>
  <p>Photowall is run by <?= e($co) ?>. This page explains what happens to the photos and details you give us. Each project also shows its own terms before you take part.</p>

  <h2>What we collect</h2>
  <ul>
    <li>Your email address, used to send your sign-in code and to let you come back later.</li>
    <li>The basic details you enter: name, country, iPhone model and, if you choose, a phone number.</li>
    <li>The photos you upload, kept exactly as your iPhone made them.</li>
    <li>The information stored inside each photo file by your iPhone, such as the phone model, camera settings, the date and time, and the place the photo was taken if location was switched on for your camera.</li>
    <li>Technical records such as your IP address and the time of your uploads, to keep the service secure.</li>
  </ul>

  <h2>How photos are checked</h2>
  <p>Every photo is checked automatically on our own server to confirm it comes from an accepted iPhone, is an original and clear picture, and does not contain unsafe content. Our staff then look at each photo before it is passed on. Photos that fail the automatic checks are deleted straight away.</p>

  <h2>Who sees your photos</h2>
  <p>Approved photos and the information inside the photo files are shared with the research client named or described in the project terms. The client sees a participant number and your country. Your name, email address and phone number are not shared with the client.</p>

  <h2>Your choices</h2>
  <ul>
    <li>You can delete a photo yourself while it is still in review.</li>
    <li>You can ask us for a copy of your data, to correct it, or to delete it, and you can withdraw from a project at any time.</li>
    <li>If you do not want a photo to carry its location, switch off location for the Camera app before you take it.</li>
  </ul>
  <p>For any of these requests, or any question, email <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a>.</p>
</article>
