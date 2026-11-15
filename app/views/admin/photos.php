<?php
/** @var array $p @var array $photos @var string $status @var int $total @var int $pages @var int $page */
$base = '/projects/' . (int) $p['id'] . '/photos';
$tabs = ['pending' => 'To review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$flagOnly = ($_GET['flag'] ?? '') === '1';
?>
<?php $tab = 'review'; require PW_ROOT . '/app/views/admin/project_tabs.php'; ?>
<div class="head">
  <h1>Photos <span class="muted">(<?= (int) $total ?>)</span></h1>
  <div class="tabs" role="tablist">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="<?= e($base . '?status=' . $k) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($status === 'pending'): ?>
      <a href="<?= e($base . ($flagOnly ? '' : '?flag=1')) ?>"<?= $flagOnly ? ' aria-current="page"' : '' ?>>Flagged only</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$photos): ?>
  <section class="card center"><p>Nothing here.</p></section>
<?php else: ?>
<form method="post" action="<?= e($base) ?>/bulk">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e(http_build_query(array_intersect_key($_GET, ['status' => 1, 'flag' => 1, 'person' => 1]))) ?>">
  <?php if ($status !== 'rejected'): ?>
  <div class="bulk">
    <label class="check"><input type="checkbox" data-check-all="photo"><span>Select all on this page</span></label>
    <span><strong data-count>0</strong> selected</span>
    <?php if ($status === 'pending'): ?><button class="btn btn-main" type="submit" name="action" value="approve">Approve selected</button><?php endif; ?>
    <label class="visually-hidden" for="reason">Reason shown to the respondent</label>
    <input id="reason" name="reason" maxlength="200" placeholder="Reason shown to the respondent">
    <button class="btn btn-danger" type="submit" name="action" value="reject" data-confirm="Reject the selected photos? The files are deleted for good.">Reject selected</button>
  </div>
  <p class="muted">Rejecting deletes the photo file for good. The respondent sees the reason and can upload a replacement while the project is open.</p>
  <?php endif; ?>

  <ul class="grid grid-review">
    <?php foreach ($photos as $ph): $flag = $ph['safety'] === 'review'; ?>
      <li class="shot<?= $flag && $status === 'pending' ? ' veil' : '' ?>">
        <?php if ($status === 'rejected'): ?>
          <div class="shot-gone">File deleted</div>
        <?php else: ?>
          <a href="/photos/<?= (int) $ph['id'] ?>"><img src="/img/<?= e($ph['token']) ?>/thumb" alt="Photo <?= (int) $ph['id'] ?>" loading="lazy" width="240" height="240"></a>
          <?php if ($flag && $status === 'pending'): ?><button class="btn btn-small reveal" type="button" data-reveal="1">Flagged by safety scan. Show</button><?php endif; ?>
        <?php endif; ?>
        <div class="shot-info">
          <?php if ($status !== 'rejected'): ?>
            <label class="check"><input type="checkbox" name="ids[]" value="<?= (int) $ph['id'] ?>" data-group="photo"><span>#<?= (int) $ph['id'] ?></span></label>
          <?php else: ?><span>#<?= (int) $ph['id'] ?></span><?php endif; ?>
          <span><?= e($ph['device_model']) ?></span>
          <span class="muted"><?= e($ph['email']) ?></span>
          <?php if ($status === 'rejected'): ?><span class="shot-why"><?= e($ph['reject_reason']) ?></span>
          <?php else: ?><span class="muted">Sharpness <?= (int) round((float) $ph['sharpness']) ?> &middot; Safety <?= e(number_format((float) $ph['safety_score'], 2)) ?></span><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</form>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
