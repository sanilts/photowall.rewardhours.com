<?php /** @var array $rows @var int $days */ ?>
<div class="head"><h1>Download requests</h1></div>
<p class="muted">Where a project needs our approval before photos are downloaded, your requests are listed here. An approved download can be used for <?= (int) $days ?> days.</p>
<?php if (!$rows): ?>
  <section class="card center">
    <h2>You have not asked for any download yet</h2>
    <p>Open a project, choose the photos and ask to download them.</p>
    <p><a class="btn btn-main" href="/">Your projects</a></p>
  </section>
<?php else: ?>
<ul class="reqs">
<?php foreach ($rows as $r):
    $ran_out = $r['status'] === 'approved' && $r['expires_at'] <= now();
    $label = ['pending' => 'Waiting for our answer', 'approved' => $ran_out ? 'Ran out' : 'Approved', 'declined' => 'Declined'][$r['status']];
    $chip = ['pending' => 'pending', 'approved' => $ran_out ? 'closed' : 'approved', 'declined' => 'rejected'][$r['status']]; ?>
  <li class="card req">
    <div class="req-head">
      <h2><?= (int) $r['photos'] ?> <?= (int) $r['photos'] === 1 ? 'photo' : 'photos' ?> <span class="muted">from <?= e($r['project_name']) ?>, <?= e(human_bytes((int) $r['bytes'])) ?></span></h2>
      <span class="chip chip-<?= e($chip) ?>"><?= e($label) ?></span>
    </div>
    <?php if ($r['thumbs']): ?>
    <ul class="req-thumbs">
      <?php foreach ($r['thumbs'] as $t): ?><li><a href="/photo/<?= e($t['token']) ?>"><img src="/img/<?= e($t['token']) ?>/thumb" alt="" loading="lazy" width="64" height="64"></a></li><?php endforeach; ?>
      <?php if ((int) $r['photos'] > count($r['thumbs'])): ?><li class="req-more">and <?= (int) $r['photos'] - count($r['thumbs']) ?> more</li><?php endif; ?>
    </ul>
    <?php endif; ?>
    <p class="muted">Asked on <?= e(nice_date($r['created_at'])) ?><?= $r['note'] !== '' ? '. Your note: ' . e($r['note']) : '' ?></p>
    <?php if ($r['status'] === 'declined'): ?>
      <p>Declined on <?= e(nice_date($r['decided_at'])) ?><?= $r['reply'] !== '' ? '. ' . e($r['reply']) : '.' ?></p>
    <?php elseif ($r['status'] === 'approved' && $ran_out): ?>
      <p>This download ran out on <?= e(nice_date($r['expires_at'])) ?>. Ask again if you still need the photos.</p>
    <?php elseif ($r['status'] === 'approved'): ?>
      <?php if ($r['reply'] !== ''): ?><p><?= e($r['reply']) ?></p><?php endif; ?>
      <form method="post" action="/requests/<?= (int) $r['id'] ?>/download" class="row">
        <?= csrf_field() ?>
        <button class="btn btn-main" type="submit"><?= (int) $r['photos'] === 1 ? 'Download the original' : 'Download ' . (int) $r['photos'] . ' photos (ZIP)' ?></button>
        <span class="muted">Available until <?= e(nice_date($r['expires_at'])) ?><?= (int) $r['downloads'] ? '. Downloaded ' . (int) $r['downloads'] . ((int) $r['downloads'] === 1 ? ' time' : ' times') : '' ?></span>
      </form>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
