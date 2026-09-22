<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/acclaimed-matching.php';
require_once __DIR__ . '/../includes/acclaimed-b2s-tmdb.php';

const B2S_EXPECTED_INSERT_COUNT = 41;
const B2S_EXPECTED_SOURCE_COUNT = 100;

function b2s_import_fail(string $message): never
{
    throw new RuntimeException($message);
}

function b2s_import_required_string(array $movie, string $field): string
{
    $value = trim((string) ($movie[$field] ?? ''));

    if ($value === '') {
        b2s_import_fail("TMDb proposal is missing required {$field}.");
    }

    return $value;
}

function b2s_import_nullable_string(array $movie, string $field): ?string
{
    $value = trim((string) ($movie[$field] ?? ''));

    return $value === '' ? null : $value;
}

function b2s_import_value(array $movie, string $field, string $type): int|float|null
{
    if (!isset($movie[$field]) || $movie[$field] === '') {
        return null;
    }

    return $type === 'int' ? (int) $movie[$field] : (float) $movie[$field];
}

function b2s_import_build_records(array $lookups): array
{
    $records = [];

    foreach ($lookups as $lookup) {
        if ($lookup['status'] !== 'HIGH_CONFIDENCE' || !is_array($lookup['proposed'])) {
            b2s_import_fail('The approved B2S insertion set contains a non-approved proposal.');
        }

        $movie = $lookup['proposed'];
        $item = $lookup['item'];
        $tmdbId = isset($movie['id']) ? (int) $movie['id'] : 0;
        $sourceAuthor = trim((string) ($item['author'] ?? ''));

        if ($tmdbId <= 0) {
            b2s_import_fail('The approved B2S insertion set contains an invalid TMDb ID.');
        }

        if ($sourceAuthor === '') {
            b2s_import_fail("B2S #{$item['rank']} is missing its curated source author.");
        }

        $records[] = [
            'rank' => (int) $item['rank'],
            'tmdb_id' => $tmdbId,
            'tmdb_keyword_id' => 818,
            'title' => b2s_import_required_string($movie, 'title'),
            'original_title' => b2s_import_nullable_string($movie, 'original_title'),
            'overview' => b2s_import_nullable_string($movie, 'overview'),
            'release_date' => b2s_import_nullable_string($movie, 'release_date'),
            'poster_path' => b2s_import_nullable_string($movie, 'poster_path'),
            'backdrop_path' => b2s_import_nullable_string($movie, 'backdrop_path'),
            'original_language' => b2s_import_nullable_string($movie, 'original_language'),
            'vote_average' => b2s_import_value($movie, 'vote_average', 'float'),
            'vote_count' => b2s_import_value($movie, 'vote_count', 'int'),
            'popularity' => b2s_import_value($movie, 'popularity', 'float'),
            'source_author' => $sourceAuthor,
            'trailer_youtube_key' => isset($lookup['trailer']['key'])
                ? (string) $lookup['trailer']['key']
                : null,
        ];
    }

    return $records;
}

function b2s_import_validate_records(array $records, array $existingTmdbIds): void
{
    if (count($records) !== B2S_EXPECTED_INSERT_COUNT) {
        b2s_import_fail('Expected exactly 41 approved records; found ' . count($records) . '.');
    }

    $recordIds = array_column($records, 'tmdb_id');

    if (count($recordIds) !== count(array_unique($recordIds))) {
        b2s_import_fail('The approved insertion set contains duplicate TMDb IDs.');
    }

    $conflicts = array_values(array_filter(
        $recordIds,
        static fn(int $tmdbId): bool => isset($existingTmdbIds[$tmdbId])
    ));

    if ($conflicts !== []) {
        b2s_import_fail('Approved TMDb IDs already exist: ' . implode(', ', $conflicts) . '.');
    }

    foreach (B2S_APPROVED_TMDB_OVERRIDES as $rank => $tmdbId) {
        $record = array_values(array_filter(
            $records,
            static fn(array $record): bool => $record['rank'] === $rank
        ))[0] ?? null;

        if ($record === null || $record['tmdb_id'] !== $tmdbId) {
            b2s_import_fail("B2S #{$rank} does not resolve to approved TMDb ID {$tmdbId}.");
        }
    }
}

