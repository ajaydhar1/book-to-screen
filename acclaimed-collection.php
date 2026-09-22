<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-public.php';

$datasets = require __DIR__ . '/includes/acclaimed-datasets.php';
$collectionKey = isset($_GET['collection']) && is_string($_GET['collection']) ? $_GET['collection'] : '';

if (!in_array($collectionKey, ACCLAIMED_PUBLIC_COLLECTIONS, true)) {
    http_response_code(404);
    $metaTitle = 'Collection Not Found | Book to Screen';
    $metaDescription = 'The requested Acclaimed collection could not be found.';
    $metaCanonical = 'https://booktoscreen.org/acclaimed.php';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <?php require __DIR__ . '/includes/meta.php'; ?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
        <link rel="stylesheet" href="/assets/css/acclaimed.css?v=<?= filemtime(__DIR__ . '/assets/css/acclaimed.css') ?>">
    </head>
    <body>
        <?php require_once __DIR__ . '/includes/header.php'; ?>
        <main class="acclaimed-shell">
            <a class="acclaimed-back-link" href="/acclaimed.php">&larr; Acclaimed</a>
            <header class="acclaimed-header">
                <p class="acclaimed-eyebrow">Not Found</p>
                <h1>Collection not found</h1>
                <p>That Acclaimed collection is not available.</p>
            </header>
        </main>
        <?php require_once __DIR__ . '/includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

$collection = $datasets[$collectionKey];
$matchedItems = acclaimed_public_matched_items($collection);
$perPage = 25;
$totalItems = count($matchedItems);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]) ?: 1;
$page = min($page, $totalPages);
$pageItems = array_slice($matchedItems, ($page - 1) * $perPage, $perPage);
$adaptations = acclaimed_public_load_adaptations($pageItems);

function acclaimed_collection_url(string $collectionKey, int $page): string
{
    return '/acclaimed-collection.php?' . http_build_query([
        'collection' => $collectionKey,
        'page' => $page,
    ]);
}

$metaTitle = $collection['title'] . ' | Acclaimed | Book to Screen';
$metaDescription = $collection['description'];
$metaCanonical = 'https://booktoscreen.org/acclaimed-collection.php?collection=' . rawurlencode($collectionKey);

if ($collectionKey === 'b2s-100') {
    $metaImage = 'https://booktoscreen.org/assets/images/social/b2s-100-social.png';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php require __DIR__ . '/includes/meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
    <link rel="stylesheet" href="/assets/css/acclaimed.css?v=<?= filemtime(__DIR__ . '/assets/css/acclaimed.css') ?>">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="acclaimed-shell acclaimed-collection-page">
        <a class="acclaimed-back-link" href="/acclaimed.php">&larr; Acclaimed</a>
        <header class="acclaimed-header">
            <p class="acclaimed-eyebrow"><?= h(acclaimed_public_collection_label($collectionKey)) ?></p>
            <h1><?= h($collection['title']) ?></h1>
            <p><?= h($collection['description']) ?></p>
            <p class="acclaimed-count"><?= number_format($totalItems) ?> <?= $totalItems === 1 ? 'adaptation' : 'adaptations' ?> represented</p>
        </header>

        <section class="acclaimed-movie-list" aria-label="<?= h($collection['title']) ?> adaptations">
            <?php foreach ($pageItems as $item): ?>
                <?php $adaptation = $adaptations[(int) $item['tmdb_id']] ?? null; ?>
                <?php if ($adaptation === null): continue; endif; ?>
                <?php $poster = acclaimed_public_poster_url($adaptation['poster_path'] ?? null); $trailerKey = trim((string) ($adaptation['trailer_youtube_key'] ?? '')); ?>
                <article class="acclaimed-movie-card">
                    <div class="acclaimed-movie-card__poster-wrap">
                        <?php if ($poster !== null && $trailerKey !== ''): ?>
                            <button class="acclaimed-movie-card__poster-button trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($adaptation['title']) ?>" aria-label="Watch trailer for <?= h($adaptation['title']) ?>">
                                <img src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                            </button>
                        <?php elseif ($poster !== null): ?>
                            <img class="acclaimed-movie-card__poster" src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                        <?php else: ?>
                            <div class="acclaimed-movie-card__empty">No poster</div>
                        <?php endif; ?>
                    </div>
                    <div class="acclaimed-movie-card__body">
                        <p class="acclaimed-movie-card__context"><?= h(acclaimed_public_item_context($item, $collectionKey)) ?></p>
                        <h2><?= h($item['title']) ?></h2>
                        <p class="acclaimed-movie-card__meta">Release: <?= h((string) ($adaptation['release_date'] ?? $item['year'] ?? 'Unknown')) ?></p>
                        <?php if (isset($item['source_title'])): ?><p class="acclaimed-movie-card__source">Based on <strong><?= h($item['source_title']) ?></strong><?php if (isset($item['author'])): ?> by <?= h($item['author']) ?><?php endif; ?>.</p><?php elseif (!empty($adaptation['source_author'])): ?><p class="acclaimed-movie-card__source">Based on the book by <?= h($adaptation['source_author']) ?>.</p><?php endif; ?>
                        <?php if (!empty($adaptation['overview'])): ?><p class="acclaimed-movie-card__overview"><?= h($adaptation['overview']) ?></p><?php endif; ?>
                        <?php if ($trailerKey !== ''): ?><button class="acclaimed-movie-card__trailer trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($adaptation['title']) ?>">Watch Trailer</button><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <nav class="acclaimed-pagination" aria-label="<?= h($collection['title']) ?> pagination">
                <?php if ($page > 1): ?><a href="<?= h(acclaimed_collection_url($collectionKey, $page - 1)) ?>">&larr; Previous</a><?php else: ?><span></span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="<?= h(acclaimed_collection_url($collectionKey, $page + 1)) ?>">Next &rarr;</a><?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>
</html>