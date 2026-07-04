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
        book_author,
        adaptation_type,
        adaptation_status,
        source_name,
        source_url,
        source_published_at,
        article_title,
        article_excerpt,
        created_at
    FROM adaptations
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$adaptations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <link rel="canonical" href="https://your-domain.com/">

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

                            <div class="card-meta">
                                <span><?= e($adaptation['adaptation_type']) ?></span>

                                <?php if (!empty($adaptation['adaptation_status'])): ?>
                                    <span><?= e($adaptation['adaptation_status']) ?></span>
                                <?php endif; ?>
                            </div>

                            <h3><?= e($adaptation['book_title']) ?></h3>

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
                    a podcast, a comic, a short story, or a real-life event. This site collects
                    those signals so readers and viewers can discover the stories behind future
                    film and television projects.
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