<?php
declare(strict_types=1);

// Keeping original photos in a DigitalOcean Spaces bucket (installed on the server as: pw-spaces).
//   pw-spaces setup     ask for the bucket details and keys, test them, switch the bucket on
//   pw-spaces test      upload, read back and delete a small test file
//   pw-spaces status    how many photos are on this server and how many in the bucket
//   pw-spaces move      move photos that are still on this server into the bucket
//   pw-spaces off       store new photos on this server again (photos in the bucket stay there and keep working)
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';
$configFile = getenv('PHOTOWALL_CONFIG') ?: (is_file('/etc/photowall/config.php') ? '/etc/photowall/config.php' : PW_ROOT . '/config.php');

/** Writes the 'spaces' section into the settings file. Keeps a backup and puts it back if anything is wrong. */
function save_spaces(string $file, array $sp): bool
{
    $block = "    'spaces' => [\n";
    foreach (['enabled', 'endpoint', 'region', 'bucket', 'prefix', 'key', 'secret', 'path_style'] as $k) {
        if (!array_key_exists($k, $sp)) continue;
        $block .= '        ' . str_pad("'$k'", 10) . ' => ' . var_export($sp[$k], true) . ",\n";
    }
    $block .= "    ],\n";
    $old = (string) file_get_contents($file);
    if (preg_match("/^    'spaces' => \\[\\n.*?^    \\],\\n/ms", $old, $m)) {
        $new = str_replace($m[0], $block, $old);
    } elseif (($pos = strpos($old, "    'timezone'")) !== false) {
        $new = substr($old, 0, $pos) . $block . "\n" . substr($old, $pos);
    } elseif (($pos = strrpos($old, '];')) !== false) {
        $new = substr($old, 0, $pos) . $block . substr($old, $pos);
    } else {
        echo "Could not find where to put the settings in $file. Nothing changed.\n";
        return false;
    }
    if (!is_writable($file)) {
        echo "This command needs to change $file. Please run it as root.\n";
        return false;
    }
    $backup = $file . '.bak';
    copy($file, $backup);
    chmod($backup, 0600); // it holds passwords
    file_put_contents($file, $new);
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    $check = $code === 0 ? (static fn() => include $file)() : null;
    if ($code !== 0 || !is_array($check) || ($check['spaces']['bucket'] ?? null) !== $sp['bucket']) {
        copy($backup, $file);
        unlink($backup);
        echo "The settings file was not valid after the change, so it was put back. Nothing changed.\n";
        return false;
    }
    unlink($backup);
    return true;
}

function ask(string $label, string $default = '', bool $hidden = false): string
{
    echo $label . ($default !== '' ? " [$default]" : '') . ': ';
    if ($hidden) @shell_exec('stty -echo 2>/dev/null');
    $v = trim((string) fgets(STDIN));
    if ($hidden) {
        @shell_exec('stty echo 2>/dev/null');
        echo "\n";
    }
    return $v !== '' ? $v : $default;
}

