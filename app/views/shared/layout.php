<?php
/** @var string $title @var string $content @var array $nav */
$area = area() ?? 'respondent';
$who = $_SESSION['who'] ?? null;
$badge = ['respondent' => '', 'admin' => 'Console', 'client' => 'Client portal'][$area];
$wide = !empty($bare); // pages that manage their own full-width sections (the public landing page)
$v = static fn(string $f): string => '/assets/' . $f . '?v=' . (string) @filemtime(PW_ROOT . '/public/assets/' . $f);
// Public pages (the home page, a project's page, privacy, and the client sign-in page) may be listed by search
// engines; the rest may not. Link previews with the sharing picture are for the contributor site only.
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
$share = $area === 'respondent' && preg_match('#^/(p/[^/]+|privacy)?$#', $path) === 1 && http_response_code() === 200;
$public = $share || ($area === 'client' && $path === '/login' && http_response_code() === 200);
// the small stylesheet made at deploy time is used when it is there and up to date
$css = is_file(PW_ROOT . '/public/assets/app.min.css') && filemtime(PW_ROOT . '/public/assets/app.min.css') >= filemtime(PW_ROOT . '/public/assets/app.css') ? 'app.min.css' : 'app.css';
$description = (string) ($description ?? '');
// Languages whose letters the site's two typefaces do not have are set in the device's own typeface.
$plainType = in_array(Lang::code(), ['pl', 'tr', 'vi', 'ru', 'ar', 'hi', 'ja', 'ko', 'zh-CN', 'zh-TW', 'th'], true);
// The language picker keeps the reader on the same page.
$langQuery = $_GET;
unset($langQuery['lang']);
$langHref = static fn(string $code): string => $path . '?' . http_build_query($langQuery + ['lang' => $code]);
?><!doctype html>
<html lang="<?= e(Lang::code()) ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="<?= $public ? 'index,follow' : 'noindex,nofollow' ?>">
<?php if ($area === 'respondent'): // on a phone the contributor site has a light bar along the top, like an app ?>
<meta name="theme-color" media="(max-width: 899px)" content="#f9f9f9">
<meta name="theme-color" media="(min-width: 900px)" content="#0a1e3c">
<meta name="apple-mobile-web-app-title" content="Photowall">
<?php else: ?>
<meta name="theme-color" content="<?= $area === 'client' ? '#ffffff' : '#0a1e3c' ?>">
<?php endif; ?>
<title><?= e($title) ?> | Photowall</title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($public): $canon = area_url($area, $path); ?>
<link rel="canonical" href="<?= e($canon) ?>">
<?php endif; ?>
<?php if ($share): ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Photowall">
<meta property="og:title" content="<?= e($title) ?> | Photowall">
<?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e(area_url('respondent', '/share.jpg?v=' . Share::version())) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Photowall by Reward Hours: share your iPhone photos">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="preload" href="/assets/fonts/bricolage-grotesque.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/instrument-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e($v($css)) ?>">
<link rel="icon" href="<?= e($v(($area === 'client' ? 'rm-' : '') . 'icon-48.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e($v(($area === 'client' ? 'rm-' : '') . 'icon-180.png')) ?>">
</head>
<body class="area-<?= e($area) ?><?= $wide ? ' is-wide' : '' ?><?= $plainType ? ' plain-type' : '' ?>">
<a class="skip" href="#main"><?= te('Skip to content') ?></a>
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="/">
      <?php if ($area === 'client'): ?><img src="<?= e($v('rm-logo.png')) ?>" alt="Research and Metric" width="215" height="40">
      <?php else: ?><img class="brand-logo" src="<?= e($v('logo-white.png')) ?>" alt="Reward Hours" width="94" height="40"><?php endif; ?>
      <?php if ($area === 'respondent'): // the app icon stands in for the logo on a phone ?><img class="brand-app" src="<?= e($v('icon-180.png')) ?>" alt="" width="28" height="28" loading="lazy"><?php endif; ?>
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
    <?php if ($area === 'respondent'): ?>
    <details class="lang" id="lang">
      <summary><?= icon('globe') ?><span class="visually-hidden"><?= te('Language') ?>: </span><span class="lang-name"><?= e(Lang::ALL[Lang::code()][0]) ?></span> <span class="lang-code"><?= e(strtoupper(substr(Lang::code(), 0, 2))) ?></span></summary>
      <ul class="lang-list">
        <?php foreach (Lang::ALL as $code => [$own, $flag]): ?>
          <li><a href="<?= e($langHref($code)) ?>" lang="<?= e($code) ?>" hreflang="<?= e($code) ?>" rel="nofollow"<?= $code === Lang::code() ? ' aria-current="true"' : '' ?>><span class="lang-flag" aria-hidden="true"><?= $flag ?></span><span dir="auto"><?= e($own) ?></span><?php if ($code === 'en'): ?><small>Global</small><?php endif; ?></a></li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
    <?php if ($who): ?>
    <form class="signout" method="post" action="/logout">
      <?= csrf_field() ?>
      <span class="who" title="<?= e($who['label']) ?>"><?= e($who['label']) ?></span>
      <button class="btn btn-onnavy" type="submit"><?= te('Sign out') ?></button>
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
      <a href="/privacy"><?= te('Privacy') ?></a>
      <a href="mailto:<?= e(cfg('privacy_email')) ?>"><?= e(cfg('privacy_email')) ?></a>
    <?php elseif ($area === 'client'): ?>
      <a href="https://researchandmetric.com" target="_blank" rel="noopener">researchandmetric.com</a>
    <?php endif; ?>
  </div>
</footer>
<dialog id="confirm" class="dialog">
  <form method="dialog">
    <p id="confirm-text"></p>
    <div class="row-end">
      <button class="btn btn-quiet" value="no"><?= te('Cancel') ?></button>
      <button class="btn btn-danger" value="yes" id="confirm-yes"><?= te('Yes') ?></button>
    </div>
  </form>
</dialog>
<script src="<?= e($v('app.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e($v($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
