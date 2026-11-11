<?php

declare(strict_types=1);

if (!defined('CINEMATIC_PREVIEW_ENTRY')) {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/includes/db.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/acclaimed-matching.php';
require_once $root . '/includes/acclaimed-public.php';

if (DB_DRIVER === 'sqlite' && !is_file(DB_PATH)) {
    throw new RuntimeException('The configured adaptations database does not exist.');
}

$db = get_db();
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) $db->query('SELECT COUNT(*) FROM adaptations')->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

$fields = 'id, book_title, adaptation_title, book_author, adaptation_type, adaptation_status,
    source_name, source_url, source_published_at, article_title, article_excerpt,
    featured_image_url, created_at';
$stmt = $db->prepare("SELECT {$fields} FROM adaptations
    ORDER BY source_published_at DESC, created_at DESC, id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$adaptations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ids = array_map(static fn(array $item): int => (int) $item['id'], $adaptations);
$placeholders = [];
foreach ($ids as $i => $id) {
    $placeholders[] = ':latest_id_' . $i;
}
$where = $placeholders ? 'WHERE id NOT IN (' . implode(', ', $placeholders) . ')' : '';
$stmt = $db->prepare("SELECT {$fields} FROM adaptations {$where} ORDER BY RANDOM() LIMIT 5");
foreach ($ids as $i => $id) {
    $stmt->bindValue(':latest_id_' . $i, $id, PDO::PARAM_INT);
}
$stmt->execute();
$discoveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$releasedMovies = $db->query("SELECT tmdb_id, title, release_date, poster_path, source_author, trailer_youtube_key
    FROM tmdb_adaptations
    WHERE release_date IS NOT NULL AND release_date <> '' AND date(release_date) <= date('now')
    ORDER BY release_date DESC, tmdb_id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);

$datasets = require $root . '/includes/acclaimed-datasets.php';
$b2sPreviewItems = array_slice(acclaimed_public_matched_items($datasets['b2s-100']), 0, 5);
$b2sPreviewAdaptations = acclaimed_public_load_adaptations($b2sPreviewItems);

function cinematic_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cinematic_image_url(string $url, int $width, int $height): string
{
    if (str_contains($url, 'deadline.com/wp-content/uploads/') && !str_contains($url, 'w=')) {
        $url .= (str_contains($url, '?') ? '&' : '?') . "w={$width}&h={$height}&crop=1";
    }
    return $url;
}
