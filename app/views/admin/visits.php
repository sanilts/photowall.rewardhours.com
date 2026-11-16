<?php
/** @var array $f @var array $projects @var ?array $project @var array $totals @var array $days @var array $pages @var array $countries
 *  @var array $devices @var array $browsers @var array $sources @var int $direct @var array $steps @var bool $located */
$link = static function (array $over) use ($f): string {
    $p = array_merge(['days' => $f['days'], 'site' => $f['area'] === 'client' ? 'client' : '', 'project' => $f['project']], $over);
    return '/visits?' . http_build_query(array_filter($p, static fn($v) => $v !== '' && $v !== 0 && $v !== null));
};
$names = countries();
// a year of single days would be bars too thin to see: show one bar for each week instead
$weekly = count($days) > 120;
$bars = [];
if ($weekly) {
    foreach (array_chunk(array_reverse($days), 7) as $week) {
        $bars[] = ['day' => end($week)['day'], 'visitors' => array_sum(array_column($week, 'visitors')), 'views' => array_sum(array_column($week, 'views')), 'label' => 'Week from ' . nice_date(end($week)['day'], 'j M')];
    }
    $bars = array_reverse($bars);
} else {
    foreach ($days as $d) $bars[] = $d + ['label' => nice_date($d['day'], 'D j M')];
}
// a round top for the scale, so the grid lines sit on clean numbers
$max = max(array_merge([0], array_column($bars, 'visitors')));
$raw = max(1, $max) / 4;
$pow = 10 ** floor(log10($raw));
$step = (int) max(1, ($raw / $pow <= 1 ? 1 : ($raw / $pow <= 2 ? 2 : ($raw / $pow <= 5 ? 5 : 10))) * $pow);
$top = $step * 4;
// which days get a date under them: all of them for a week, about six otherwise
$every = count($bars) <= 14 ? (count($bars) <= 7 ? 1 : 2) : (int) ceil(count($bars) / 6);
$page_name = static function (string $path) use ($f): string {
    if ($f['area'] === 'client') {
        return ['/login' => 'Sign-in page', '/login/code' => 'Sign-in code page', '/' => 'Their projects', '/projects/*' => 'A project\'s photos', '/photo/*' => 'A photo', '/requests' => 'Download requests'][$path] ?? $path;
    }
    if ($path === '/') return 'Home page';
    if ($path === '/privacy') return 'Privacy page';
    if (preg_match('#^/p/([a-z0-9-]+)(/[a-z]+)?$#', $path, $m)) {
        return (['' => 'Project page', '/code' => 'Sign-in code page', '/details' => 'Details form', '/upload' => 'Upload page', '/email' => 'Change email page'][$m[2] ?? ''] ?? $path) . ': ' . $m[1];
    }
    return $path;
};
$n = static fn(int $v, string $one, string $many): string => number_format($v) . ' ' . ($v === 1 ? $one : $many);
$list = static function (array $rows, callable $label) use ($n): void {
    $most = max(array_merge([1], array_map('intval', array_column($rows, 'visitors')))); ?>
    <ul class="bars">
      <?php foreach ($rows as $r): ?>
        <li><span><?= $label($r) ?></span>
          <span class="bars-n"><strong><?= number_format((int) $r['visitors']) ?></strong><?= isset($r['views']) ? ' ' . ((int) $r['visitors'] === 1 ? 'visitor' : 'visitors') . ', ' . e($n((int) $r['views'], 'page view', 'page views')) : '' ?></span>
          <span class="bar"><meter min="0" max="<?= $most ?>" value="<?= (int) $r['visitors'] ?>"><?= (int) $r['visitors'] ?></meter></span></li>
      <?php endforeach; ?>
    </ul>
<?php };
?>
<div class="head">
  <h1>Visits</h1>
  <a class="btn" href="<?= e(str_replace('/visits?', '/visits.csv?', $link([]))) ?>">Download daily numbers (CSV)</a>
</div>
<p class="muted">How many people opened the <?= $f['area'] === 'client' ? 'client portal' : 'contributor site' ?>, counted on our own server without cookies. A visitor is one browser on one day. Search engines, link previews and other robots are left out, and the console is not counted.</p>

