<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-matching.php';
require_once __DIR__ . '/includes/acclaimed-public.php';

$datasets = require __DIR__ . '/includes/acclaimed-datasets.php';
$collectionKeys = ACCLAIMED_PUBLIC_COLLECTIONS;
$hasCollectionQuery = array_key_exists('collections', $_GET);
$requestedCollections = $_GET['collections'] ?? [];

if (!is_array($requestedCollections)) {
    $requestedCollections = [];
}

$selectedCollections = array_values(array_unique(array_filter(
    $requestedCollections,
    static fn($key): bool => is_string($key) && in_array($key, $collectionKeys, true)
)));

if (!$hasCollectionQuery) {
    $selectedCollections = $collectionKeys;
}

$selectionError = $hasCollectionQuery && count($selectedCollections) < 2;
$overlapsById = [];

if (!$selectionError) {
    foreach ($selectedCollections as $collectionKey) {
        foreach (acclaimed_public_matched_items($datasets[$collectionKey]) as $item) {
            $tmdbId = (int) $item['tmdb_id'];
            $overlapsById[$tmdbId]['title'] = $item['title'];
            $overlapsById[$tmdbId]['memberships'][$collectionKey] = $item;
        }
    }
}

$overlapsById = array_filter(
    $overlapsById,
    static fn(array $entry): bool => count($entry['memberships']) >= 2
);
$matchedItems = [];

foreach ($overlapsById as $tmdbId => $entry) {
    foreach ($entry['memberships'] as $item) {
        $item['tmdb_id'] = (int) $tmdbId;
        $matchedItems[] = $item;
    }
}

$adaptations = acclaimed_public_load_adaptations($matchedItems);
$results = [];

foreach ($overlapsById as $tmdbId => $entry) {
    $adaptation = $adaptations[(int) $tmdbId] ?? null;

    if ($adaptation !== null) {
        $results[] = [
            'tmdb_id' => (int) $tmdbId,
            'title' => $entry['title'],
            'memberships' => $entry['memberships'],
            'adaptation' => $adaptation,
        ];
    }
}

usort($results, static function (array $left, array $right): int {
    $membershipOrder = count($right['memberships']) <=> count($left['memberships']);

    return $membershipOrder !== 0
        ? $membershipOrder
        : strcasecmp($left['title'], $right['title']);
});

