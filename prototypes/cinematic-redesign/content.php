<?php
if (!defined('CINEMATIC_PREVIEW_ENTRY')) {
    http_response_code(404);
    exit;
}
?>
<?php if ($releasedMovies !== []): ?>
<section class="section cinema-section" id="releases">
    <div class="section-inner">
        <div class="cinema-heading"><div data-reveal><p class="eyebrow">Now playing</p><h2 class="section-title">Recently released adaptations.</h2></div><div class="cinema-heading__links"><a href="../../releases.php">Browse releases →</a><a href="trailers.php">Browse trailers →</a></div></div>
        <div class="cinema-posters cinema-posters--four">
            <?php foreach ($releasedMovies as $movie):
                $poster = acclaimed_public_poster_url($movie['poster_path'] ?? null);
                $key = trim((string) ($movie['trailer_youtube_key'] ?? ''));
                $title = $movie['title'] ?? 'Untitled';
                $bookUrl = barnes_and_noble_search_url($movie['title'] ?? null, $movie['source_author'] ?? null);
                $soundtrackUrl = youtube_soundtrack_search_url($movie['title'] ?? null, acclaimed_release_year($movie['release_date'] ?? null));
            ?>
                <article class="cinema-film">
                    <div class="cinema-poster-frame">
                    <?php if ($poster && $key !== ''): ?>
                        <button class="cinema-poster trailer-theater-trigger" type="button" data-trailer-key="<?= cinematic_escape($key) ?>" data-trailer-title="<?= cinematic_escape($title) ?>" data-tmdb-id="<?= (int) $movie['tmdb_id'] ?>" aria-label="Watch trailer for <?= cinematic_escape($title) ?>"><img src="<?= cinematic_escape($poster) ?>" alt="" loading="lazy" decoding="async"><span class="cinema-play" aria-hidden="true">▶</span></button>
                    <?php elseif ($poster): ?>
                        <img class="cinema-poster" src="<?= cinematic_escape($poster) ?>" alt="<?= cinematic_escape($title) ?> poster" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="cinema-poster cinema-poster--empty">No poster available</div>
                    <?php endif; ?>
                    </div>
                    <div class="cinema-film__body">
                        <h3><?php if ($key !== ''): ?><button class="cinema-title-button trailer-theater-trigger" type="button" data-trailer-key="<?= cinematic_escape($key) ?>" data-trailer-title="<?= cinematic_escape($title) ?>" data-tmdb-id="<?= (int) $movie['tmdb_id'] ?>"><?= cinematic_escape($title) ?></button><?php else: ?><?= cinematic_escape($title) ?><?php endif; ?></h3>
                        <?php if (!empty($movie['release_date'])): ?><p><?= cinematic_escape(date('M j, Y', strtotime($movie['release_date']))) ?></p><?php endif; ?>
                        <?php if (!empty($movie['source_author'])): ?><p>Based on the book by <a href="trailers.php?author=<?= urlencode($movie['source_author']) ?>"><?= cinematic_escape($movie['source_author']) ?></a></p><?php endif; ?>
                        <div class="cinema-actions">
                            <?php if ($key !== ''): ?><button class="trailer-theater-trigger" type="button" data-trailer-key="<?= cinematic_escape($key) ?>" data-trailer-title="<?= cinematic_escape($title) ?>" data-tmdb-id="<?= (int) $movie['tmdb_id'] ?>">Watch</button><?php endif; ?>
                            <?php if ($bookUrl): ?><a href="<?= cinematic_escape($bookUrl) ?>" target="_blank" rel="noopener noreferrer">Read</a><?php endif; ?>
                            <?php if ($soundtrackUrl): ?><a href="<?= cinematic_escape($soundtrackUrl) ?>" target="_blank" rel="noopener noreferrer">Listen</a><?php endif; ?>
                            <button type="button" data-save-tmdb-id="<?= (int) $movie['tmdb_id'] ?>" aria-pressed="false">Save</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section cinema-section cinema-recommend">
    <div class="section-inner cinema-recommend__inner">
        <div><p class="eyebrow">Find your next adaptation</p><h2 class="section-title">Not sure what to watch?</h2><p class="lede">Tell us what you're looking for and discover a book-to-screen adaptation from the B2S collection.</p></div>
        <div class="cinema-recommend__actions"><a class="btn btn-primary" href="../../recommendation-wizard.php">Find a movie →</a><a class="btn btn-ghost" href="../../recommendation-wizard.php?discovery=surprise&amp;era=any&amp;rating=any&amp;language=open">Surprise me →</a></div>
    </div>
