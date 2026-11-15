<?php
/** @var array $p @var array $rows @var string $q @var string $sort @var string $show @var int $total @var int $pages @var int $page @var array $sum */
$pid = (int) $p['id'];
$rw = reward($p);
$isAdmin = (ad_me()['role'] ?? '') === 'admin';
$sorts = ['new' => 'Newest first', 'accepted' => 'Most accepted', 'review' => 'Most waiting for review', 'name' => 'Name A to Z'] + ($rw ? ['reward' => 'Highest reward'] : []);
$shows = ['' => 'Everyone', 'review' => 'With photos to review', 'accepted' => 'With accepted photos', 'blocked' => 'Blocked'];
$tab = 'people';
require PW_ROOT . '/app/views/admin/project_tabs.php';
?>
<div class="head">
  <h1>Contributors <span class="muted">(<?= (int) $total ?><?= $q !== '' || $show !== '' ? ' shown' : '' ?>)</span></h1>
  <?php if ($isAdmin): ?><a class="btn" href="/projects/<?= $pid ?>/respondents.csv"><?= icon('doc') ?> Download as CSV</a><?php endif; ?>
</div>

<div class="stats stats-small">
  <div class="stat"><span class="stat-n"><?= (int) $sum['people'] ?></span><span>Contributors</span></div>
  <div class="stat st-ok"><span class="stat-n"><?= (int) $sum['with_accepted'] ?></span><span>Have accepted photos</span></div>
  <div class="stat st-wait"><span class="stat-n"><?= (int) $sum['with_review'] ?></span><span>Have photos to review</span></div>
  <?php if ($rw): ?><div class="stat st-pay"><span class="stat-n"><?= e($sum['reward']) ?></span><span>Reward earned in total</span></div><?php endif; ?>
</div>

<form method="get" class="row filters" role="search">
  <label class="visually-hidden" for="q">Search by name, email or contributor ID</label>
  <input id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email or RM-C number">
  <label class="visually-hidden" for="show">Show</label>
  <select id="show" name="show">
    <?php foreach ($shows as $k => $label): ?><option value="<?= e($k) ?>"<?= $show === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <label class="visually-hidden" for="sort">Order</label>
  <select id="sort" name="sort">
    <?php foreach ($sorts as $k => $label): ?><option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-main" type="submit">Show</button>
  <?php if ($q !== '' || $show !== '' || $sort !== 'new'): ?><a class="btn btn-quiet" href="/projects/<?= $pid ?>/respondents">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <section class="card center"><p><?= $q !== '' || $show !== '' ? 'Nobody matches. Clear the search to see everyone.' : 'Nobody has signed in to this project yet.' ?></p></section>
<?php else: ?>
<div class="table-wrap">
<table class="table table-people">
  <thead><tr><th scope="col">Contributor</th><th scope="col">Country and phone</th><th scope="col">Joined</th><th scope="col">Photos</th><?php if ($rw): ?><th scope="col" class="num">Reward earned</th><?php endif; ?><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $rid = (int) $r['id']; $bad = (int) $r['n_rejected'] + (int) $r['n_discarded']; ?>
    <tr<?= (int) $r['blocked'] ? ' class="row-off"' : '' ?>>
      <th scope="row">
        <span class="cname"><?= e($r['full_name'] !== '' ? $r['full_name'] : 'Details not filled in') ?></span>
        <span class="cname-id"><?= e(contributor_id($rid)) ?><?= (int) $r['blocked'] ? ' <span class="chip chip-rejected">Blocked</span>' : '' ?></span>
        <a class="cname-mail" href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
      </th>
      <td><?= e($r['country'] !== '' ? $r['country'] : 'Not given') ?><br><span class="muted"><?= e($r['iphone_model'] !== '' ? $r['iphone_model'] : 'Phone not given') ?></span></td>
      <td class="nowrap"><?= e(nice_date($r['created_at'], 'j M Y')) ?></td>
      <td>
        <span class="tally">
          <a class="chip chip-pending" href="/projects/<?= $pid ?>/photos?person=<?= $rid ?>" title="Waiting for review"><?= (int) $r['n_pending'] ?> to review</a>
          <a class="chip chip-approved" href="/projects/<?= $pid ?>/photos?person=<?= $rid ?>&amp;status=approved" title="Accepted"><?= (int) $r['n_approved'] ?> accepted</a>
          <a class="chip chip-rejected" href="/projects/<?= $pid ?>/tracker?person=<?= $rid ?>&amp;status=rejected" title="Rejected by staff or turned away by a check"><?= $bad ?> rejected</a>
        </span>
      </td>
      <?php if ($rw): ?><td class="num"><strong><?= e(reward_earned($rw, (int) $r['n_approved'])) ?></strong></td><?php endif; ?>
      <td>
        <div class="row acts">
          <a class="btn btn-small" href="/projects/<?= $pid ?>/tracker?person=<?= $rid ?>">All photos</a>
          <form method="post" action="/respondents/<?= $rid ?>/block"<?= (int) $r['blocked'] ? '' : ' data-confirm="Stop this person from uploading to this project?"' ?>>
            <?= csrf_field() ?>
            <button class="btn btn-small<?= (int) $r['blocked'] ? '' : ' btn-quiet' ?>" type="submit"><?= (int) $r['blocked'] ? 'Allow again' : 'Block' ?></button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
<?php endif; ?>
