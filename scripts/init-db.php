<?php

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$db->exec("
    CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rss_guid TEXT NOT NULL UNIQUE,
        article_title TEXT NOT NULL,
        article_url TEXT NOT NULL,
        article_excerpt TEXT,
        published_at TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS adaptations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        author TEXT,
        status TEXT NOT NULL DEFAULT 'upcoming',
        release_info TEXT,
        source_lead_id INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_lead_id) REFERENCES leads(id)
    )
");

echo "Database initialized successfully.\n";