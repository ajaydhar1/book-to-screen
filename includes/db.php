<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database/Database.php';

function get_db(): Database
{
    return match (DB_DRIVER) {
        'sqlite' => get_local_sqlite_db(),
        'sqlitecloud' => get_sqlite_cloud_db(),

        default => throw new RuntimeException(
            'Unsupported database driver: ' . DB_DRIVER
        ),
    };
}

function get_local_sqlite_db(): Database
{
    require_once __DIR__ . '/database/LocalSqliteDatabase.php';

    return new LocalSqliteDatabase(DB_PATH);
}

function get_sqlite_cloud_db(): Database
{
    require_once __DIR__ . '/database/SQLiteCloudDatabase.php';

    return new SQLiteCloudDatabase(
        SQLITE_CLOUD_CONNECTION_STRING
    );
}