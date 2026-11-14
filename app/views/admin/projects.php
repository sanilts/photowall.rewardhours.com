<?php /** @var array $projects @var array $warnings */ $isAdmin = (ad_me()['role'] ?? '') === 'admin'; ?>
<div class="head">
  <h1>Projects</h1>
  <?php if ($isAdmin): ?><a class="btn btn-main" href="/projects/new">New project</a><?php endif; ?>
</div>
<?php foreach ($warnings as $w): ?><div class="note note-warn" role="status"><?= e($w) ?></div><?php endforeach; ?>

<?php if (!$projects): ?>
  <section class="card center">
    <h2>No projects yet</h2>
    <p>Create a project to get the link you can share with respondents.</p>
  </section>
<?php else: ?>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th scope="col">Project</th><th scope="col">Status</th><th scope="col">Closes</th><th scope="col" class="num">People</th>
        <th scope="col" class="num">To review</th><th scope="col" class="num">Approved</th><th scope="col" class="num">Rejected</th><th scope="col" class="num">Turned away</th></tr>
  </thead>
  <tbody>
  <?php foreach ($projects as $p): ?>
    <tr>
      <th scope="row"><a href="/projects/<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a><br><span class="muted"><?= e($p['client_name'] ?? 'No client linked') ?></span></th>
      <td><span class="chip chip-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
      <td><?= e($p['closes_at'] ? nice_date($p['closes_at']) : 'No closing date') ?></td>
      <td class="num"><?= (int) $p['n_people'] ?></td>
      <td class="num"><?php if ($p['n_pending']): ?><a href="/projects/<?= (int) $p['id'] ?>/photos"><strong><?= (int) $p['n_pending'] ?></strong></a><?php else: ?>0<?php endif; ?></td>
      <td class="num"><?= (int) $p['n_approved'] ?></td>
      <td class="num"><?= (int) $p['n_rejected'] ?></td>
      <td class="num"><?= (int) $p['n_discarded'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
