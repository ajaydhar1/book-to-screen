<?php

declare(strict_types=1);

/**
 * trailers.php
 *
 * Displays the newest released U.S. movies previously synced from TMDB
 * with keyword 818 = "based on novel or book".
 *
 * Movie data is read from the local tmdb_adaptations database table.
 * TMDB synchronization is handled separately by:
 * scripts/sync-tmdb-adaptations.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-matching.php';

$db = get_db();

$perPage = 24;

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 1,
            'min_range' => 1,
        ],
    ]
);

$page = $page ?: 1;
$offset = ($page - 1) * $perPage;

$isShuffle = isset($_GET['shuffle']);

$author = trim(
    (string) ($_GET['author'] ?? '')
);

$hasAuthorFilter = $author !== '';

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$hasSearch = $search !== '';

$totalMovies = 0;
$totalPages = 1;


// --------------------------------------------------
// SORT
// --------------------------------------------------

// Weighted rating (IMDB-style Bayesian average) keeps low-vote-count
// titles from dominating "Highest Rated" with an unrepresentative 10/10.
// C = overall mean vote_average across tmdb_adaptations; m = minimum
// votes for a title's own rating to be trusted (chosen from the data:
// titles need ~20 votes before their rating stops looking like noise).
const RATING_PRIOR_MEAN = 6.34;
const RATING_PRIOR_VOTES = 20;

const SORT_OPTIONS = [
    'newest' => [
        'label' => 'Newest',
        'order_by' => 'release_date DESC, tmdb_id DESC',
    ],
    'oldest' => [
        'label' => 'Oldest',
        'order_by' => 'release_date ASC, tmdb_id ASC',
    ],
    'rating' => [
        'label' => 'Highest Rated',
        'order_by' => '
            ((vote_average * vote_count) + (' . RATING_PRIOR_MEAN . ' * ' . RATING_PRIOR_VOTES . '))
            / (vote_count + ' . RATING_PRIOR_VOTES . ') DESC,
            vote_count DESC,
            tmdb_id DESC
        ',
    ],
    'popularity' => [
        'label' => 'Most Popular',
        'order_by' => 'popularity DESC, tmdb_id DESC',
    ],
];

const DEFAULT_SORT = 'newest';

$sort = (string) ($_GET['sort'] ?? DEFAULT_SORT);

if (!isset(SORT_OPTIONS[$sort])) {
    $sort = DEFAULT_SORT;
}

$orderByClause = SORT_OPTIONS[$sort]['order_by'];

$hasPageState = isset($_GET['page'])
    && (is_string($_GET['page']) || is_int($_GET['page']))
    && filter_var(
        $_GET['page'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) !== false;

$hasExplicitSortState = isset($_GET['sort'])
    && is_string($_GET['sort'])
    && isset(SORT_OPTIONS[$_GET['sort']]);

$showDiscoveryChips = !$hasAuthorFilter;

$discoveryChips = [
    ['term' => 'Stephen King', 'parameter' => 'author'],
    ['term' => 'Mystery', 'parameter' => 'q'],
    ['term' => 'Jane Austen', 'parameter' => 'author'],
    ['term' => 'True Story', 'parameter' => 'q'],
    ['term' => 'Agatha Christie', 'parameter' => 'author'],
    ['term' => 'Crime', 'parameter' => 'q'],
    ['term' => 'Sherlock Holmes', 'parameter' => 'q'],
    ['term' => 'Romance', 'parameter' => 'q'],
    ['term' => 'Frankenstein', 'parameter' => 'q'],
    ['term' => 'Christmas', 'parameter' => 'q'],
    ['term' => 'Dracula', 'parameter' => 'q'],
];


// --------------------------------------------------
// FETCH MOVIES
// --------------------------------------------------

try {

    // --------------------------------------------------
    // AUTHOR VIEW
    // --------------------------------------------------

    if ($hasAuthorFilter) {

        $sql = "
        SELECT
            tmdb_id,
            title,
            original_title,
            overview,
            release_date,
            poster_path,
            backdrop_path,
            original_language,
            vote_average,
            vote_count,
            popularity,
            source_author,
            trailer_youtube_key
        FROM tmdb_adaptations
        WHERE source_author = :author
    ";

        if ($hasSearch) {
            $sql .= "
            AND (
                title LIKE :search
                OR original_title LIKE :search
                OR source_author LIKE :search
                OR overview LIKE :search
            )
        ";
        }

        if ($isShuffle) {
            $sql .= "
            ORDER BY RANDOM()
        ";
        } else {
            $sql .= "
            ORDER BY {$orderByClause}
        ";
        }

        $stmt = $db->prepare($sql);

        $stmt->bindValue(
            ':author',
            $author,
            PDO::PARAM_STR
        );

        if ($hasSearch) {
            $stmt->bindValue(
                ':search',
                '%' . $search . '%',
                PDO::PARAM_STR
            );
        }

        // --------------------------------------------------
        // GLOBAL SHUFFLE
        // --------------------------------------------------

    } elseif ($isShuffle) {

        $sql = "
        SELECT
            tmdb_id,
            title,
            original_title,
            overview,
            release_date,
            poster_path,
            backdrop_path,
            original_language,
            vote_average,
            vote_count,
            popularity,
            source_author,
            trailer_youtube_key
        FROM tmdb_adaptations
    ";

        if ($hasSearch) {
            $sql .= "
            WHERE
                title LIKE :search
                OR original_title LIKE :search
                OR source_author LIKE :search
                OR overview LIKE :search
        ";
        }

        $sql .= "
        ORDER BY RANDOM()
        LIMIT :limit
    ";

        $stmt = $db->prepare($sql);

        if ($hasSearch) {
            $stmt->bindValue(
                ':search',
                '%' . $search . '%',
                PDO::PARAM_STR
            );
        }

        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        // --------------------------------------------------
        // GLOBAL NEWEST / PAGINATED VIEW
        // --------------------------------------------------

    } else {

        $countSql = "
        SELECT COUNT(*)
        FROM tmdb_adaptations
    ";

        if ($hasSearch) {
            $countSql .= "
            WHERE
                title LIKE :search
                OR original_title LIKE :search
                OR source_author LIKE :search
                OR overview LIKE :search
        ";
        }

        $countStmt = $db->prepare($countSql);

        if ($hasSearch) {
            $countStmt->bindValue(
                ':search',
                '%' . $search . '%',
                PDO::PARAM_STR
            );
        }

        $countStmt->execute();

        $totalMovies = (int) $countStmt->fetchColumn();

        $totalPages = max(
            1,
            (int) ceil($totalMovies / $perPage)
        );

        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = "
        SELECT
            tmdb_id,
            title,
            original_title,
            overview,
            release_date,
            poster_path,
            backdrop_path,
            original_language,
            vote_average,
            vote_count,
            popularity,
            source_author,
            trailer_youtube_key
        FROM tmdb_adaptations
    ";

        if ($hasSearch) {
            $sql .= "
            WHERE
                title LIKE :search
                OR original_title LIKE :search
                OR source_author LIKE :search
                OR overview LIKE :search
        ";
        }

        $sql .= "
        ORDER BY {$orderByClause}
        LIMIT :limit
        OFFSET :offset
    ";

        $stmt = $db->prepare($sql);

        if ($hasSearch) {
            $stmt->bindValue(
                ':search',
                '%' . $search . '%',
                PDO::PARAM_STR
            );
        }

        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();

    $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {

    die('<h2>Database Error</h2>' .
        '<pre>' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ) .
        '</pre>');
}


// --------------------------------------------------
// AUTHOR DISCOVERY
// --------------------------------------------------

$discoveryAuthors = [];

if ($hasAuthorFilter) {
    try {
        $authorProfiles = author_adaptation_profiles($db);
        $discoveryAuthors = author_discovery_recommendations($authorProfiles, $author);
    } catch (Throwable $e) {
        $discoveryAuthors = [];
    }
}


// --------------------------------------------------
// HELPERS
// --------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

$metaTitle = 'Adaptation Trailers | Book to Screen';
$metaDescription = 'Watch trailers for movies adapted from books and other source material, with release details and author information.';
$metaCanonical = 'https://booktoscreen.org/trailers.php';

?>
<!DOCTYPE html>
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
    <link rel="stylesheet" href="/assets/css/trailers.css?v=<?= filemtime(__DIR__ . '/assets/css/trailers.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
</head>
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="trailers-shell">

        <header class="trailers-header">
            <div>
                <p class="eyebrow">Book to Screen</p>
                <h1 class="page-title">Adaptation Trailers</h1>
                <p class="page-intro">
                    Discover movies based on books and watch their latest trailers.
                </p>
            </div>
        </header>

        <div class="subtitle page-intro">

            <?php if ($hasAuthorFilter): ?>

                Movies based on books by
                <strong><?= e($author) ?></strong>

            <?php endif; ?>

        </div>

        <div class="stats">

            <?php if ($hasAuthorFilter): ?>

                Showing
                <strong><?= count($movies) ?></strong>
                movie<?= count($movies) === 1 ? '' : 's' ?>
                based on books by
                <strong><?= e($author) ?></strong>.

            <?php elseif ($hasSearch && $isShuffle): ?>

                Showing
                <strong><?= count($movies) ?></strong>
                random result<?= count($movies) === 1 ? '' : 's' ?>
                for
                <strong>“<?= e($search) ?>”</strong>.

            <?php elseif ($hasSearch): ?>

                Showing
                <strong>
                    <?= $totalMovies > 0 ? $offset + 1 : 0 ?>
                    –
                    <?= min($offset + count($movies), $totalMovies) ?>
                </strong>
                of
                <strong><?= $totalMovies ?></strong>
                results for
                <strong>“<?= e($search) ?>”</strong>.

            <?php elseif ($isShuffle): ?>

                Showing <strong><?= count($movies) ?></strong>
                random TMDB movie results.

            <?php else: ?>

                Showing
                <strong>
                    <?= $totalMovies > 0 ? $offset + 1 : 0 ?>
                    –
                    <?= min($offset + count($movies), $totalMovies) ?>
                </strong>
                of
                <strong><?= $totalMovies ?></strong>
                TMDB movie results.

            <?php endif; ?>

        </div>

        <form
            class="trailer-search"
            method="get"
            action="trailers.php">

            <input
                class="trailer-search__input"
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Search movies or authors..."
                aria-label="Search movies or authors">

            <button
                class="trailer-search__button"
                type="submit">
                Search
            </button>

            <?php if ($hasSearch): ?>

                <a
                    class="trailer-search__clear"
                    href="trailers.php">
                    Clear
                </a>

            <?php endif; ?>

        </form>

        <div class="trailer-controls">

            <form
                class="sort-control"
                method="get"
                action="trailers.php">

                <?php if ($hasAuthorFilter): ?>
                    <input type="hidden" name="author" value="<?= e($author) ?>">
                <?php endif; ?>

                <?php if ($hasSearch): ?>
                    <input type="hidden" name="q" value="<?= e($search) ?>">
                <?php endif; ?>

                <label class="sort-control__label" for="sort-select">
                    Sort by
                </label>

                <select
                    id="sort-select"
                    class="sort-control__select"
                    name="sort"
                    onchange="this.form.submit()">

                    <?php foreach (SORT_OPTIONS as $sortKey => $sortOption): ?>

                        <option
                            value="<?= e($sortKey) ?>"
                            <?= $sort === $sortKey ? 'selected' : '' ?>>
                            <?= e($sortOption['label']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </form>

            <button
                class="shuffle-link vibe-button shimmer-button"
                type="button"
                data-random-trailer>
                🎲 Vibe right now
            </button>

            <a
                class="shuffle-link"
                href="?<?= http_build_query(
                            array_filter([
                                'author' => $hasAuthorFilter
                                    ? $author
                                    : null,
                                'q' => $hasSearch
                                    ? $search
                                    : null,
                                'sort' => $sort !== DEFAULT_SORT
                                    ? $sort
                                    : null,
                                'shuffle' => 1,
                            ])
                        ) ?>">
                ↻ Shuffle
            </a>

            <?php if ($isShuffle): ?>

                <a
                    class="shuffle-link"
                    href="?<?= http_build_query(
                                array_filter([
                                    'author' => $hasAuthorFilter
                                        ? $author
                                        : null,
                                    'q' => $hasSearch
                                        ? $search
                                        : null,
                                    'sort' => $sort !== DEFAULT_SORT
                                        ? $sort
                                        : null,
                                ])
                            ) ?>">
                    ▼ Newest
                </a>

            <?php endif; ?>

            <?php if ($hasAuthorFilter): ?>

                <a class="shuffle-link" href="trailers.php">
                    ✕ All Authors
                </a>

            <?php endif; ?>

        </div>

        <?php if ($hasAuthorFilter && !empty($discoveryAuthors)): ?>

            <section class="author-discovery" aria-label="Discover more authors">

                <h2 class="author-discovery__heading">Discover More Authors</h2>

                <p class="author-discovery__intro">
                    Explore writers with a similar screen-adaptation era.
                </p>

                <div class="author-discovery__chips">

                    <?php foreach ($discoveryAuthors as $recommendation): ?>

                        <a
                            class="author-discovery__chip"
                            href="trailers.php?author=<?= urlencode($recommendation['author']) ?>">
                            <?= e($recommendation['author']) ?>
                            <span class="author-discovery__count">
                                · <?= $recommendation['trailer_count'] ?>
                                adaptation<?= $recommendation['trailer_count'] === 1 ? '' : 's' ?>
                            </span>
                        </a>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>

        <?php if ($showDiscoveryChips): ?>

            <section class="author-discovery" aria-label="Discover trailers">

                <h2 class="author-discovery__heading">Explore Trailers</h2>

                <div class="author-discovery__chips">

                    <?php foreach ($discoveryChips as $chip): ?>

                        <a
                            class="author-discovery__chip"
                            href="trailers.php?<?= e(http_build_query([
                                $chip['parameter'] => $chip['term'],
                            ])) ?>">
                            <?= e($chip['term']) ?>
                        </a>

                    <?php endforeach; ?>

                </div>

            </section>

        <?php endif; ?>

        <?php if ($hasSearch && empty($movies)): ?>

            <div class="no-results">
                <h2>No trailers found</h2>

                <p>
                    We couldn't find any movies or authors matching
                    <strong>“<?= e($search) ?>”</strong>.
                </p>

                <a
                    class="no-results__clear"
                    href="trailers.php">
                    View all trailers
                </a>
            </div>

        <?php else: ?>

            <div class="trailer-grid">

                <?php foreach ($movies as $movie): ?>
                    <?php require __DIR__ . '/includes/tmdb-trailer-card.php'; ?>

                <?php endforeach; ?>

            </div>

            <?php if (
                !$isShuffle
                && !$hasAuthorFilter
                && $totalPages > 1
            ): ?>

                <nav
                    class="pagination"
                    aria-label="Movie results pagination">

                    <?php if ($page > 1): ?>

                        <a href="?<?= http_build_query(
                                        array_filter([
                                            'q' => $hasSearch
                                                ? $search
                                                : null,
                                            'sort' => $sort !== DEFAULT_SORT
                                                ? $sort
                                                : null,
                                            'page' => $page - 1,
                                        ])
                                    ) ?>">
                            ← Previous
                        </a>

                    <?php endif; ?>

                    <span>
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>

                        <a href="?<?= http_build_query(
                                        array_filter([
                                            'q' => $hasSearch
                                                ? $search
                                                : null,
                                            'sort' => $sort !== DEFAULT_SORT
                                                ? $sort
                                                : null,
                                            'page' => $page + 1,
                                        ])
                                    ) ?>">
                            Next →
                        </a>

                    <?php endif; ?>

                </nav>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>

</html>