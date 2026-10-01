<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/video-feed-sources.php';

$enabledSources = video_feed_enabled_sources();
$activeSource = $enabledSources[0] ?? [
    'kind' => 'b2s',
    'slug' => 'b2s-trailers',
    'label' => 'B2S Trailers',
    'enabled' => true,
];

$initialItems = $activeSource['kind'] === 'b2s'
    ? video_feed_b2s_items([], 10)
    : [];

$sourceCount = count($enabledSources);
$initialSeenIds = array_map(
    static fn (array $item): string => (string) ($item['tmdb_id'] ?? ''),
    $initialItems
);

$metaTitle = 'Video Feed | Book to Screen';
$metaDescription = 'A modern vertical video feed of Book to Screen trailers.';
$metaCanonical = 'https://booktoscreen.org/video-feed.php';
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

    <meta charset="UTF-8">

    <?php require __DIR__ . '/includes/meta.php'; ?>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/video-feed.css?v=<?= filemtime(__DIR__ . '/assets/css/video-feed.css') ?>">
</head>
<body>
<?php require __DIR__ . '/includes/header.php'; ?>

<main class="video-feed-page">
    <div class="video-feed-shell">
        <?php if ($sourceCount > 1): ?>
            <nav class="video-feed-tabs" aria-label="Video feed sources" role="tablist">
                <?php foreach ($enabledSources as $source): ?>
                    <?php $isActive = $source['slug'] === $activeSource['slug']; ?>
                    <button
                        type="button"
                        class="video-feed-tab<?= $isActive ? ' is-active' : '' ?>"
                        role="tab"
                        aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                        data-video-feed-tab="<?= h((string) ($source['slug'] ?? '')) ?>"
                        data-video-feed-source="<?= h((string) ($source['slug'] ?? '')) ?>">
                        <?= h((string) ($source['label'] ?? '')) ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <button
            type="button"
            class="video-feed-sound-toggle"
            data-video-feed-sound-toggle
            aria-pressed="false">
            <span class="video-feed-sound-toggle__icon" aria-hidden="true">🔇</span>
            <span class="video-feed-sound-toggle__label">Sound Off</span>
        </button>

        <div
            class="video-feed"
            data-video-feed
            data-video-feed-source="<?= h((string) ($activeSource['slug'] ?? 'b2s-trailers')) ?>"
            data-video-feed-has-more="<?= count($initialItems) >= 10 ? 'true' : 'false' ?>">

            <?php foreach ($initialItems as $item): ?>
                <?php
                $tmdbId = (int) ($item['tmdb_id'] ?? 0);
                $movieTitle = (string) ($item['title'] ?? 'Untitled');
                $author = trim((string) ($item['source_author'] ?? ''));
                $bookTitle = trim((string) ($item['book_title'] ?? ''));
                $description = trim((string) ($item['overview'] ?? ''));
                $releaseYear = $item['release_year'] ?? null;
                $posterUrl = !empty($item['poster_url']) ? $item['poster_url'] : null;
                $bookUrl = $item['book_url'] ?? null;
                $soundtrackUrl = $item['soundtrack_url'] ?? null;
                $authorUrl = $item['author_url'] ?? null;
                ?>

                <article
                    class="video-feed-item"
                    data-video-item
                    data-tmdb-id="<?= $tmdbId ?>"
                    data-video-key="<?= h((string) ($item['trailer_youtube_key'] ?? '')) ?>"
                    data-video-title="<?= h($movieTitle) ?>">

                    <div class="video-feed-item__video-shell">
                        <div class="video-feed-item__player" data-video-player-host>
                            <?php if ($posterUrl !== null): ?>
                                <img
                                    class="video-feed-item__poster"
                                    src="<?= h($posterUrl) ?>"
                                    alt="<?= h($movieTitle) ?> poster"
                                    loading="lazy"
                                    decoding="async">
                            <?php else: ?>
                                <div class="video-feed-item__poster-fallback">Trailer</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="video-feed-item__content">
                        <h2 class="video-feed-item__title"><?= h($movieTitle) ?></h2>

                        <?php if ($bookTitle !== ''): ?>
                            <p class="video-feed-item__meta">
                                Based on <span class="video-feed-item__book-title"><?= h($bookTitle) ?></span>
                                <?php if ($author !== ''): ?>
                                    by <a href="<?= h($authorUrl ?? '/trailers.php') ?>" class="video-feed-item__author-link"><?= h($author) ?></a>
                                <?php endif; ?>
                            </p>
                        <?php elseif ($author !== ''): ?>
                            <p class="video-feed-item__meta">
                                By <a href="<?= h($authorUrl ?? '/trailers.php') ?>" class="video-feed-item__author-link"><?= h($author) ?></a>
                            </p>
                        <?php endif; ?>

                        <?php if ($releaseYear !== null): ?>
                            <p class="video-feed-item__year"><?= h((string) $releaseYear) ?></p>
                        <?php endif; ?>

                        <?php if ($description !== ''): ?>
                            <div class="video-feed-item__description-wrap">
                                <div
                                    id="desc-<?= $tmdbId ?>"
                                    class="video-feed-item__description video-feed-item__description--collapsed"
                                    data-description><?= h($description) ?></div>
                                <button
                                    type="button"
                                    class="video-feed-item__more"
                                    aria-expanded="false"
                                    aria-controls="desc-<?= $tmdbId ?>">
                                    More
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="video-feed-item__actions">
                            <?php if ($bookUrl !== null): ?>
                                <a class="video-feed-item__action" href="<?= h($bookUrl) ?>" target="_blank" rel="noopener noreferrer">Read</a>
                            <?php endif; ?>
                            <?php if ($soundtrackUrl !== null): ?>
                                <a class="video-feed-item__action" href="<?= h($soundtrackUrl) ?>" target="_blank" rel="noopener noreferrer">Listen</a>
                            <?php endif; ?>
                            <?php if ($tmdbId > 0): ?>
                                <button
                                    type="button"
                                    class="b2s-save-button video-feed-item__save"
                                    data-save-tmdb-id="<?= $tmdbId ?>"
                                    aria-pressed="false">
                                    Save
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <div id="video-feed-sentinel" class="video-feed__sentinel" aria-hidden="true"></div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
    window.VideoFeedBootstrap = {
        source: <?= json_encode((string) ($activeSource['slug'] ?? 'b2s-trailers'), JSON_THROW_ON_ERROR) ?>,
        dataUrl: '/video-feed-data.php',
        seenIds: <?= json_encode($initialSeenIds, JSON_THROW_ON_ERROR) ?>,
        hasMore: <?= json_encode(count($initialItems) >= 10, JSON_THROW_ON_ERROR) ?>
    };
</script>
<script src="/assets/js/video-feed.js?v=<?= filemtime(__DIR__ . '/assets/js/video-feed.js') ?>"></script>
</body>
</html>
