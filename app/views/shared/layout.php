<?php
/** @var string $title @var string $content @var array $nav */
$area = area() ?? 'respondent';
$who = $_SESSION['who'] ?? null;
$badge = ['respondent' => '', 'admin' => 'Console', 'client' => 'Client portal'][$area];
$wide = !empty($bare); // pages that manage their own full-width sections (the public landing page)
$v = static fn(string $f): string => '/assets/' . $f . '?v=' . (string) @filemtime(PW_ROOT . '/public/assets/' . $f);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?= $area === 'respondent' ? 'index,follow' : 'noindex,nofollow' ?>">
<meta name="theme-color" content="#0a1e3c">
<title><?= e($title) ?> | Photowall</title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<link rel="preload" href="/assets/fonts/bricolage-grotesque.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/instrument-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e($v('app.css')) ?>">
<link rel="icon" href="/assets/icon-48.png" type="image/png">
<link rel="apple-touch-icon" href="/assets/icon-180.png">
</head>
<body class="area-<?= e($area) ?><?= $wide ? ' is-wide' : '' ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="/">
      <img src="/assets/logo-white.png" alt="Reward Hours" width="94" height="40">
      <span class="brand-name">Photowall</span>
      <?php if ($badge): ?><span class="brand-tag"><?= e($badge) ?></span><?php endif; ?>
    </a>
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
      <button class="btn btn-onnavy" type="submit">Sign out</button>
    </form>
    <?php elseif (!empty($headerLink)): ?>
      <a class="btn btn-onnavy top-link" href="<?= e($headerLink[1]) ?>"><?= e($headerLink[0]) ?></a>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="<?= $wide ? 'main-wide' : 'wrap main' ?>">
  <?php $flashes = take_flashes(); if ($flashes): ?>
  <div class="<?= $wide ? 'wrap flashes' : 'flashes' ?>">
    <?php foreach ($flashes as [$type, $msg]): ?>
      <div class="note note-<?= e($type) ?>" role="<?= $type === 'bad' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?= $content ?>
</main>
<footer class="foot">
  <div class="wrap foot-in">
    <span>&copy; <?= date('Y') ?> <?= e(cfg('company', 'Research and Metric Corporation')) ?></span>
    <?php if ($area === 'respondent'): ?>
      <a href="/privacy">Privacy</a>
      <a href="mailto:<?= e(cfg('privacy_email')) ?>"><?= e(cfg('privacy_email')) ?></a>
    <?php endif; ?>
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
<script src="<?= e($v('app.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e($v($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
