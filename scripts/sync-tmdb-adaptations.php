<?php

declare(strict_types=1);

/**
 * scripts/sync-tmdb-adaptations.php
 *
 * Syncs released U.S. movies tagged by TMDB with:
 * Keyword 818 = "based on novel or book"
 *
 * Normal mode:
 *   Fetches the newest TMDB pages for daily cron use.
 *
 * Full mode:
 *   php scripts/sync-tmdb-adaptations.php --full
 *
 *   Fetches all matching TMDB pages for backfills
 *   and occasional manual maintenance.
 */

require_once __DIR__ . '/../includes/config.local.php';
require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$tmdbToken = TMDB_READ_TOKEN;
$keywordId = 818;
$jobName = 'tmdb_adaptations_import';
$startedAt = microtime(true);

$cronRunId = null;

$isFullSync = in_array('--full', $argv ?? [], true);

// Normal daily sync: newest 5 pages.
// Full sync: all available pages.
$dailyPageLimit = 5;

if (!$tmdbToken) {
    throw new RuntimeException(
        'TMDB_READ_TOKEN is not configured.'
    );
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
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException(
            'TMDB cURL error: ' . $error
        );
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            'TMDB returned invalid JSON.'
        );
    }

    if ($httpCode !== 200) {
        $message = $data['status_message']
            ?? 'Unknown TMDB error';

        throw new RuntimeException(
            "TMDB returned HTTP {$httpCode}: {$message}"
        );
    }

    return $data;
}


// --------------------------------------------------
// SYNC
// --------------------------------------------------

