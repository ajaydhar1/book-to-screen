<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS cron_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'running',
            started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TEXT,
            inserted_count INTEGER NOT NULL DEFAULT 0,
            skipped_count INTEGER NOT NULL DEFAULT 0,
            duration_ms INTEGER,
            error_message TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    echo "Created cron_runs table.\n";
} catch (PDOException $e) {
    echo "Cron runs table: " . $e->getMessage() . "\n";
}

try {
    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_cron_runs_job_started
        ON cron_runs (job_name, started_at DESC)
    ");

    echo "Created cron_runs lookup index.\n";
} catch (PDOException $e) {
    echo "Cron runs index: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";