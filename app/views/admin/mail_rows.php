<?php /** @var array $rows  Lines of the email sending log, as a table. */ ?>
<div class="table-wrap table-flat">
<table class="table table-mail">
  <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Email</th><th scope="col">Result</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="nowrap"><?= e(nice_date($r['created_at'], 'j M Y, H:i:s')) ?></td>
      <td><?= e($r['to_email']) ?></td>
      <td><strong><?= e(Mailer::KINDS[$r['kind']] ?? 'Other') ?></strong><br><span class="muted"><?= e($r['subject']) ?></span></td>
      <td><?php if ($r['result'] === 'sent'): ?><span class="chip chip-approved">Sent</span> <span class="muted">by <?= $r['driver'] === 'postmark' ? 'Postmark' : 'SMTP' ?></span>
        <?php elseif ($r['result'] === 'failed'): ?><span class="chip chip-rejected">Failed</span><br><span class="mail-why"><?= e($r['error'] !== '' ? $r['error'] : 'The mail server gave no reason.') ?></span>
        <?php else: ?><span class="chip chip-pending">Not sent</span><br><span class="muted">Email is switched off. Written to the server file only.</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
