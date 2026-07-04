<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$feedUrl = DEADLINE_RSS_URL;

$source = 'Deadline';

$db = get_db();

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
    fwrite(STDERR, 'cURL error: ' . curl_error($ch) . PHP_EOL);
    curl_close($ch);
    exit(1);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    fwrite(STDERR, "HTTP error: {$httpCode}" . PHP_EOL);
    exit(1);
}

$rss = simplexml_load_string($rssXml);

if ($rss === false) {
    fwrite(STDERR, "Could not parse RSS XML." . PHP_EOL);
    exit(1);
}

if ($rss === false) {
    fwrite(STDERR, "Could not load RSS feed: {$feedUrl}" . PHP_EOL);
    exit(1);
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
        published_at
    ) VALUES (
        :rss_guid,
        :source,
        :article_title,
        :article_url,
        :article_excerpt,
        :published_at
    )
");

foreach ($rss->channel->item as $item) {
    $guid = trim((string) $item->guid);
    $title = trim(html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $url = trim((string) $item->link);
    $excerpt = trim(html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $pubDateRaw = trim((string) $item->pubDate);

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
        ':published_at' => $publishedAt,
    ]);

    if ($insert->rowCount() > 0) {
        $inserted++;
        echo "Added: {$title}" . PHP_EOL;
    } else {
        $skipped++;
    }
}

echo PHP_EOL;
echo "Done." . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Skipped existing: {$skipped}" . PHP_EOL;
