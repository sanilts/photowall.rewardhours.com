<?php /** @var array $rows @var int $days */ $ctab = 'requests'; require PW_ROOT . '/app/views/admin/client_tabs.php'; ?>
<p class="muted">Where a project is set to "Clients must ask before they download", their requests arrive here. An approved download can be used for <?= (int) $days ?> days. The switch is in each project's Settings.</p>
<?php if (!$rows): ?>
  <section class="card center"><h2>No download requests yet</h2><p>They appear here as soon as a client asks.</p></section>
<?php endif; ?>
<?php foreach ($rows as $r):
    $ran_out = $r['status'] === 'approved' && $r['expires_at'] <= now();
    $label = ['pending' => 'Waiting for you', 'approved' => $ran_out ? 'Approved, ran out' : 'Approved', 'declined' => 'Declined'][$r['status']];
    $chip = ['pending' => 'pending', 'approved' => $ran_out ? 'closed' : 'approved', 'declined' => 'rejected'][$r['status']]; ?>
<section class="card req" id="request-<?= (int) $r['id'] ?>">
  <div class="req-head">
    <h2><?= e($r['client_name']) ?> <span class="muted">asks for <?= (int) $r['photos'] ?> <?= (int) $r['photos'] === 1 ? 'photo' : 'photos' ?> (<?= e(human_bytes((int) $r['bytes'])) ?>)</span></h2>
    <span class="chip chip-<?= e($chip) ?>"><?= e($label) ?></span>
  </div>
  <p><?= e($r['user_name']) ?>, <?= e($r['user_email']) ?>. Project: <a href="/projects/<?= (int) $r['project_id'] ?>"><?= e($r['project_name']) ?></a>. Asked on <?= e(nice_date($r['created_at'])) ?>.</p>
  <?php if ($r['note'] !== ''): ?><p><strong>Their note:</strong> <?= e($r['note']) ?></p><?php endif; ?>
  <ul class="req-thumbs">
    <?php foreach ($r['thumbs'] as $t): ?><li><a href="/photos/<?= (int) $t['id'] ?>"><?php if ($t['status'] !== 'rejected'): ?><img src="/img/<?= e($t['token']) ?>/thumb" alt="Photo <?= e(image_id($t['id'])) ?>" loading="lazy" width="64" height="64"><?php else: ?><?= e(image_id($t['id'])) ?><?php endif; ?></a></li><?php endforeach; ?>
    <?php if ((int) $r['photos'] > count($r['thumbs'])): ?><li class="req-more">and <?= (int) $r['photos'] - count($r['thumbs']) ?> more</li><?php endif; ?>
  </ul>
  <?php if ($r['status'] === 'pending'): ?>
    <form method="post" action="/clients/requests/<?= (int) $r['id'] ?>/decide" class="form">
      <?= csrf_field() ?>
      <label for="reply-<?= (int) $r['id'] ?>">Message to the client <span class="muted">(optional; sent with your answer)</span></label>
      <input id="reply-<?= (int) $r['id'] ?>" name="reply" maxlength="500">
      <div class="row">
        <button class="btn btn-main" type="submit" name="do" value="approve">Approve the download</button>
        <button class="btn btn-danger" type="submit" name="do" value="decline">Decline</button>
      </div>
    </form>
  <?php else: ?>
    <p class="muted"><?= $r['status'] === 'approved' ? 'Approved' : 'Declined' ?> by <?= e($r['staff_name'] ?? 'staff') ?> on <?= e(nice_date($r['decided_at'])) ?><?= $r['reply'] !== '' ? '. Message: ' . e($r['reply']) : '' ?>.
      <?php if ($r['status'] === 'approved'): ?><?= $ran_out ? 'Ran out on ' : 'Available until ' ?><?= e(nice_date($r['expires_at'])) ?>. Downloaded <?= (int) $r['downloads'] ?> <?= (int) $r['downloads'] === 1 ? 'time' : 'times' ?><?= $r['last_download_at'] ? ', last on ' . e(nice_date($r['last_download_at'])) : '' ?>.<?php endif; ?></p>
    <?php if ($r['status'] === 'approved' && !$ran_out): ?>
    <form method="post" action="/clients/requests/<?= (int) $r['id'] ?>/decide">
      <?= csrf_field() ?>
      <button class="btn btn-small btn-quiet" type="submit" name="do" value="decline" data-confirm="Withdraw this approval? The client can no longer download these photos.">Withdraw the approval</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endforeach; ?>
