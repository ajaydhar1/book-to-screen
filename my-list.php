<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-matching.php';

if (isset($_GET['fragment'])) {
    $rawIds = isset($_GET['ids']) && is_string($_GET['ids'])
        ? explode(',', $_GET['ids'])
        : [];
    $ids = [];

    foreach ($rawIds as $rawId) {
        if (preg_match('/^\d+$/', $rawId) !== 1) {
            continue;
        }

        $id = (int) $rawId;

        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    if ($ids !== []) {
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $placeholders[] = ':id_' . $index;
        }

        $statement = get_db()->prepare('SELECT tmdb_id, title, overview, release_date, poster_path, vote_average, source_author, trailer_youtube_key FROM tmdb_adaptations WHERE tmdb_id IN ('
            . implode(', ', $placeholders) . ')');

        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }

        $statement->execute();
        $adaptations = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $adaptation) {
            $adaptations[(int) $adaptation['tmdb_id']] = $adaptation;
        }

        foreach ($ids as $id) {
            if (isset($adaptations[$id])) {
                $movie = $adaptations[$id];
                require __DIR__ . '/includes/tmdb-trailer-card.php';
            }
        }
    }

    exit;
}

$metaTitle = 'My List | Book to Screen';
$metaDescription = 'Your saved Book to Screen adaptations.';
$metaCanonical = 'https://booktoscreen.org/my-list.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php require __DIR__ . '/includes/meta.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailers.css?v=<?= filemtime(__DIR__ . '/assets/css/trailers.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="trailers-shell my-list-shell">
        <header class="trailers-header">
            <div>
                <p class="eyebrow">Book to Screen</p>
                <h1 class="page-title">My List</h1>
                <p class="page-intro">Adaptations you have saved while exploring Book to Screen.</p>
            </div>
        </header>
        <section aria-label="Saved adaptations">
            <div class="trailer-grid" id="my-list-cards"></div>
            <div class="my-list-empty" id="my-list-empty" hidden>
                <h2>Your list is empty</h2>
                <p>Save adaptations while exploring Book to Screen and they'll appear here.</p>
                <a href="/trailers.php">Discover trailers</a>
            </div>
        </section>
    </main>
    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>
</html>