$metaTitle = 'Acclaim Overlap Explorer | Book to Screen';
$metaDescription = 'Discover book-to-screen films appearing in multiple acclaimed film collections.';
$metaCanonical = 'https://booktoscreen.org/acclaimed-overlap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php require __DIR__ . '/includes/meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
    <link rel="stylesheet" href="/assets/css/acclaimed.css?v=<?= filemtime(__DIR__ . '/assets/css/acclaimed.css') ?>">
    <link rel="stylesheet" href="/assets/css/acclaimed-overlap.css?v=<?= filemtime(__DIR__ . '/assets/css/acclaimed-overlap.css') ?>">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="acclaimed-shell acclaimed-collection-page acclaimed-overlap-page">
        <a class="acclaimed-back-link" href="/acclaimed.php">&larr; Acclaimed</a>
        <header class="acclaimed-header">
            <p class="eyebrow">Across the lists</p>
            <h1 class="page-title">Acclaim Overlap</h1>
            <p class="page-intro">Find book-to-screen films recognized by more than one acclaimed collection.</p>
        </header>

        <form class="overlap-filter" method="get" action="/acclaimed-overlap.php">
            <fieldset class="overlap-filter__collections">
                <legend>Compare collections</legend>
                <p class="overlap-filter__hint">Select at least two collections.</p>
                <div class="overlap-filter__options">
                    <?php foreach ($collectionKeys as $collectionKey): ?>
                        <?php $collection = $datasets[$collectionKey]; ?>
                        <label class="overlap-filter__option">
                            <input
                                type="checkbox"
                                name="collections[]"
                                value="<?= h($collectionKey) ?>"
                                <?= in_array($collectionKey, $selectedCollections, true) ? 'checked' : '' ?>
                            >
                            <span><?= h($collection['short_title']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button class="overlap-filter__submit" type="submit">Show overlaps</button>
        </form>

        <?php if ($selectionError): ?>
            <p class="overlap-status" role="status">Choose at least two collections to compare.</p>
        <?php else: ?>
            <p class="overlap-status" aria-live="polite">
                <?= number_format(count($results)) ?> <?= count($results) === 1 ? 'film appears' : 'films appear' ?> in at least two selected collections.
            </p>

            <?php if ($results === []): ?>
                <p class="overlap-empty">No matched films overlap across those collections yet.</p>
            <?php else: ?>
                <section class="acclaimed-movie-list" aria-label="Films appearing in multiple collections">
                    <?php foreach ($results as $result): ?>
                        <?php
                        $adaptation = $result['adaptation'];
                        $poster = acclaimed_public_poster_url($adaptation['poster_path'] ?? null);
                        $trailerKey = trim((string) ($adaptation['trailer_youtube_key'] ?? ''));
                        $bookItem = reset($result['memberships']);
                        $bookUrl = barnes_and_noble_search_url(
                            $bookItem['source_title'] ?? $adaptation['title'] ?? null,
                            $bookItem['author'] ?? $adaptation['source_author'] ?? null
                        );
                        $soundtrackYear = acclaimed_release_year($adaptation['release_date'] ?? null) ?? acclaimed_item_year($bookItem);
                        $soundtrackUrl = youtube_soundtrack_search_url($adaptation['title'] ?? null, $soundtrackYear);
                        ?>
                        <article class="acclaimed-movie-card overlap-film">
                            <div class="acclaimed-movie-card__poster-wrap">
                                <?php if ($poster !== null && $trailerKey !== ''): ?>
                                    <button class="acclaimed-movie-card__poster-button trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($adaptation['title']) ?>" data-tmdb-id="<?= h((string) $adaptation['tmdb_id']) ?>" aria-label="Watch trailer for <?= h($adaptation['title']) ?>">
                                        <img src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                                    </button>
                                <?php elseif ($poster !== null): ?>
                                    <img class="acclaimed-movie-card__poster" src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                                <?php else: ?>
                                    <div class="acclaimed-movie-card__empty">No poster</div>
                                <?php endif; ?>
                            </div>
                            <div class="acclaimed-movie-card__body">
                                <p class="acclaimed-movie-card__context"><?= count($result['memberships']) ?> collections</p>
                                <h2><?= h($result['title']) ?></h2>
                                <p class="acclaimed-movie-card__meta">Release: <?= h((string) ($adaptation['release_date'] ?? 'Unknown')) ?></p>
                                <div class="overlap-film__memberships" aria-label="Collection rankings">
                                    <?php foreach ($result['memberships'] as $collectionKey => $item): ?>
                                        <?php $context = acclaimed_public_item_context($item, $collectionKey); ?>
                                        <span><?= h($datasets[$collectionKey]['short_title']) ?><?php if ($context !== ''): ?> <b><?= h($context) ?></b><?php endif; ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($trailerKey !== '' || $bookUrl !== null || $soundtrackUrl !== null): ?>
                                    <div class="acclaimed-movie-card__actions">
                                        <?php if ($trailerKey !== ''): ?><button class="acclaimed-movie-card__trailer trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($adaptation['title']) ?>" data-tmdb-id="<?= h((string) $adaptation['tmdb_id']) ?>">Watch Trailer</button><?php endif; ?>
                                        <?php if ($bookUrl !== null): ?><a class="acclaimed-movie-card__book" href="<?= h($bookUrl) ?>" target="_blank" rel="noopener">Find the Book</a><?php endif; ?>
                                        <?php if ($soundtrackUrl !== null): ?><a class="acclaimed-movie-card__soundtrack" href="<?= h($soundtrackUrl) ?>" target="_blank" rel="noopener">Find the Soundtrack</a><?php endif; ?>
                                        <button class="b2s-save-button" type="button" data-save-tmdb-id="<?= h((string) $adaptation['tmdb_id']) ?>" aria-pressed="false">Save</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>
</html>