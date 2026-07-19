<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

// Supported roles:
//
// admin
// editor
//

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    display_name TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    email TEXT,

    password_hash TEXT NOT NULL,

    role TEXT NOT NULL DEFAULT 'editor',

    is_active INTEGER NOT NULL DEFAULT 1,
    must_change_password INTEGER NOT NULL DEFAULT 1,

    last_login_at TEXT,

    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_by_user_id INTEGER,

    FOREIGN KEY (created_by_user_id)
        REFERENCES users(id)
);
");

$db->exec("
CREATE INDEX IF NOT EXISTS idx_users_role
ON users(role);
");

$db->exec("
CREATE INDEX IF NOT EXISTS idx_users_active
ON users(is_active);
");

echo "Users table created successfully.\n";