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

$totalMovies = 0;
$totalPages = 1;


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
                source_author
            FROM tmdb_adaptations
            WHERE source_author = :author
        ";

        if ($isShuffle) {
            $sql .= "
                ORDER BY RANDOM()
            ";
        } else {
            $sql .= "
                ORDER BY release_date DESC, tmdb_id DESC
            ";
        }

        $stmt = $db->prepare($sql);

        $stmt->bindValue(
            ':author',
            $author,
            PDO::PARAM_STR
        );

        // --------------------------------------------------
        // GLOBAL SHUFFLE
        // --------------------------------------------------

    } elseif ($isShuffle) {

        $stmt = $db->prepare("
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
                source_author
            FROM tmdb_adaptations
            ORDER BY RANDOM()
            LIMIT :limit
        ");

        $stmt->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        // --------------------------------------------------
        // GLOBAL NEWEST / PAGINATED VIEW
        // --------------------------------------------------

    } else {

        $countStmt = $db->query("
            SELECT COUNT(*)
            FROM tmdb_adaptations
        ");

        $totalMovies = (int) $countStmt->fetchColumn();

        $totalPages = max(
            1,
            (int) ceil($totalMovies / $perPage)
        );

        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $stmt = $db->prepare("
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
                source_author
            FROM tmdb_adaptations
            ORDER BY release_date DESC, tmdb_id DESC
            LIMIT :limit
            OFFSET :offset
        ");

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

function posterUrl(?string $posterPath): ?string
{
    if (!$posterPath) {
        return null;
    }

    return 'https://image.tmdb.org/t/p/w342' . $posterPath;
}

function barnesAndNobleSearchUrl(
    ?string $movieTitle,
    ?string $sourceAuthor
): ?string {
    $query = trim(
        ($movieTitle ?? '')
            . ' '
            . ($sourceAuthor ?? '')
    );

    if ($query === '') {
        return null;
    }

    return 'https://www.barnesandnoble.com/search?q='
        . urlencode($query);
}

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

    <title>TMDB Book Adaptations POC</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="canonical" href="https://booktoscreen.org/trailers.php">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailers.css?v=<?= filemtime(__DIR__ . '/assets/css/trailers.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="container">

        <a class="back-link" href="/">
            ← Back to Home
        </a>

        <h1>TMDB Book Adaptations POC</h1>

        <div class="subtitle">

            <?php if ($hasAuthorFilter): ?>

                Movies based on books by
                <strong><?= e($author) ?></strong>

            <?php else: ?>

                Keyword 818 — “Based on novel or book”

            <?php endif; ?>

        </div>

        <div class="stats">

            <?php if ($hasAuthorFilter): ?>

                Showing
                <strong><?= count($movies) ?></strong>
                movie<?= count($movies) === 1 ? '' : 's' ?>
                based on books by
                <strong><?= e($author) ?></strong>.

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

        <div class="trailer-controls">

            <a
                class="shuffle-link"
                href="?<?= http_build_query(
                            array_filter([
                                'author' => $hasAuthorFilter
                                    ? $author
                                    : null,
                                'shuffle' => 1,
                            ])
                        ) ?>">
                🔀 Shuffle
            </a>

            <?php if ($isShuffle): ?>

                <a
                    class="shuffle-link"
                    href="<?= $hasAuthorFilter
                                ? '?author=' . urlencode($author)
                                : 'trailers.php' ?>">
                    ↩ Newest
                </a>

            <?php endif; ?>

            <?php if ($hasAuthorFilter): ?>

                <a class="shuffle-link" href="trailers.php">
                    ✕ All Authors
                </a>

            <?php endif; ?>

        </div>

        <div class="trailer-grid">

            <?php foreach ($movies as $movie): ?>

                <?php
                $poster = posterUrl(
                    $movie['poster_path'] ?? null
                );

                $bookUrl = barnesAndNobleSearchUrl(
                    $movie['title'] ?? null,
                    $movie['source_author'] ?? null
                );
                ?>

                <div class="card">

                    <?php if ($poster): ?>

                        <a
                            class="poster-link"
                            href="tmdb-trailer.php?id=<?= urlencode(
                                                            (string) $movie['tmdb_id']
                                                        ) ?>"
                            target="_blank"
                            rel="noopener"
                            aria-label="Watch trailer for <?= e(
                                                                $movie['title']
                                                                    ?? 'Untitled'
                                                            ) ?>">

                            <img
                                class="poster"
                                src="<?= e($poster) ?>"
                                alt="<?= e($movie['title'] ?? '') ?>">

                        </a>

                    <?php else: ?>

                        <div class="no-poster">
                            No poster
                        </div>

                    <?php endif; ?>

                    <div class="card-body">

                        <div class="title">
                            <?= e($movie['title'] ?? 'Untitled') ?>
                        </div>

                        <div class="meta">

                            Release:
                            <?= e(
                                $movie['release_date']
                                    ?? 'Unknown'
                            ) ?>

                            <br>

                            TMDB rating:
                            <?= e(
                                isset($movie['vote_average'])
                                    ? number_format(
                                        (float) $movie['vote_average'],
                                        1
                                    )
                                    : 'N/A'
                            ) ?>

                        </div>

                        <?php if (!empty($movie['source_author'])): ?>

                            <div class="book-source">
                                Based on the book by
                                <a
                                    class="author-link"
                                    href="?author=<?= urlencode(
                                                        $movie['source_author']
                                                    ) ?>">
                                    <?= e($movie['source_author']) ?>
                                </a>
                            </div>

                        <?php endif; ?>

                        <div class="overview">
                            <?= e(
                                $movie['overview']
                                    ?? 'No overview available.'
                            ) ?>
                        </div>

                        <div class="actions">

                            <?php if ($bookUrl): ?>

                                <a
                                    href="<?= e($bookUrl) ?>"
                                    target="_blank"
                                    rel="noopener">
                                    📖 Find the Book
                                </a>

                            <?php endif; ?>

                            <a
                                href="tmdb-trailer.php?id=<?= urlencode(
                                                                (string) $movie['tmdb_id']
                                                            ) ?>"
                                target="_blank"
                                rel="noopener">
                                ▶ Watch Trailer
                            </a>

                        </div>

                        <div class="tmdb-id">
                            TMDB ID:
                            <?= e(
                                (string) $movie['tmdb_id']
                            ) ?>
                        </div>

                    </div>

                </div>

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
                                        'author' => $hasAuthorFilter
                                            ? $author
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
                                        'author' => $hasAuthorFilter
                                            ? $author
                                            : null,
                                        'page' => $page + 1,
                                    ])
                                ) ?>">
                        Next →
                    </a>

                <?php endif; ?>

            </nav>

        <?php endif; ?>

    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>