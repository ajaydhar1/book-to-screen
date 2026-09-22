<?php

declare(strict_types=1);

const ACCLAIMED_MATCH_STATUSES = [
    'EXACT_TITLE_YEAR',
    'UNIQUE_TITLE',
    'AMBIGUOUS',
    'UNMATCHED',
];

const ACCLAIMED_LITERARY_COLLECTIONS = [
    'pulitzer-fiction',
    'booker-prize',
];

const ACCLAIMED_APPROVED_ITEM_OVERRIDES = [
    'b2s-100' => [
        29 => 329865,
        48 => 8416,
        53 => 488,
        58 => 935,
        79 => 16372,
    ],
];

function acclaimed_normalize_title(string $title): string
{
    $title = trim($title);
    $title = strtr($title, [
        "\u{2018}" => "'",
        "\u{2019}" => "'",
        "\u{201A}" => "'",
        "\u{201B}" => "'",
        "\u{201C}" => '"',
        "\u{201D}" => '"',
        "\u{201E}" => '"',
        "\u{201F}" => '"',
        "\u{00A0}" => ' ',
    ]);
    $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

    return function_exists('mb_strtolower')
        ? mb_strtolower($title, 'UTF-8')
        : strtolower($title);
}

function acclaimed_release_year(?string $releaseDate): ?int
{
    if ($releaseDate === null || !preg_match('/^(\d{4})-\d{2}-\d{2}$/', $releaseDate, $matches)) {
        return null;
    }

    return (int) $matches[1];
}

function acclaimed_item_year(array $item): ?int
{
    return isset($item['year']) && is_int($item['year']) ? $item['year'] : null;
}

function acclaimed_build_tmdb_indexes(array $tmdbRows): array
{
    $titleIndex = [];
    $titleYearIndex = [];
    $tmdbIdIndex = [];

    foreach ($tmdbRows as $tmdbRow) {
        $tmdbId = isset($tmdbRow['tmdb_id']) ? (int) $tmdbRow['tmdb_id'] : 0;

        if ($tmdbId > 0) {
            $tmdbIdIndex[$tmdbId] = $tmdbRow;
        }

        $normalizedTitle = acclaimed_normalize_title((string) $tmdbRow['title']);

        if ($normalizedTitle === '') {
            continue;
        }

        $titleIndex[$normalizedTitle][] = $tmdbRow;
        $releaseYear = acclaimed_release_year($tmdbRow['release_date'] ?? null);

        if ($releaseYear !== null) {
            $titleYearIndex[$normalizedTitle . '|' . $releaseYear][] = $tmdbRow;
        }
    }

    return [$titleIndex, $titleYearIndex, $tmdbIdIndex];
}

function acclaimed_build_collection_report(
    string $collectionKey,
    array $collection,
    array $titleIndex,
    array $titleYearIndex,
    array $tmdbIdIndex = []
): array {
    $collectionCounts = array_fill_keys(ACCLAIMED_MATCH_STATUSES, 0);
    $matches = [];
    $isLiteraryCollection = in_array($collectionKey, ACCLAIMED_LITERARY_COLLECTIONS, true);

    foreach ($collection['items'] as $item) {
        $normalizedTitle = acclaimed_normalize_title((string) $item['title']);
        $candidates = [];
        $status = 'UNMATCHED';
        $note = null;
        $itemYear = acclaimed_item_year($item);

        if ($isLiteraryCollection) {
            $note = 'No source/book-title field exists in tmdb_adaptations; no automatic match was proposed.';
        } else {
            $candidates = $titleIndex[$normalizedTitle] ?? [];
            $yearCandidates = $itemYear === null
                ? []
                : ($titleYearIndex[$normalizedTitle . '|' . $itemYear] ?? []);

            if (count($yearCandidates) === 1) {
                $status = 'EXACT_TITLE_YEAR';
                $candidates = $yearCandidates;
            } elseif (count($yearCandidates) > 1) {
                $status = 'AMBIGUOUS';
                $candidates = $yearCandidates;
                $note = 'More than one TMDb record has this normalized title and release year.';
            } elseif (count($candidates) === 1) {
                $status = 'UNIQUE_TITLE';
            } elseif (count($candidates) > 1) {
                $status = 'AMBIGUOUS';
                $note = 'More than one TMDb record has this normalized title.';
            }

            $itemRank = isset($item['rank']) ? (int) $item['rank'] : 0;
            $approvedTmdbId = ACCLAIMED_APPROVED_ITEM_OVERRIDES[$collectionKey][$itemRank] ?? null;

            if ($status === 'UNMATCHED' && $approvedTmdbId !== null && isset($tmdbIdIndex[$approvedTmdbId])) {
                $status = 'UNIQUE_TITLE';
                $candidates = [$tmdbIdIndex[$approvedTmdbId]];
                $note = 'Approved B2S identity override.';
            }
        }

        $collectionCounts[$status]++;
        $matches[] = [
            'item' => $item,
            'status' => $status,
            'candidates' => $candidates,
            'note' => $note,
        ];
    }

    return [
        'collection' => $collection,
        'counts' => $collectionCounts,
        'matches' => $matches,
    ];
}