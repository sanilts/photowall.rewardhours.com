<?php /** @var array $rows @var string $only @var array $counts @var int $total @var int $pages @var int $page */ ?>
<p class="crumbs"><a href="/settings#email">Settings</a> / Email sending log</p>
<div class="head"><h1>Email sending log <span class="muted">(<?= (int) $total ?>)</span></h1></div>
<p class="muted">Every email the application tried to send in the last 90 days: sign-in codes, accepted-photo emails, client invitations, download requests and tests. "Sent" means the mail server accepted it; it can still land in a spam folder. Sign-in codes are blanked out here.</p>
<nav class="pills" aria-label="Which emails">
  <a class="pill" href="/settings/mail-log"<?= $only === '' ? ' aria-current="page"' : '' ?>>All (<?= (int) $counts['total'] ?>)</a>
  <a class="pill" href="/settings/mail-log?show=sent"<?= $only === 'sent' ? ' aria-current="page"' : '' ?>>Sent (<?= (int) $counts['sent'] ?>)</a>
  <a class="pill" href="/settings/mail-log?show=failed"<?= $only === 'failed' ? ' aria-current="page"' : '' ?>>Failed (<?= (int) $counts['failed'] ?>)</a>
  <a class="pill" href="/settings/mail-log?show=file"<?= $only === 'file' ? ' aria-current="page"' : '' ?>>Not sent, email off (<?= (int) $counts['file'] ?>)</a>
</nav>
<?php if (!$rows): ?><section class="card center"><h2>Nothing here</h2><p>No email of this kind has been recorded.</p></section>
<?php else: require PW_ROOT . '/app/views/admin/mail_rows.php'; require PW_ROOT . '/app/views/shared/pager.php'; endif; ?>
