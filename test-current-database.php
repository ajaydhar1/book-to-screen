<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    $db = get_db();

    echo "Configured driver: " . DB_DRIVER . PHP_EOL;
    echo "Database class: " . get_class($db) . PHP_EOL;

    if ($db instanceof SQLiteCloudDatabase) {
        echo "Active database: SQLite Cloud" . PHP_EOL;
    } elseif ($db instanceof LocalSqliteDatabase) {
        echo "Active database: Local SQLite" . PHP_EOL;
    } else {
        echo "Active database: Unknown" . PHP_EOL;
    }

    $leadCount = $db
        ->query('SELECT COUNT(*) FROM leads')
        ->fetchColumn();

    echo "Lead count: " . $leadCount . PHP_EOL;
    echo "Database query: SUCCESS" . PHP_EOL;

} catch (Throwable $e) {
    http_response_code(500);

    echo "Database test FAILED" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
}