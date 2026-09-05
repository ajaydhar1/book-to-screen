<?php

require_once __DIR__ . '/includes/config.local.php';

$tmdbToken = TMDB_READ_TOKEN;

if (!$tmdbToken) {
    die('TMDB_READ_TOKEN is not configured.');
}

$movieId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$movieId) {
    die('Missing TMDB movie ID.');
}

$url = 'https://api.themoviedb.org/3/movie/' . $movieId . '/videos?language=en-US';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $tmdbToken,
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    die('TMDB cURL error: ' . htmlspecialchars($error));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $message = $data['status_message'] ?? 'Unknown TMDB error';
    die('TMDB error: ' . htmlspecialchars($message));
}

$videos = $data['results'] ?? [];

// --------------------------------------------------
// FIND BEST TRAILER
// --------------------------------------------------

$trailer = null;

// First preference:
// Official YouTube Trailer
foreach ($videos as $video) {

    if (
        ($video['site'] ?? '') === 'YouTube' &&
        ($video['type'] ?? '') === 'Trailer' &&
        !empty($video['official'])
    ) {
        $trailer = $video;
        break;
    }
}

// Second preference:
// Any YouTube Trailer
if (!$trailer) {

    foreach ($videos as $video) {

        if (
            ($video['site'] ?? '') === 'YouTube' &&
            ($video['type'] ?? '') === 'Trailer'
        ) {
            $trailer = $video;
            break;
        }
    }
}

// Third preference:
// Official YouTube Teaser
if (!$trailer) {

    foreach ($videos as $video) {

        if (
            ($video['site'] ?? '') === 'YouTube' &&
            ($video['type'] ?? '') === 'Teaser' &&
            !empty($video['official'])
        ) {
            $trailer = $video;
            break;
        }
    }
}

// --------------------------------------------------
// REDIRECT
// --------------------------------------------------

if ($trailer && !empty($trailer['key'])) {

    $youtubeUrl =
        'https://www.youtube.com/watch?v=' .
        urlencode($trailer['key']);

    header('Location: ' . $youtubeUrl);
    exit;
}


// --------------------------------------------------
// FALLBACK
// --------------------------------------------------

$tmdbUrl =
    'https://www.themoviedb.org/movie/' .
    urlencode((string) $movieId);

header('Location: ' . $tmdbUrl);
exit;