<?php
/** @var array $p @var array $photo @var array $meta @var mixed $next */
$gone = in_array($photo['status'], ['rejected', 'removed'], true);
$flag = $photo['safety'] === 'review' && $photo['status'] === 'pending';
$detail = json_decode($photo['safety_detail'], true) ?: [];
?>
<p class="crumbs"><a href="/">Projects</a> / <a href="/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a> / <a href="/projects/<?= (int) $p['id'] ?>/photos?status=<?= e($gone ? 'rejected' : $photo['status']) ?>">Photos</a> / #<?= (int) $photo['id'] ?></p>
<div class="head">
  <h1>Photo #<?= (int) $photo['id'] ?> <span class="chip chip-<?= e($photo['status']) ?>"><?= e(ucfirst($photo['status'])) ?></span></h1>
  <?php if ($next): ?><a class="btn btn-quiet" href="/photos/<?= (int) $next ?>">Next to review</a><?php endif; ?>
</div>

<div class="cols cols-wide">
  <div class="card">
    <?php if ($gone): ?>
      <div class="shot-gone big">The file was deleted<?= $photo['reject_reason'] !== '' ? ': ' . e($photo['reject_reason']) : '' ?></div>
    <?php else: ?>
      <div class="viewer<?= $flag ? ' veil' : '' ?>">
        <img src="/img/<?= e($photo['token']) ?>/preview" alt="Photo <?= (int) $photo['id'] ?>">
        <?php if ($flag): ?><button class="btn reveal" type="button" data-reveal="1">Flagged by the safety scan. Show the photo</button><?php endif; ?>
      </div>
      <div class="row">
        <?php if ($photo['status'] === 'pending'): ?>
          <form method="post" action="/photos/<?= (int) $photo['id'] ?>/decide">
            <?= csrf_field() ?><input type="hidden" name="action" value="approve">
            <button class="btn btn-main" type="submit">Approve</button>
          </form>
        <?php else: ?>
          <form method="post" action="/photos/<?= (int) $photo['id'] ?>/decide">
            <?= csrf_field() ?><input type="hidden" name="action" value="pending">
            <button class="btn" type="submit">Move back to review</button>
          </form>
        <?php endif; ?>
        <a class="btn" href="/img/<?= e($photo['token']) ?>/original">Download original</a>
      </div>
      <form method="post" action="/photos/<?= (int) $photo['id'] ?>/decide" class="stack" data-confirm="Reject this photo? The file is deleted for good.">
        <?= csrf_field() ?><input type="hidden" name="action" value="reject">
        <label for="reason">Reason shown to the respondent</label>
        <input id="reason" name="reason" maxlength="200" placeholder="For example: the photo shows another person's face">
        <button class="btn btn-danger" type="submit">Reject and delete</button>
      </form>
    <?php endif; ?>
  </div>

  <section class="card">
    <h2>Checks</h2>
    <dl class="facts">
      <div><dt>Sharpness</dt><dd><?= e(number_format((float) $photo['sharpness'], 1)) ?> <span class="muted">(needs <?= e((float) $p['blur_min']) ?>)</span></dd></div>
      <div><dt>Brightness</dt><dd><?= e(number_format((float) $photo['brightness'], 1)) ?> <span class="muted">(<?= e((float) $p['dark_min']) ?> to <?= e((float) $p['bright_max']) ?>)</span></dd></div>
      <div><dt>Safety scan</dt><dd><?= $photo['safety'] === 'review' ? 'Flagged for careful review' : 'Nothing found' ?>, score <?= e(number_format((float) $photo['safety_score'], 3)) ?>
        <?php if (!($detail['engines'] ?? [])): ?><br><span class="muted">The scanner models were not available for this photo.</span><?php endif; ?>
        <?php foreach ($detail['parts'] ?? [] as $part): ?><br><span class="muted"><?= e(ucfirst(strtolower(str_replace('_', ' ', $part['label'])))) ?> <?= e($part['score']) ?></span><?php endforeach; ?></dd></div>
    </dl>
    <h2>Filed as</h2>
    <dl class="facts"><div><dt>Image ID</dt><dd><?= e(image_id($photo['id'])) ?></dd></div>
      <div><dt>Client</dt><dd><?= $photo['status'] !== 'approved' ? 'Not visible to the client' : 'Viewed ' . (int) $seen['views'] . ((int) $seen['views'] === 1 ? ' time' : ' times') . ', downloaded ' . (int) $seen['downloads'] . ((int) $seen['downloads'] === 1 ? ' time' : ' times') . ($seen['last_download'] ? ' (last on ' . e(nice_date($seen['last_download'])) . ')' : '') ?></dd></div></dl>
    <form method="post" action="/photos/<?= (int) $photo['id'] ?>/category" class="row">
      <?= csrf_field() ?>
      <label class="visually-hidden" for="photo-category">Category</label>
      <select id="photo-category" name="category">
        <?php $cats = project_categories($p); if (!in_array($photo['category'], $cats, true)): ?><option value="" selected>No category chosen</option><?php endif; ?>
        <?php foreach ($cats as $c): ?><option<?= $photo['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-small" type="submit">Save category</button>
    </form>
    <?php if ((int) ($photo['category_auto'] ?? 0)): ?><p class="muted">This category was detected automatically and nobody has checked it yet. Saving it here marks it as checked.</p><?php endif; ?>
    <h2>Respondent</h2>
    <dl class="facts">
      <div><dt>Contributor ID</dt><dd><a href="/projects/<?= (int) $photo['project_id'] ?>/tracker?person=<?= (int) $photo['respondent_id'] ?>"><?= e(contributor_id($photo['respondent_id'])) ?></a></dd></div>
      <div><dt>Name</dt><dd><?= e($photo['full_name']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($photo['email']) ?></dd></div>
      <div><dt>Country</dt><dd><?= e($photo['country']) ?></dd></div>
      <div><dt>Said they use</dt><dd><?= e($photo['iphone_model']) ?></dd></div>
      <div><dt>File name</dt><dd><?= e($photo['orig_name']) ?></dd></div>
    </dl>
    <p><a href="/projects/<?= (int) $p['id'] ?>/photos?person=<?= (int) $photo['respondent_id'] ?>&amp;status=<?= e($gone ? 'rejected' : $photo['status']) ?>">All photos from this person</a></p>
    <h2>Photo information</h2>
    <?php require PW_ROOT . '/app/views/shared/meta.php'; ?>
  </section>
</div>