<div class="visit-filters">
  <nav class="pills" aria-label="Which site">
    <a class="pill" href="<?= e($link(['site' => ''])) ?>"<?= $f['area'] === 'respondent' ? ' aria-current="page"' : '' ?>>Contributor site</a>
    <a class="pill" href="<?= e($link(['site' => 'client', 'project' => 0])) ?>"<?= $f['area'] === 'client' ? ' aria-current="page"' : '' ?>>Client portal</a>
  </nav>
  <nav class="pills" aria-label="How far back">
    <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'] as $d => $label): ?>
      <a class="pill" href="<?= e($link(['days' => $d])) ?>"<?= $f['days'] === $d ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($f['area'] === 'respondent' && $projects): ?>
  <form method="get" action="/visits" class="row">
    <input type="hidden" name="days" value="<?= (int) $f['days'] ?>">
    <label class="visually-hidden" for="v-project">Project</label>
    <select id="v-project" name="project">
      <option value="0">All projects and pages</option>
      <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $f['project'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Show</button>
  </form>
  <?php endif; ?>
</div>

<div class="stats">
  <div class="stat st-pay"><span class="stat-n"><?= number_format($totals['visitors']) ?></span><span>Visitors</span></div>
  <div class="stat"><span class="stat-n"><?= number_format($totals['views']) ?></span><span>Page views</span></div>
  <div class="stat"><span class="stat-n"><?= $totals['visitors'] ? number_format($totals['views'] / $totals['visitors'], 1) : '0' ?></span><span>Pages per visitor</span></div>
  <div class="stat st-ok"><span class="stat-n"><?= number_format($totals['today_visitors']) ?></span><span>Visitors today</span></div>
</div>

<?php if (!$totals['views']): ?>
  <section class="card center">
    <h2>No visits counted <?= $project ? 'for this project ' : '' ?>in these days</h2>
    <p>Counting started when this version was installed. Open the <?= $f['area'] === 'client' ? 'client portal' : 'public page' ?> in a browser and it appears here straight away.</p>
  </section>
<?php else: ?>

<section class="card">
  <h2>Visitors each <?= $weekly ? 'week' : 'day' ?><?= $project ? ': ' . e($project['name']) : '' ?></h2>
  <figure class="chart">
    <div class="chart-y" aria-hidden="true"><?php for ($i = 4; $i >= 0; $i--): ?><span><?= number_format($step * $i) ?></span><?php endfor; ?></div>
    <div class="chart-plot">
      <div class="chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
      <ol class="chart-bars">
        <?php foreach ($bars as $d): $tip = $d['label'] . ': ' . $n($d['visitors'], 'visitor', 'visitors') . ', ' . $n($d['views'], 'page view', 'page views'); ?>
          <li data-h="<?= round($d['visitors'] / $top * 100, 2) ?>" data-tip="<?= e($tip) ?>"><span class="chart-bar"></span><span class="visually-hidden"><?= e($tip) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <div class="chart-x" aria-hidden="true">
      <?php foreach ($bars as $i => $d): ?><span><?php if ((count($bars) - 1 - $i) % $every === 0): ?><i><?= e(nice_date($d['day'], 'j M')) ?></i><?php endif; ?></span><?php endforeach; ?>
    </div>
  </figure>
  <details class="edit">
    <summary>Show the daily numbers as a table</summary>
    <div class="table-wrap table-flat" tabindex="0">
      <table class="table table-days">
        <thead><tr><th scope="col">Day</th><th scope="col" class="num">Visitors</th><th scope="col" class="num">Page views</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($days) as $d): ?>
          <tr><th scope="row"><?= e(nice_date($d['day'], 'D j M Y')) ?></th><td class="num"><?= number_format($d['visitors']) ?></td><td class="num"><?= number_format($d['views']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
</section>

<?php if ($steps): $first = max(1, (int) reset($steps)); ?>
<section class="card">
  <h2>From the project page to uploading</h2>
  <p class="muted">Of the visitors who opened this project's page, how many went on to each step.</p>
  <ul class="bars">
    <?php foreach ($steps as $label => $count): ?>
      <li><span><?= e($label) ?></span>
        <span class="bars-n"><strong><?= number_format($count) ?></strong> <?= $count === 1 ? 'visitor' : 'visitors' ?><?= $label !== array_key_first($steps) ? ', ' . round($count / $first * 100) . '%' : '' ?></span>
        <span class="bar"><meter min="0" max="<?= $first ?>" value="<?= min($first, $count) ?>"><?= $count ?></meter></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="cols">
  <section class="card">
    <h2>Pages opened most</h2>
    <?php $list($pages, static fn(array $r): string => e($page_name($r['label'])) . '<br><span class="muted">' . e($r['label']) . '</span>'); ?>
  </section>
  <section class="card">
    <h2>Where visitors came from</h2>
    <?php if (!$sources): ?><p class="muted">No visitor arrived from a link on another site or a tagged link in these days.</p><?php else: ?>
    <?php $list($sources, static fn(array $r): string => e($r['label']) . ((int) $r['tagged'] ? ' <span class="chip">campaign tag</span>' : '')); ?>
    <?php endif; ?>
    <p class="muted hint"><strong><?= number_format($direct) ?></strong> <?= $direct === 1 ? 'visitor' : 'visitors' ?> came directly or from somewhere that does not say, such as WhatsApp or email. To tell your own links apart, add a tag to the link you share, for example <code>?utm_source=whatsapp</code>.</p>
  </section>
</div>

<div class="cols">
  <section class="card">
    <h2>Countries</h2>
    <?php if (!$located): ?><p class="muted">Countries are counted once an IPzentra key is saved under <a href="/settings#checks">Settings, Sign-in checks</a>. The check itself does not need to be switched on.</p>
    <?php else: ?>
    <?php $list($countries, static fn(array $r): string => e($r['label'] === '' ? 'Not known yet' : ($names[$r['label']] ?? $r['label']))); ?>
    <p class="muted hint">A new visit gets its country within about ten minutes.</p>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Devices and browsers</h2>
    <?php $list($devices, static fn(array $r): string => e($r['label'])); ?>
    <?php $list($browsers, static fn(array $r): string => e($r['label'])); ?>
  </section>
</div>
<?php endif; ?>
