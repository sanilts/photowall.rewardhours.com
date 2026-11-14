<?php
declare(strict_types=1);

// Staff accounts from the command line (installed on the server as: pw-admin).
//   pw-admin add EMAIL "Full Name"     create an administrator, prints a one-time password
//   pw-admin reset EMAIL               set a new password, prints it
//   pw-admin list                      show staff accounts
//   pw-admin code                      show the last sign-in email (only useful while mail is in "log" mode)
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';
$email = clean_email((string) ($argv[2] ?? ''));
$newPass = static fn(): string => rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'xy'), '=');

if ($cmd === 'add' && $email) {
    if (Db::val('SELECT id FROM staff WHERE email = ?', [$email])) exit("That email already has a staff account. Use: pw-admin reset $email\n");
    $pass = $newPass();
    Db::q('INSERT INTO staff (email, name, pass_hash, role, created_at) VALUES (?,?,?,?,?)',
        [$email, (string) ($argv[3] ?? 'Administrator'), password_hash($pass, PASSWORD_DEFAULT), 'admin', now()]);
    echo "Administrator created.\n  Sign in at: " . area_url('admin', '/login') . "\n  Email:      $email\n  Password:   $pass\nChange the password on the Staff page after you sign in.\n";
} elseif ($cmd === 'reset' && $email) {
    if (!Db::val('SELECT id FROM staff WHERE email = ?', [$email])) exit("No staff account with that email.\n");
    $pass = $newPass();
    Db::q('UPDATE staff SET pass_hash = ?, active = 1 WHERE email = ?', [password_hash($pass, PASSWORD_DEFAULT), $email]);
    echo "New password for $email: $pass\n";
} elseif ($cmd === 'list') {
    foreach (Db::all('SELECT email, name, role, active, last_login_at FROM staff ORDER BY email') as $s) {
        echo str_pad($s['email'], 40) . str_pad($s['role'], 10) . ($s['active'] ? 'on ' : 'off') . '  last sign-in: ' . ($s['last_login_at'] ?? 'never') . "\n";
    }
} elseif ($cmd === 'code') {
    $log = cfg('storage') . '/logs/mail.log';
    if (!is_file($log)) exit("No mail log yet. (Mail driver: " . cfg('mail.driver') . ")\n");
    $parts = array_filter(explode("\n\n\n", str_replace("\n\n[", "\n\n\n[", (string) file_get_contents($log))));
    echo trim((string) end($parts)) . "\n";
} else {
    echo "Usage:\n  pw-admin add EMAIL \"Full Name\"\n  pw-admin reset EMAIL\n  pw-admin list\n  pw-admin code\n";
}
