<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();

$stmt = $db->query("
    SELECT
        tmdb_id,
        title,
        release_date,
        poster_path,
        source_author,
        trailer_youtube_key
    FROM tmdb_adaptations
    WHERE trailer_youtube_key IS NOT NULL
      AND trailer_youtube_key <> ''
    ORDER BY RANDOM()
    LIMIT 1
");

$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    http_response_code(404);

    echo json_encode([
        'error' => 'No trailer available.',
    ]);

    exit;
}

echo json_encode([
    'tmdb_id' => (int) $movie['tmdb_id'],
    'title' => $movie['title'],
    'release_date' => $movie['release_date'],
    'poster_path' => $movie['poster_path'],
    'source_author' => $movie['source_author'],
    'youtube_key' => $movie['trailer_youtube_key'],
]);