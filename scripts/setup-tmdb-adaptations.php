<?php

declare(strict_types=1);

/**
 * scripts/setup-tmdb-adaptations.php
 *
 * Creates the database table and indexes used by
 * the TMDB Book Adaptations feature.
 */

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

try {

    $db->exec("
        CREATE TABLE IF NOT EXISTS tmdb_adaptations (
            id INTEGER PRIMARY KEY,
            tmdb_id INTEGER NOT NULL UNIQUE,
            tmdb_keyword_id INTEGER NOT NULL DEFAULT 818,
            title TEXT NOT NULL,
            original_title TEXT,
            overview TEXT,
            release_date TEXT,
            poster_path TEXT,
            backdrop_path TEXT,
            original_language TEXT,
            vote_average REAL,
            vote_count INTEGER,
            popularity REAL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_tmdb_adaptations_release_date
        ON tmdb_adaptations(release_date)
    ");

    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_tmdb_adaptations_title
        ON tmdb_adaptations(title)
    ");

    $db->exec("
        CREATE INDEX IF NOT EXISTS idx_tmdb_adaptations_keyword
        ON tmdb_adaptations(tmdb_keyword_id)
    ");

    echo "TMDB adaptations database setup complete.\n";
} catch (Throwable $e) {

    http_response_code(500);

    echo "TMDB adaptations database setup failed:\n";
    echo $e->getMessage() . "\n";
}
