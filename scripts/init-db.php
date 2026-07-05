<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$db->exec("
    CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rss_guid TEXT NOT NULL UNIQUE,
        source TEXT NOT NULL DEFAULT 'Deadline',
        article_title TEXT NOT NULL,
        article_url TEXT NOT NULL,
        article_excerpt TEXT,
        featured_image_url TEXT,
        published_at TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        notes TEXT,
        reviewed_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS adaptations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER,
        book_title TEXT NOT NULL,
        book_author TEXT,
        adaptation_title TEXT,
        adaptation_type TEXT,
        adaptation_status TEXT DEFAULT 'In Development',
        short_note TEXT,
        source_name TEXT,
        source_url TEXT,
        source_published_at TEXT,
        article_title TEXT,
        article_excerpt TEXT,
        featured_image_url TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");

echo "Database initialized successfully.\n";
