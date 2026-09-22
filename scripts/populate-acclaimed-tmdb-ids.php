<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/acclaimed-matching.php';

const ACCLAIMED_DATASET_PATH = __DIR__ . '/../includes/acclaimed-datasets.php';

const ACADEMY_APPROVED_YEAR_OVERRIDES = [
    'Ben-Hur' => 1959,
    'From Here to Eternity' => 1953,
    'All the King’s Men' => 1949,
];

function acclaimed_static_fail(string $message): never
{
    throw new RuntimeException($message);
}

function acclaimed_find_single_year_candidate(
    array $titleIndex,
    string $title,
    int $year
): ?array {
    $candidates = $titleIndex[acclaimed_normalize_title($title)] ?? [];
    $yearCandidates = array_values(array_filter(
        $candidates,
        static fn(array $candidate): bool => acclaimed_release_year($candidate['release_date'] ?? null) === $year
    ));

    return count($yearCandidates) === 1 ? $yearCandidates[0] : null;
}

function acclaimed_static_collection_matches(
    string $collectionKey,
    array $collection,
    array $titleIndex,
    array $titleYearIndex,
    array $tmdbIdIndex
): array {
    $report = acclaimed_build_collection_report(
        $collectionKey,
        $collection,
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );
    $matches = [];
    $ambiguities = [];

    foreach ($report['matches'] as $itemIndex => $match) {
        if ($match['status'] === 'EXACT_TITLE_YEAR') {
            $matches[$itemIndex] = (int) $match['candidates'][0]['tmdb_id'];
        } elseif ($match['status'] === 'AMBIGUOUS') {
            $ambiguities[] = $match['item']['title'];
        }
    }

    return [$matches, $ambiguities];
}

function acclaimed_static_academy_matches(array $collection, array $titleIndex): array
{
    $matches = [];
    $ambiguities = [];

    foreach ($collection['items'] as $itemIndex => $item) {
        $title = (string) $item['title'];

        if (isset(ACADEMY_APPROVED_YEAR_OVERRIDES[$title])) {
            $candidate = acclaimed_find_single_year_candidate(
                $titleIndex,
                $title,
                ACADEMY_APPROVED_YEAR_OVERRIDES[$title]
            );

            if ($candidate === null) {
                acclaimed_static_fail("Could not resolve approved Academy title {$title}.");
            }

            $matches[$itemIndex] = (int) $candidate['tmdb_id'];
            continue;
        }

        $candidates = $titleIndex[acclaimed_normalize_title($title)] ?? [];

        if (count($candidates) === 1) {
            $matches[$itemIndex] = (int) $candidates[0]['tmdb_id'];
        } elseif (count($candidates) > 1) {
            $ambiguities[] = $title;
        }
    }

    return [$matches, $ambiguities];
}

function acclaimed_static_b2s_matches(
    array $collection,
    array $titleIndex,
    array $titleYearIndex,
    array $tmdbIdIndex
): array {
    $report = acclaimed_build_collection_report(
        'b2s-100',
        $collection,
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );
    $matches = [];

    foreach ($report['matches'] as $itemIndex => $match) {
        if (count($match['candidates']) !== 1) {
            acclaimed_static_fail('B2S contains a non-deterministic match for ' . $match['item']['title'] . '.');
        }

        $matches[$itemIndex] = (int) $match['candidates'][0]['tmdb_id'];
    }

    if (count($matches) !== 100) {
        acclaimed_static_fail('B2S did not produce exactly 100 deterministic IDs.');
    }

    return $matches;
}

