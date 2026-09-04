<?php

/**
 * tmdb_818_poc.php
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
require_once __DIR__ . '/../includes/config.local.php';

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
    <meta charset="UTF-8">

    <title>TMDB Book Adaptations POC</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f6f7f9;
            color: #222;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        h1 {
            margin-bottom: 5px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .stats {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px 18px;
            margin-bottom: 25px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            overflow: hidden;
        }

        .poster {
            width: 100%;
            aspect-ratio: 2 / 3;
            object-fit: cover;
            background: #eee;
        }

        .no-poster {
            width: 100%;
            aspect-ratio: 2 / 3;
            background: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #888;
        }

        .card-body {
            padding: 15px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .overview {
            font-size: 14px;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .actions {
            margin-top: 15px;
        }

        .actions a {
            display: inline-block;
            padding: 8px 12px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }

        .tmdb-id {
            margin-top: 10px;
            font-size: 11px;
            color: #999;
        }

        .shuffle-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 14px;
            margin-bottom: 24px;
            border: 1px solid #d7c7b2;
            border-radius: 999px;
            background: #fff;
            color: #2b2118;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .shuffle-link:hover {
            background: #2b2118;
            border-color: #2b2118;
            color: #fff;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            color: #7a5c3e;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .back-link:hover {
            color: #2b2118;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="container">

        <a class="back-link" href="/admin/leads.php">
            ← Back to Leads
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

        <div class="grid">

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

</body>

</html>