function b2s_import_verify(
    Database $db,
    array $records,
    int $expectedRowCount,
    array $datasets
): array {
    $rows = $db->query('
        SELECT
            tmdb_id,
            title,
            release_date,
            poster_path,
            source_author,
            trailer_youtube_key
        FROM tmdb_adaptations
    ')->fetchAll(PDO::FETCH_ASSOC);
    $rowCount = count($rows);

    if ($rowCount !== $expectedRowCount) {
        b2s_import_fail("Expected {$expectedRowCount} rows after insert; found {$rowCount}.");
    }

    $rowsByTmdbId = [];

    foreach ($rows as $row) {
        $tmdbId = (int) $row['tmdb_id'];

        if (isset($rowsByTmdbId[$tmdbId])) {
            b2s_import_fail("Duplicate TMDb ID {$tmdbId} exists in tmdb_adaptations.");
        }

        $rowsByTmdbId[$tmdbId] = $row;
    }

    $trailerCount = 0;

    foreach ($records as $record) {
        $row = $rowsByTmdbId[$record['tmdb_id']] ?? null;

        if ($row === null || trim((string) $row['title']) === '') {
            b2s_import_fail("Inserted TMDb ID {$record['tmdb_id']} could not be verified.");
        }

        if ($record['release_date'] !== null && $row['release_date'] !== $record['release_date']) {
            b2s_import_fail("Inserted TMDb ID {$record['tmdb_id']} has an unexpected release date.");
        }

        if ($record['poster_path'] !== null && $row['poster_path'] !== $record['poster_path']) {
            b2s_import_fail("Inserted TMDb ID {$record['tmdb_id']} has an unexpected poster path.");
        }

        if ($row['source_author'] !== $record['source_author']) {
            b2s_import_fail("Inserted TMDb ID {$record['tmdb_id']} is missing its curated source author.");
        }

        if ($record['trailer_youtube_key'] !== null) {
            if ($row['trailer_youtube_key'] !== $record['trailer_youtube_key']) {
                b2s_import_fail("Inserted TMDb ID {$record['tmdb_id']} has an unexpected trailer key.");
            }

            $trailerCount++;
        }
    }

    [$titleIndex, $titleYearIndex, $tmdbIdIndex] = acclaimed_build_tmdb_indexes($rows);
    $b2sReport = acclaimed_build_collection_report(
        'b2s-100',
        $datasets['b2s-100'],
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );

    if (count($b2sReport['matches']) !== B2S_EXPECTED_SOURCE_COUNT) {
        b2s_import_fail('B2S source count changed during import verification.');
    }

    if ($b2sReport['counts']['UNMATCHED'] !== 0) {
        b2s_import_fail('B2S matcher still has unmatched entries after insert.');
    }

    foreach (B2S_APPROVED_TMDB_OVERRIDES as $rank => $tmdbId) {
        $match = array_values(array_filter(
            $b2sReport['matches'],
            static fn(array $match): bool => (int) ($match['item']['rank'] ?? 0) === $rank
        ))[0] ?? null;
        $matchedTmdbIds = array_map(
            static fn(array $candidate): int => (int) $candidate['tmdb_id'],
            $match['candidates'] ?? []
        );

        if (!in_array($tmdbId, $matchedTmdbIds, true)) {
            b2s_import_fail("B2S #{$rank} does not resolve to its approved TMDb ID after insert.");
        }
    }

    return [
        'row_count' => $rowCount,
        'trailer_count' => $trailerCount,
        'b2s_counts' => $b2sReport['counts'],
    ];
}

$apply = in_array('--apply', $argv ?? [], true);

try {
    $datasets = require __DIR__ . '/../includes/acclaimed-datasets.php';

    if (count($datasets['b2s-100']['items'] ?? []) !== B2S_EXPECTED_SOURCE_COUNT) {
        b2s_import_fail('B2S dataset does not contain exactly 100 entries.');
    }

    $db = get_db();
    $existingRows = $db->query('
        SELECT tmdb_id, title, release_date
        FROM tmdb_adaptations
    ')->fetchAll(PDO::FETCH_ASSOC);
    $preInsertRowCount = count($existingRows);
    [$titleIndex, $titleYearIndex, $tmdbIdIndex] = acclaimed_build_tmdb_indexes($existingRows);
    $preInsertReport = acclaimed_build_collection_report(
        'b2s-100',
        $datasets['b2s-100'],
        $titleIndex,
        $titleYearIndex,
        $tmdbIdIndex
    );
    $unmatchedItems = array_values(array_map(
        static fn(array $match): array => $match['item'],
        array_filter(
            $preInsertReport['matches'],
            static fn(array $match): bool => $match['status'] === 'UNMATCHED'
        )
    ));

    if (count($unmatchedItems) !== B2S_EXPECTED_INSERT_COUNT) {
        b2s_import_fail('Expected 41 unmatched B2S entries; found ' . count($unmatchedItems) . '.');
    }

    $existingTmdbIds = array_fill_keys(array_map(
        static fn(array $row): int => (int) $row['tmdb_id'],
        $existingRows
    ), true);
    $lookups = b2s_tmdb_build_approved_insertion_set($unmatchedItems, $existingTmdbIds);
    $records = b2s_import_build_records($lookups);
    b2s_import_validate_records($records, $existingTmdbIds);

    if (!$apply) {
        echo json_encode([
            'mode' => 'dry-run',
            'pre_insert_rows' => $preInsertRowCount,
            'b2s_unmatched' => count($unmatchedItems),
            'approved_records' => count($records),
            'trailer_keys' => count(array_filter(
                $records,
                static fn(array $record): bool => $record['trailer_youtube_key'] !== null
            )),
        ], JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    if (!$db->beginTransaction()) {
        b2s_import_fail('Could not begin the B2S import transaction.');
    }

    try {
        $insert = $db->prepare('
            INSERT INTO tmdb_adaptations (
                tmdb_id,
                tmdb_keyword_id,
                title,
                original_title,
                overview,
                release_date,
                poster_path,
                backdrop_path,
                original_language,
                vote_average,
                vote_count,
                popularity,
                source_author,
                source_credit_job,
                source_checked_at,
                trailer_youtube_key,
                trailer_checked_at,
                last_seen_at
            ) VALUES (
                :tmdb_id,
                :tmdb_keyword_id,
                :title,
                :original_title,
                :overview,
                :release_date,
                :poster_path,
                :backdrop_path,
                :original_language,
                :vote_average,
                :vote_count,
                :popularity,
                :source_author,
                NULL,
                CURRENT_TIMESTAMP,
                :trailer_youtube_key,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ');

        foreach ($records as $record) {
            $insert->execute([
                ':tmdb_id' => $record['tmdb_id'],
                ':tmdb_keyword_id' => $record['tmdb_keyword_id'],
                ':title' => $record['title'],
                ':original_title' => $record['original_title'],
                ':overview' => $record['overview'],
                ':release_date' => $record['release_date'],
                ':poster_path' => $record['poster_path'],
                ':backdrop_path' => $record['backdrop_path'],
                ':original_language' => $record['original_language'],
                ':vote_average' => $record['vote_average'],
                ':vote_count' => $record['vote_count'],
                ':popularity' => $record['popularity'],
                ':source_author' => $record['source_author'],
                ':trailer_youtube_key' => $record['trailer_youtube_key'],
            ]);
        }

        $verification = b2s_import_verify(
            $db,
            $records,
            $preInsertRowCount + B2S_EXPECTED_INSERT_COUNT,
            $datasets
        );

        if (!$db->commit()) {
            b2s_import_fail('Could not commit the B2S import transaction.');
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $error;
    }

    echo json_encode([
        'mode' => 'applied',
        'pre_insert_rows' => $preInsertRowCount,
        'inserted_records' => count($records),
        'post_insert_rows' => $verification['row_count'],
        'trailer_keys' => $verification['trailer_count'],
        'b2s_counts' => $verification['b2s_counts'],
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'B2S TMDb import failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}