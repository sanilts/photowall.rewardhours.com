<?php
// For trying the app on a developer machine only (never used on the server):
//   PHOTOWALL_CONFIG=/path/config.php php -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8080 -t public bin/dev-router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(dirname(__DIR__) . '/public' . $path)) return false;
require dirname(__DIR__) . '/public/index.php';
