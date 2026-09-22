<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/acclaimed-matching.php';
require_once __DIR__ . '/../includes/acclaimed-b2s-tmdb.php';

require_admin();

function b2s_lookup_status_class(string $status): string
{
    return strtolower(str_replace('_', '-', $status));
}

$datasets = require __DIR__ . '/../includes/acclaimed-datasets.php';
$db = get_db();

// This page uses one database query; all B2S matching occurs in PHP memory.
$tmdbRows = $db->query('
    SELECT tmdb_id, title, release_date
    FROM tmdb_adaptations
')->fetchAll(PDO::FETCH_ASSOC);
[$titleIndex, $titleYearIndex, $tmdbIdIndex] = acclaimed_build_tmdb_indexes($tmdbRows);
$b2sReport = acclaimed_build_collection_report(
    'b2s-100',
    $datasets['b2s-100'],
    $titleIndex,
    $titleYearIndex,
    $tmdbIdIndex
);
$unmatchedItems = array_values(array_map(
    static fn(array $match): array => $match['item'],
    array_filter(
        $b2sReport['matches'],
        static fn(array $match): bool => $match['status'] === 'UNMATCHED'
    )
));
$datasetSignature = hash('sha256', serialize($unmatchedItems));
$cacheKey = 'acclaimed_b2s_missing_lookup_' . $datasetSignature;
$runLookup = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'run-lookup';
$refreshLookup = $runLookup && isset($_POST['refresh']);

if ($runLookup && ($refreshLookup || !isset($_SESSION[$cacheKey]))) {
    $existingTmdbIds = array_fill_keys(array_map(
        static fn(array $row): int => (int) $row['tmdb_id'],
        $tmdbRows
    ), true);
    $lookups = [];

    $lookups = b2s_tmdb_build_approved_insertion_set($unmatchedItems, $existingTmdbIds);

    $_SESSION[$cacheKey] = $lookups;
}

$lookups = $_SESSION[$cacheKey] ?? [];
$lookupCounts = array_fill_keys(B2S_LOOKUP_STATUSES, 0);

foreach ($lookups as $lookup) {
    $lookupCounts[$lookup['status']]++;
}

$filterStatus = $_GET['status'] ?? 'all';

if (!is_string($filterStatus) || !in_array($filterStatus, B2S_LOOKUP_STATUSES, true)) {
    $filterStatus = 'all';
}

$visibleLookups = array_values(array_filter(
    $lookups,
    static fn(array $lookup): bool => $filterStatus === 'all' || $lookup['status'] === $filterStatus
));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin | B2S Missing TMDb Review</title>
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
        .eyebrow { margin: 0 0 8px; color: #7a5c3e; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 38px; }
        .context { max-width: 720px; color: #5d5144; line-height: 1.5; }
        .link { color: #7a5c3e; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; }
        .link:hover { color: #2b2118; text-decoration: underline; }
        .panel, .stat-card { background: #fff; border: 1px solid #e3d8c8; border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, .04); }
        .panel { padding: 20px; margin-bottom: 22px; }
        .panel h2 { margin: 0 0 10px; font-size: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-card { padding: 16px; }
        .stat-card span { display: block; margin-bottom: 7px; color: #756553; font-size: 12px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .stat-card strong { font-size: 28px; }
        .stat-card.primary { background: #2b2118; border-color: #2b2118; color: #fff; }
        .stat-card.primary span { color: #eadfce; }
        form { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; }
        button, select { min-height: 38px; border-radius: 6px; font: inherit; }
        button { border: 0; background: #2b2118; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 14px; }
        button.secondary { background: #756553; }
        select { border: 1px solid #cdbda8; background: #fff; padding: 7px 10px; }
        label { display: grid; gap: 5px; color: #5d5144; font-size: 13px; font-weight: 700; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1210px; border-collapse: collapse; }
        th, td { padding: 11px 10px; border-bottom: 1px solid #eee4d7; text-align: left; vertical-align: top; }
        th { color: #756553; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        .item-title, .candidate-title { font-weight: 750; }
        .muted, .note, .candidate-meta { color: #756553; font-size: 13px; line-height: 1.4; }
        .note { margin-top: 7px; }
        .badge { display: inline-flex; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .04em; padding: 5px 8px; white-space: nowrap; }
        .high-confidence { background: #d1e7dd; color: #0f5132; }
        .review { background: #fff3cd; color: #7a5600; }
        .not-found { background: #f8d7da; color: #842029; }
        .candidate { display: grid; grid-template-columns: 62px minmax(240px, 1fr); gap: 10px; margin-bottom: 12px; }
        .candidate:last-child { margin-bottom: 0; }
        .poster, .poster-placeholder { width: 62px; height: 93px; border-radius: 4px; background: #eee4d7; object-fit: cover; }
        .poster-placeholder { display: block; }
        .trailer { margin-top: 5px; color: #0f5132; font-size: 12px; font-weight: 700; }
        .empty { color: #756553; font-style: italic; }
        @media (max-width: 900px) { .admin-header { align-items: start; flex-direction: column; } .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 540px) { .admin-shell { padding: 28px 14px 48px; } h1 { font-size: 31px; } .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/admin-nav.php'; ?>
    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Editorial Administration</p>
                <h1>B2S 100 — Missing from TMDb Adaptations</h1>
                <p class="context">The shared matcher found <?= number_format(count($unmatchedItems)); ?> unmatched entries from <?= number_format(count($datasets['b2s-100']['items'])); ?> curated B2S records. This review tool performs no database or dataset writes.</p>
            </div>
            <a class="link" href="/admin/acclaimed-match.php">Back to Acclaimed matching</a>
        </header>

        <?php if ($lookups === []): ?>
            <section class="panel">
                <h2>Run TMDb Review</h2>
                <p class="context">Searches each currently unmatched B2S title using its curated release year. A proposed match must confirm normalized title and year in TMDb movie details; videos are retrieved with those details.</p>
                <form method="post"><button name="action" value="run-lookup" type="submit">Run TMDb lookup</button></form>
            </section>
        <?php else: ?>
            <section class="stats-grid" aria-label="B2S missing TMDb lookup summary">
                <div class="stat-card primary"><span>B2S unmatched source entries</span><strong><?= number_format(count($unmatchedItems)); ?></strong></div>
                <?php foreach (B2S_LOOKUP_STATUSES as $status): ?><div class="stat-card"><span><?= h(str_replace('_', ' ', $status)); ?></span><strong><?= number_format($lookupCounts[$status]); ?></strong></div><?php endforeach; ?>
            </section>
            <section class="panel">
                <h2>Review Controls</h2>
                <form method="get">
                    <label>Status<select name="status"><option value="all">All statuses</option><?php foreach (B2S_LOOKUP_STATUSES as $status): ?><option value="<?= h($status); ?>" <?= $filterStatus === $status ? 'selected' : ''; ?>><?= h(str_replace('_', ' ', $status)); ?></option><?php endforeach; ?></select></label>
                    <button type="submit">Apply</button>
                </form>
                <form method="post" style="margin-top: 12px;"><button class="secondary" name="action" value="run-lookup" type="submit" formaction="/admin/acclaimed-b2s-missing.php"><span>Use cached results</span></button><button class="secondary" name="refresh" value="1" type="submit">Refresh TMDb lookup</button><input type="hidden" name="action" value="run-lookup"></form>
            </section>
            <section class="panel">
                <h2>Proposed TMDb Records</h2>
                <div class="table-wrap"><table>
                    <thead><tr><th>B2S entry</th><th>Book/source</th><th>Status</th><th>TMDb proposal or candidates</th></tr></thead>
                    <tbody>
                    <?php foreach ($visibleLookups as $lookup): ?>
                        <?php $item = $lookup['item']; ?>
                        <tr>
                            <td><div class="item-title">#<?= h((string) $item['rank']); ?> <?= h($item['title']); ?></div><div class="muted"><?= h((string) $item['year']); ?></div></td>
                            <td><div><?= h($item['source_title']); ?></div><div class="muted"><?= h($item['author']); ?></div></td>
                            <td><span class="badge <?= h(b2s_lookup_status_class($lookup['status'])); ?>"><?= h(str_replace('_', ' ', $lookup['status'])); ?></span><?php if ($lookup['note'] !== null): ?><div class="note"><?= h($lookup['note']); ?></div><?php endif; ?></td>
                            <td>
                                <?php if ($lookup['candidates'] === []): ?><span class="empty">No TMDb proposal.</span><?php endif; ?>
                                <?php foreach ($lookup['candidates'] as $candidate): ?>
                                    <div class="candidate">
                                        <?php if (!empty($candidate['poster_path'])): ?><img class="poster" src="https://image.tmdb.org/t/p/w185<?= h($candidate['poster_path']); ?>" alt=""><?php else: ?><span class="poster-placeholder" aria-hidden="true"></span><?php endif; ?>
                                        <div>
                                            <div class="candidate-title"><?= h((string) ($candidate['title'] ?? 'Untitled')); ?></div>
                                            <div class="candidate-meta"><?= h((string) ($candidate['release_date'] ?? 'No release date')); ?> | TMDb <?= h((string) ($candidate['id'] ?? 'Unknown')); ?></div>
                                            <?php if (!empty($candidate['overview'])): ?><div class="candidate-meta"><?= h(mb_strimwidth((string) $candidate['overview'], 0, 360, '…', 'UTF-8')); ?></div><?php endif; ?>
                                            <?php if ($lookup['proposed'] !== null && (int) ($candidate['id'] ?? 0) === (int) ($lookup['proposed']['id'] ?? 0)): ?>
                                                <div class="candidate-meta">Proposed source author: <?= h($item['author']); ?></div>
                                                <?php if ($lookup['trailer'] !== null): ?><div class="trailer">Trailer: <?= h((string) ($lookup['trailer']['name'] ?? 'YouTube Trailer')); ?> (<?= h((string) $lookup['trailer']['key']); ?>)</div><?php else: ?><div class="candidate-meta">No usable YouTube trailer found.</div><?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>