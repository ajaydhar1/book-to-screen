<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$notice = $_GET['notice'] ?? '';

$db = get_db();

$cronRunStmt = $db->prepare("
    SELECT
        id,
        job_name,
        status,
        started_at,
        completed_at,
        inserted_count,
        skipped_count,
        duration_ms,
        error_message
    FROM cron_runs
    WHERE job_name = :job_name
    ORDER BY id DESC
    LIMIT 1
");

$cronRunStmt->execute([
    ':job_name' => 'deadline_rss_import',
]);

$latestCronRun = $cronRunStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$nextCronRun = next_cron_run();

$allowedStatuses = ['all', 'pending', 'ignored', 'approved', 'rejected'];
$currentStatus = $_GET['status'] ?? 'all';

if (!in_array($currentStatus, $allowedStatuses, true)) {
    $currentStatus = 'all';
}

function pagination_url(int $page, string $status): string
{
    $params = ['page' => $page];

    if ($status !== 'all') {
        $params['status'] = $status;
    }

    return '/admin/leads.php?' . http_build_query($params);
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

function next_cron_run(): DateTimeImmutable
{
    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    /*
     * Cron schedule: 0 -/4 - - -
     *
     * Runs at minute 0 on UTC hours:
     * 00:00, 04:00, 08:00, 12:00, 16:00, and 20:00.
     */
    $currentHour = (int) $nowUtc->format('G');
    $nextHour = (int) (floor($currentHour / 4) * 4) + 4;

    if ($nextHour >= 24) {
        $nextUtc = $nowUtc
            ->modify('tomorrow')
            ->setTime(0, 0, 0);
    } else {
        $nextUtc = $nowUtc->setTime($nextHour, 0, 0);
    }

    return $nextUtc->setTimezone(new DateTimeZone(TIMEZONE));
}

function format_display_datetime(DateTimeInterface $date): string
{
    return $date->format('D, M j, Y \a\t g:i A');
}

function format_time_until(DateTimeInterface $futureDate): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    $seconds = $futureDate->getTimestamp() - $now->getTimestamp();

    if ($seconds <= 0) {
        return 'due now';
    }

    if ($seconds < 60) {
        return 'in less than a minute';
    }

    $minutes = intdiv($seconds, 60);

    if ($minutes < 60) {
        return 'in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($remainingMinutes === 0) {
        return 'in ' . $hours . ' hour' . ($hours === 1 ? '' : 's');
    }

    return 'in '
        . $hours
        . ' hour'
        . ($hours === 1 ? '' : 's')
        . ' '
        . $remainingMinutes
        . ' minute'
        . ($remainingMinutes === 1 ? '' : 's');
}

$countStmt = $db->query("
    SELECT status, COUNT(*) AS total
    FROM leads
    GROUP BY status
");

$statusCounts = [
    'pending' => 0,
    'ignored' => 0,
    'approved' => 0,
    'rejected' => 0,
];

foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$totalLeads = array_sum($statusCounts);

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$totalForCurrentFilter = $currentStatus === 'all'
    ? $totalLeads
    : $statusCounts[$currentStatus];

$totalPages = max(1, (int) ceil($totalForCurrentFilter / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

if ($currentStatus === 'all') {
    $stmt = $db->prepare("
        SELECT
            id,
            source,
            article_title,
            article_url,
            article_excerpt,
            featured_image_url,
            published_at,
            status,
            notes,
            created_at
        FROM leads
        ORDER BY published_at DESC
        LIMIT :limit OFFSET :offset
    ");
} else {
    $stmt = $db->prepare("
        SELECT
            id,
            source,
            article_title,
            article_url,
            article_excerpt,
            featured_image_url,
            published_at,
            status,
            notes,
            created_at
        FROM leads
        WHERE status = :status
        ORDER BY published_at DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':status', $currentStatus, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LRF3X9CMCT"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-LRF3X9CMCT');
    </script>

    <meta charset="utf-8">
    <title>Admin | Leads</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
    <link rel="stylesheet" href="/assets/css/leads.css?v=<?= filemtime(__DIR__ . '/../assets/css/leads.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p>Book-to-Screen Admin</p>
                <h1>Article Leads</h1>
            </div>

            <a class="view-site-link" href="/" target="_blank" rel="noopener">
                View Site ↗
            </a>
        </header>

        <section class="stats-grid" aria-label="Lead summary">
            <div class="stat-card">
                <span>Total</span>
                <strong><?= h((string) $totalLeads) ?></strong>
            </div>

            <div class="stat-card">
                <span>Pending</span>
                <strong><?= h((string) $statusCounts['pending']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Ignored</span>
                <strong><?= h((string) $statusCounts['ignored']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Approved</span>
                <strong><?= h((string) $statusCounts['approved']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Rejected</span>
                <strong><?= h((string) $statusCounts['rejected']) ?></strong>
            </div>
        </section>

        <section class="cron-panel" aria-labelledby="cron-panel-title">
            <?php if ($latestCronRun !== null): ?>
                <?php
                $cronDisplayTime = $latestCronRun['completed_at']
                    ?: $latestCronRun['started_at'];
                ?>

                <div class="cron-panel-header">
                    <div>
                        <h2 id="cron-panel-title">Deadline RSS Scheduler</h2>

                        <p>
                            <strong>Last run:</strong>
                            <strong><?= h(format_time_ago($cronDisplayTime)) ?></strong>
                            · <?= h(format_cron_datetime($cronDisplayTime)) ?>
                        </p>
                    </div>

                    <span class="cron-status-badge <?= h(cron_status_class($latestCronRun['status'])) ?>">
                        <?= $latestCronRun['status'] === 'failed' ? '●' : '●' ?>
                        <?= h(cron_status_label($latestCronRun['status'])) ?>
                    </span>
                </div>

                <div class="cron-details">
                    <div class="cron-detail">
                        <span>Inserted</span>
                        <strong><?= h((string) $latestCronRun['inserted_count']) ?></strong>
                    </div>

                    <div class="cron-detail">
                        <span>Skipped</span>
                        <strong><?= h((string) $latestCronRun['skipped_count']) ?></strong>
                    </div>

                    <div class="cron-detail">
                        <span>Duration</span>
                        <strong>
                            <?= h(format_duration(
                                $latestCronRun['duration_ms'] !== null
                                    ? (int) $latestCronRun['duration_ms']
                                    : null
                            )) ?>
                        </strong>
                    </div>

                    <div class="cron-detail">
                        <span>Next Run</span>

                        <strong>
                            <?= h(format_time_until($nextCronRun)) ?>
                        </strong>

                        <small class="cron-detail-secondary">
                            <?= h(format_display_datetime($nextCronRun)) ?>
                        </small>
                    </div>
                </div>

                <?php if (
                    $latestCronRun['status'] === 'failed'
                    && !empty($latestCronRun['error_message'])
                ): ?>
                    <p class="cron-error">
                        <strong>Error:</strong>
                        <?= h($latestCronRun['error_message']) ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="cron-panel-header">
                    <div>
                        <h2 id="cron-panel-title">Deadline RSS Scheduler</h2>
                    </div>

                    <span class="cron-status-badge cron-status-unknown">
                        ● No Runs
                    </span>
                </div>

                <p class="cron-empty">
                    No Deadline RSS runs have been recorded yet.
                </p>
            <?php endif; ?>
        </section>

        <div class="admin-toolbar">
            <nav class="filter-bar" aria-label="Lead filters">
                <?php foreach (['all', 'pending', 'ignored', 'approved', 'rejected'] as $filterStatus): ?>
                    <a
                        class="filter-link <?= $currentStatus === $filterStatus ? 'active' : '' ?>"
                        href="<?= h(filter_url($filterStatus)) ?>">
                        <?= h(ucfirst($filterStatus)) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-toolbar-actions">

                <form method="post" action="fetch-rss.php">
                    <button type="submit" class="button-secondary">
                        Fetch RSS
                    </button>
                </form>

            </div>
        </div>

        <?php if ($notice === 'ignored'): ?>
            <div class="notice success">Lead marked as ignored.</div>
        <?php elseif ($notice === 'rejected'): ?>
            <div class="notice success">Lead marked as rejected.</div>
        <?php elseif ($notice === 'invalid'): ?>
            <div class="notice error">Invalid lead status update.</div>
        <?php endif; ?>

        <?php if (($_GET['created'] ?? '') === '1'): ?>
            <div class="notice success">Adaptation created successfully.</div>
        <?php elseif (($_GET['created'] ?? '') === '0'): ?>
            <div class="notice error">Could not create adaptation.</div>
        <?php endif; ?>

        <?php if (($_GET['fetch'] ?? '') === 'success'): ?>
            <div class="notice success">
                RSS fetched. Inserted <?= h((string) ($_GET['inserted'] ?? 0)) ?>,
                skipped <?= h((string) ($_GET['skipped'] ?? 0)) ?>.
            </div>
        <?php elseif (($_GET['fetch'] ?? '') === 'error'): ?>
            <div class="notice error">
                RSS fetch failed.
            </div>
        <?php endif; ?>

        <?php if (empty($leads)): ?>
            <div class="empty-state">
                No leads found for this filter.
            </div>
        <?php else: ?>
            <section class="lead-list">
                <?php foreach ($leads as $lead): ?>

                    <?php
                    $potentialText = strtolower(
                        ($lead['article_title'] ?? '') . ' ' .
                            ($lead['article_excerpt'] ?? '')
                    );

                    $potentialKeywords = [
                        'adaptation'    => 5,
                        'based on'      => 5,
                        'optioned'      => 5,
                        'novel'         => 4,
                        'book'          => 3,
                        'memoir'        => 3,
                        'graphic novel' => 4,
                        'short story'   => 4,
                        'bestseller'    => 2,
                        'author'        => 2,
                    ];

                    $potentialScore = 0;

                    foreach ($potentialKeywords as $keyword => $points) {
                        if (str_contains($potentialText, $keyword)) {
                            $potentialScore += $points;
                        }
                    }

                    $potentialLevel = '';

                    if ($potentialScore >= 7) {
                        $potentialLevel = 'high';
                    } elseif ($potentialScore >= 3) {
                        $potentialLevel = 'possible';
                    }
                    ?>

                    <article class="lead-card <?= $potentialLevel ? 'potential-' . $potentialLevel : '' ?>">
                        <div class="lead-meta">
                            <span class="status-badge <?= h(status_class($lead['status'])) ?>">
                                <?= h($lead['status']) ?>
                            </span>

                            <span><?= h($lead['source']) ?></span>

                            <?php if (!empty($lead['published_at'])): ?>
                                <span>Published: <?= h(format_datetime($lead['published_at'])) ?></span>
                            <?php endif; ?>

                            <span>Lead #<?= h((string) $lead['id']) ?></span>
                        </div>

                        <?php if ($potentialLevel === 'high'): ?>
                            <div class="potential-badge potential-badge-high">
                                ⭐ High Adaptation Potential
                            </div>
                        <?php elseif ($potentialLevel === 'possible'): ?>
                            <div class="potential-badge potential-badge-possible">
                                📚 Possible Adaptation Lead
                            </div>
                        <?php endif; ?>

                        <div class="lead-image">
                            <?php if (!empty($lead['featured_image_url'])): ?>
                                <img
                                    src="<?= h($lead['featured_image_url']) ?>"
                                    alt=""
                                    loading="lazy">
                            <?php else: ?>
                                <div class="lead-image-placeholder">
                                    No image available
                                </div>
                            <?php endif; ?>
                        </div>

                        <h2>
                            <a href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                                <?= h($lead['article_title']) ?>
                            </a>
                        </h2>

                        <?php if (!empty($lead['article_excerpt'])): ?>
                            <p class="excerpt"><?= h($lead['article_excerpt']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($lead['notes'])): ?>
                            <p class="notes"><?= h($lead['notes']) ?></p>
                        <?php endif; ?>

                        <div class="lead-actions">
                            <a class="button button-primary" href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                                View Article
                            </a>

                            <a class="button button-muted" href="/admin/create-adaptation.php?lead_id=<?= (int) $lead['id'] ?>">
                                Approve
                            </a>

                            <a
                                class="button button-muted"
                                href="update-lead-status.php?id=<?= (int) $lead['id'] ?>&status=ignored&return_status=<?= urlencode($currentStatus) ?>&page=<?= (int) $page ?>">
                                Ignore
                            </a>

                            <a
                                class="button button-muted"
                                href="update-lead-status.php?id=<?= (int) $lead['id'] ?>&status=rejected&return_status=<?= urlencode($currentStatus) ?>&page=<?= (int) $page ?>">
                                Reject
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Lead pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= h(pagination_url($page - 1, $currentStatus)) ?>">← Newer</a>
                    <?php endif; ?>

                    <span>Page <?= h((string) $page) ?> of <?= h((string) $totalPages) ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= h(pagination_url($page + 1, $currentStatus)) ?>">Older →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>