if ($cmd === 'setup') {
    $now = (array) cfg('spaces', []);
    echo "Photowall: keep original photos in a DigitalOcean Spaces bucket.\nPress Enter to keep the value shown in [brackets].\n\n";
    $sp = [
        'enabled' => true,
        'endpoint' => rtrim(ask('Region address, without the bucket name', (string) ($now['endpoint'] ?? 'https://atl1.digitaloceanspaces.com')), '/'),
        'region' => (string) ($now['region'] ?? 'us-east-1'),
        'bucket' => ask('Bucket name', (string) ($now['bucket'] ?? 'rm-docs')),
        'prefix' => trim(ask('Folder inside the bucket', (string) ($now['prefix'] ?? 'photowall')), '/'),
        'key' => ask('Access key', (string) ($now['key'] ?? '')),
        'secret' => '',
    ];
    $secret = ask('Secret key (not shown while you type' . (($now['secret'] ?? '') !== '' ? ', Enter keeps the saved one' : '') . ')', '', true);
    $sp['secret'] = $secret !== '' ? $secret : (string) ($now['secret'] ?? '');
    if (!empty($now['path_style'])) $sp['path_style'] = true;
    // People often paste the bucket's own address (https://BUCKET.region...). The region address is the part after the bucket name.
    if (stripos($sp['endpoint'], '://' . $sp['bucket'] . '.') !== false) {
        $sp['endpoint'] = str_ireplace('://' . $sp['bucket'] . '.', '://', $sp['endpoint']);
        echo "\nNote: using {$sp['endpoint']} as the region address (the bucket name is added automatically).\n";
    }
    if (!preg_match('#^https?://[^/]+$#', $sp['endpoint']) || $sp['key'] === '' || $sp['secret'] === '' || $sp['bucket'] === '') {
        exit("The region address, bucket, access key and secret key are all needed. Nothing changed.\n");
    }
    $GLOBALS['PW_CFG']['spaces'] = $sp;
    echo "\nTesting the connection (upload, read back, delete)...\n";
    $problem = Spaces::selfTest();
    if ($problem !== null) exit("It did not work: $problem\nNothing changed. Check the details and run pw-spaces setup again.\n");
    if (!save_spaces($configFile, $sp)) exit(1);
    echo "It works. New photos now go to the bucket '{$sp['bucket']}', folder '{$sp['prefix']}', as private files.\n";
    try {
        $local = (int) Db::val("SELECT COUNT(*) FROM photos WHERE store = 'local' AND status IN ('pending','approved')");
    } catch (Throwable $ex) {
        $local = 0;
    }
    if ($local) echo "$local photos are still on this server. To move them into the bucket too, run: pw-spaces move\n";
} elseif ($cmd === 'off') {
    $sp = (array) cfg('spaces', []);
    if (!$sp) exit("The bucket was never set up. Nothing changed.\n");
    $sp['enabled'] = false;
    if (!save_spaces($configFile, $sp)) exit(1);
    echo "New photos are stored on this server again. Photos already in the bucket stay there and keep working.\n";
} elseif ($cmd === 'test') {
    if (!Spaces::on()) exit("The bucket is not switched on. Run: pw-spaces setup\n");
    $problem = Spaces::selfTest();
    echo $problem === null ? "The bucket works: a test file was uploaded, read back and deleted, and it was not readable without a private link.\n" : "Problem: $problem\n";
    exit($problem === null ? 0 : 1);
} elseif ($cmd === 'status') {
    echo 'New photos go to: ' . (Spaces::on() ? "the bucket '" . cfg('spaces.bucket') . "' (" . cfg('spaces.endpoint') . "), folder '" . cfg('spaces.prefix') . "'" : 'this server (' . cfg('storage') . ')') . "\n";
    foreach (Db::all("SELECT store, COUNT(*) AS n, COALESCE(SUM(bytes), 0) AS b FROM photos WHERE status IN ('pending','approved') GROUP BY store") as $r) {
        echo '  ' . str_pad($r['store'] === 'spaces' ? 'In the bucket' : 'On this server', 16) . $r['n'] . ' photos, ' . human_bytes((int) $r['b']) . "\n";
    }
} elseif ($cmd === 'move') {
    if (!Spaces::on()) exit("The bucket is not switched on. Run: pw-spaces setup\n");
    $rows = Db::all("SELECT * FROM photos WHERE store = 'local' AND status IN ('pending','approved') ORDER BY id");
    echo count($rows) . " photos to move.\n";
    $moved = $failed = 0;
    foreach ($rows as $photo) {
        $file = Storage::original($photo);
        $key = Spaces::keyFor($photo);
        // The bucket only accepts the upload if the bytes match the checksum recorded when the photo arrived.
        if (!is_file($file) || !Spaces::put($file, $key, (string) $photo['mime'], (string) $photo['sha256']) || Spaces::size($key) !== (int) $photo['bytes']) {
            echo "  photo {$photo['id']}: NOT moved (" . (is_file($file) ? Spaces::$lastError : 'file missing on this server') . ")\n";
            $failed++;
            continue;
        }
        Db::q("UPDATE photos SET store = 'spaces', store_key = ? WHERE id = ?", [$key, $photo['id']]);
        unlink($file);
        if (++$moved % 25 === 0) echo "  $moved moved...\n";
    }
    echo "Done: $moved moved" . ($failed ? ", $failed could not be moved and are still on this server" : '') . ".\n";
    exit($failed ? 1 : 0);
} else {
    echo "Usage:\n  pw-spaces setup    connect a Spaces bucket and switch it on\n  pw-spaces test     check the bucket works\n  pw-spaces status   where photos are stored\n  pw-spaces move     move photos from this server into the bucket\n  pw-spaces off      store new photos on this server again\n";
}
