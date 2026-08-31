<?php

declare(strict_types=1);

date_default_timezone_set('America/New_York');

$source = __DIR__ . '/../data/adaptations.sqlite';
$backupDir = __DIR__ . '/../backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0700, true);
}

if (!file_exists($source)) {
    throw new RuntimeException('Source database does not exist: ' . $source);
}

$filename = sprintf(
    'adaptations-%s.sqlite',
    date('Y-m-d-His')
);

$destination = $backupDir . '/' . $filename;

$sourceDb = new SQLite3($source, SQLITE3_OPEN_READONLY);
$backupDb = new SQLite3($destination);

if (!$sourceDb->backup($backupDb)) {
    throw new RuntimeException('SQLite backup failed.');
}

$backupDb->close();
$sourceDb->close();

// Keep 30 days of backups.
$cutoff = time() - (30 * 24 * 60 * 60);

foreach (glob($backupDir . '/adaptations-*.sqlite') as $file) {
    if (is_file($file) && filemtime($file) < $cutoff) {
        unlink($file);
    }
}

echo sprintf(
    "[%s] Backup completed: %s\n",
    date('Y-m-d H:i:s'),
    $destination
);