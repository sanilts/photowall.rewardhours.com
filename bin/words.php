<?php
declare(strict_types=1);

// Lists every sentence the contributor site can show, in the order it is found in the code.
// Used when adding a language: php bin/words.php > words.json. With "check" it reports sentences
// that a language file in app/lang does not have, and translations whose {places} or tags differ.
define('PW_ROOT', dirname(__DIR__));
require PW_ROOT . '/app/lib/Lang.php';

$files = array_merge(
    glob(PW_ROOT . '/app/views/respondent/*.php') ?: [],
    [PW_ROOT . '/app/views/shared/layout.php', PW_ROOT . '/app/views/shared/code.php', PW_ROOT . '/app/views/shared/error.php',
     PW_ROOT . '/app/areas/respondent.php', PW_ROOT . '/app/lib/Otp.php', PW_ROOT . '/app/lib/Guard.php', PW_ROOT . '/app/lib/Notify.php']
);
$keys = [];
$add = static function (string $k) use (&$keys): void { if ($k !== '' && !in_array($k, $keys, true)) $keys[] = $k; };
foreach ($files as $f) {
    preg_match_all('/\bte?\(\s*\'((?:[^\'\\\\]|\\\\.)+)\'/s', (string) file_get_contents($f), $m);
    foreach ($m[1] as $k) $add(stripslashes($k));
}
// sentences that reach the page through a variable
$extra = [
    'Confirm your new email', 'Other', 'Another newer iPhone',
    'Portraits and human activities', 'Outdoor landscapes', 'Indoor environments', 'Animals and pets', 'Food and dining', 'Still life', 'Macro and texture photography',
    'Join Reward Hours', 'Reward Hours is our research community. Join to be invited to more studies from Research and Metric.', 'Visit Reward Hours',
    Lang::HOW,
    'This looks like a screenshot, not a photo taken with the camera. Screenshots are not accepted.',
    'This is not a camera photo file (HEIC or JPEG). Screenshots and other file types are not accepted.',
    'This photo was taken on {model}. Only photos taken on iPhone {min} or newer are accepted.',
    'This file has no camera information, so we cannot confirm it was taken on iPhone {min} or newer.',
    'This file is missing the date it was taken, so it is not an original camera photo.',
    'This photo was saved by "{app}", so it is not the original camera file.',
    'This photo looks edited or re-saved by another app.',
    'This file is missing the camera details an original iPhone photo always has.',
    'This photo is smaller than a full camera photo ({mp} megapixels), so it was resized or cropped. Please upload the original.',
    'This photo was resized or cropped after it was taken. Please upload the original.',
    'This file is damaged or incomplete and cannot be opened. Please upload the photo again.',
    'This photo is too dark to use. Please take it again with more light.',
    'This photo is washed out (too bright). Please take it again.',
    'This photo is blurry. Hold the phone steady, tap to focus and take it again.',
    'This looks like the same picture as one already uploaded to this project. Please upload a different photo.',
    'This photo cannot be accepted because it appears to contain nudity or sexual content.',
    'The upload was interrupted. Please try this photo again.',
    'That category is not part of this project. Please choose another.',
    'This project has collected all the photos it needs and is now closed. Thank you.',
    'This project is not accepting photos right now.',
    'Your account cannot upload photos to this project.',
    'You have already uploaded all {n} photos for this project.',
    'You have reached today\'s limit of {n} photos. Please come back tomorrow.',
];
foreach ($extra as $k) $add($k);

if (($argv[1] ?? '') !== 'check') {
    echo json_encode($keys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit;
}
$shape = static function (string $s): array {
    preg_match_all('/\{[a-z]+\}|<\/?[a-z]+[^>]*>/', $s, $m);
    $parts = array_unique($m[0]);
    sort($parts);
    return $parts;
};
$bad = 0;
foreach (array_keys(Lang::ALL) as $code) {
    if ($code === 'en') continue;
    $file = PW_ROOT . '/app/lang/' . $code . '.php';
    $words = is_file($file) ? require $file : [];
    $missing = array_values(array_diff($keys, array_keys($words)));
    $odd = [];
    foreach ($words as $k => $v) {
        if (!in_array($k, $keys, true)) $odd[] = 'not used: ' . mb_substr($k, 0, 50);
        elseif ($shape($k) !== $shape($v)) $odd[] = 'places differ: ' . mb_substr($k, 0, 50);
    }
    printf("%-6s %3d of %d%s\n", $code, count($words) - count(array_filter($odd, static fn($o) => str_starts_with($o, 'not used'))), count($keys), $missing || $odd ? '  missing ' . count($missing) . ', to look at ' . count($odd) : '');
    foreach (array_slice($missing, 0, 5) as $k) echo '         missing: ', mb_substr($k, 0, 70), "\n";
    foreach (array_slice($odd, 0, 8) as $o) echo '         ', $o, "\n";
    $bad += count($missing) + count($odd);
}
exit($bad ? 1 : 0);
