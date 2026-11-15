<?php
declare(strict_types=1);

// Checks that everything the app needs on this server is in place (installed as: pw-check).
// With a file name, it also shows exactly what the checks say about that photo:
//   pw-check /path/to/IMG_0001.HEIC
require dirname(__DIR__) . '/app/bootstrap.php';

$ok = true;
$say = static function (bool $good, string $what, string $hint = '') use (&$ok): void {
    echo ($good ? '  OK    ' : '  FAIL  ') . $what . ($good || $hint === '' ? '' : "\n          " . $hint) . "\n";
    if (!$good) $ok = false;
};

echo "Photowall check\n";
try {
    $tables = (int) Db::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('projects','photos','respondents')");
    $say($tables === 3, 'Database connection and tables', 'Run: php ' . PW_ROOT . '/bin/migrate.php');
} catch (Throwable $ex) {
    $say(false, 'Database connection', $ex->getMessage());
}
foreach (['originals', 'previews', 'thumbs', 'tmp', 'logs'] as $d) {
    try {
        $dir = Storage::dir($d);
        $say(is_writable($dir), "Storage folder $dir is writable");
    } catch (Throwable $ex) {
        $say(false, "Storage folder $d", $ex->getMessage());
    }
}
$ver = trim((string) @shell_exec(escapeshellarg((string) cfg('exiftool')) . ' -ver 2>/dev/null'));
$say($ver !== '', 'exiftool' . ($ver !== '' ? " $ver" : ''), 'Install it: apt-get install libimage-exiftool-perl');
$say(is_file((string) cfg('python')), 'Python for the scanner at ' . cfg('python'), 'Run pw-deploy again to build it.');
$say(is_file(cfg('models') . '/resnet_50_1by2_nsfw.caffemodel'), 'Safety model 1 (OpenNSFW) is downloaded', 'Run pw-deploy again to fetch it. The scan still works with model 2 alone, but is weaker.');

// A real scan of a generated picture proves the whole scanner works.
$probe = Storage::dir('tmp') . '/selftest-' . bin2hex(random_bytes(4)) . '.jpg';
$img = imagecreatetruecolor(1200, 900);
for ($i = 0; $i < 400; $i++) {
    imagefilledrectangle($img, random_int(0, 1150), random_int(0, 850), random_int(50, 1200), random_int(50, 900), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
}
imagejpeg($img, $probe, 90);
$scan = Scanner::run($probe);
@unlink($probe);
$say(!empty($scan['ok']), 'Scanner opens and measures a picture' . (!empty($scan['ok']) ? " ({$scan['ms']} ms)" : ''), (string) ($scan['detail'] ?? ''));
$engines = $scan['safety']['engines'] ?? [];
$say(count($engines) === 2, 'Safety models running: ' . ($engines ? implode(', ', $engines) : 'none'), 'Photos would still be held for staff review, but nothing would be blocked automatically.');
if (Spaces::on()) {
    $problem = Spaces::selfTest();
    $say($problem === null, "Original photos go to the bucket '" . cfg('spaces.bucket') . "' (private files)", (string) $problem);
} else {
    echo "  NOTE  Original photos are stored on this server (" . cfg('storage') . "/originals). To use a Spaces bucket: pw-spaces setup\n";
}
$say(Mailer::isSetUp(), 'Email sending is set up (' . (Settings::mail()['driver'] ?? 'log') . ')', 'Sign-in codes are only written to ' . cfg('storage') . '/logs/mail.log. Add your email details in the console under Settings.');
$limit = (int) ini_get('upload_max_filesize');
echo "  NOTE  PHP on the command line allows uploads of " . ini_get('upload_max_filesize') . "; the web server setting is what counts (needs " . cfg('max_upload_mb', 60) . "M or more).\n";

if (isset($argv[1])) {
    $file = $argv[1];
    echo "\nChecks for $file\n";
    if (!is_file($file)) exit("  That file does not exist.\n");
    $meta = PhotoCheck::readMeta($file);
    if ($meta === null) exit("  exiftool could not read this file.\n");
    $facts = PhotoCheck::facts($meta);
    foreach (['file_type', 'make', 'model', 'lens', 'software', 'taken_at', 'exif_w', 'exif_h'] as $k) echo '  ' . str_pad($k, 10) . ' ' . $facts[$k] . "\n";
    echo '  ' . str_pad('apple notes', 10) . ' ' . (PhotoCheck::hasGroup($meta, 'Apple') ? 'present' : 'missing') . "\n";
    $project = ['min_iphone' => 16, 'extra_models' => "iPhone Air\niPhone Duo", 'strict_original' => 1, 'min_megapixels' => 8];
    $bad = PhotoCheck::evaluate($meta, $project);
    $s = Scanner::run($file);
    if (!$bad && !empty($s['ok'])) $bad = PhotoCheck::evaluateSize($facts, (int) $s['width'], (int) $s['height'], $project);
    echo "  scanner:   " . json_encode($s) . "\n";
    echo "  verdict with default rules: " . ($bad ? "TURNED AWAY ({$bad[0]}) {$bad[1]}" : 'device and original checks passed') . "\n";
}
exit($ok ? 0 : 1);
