<?php
/** @var string $title @var string $content @var array $nav */
$area = area() ?? 'respondent';
$who = $_SESSION['who'] ?? null;
$badge = ['respondent' => '', 'admin' => 'Console', 'client' => 'Client'][$area];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?= $area === 'respondent' ? 'index,follow' : 'noindex,nofollow' ?>">
<title><?= e($title) ?> | Photowall</title>
<link rel="stylesheet" href="/assets/app.css?v=<?= e((string) @filemtime(PW_ROOT . '/public/assets/app.css')) ?>">
<link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
</head>
<body class="area-<?= e($area) ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span>Photowall<?php if ($badge): ?> <span class="brand-tag"><?= e($badge) ?></span><?php endif; ?></a>
    <?php if ($nav): ?>
    <nav class="nav" aria-label="Main">
      <?php foreach ($nav as [$label, $href, $on]): ?>
        <a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($who): ?>
    <form class="signout" method="post" action="/logout">
      <?= csrf_field() ?>
      <span class="who"><?= e($who['label']) ?></span>
      <button class="btn btn-quiet" type="submit">Sign out</button>
    </form>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="wrap main">
  <?php foreach (take_flashes() as [$type, $msg]): ?>
    <div class="note note-<?= e($type) ?>" role="<?= $type === 'bad' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="foot">
  <div class="wrap">
    &copy; <?= date('Y') ?> <?= e(cfg('company', 'Research and Metric Corporation')) ?>
    <?php if ($area === 'respondent'): ?> &middot; <a href="/privacy">Privacy</a><?php endif; ?>
  </div>
</footer>
<dialog id="confirm" class="dialog">
  <form method="dialog">
    <p id="confirm-text"></p>
    <div class="row-end">
      <button class="btn btn-quiet" value="no">Cancel</button>
      <button class="btn btn-danger" value="yes" id="confirm-yes">Yes</button>
    </div>
  </form>
</dialog>
<script src="/assets/app.js?v=<?= e((string) @filemtime(PW_ROOT . '/public/assets/app.js')) ?>" defer></script>
</body>
</html>
