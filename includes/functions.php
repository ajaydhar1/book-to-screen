<?php

declare(strict_types=1);

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);

    if ($date === false) {
        return $datetime;
    }

    return $date->format('M j, Y, g:i A');
}

function h(string|null $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function barnes_and_noble_search_url(?string $bookTitle, ?string $bookAuthor): ?string
{
    $query = trim(($bookTitle ?? '') . ' ' . ($bookAuthor ?? ''));

    if ($query === '') {
        return null;
    }

    return 'https://www.barnesandnoble.com/search?q=' . urlencode($query);
}

function youtube_soundtrack_search_url(?string $movieTitle, ?int $releaseYear = null): ?string
{
    $title = trim($movieTitle ?? '');

    if ($title === '') {
        return null;
    }

    $query = $releaseYear !== null
        ? sprintf('"%s" %d soundtrack', $title, $releaseYear)
        : sprintf('"%s" soundtrack', $title);

    return 'https://www.youtube.com/results?search_query=' . urlencode($query);
}

function status_class(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'ignored' => 'status-ignored',
        'flagged' => 'status-flagged',
        default => 'status-default',
    };
}

function filter_url(string $status, string $researcher = 'all', string $search = ''): string
{
    $params = ['status' => $status];

    if ($researcher !== 'all') {
        $params['researcher'] = $researcher;
    }

    if ($search !== '') {
        $params['search'] = $search;
    }

    return '?' . http_build_query($params);
}

function formatDate(string|null $value): string
{
    if (!$value) {
        return 'Unknown';
    }

    try {
        $date = new DateTime($value);
        return $date->format('M j, Y, g:i A');
    } catch (Exception) {
        return h($value);
    }
}

/**
 * Extracts a 4-digit year from a YYYY-MM-DD date string. Kept local to
 * avoid a cross-feature dependency on includes/acclaimed-matching.php.
 */
function release_year_from_date(?string $releaseDate): ?int
{
    if ($releaseDate === null || !preg_match('/^(\d{4})-\d{2}-\d{2}$/', $releaseDate, $matches)) {
        return null;
    }

    return (int) $matches[1];
}

/**
 * Builds a per-author adaptation profile (median/newest release year and
 * trailer-available count) from tmdb_adaptations, keyed by source_author.
 * Reads the whole table in one query to avoid N+1 lookups.
 */
function author_adaptation_profiles(Database $db): array
{
    $stmt = $db->query("
        SELECT
            source_author,
            release_date,
            trailer_youtube_key
        FROM tmdb_adaptations
        WHERE source_author IS NOT NULL
          AND source_author <> ''
    ");

    $profiles = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $author = trim((string) $row['source_author']);

        if ($author === '') {
            continue;
        }

        $profiles[$author] ??= [
            'author' => $author,
            'years' => [],
            'trailer_count' => 0,
        ];

        $year = release_year_from_date($row['release_date'] ?? null);

        if ($year !== null) {
            $profiles[$author]['years'][] = $year;
        }

        if (!empty($row['trailer_youtube_key'])) {
            $profiles[$author]['trailer_count']++;
        }
    }

    foreach ($profiles as &$profile) {
        $profile['median_year'] = author_adaptation_median_year($profile['years']);
        $profile['newest_year'] = $profile['years'] === [] ? null : max($profile['years']);
    }
    unset($profile);

    return $profiles;
}

function author_adaptation_median_year(array $years): ?float
{
    if ($years === []) {
        return null;
    }

    sort($years);

    $count = count($years);
    $middle = intdiv($count, 2);

    if ($count % 2 === 1) {
        return (float) $years[$middle];
    }

    return ($years[$middle - 1] + $years[$middle]) / 2;
}

/**
 * Buckets a trailer-available adaptation count into a catalog-size band,
 * used as a secondary (lightly-weighted) signal alongside era closeness.
 */
function author_catalog_band(int $trailerCount): int
{
    return match (true) {
        $trailerCount <= 5 => 1,
        $trailerCount <= 10 => 2,
        $trailerCount <= 20 => 3,
        default => 4,
    };
}

/**
 * Recommends authors with a similar screen-adaptation era (not literary
 * similarity), based on median/newest adaptation release year, with a
 * modest preference for a similarly sized trailer-available catalog.
 */
function author_discovery_recommendations(array $profiles, string $currentAuthor, int $limit = 4): array
{
    $current = $profiles[$currentAuthor] ?? null;

    if (
        $current === null
        || $current['median_year'] === null
        || $current['newest_year'] === null
    ) {
        return [];
    }

    $currentBand = author_catalog_band($current['trailer_count']);

    $candidates = [];

    foreach ($profiles as $author => $profile) {
        if (
            $author === $currentAuthor
            || $profile['trailer_count'] < 3
            || $profile['median_year'] === null
            || $profile['newest_year'] === null
        ) {
            continue;
        }

        $eraScore = (abs($profile['median_year'] - $current['median_year']) * 2)
            + abs($profile['newest_year'] - $current['newest_year']);

        $catalogBandPenalty = abs($currentBand - author_catalog_band($profile['trailer_count'])) * 4;

        $candidates[] = [
            'author' => $author,
            'trailer_count' => $profile['trailer_count'],
            'score' => $eraScore + $catalogBandPenalty,
        ];
    }

    usort($candidates, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $a['score'] <=> $b['score'];
        }

        if ($a['trailer_count'] !== $b['trailer_count']) {
            return $b['trailer_count'] <=> $a['trailer_count'];
        }

        return strcasecmp($a['author'], $b['author']);
    });

    return array_slice($candidates, 0, $limit);
}