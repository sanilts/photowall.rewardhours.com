<?php
declare(strict_types=1);

// Photowall: loads settings and the small library. No framework, no Composer.
define('PW_ROOT', dirname(__DIR__));

$pwConfig = getenv('PHOTOWALL_CONFIG') ?: (is_file('/etc/photowall/config.php') ? '/etc/photowall/config.php' : PW_ROOT . '/config.php');
if (!is_file($pwConfig)) {
    http_response_code(500);
    exit('Photowall is not set up yet (settings file missing).');
}
$GLOBALS['PW_CFG'] = require $pwConfig;

foreach (['helpers', 'icons', 'Db', 'Settings', 'Mailer', 'Otp', 'PhotoCheck', 'Scanner', 'Spaces', 'Storage', 'Gemini', 'Intake', 'Tracker', 'Notify', 'ZipStream', 'Export'] as $pwLib) {
    require PW_ROOT . '/app/lib/' . $pwLib . '.php';
}
date_default_timezone_set((string) cfg('timezone', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');
