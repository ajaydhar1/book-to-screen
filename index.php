<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

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
    ORDER BY source_published_at DESC, created_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$adaptations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$discoverStmt = $db->query("
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
    ORDER BY RANDOM()
    LIMIT 5
");

$discoveries = $discoverStmt->fetchAll(PDO::FETCH_ASSOC);

$releasedStmt = $db->query("
    SELECT
        tmdb_id,
        title,
        release_date,
        poster_path,
        source_author
    FROM tmdb_adaptations
    WHERE release_date IS NOT NULL
      AND release_date <> ''
      AND date(release_date) <= date('now')
    ORDER BY release_date DESC, tmdb_id DESC
    LIMIT 4
");

$releasedMovies = $releasedStmt->fetchAll(PDO::FETCH_ASSOC);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $value): string
{
    if (!$value) {
        return '';
    }

    return date('M j, Y', strtotime($value));
}

function barnesAndNobleSearchUrl(
    ?string $bookTitle,
    ?string $bookAuthor
): ?string {
    $query = trim(
        ($bookTitle ?? '') . ' ' . ($bookAuthor ?? '')
    );

    if ($query === '') {
        return null;
    }

    return 'https://www.barnesandnoble.com/search?q='
        . urlencode($query);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LRF3X9CMCT"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-LRF3X9CMCT');
    </script>

    <meta charset="UTF-8">

    <title>Book to Screen | Books, Articles & Podcasts Becoming Movies and TV</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta
        name="description"
        content="Discover books, articles, podcasts, comics, and true stories being adapted into movies and television. Follow the latest adaptation announcements from across the entertainment industry.">

    <meta
        name="robots"
        content="index,follow">

    <link rel="canonical" href="https://booktoscreen.org/">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main>
        <section class="hero home-hero">
            <p class="eyebrow">Adaptation Tracker</p>

            <h2>Stories on their way to film and/or television.</h2>

            <p>
                Book to Screen tracks books, articles, podcasts, comics, and true stories
                being adapted for movies and TV.
            </p>

            <div class="hero-actions">
                <button
                    class="hero-button hero-button--primary"
                    type="button"
                    data-random-trailer>
                    🎲 Watch a Trailer
                </button>

                <a
                    class="hero-button hero-button--secondary"
                    href="/trailers.php">
                    Browse Trailers
                </a>
            </div>

        </section>

        <?php if (!empty($releasedMovies)): ?>
            <section class="section released-section">

                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Now Playing</p>
                        <h2>Recently released adaptations</h2>
                    </div>
                </div>

                <div class="released-grid">

                    <?php foreach ($releasedMovies as $movie): ?>

                        <?php
                        $posterUrl = !empty($movie['poster_path'])
                            ? 'https://image.tmdb.org/t/p/w342' . $movie['poster_path']
                            : null;

                        $trailerUrl = '/tmdb-trailer.php?id='
                            . urlencode((string) $movie['tmdb_id']);

                        $bookUrl = barnesAndNobleSearchUrl(
                            $movie['title'] ?? null,
                            $movie['source_author'] ?? null
                        );

                        $authorUrl = !empty($movie['source_author'])
                            ? '/trailers.php?author='
                            . urlencode($movie['source_author'])
                            : null;
                        ?>

                        <article class="released-card">

                            <?php if ($posterUrl): ?>
                                <a
                                    class="released-poster-link"
                                    href="<?= e($trailerUrl) ?>"
                                    target="_blank"
                                    rel="noopener">

                                    <img
                                        class="released-poster"
                                        src="<?= e($posterUrl) ?>"
                                        alt="<?= e($movie['title'] ?? '') ?>"
                                        loading="lazy"
                                        decoding="async">

                                </a>
                            <?php endif; ?>

                            <div class="released-card-body">

                                <h3>
                                    <a
                                        href="<?= e($trailerUrl) ?>"
                                        target="_blank"
                                        rel="noopener">
                                        <?= e($movie['title'] ?? 'Untitled') ?>
                                    </a>
                                </h3>

                                <?php if (!empty($movie['release_date'])): ?>
                                    <p class="released-date">
                                        <?= e(date(
                                            'M j, Y',
                                            strtotime($movie['release_date'])
                                        )) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($authorUrl): ?>
                                    <p class="released-author">
                                        Based on the book by
                                        <a href="<?= e($authorUrl) ?>">
                                            <?= e($movie['source_author']) ?>
                                        </a>
                                    </p>
                                <?php endif; ?>

                                <div class="released-actions">

                                    <a
                                        href="<?= e($trailerUrl) ?>"
                                        target="_blank"
                                        rel="noopener">
                                        Watch trailer →
                                    </a>

                                    <?php if ($bookUrl): ?>
                                        <a
                                            href="<?= e($bookUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            Find the book →
                                        </a>
                                    <?php endif; ?>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

                <div class="released-more">
                    <a href="/trailers.php">
                        Browse all released adaptations →
                    </a>
                </div>

            </section>
        <?php endif; ?>

        <?php if (!empty($discoveries)): ?>
            <section class="section discover-section">

                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Discover</p>
                        <h2>Stories worth discovering</h2>
                    </div>
                </div>

                <?php $featured = $discoveries[0]; ?>

                <article class="discover-feature">

                    <?php if (!empty($featured['featured_image_url'])): ?>

                        <?php
                        $imageUrl = $featured['featured_image_url'];

                        if (
                            str_contains($imageUrl, 'deadline.com/wp-content/uploads/')
                            && !str_contains($imageUrl, 'w=')
                        ) {
                            $separator = str_contains($imageUrl, '?') ? '&' : '?';
                            $imageUrl .= $separator . 'w=900&h=506&crop=1';
                        }
                        ?>

                        <div class="discover-feature-image">
                            <img
                                src="<?= e($imageUrl) ?>"
                                alt="<?= e($featured['book_title']) ?>"
                                width="900"
                                height="506"
                                loading="eager"
                                decoding="async">
                        </div>

                    <?php endif; ?>

                    <div class="discover-feature-content">

                        <div class="card-meta">
                            <span><?= e($featured['adaptation_type']) ?></span>

                            <?php if (!empty($featured['adaptation_status'])): ?>
                                <span><?= e($featured['adaptation_status']) ?></span>
                            <?php endif; ?>
                        </div>

                        <h3>
                            <?= e($featured['adaptation_title'] ?: $featured['book_title']) ?>
                        </h3>

                        <p class="based-on">
                            Based on <em><?= e($featured['book_title']) ?></em>
                        </p>

                        <?php if (!empty($featured['book_author'])): ?>
                            <p class="book-author">
                                by <?= e($featured['book_author']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($featured['article_excerpt'])): ?>
                            <p class="card-summary">
                                <?= e($featured['article_excerpt']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="card-footer">

                            <span class="source-name">
                                <?= e($featured['source_name']) ?>

                                <?php if (!empty($featured['source_published_at'])): ?>
                                    • <?= e(date('M j, Y', strtotime($featured['source_published_at']))) ?>
                                <?php endif; ?>
                            </span>

                            <div class="card-actions">

                                <?php
                                $bookUrl = barnesAndNobleSearchUrl(
                                    $featured['book_title'] ?? null,
                                    $featured['book_author'] ?? null
                                );
                                ?>

                                <?php if ($bookUrl): ?>
                                    <a
                                        class="card-link"
                                        href="<?= e($bookUrl) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer">
                                        Find the book →
                                    </a>
                                <?php endif; ?>

                                <a
                                    class="card-link"
                                    href="<?= e($featured['source_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer">
                                    Read article →
                                </a>

                            </div>

                        </div>

                    </div>

                </article>


                <?php if (count($discoveries) > 1): ?>

                    <div class="discover-grid">

                        <?php foreach (array_slice($discoveries, 1) as $adaptation): ?>

                            <article class="discover-card">

                                <?php if (!empty($adaptation['featured_image_url'])): ?>

                                    <?php
                                    $imageUrl = $adaptation['featured_image_url'];

                                    if (
                                        str_contains($imageUrl, 'deadline.com/wp-content/uploads/')
                                        && !str_contains($imageUrl, 'w=')
                                    ) {
                                        $separator = str_contains($imageUrl, '?') ? '&' : '?';
                                        $imageUrl .= $separator . 'w=600&h=338&crop=1';
                                    }
                                    ?>

                                    <div class="discover-card-image">
                                        <img
                                            src="<?= e($imageUrl) ?>"
                                            alt="<?= e($adaptation['book_title']) ?>"
                                            width="600"
                                            height="338"
                                            loading="lazy"
                                            decoding="async">
                                    </div>

                                <?php endif; ?>

                                <div class="discover-card-content">

                                    <div class="card-meta">
                                        <span><?= e($adaptation['adaptation_type']) ?></span>

                                        <?php if (!empty($adaptation['adaptation_status'])): ?>
                                            <span><?= e($adaptation['adaptation_status']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <h3>
                                        <?= e($adaptation['adaptation_title'] ?: $adaptation['book_title']) ?>
                                    </h3>

                                    <p class="based-on">
                                        Based on <em><?= e($adaptation['book_title']) ?></em>
                                    </p>

                                    <?php if (!empty($adaptation['book_author'])): ?>
                                        <p class="book-author">
                                            by <?= e($adaptation['book_author']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($adaptation['article_excerpt'])): ?>
                                        <p class="card-summary">
                                            <?= e($adaptation['article_excerpt']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="card-footer">

                                        <span class="source-name">
                                            <?= e($adaptation['source_name']) ?>

                                            <?php if (!empty($adaptation['source_published_at'])): ?>
                                                • <?= e(date('M j, Y', strtotime($adaptation['source_published_at']))) ?>
                                            <?php endif; ?>
                                        </span>

                                        <div class="card-actions">

                                            <?php
                                            $bookUrl = barnesAndNobleSearchUrl(
                                                $adaptation['book_title'] ?? null,
                                                $adaptation['book_author'] ?? null
                                            );
                                            ?>

                                            <?php if ($bookUrl): ?>
                                                <a
                                                    class="card-link"
                                                    href="<?= e($bookUrl) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer">
                                                    Find the book →
                                                </a>
                                            <?php endif; ?>

                                            <a
                                                class="card-link"
                                                href="<?= e($adaptation['source_url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer">
                                                Read article →
                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>
        <?php endif; ?>

        <section class="section" id="latest-announcements">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Recently Added</p>
                    <h2>Latest announcements</h2>
                </div>
            </div>

            <?php if (empty($adaptations)): ?>
                <div class="empty-state">
                    <strong>No published adaptations yet.</strong>
                    <p>
                        Once adaptations are reviewed and published from the admin dashboard,
                        they will appear here automatically.
                    </p>
                </div>
            <?php else: ?>
                <div class="latest-grid">
                    <?php foreach ($adaptations as $adaptation): ?>
                        <article class="card">

                            <?php if (!empty($adaptation['featured_image_url'])): ?>

                                <?php
                                $imageUrl = $adaptation['featured_image_url'];

                                if (
                                    str_contains($imageUrl, 'deadline.com/wp-content/uploads/')
                                    && !str_contains($imageUrl, 'w=')
                                ) {
                                    $separator = str_contains($imageUrl, '?') ? '&' : '?';
                                    $imageUrl .= $separator . 'w=450&h=253&crop=1';
                                }
                                ?>

                                <div class="card-image">
                                    <img
                                        src="<?= e($imageUrl) ?>"
                                        alt="<?= e($adaptation['book_title']) ?>"
                                        width="450"
                                        height="253"
                                        loading="lazy"
                                        decoding="async">
                                </div>
                            <?php endif; ?>

                            <div class="card-meta">
                                <span><?= e($adaptation['adaptation_type']) ?></span>

                                <?php if (!empty($adaptation['adaptation_status'])): ?>
                                    <span><?= e($adaptation['adaptation_status']) ?></span>
                                <?php endif; ?>
                            </div>

                            <h3>
                                <?= e($adaptation['adaptation_title'] ?: $adaptation['book_title']) ?>
                            </h3>

                            <p class="based-on">
                                Based on <em><?= e($adaptation['book_title']) ?></em>
                            </p>

                            <?php if (!empty($adaptation['book_author'])): ?>
                                <p class="book-author">
                                    by <?= e($adaptation['book_author']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($adaptation['article_excerpt'])): ?>
                                <p class="card-summary">
                                    <?= e($adaptation['article_excerpt']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="card-footer">

                                <span class="source-name">
                                    <?= e($adaptation['source_name']) ?>

                                    <?php if (!empty($adaptation['source_published_at'])): ?>
                                        • <?= e(date('M j, Y', strtotime($adaptation['source_published_at']))) ?>
                                    <?php endif; ?>
                                </span>

                                <div class="card-actions">

                                    <?php
                                    $bookUrl = barnesAndNobleSearchUrl(
                                        $adaptation['book_title'] ?? null,
                                        $adaptation['book_author'] ?? null
                                    );
                                    ?>

                                    <?php if ($bookUrl): ?>
                                        <a
                                            class="card-link"
                                            href="<?= e($bookUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            Find the book →
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        class="card-link"
                                        href="<?= e($adaptation['source_url']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer">
                                        Read article →
                                    </a>

                                </div>

                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>

                    <nav class="pagination">

                        <?php if ($page > 1): ?>
                            <a href="/?page=<?= $page - 1 ?>#latest-announcements">← Newer</a>
                        <?php endif; ?>

                        <span>Page <?= $page ?> of <?= $totalPages ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="/?page=<?= $page + 1 ?>#latest-announcements">Older →</a>
                        <?php endif; ?>

                    </nav>

                <?php endif; ?>

            <?php endif; ?>
        </section>

        <section class="section">
            <div class="about">
                <p class="eyebrow">About</p>

                <h2>Follow the source material before it reaches the screen.</h2>

                <p>
                    Every adaptation starts somewhere: a novel, a memoir, a magazine article,
                    a podcast, a comic, a short story, or a real-life event. Book to Screen
                    tracks those signals so readers and viewers can discover the stories behind
                    future film and television projects.
                </p>

                <p>
                    Learn more about our mission, editorial process, and the team behind
                    Book to Screen.
                </p>

                <p>
                    <a class="button button-primary" href="/about/">
                        About Book to Screen →
                    </a>
                </p>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>

</html>