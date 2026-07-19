<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_db(): PDO
{
    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Enable SQLite foreign key constraints.
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}