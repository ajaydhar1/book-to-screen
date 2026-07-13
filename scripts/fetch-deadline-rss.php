<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$feedUrl = DEADLINE_RSS_URL;
$source = 'Deadline';
$jobName = 'deadline_rss_import';
$startedAt = microtime(true);

$db = get_db();
$cronRunId = null;

try {

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

    $cronRunId = (int)$db->lastInsertId();

    $ch = curl_init($feedUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'BookToScreenBot/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $rssXml = curl_exec($ch);

    if ($rssXml === false) {
        $errorMessage = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL error: ' . $errorMessage);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("HTTP error: {$httpCode}");
    }

    $rss = simplexml_load_string($rssXml);

    if ($rss === false) {
        throw new RuntimeException("Could not parse RSS XML.");
    }

    $inserted = 0;
    $skipped = 0;

    $insert = $db->prepare("
        INSERT OR IGNORE INTO leads (
            rss_guid,
            source,
            article_title,
            article_url,
            article_excerpt,
            featured_image_url,
            published_at
        ) VALUES (
            :rss_guid,
            :source,
            :article_title,
            :article_url,
            :article_excerpt,
            :featured_image_url,
            :published_at
        )
    ");

    foreach ($rss->channel->item as $item) {
        $guid = trim((string) $item->guid);
        $title = trim(html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = trim((string) $item->link);
        $excerpt = trim(html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $pubDateRaw = trim((string) $item->pubDate);
        $featuredImageUrl = null;

        $media = $item->children('media', true);

        if (isset($media->content)) {
            $attributes = $media->content->attributes();
            $featuredImageUrl = isset($attributes['url']) ? trim((string) $attributes['url']) : null;
        }

        if ($featuredImageUrl === null && isset($media->thumbnail)) {
            $attributes = $media->thumbnail->attributes();
            $featuredImageUrl = isset($attributes['url']) ? trim((string) $attributes['url']) : null;
        }

        if ($featuredImageUrl === '') {
            $featuredImageUrl = null;
        }

        if ($guid === '') {
            $guid = $url;
        }

        if ($guid === '' || $title === '' || $url === '') {
            continue;
        }

        $publishedAt = null;

        if ($pubDateRaw !== '') {
            $date = new DateTimeImmutable($pubDateRaw);
            $date = $date->setTimezone(new DateTimeZone(TIMEZONE));
            $publishedAt = $date->format('Y-m-d H:i:s');
        }

        $insert->execute([
            ':rss_guid' => $guid,
            ':source' => $source,
            ':article_title' => $title,
            ':article_url' => $url,
            ':article_excerpt' => $excerpt,
            ':featured_image_url' => $featuredImageUrl,
            ':published_at' => $publishedAt,
        ]);

        if ($insert->rowCount() > 0) {
            $inserted++;
            echo "Added: {$title}" . PHP_EOL;
        } else {
            $skipped++;
        }
    }

    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

    $finishRun = $db->prepare("
        UPDATE cron_runs
        SET
            status = 'completed',
            completed_at = CURRENT_TIMESTAMP,
            inserted_count = :inserted,
            skipped_count = :skipped,
            duration_ms = :duration
        WHERE id = :id
    ");

    $finishRun->execute([
        ':inserted' => $inserted,
        ':skipped' => $skipped,
        ':duration' => $durationMs,
        ':id' => $cronRunId,
    ]);

    echo PHP_EOL;
    echo "Done." . PHP_EOL;
    echo "Inserted: {$inserted}" . PHP_EOL;
    echo "Skipped existing: {$skipped}" . PHP_EOL;
} catch (Throwable $e) {
    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

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
            $loggingMessage = 'Could not record cron failure: '
                . $loggingError->getMessage();

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, $loggingMessage . PHP_EOL);
            } else {
                error_log($loggingMessage);
            }
        }
    }

    $errorMessage = $e->getMessage();

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $errorMessage . PHP_EOL);
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
