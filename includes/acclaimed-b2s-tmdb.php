<?php

declare(strict_types=1);

require_once __DIR__ . '/acclaimed-matching.php';
require_once __DIR__ . '/tmdb-api.php';

const B2S_LOOKUP_STATUSES = ['HIGH_CONFIDENCE', 'REVIEW', 'NOT_FOUND'];

const B2S_APPROVED_TMDB_OVERRIDES = [
    29 => 329865,
    48 => 8416,
    53 => 488,
    58 => 935,
    79 => 16372,
];

function b2s_tmdb_normalize_title(string $title): string
{
    $title = acclaimed_normalize_title($title);
    $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? $title;

    return trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
}

function b2s_tmdb_result_matches_item(array $result, array $item): bool
{
    $itemYear = acclaimed_item_year($item);
    $resultYear = acclaimed_release_year($result['release_date'] ?? null);

    if ($itemYear === null || $resultYear !== $itemYear) {
        return false;
    }

    $normalizedItemTitle = b2s_tmdb_normalize_title((string) $item['title']);

    foreach (['title', 'original_title'] as $field) {
        if (
            isset($result[$field])
            && b2s_tmdb_normalize_title((string) $result[$field]) === $normalizedItemTitle
        ) {
            return true;
        }
    }

    return false;
}

function b2s_tmdb_movie_details(int $tmdbId): array
{
    return tmdb_api_request('movie/' . $tmdbId, [
        'language' => 'en-US',
        'append_to_response' => 'videos',
    ]);
}

function b2s_tmdb_lookup_item(array $item, array $existingTmdbIds): array
{
    try {
        $search = tmdb_api_request('search/movie', [
            'query' => $item['title'],
            'year' => $item['year'],
            'language' => 'en-US',
            'include_adult' => 'false',
        ]);
    } catch (Throwable $error) {
        return [
            'item' => $item,
            'status' => 'REVIEW',
            'candidates' => [],
            'proposed' => null,
            'trailer' => null,
            'note' => 'TMDb search failed: ' . $error->getMessage(),
        ];
    }

    $searchResults = array_values(array_filter(
        $search['results'] ?? [],
        static fn(mixed $result): bool => is_array($result)
    ));
    $exactCandidates = array_values(array_filter(
        $searchResults,
        static fn(array $result): bool => b2s_tmdb_result_matches_item($result, $item)
    ));

    if ($exactCandidates === []) {
        return [
            'item' => $item,
            'status' => $searchResults === [] ? 'NOT_FOUND' : 'REVIEW',
            'candidates' => array_slice($searchResults, 0, 5),
            'proposed' => null,
            'trailer' => null,
            'note' => $searchResults === []
                ? 'TMDb returned no movie search results for the curated title and year.'
                : 'TMDb returned candidates, but none safely matched both the curated title and release year.',
        ];
    }

    if (count($exactCandidates) > 1) {
        return [
            'item' => $item,
            'status' => 'REVIEW',
            'candidates' => $exactCandidates,
            'proposed' => null,
            'trailer' => null,
            'note' => 'More than one TMDb search result matched the curated title and release year.',
        ];
    }

    $candidate = $exactCandidates[0];
    $tmdbId = isset($candidate['id']) ? (int) $candidate['id'] : 0;

    if ($tmdbId <= 0) {
        return [
            'item' => $item,
            'status' => 'REVIEW',
            'candidates' => [$candidate],
            'proposed' => null,
            'trailer' => null,
            'note' => 'TMDb returned a matching result without a usable movie ID.',
        ];
    }

    try {
        $details = b2s_tmdb_movie_details($tmdbId);
    } catch (Throwable $error) {
        return [
            'item' => $item,
            'status' => 'REVIEW',
            'candidates' => [$candidate],
            'proposed' => $candidate,
            'trailer' => null,
            'note' => 'TMDb detail lookup failed: ' . $error->getMessage(),
        ];
    }

    $trailer = tmdb_find_trailer_youtube_video($details['videos'] ?? []);
    $alreadyStored = isset($existingTmdbIds[$tmdbId]);
    $detailsMatch = b2s_tmdb_result_matches_item($details, $item);

    return [
        'item' => $item,
        'status' => $detailsMatch && !$alreadyStored ? 'HIGH_CONFIDENCE' : 'REVIEW',
        'candidates' => [$details],
        'proposed' => $details,
        'trailer' => $trailer,
        'note' => $alreadyStored
            ? 'This TMDb ID already exists in tmdb_adaptations and cannot be proposed as missing.'
            : ($detailsMatch ? null : 'TMDb detail data did not confirm the curated title and release year.'),
    ];
}

function b2s_tmdb_approved_lookup_item(array $item, int $tmdbId, array $existingTmdbIds): array
{
    try {
        $details = b2s_tmdb_movie_details($tmdbId);
    } catch (Throwable $error) {
        return [
            'item' => $item,
            'status' => 'REVIEW',
            'candidates' => [],
            'proposed' => null,
            'trailer' => null,
            'note' => 'Approved TMDb detail lookup failed: ' . $error->getMessage(),
        ];
    }

    $returnedId = isset($details['id']) ? (int) $details['id'] : 0;

    return [
        'item' => $item,
        'status' => $returnedId === $tmdbId && !isset($existingTmdbIds[$tmdbId])
            ? 'HIGH_CONFIDENCE'
            : 'REVIEW',
        'candidates' => [$details],
        'proposed' => $details,
        'trailer' => tmdb_find_trailer_youtube_video($details['videos'] ?? []),
        'note' => $returnedId !== $tmdbId
            ? 'TMDb detail response did not return the approved TMDb ID.'
            : (isset($existingTmdbIds[$tmdbId])
                ? 'This approved TMDb ID already exists in tmdb_adaptations.'
                : 'Manually approved B2S-to-TMDb identity override.'),
    ];
}

function b2s_tmdb_build_approved_insertion_set(array $unmatchedItems, array $existingTmdbIds): array
{
    $lookups = [];

    foreach ($unmatchedItems as $item) {
        $rank = (int) ($item['rank'] ?? 0);

        if (isset(B2S_APPROVED_TMDB_OVERRIDES[$rank])) {
            $lookups[] = b2s_tmdb_approved_lookup_item(
                $item,
                B2S_APPROVED_TMDB_OVERRIDES[$rank],
                $existingTmdbIds
            );
        } else {
            $lookups[] = b2s_tmdb_lookup_item($item, $existingTmdbIds);
        }

        usleep(100000);
    }

    return $lookups;
}