<?php
declare(strict_types=1);

// Run every 10 minutes by cron: emails contributors about photos that were accepted since their last email.
// An optional number sets how many seconds to wait after a person's latest approval (default 300).
require dirname(__DIR__) . '/app/bootstrap.php';

$sent = Notify::accepted(isset($argv[1]) ? (int) $argv[1] : Notify::SETTLE);
echo $sent . ' ' . ($sent === 1 ? 'email' : 'emails') . " sent.\n";
