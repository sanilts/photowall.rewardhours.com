<?php
declare(strict_types=1);

// Applies any database change in db/migrations that this database has not had yet. Safe to run again.
require dirname(__DIR__) . '/app/bootstrap.php';

Db::q('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$done = array_column(Db::all('SELECT name FROM migrations'), 'name');
$files = glob(PW_ROOT . '/db/migrations/*.sql') ?: [];
sort($files);
$n = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) continue;
    foreach (array_filter(array_map('trim', explode(";\n", (string) file_get_contents($file)))) as $sql) {
        Db::pdo()->exec($sql);
    }
    Db::q('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, now()]);
    echo "Applied $name\n";
    $n++;
}
echo $n ? "Database updated.\n" : "Database is already up to date.\n";
