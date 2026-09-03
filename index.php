<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

    <style>
        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            background: #f7f2ea;
            color: #241c15;
        }

        a {
            color: inherit;
        }

        .site-header {
            padding: 28px 24px;
            border-bottom: 1px solid #ded2c2;
            background: #fffaf3;
        }

        .site-title {
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero {
            padding: 72px 24px 56px;
            max-width: 960px;
            margin: 0 auto;
        }

        .eyebrow {
            margin: 0 0 12px;
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #8a5a32;
            font-weight: bold;
        }

        .hero h2 {
            margin: 0;
            font-size: clamp(2.4rem, 7vw, 5rem);
            line-height: 0.95;
            max-width: 820px;
        }

        .hero p {
            margin-top: 24px;
            max-width: 620px;
            font-size: 1.2rem;
            line-height: 1.6;
            color: #5d5146;
        }

        .section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: end;
            margin-bottom: 20px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }

        .card {
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 30px rgba(36, 28, 21, 0.05);
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8a5a32;
            font-weight: bold;
        }

        .card h3 {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .card p {
            margin: 14px 0 18px;
            line-height: 1.55;
            color: #5d5146;
        }

        .card-link {
            font-weight: bold;
            text-decoration: none;
        }

        .empty-state {
            background: #fffaf3;
            border: 1px dashed #c9b8a4;
            border-radius: 18px;
            padding: 28px;
            color: #5d5146;
        }

        .about {
            margin-top: 40px;
            background: #2b2118;
            color: #fffaf3;
            border-radius: 24px;
            padding: 34px;
        }

        .about p {
            max-width: 720px;
            line-height: 1.65;
            color: #eadfce;
        }

        .site-footer {
            padding: 36px 24px;
            text-align: center;
            color: #7b6d5e;
            font-size: 0.9rem;
        }

        @media (max-width: 640px) {
            .section-heading {
                display: block;
            }

            .hero {
                padding-top: 48px;
            }
        }

        .book-author {
            margin: 8px 0 18px;
            font-style: italic;
            color: #75685b;
        }

        .card-summary {
            line-height: 1.6;
            color: #5d5146;
        }

        .card-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid #e5d8c8;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .source-name {
            font-size: .9rem;
            color: #75685b;
        }

        .card-link {
            font-weight: 600;
            text-decoration: none;
        }

        .card-link:hover {
            text-decoration: underline;
        }

        .pagination {
            margin-top: 32px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 18px;
            font-weight: 600;
        }

        .pagination a {
            text-decoration: none;
            padding: 10px 14px;
            border: 1px solid #ded2c2;
            border-radius: 999px;
            background: #fffaf3;
        }

        .pagination a:hover {
            text-decoration: underline;
        }

        .pagination span {
            color: #75685b;
        }

        .site-footer a {
            color: inherit;
            text-decoration: none;
        }

        .site-footer a:hover {
            text-decoration: underline;
        }

        .card-image {
            width: calc(100% + 44px);
            margin: -22px -22px 18px;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            border-radius: 18px 18px 0 0;
            background: #f2ece3;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .card-image {
            aspect-ratio: 450 / 253;
            overflow: hidden;
        }

        .card-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }


        /* ========================================
   DISCOVER
======================================== */

        .discover-section {
            padding-top: 10px;
            padding-bottom: 48px;
        }


        /* ---------- Featured Story ---------- */

        .discover-feature {
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 22px;
            box-shadow: 0 14px 38px rgba(36, 28, 21, 0.07);
        }

        .discover-feature-image {
            width: 100%;
            aspect-ratio: 16 / 7;
            overflow: hidden;
            background: #f2ece3;
        }

        .discover-feature-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .discover-feature-content {
            padding: 30px 32px 32px;
        }

        .discover-feature-content h3 {
            margin: 0;
            max-width: 850px;
            font-size: clamp(1.9rem, 4vw, 2.8rem);
            line-height: 1.08;
        }

        .discover-feature-content .based-on {
            margin: 14px 0 0;
            font-size: 1.05rem;
            color: #5d5146;
        }

        .discover-feature-content .book-author {
            margin: 7px 0 18px;
        }

        .discover-feature-content .card-summary {
            max-width: 780px;
            font-size: 1.05rem;
            line-height: 1.65;
        }


        /* ---------- Four Smaller Stories ---------- */

        .discover-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .discover-card {
            display: flex;
            flex-direction: column;
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(36, 28, 21, 0.05);
        }

        .discover-card-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background: #f2ece3;
        }

        .discover-card-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .discover-card-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 24px;
        }

        .discover-card-content h3 {
            margin: 0;
            font-size: 1.55rem;
            line-height: 1.18;
        }

        .discover-card-content .based-on {
            margin: 12px 0 0;
            color: #5d5146;
        }

        .discover-card-content .book-author {
            margin: 7px 0 16px;
        }

        .discover-card-content .card-summary {
            margin-top: 0;
            line-height: 1.6;
        }


        /* Keeps footer aligned toward bottom of cards */

        .discover-card-content .card-footer {
            margin-top: auto;
        }


        /* ---------- Responsive ---------- */

        @media (max-width: 700px) {

            .discover-grid {
                grid-template-columns: 1fr;
            }

            .discover-feature-image {
                aspect-ratio: 16 / 9;
            }

            .discover-feature-content {
                padding: 24px;
            }

            .discover-feature-content h3 {
                font-size: 1.9rem;
            }

            .discover-card-content {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <header class="site-header">
        <h1 class="site-title">Book to Screen</h1>
    </header>

    <main>
        <section class="hero">
            <p class="eyebrow">Adaptation Tracker</p>

            <h2>Stories on their way to film and television.</h2>

            <p>
                Book to Screen tracks books, articles, podcasts, comics, and true stories
                being adapted for movies and TV.
            </p>
        </section>

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

                            <a
                                class="card-link"
                                href="<?= e($featured['source_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer">
                                Read article →
                            </a>

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

                <?php endif; ?>

            </section>
        <?php endif; ?>

        <section class="section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Recently Added</p>
                    <h2>Latest adaptations</h2>
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
                <div class="grid">
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

                                <a
                                    class="card-link"
                                    href="<?= e($adaptation['source_url']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer">
                                    Read article →
                                </a>

                            </div>

                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>

                    <nav class="pagination">

                        <?php if ($page > 1): ?>
                            <a href="/?page=<?= $page - 1 ?>">← Newer</a>
                        <?php endif; ?>

                        <span>Page <?= $page ?> of <?= $totalPages ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="/?page=<?= $page + 1 ?>">Older →</a>
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

    <footer class="site-footer">
        &copy; <?= date('Y') ?> Book to Screen ·
        <a href="/admin/">Editorial Administration</a>
    </footer>

</body>

</html>