function acclaimed_static_apply_ids(string $source, array $collectionIds): string
{
    $lines = preg_split('/(\r\n|\n|\r)/', $source);

    if ($lines === false) {
        acclaimed_static_fail('Could not read the dataset source lines.');
    }

    $currentCollection = null;
    $insideItems = false;
    $itemIndex = -1;
    $replacements = 0;

    foreach ($lines as $lineIndex => $line) {
        if (preg_match("/^    '([a-z0-9-]+)' => \[$/", $line, $collectionMatch)) {
            $currentCollection = $collectionMatch[1];
            $insideItems = false;
            $itemIndex = -1;
            continue;
        }

        if ($currentCollection !== null && trim($line) === "'items' => [") {
            $insideItems = true;
            continue;
        }

        if ($insideItems && $line === '        ],') {
            $insideItems = false;
            continue;
        }

        if ($insideItems && $line === '            [') {
            $itemIndex++;
            continue;
        }

        if (
            $insideItems
            && isset($collectionIds[$currentCollection][$itemIndex])
            && preg_match("/^(\s*'tmdb_id' => )null(,\s*)$/", $line, $tmdbMatch)
        ) {
            $lines[$lineIndex] = $tmdbMatch[1] . $collectionIds[$currentCollection][$itemIndex] . $tmdbMatch[2];
            $replacements++;
        }
    }

    $expectedReplacements = array_sum(array_map('count', $collectionIds));

    if ($replacements !== $expectedReplacements) {
        acclaimed_static_fail("Expected {$expectedReplacements} dataset replacements; made {$replacements}.");
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function acclaimed_static_collection_summary(array $collection, array $ids, array $ambiguities): array
{
    return [
        'total' => count($collection['items']),
        'ids_populated' => count($ids),
        'ids_null' => count($collection['items']) - count($ids),
        'ambiguous' => $ambiguities,
    ];
}

$apply = in_array('--apply', $argv ?? [], true);

try {
    $datasets = require ACCLAIMED_DATASET_PATH;
    $db = get_db();

    // The only database operation in Phase 2D is this read-only catalog query.
    $tmdbRows = $db->query('
        SELECT tmdb_id, title, release_date
        FROM tmdb_adaptations
    ')->fetchAll(PDO::FETCH_ASSOC);

    if (count($tmdbRows) !== 2827) {
        acclaimed_static_fail('Expected 2,827 TMDb records; found ' . count($tmdbRows) . '.');
    }

    [$titleIndex, $titleYearIndex, $tmdbIdIndex] = acclaimed_build_tmdb_indexes($tmdbRows);
    $collectionIds = [];
    $summaries = [];

    foreach (['afi-100', 'sight-sound-100', 'national-film-registry'] as $collectionKey) {
        [$collectionIds[$collectionKey], $ambiguities] = acclaimed_static_collection_matches(
            $collectionKey,
            $datasets[$collectionKey],
            $titleIndex,
            $titleYearIndex,
            $tmdbIdIndex
        );
        $summaries[$collectionKey] = acclaimed_static_collection_summary(
            $datasets[$collectionKey],
            $collectionIds[$collectionKey],
            $ambiguities
        );
    }

    [$collectionIds['academy-best-picture'], $academyAmbiguities] = acclaimed_static_academy_matches(
        $datasets['academy-best-picture'],
        $titleIndex
    );
    $summaries['academy-best-picture'] = acclaimed_static_collection_summary(
        $datasets['academy-best-picture'],
        $collectionIds['academy-best-picture'],
        $academyAmbiguities
    );

    $collectionIds['b2s-100'] = acclaimed_static_b2s_matches(
        $datasets['b2s-100'],
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );
    $summaries['b2s-100'] = acclaimed_static_collection_summary(
        $datasets['b2s-100'],
        $collectionIds['b2s-100'],
        []
    );

    foreach (['pulitzer-fiction', 'booker-prize'] as $collectionKey) {
        $collectionIds[$collectionKey] = [];
        $summaries[$collectionKey] = acclaimed_static_collection_summary(
            $datasets[$collectionKey],
            [],
            []
        );
    }

    $source = file_get_contents(ACCLAIMED_DATASET_PATH);

    if ($source === false) {
        acclaimed_static_fail('Could not read the static dataset source.');
    }

    if ($apply) {
        $updatedSource = acclaimed_static_apply_ids($source, $collectionIds);
        file_put_contents(ACCLAIMED_DATASET_PATH, $updatedSource);
    }

    echo json_encode([
        'mode' => $apply ? 'applied' : 'dry-run',
        'database_rows' => count($tmdbRows),
        'summaries' => $summaries,
        'academy_overrides' => array_map(
            static fn(string $title, int $year): array => [
                'title' => $title,
                'year' => $year,
                'tmdb_id' => (int) $collectionIds['academy-best-picture'][array_search(
                    $title,
                    array_column($datasets['academy-best-picture']['items'], 'title'),
                    true
                )],
            ],
            array_keys(ACADEMY_APPROVED_YEAR_OVERRIDES),
            ACADEMY_APPROVED_YEAR_OVERRIDES
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Acclaimed TMDb ID update failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}