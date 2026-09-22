<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-public.php';

$datasets = require __DIR__ . '/includes/acclaimed-datasets.php';
$collections = [];
$previewItems = [];

foreach (ACCLAIMED_PUBLIC_COLLECTIONS as $collectionKey) {
    $collection = $datasets[$collectionKey];
    $matchedItems = acclaimed_public_matched_items($collection);
    $collectionPreviewItems = array_slice($matchedItems, 0, 5);
    $previewItems = [...$previewItems, ...$collectionPreviewItems];

    $collections[] = [
        'key' => $collectionKey,
        'collection' => $collection,
        'matched_items' => $matchedItems,
        'preview_items' => $collectionPreviewItems,
    ];
}

$previewAdaptations = acclaimed_public_load_adaptations($previewItems);

$metaTitle = 'Acclaimed Film Adaptations | Book to Screen';
$metaDescription = 'Explore Book to Screen\'s acclaimed film adaptations and curated intersections with landmark film collections.';
$metaCanonical = 'https://booktoscreen.org/acclaimed.php';
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
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="acclaimed-shell">
        <header class="acclaimed-header">
            <p class="acclaimed-eyebrow">Book to Screen</p>
            <h1>Acclaimed</h1>
            <p>Editorial film lists and landmark collections, viewed through the stories that made their way from page to screen.</p>
        </header>

        <?php foreach ($collections as $index => $entry): ?>
            <?php $collection = $entry['collection']; ?>
            <section class="acclaimed-cluster<?= $index === 0 ? ' acclaimed-cluster--flagship' : '' ?>" aria-labelledby="<?= h($entry['key']) ?>-heading">
                <div class="acclaimed-cluster__heading">
                    <div>
                        <p class="acclaimed-eyebrow"><?= h(acclaimed_public_collection_label($entry['key'])) ?></p>
                        <h2 id="<?= h($entry['key']) ?>-heading"><?= h($collection['title']) ?></h2>
                        <p><?= h($collection['description']) ?></p>
                    </div>
                    <a class="acclaimed-view-all" href="/acclaimed-collection.php?collection=<?= urlencode($entry['key']) ?>">View All<span class="acclaimed-view-all__count"> <?= number_format(count($entry['matched_items'])) ?></span></a>
                </div>
                <div class="acclaimed-poster-grid">
                    <?php foreach ($entry['preview_items'] as $item): ?>
                        <?php $adaptation = $previewAdaptations[(int) $item['tmdb_id']] ?? null; ?>
                        <?php if ($adaptation === null): continue; endif; ?>
                        <?php $poster = acclaimed_public_poster_url($adaptation['poster_path'] ?? null); $trailerKey = trim((string) ($adaptation['trailer_youtube_key'] ?? '')); ?>
                        <article class="acclaimed-poster-card">
                            <?php if ($poster !== null && $trailerKey !== ''): ?>
                                <button class="acclaimed-poster-card__button trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($adaptation['title']) ?>" aria-label="Watch trailer for <?= h($adaptation['title']) ?>">
                                    <img src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                                </button>
                            <?php elseif ($poster !== null): ?>
                                <img class="acclaimed-poster-card__image" src="<?= h($poster) ?>" alt="<?= h($adaptation['title']) ?> poster" loading="lazy">
                            <?php else: ?>
                                <div class="acclaimed-poster-card__empty">No poster</div>
                            <?php endif; ?>
                            <div class="acclaimed-poster-card__body">
                                <span><?= h(acclaimed_public_item_context($item, $entry['key'])) ?></span>
                                <h3><?= h($item['title']) ?></h3>
                                <p><?= h((string) ($item['year'] ?? $adaptation['release_date'] ?? '')) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>
</html>