try {

    // --------------------------------------------------
    // CREATE CRON RUN RECORD
    // --------------------------------------------------

    $createRun = $db->prepare("
        INSERT INTO cron_runs (
            job_name,
            status
        ) VALUES (
            :job_name,
            'running'
        )
    ");

    $createRun->execute([
        ':job_name' => $jobName,
    ]);

    $cronRunId = (int) $db->lastInsertId();


    // --------------------------------------------------
    // PREPARED STATEMENTS
    // --------------------------------------------------

    $existsStmt = $db->prepare("
        SELECT 1
        FROM tmdb_adaptations
        WHERE tmdb_id = :tmdb_id
        LIMIT 1
    ");

    $upsertStmt = $db->prepare("
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
            last_seen_at
        )
        VALUES (
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
            CURRENT_TIMESTAMP
        )

        ON CONFLICT(tmdb_id)
        DO UPDATE SET
            tmdb_keyword_id = excluded.tmdb_keyword_id,
            title = excluded.title,
            original_title = excluded.original_title,
            overview = excluded.overview,
            release_date = excluded.release_date,
            poster_path = excluded.poster_path,
            backdrop_path = excluded.backdrop_path,
            original_language = excluded.original_language,
            vote_average = excluded.vote_average,
            vote_count = excluded.vote_count,
            popularity = excluded.popularity,
            updated_at = CURRENT_TIMESTAMP,
            last_seen_at = CURRENT_TIMESTAMP
    ");


    // --------------------------------------------------
    // FETCH TMDB
    // --------------------------------------------------

    $page = 1;

    $pagesFetched = 0;
    $processedCount = 0;
    $insertedCount = 0;
    $updatedCount = 0;

    do {

        $query = http_build_query([
            'include_adult' => 'false',
            'include_video' => 'false',
            'language' => 'en-US',
            'page' => $page,
            'sort_by' => 'primary_release_date.desc',
            'with_keywords' => $keywordId,
            'with_origin_country' => 'US',
            'primary_release_date.lte' => date('Y-m-d'),
        ]);

        $url =
            'https://api.themoviedb.org/3/discover/movie?'
            . $query;

        $data = tmdbRequest(
            $url,
            $tmdbToken
        );

        $pagesFetched++;

        $results = $data['results'] ?? [];

        foreach ($results as $movie) {

            if (!isset($movie['id'])) {
                continue;
            }

            $tmdbId = (int) $movie['id'];

            $existsStmt->execute([
                ':tmdb_id' => $tmdbId,
            ]);

            $exists = (bool) $existsStmt->fetchColumn();

            $releaseDate =
                isset($movie['release_date'])
                && $movie['release_date'] !== ''
                    ? $movie['release_date']
                    : null;

            $upsertStmt->execute([
                ':tmdb_id' => $tmdbId,
                ':tmdb_keyword_id' => $keywordId,
                ':title' =>
                    $movie['title'] ?? 'Untitled',
                ':original_title' =>
                    $movie['original_title'] ?? null,
                ':overview' =>
                    $movie['overview'] ?? null,
                ':release_date' =>
                    $releaseDate,
                ':poster_path' =>
                    $movie['poster_path'] ?? null,
                ':backdrop_path' =>
                    $movie['backdrop_path'] ?? null,
                ':original_language' =>
                    $movie['original_language'] ?? null,
                ':vote_average' =>
                    isset($movie['vote_average'])
                        ? (float) $movie['vote_average']
                        : null,
                ':vote_count' =>
                    isset($movie['vote_count'])
                        ? (int) $movie['vote_count']
                        : null,
                ':popularity' =>
                    isset($movie['popularity'])
                        ? (float) $movie['popularity']
                        : null,
            ]);

            $processedCount++;

            if ($exists) {
                $updatedCount++;
            } else {
                $insertedCount++;
            }
        }

        $totalPages = max(
            1,
            (int) ($data['total_pages'] ?? 1)
        );

        if ($isFullSync) {
            $lastPage = $totalPages;
        } else {
            $lastPage = min(
                $dailyPageLimit,
                $totalPages
            );
        }

        echo "Synced page {$page} of {$lastPage}"
            . PHP_EOL;

        $page++;

    } while ($page <= $lastPage);


    // --------------------------------------------------
    // COMPLETE CRON RUN
    // --------------------------------------------------

    $durationMs = (int) round(
        (microtime(true) - $startedAt) * 1000
    );

    $finishRun = $db->prepare("
        UPDATE cron_runs
        SET
            status = 'completed',
            completed_at = CURRENT_TIMESTAMP,
            inserted_count = :inserted,
            updated_count = :updated,
            skipped_count = 0,
            duration_ms = :duration
        WHERE id = :id
    ");

    $finishRun->execute([
        ':inserted' => $insertedCount,
        ':updated' => $updatedCount,
        ':duration' => $durationMs,
        ':id' => $cronRunId,
    ]);


    // --------------------------------------------------
    // SUMMARY
    // --------------------------------------------------

    echo PHP_EOL;
    echo "TMDB adaptations sync complete." . PHP_EOL;
    echo "Mode: "
        . ($isFullSync ? 'full' : 'daily')
        . PHP_EOL;
    echo "Pages fetched: {$pagesFetched}" . PHP_EOL;
    echo "Movies processed: {$processedCount}" . PHP_EOL;
    echo "Inserted: {$insertedCount}" . PHP_EOL;
    echo "Updated: {$updatedCount}" . PHP_EOL;

} catch (Throwable $e) {

    $durationMs = (int) round(
        (microtime(true) - $startedAt) * 1000
    );

    if ($cronRunId !== null) {
        try {

            $failRun = $db->prepare("
                UPDATE cron_runs
                SET
                    status = 'failed',
                    completed_at = CURRENT_TIMESTAMP,
                    duration_ms = :duration,
                    error_message = :error_message
                WHERE id = :id
            ");

            $failRun->execute([
                ':duration' => $durationMs,
                ':error_message' => $e->getMessage(),
                ':id' => $cronRunId,
            ]);

        } catch (Throwable $loggingError) {

            $loggingMessage =
                'Could not record cron failure: '
                . $loggingError->getMessage();

            if (PHP_SAPI === 'cli') {
                fwrite(
                    STDERR,
                    $loggingMessage . PHP_EOL
                );
            } else {
                error_log($loggingMessage);
            }
        }
    }

    $errorMessage = $e->getMessage();

    if (PHP_SAPI === 'cli') {
        fwrite(
            STDERR,
            $errorMessage . PHP_EOL
        );
    } else {
        http_response_code(500);

        echo htmlspecialchars(
            $errorMessage,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    exit(1);
}