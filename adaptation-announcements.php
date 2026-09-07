<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();

$allowedAdaptationTypes = [
    'film',
    'tv-series',
    'limited-series',
];

$allowedSourceTypes = [
    'books',
    'memoirs-nonfiction',
    'comics-graphic-novels',
    'short-stories',
];

$currentAdaptationType = $_GET['adaptation_type'] ?? '';
$currentSourceType = $_GET['source_type'] ?? '';

if (!in_array($currentAdaptationType, $allowedAdaptationTypes, true)) {
    $currentAdaptationType = '';
}

if (!in_array($currentSourceType, $allowedSourceTypes, true)) {
    $currentSourceType = '';
}

function announcements_url(
    string $adaptationType = '',
    string $sourceType = '',
    int $page = 1
): string {
    $params = [];

    if ($adaptationType !== '') {
        $params['adaptation_type'] = $adaptationType;
    }

    if ($sourceType !== '') {
        $params['source_type'] = $sourceType;
    }

    if ($page > 1) {
        $params['page'] = $page;
    }

    return '/adaptation-announcements.php'
        . ($params ? '?' . http_build_query($params) : '');
}

function source_type_label(string $sourceType): string
{
    return match ($sourceType) {
        'books' => 'Books',
        'memoirs-nonfiction' => 'Memoirs & Nonfiction',
        'comics-graphic-novels' => 'Comics & Graphic Novels',
        'short-stories' => 'Short Stories',
        default => 'Other',
    };
}

function adaptation_type_label(string $adaptationType): string
{
    return match ($adaptationType) {
        'film' => 'Film',
        'tv-series' => 'TV Series',
        'limited-series' => 'Limited Series',
        default => 'Adaptation',
    };
}

/*
 * The dashboard has two required gates:
 *
 * 1. The article must contain an adaptation-announcement signal.
 * 2. The article must contain a recognized source-material signal.
 *
 * After that, CASE expressions classify the article for the two public
 * filter dimensions.
 */
$classifiedLeadsSql = <<<'SQL'
WITH classified_leads AS (
    SELECT
        id,
        source,
        article_title,
        article_url,
        article_excerpt,
        featured_image_url,
        published_at,
        status,
        notes,
        created_at,

        CASE
            WHEN article_text LIKE '%graphic novel%'
                OR article_text LIKE '%comic book%'
                OR article_text LIKE '%comics%'
            THEN 'comics-graphic-novels'

            WHEN article_text LIKE '%short story%'
                OR article_text LIKE '%short stories%'
            THEN 'short-stories'

            WHEN article_text LIKE '%memoir%'
                OR article_text LIKE '%nonfiction%'
                OR article_text LIKE '%non-fiction%'
            THEN 'memoirs-nonfiction'

            WHEN article_text LIKE '%novel%'
                OR article_text LIKE '%book%'
            THEN 'books'

            ELSE NULL
        END AS source_type,

        CASE
            WHEN article_text LIKE '%limited series%'
                OR article_text LIKE '%limited-series%'
                OR article_text LIKE '%miniseries%'
                OR article_text LIKE '%mini-series%'
            THEN 'limited-series'

            WHEN article_text LIKE '%tv series%'
                OR article_text LIKE '%television series%'
                OR article_text LIKE '%streaming series%'
                OR article_text LIKE '%series adaptation%'
                OR article_text LIKE '%series based on%'
                OR article_text LIKE '%series based upon%'
            THEN 'tv-series'

            WHEN article_text LIKE '%film%'
                OR article_text LIKE '%movie%'
                OR article_text LIKE '%feature adaptation%'
                OR article_text LIKE '%feature film%'
            THEN 'film'

            ELSE NULL
        END AS adaptation_type

    FROM (
        SELECT
            leads.*,
            LOWER(
                COALESCE(article_title, '') || ' ' ||
                COALESCE(article_excerpt, '')
            ) AS article_text
        FROM leads
    ) AS normalized_leads

    WHERE
        (
            article_text LIKE '%adaptation%'
            OR article_text LIKE '%adapted%'
            OR article_text LIKE '%adapting%'
            OR article_text LIKE '%to adapt%'
            OR article_text LIKE '%based on%'
            OR article_text LIKE '%based upon%'
            OR article_text LIKE '%optioned%'
        )
        AND
        (
            article_text LIKE '%novel%'
            OR article_text LIKE '%book%'
            OR article_text LIKE '%memoir%'
            OR article_text LIKE '%nonfiction%'
            OR article_text LIKE '%non-fiction%'
            OR article_text LIKE '%graphic novel%'
            OR article_text LIKE '%comic book%'
            OR article_text LIKE '%comics%'
            OR article_text LIKE '%short story%'
            OR article_text LIKE '%short stories%'
        )
)
SQL;

$where = [];
$params = [];

if ($currentAdaptationType !== '') {
    $where[] = 'adaptation_type = :adaptation_type';
    $params[':adaptation_type'] = $currentAdaptationType;
}

if ($currentSourceType !== '') {
    $where[] = 'source_type = :source_type';
    $params[':source_type'] = $currentSourceType;
}

$whereSql = $where
    ? ' WHERE ' . implode(' AND ', $where)
    : '';

$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));

$countStmt = $db->prepare(
    $classifiedLeadsSql
    . ' SELECT COUNT(*) FROM classified_leads'
    . $whereSql
);

foreach ($params as $name => $value) {
    $countStmt->bindValue($name, $value, PDO::PARAM_STR);
}

$countStmt->execute();

