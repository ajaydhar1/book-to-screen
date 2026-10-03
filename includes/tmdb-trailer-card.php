<?php
$movieId = (int) ($movie['tmdb_id'] ?? 0);
$poster = !empty($movie['poster_path'])
    ? 'https://image.tmdb.org/t/p/w342' . $movie['poster_path']
    : null;
$bookUrl = barnes_and_noble_search_url(
    $movie['title'] ?? null,
    $movie['source_author'] ?? null
);
$soundtrackUrl = youtube_soundtrack_search_url(
    $movie['title'] ?? null,
    acclaimed_release_year($movie['release_date'] ?? null)
);
$trailerKey = trim((string) ($movie['trailer_youtube_key'] ?? ''));

// Optional: set $tmdbCardFriendlyDate / $tmdbCardShowId before including; defaults keep Trailers unchanged.
$releaseLabel = (string) ($movie['release_date'] ?? 'Unknown');

if ($tmdbCardFriendlyDate ?? false) {
    $parsedRelease = DateTimeImmutable::createFromFormat('!Y-m-d', $releaseLabel);

    if ($parsedRelease !== false) {
        $releaseLabel = $parsedRelease->format('M j, Y');
    }
}
?>
<div class="card" data-my-list-card="<?= h((string) $movieId) ?>">
    <?php if ($poster !== null): ?>
        <?php if ($trailerKey !== ''): ?>
            <button
                class="poster-link trailer-theater-trigger"
                type="button"
                data-trailer-key="<?= h($trailerKey) ?>"
                data-trailer-title="<?= h($movie['title'] ?? 'Untitled') ?>"
                data-tmdb-id="<?= h((string) $movieId) ?>"
                aria-label="Watch trailer for <?= h($movie['title'] ?? 'Untitled') ?>">
                <img class="poster" src="<?= h($poster) ?>" alt="<?= h($movie['title'] ?? '') ?>">
            </button>
        <?php else: ?>
            <img class="poster" src="<?= h($poster) ?>" alt="<?= h($movie['title'] ?? '') ?>">
        <?php endif; ?>
    <?php else: ?>
        <div class="no-poster">No poster</div>
    <?php endif; ?>

    <div class="card-body">
        <div class="title"><?= h($movie['title'] ?? 'Untitled') ?></div>
        <div class="meta">
            Release: <?= h($releaseLabel) ?><br>
            TMDB rating: <?= h(isset($movie['vote_average']) ? number_format((float) $movie['vote_average'], 1) : 'N/A') ?>
        </div>

        <?php if (!empty($movie['source_author'])): ?>
            <div class="book-source">
                Based on the book by
                <a class="author-link" href="/trailers.php?author=<?= urlencode($movie['source_author']) ?>">
                    <?= h($movie['source_author']) ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="overview card-summary"><?= h($movie['overview'] ?? 'No overview available.') ?></div>

        <div class="actions">
            <?php if ($trailerKey !== ''): ?>
                <button
                    class="trailer-button trailer-theater-trigger"
                    type="button"
                    data-trailer-key="<?= h($trailerKey) ?>"
                    data-trailer-title="<?= h($movie['title'] ?? 'Untitled') ?>"
                    data-tmdb-id="<?= h((string) $movieId) ?>">▶ Watch Trailer</button>
            <?php endif; ?>
            <?php if ($bookUrl !== null): ?>
                <a href="<?= h($bookUrl) ?>" target="_blank" rel="noopener">📖 Find the Book</a>
            <?php endif; ?>
            <?php if ($soundtrackUrl !== null): ?>
                <a href="<?= h($soundtrackUrl) ?>" target="_blank" rel="noopener">🎵 Find the Soundtrack</a>
            <?php endif; ?>
            <?php if ($movieId > 0): ?>
                <button class="b2s-save-button" type="button" data-save-tmdb-id="<?= h((string) $movieId) ?>" aria-pressed="false">Save</button>
            <?php endif; ?>
        </div>

        <?php if ($tmdbCardShowId ?? true): ?>
            <div class="tmdb-id">TMDB ID: <?= h((string) $movieId) ?></div>
        <?php endif; ?>
    </div>
</div>