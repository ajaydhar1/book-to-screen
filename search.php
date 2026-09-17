<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$search = trim(
    isset($_GET['q']) && is_string($_GET['q'])
        ? $_GET['q']
        : ''
);
$hasSearch = $search !== '';
$movies = [];
$announcements = [];
$totalMovies = 0;
$totalAnnouncements = 0;

function search_poster_url(?string $posterPath): ?string
{
    if (!$posterPath) {
        return null;
    }

    return 'https://image.tmdb.org/t/p/w342' . $posterPath;
}

function search_adaptation_type_label(string $adaptationType): string
{
    return match ($adaptationType) {
        'film' => 'Film',
        'tv-series' => 'TV Series',
        'limited-series' => 'Limited Series',
        default => 'Adaptation',
    };
}

function search_source_type_label(string $sourceType): string
{
    return match ($sourceType) {
        'books' => 'Books',
        'memoirs-nonfiction' => 'Memoirs & Nonfiction',
        'comics-graphic-novels' => 'Comics & Graphic Novels',
        'short-stories' => 'Short Stories',
        default => 'Other',
    };
}

if ($hasSearch) {
    $db = get_db();
    $movieSearchWhere = 'title LIKE :search OR original_title LIKE :search OR source_author LIKE :search OR overview LIKE :search';

    $movieCountStmt = $db->prepare(
        'SELECT COUNT(*) FROM tmdb_adaptations WHERE ' . $movieSearchWhere
    );
    $movieCountStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $movieCountStmt->execute();
    $totalMovies = (int) $movieCountStmt->fetchColumn();

    $movieListStmt = $db->prepare(
        'SELECT tmdb_id, title, original_title, overview, release_date, poster_path, '
        . 'source_author, trailer_youtube_key FROM tmdb_adaptations WHERE '
        . $movieSearchWhere
        . ' ORDER BY release_date DESC, tmdb_id DESC LIMIT :limit'
    );
    $movieListStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $movieListStmt->bindValue(':limit', 5, PDO::PARAM_INT);
    $movieListStmt->execute();
    $movies = $movieListStmt->fetchAll(PDO::FETCH_ASSOC);

    $classifiedLeadsSql = <<<'SQL'
WITH classified_leads AS (
    SELECT
        id, adaptation_id, source, article_title, article_url, article_excerpt,
        featured_image_url, published_at,
        CASE
            WHEN article_text LIKE '%graphic novel%' OR article_text LIKE '%comic book%' OR article_text LIKE '%comics%' THEN 'comics-graphic-novels'
            WHEN article_text LIKE '%short story%' OR article_text LIKE '%short stories%' THEN 'short-stories'
            WHEN article_text LIKE '%memoir%' OR article_text LIKE '%nonfiction%' OR article_text LIKE '%non-fiction%' THEN 'memoirs-nonfiction'
            WHEN article_text LIKE '%novel%' OR article_text LIKE '%book%' THEN 'books'
            ELSE NULL
        END AS source_type,
        CASE
            WHEN article_text LIKE '%limited series%' OR article_text LIKE '%limited-series%' OR article_text LIKE '%miniseries%' OR article_text LIKE '%mini-series%' THEN 'limited-series'
            WHEN article_text LIKE '%tv series%' OR article_text LIKE '%television series%' OR article_text LIKE '%streaming series%' OR article_text LIKE '%series adaptation%' OR article_text LIKE '%series based on%' OR article_text LIKE '%series based upon%' THEN 'tv-series'
            WHEN article_text LIKE '%film%' OR article_text LIKE '%movie%' OR article_text LIKE '%feature adaptation%' OR article_text LIKE '%feature film%' THEN 'film'
            ELSE NULL
        END AS adaptation_type
    FROM (
        SELECT
            leads.*,
            linked_adaptations.adaptation_id,
            LOWER(COALESCE(article_title, '') || ' ' || COALESCE(article_excerpt, '')) AS article_text
        FROM leads
        LEFT JOIN (
            SELECT lead_id, MAX(id) AS adaptation_id
            FROM adaptations
            WHERE lead_id IS NOT NULL
            GROUP BY lead_id
        ) AS linked_adaptations
            ON linked_adaptations.lead_id = leads.id
    ) AS normalized_leads
    WHERE
        (article_text LIKE '%adaptation%' OR article_text LIKE '%adapted%' OR article_text LIKE '%adapting%' OR article_text LIKE '%to adapt%' OR article_text LIKE '%based on%' OR article_text LIKE '%based upon%' OR article_text LIKE '%optioned%')
        AND
        (article_text LIKE '%novel%' OR article_text LIKE '%book%' OR article_text LIKE '%memoir%' OR article_text LIKE '%nonfiction%' OR article_text LIKE '%non-fiction%' OR article_text LIKE '%graphic novel%' OR article_text LIKE '%comic book%' OR article_text LIKE '%comics%' OR article_text LIKE '%short story%' OR article_text LIKE '%short stories%')
)
SQL;
    $announcementSearchWhere = '(article_title LIKE :search OR article_excerpt LIKE :search)';

    $announcementCountStmt = $db->prepare(
        $classifiedLeadsSql . ' SELECT COUNT(*) FROM classified_leads WHERE ' . $announcementSearchWhere
    );
    $announcementCountStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $announcementCountStmt->execute();
    $totalAnnouncements = (int) $announcementCountStmt->fetchColumn();

    $announcementListStmt = $db->prepare(
        $classifiedLeadsSql . ' SELECT * FROM classified_leads WHERE ' . $announcementSearchWhere
        . ' ORDER BY published_at DESC, id DESC LIMIT :limit'
    );
    $announcementListStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $announcementListStmt->bindValue(':limit', 5, PDO::PARAM_INT);
    $announcementListStmt->execute();
    $announcements = $announcementListStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Search | Book to Screen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
    <link rel="stylesheet" href="/assets/css/search.css?v=<?= filemtime(__DIR__ . '/assets/css/search.css') ?>">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="search-shell">
        <header class="search-page-header">
            <p class="search-eyebrow">Book to Screen</p>
            <h1><?= $hasSearch ? 'Search results for &ldquo;' . h($search) . '&rdquo;' : 'Search Book to Screen' ?></h1>
            <form class="search-page-form" method="get" action="/search.php">
                <label for="search-page-query">Search Book to Screen</label>
                <div class="search-page-form__controls">
                    <input id="search-page-query" type="search" name="q" value="<?= h($search) ?>" placeholder="Search titles, authors, trailers, or announcements...">
                    <button type="submit">Search</button>
                </div>
            </form>
        </header>

        <?php if (!$hasSearch): ?>
            <section class="search-prompt"><p>Enter a search term to find trailers, adaptations, and adaptation announcements.</p></section>
        <?php else: ?>
            <div class="search-results-grid">
                <section class="search-results-section" aria-labelledby="trailers-heading">
                    <div class="search-results-section__heading">
                        <div><p class="search-section-label">Trailers &amp; Adaptations</p><h2 id="trailers-heading"><?= h(number_format($totalMovies)) ?> <?= $totalMovies === 1 ? 'result' : 'results' ?></h2></div>
                        <a class="search-view-all" href="/trailers.php?q=<?= urlencode($search) ?>">View all results</a>
                    </div>
                    <?php if (!$movies): ?>
                        <div class="search-empty-state">No trailers or adaptations match this search.</div>
                    <?php else: ?>
                        <div class="search-movie-list">
                            <?php foreach ($movies as $movie): ?>
                                <?php $poster = search_poster_url($movie['poster_path'] ?? null); $trailerKey = trim((string) ($movie['trailer_youtube_key'] ?? '')); ?>
                                <article class="search-movie-card">
                                    <?php if ($poster): ?><img class="search-movie-card__poster" src="<?= h($poster) ?>" alt="<?= h($movie['title'] ?? '') ?>" loading="lazy"><?php else: ?><div class="search-movie-card__poster search-movie-card__poster--empty">No poster</div><?php endif; ?>
                                    <div class="search-movie-card__body">
                                        <h3><?= h($movie['title'] ?? 'Untitled') ?></h3>
                                        <p class="search-meta">Release: <?= h($movie['release_date'] ?? 'Unknown') ?></p>
                                        <?php if (!empty($movie['source_author'])): ?><p class="search-source">Based on the book by <?= h($movie['source_author']) ?></p><?php endif; ?>
                                        <p class="search-overview"><?= h($movie['overview'] ?? 'No overview available.') ?></p>
                                        <?php if ($trailerKey !== ''): ?><button class="search-trailer-button trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($movie['title'] ?? 'Untitled') ?>">Watch Trailer</button><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="search-results-section" aria-labelledby="announcements-heading">
                    <div class="search-results-section__heading">
                        <div><p class="search-section-label">Adaptation Announcements</p><h2 id="announcements-heading"><?= h(number_format($totalAnnouncements)) ?> <?= $totalAnnouncements === 1 ? 'result' : 'results' ?></h2></div>
                        <a class="search-view-all" href="/adaptation-announcements.php?search=<?= urlencode($search) ?>">View all results</a>
                    </div>
                    <?php if (!$announcements): ?>
                        <div class="search-empty-state">No adaptation announcements match this search.</div>
                    <?php else: ?>
                        <div class="search-announcement-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <article class="search-announcement-card">
                                    <?php if (!empty($announcement['featured_image_url'])): ?><img class="search-announcement-card__image" src="<?= h($announcement['featured_image_url']) ?>" alt="" loading="lazy"><?php endif; ?>
                                    <div class="search-announcement-card__body">
                                        <p class="search-meta"><?php if (!empty($announcement['adaptation_type'])): ?><span><?= h(search_adaptation_type_label($announcement['adaptation_type'])) ?></span><?php endif; ?><?php if (!empty($announcement['source_type'])): ?><span><?= h(search_source_type_label($announcement['source_type'])) ?></span><?php endif; ?><?= !empty($announcement['published_at']) ? h(format_datetime($announcement['published_at'])) : '' ?></p>
                                        <h3><a href="<?= !empty($announcement['adaptation_id']) ? h('/adaptation.php?id=' . (int) $announcement['adaptation_id']) : h($announcement['article_url']) ?>"<?= empty($announcement['adaptation_id']) ? ' target="_blank" rel="noopener"' : '' ?>><?= h($announcement['article_title']) ?></a></h3>
                                        <?php if (!empty($announcement['article_excerpt'])): ?><p class="search-overview"><?= h($announcement['article_excerpt']) ?></p><?php endif; ?>
                                        <a class="search-announcement-button" href="<?= !empty($announcement['adaptation_id']) ? h('/adaptation.php?id=' . (int) $announcement['adaptation_id']) : h($announcement['article_url']) ?>"<?= empty($announcement['adaptation_id']) ? ' target="_blank" rel="noopener"' : '' ?>><?= !empty($announcement['adaptation_id']) ? 'View Adaptation' : 'View Announcement' ?></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($hasSearch && $movies): ?><?php require_once __DIR__ . '/includes/trailer-theater.php'; ?><script src="/assets/js/trailer-theater.js"></script><?php endif; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>