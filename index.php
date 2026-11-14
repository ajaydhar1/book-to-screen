<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-matching.php';
require_once __DIR__ . '/includes/acclaimed-public.php';

$db = get_db();

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int) $db->query("
    SELECT COUNT(*)
    FROM adaptations
")->fetchColumn();

$totalPages = (int) ceil($total / $perPage);

$stmt = $db->prepare("
    SELECT
        id,
        book_title,
        adaptation_title,
        book_author,
        adaptation_type,
        adaptation_status,
        source_name,
        source_url,
        source_published_at,
        article_title,
        article_excerpt,
        featured_image_url,
        created_at
    FROM adaptations
    ORDER BY source_published_at DESC, created_at DESC, id DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$adaptations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$discoverWhere = '';
$discoverParams = [];
$latestIds = array_map(
    static fn(array $adaptation): int => (int) $adaptation['id'],
    $adaptations
);

if ($latestIds) {
    $placeholders = [];

    foreach ($latestIds as $index => $latestId) {
        $placeholder = ':latest_id_' . $index;
        $placeholders[] = $placeholder;
        $discoverParams[$placeholder] = $latestId;
    }

    $discoverWhere = 'WHERE id NOT IN (' . implode(', ', $placeholders) . ')';
}

$discoverStmt = $db->prepare("
    SELECT
        id,
        book_title,
        adaptation_title,
        book_author,
        adaptation_type,
        adaptation_status,
        source_name,
        source_url,
        source_published_at,
        article_title,
        article_excerpt,
        featured_image_url,
        created_at
    FROM adaptations
    {$discoverWhere}
    ORDER BY RANDOM()
    LIMIT 5
");

foreach ($discoverParams as $placeholder => $latestId) {
    $discoverStmt->bindValue($placeholder, $latestId, PDO::PARAM_INT);
}

$discoverStmt->execute();

$discoveries = $discoverStmt->fetchAll(PDO::FETCH_ASSOC);

$releasedStmt = $db->query("
    SELECT
        tmdb_id,
        title,
        release_date,
        poster_path,
        source_author,
        trailer_youtube_key
    FROM tmdb_adaptations
    WHERE release_date IS NOT NULL
      AND release_date <> ''
      AND date(release_date) <= date('now')
    ORDER BY release_date DESC, tmdb_id DESC
    LIMIT 4
");

$releasedMovies = $releasedStmt->fetchAll(PDO::FETCH_ASSOC);

$acclaimedDatasets = require __DIR__ . '/includes/acclaimed-datasets.php';
$b2sPreviewItems = array_slice(
    acclaimed_public_matched_items($acclaimedDatasets['b2s-100']),
    0,
    5
);
$b2sPreviewAdaptations = acclaimed_public_load_adaptations($b2sPreviewItems);

$metaTitle = 'Book to Screen | Books, Articles & Podcasts Becoming Movies and TV';
$metaDescription = 'Discover books, articles, podcasts, comics, and true stories being adapted into movies and television. Follow the latest adaptation announcements from across the entertainment industry.';
$metaCanonical = 'https://booktoscreen.org/';

require __DIR__ . '/includes/cinematic-homepage.php';
