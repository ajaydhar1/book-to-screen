<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function video_feed_sources(): array
{
    return [
        [
            'kind' => 'b2s',
            'slug' => 'b2s-trailers',
            'label' => 'B2S Trailers',
            'enabled' => true,
        ],
        [
            'kind' => 'youtube_playlist',
            'slug' => 'entertainment-tonight',
            'label' => 'Entertainment Tonight',
            'playlist_id' => 'PLQwITQ__CeH2Y_7g2xeiNDa0vQsROQQgv',
            'enabled' => false,
        ],
        [
            'kind' => 'youtube_playlist',
            'slug' => 'rotten-tomatoes',
            'label' => 'Rotten Tomatoes',
            'playlist_id' => 'PLScC8g4bqD47c-qHlsfhGH3j6Bg7jzFy-',
            'enabled' => false,
        ],
    ];
}

function video_feed_enabled_sources(): array
{
    return array_values(
        array_filter(
            video_feed_sources(),
            static fn (array $source): bool => !empty($source['enabled'])
        )
    );
}

function video_feed_source_by_slug(?string $slug): ?array
{
    foreach (video_feed_sources() as $source) {
        if (($source['slug'] ?? null) === $slug) {
            return $source;
        }
    }

    return null;
}

function video_feed_release_year(?string $releaseDate): ?int
{
    if ($releaseDate === null || trim($releaseDate) === '') {
        return null;
    }

    if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', trim($releaseDate), $matches) === 1) {
        return (int) $matches[1];
    }

    if (preg_match('/^(\d{4})$/', trim($releaseDate), $matches) === 1) {
        return (int) $matches[1];
    }

    return null;
}

function video_feed_b2s_items(array $seenIds = [], int $limit = 12): array
{
    require_once __DIR__ . '/db.php';

    $db = get_db();

    $statement = $db->query(
        "
        SELECT
            tmdb_id,
            title,
            overview,
            release_date,
            poster_path,
            source_author,
            trailer_youtube_key,
            vote_average,
            vote_count,
            popularity
        FROM tmdb_adaptations
        WHERE trailer_youtube_key IS NOT NULL
          AND TRIM(trailer_youtube_key) <> ''
        ORDER BY popularity DESC, vote_average DESC, release_date DESC, tmdb_id DESC
        "
    );

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $seenSet = [];

    foreach ($seenIds as $seenId) {
        $id = (int) $seenId;

        if ($id > 0) {
            $seenSet[$id] = true;
        }
    }

    $filtered = [];

    foreach ($rows as $row) {
        $id = (int) ($row['tmdb_id'] ?? 0);

        if ($id <= 0 || isset($seenSet[$id])) {
            continue;
        }

        $trailerKey = trim((string) ($row['trailer_youtube_key'] ?? ''));

        if ($trailerKey === '') {
            continue;
        }

        $filtered[] = [
            'tmdb_id' => $id,
            'title' => trim((string) ($row['title'] ?? '')),
            'overview' => trim((string) ($row['overview'] ?? '')),
            'release_date' => (string) ($row['release_date'] ?? ''),
            'release_year' => video_feed_release_year((string) ($row['release_date'] ?? '')),
            'poster_path' => trim((string) ($row['poster_path'] ?? '')),
            'source_author' => trim((string) ($row['source_author'] ?? '')),
            'trailer_youtube_key' => $trailerKey,
            'vote_average' => (float) ($row['vote_average'] ?? 0.0),
            'vote_count' => (int) ($row['vote_count'] ?? 0),
            'popularity' => (float) ($row['popularity'] ?? 0.0),
            'book_title' => '',
        ];
    }

    if ($filtered === []) {
        return [];
    }

    $ranked = [];

    foreach ($filtered as $index => $row) {
        $score = 0;
        $score += min((int) round((float) ($row['vote_average'] ?? 0) * 8), 60);
        $score += min((int) round((float) ($row['popularity'] ?? 0) / 6), 24);
        $score += random_int(0, 18);

        $ranked[] = ['item' => $row, 'score' => $score, 'index' => $index];
    }

    usort(
        $ranked,
        static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        }
    );

    $ordered = [];
    $historyAuthors = [];
    $historyYears = [];

    foreach ($ranked as $entry) {
        $item = $entry['item'];
        $author = strtolower(trim((string) ($item['source_author'] ?? '')));
        $year = $item['release_year'];
        $score = $entry['score'];

        if ($author !== '' && in_array($author, $historyAuthors, true)) {
            $score -= 18;
        }

        if ($author !== '' && in_array($author, array_slice($historyAuthors, -2), true)) {
            $score -= 10;
        }

        if ($year !== null && in_array($year, $historyYears, true)) {
            $score -= 12;
        }

        if ($year !== null && in_array($year, array_slice($historyYears, -2), true)) {
            $score -= 8;
        }

        $item['diversity_score'] = $score;
        $ordered[] = $item;

        if ($author !== '') {
            $historyAuthors[] = $author;
        }

        if ($year !== null) {
            $historyYears[] = $year;
        }

        if (count($historyAuthors) > 3) {
            array_shift($historyAuthors);
        }

        if (count($historyYears) > 3) {
            array_shift($historyYears);
        }
    }

    usort(
        $ordered,
        static function (array $left, array $right): int {
            return ($right['diversity_score'] ?? 0) <=> ($left['diversity_score'] ?? 0);
        }
    );

    $result = [];

    foreach (array_slice($ordered, 0, max(1, (int) $limit)) as $item) {
        $bookUrl = barnes_and_noble_search_url(
            $item['title'] ?? null,
            $item['source_author'] ?? null
        );

        $soundtrackUrl = youtube_soundtrack_search_url(
            $item['title'] ?? null,
            $item['release_year'] ?? null
        );

        $authorUrl = !empty($item['source_author'])
            ? '/trailers.php?author=' . urlencode($item['source_author'])
            : null;

        $posterUrl = !empty($item['poster_path'])
            ? 'https://image.tmdb.org/t/p/w780' . $item['poster_path']
            : null;

        $result[] = [
            'tmdb_id' => (int) $item['tmdb_id'],
            'title' => $item['title'],
            'overview' => $item['overview'],
            'release_date' => $item['release_date'],
            'release_year' => $item['release_year'],
            'source_author' => $item['source_author'],
            'book_title' => $item['book_title'],
            'trailer_youtube_key' => $item['trailer_youtube_key'],
            'poster_url' => $posterUrl,
            'book_url' => $bookUrl,
            'soundtrack_url' => $soundtrackUrl,
            'author_url' => $authorUrl,
        ];
    }

    return $result;
}
