<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/acclaimed-matching.php';

require_admin();

function acclaimed_item_label(array $item): string
{
    $parts = [];

    if (isset($item['rank'])) {
        $parts[] = '#' . $item['rank'];
    }

    if (isset($item['year'])) {
        $parts[] = (string) $item['year'];
    } elseif (isset($item['award_year'])) {
        $parts[] = 'Award ' . $item['award_year'];
    } elseif (isset($item['induction_year'])) {
        $parts[] = 'Inducted ' . $item['induction_year'];
    }

    return implode(' | ', $parts);
}

function acclaimed_status_class(string $status): string
{
    return strtolower(str_replace('_', '-', $status));
}

function acclaimed_filter_url(string $collection, string $status): string
{
    $query = [];

    if ($collection !== 'all') {
        $query['collection'] = $collection;
    }

    if ($status !== 'all') {
        $query['status'] = $status;
    }

    return '/admin/acclaimed-match.php' . ($query === [] ? '' : '?' . http_build_query($query));
}

$datasets = require __DIR__ . '/../includes/acclaimed-datasets.php';
$db = get_db();

// This is deliberately the page's only database query. All matching is in memory.
$tmdbRows = $db->query('
    SELECT tmdb_id, title, release_date, poster_path, trailer_youtube_key, source_author
    FROM tmdb_adaptations
')->fetchAll(PDO::FETCH_ASSOC);

[$titleIndex, $titleYearIndex, $tmdbIdIndex] = acclaimed_build_tmdb_indexes($tmdbRows);

$reports = [];
$overallCounts = array_fill_keys(ACCLAIMED_MATCH_STATUSES, 0);

foreach ($datasets as $collectionKey => $collection) {
    $reports[$collectionKey] = acclaimed_build_collection_report(
        $collectionKey,
        $collection,
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );

    foreach (ACCLAIMED_MATCH_STATUSES as $status) {
        $overallCounts[$status] += $reports[$collectionKey]['counts'][$status];
    }
}

$allowedCollectionKeys = array_keys($reports);
$selectedCollection = $_GET['collection'] ?? 'all';
$selectedStatus = $_GET['status'] ?? 'all';

if (!is_string($selectedCollection) || !in_array($selectedCollection, $allowedCollectionKeys, true)) {
    $selectedCollection = 'all';
}

if (!is_string($selectedStatus) || !in_array($selectedStatus, ACCLAIMED_MATCH_STATUSES, true)) {
    $selectedStatus = 'all';
}

$totalItems = array_sum($overallCounts);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin | Acclaimed Match Review</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/../assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-nav.css') ?>">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f3ee; color: #1f1f1f; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .admin-shell { max-width: 1480px; margin: 0 auto; padding: 40px 20px 64px; }
        .admin-header { display: flex; justify-content: space-between; align-items: end; gap: 24px; margin-bottom: 24px; }
        .admin-header p { margin: 0 0 8px; color: #7a5c3e; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .admin-header h1 { margin: 0; font-size: 38px; }
        .admin-header .context { max-width: 610px; margin: 10px 0 0; color: #5d5144; line-height: 1.5; }
        .view-site-link { color: #7a5c3e; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .view-site-link:hover { color: #2b2118; text-decoration: underline; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-card, .panel { background: #fff; border: 1px solid #e3d8c8; border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, .04); }
        .stat-card { padding: 16px; }
        .stat-card span { display: block; margin-bottom: 7px; color: #756553; font-size: 12px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .stat-card strong { font-size: 28px; }
        .stat-card.b2s { background: #2b2118; border-color: #2b2118; color: #fff; }
        .stat-card.b2s span { color: #eadfce; }
        .panel { padding: 20px; margin-bottom: 22px; }
        .panel h2 { margin: 0 0 14px; font-size: 20px; }
        .summary-table, .review-table { width: 100%; border-collapse: collapse; }
        .summary-table th, .summary-table td, .review-table th, .review-table td { padding: 11px 10px; border-bottom: 1px solid #eee4d7; text-align: left; vertical-align: top; }
        .summary-table th, .review-table th { color: #756553; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
        .summary-table tr:last-child td, .review-table tr:last-child td { border-bottom: 0; }
        .filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
        .filters label { display: grid; gap: 5px; color: #5d5144; font-size: 13px; font-weight: 700; }
        select, button { min-height: 38px; border-radius: 6px; font: inherit; }
        select { border: 1px solid #cdbda8; background: #fff; padding: 7px 10px; }
        button { border: 0; background: #2b2118; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 14px; }
        .collection { margin-top: 34px; }
        .collection h2 { margin: 0; font-size: 24px; }
        .collection-description { margin: 6px 0 14px; color: #756553; }
        .collection-counts { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .04em; padding: 5px 8px; white-space: nowrap; }
        .exact-title-year { background: #d1e7dd; color: #0f5132; }
        .unique-title { background: #dbeafe; color: #174ea6; }
        .ambiguous { background: #fff3cd; color: #7a5600; }
        .unmatched { background: #f8d7da; color: #842029; }
        .table-wrap { overflow-x: auto; }
        .review-table { min-width: 980px; }
        .item-title { font-weight: 750; }
        .item-meta, .note, .candidate-meta { margin-top: 4px; color: #756553; font-size: 13px; line-height: 1.4; }
        .candidate { display: grid; grid-template-columns: 48px minmax(180px, 1fr); gap: 9px; margin-bottom: 10px; }
        .candidate:last-child { margin-bottom: 0; }
        .poster, .poster-placeholder { width: 48px; height: 72px; border-radius: 4px; background: #eee4d7; object-fit: cover; }
        .poster-placeholder { display: block; }
        .candidate-title { font-weight: 700; }
        .trailer { color: #0f5132; font-size: 12px; font-weight: 700; }
        .no-results { color: #756553; font-style: italic; }
        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .admin-header { align-items: start; flex-direction: column; } }
        @media (max-width: 540px) { .stats-grid { grid-template-columns: 1fr; } .admin-shell { padding: 28px 14px 48px; } .admin-header h1 { font-size: 31px; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/admin-nav.php'; ?>
    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p>Editorial Administration</p>
                <h1>Acclaimed TMDb Match Review</h1>
                <p class="context">Read-only comparison of curated Acclaimed entries against <?= number_format(count($tmdbRows)); ?> TMDb adaptation records. One database query; all classification occurs in PHP memory.</p>
            </div>
            <a class="view-site-link" href="/">View site</a>
        </header>

        <section class="stats-grid" aria-label="Overall matching summary">
            <div class="stat-card b2s"><span>B2S 100 source entries</span><strong><?= number_format(count($reports['b2s-100']['matches'])); ?></strong></div>
            <div class="stat-card"><span>Total examined</span><strong><?= number_format($totalItems); ?></strong></div>
            <?php foreach (ACCLAIMED_MATCH_STATUSES as $status): ?>
                <div class="stat-card"><span><?= h(str_replace('_', ' ', $status)); ?></span><strong><?= number_format($overallCounts[$status]); ?></strong></div>
            <?php endforeach; ?>
        </section>

        <section class="panel">
            <h2>Collection Summary</h2>
            <div class="table-wrap">
                <table class="summary-table">
                    <thead><tr><th>Collection</th><th>Entries</th><?php foreach (ACCLAIMED_MATCH_STATUSES as $status): ?><th><?= h(str_replace('_', ' ', $status)); ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($reports as $collectionKey => $report): ?>
                        <tr>
                            <td><strong><?= h($report['collection']['title']); ?></strong></td>
                            <td><?= number_format(count($report['matches'])); ?></td>
                            <?php foreach (ACCLAIMED_MATCH_STATUSES as $status): ?><td><?= number_format($report['counts'][$status]); ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2>Review Filters</h2>
            <form class="filters" method="get">
                <label>Collection
                    <select name="collection">
                        <option value="all">All collections</option>
                        <?php foreach ($reports as $collectionKey => $report): ?><option value="<?= h($collectionKey); ?>" <?= $selectedCollection === $collectionKey ? 'selected' : ''; ?>><?= h($report['collection']['title']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="all">All statuses</option>
                        <?php foreach (ACCLAIMED_MATCH_STATUSES as $status): ?><option value="<?= h($status); ?>" <?= $selectedStatus === $status ? 'selected' : ''; ?>><?= h(str_replace('_', ' ', $status)); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Apply</button>
            </form>
        </section>

        <?php foreach ($reports as $collectionKey => $report): ?>
            <?php
            if ($selectedCollection !== 'all' && $selectedCollection !== $collectionKey) {
                continue;
            }
            $visibleMatches = array_values(array_filter(
                $report['matches'],
                static fn(array $match): bool => $selectedStatus === 'all' || $match['status'] === $selectedStatus
            ));
            ?>
            <section class="collection" id="<?= h($collectionKey); ?>">
                <h2><?= h($report['collection']['title']); ?></h2>
                <?php if (!empty($report['collection']['description'])): ?><p class="collection-description"><?= h($report['collection']['description']); ?></p><?php endif; ?>
                <div class="collection-counts">
                    <?php foreach (ACCLAIMED_MATCH_STATUSES as $status): ?><span class="badge <?= h(acclaimed_status_class($status)); ?>"><?= h(str_replace('_', ' ', $status)); ?> <?= number_format($report['counts'][$status]); ?></span><?php endforeach; ?>
                </div>
                <?php if ($visibleMatches === []): ?>
                    <p class="no-results">No entries match the current filters.</p>
                <?php else: ?>
                    <div class="table-wrap"><table class="review-table">
                        <thead><tr><th>Dataset entry</th><th>Source detail</th><th>Status</th><th>Proposed TMDb record / candidates</th></tr></thead>
                        <tbody>
                        <?php foreach ($visibleMatches as $match): ?>
                            <?php $item = $match['item']; ?>
                            <tr>
                                <td><div class="item-title"><?= h($item['title']); ?></div><div class="item-meta"><?= h(acclaimed_item_label($item)); ?></div></td>
                                <td>
                                    <?php if (isset($item['source_title'])): ?><div><strong>Book:</strong> <?= h($item['source_title']); ?></div><?php endif; ?>
                                    <?php if (isset($item['author'])): ?><div class="item-meta"><strong>Author:</strong> <?= h($item['author']); ?></div><?php endif; ?>
                                    <?php if (isset($item['director'])): ?><div class="item-meta"><strong>Director:</strong> <?= h($item['director']); ?></div><?php endif; ?>
                                    <?php if (isset($item['film_type'])): ?><div class="item-meta"><strong>Category:</strong> <?= h($item['film_type']); ?></div><?php endif; ?>
                                </td>
                                <td><span class="badge <?= h(acclaimed_status_class($match['status'])); ?>"><?= h(str_replace('_', ' ', $match['status'])); ?></span><?php if ($match['note'] !== null): ?><div class="note"><?= h($match['note']); ?></div><?php endif; ?></td>
                                <td>
                                    <?php if ($match['candidates'] === []): ?>
                                        <span class="no-results">No proposed TMDb record.</span>
                                    <?php else: ?>
                                        <?php foreach ($match['candidates'] as $candidate): ?>
                                            <div class="candidate">
                                                <?php if (!empty($candidate['poster_path'])): ?><img class="poster" src="https://image.tmdb.org/t/p/w92<?= h($candidate['poster_path']); ?>" alt=""><?php else: ?><span class="poster-placeholder" aria-hidden="true"></span><?php endif; ?>
                                                <div><div class="candidate-title"><?= h($candidate['title']); ?></div><div class="candidate-meta"><?= h((string) ($candidate['release_date'] ?: 'No release date')); ?> | TMDb <?= h((string) $candidate['tmdb_id']); ?></div><?php if (!empty($candidate['source_author'])): ?><div class="candidate-meta">Source author: <?= h($candidate['source_author']); ?></div><?php endif; ?><?php if (!empty($candidate['trailer_youtube_key'])): ?><div class="trailer">Trailer available</div><?php endif; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>