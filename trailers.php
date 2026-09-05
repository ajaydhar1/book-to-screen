<?php

/**
 * trailers.php
 *
 * POC: Pull newest movies tagged by TMDB with:
 * Keyword 818 = "based on novel or book"
 *
 * TMDB API:
 * https://api.themoviedb.org/3/discover/movie
 */

// --------------------------------------------------
// CONFIG
// --------------------------------------------------

// Best option: store this outside the public web root,
// or pull it from an environment variable.
require_once __DIR__ . '/includes/config.local.php';

$tmdbToken = TMDB_READ_TOKEN;

// For a quick LOCAL POC only, you could temporarily use:
// $tmdbToken = 'YOUR_TMDB_READ_ACCESS_TOKEN';

$keywordId = 818;
$limit = 50; // Change to 20 if you want a smaller test.

if (!$tmdbToken) {
    die('TMDB_READ_TOKEN is not configured.');
}


// --------------------------------------------------
// TMDB REQUEST
// --------------------------------------------------

function tmdbRequest(string $url, string $token): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new Exception('TMDB cURL error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $message = $data['status_message'] ?? 'Unknown TMDB error';
        throw new Exception("TMDB returned HTTP {$httpCode}: {$message}");
    }

    return $data;
}


// --------------------------------------------------
// FETCH ENOUGH PAGES TO GET OUR DESIRED LIMIT
// --------------------------------------------------

$movies = [];
$page = 1;

try {

    while (count($movies) < $limit) {

        $query = http_build_query([
            'include_adult' => 'false',
            'include_video' => 'false',
            'language' => 'en-US',
            'page' => $page,
            'sort_by' => 'primary_release_date.desc',
            'with_keywords' => $keywordId,
            'with_origin_country' => 'US',
            'primary_release_date.lte' => date('Y-m-d')
        ]);

        $url = 'https://api.themoviedb.org/3/discover/movie?' . $query;

        $data = tmdbRequest($url, $tmdbToken);

        if (empty($data['results'])) {
            break;
        }

        foreach ($data['results'] as $movie) {
            $movies[] = $movie;

            if (count($movies) >= $limit) {
                break;
            }
        }

        if ($page >= ($data['total_pages'] ?? 1)) {
            break;
        }

        $page++;
    }

    if (isset($_GET['shuffle'])) {
        shuffle($movies);
    }
} catch (Exception $e) {
    die('<h2>TMDB Error</h2>' .
        '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}


// --------------------------------------------------
// HELPERS
// --------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
            newest TMDB movie results.
        </div>

        <a class="shuffle-link" href="?shuffle=1">
            🔀 Shuffle
        </a>

        <div class="trailer-grid">

            <?php foreach ($movies as $movie): ?>

                <?php
                $poster = posterUrl($movie['poster_path'] ?? null);
                $tmdbUrl = 'https://www.themoviedb.org/movie/' . urlencode($movie['id']);
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
                            <?= e($movie['release_date'] ?? 'Unknown') ?>

                            <br>

                            TMDB rating:
                            <?= e(
                                isset($movie['vote_average'])
                                    ? number_format((float)$movie['vote_average'], 1)
                                    : 'N/A'
                            ) ?>

                        </div>

                        <div class="overview">
                            <?= e($movie['overview'] ?? 'No overview available.') ?>
                        </div>

                        <div class="actions">

                            <a
                                href="tmdb-trailer.php?id=<?= urlencode($movie['id']) ?>"
                                target="_blank"
                                rel="noopener">
                                ▶ Watch Trailer
                            </a>

                        </div>

                        <div class="tmdb-id">
                            TMDB ID: <?= e((string)$movie['id']) ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>