<?php

declare(strict_types=1);

/**
 * releases.php
 *
 * Month-by-month browser of films already stored in tmdb_adaptations,
 * grouped by release day. Upcoming (future-dated) releases are not synced.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-matching.php';

const RELEASES_PATH = '/releases.php';
const RELEASES_VALID_DATE = 'length(release_date) = 10';

function releases_month_url(string $ym): string
{
    return RELEASES_PATH . '?month=' . $ym;
}

function releases_month_label(string $ym): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m', $ym);

    return $date !== false ? $date->format('F Y') : $ym;
}

function releases_redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

// Returns YYYY-MM or null when the input is not a real month.
function releases_parse_month(mixed $month, mixed $year): ?string
{
    if (!is_string($month)) {
        return null;
    }

    if ($year !== null) {
        if (!is_string($year) || preg_match('/^\d{4}$/', $year) !== 1 || preg_match('/^(0[1-9]|1[0-2])$/', $month) !== 1) {
            return null;
        }

        $month = $year . '-' . $month;
    }

    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $parts) !== 1 || (int) $parts[1] < 1) {
        return null;
    }

    return $month;
}

// Nearest month with releases on either side, with its release count.
function releases_nearest_month(Database $db, string $direction, string $boundary): ?array
{
    $sql = $direction === 'before'
        ? 'SELECT substr(release_date, 1, 7) AS ym, COUNT(*) AS total FROM tmdb_adaptations
           WHERE release_date < :boundary AND ' . RELEASES_VALID_DATE . '
           GROUP BY ym ORDER BY ym DESC LIMIT 1'
        : 'SELECT substr(release_date, 1, 7) AS ym, COUNT(*) AS total FROM tmdb_adaptations
           WHERE release_date >= :boundary AND ' . RELEASES_VALID_DATE . '
           GROUP BY ym ORDER BY ym ASC LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':boundary', $boundary, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? ['ym' => (string) $row['ym'], 'total' => (int) $row['total']] : null;
}

$db = get_db();
$currentMonth = date('Y-m');
$loadError = false;

$minMonth = $currentMonth;
$maxMonth = $currentMonth;

try {
    $bounds = $db->query(
        'SELECT MIN(substr(release_date, 1, 7)) AS min_ym, MAX(substr(release_date, 1, 7)) AS max_ym
         FROM tmdb_adaptations WHERE ' . RELEASES_VALID_DATE
    )->fetch(PDO::FETCH_ASSOC);

    if (is_array($bounds) && !empty($bounds['min_ym'])) {
        $minMonth = min($currentMonth, (string) $bounds['min_ym']);
        $maxMonth = max($currentMonth, (string) $bounds['max_ym']);
    }
} catch (Throwable $e) {
    $loadError = true;
}

// --------------------------------------------------
// MONTH INPUT: validate, clamp, redirect to canonical URL
// --------------------------------------------------

$hasMonthInput = array_key_exists('month', $_GET) || array_key_exists('year', $_GET);
$ym = $currentMonth;

if ($hasMonthInput) {
    $parsed = releases_parse_month($_GET['month'] ?? null, $_GET['year'] ?? null);

    if ($parsed === null) {
        releases_redirect(RELEASES_PATH);
    }

    $ym = max($minMonth, min($maxMonth, $parsed));

    if (isset($_GET['year']) || $ym !== ($_GET['month'] ?? null)) {
        releases_redirect(releases_month_url($ym));
    }
}

$monthStart = new DateTimeImmutable($ym . '-01');
$startDate = $monthStart->format('Y-m-d');
$endDate = $monthStart->modify('+1 month')->format('Y-m-d');
$monthLabel = releases_month_label($ym);

$prevMonth = $ym > $minMonth ? $monthStart->modify('-1 month')->format('Y-m') : null;
$nextMonth = $ym < $maxMonth ? $monthStart->modify('+1 month')->format('Y-m') : null;

// --------------------------------------------------
// FETCH RELEASES
// --------------------------------------------------

$days = [];
$total = 0;
$nearestBefore = null;
$nearestAfter = null;

if (!$loadError) {
    try {
        $stmt = $db->prepare(
            'SELECT tmdb_id, title, overview, release_date, poster_path, vote_average,
                    source_author, trailer_youtube_key
             FROM tmdb_adaptations
             WHERE release_date >= :start AND release_date < :end AND ' . RELEASES_VALID_DATE . '
             ORDER BY release_date ASC, popularity DESC, tmdb_id ASC'
        );
        $stmt->bindValue(':start', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endDate, PDO::PARAM_STR);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $days[(string) $row['release_date']][] = $row;
            $total++;
        }

        if ($total === 0) {
            $nearestBefore = releases_nearest_month($db, 'before', $startDate);
            $nearestAfter = releases_nearest_month($db, 'after', $endDate);
        }
    } catch (Throwable $e) {
        $loadError = true;
        $days = [];
        $total = 0;
    }
}

$yearOptions = range((int) substr($maxMonth, 0, 4), (int) substr($minMonth, 0, 4));
[$selectedYear, $selectedMonthNumber] = explode('-', $ym);

$tmdbCardFriendlyDate = true;
$tmdbCardShowId = false;

$metaTitle = 'Book Adaptation Releases: ' . $monthLabel . ' | Book to Screen';
$metaDescription = 'Browse films adapted from books by release date. See what came out in ' . $monthLabel . ', then explore other months.';
$metaCanonical = 'https://booktoscreen.org' . releases_month_url($ym);

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

    <?php require __DIR__ . '/includes/meta.php'; ?>

    <?php if ($total === 0): ?>
        <meta name="robots" content="noindex, follow">
    <?php endif; ?>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/site.css?v=<?= filemtime(__DIR__ . '/assets/css/site.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailers.css?v=<?= filemtime(__DIR__ . '/assets/css/trailers.css') ?>">
    <link rel="stylesheet" href="/assets/css/releases.css?v=<?= filemtime(__DIR__ . '/assets/css/releases.css') ?>">
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/header.php'; ?>

    <main class="trailers-shell">

        <header class="trailers-header">
            <div>
                <p class="eyebrow">Book to Screen</p>
                <h1 class="page-title">Release Browser</h1>
                <p class="release-tagline">A time machine through adaptation history.</p>
                <p class="page-intro">
                    Explore films adapted from books by when they were released.
                    Dates come from TMDb, and upcoming releases aren't tracked yet.
                </p>
            </div>
        </header>

        <nav class="release-nav" aria-label="Browse by month">
            <?php if ($prevMonth !== null): ?>
                <a class="release-nav__link release-nav__link--prev" href="<?= h(releases_month_url($prevMonth)) ?>" rel="prev">
                    <span aria-hidden="true">←</span> <?= h(releases_month_label($prevMonth)) ?>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <div class="release-nav__current">
                <h2><?= h($monthLabel) ?></h2>
                <?php if ($total > 0): ?>
                    <p class="release-nav__count"><?= $total ?> <?= $total === 1 ? 'release' : 'releases' ?></p>
                <?php endif; ?>
            </div>

            <?php if ($nextMonth !== null): ?>
                <a class="release-nav__link release-nav__link--next" href="<?= h(releases_month_url($nextMonth)) ?>" rel="next">
                    <?= h(releases_month_label($nextMonth)) ?> <span aria-hidden="true">→</span>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </nav>

        <form class="release-jump" method="get" action="<?= h(RELEASES_PATH) ?>">
            <label class="sort-control__label" for="release-month">Month</label>
            <select class="sort-control__select" id="release-month" name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $monthValue = sprintf('%02d', $m); ?>
                    <option value="<?= $monthValue ?>" <?= $monthValue === $selectedMonthNumber ? 'selected' : '' ?>>
                        <?= h(DateTimeImmutable::createFromFormat('!m', $monthValue)->format('F')) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <label class="sort-control__label" for="release-year">Year</label>
            <select class="sort-control__select" id="release-year" name="year">
                <?php foreach ($yearOptions as $yearOption): ?>
                    <option value="<?= $yearOption ?>" <?= (string) $yearOption === $selectedYear ? 'selected' : '' ?>>
                        <?= $yearOption ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="trailer-search__button" type="submit">Go</button>

            <?php if ($ym !== $currentMonth): ?>
                <a class="shuffle-link" href="<?= h(RELEASES_PATH) ?>">Current month</a>
            <?php endif; ?>
        </form>

        <?php if ($loadError): ?>

            <div class="no-results release-empty">
                <h2>Releases are unavailable right now</h2>
                <p>We couldn't load the release archive. Please try again in a moment.</p>
            </div>

        <?php elseif ($total === 0): ?>

            <div class="no-results release-empty">
                <h2>Nothing in the archive for <?= h($monthLabel) ?></h2>
                <p>
                    No book-to-screen film releases are currently in the archive for
                    <?= h($monthLabel) ?>.
                </p>

                <?php if ($nearestBefore !== null || $nearestAfter !== null): ?>
                    <div class="release-empty__links">
                        <?php if ($nearestBefore !== null): ?>
                            <a class="no-results__clear" href="<?= h(releases_month_url($nearestBefore['ym'])) ?>">
                                <span aria-hidden="true">←</span>&nbsp;Browse <?= h(releases_month_label($nearestBefore['ym'])) ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($nearestAfter !== null): ?>
                            <a class="no-results__clear" href="<?= h(releases_month_url($nearestAfter['ym'])) ?>">
                                Browse <?= h(releases_month_label($nearestAfter['ym'])) ?>&nbsp;<span aria-hidden="true">→</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <?php foreach ($days as $date => $movies): ?>
                <?php $dayDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date); ?>
                <section class="release-day" aria-labelledby="day-<?= h((string) $date) ?>">
                    <h3 class="release-day__heading" id="day-<?= h((string) $date) ?>">
                        <time datetime="<?= h((string) $date) ?>">
                            <?= h($dayDate !== false ? $dayDate->format('l, F j') : (string) $date) ?>
                        </time>
                    </h3>

                    <div class="trailer-grid">
                        <?php foreach ($movies as $movie): ?>
                            <?php require __DIR__ . '/includes/tmdb-trailer-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>

</html>
