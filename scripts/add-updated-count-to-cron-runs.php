<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

try {
    $db->exec("
        ALTER TABLE cron_runs
        ADD COLUMN updated_count INTEGER NOT NULL DEFAULT 0
    ");

    echo "Added updated_count to cron_runs." . PHP_EOL;
} catch (PDOException $e) {
    echo "updated_count migration: " . $e->getMessage() . PHP_EOL;
}