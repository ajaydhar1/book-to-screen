<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/video-feed-sources.php';

header('Content-Type: application/json; charset=utf-8');

$sourceSlug = trim((string) ($_GET['source'] ?? ''));
$source = video_feed_source_by_slug($sourceSlug);

if ($source === null || empty($source['enabled'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported or disabled feed source.']);
    exit;
}

if (($source['kind'] ?? '') !== 'b2s') {
    http_response_code(400);
    echo json_encode(['error' => 'This feed source does not support itemized batches yet.']);
    exit;
}

$limit = filter_input(
    INPUT_GET,
    'limit',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 6,
            'min_range' => 1,
            'max_range' => 24,
        ],
    ]
) ?: 6;

$seenRaw = trim((string) ($_GET['seen'] ?? ''));
$seenIds = [];

if ($seenRaw !== '') {
    foreach (explode(',', $seenRaw) as $idString) {
        $id = (int) trim((string) $idString);

        if ($id > 0) {
            $seenIds[] = $id;
        }
    }
}

$items = video_feed_b2s_items($seenIds, $limit);

$payload = [
    'source' => $source['slug'],
    'items' => $items,
    'has_more' => count($items) === $limit,
];

echo json_encode($payload, JSON_THROW_ON_ERROR);
