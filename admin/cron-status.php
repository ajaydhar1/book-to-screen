<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db();

// Most recent run for each distinct job.
$latestRunsStmt = $db->query("
    SELECT
        id,
        job_name,
        status,
        started_at,
        completed_at,
        inserted_count,
        updated_count,
        skipped_count,
        duration_ms,
        error_message
    FROM cron_runs
    WHERE id IN (
        SELECT MAX(id)
        FROM cron_runs
        GROUP BY job_name
    )
    ORDER BY job_name COLLATE NOCASE ASC
");

$latestRunsByJob = $latestRunsStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent run history across all jobs.
$recentRunsStmt = $db->query("
    SELECT
        id,
        job_name,
        status,
        started_at,
        completed_at,
        inserted_count,
        updated_count,
        skipped_count,
        duration_ms,
        error_message
    FROM cron_runs
    ORDER BY id DESC
    LIMIT 25
");

$recentRuns = $recentRunsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRunsStmt = $db->query("SELECT COUNT(*) AS total FROM cron_runs");
$totalRuns = (int) ($totalRunsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$statusCountsStmt = $db->query("
    SELECT status, COUNT(*) AS total
    FROM cron_runs
    GROUP BY status
");

$statusCounts = [
    'completed' => 0,
    'success' => 0,
    'failed' => 0,
    'running' => 0,
];

foreach ($statusCountsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$completedRuns = $statusCounts['completed'] + $statusCounts['success'];
$failedRuns = $statusCounts['failed'];
$runningRuns = $statusCounts['running'];

/**
 * Overall health is "failed" if any tracked job's most recent run failed,
 * "running" if any job is currently mid-run, otherwise "healthy".
 */
$overallHealth = 'healthy';

foreach ($latestRunsByJob as $run) {
    if ($run['status'] === 'failed') {
        $overallHealth = 'failed';
        break;
    }

    if ($run['status'] === 'running') {
        $overallHealth = 'running';
    }
}

if (empty($latestRunsByJob)) {
    $overallHealth = 'unknown';
}

function cron_job_label(string $jobName): string
{
    return ucwords(str_replace('_', ' ', $jobName));
}

function cron_status_class(string $status): string
{
    return match ($status) {
        'completed', 'success' => 'cron-status-success',
        'failed' => 'cron-status-failed',
        'running' => 'cron-status-running',
        default => 'cron-status-unknown',
    };
}

function cron_status_label(string $status): string
{
    return match ($status) {
        'success' => 'Completed',
        default => ucfirst($status),
    };
}

function cron_health_class(string $health): string
{
    return match ($health) {
        'healthy' => 'cron-status-success',
        'failed' => 'cron-status-failed',
        'running' => 'cron-status-running',
        default => 'cron-status-unknown',
    };
}

function cron_health_label(string $health): string
{
    return match ($health) {
        'healthy' => 'Healthy',
        'failed' => 'Attention Needed',
        'running' => 'Run In Progress',
        default => 'No Data',
    };
}

function cron_datetime(?string $datetime): ?DateTimeImmutable
{
    if ($datetime === null || $datetime === '') {
        return null;
    }

    /*
     * SQLite CURRENT_TIMESTAMP values are UTC.
     * Convert them to the site's configured timezone for display.
     */
    return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(TIMEZONE));
}

function format_cron_datetime(?string $datetime): string
{
    $date = cron_datetime($datetime);

    if ($date === null) {
        return 'Not available';
    }

    return $date->format('D, M j, Y \a\t g:i A');
}

function format_time_ago(?string $datetime): string
{
    $date = cron_datetime($datetime);

    if ($date === null) {
        return 'Unknown';
    }

    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    $seconds = max(0, $now->getTimestamp() - $date->getTimestamp());

    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = intdiv($seconds, 60);

    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = intdiv($minutes, 60);

    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = intdiv($hours, 24);

    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function format_duration(?int $durationMs): string
{
    if ($durationMs === null) {
        return 'Not available';
    }

    if ($durationMs < 1000) {
        return $durationMs . ' ms';
    }

    return number_format($durationMs / 1000, 2) . ' seconds';
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin | Cron Status</title>
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

        .cron-job-list {
            display: grid;
            gap: 16px;
        }

        .panel {
            padding: 20px 22px;
        }

        .cron-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .cron-panel-header h3 {
            margin: 0;
            font-size: 19px;
        }

        .cron-panel-header p {
            margin: 5px 0 0;
            color: #756553;
            font-size: 14px;
        }

        .cron-status-badge {
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

        .cron-status-success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .cron-status-failed {
            background: #f8d7da;
            color: #842029;
        }

        .cron-status-running {
            background: #fff3cd;
            color: #7a5600;
        }

        .cron-status-unknown {
            background: #e2e3e5;
            color: #41464b;
        }

        .cron-details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .cron-detail {
            padding: 14px;
            background: #faf7f1;
            border: 1px solid #eee4d7;
            border-radius: 12px;
        }

        .cron-detail span {
            display: block;
            margin-bottom: 6px;
            color: #756553;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .cron-detail strong {
            display: block;
            font-size: 16px;
            line-height: 1.35;
        }

        .cron-error {
            margin: 16px 0 0;
            padding: 12px 14px;
            background: #fef2f2;
            border: 1px solid #f5a5a5;
            border-radius: 10px;
            color: #991b1b;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .empty-state {
            padding: 22px;
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            color: #756553;
            text-align: center;
        }

        .history-table-wrapper {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
        }

        .history-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eee4d7;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }

        .history-table th {
            color: #756553;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            white-space: nowrap;
        }

        .history-table tr:last-child td {
            border-bottom: none;
        }

        .history-table td.numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .history-table .cron-status-badge {
            white-space: nowrap;
        }

        .history-error-cell {
            max-width: 260px;
            color: #991b1b;
            overflow-wrap: anywhere;
        }

        @media (max-width: 850px) {

            .stats-grid,
            .cron-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {

            .admin-header,
            .section-heading,
            .cron-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid,
            .cron-details {
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
                <h1>Cron Status</h1>
            </div>

            <a class="view-site-link" href="/" target="_blank" rel="noopener">
                View Site ↗
            </a>
        </header>

        <section class="stats-grid" aria-label="Cron summary">
            <div class="stat-card">
                <span>Overall Health</span>
                <strong>
                    <span class="cron-status-badge <?= h(cron_health_class($overallHealth)) ?>">
                        ● <?= h(cron_health_label($overallHealth)) ?>
                    </span>
                </strong>
            </div>

            <div class="stat-card">
                <span>Jobs Tracked</span>
                <strong><?= h((string) count($latestRunsByJob)) ?></strong>
            </div>

            <div class="stat-card">
                <span>Total Runs Recorded</span>
                <strong><?= h((string) $totalRuns) ?></strong>
            </div>

            <div class="stat-card">
                <span>Completed / Failed</span>
                <strong><?= h((string) $completedRuns) ?> / <?= h((string) $failedRuns) ?></strong>
            </div>
        </section>

        <div class="section-heading">
            <div>
                <h2>Latest Run by Job</h2>
                <p>Most recent recorded run for each tracked cron job.</p>
            </div>
        </div>

        <?php if (empty($latestRunsByJob)): ?>
            <div class="empty-state">
                No cron runs have been recorded yet.
            </div>
        <?php else: ?>
            <section class="cron-job-list" aria-label="Latest run per job">
                <?php foreach ($latestRunsByJob as $run): ?>
                    <?php
                    $cronDisplayTime = $run['completed_at'] ?: $run['started_at'];
                    ?>
                    <article class="panel">
                        <div class="cron-panel-header">
                            <div>
                                <h3><?= h(cron_job_label($run['job_name'])) ?></h3>

                                <p>
                                    <strong>Last run:</strong>
                                    <strong><?= h(format_time_ago($cronDisplayTime)) ?></strong>
                                    · <?= h(format_cron_datetime($cronDisplayTime)) ?>
                                </p>
                            </div>

                            <span class="cron-status-badge <?= h(cron_status_class($run['status'])) ?>">
                                ● <?= h(cron_status_label($run['status'])) ?>
                            </span>
                        </div>

                        <div class="cron-details">
                            <div class="cron-detail">
                                <span>Inserted</span>
                                <strong><?= h((string) $run['inserted_count']) ?></strong>
                            </div>

                            <div class="cron-detail">
                                <span>Updated</span>
                                <strong><?= h((string) $run['updated_count']) ?></strong>
                            </div>

                            <div class="cron-detail">
                                <span>Skipped</span>
                                <strong><?= h((string) $run['skipped_count']) ?></strong>
                            </div>

                            <div class="cron-detail">
                                <span>Duration</span>
                                <strong>
                                    <?= h(format_duration(
                                        $run['duration_ms'] !== null ? (int) $run['duration_ms'] : null
                                    )) ?>
                                </strong>
                            </div>
                        </div>

                        <?php if ($run['status'] === 'failed' && !empty($run['error_message'])): ?>
                            <p class="cron-error">
                                <strong>Error:</strong>
                                <?= h($run['error_message']) ?>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <div class="section-heading">
            <div>
                <h2>Recent Run History</h2>
                <p>The most recent <?= h((string) count($recentRuns)) ?> recorded runs across all jobs.</p>
            </div>
        </div>

        <?php if (empty($recentRuns)): ?>
            <div class="empty-state">
                No cron run history is available yet.
            </div>
        <?php else: ?>
            <div class="history-table-wrapper">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th scope="col">Job</th>
                            <th scope="col">Status</th>
                            <th scope="col">Started</th>
                            <th scope="col">Completed</th>
                            <th scope="col">Duration</th>
                            <th scope="col">Inserted</th>
                            <th scope="col">Updated</th>
                            <th scope="col">Skipped</th>
                            <th scope="col">Error</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <td><?= h(cron_job_label($run['job_name'])) ?></td>

                                <td>
                                    <span class="cron-status-badge <?= h(cron_status_class($run['status'])) ?>">
                                        <?= h(cron_status_label($run['status'])) ?>
                                    </span>
                                </td>

                                <td><?= h(format_cron_datetime($run['started_at'])) ?></td>
                                <td><?= h(format_cron_datetime($run['completed_at'])) ?></td>
                                <td>
                                    <?= h(format_duration(
                                        $run['duration_ms'] !== null ? (int) $run['duration_ms'] : null
                                    )) ?>
                                </td>
                                <td class="numeric"><?= h((string) $run['inserted_count']) ?></td>
                                <td class="numeric"><?= h((string) $run['updated_count']) ?></td>
                                <td class="numeric"><?= h((string) $run['skipped_count']) ?></td>
                                <td class="history-error-cell">
                                    <?= $run['error_message'] !== null ? h($run['error_message']) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>
