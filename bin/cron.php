<?php
declare(strict_types=1);

// Housekeeping, run every hour by cron: old codes, counters and leftover temporary files.
require dirname(__DIR__) . '/app/bootstrap.php';

Db::q('DELETE FROM otps WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 2 * 86400)]);
Db::q('DELETE FROM rate_limits WHERE reset_at < ?', [now()]);
foreach (glob(Storage::dir('tmp') . '/*') ?: [] as $f) {
    if (is_file($f) && filemtime($f) < time() - 3600) @unlink($f);
}
foreach (glob(rtrim((string) cfg('storage'), '/') . '/sessions/sess_*') ?: [] as $f) {
    if (filemtime($f) < time() - 43200) @unlink($f);
}