</section>

<?php if ($b2sPreviewItems !== []): ?>
<section class="section cinema-section cinema-acclaimed">
    <div class="section-inner">
        <div class="cinema-heading"><div data-reveal><p class="eyebrow">Acclaimed</p><h2 class="section-title">Book to Screen's 100 greatest film adaptations.</h2><p class="lede">Our editorial ranking of outstanding films adapted from books and other published works.</p></div><div class="cinema-heading__links"><a href="../../acclaimed-collection.php?collection=b2s-100">Explore the B2S 100 →</a><a href="../../acclaimed.php">All acclaimed collections →</a></div></div>
        <div class="cinema-posters cinema-posters--five">
            <?php foreach ($b2sPreviewItems as $item):
                $movie = $b2sPreviewAdaptations[(int) $item['tmdb_id']] ?? null;
                if ($movie === null) continue;
                $poster = acclaimed_public_poster_url($movie['poster_path'] ?? null);
                $key = trim((string) ($movie['trailer_youtube_key'] ?? ''));
                $title = $movie['title'] ?? $item['title'];
            ?>
                <article class="cinema-film">
                    <div class="cinema-poster-frame">
                    <?php if ($poster && $key !== ''): ?>
                        <button class="cinema-poster trailer-theater-trigger" type="button" data-trailer-key="<?= cinematic_escape($key) ?>" data-trailer-title="<?= cinematic_escape($title) ?>" data-tmdb-id="<?= (int) $movie['tmdb_id'] ?>" aria-label="Watch trailer for <?= cinematic_escape($title) ?>"><img src="<?= cinematic_escape($poster) ?>" alt="" loading="lazy" decoding="async"><span class="cinema-play" aria-hidden="true">▶</span></button>
                    <?php elseif ($poster): ?>
                        <img class="cinema-poster" src="<?= cinematic_escape($poster) ?>" alt="<?= cinematic_escape($title) ?> poster" loading="lazy" decoding="async">
                    <?php else: ?>
                        <div class="cinema-poster cinema-poster--empty">No poster available</div>
                    <?php endif; ?>
                    </div>
                    <div class="cinema-film__body"><p class="cinema-meta">#<?= (int) $item['rank'] ?> · <?= cinematic_escape((string) $item['year']) ?></p><h3><?= cinematic_escape($item['title']) ?></h3></div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($discoveries !== []): ?>
<section class="section cinema-section" id="featured">
    <div class="section-inner">
        <div class="cinema-heading"><div data-reveal><p class="eyebrow">Discover</p><h2 class="section-title">Stories worth discovering.</h2><p class="lede">A fresh selection from the archive, beyond the latest headlines.</p></div></div>
        <div class="cinema-discover">
            <?php foreach ($discoveries as $index => $adaptation): $featuredCard = $index === 0; require __DIR__ . '/story-card.php'; endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section cinema-section cinema-latest" id="latest-announcements">
    <div class="section-inner">
        <div class="cinema-heading"><div data-reveal><p class="eyebrow">Recently added</p><h2 class="section-title">Latest announcements.</h2><p class="lede">The stories beginning their journey to the screen.</p></div><a href="../../adaptation-announcements.php">Browse all announcements →</a></div>
        <?php if ($adaptations === []): ?>
            <p class="cinema-empty">No published adaptations found on this page.</p>
        <?php else: ?>
            <div class="cinema-latest-grid">
                <?php foreach ($adaptations as $adaptation): $featuredCard = false; require __DIR__ . '/story-card.php'; endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
            <nav class="cinema-pagination" aria-label="Announcement pages">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>#latest-announcements">← Newer</a><?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>#latest-announcements">Older →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
        <div class="section-cta"><a class="btn btn-ghost" href="../../adaptation-announcements.php">Browse all announcements →</a></div>
    </div>
</section>
