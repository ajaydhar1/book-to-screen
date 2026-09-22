<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function tmdb_api_request(string $path, array $parameters = []): array
{
    if (!defined('TMDB_READ_TOKEN') || TMDB_READ_TOKEN === '') {
        throw new RuntimeException('TMDB_READ_TOKEN is not configured.');
    }

    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/');

    if ($parameters !== []) {
        $url .= '?' . http_build_query($parameters);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . TMDB_READ_TOKEN,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('TMDB cURL error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('TMDB returned invalid JSON.');
    }

    if ($httpCode !== 200) {
        $message = $data['status_message'] ?? 'Unknown TMDB error';
        throw new RuntimeException("TMDB returned HTTP {$httpCode}: {$message}");
    }

    return $data;
}

function tmdb_find_trailer_youtube_video(array $videos): ?array
{
    $results = $videos['results'] ?? [];

    if (!is_array($results)) {
        return null;
    }

    $isYouTubeTrailer = static function (array $video): bool {
        return ($video['site'] ?? '') === 'YouTube'
            && ($video['type'] ?? '') === 'Trailer'
            && !empty($video['key']);
    };

    $preferences = [
        static fn(array $video): bool => !empty($video['official'])
            && ($video['iso_639_1'] ?? '') === 'en'
            && ($video['iso_3166_1'] ?? '') === 'US',
        static fn(array $video): bool => !empty($video['official'])
            && ($video['iso_639_1'] ?? '') === 'en',
        static fn(array $video): bool => !empty($video['official']),
        static fn(array $video): bool => true,
    ];

    foreach ($preferences as $preference) {
        foreach ($results as $video) {
            if (is_array($video) && $isYouTubeTrailer($video) && $preference($video)) {
                return $video;
            }
        }
    }

    return null;
}