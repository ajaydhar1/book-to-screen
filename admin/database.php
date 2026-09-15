<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Book-to-Screen application tables to surface on this dashboard.
 * SQLite internal/system objects are intentionally excluded.
 */
const DATABASE_APP_TABLES = [
    'leads',
    'adaptations',
    'tmdb_adaptations',
    'users',
    'cron_runs',
];

function database_table_label(string $name): string
{
    return match ($name) {
        'tmdb_adaptations' => 'TMDB Adaptations',
        default => ucwords(str_replace('_', ' ', $name)),
    };
}

/**
 * Reads row/column/index counts for a known, allow-listed table name.
 * $tableName must already be validated against DATABASE_APP_TABLES.
 */
function database_inspect_table(Database $db, string $tableName): array
{
    $quotedName = '"' . str_replace('"', '""', $tableName) . '"';

    try {
        $rowCount = (int) $db
            ->query("SELECT COUNT(*) FROM {$quotedName}")
            ->fetchColumn();
    } catch (Throwable $e) {
        $rowCount = null;
    }

    try {
        $columnCount = count(
            $db->query("PRAGMA table_info({$quotedName})")->fetchAll(PDO::FETCH_ASSOC)
        );
    } catch (Throwable $e) {
        $columnCount = null;
    }

    try {
        $indexCount = count(
            $db->query("PRAGMA index_list({$quotedName})")->fetchAll(PDO::FETCH_ASSOC)
        );
    } catch (Throwable $e) {
        $indexCount = null;
    }

    return [
        'name' => $tableName,
        'row_count' => $rowCount,
        'column_count' => $columnCount,
        'index_count' => $indexCount,
    ];
}

function database_count_label(?int $count): string
{
    return $count === null ? 'Unavailable' : number_format($count);
}

$db = null;
$connectionError = null;
$driverLabel = 'Unknown';

try {
    $db = get_db();
    $db->query('SELECT 1');

    $driverLabel = match (true) {
        $db instanceof SQLiteCloudDatabase => 'SQLite Cloud',
        $db instanceof LocalSqliteDatabase => 'Local SQLite',
        default => 'Unknown',
    };
} catch (Throwable $e) {
    $connectionError = 'Could not connect to the configured database.';
}

$tables = [];
$schemaError = null;

if ($db !== null && $connectionError === null) {
    try {
        $existingTableNames = array_column(
            $db->query("
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name NOT LIKE 'sqlite_%'
                ORDER BY name COLLATE NOCASE ASC
            ")->fetchAll(PDO::FETCH_ASSOC),
            'name'
        );

        foreach ($existingTableNames as $tableName) {
            if (!in_array($tableName, DATABASE_APP_TABLES, true)) {
                continue;
            }

            $tables[] = database_inspect_table($db, $tableName);
        }
    } catch (Throwable $e) {
        $schemaError = 'Could not read database schema information.';
    }
}

$totalRecords = array_sum(array_map(
    static fn(array $table): int => $table['row_count'] ?? 0,
    $tables
));

$overallStatus = $connectionError !== null ? 'error' : 'connected';

function database_status_class(string $status): string
{
    return match ($status) {
        'connected' => 'db-status-success',
        default => 'db-status-failed',
    };
}

function database_status_label(string $status): string
{
    return match ($status) {
        'connected' => 'Connected',
        default => 'Connection Error',
    };
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin | Database</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/../assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-nav.css') ?>">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f3ee;
            color: #1f1f1f;
        }

        .admin-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .admin-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
        }

        .admin-header p {
            margin: 0 0 8px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
            color: #7a5c3e;
            font-weight: 700;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 38px;
        }

        .view-site-link {
            flex-shrink: 0;
            margin-bottom: 4px;
            color: #7a5c3e;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .view-site-link:hover {
            color: #2b2118;
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card,
        .panel {
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
        }

        .stat-card {
            padding: 18px;
        }

        .stat-card span {
            display: block;
            margin-bottom: 8px;
            color: #756553;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .stat-card strong {
            font-size: 30px;
        }

        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin: 32px 0 14px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 23px;
        }

        .section-heading p {
            margin: 0;
            color: #756553;
            font-size: 14px;
        }

        .db-table-list {
            display: grid;
            gap: 16px;
        }

        .panel {
            padding: 20px 22px;
        }

        .db-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .db-panel-header h3 {
            margin: 0;
            font-size: 19px;
        }

        .db-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .db-status-success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .db-status-failed {
            background: #f8d7da;
            color: #842029;
        }

        .db-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .db-detail {
            padding: 14px;
            background: #faf7f1;
            border: 1px solid #eee4d7;
            border-radius: 12px;
        }

        .db-detail span {
            display: block;
            margin-bottom: 6px;
            color: #756553;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .db-detail strong {
            display: block;
            font-size: 16px;
            line-height: 1.35;
        }

        .db-error {
            margin: 0 0 24px;
            padding: 12px 14px;
            background: #fef2f2;
            border: 1px solid #f5a5a5;
            border-radius: 10px;
            color: #991b1b;
            line-height: 1.5;
        }

        .empty-state {
            padding: 22px;
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            color: #756553;
            text-align: center;
        }

        @media (max-width: 850px) {

            .stats-grid,
            .db-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {

            .admin-header,
            .section-heading,
            .db-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid,
            .db-details {
                grid-template-columns: 1fr;
            }

            .admin-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <?php require_once __DIR__ . '/../includes/admin-nav.php'; ?>

    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p>Book-to-Screen Admin</p>
                <h1>Database</h1>
            </div>

            <a class="view-site-link" href="/" target="_blank" rel="noopener">
                View Site ↗
            </a>
        </header>

        <?php if ($connectionError !== null): ?>
            <p class="db-error"><?= h($connectionError) ?></p>
        <?php endif; ?>

        <?php if ($schemaError !== null): ?>
            <p class="db-error"><?= h($schemaError) ?></p>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Database summary">
            <div class="stat-card">
                <span>Connection Status</span>
                <strong>
                    <span class="db-status-badge <?= h(database_status_class($overallStatus)) ?>">
                        ● <?= h(database_status_label($overallStatus)) ?>
                    </span>
                </strong>
            </div>

            <div class="stat-card">
                <span>Active Driver</span>
                <strong><?= h($driverLabel) ?></strong>
            </div>

            <div class="stat-card">
                <span>Tables Tracked</span>
                <strong><?= h((string) count($tables)) ?></strong>
            </div>

            <div class="stat-card">
                <span>Total Records</span>
                <strong><?= h(database_count_label($totalRecords)) ?></strong>
            </div>
        </section>

        <div class="section-heading">
            <div>
                <h2>Application Tables</h2>
                <p>Row, column, and index counts for each Book-to-Screen application table.</p>
            </div>
        </div>

        <?php if (empty($tables)): ?>
            <div class="empty-state">
                No application table information is currently available.
            </div>
        <?php else: ?>
            <section class="db-table-list" aria-label="Application tables">
                <?php foreach ($tables as $table): ?>
                    <article class="panel">
                        <div class="db-panel-header">
                            <h3><?= h(database_table_label($table['name'])) ?></h3>
                        </div>

                        <div class="db-details">
                            <div class="db-detail">
                                <span>Rows</span>
                                <strong><?= h(database_count_label($table['row_count'])) ?></strong>
                            </div>

                            <div class="db-detail">
                                <span>Columns</span>
                                <strong><?= h(database_count_label($table['column_count'])) ?></strong>
                            </div>

                            <div class="db-detail">
                                <span>Indexes</span>
                                <strong><?= h(database_count_label($table['index_count'])) ?></strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>