$totalAnnouncements = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalAnnouncements / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$listStmt = $db->prepare(
    $classifiedLeadsSql
    . ' SELECT * FROM classified_leads'
    . $whereSql
    . ' ORDER BY published_at DESC, id DESC'
    . ' LIMIT :limit OFFSET :offset'
);

foreach ($params as $name => $value) {
    $listStmt->bindValue($name, $value, PDO::PARAM_STR);
}

$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();

$announcements = $listStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
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

    <meta charset="utf-8">
    <title>Adaptation Announcements | Book to Screen</title>
    <meta
        name="description"
        content="Browse recent film, television, and limited-series adaptation announcements by source type.">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link
        rel="stylesheet"
        href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link
        rel="stylesheet"
        href="/assets/css/adaptation-announcements.css?v=<?= filemtime(__DIR__ . '/assets/css/adaptation-announcements.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="announcements-shell">

        <header class="announcements-header">
            <div>
                <p class="eyebrow">Book to Screen</p>
                <h1>Adaptation Announcements</h1>
                <p class="intro">
                    Track recent announcements for film, television, and limited-series adaptations.
                </p>
            </div>
        </header>

        <section class="filter-panel" aria-label="Adaptation announcement filters">

            <div class="filter-group">
                <span class="filter-label">Adaptation Type</span>

                <nav class="filter-bar" aria-label="Adaptation type">
                    <a
                        class="filter-link <?= $currentAdaptationType === '' ? 'active' : '' ?>"
                        href="<?= h(announcements_url('', $currentSourceType)) ?>">
                        All
                    </a>

                    <?php foreach ([
                        'film' => 'Film',
                        'tv-series' => 'TV Series',
                        'limited-series' => 'Limited Series',
                    ] as $value => $label): ?>
                        <a
                            class="filter-link <?= $currentAdaptationType === $value ? 'active' : '' ?>"
                            href="<?= h(announcements_url($value, $currentSourceType)) ?>">
                            <?= h($label) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="filter-group">
                <span class="filter-label">Source Type</span>

                <nav class="filter-bar" aria-label="Source type">
                    <a
                        class="filter-link <?= $currentSourceType === '' ? 'active' : '' ?>"
                        href="<?= h(announcements_url($currentAdaptationType, '')) ?>">
                        All
                    </a>

                    <?php foreach ([
                        'books' => 'Books',
                        'memoirs-nonfiction' => 'Memoirs & Nonfiction',
                        'comics-graphic-novels' => 'Comics & Graphic Novels',
                        'short-stories' => 'Short Stories',
                    ] as $value => $label): ?>
                        <a
                            class="filter-link <?= $currentSourceType === $value ? 'active' : '' ?>"
                            href="<?= h(announcements_url($currentAdaptationType, $value)) ?>">
                            <?= h($label) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

        </section>

        <div class="results-summary">
            <strong><?= h(number_format($totalAnnouncements)) ?></strong>
            <?= $totalAnnouncements === 1 ? 'announcement' : 'announcements' ?>
        </div>

        <?php if (empty($announcements)): ?>

            <div class="empty-state">
                No adaptation announcements found for these filters.
            </div>

        <?php else: ?>

            <section class="announcement-list">

                <?php foreach ($announcements as $announcement): ?>

                    <article class="announcement-card">

                        <div class="announcement-meta">
                            <?php if (!empty($announcement['adaptation_type'])): ?>
                                <span class="type-badge">
                                    <?= h(adaptation_type_label($announcement['adaptation_type'])) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($announcement['source_type'])): ?>
                                <span class="source-badge">
                                    <?= h(source_type_label($announcement['source_type'])) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($announcement['source'])): ?>
                                <span><?= h($announcement['source']) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($announcement['published_at'])): ?>
                                <span><?= h(format_datetime($announcement['published_at'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="announcement-image">
                            <?php if (!empty($announcement['featured_image_url'])): ?>
                                <img
                                    src="<?= h($announcement['featured_image_url']) ?>"
                                    alt=""
                                    loading="lazy">
                            <?php else: ?>
                                <div class="announcement-image-placeholder">
                                    No image available
                                </div>
                            <?php endif; ?>
                        </div>

                        <h2>
                            <a
                                href="<?= h($announcement['article_url']) ?>"
                                target="_blank"
                                rel="noopener">
                                <?= h($announcement['article_title']) ?>
                            </a>
                        </h2>

                        <?php if (!empty($announcement['article_excerpt'])): ?>
                            <p class="excerpt">
                                <?= h($announcement['article_excerpt']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="announcement-actions">
                            <a
                                class="button button-primary"
                                href="<?= h($announcement['article_url']) ?>"
                                target="_blank"
                                rel="noopener">
                                View Announcement
                            </a>
                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

            <?php if ($totalPages > 1): ?>

                <nav class="pagination" aria-label="Announcement pagination">

                    <div>
                        <?php if ($page > 1): ?>
                            <a
                                href="<?= h(announcements_url(
                                    $currentAdaptationType,
                                    $currentSourceType,
                                    $page - 1
                                )) ?>">
                                ← Newer
                            </a>
                        <?php endif; ?>
                    </div>

                    <span>
                        Page <?= h((string) $page) ?>
                        of <?= h((string) $totalPages) ?>
                    </span>

                    <div>
                        <?php if ($page < $totalPages): ?>
                            <a
                                href="<?= h(announcements_url(
                                    $currentAdaptationType,
                                    $currentSourceType,
                                    $page + 1
                                )) ?>">
                                Older →
                            </a>
                        <?php endif; ?>
                    </div>

                </nav>

            <?php endif; ?>

        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>
