<?php
declare(strict_types=1);

// Makes the small copy of the stylesheet that pages load (public/assets/app.min.css). Run by pw-deploy.
// Only comments and spare spaces are removed. If the copy is missing or older than app.css, pages use app.css.
$dir = dirname(__DIR__) . '/public/assets';
$css = (string) file_get_contents($dir . '/app.css');
if ($css === '') { fwrite(STDERR, "app.css is missing or empty.\n"); exit(1); }

$css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
// quoted text (font names, file addresses, content) is set aside so nothing inside it changes
$kept = [];
$css = (string) preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/', static function (array $m) use (&$kept): string {
    $kept[] = $m[0];
    return "\x01" . (count($kept) - 1) . "\x02";
}, $css);
$css = (string) preg_replace('/\s+/', ' ', $css);
$css = (string) preg_replace('/ ?([{};,]) ?/', '$1', $css);
$css = str_replace(': ', ':', $css); // only the space after a colon: ".top :focus-visible" needs the one before
$css = str_replace(';}', '}', trim($css));
$css = (string) preg_replace_callback('/\x01(\d+)\x02/', static fn(array $m): string => $kept[(int) $m[1]], $css);

if (substr_count($css, '{') !== substr_count($css, '}') || strlen($css) < 1000) { fwrite(STDERR, "The small stylesheet did not come out right; pages keep using app.css.\n"); @unlink($dir . '/app.min.css'); exit(1); }
$tmp = $dir . '/app.min.css.tmp';
file_put_contents($tmp, $css . "\n");
chmod($tmp, 0644);
rename($tmp, $dir . '/app.min.css');
echo 'Stylesheet: ' . round(filesize($dir . '/app.css') / 1024) . ' KB made into ' . round(strlen($css) / 1024) . " KB.\n";
