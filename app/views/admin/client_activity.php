<?php /** @var array $people @var array $f @var array $rows @var int $total @var int $pages @var int $page @var bool $located */
$ctab = 'activity'; require PW_ROOT . '/app/views/admin/client_tabs.php';
$names = countries();
$place = static function (string $ip, string $cc) use ($names): string { return trim(($names[$cc] ?? $cc) . ($ip !== '' ? ($cc !== '' ? ', ' : '') . $ip : '')); };
$query = http_build_query(array_filter($f));
?>
<p class="muted">What each client person did in the client portal: when and from where they signed in, and how many photos they viewed and downloaded.<?= $located ? '' : ' Countries are shown once an IPzentra key is saved in Settings; until then only the IP address is recorded.' ?></p>

<section class="card">
  <h2>By person</h2>
  <?php if (!$people): ?><p class="muted">Nobody has client access yet.</p><?php else: ?>
  <div class="table-wrap" tabindex="0">
  <table class="table">
    <thead><tr><th scope="col">Person</th><th scope="col">Sign-ins</th><th scope="col">Last sign-in</th><th scope="col">From</th><th scope="col">Gallery pages</th><th scope="col">Photos viewed</th><th scope="col">Photos downloaded</th><th scope="col">Requests</th></tr></thead>
    <tbody>
    <?php foreach ($people as $u): [$ip, $cc, $agent] = array_pad(explode('|', (string) $u['last_from'], 3), 3, ''); ?>
      <tr>
        <th scope="row"><a href="/clients/activity?user=<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a><br><span class="muted"><?= e($u['client_name']) ?>, <?= e($u['email']) ?><?= (int) $u['active'] ? '' : ', access off' ?></span></th>
        <td><?= (int) $u['logins'] ?></td>
        <td><?= e($u['last_login_at'] ? nice_date($u['last_login_at']) : 'Never') ?></td>
        <td><?= e($place($ip, $cc)) ?><?php if ($agent !== ''): ?><br><span class="muted"><?= e(agent_label($agent)) ?></span><?php endif; ?></td>
        <td><?= (int) $u['galleries'] ?></td>
        <td><?= (int) $u['viewed'] ?><?php if ((int) $u['views'] > (int) $u['viewed']): ?> <span class="muted">(<?= (int) $u['views'] ?> views)</span><?php endif; ?></td>
        <td><?= (int) $u['downloaded'] ?><?php if ((int) $u['downloads'] > (int) $u['downloaded']): ?> <span class="muted">(<?= (int) $u['downloads'] ?> downloads)</span><?php endif; ?></td>
        <td><?= (int) $u['requests'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted">"Photos viewed" and "Photos downloaded" count different photos. The number in brackets includes repeats.</p>
  <?php endif; ?>
</section>

<section class="card" id="timeline">
  <div class="head">
    <h2>Everything they did <span class="muted">(<?= (int) $total ?>)</span></h2>
    <a class="btn" href="/clients/activity.csv<?= $query !== '' ? '?' . e($query) : '' ?>">Download as CSV</a>
  </div>
  <?php if ($f['client'] > 0): $only = ''; foreach ($people as $u) { if ((int) $u['client_id'] === $f['client']) $only = $u['client_name']; } ?>
  <p><span class="chip chip-dl">Only <?= e($only !== '' ? $only : 'one client') ?></span> <a href="/clients/activity#timeline">Show every client</a></p>
  <?php endif; ?>
  <form method="get" action="/clients/activity#timeline" class="row filters">
    <?php if ($f['client'] > 0): ?><input type="hidden" name="client" value="<?= (int) $f['client'] ?>"><?php endif; ?>
    <div><label for="f-user">Person</label>
      <select id="f-user" name="user"><option value="0">Everyone</option>
        <?php foreach ($people as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $f['user'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['client_name']) ?>)</option><?php endforeach; ?>
      </select></div>
    <div><label for="f-action">What</label>
      <select id="f-action" name="action"><option value="">Everything</option>
        <?php foreach (ClientLog::ACTIONS as $k => $label): ?><option value="<?= e($k) ?>"<?= $f['action'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="f-from">From</label><input id="f-from" name="from" type="date" value="<?= e($f['from']) ?>"></div>
    <div><label for="f-to">To</label><input id="f-to" name="to" type="date" value="<?= e($f['to']) ?>"></div>
    <button class="btn btn-main" type="submit">Show</button>
    <?php if ($query !== ''): ?><a class="btn btn-quiet" href="/clients/activity#timeline">Clear</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?><p class="muted">Nothing to show for this choice.</p><?php else: ?>
  <div class="table-wrap" tabindex="0">
  <table class="table">
    <thead><tr><th scope="col">When</th><th scope="col">Person</th><th scope="col">What</th><th scope="col">Project</th><th scope="col">Photos</th><th scope="col">From</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(nice_date($r['created_at'], 'j M Y, H:i:s')) ?></td>
        <td><?= e($r['name']) ?><br><span class="muted"><?= e($r['client_name']) ?></span></td>
        <td><?= e(ClientLog::ACTIONS[$r['action']] ?? $r['action']) ?><?php if ($r['detail'] !== ''): ?><br><span class="muted"><?= e($r['detail']) ?></span><?php endif; ?></td>
        <td><?= e((string) $r['project_name']) ?></td>
        <td><?= (int) $r['photos'] ?: '' ?></td>
        <td><?= e($place($r['ip'], $r['country'])) ?><br><span class="muted"><?= e(agent_label($r['agent'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php require PW_ROOT . '/app/views/shared/pager.php'; ?>
  <?php endif; ?>
</section>
