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

$limit = 50;


// --------------------------------------------------
// FETCH MOVIES
// --------------------------------------------------

try {

    if (isset($_GET['shuffle'])) {

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
                popularity
            FROM tmdb_adaptations
            ORDER BY RANDOM()
            LIMIT :limit
        ");

    } else {

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
                popularity
            FROM tmdb_adaptations
            ORDER BY release_date DESC
            LIMIT :limit
        ");
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();

    $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    die(
        '<h2>Database Error</h2>' .
        '<pre>' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ) .
        '</pre>'
    );
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

    <link rel="stylesheet" href="/assets/css/site.css">
    <link rel="stylesheet" href="/assets/css/trailers.css">
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <div class="container">

        <a class="back-link" href="/">
            ← Back to Home
        </a>

        <h1>TMDB Book Adaptations POC</h1>

        <div class="subtitle">
            Keyword 818 — “Based on novel or book”
        </div>

        <div class="stats">
            Showing <strong><?= count($movies) ?></strong>
            <?= isset($_GET['shuffle']) ? 'random' : 'newest' ?>
            TMDB movie results.
        </div>

        <a class="shuffle-link" href="?shuffle=1">
            🔀 Shuffle
        </a>

        <div class="trailer-grid">

            <?php foreach ($movies as $movie): ?>

                <?php
                $poster = posterUrl(
                    $movie['poster_path'] ?? null
                );
                ?>

                <div class="card">

                    <?php if ($poster): ?>

                        <img
                            class="poster"
                            src="<?= e($poster) ?>"
                            alt="<?= e($movie['title'] ?? '') ?>">

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

                        <div class="overview">
                            <?= e(
                                $movie['overview']
                                    ?? 'No overview available.'
                            ) ?>
                        </div>

                        <div class="actions">

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

    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>