<?php

declare(strict_types=1);

/**
 * recommendation-wizard.php
 *
 * Standalone, stateless "Recommendation Wizard" MVP.
 * Walks a visitor through 4 questions (via GET query params) and
 * recommends one trailer-ready title from tmdb_adaptations.
 *
 * No new tables, no sessions, no APIs: candidate pool, filtering and
 * "why this pick" copy are all built from data B2S already stores.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/acclaimed-public.php';

$datasets = require __DIR__ . '/includes/acclaimed-datasets.php';

// --------------------------------------------------
// QUESTION DEFINITIONS
// --------------------------------------------------

const WIZARD_QUESTION_ORDER = ['discovery', 'era', 'rating', 'language'];

const WIZARD_QUESTIONS = [
    'discovery' => [
        'title' => 'What kind of discovery are you in the mood for?',
        'options' => [
            'acclaimed' => 'Acclaimed — recognized by a major film list',
            'popular' => 'Popular — well-known and widely watched',
            'less_obvious' => 'Less obvious — lower-profile, still reliably voted on',
            'surprise' => 'Surprise me — no preference',
        ],
    ],
    'era' => [
        'title' => 'Any era preference?',
        'options' => [
            'classic' => 'Classic (before 1980)',
            '80s_90s' => '1980s-1990s',
            '2000s_2010s' => '2000s-2010s',
            'recent' => 'Recent (2020 or later)',
            'any' => 'Any era',
        ],
    ],
    'rating' => [
        'title' => 'How much should the TMDb rating matter?',
        'options' => [
            'highly' => 'Highly rated (7.5+/10, at least 50 votes)',
            'well' => 'Well liked (6.0+/10, at least 20 votes)',
            'any' => "Doesn't matter",
        ],
    ],
    'language' => [
        'title' => 'English only, or open to anything?',
        'options' => [
            'en_only' => 'English only',
            'open' => 'Open to anything, including foreign language',
        ],
    ],
];

const WIZARD_MIN_CANDIDATES = 3;
const WIZARD_LESS_OBVIOUS_MIN_VOTE_COUNT = 15;

// Order in which active filters are relaxed if too few candidates match.
const WIZARD_RELAX_ORDER = ['rating', 'language', 'era', 'discovery'];

const WIZARD_RELAX_LABELS = [
    'rating' => 'rating preference',
    'language' => 'language preference',
    'era' => 'era',
    'discovery' => 'discovery style',
];

const WIZARD_LANGUAGE_NAMES = [
    'en' => 'English',
    'fr' => 'French',
    'es' => 'Spanish',
    'it' => 'Italian',
    'de' => 'German',
    'ja' => 'Japanese',
    'ko' => 'Korean',
    'zh' => 'Chinese',
    'pt' => 'Portuguese',
    'ru' => 'Russian',
    'sv' => 'Swedish',
    'da' => 'Danish',
    'no' => 'Norwegian',
    'nl' => 'Dutch',
    'hi' => 'Hindi',
    'ar' => 'Arabic',
    'pl' => 'Polish',
];

// --------------------------------------------------
// READ + VALIDATE ANSWERS FROM QUERY STRING
// --------------------------------------------------

function wizard_read_answers(): array
{
    $answers = [];

    foreach (WIZARD_QUESTION_ORDER as $key) {
        $value = $_GET[$key] ?? null;

        if (is_string($value) && isset(WIZARD_QUESTIONS[$key]['options'][$value])) {
            $answers[$key] = $value;
        }
    }

    return $answers;
}

function wizard_read_seen_ids(): array
{
    $raw = $_GET['seen'] ?? [];

    if (!is_array($raw)) {
        return [];
    }

    return array_values(array_unique(array_filter(
        array_map(static fn($id): int => (int) $id, $raw),
        static fn(int $id): bool => $id > 0
    )));
}

function wizard_first_unanswered_question(array $answers): ?string
{
    foreach (WIZARD_QUESTION_ORDER as $key) {
        if (!isset($answers[$key])) {
            return $key;
        }
    }

    return null;
}

function wizard_step_number(string $questionKey): int
{
    return array_search($questionKey, WIZARD_QUESTION_ORDER, true) + 1;
}

// --------------------------------------------------
// ACCLAIMED MEMBERSHIP (reuses includes/acclaimed-public.php)
// --------------------------------------------------

function wizard_build_acclaimed_membership(array $datasets): array
{
    $membership = [];

    foreach (ACCLAIMED_PUBLIC_COLLECTIONS as $collectionKey) {
        foreach (acclaimed_public_matched_items($datasets[$collectionKey]) as $item) {
            $tmdbId = (int) $item['tmdb_id'];
            $membership[$tmdbId][] = $collectionKey;
        }
    }

    return $membership;
}

// --------------------------------------------------
// POPULARITY PERCENTILES (relative to trailer-eligible catalog)
// --------------------------------------------------

function wizard_percentile(array $sortedAscending, float $percentile): ?float
{
    $count = count($sortedAscending);

    if ($count === 0) {
        return null;
    }

    $index = (int) ceil($percentile / 100 * $count) - 1;
    $index = max(0, min($count - 1, $index));

    return (float) $sortedAscending[$index];
}

function wizard_popularity_thresholds(Database $db): array
{
    $rows = $db->query("
        SELECT popularity
        FROM tmdb_adaptations
        WHERE trailer_youtube_key IS NOT NULL
          AND trailer_youtube_key <> ''
          AND popularity IS NOT NULL
        ORDER BY popularity ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $values = array_map('floatval', array_column($rows, 'popularity'));

    return [
        'p75' => wizard_percentile($values, 75.0),
        'p50' => wizard_percentile($values, 50.0),
    ];
}

// --------------------------------------------------
// BUILD ACTIVE FILTERS FROM ANSWERS
// --------------------------------------------------

function wizard_build_active_filters(array $answers, array $popularityThresholds, array $acclaimedIds): array
{
    $filters = [];

    // Discovery style
    switch ($answers['discovery'] ?? 'surprise') {
        case 'acclaimed':
            if ($acclaimedIds !== []) {
                $placeholders = [];
                $params = [];

                foreach (array_values($acclaimedIds) as $index => $id) {
                    $placeholder = ':acc_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $id;
                }

                $filters['discovery'] = [
                    'sql' => 'tmdb_id IN (' . implode(', ', $placeholders) . ')',
                    'params' => $params,
                ];
            } else {
                // No acclaimed matches exist at all: force an empty result
                // rather than silently ignoring the visitor's choice.
                $filters['discovery'] = [
                    'sql' => '1 = 0',
                    'params' => [],
                ];
            }
            break;

        case 'popular':
            if ($popularityThresholds['p75'] !== null) {
                $filters['discovery'] = [
                    'sql' => 'popularity >= :pop_p75',
                    'params' => [':pop_p75' => $popularityThresholds['p75']],
                ];
            }
            break;

        case 'less_obvious':
            if ($popularityThresholds['p50'] !== null) {
                $filters['discovery'] = [
                    'sql' => 'popularity <= :pop_p50 AND vote_count >= :min_vote_count',
                    'params' => [
                        ':pop_p50' => $popularityThresholds['p50'],
                        ':min_vote_count' => WIZARD_LESS_OBVIOUS_MIN_VOTE_COUNT,
                    ],
                ];
            }
            break;

        case 'surprise':
        default:
            break;
    }

    // Era
    switch ($answers['era'] ?? 'any') {
        case 'classic':
            $filters['era'] = [
                'sql' => "release_date IS NOT NULL AND release_date <> '' AND CAST(substr(release_date, 1, 4) AS INTEGER) <= 1979",
                'params' => [],
            ];
            break;

        case '80s_90s':
            $filters['era'] = [
                'sql' => "release_date IS NOT NULL AND release_date <> '' AND CAST(substr(release_date, 1, 4) AS INTEGER) BETWEEN 1980 AND 1999",
                'params' => [],
            ];
            break;

        case '2000s_2010s':
            $filters['era'] = [
                'sql' => "release_date IS NOT NULL AND release_date <> '' AND CAST(substr(release_date, 1, 4) AS INTEGER) BETWEEN 2000 AND 2019",
                'params' => [],
            ];
            break;

        case 'recent':
            $filters['era'] = [
                'sql' => "release_date IS NOT NULL AND release_date <> '' AND CAST(substr(release_date, 1, 4) AS INTEGER) >= 2020",
                'params' => [],
            ];
            break;

        case 'any':
        default:
            break;
    }

    // Rating
    switch ($answers['rating'] ?? 'any') {
        case 'highly':
            $filters['rating'] = [
                'sql' => 'vote_average >= 7.5 AND vote_count >= 50',
                'params' => [],
            ];
            break;

        case 'well':
            $filters['rating'] = [
                'sql' => 'vote_average >= 6.0 AND vote_count >= 20',
                'params' => [],
            ];
            break;

        case 'any':
        default:
            break;
    }

    // Language
    if (($answers['language'] ?? 'open') === 'en_only') {
        $filters['language'] = [
            'sql' => "original_language = 'en'",
            'params' => [],
        ];
    }

    return $filters;
}

// --------------------------------------------------
// QUERY HELPERS
// --------------------------------------------------

function wizard_base_where(array $filters, array $excludeIds): array
{
    $clauses = ["trailer_youtube_key IS NOT NULL", "trailer_youtube_key <> ''"];
    $params = [];

    foreach ($filters as $filter) {
        $clauses[] = '(' . $filter['sql'] . ')';
        $params = [...$params, ...$filter['params']];
    }

    if ($excludeIds !== []) {
        $placeholders = [];

        foreach (array_values($excludeIds) as $index => $id) {
            $placeholder = ':exclude_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        $clauses[] = 'tmdb_id NOT IN (' . implode(', ', $placeholders) . ')';
    }

    return [implode(' AND ', $clauses), $params];
}

function wizard_count_candidates(Database $db, array $filters, array $excludeIds): int
{
    [$where, $params] = wizard_base_where($filters, $excludeIds);

    $statement = $db->prepare("SELECT COUNT(*) FROM tmdb_adaptations WHERE {$where}");

    foreach ($params as $placeholder => $value) {
        $statement->bindValue(
            $placeholder,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $statement->execute();

    return (int) $statement->fetchColumn();
}

function wizard_pick_candidate(Database $db, array $filters, array $excludeIds): ?array
{
    [$where, $params] = wizard_base_where($filters, $excludeIds);

    $statement = $db->prepare("
        SELECT
            tmdb_id, title, original_title, overview, release_date,
            poster_path, original_language, vote_average, vote_count,
            popularity, source_author, trailer_youtube_key
        FROM tmdb_adaptations
        WHERE {$where}
        ORDER BY RANDOM()
        LIMIT 1
    ");

    foreach ($params as $placeholder => $value) {
        $statement->bindValue(
            $placeholder,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $statement->execute();

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/**
 * Resolves a candidate pick, resetting "seen" exclusions before relaxing
 * any of the visitor's actual answers, and relaxing filters in
 * WIZARD_RELAX_ORDER only as a last resort.
 */
function wizard_resolve_candidate(Database $db, array $filters, array $excludeIds): array
{
    $notes = [];
    $activeFilters = $filters;
    $activeExclude = $excludeIds;

    while (true) {
        $count = wizard_count_candidates($db, $activeFilters, $activeExclude);

        if ($count >= WIZARD_MIN_CANDIDATES) {
            break;
        }

        if ($activeExclude !== []) {
            $activeExclude = [];
            $notes[] = [
                'type' => 'seen_reset',
                'message' => "You've seen every match for these answers, so we started over.",
            ];
            continue;
        }

        $relaxed = false;

        foreach (WIZARD_RELAX_ORDER as $key) {
            if (isset($activeFilters[$key])) {
                unset($activeFilters[$key]);
                $notes[] = [
                    'type' => 'relaxed',
                    'key' => $key,
                    'message' => 'We loosened the ' . WIZARD_RELAX_LABELS[$key] . ' filter to find a match.',
                ];
                $relaxed = true;
                break;
            }
        }

        if (!$relaxed) {
            // Nothing left to relax; the trailer-eligible pool is exhausted.
            break;
        }
    }

    $finalCount = wizard_count_candidates($db, $activeFilters, $activeExclude);

    if ($finalCount === 0) {
        return ['candidate' => null, 'notes' => $notes, 'active_filters' => $activeFilters];
    }

    $candidate = wizard_pick_candidate($db, $activeFilters, $activeExclude);

    return ['candidate' => $candidate, 'notes' => $notes, 'active_filters' => $activeFilters];
}

// --------------------------------------------------
// "WHY THIS PICK" COPY (factual signals only)
// --------------------------------------------------

function wizard_why_this_pick(
    array $candidate,
    array $answers,
    array $activeFilters,
    array $membershipKeys,
    array $datasets,
    array $popularityThresholds
): array {
    $reasons = [];

    if ($membershipKeys !== []) {
        $names = array_map(
            static fn(string $key): string => $datasets[$key]['short_title'] ?? $datasets[$key]['title'],
            $membershipKeys
        );

        $reasons[] = 'Part of ' . implode(' and ', $names) . '.';
    }

    $year = null;

    if (!empty($candidate['release_date'])) {
        $year = substr((string) $candidate['release_date'], 0, 4);
        $reasons[] = 'Released in ' . $year . '.';
    }

    if ($candidate['vote_average'] !== null && $candidate['vote_count'] !== null) {
        $reasons[] = sprintf(
            'Rated %.1f/10 on TMDb from %s votes.',
            (float) $candidate['vote_average'],
            number_format((int) $candidate['vote_count'])
        );
    }

    if (isset($activeFilters['discovery'])) {
        $style = $answers['discovery'] ?? null;

        if ($style === 'popular' && $popularityThresholds['p75'] !== null) {
            $reasons[] = 'In the top 25% of our catalog by TMDb popularity.';
        } elseif ($style === 'less_obvious' && $popularityThresholds['p50'] !== null) {
            $reasons[] = 'In the lower half of our catalog by TMDb popularity, with at least '
                . WIZARD_LESS_OBVIOUS_MIN_VOTE_COUNT . ' TMDb votes.';
        }
    }

    $language = (string) ($candidate['original_language'] ?? '');

    if ($language !== '' && $language !== 'en' && ($answers['language'] ?? 'open') === 'open') {
        $languageName = WIZARD_LANGUAGE_NAMES[$language] ?? strtoupper($language);
        $reasons[] = 'Originally in ' . $languageName . '.';
    }

    if (!empty($candidate['source_author'])) {
        $reasons[] = 'Based on the book by ' . $candidate['source_author'] . '.';
    }

    return $reasons;
}

// --------------------------------------------------
// URL HELPERS
// --------------------------------------------------

function wizard_url(array $answers, array $seenIds = []): string
{
    $params = $answers;

    if ($seenIds !== []) {
        $params['seen'] = $seenIds;
    }

    return '/recommendation-wizard.php' . ($params !== [] ? '?' . http_build_query($params) : '');
}

// --------------------------------------------------
// MAIN
// --------------------------------------------------

$db = get_db();
$answers = wizard_read_answers();
$seenIds = wizard_read_seen_ids();
$currentQuestion = wizard_first_unanswered_question($answers);

$result = null;
$whyReasons = [];
$relaxNotes = [];
$noMatches = false;

if ($currentQuestion === null) {
    $popularityThresholds = wizard_popularity_thresholds($db);
    $acclaimedMembership = wizard_build_acclaimed_membership($datasets);
    $acclaimedIds = array_keys($acclaimedMembership);

    $filters = wizard_build_active_filters($answers, $popularityThresholds, $acclaimedIds);
    $resolution = wizard_resolve_candidate($db, $filters, $seenIds);

    $relaxNotes = $resolution['notes'];

    if ($resolution['candidate'] === null) {
        $noMatches = true;
    } else {
        $result = $resolution['candidate'];
        $membershipKeys = $acclaimedMembership[(int) $result['tmdb_id']] ?? [];

        $whyReasons = wizard_why_this_pick(
            $result,
            $answers,
            $resolution['active_filters'],
            $membershipKeys,
            $datasets,
            $popularityThresholds
        );
    }
}

$metaTitle = 'Recommendation Wizard | Book to Screen';
$metaDescription = 'Answer four quick questions and get one book-to-screen adaptation to watch next.';
$metaCanonical = 'https://booktoscreen.org/recommendation-wizard.php';
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
    <link rel="stylesheet" href="/assets/css/trailer-theater.css?v=<?= filemtime(__DIR__ . '/assets/css/trailer-theater.css') ?>">
    <link rel="stylesheet" href="/assets/css/recommendation-wizard.css?v=<?= filemtime(__DIR__ . '/assets/css/recommendation-wizard.css') ?>">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="wizard-shell">
        <header class="wizard-header">
            <p class="eyebrow">Book to Screen</p>
            <h1 class="page-title">Recommendation Wizard</h1>
            <p class="page-intro">Answer four quick questions and we'll suggest one book-to-screen adaptation to watch, pulled from our trailer-ready catalog.</p>
        </header>

        <?php if ($currentQuestion !== null): ?>
            <?php $question = WIZARD_QUESTIONS[$currentQuestion]; ?>
            <form class="wizard-question" method="get" action="/recommendation-wizard.php">
                <p class="wizard-progress">Question <?= wizard_step_number($currentQuestion) ?> of <?= count(WIZARD_QUESTION_ORDER) ?></p>
                <h2 class="wizard-question__title"><?= h($question['title']) ?></h2>

                <?php foreach ($answers as $key => $value): ?>
                    <input type="hidden" name="<?= h($key) ?>" value="<?= h($value) ?>">
                <?php endforeach; ?>

                <div class="wizard-options">
                    <?php foreach ($question['options'] as $optionValue => $optionLabel): ?>
                        <label class="wizard-option">
                            <input type="radio" name="<?= h($currentQuestion) ?>" value="<?= h($optionValue) ?>" required>
                            <span><?= h($optionLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button class="wizard-submit" type="submit">
                    <?= wizard_step_number($currentQuestion) === count(WIZARD_QUESTION_ORDER) ? 'See my recommendation' : 'Next' ?>
                </button>

                <?php if ($answers !== []): ?>
                    <a class="wizard-restart" href="/recommendation-wizard.php">Start over</a>
                <?php endif; ?>
            </form>
        <?php elseif ($noMatches): ?>
            <div class="wizard-empty">
                <p>We couldn't find a trailer-ready adaptation for these answers right now.</p>
                <a class="wizard-restart" href="/recommendation-wizard.php">Start over</a>
            </div>
        <?php else: ?>
            <?php
            $poster = acclaimed_public_poster_url($result['poster_path'] ?? null);
            $trailerKey = trim((string) ($result['trailer_youtube_key'] ?? ''));
            $bookUrl = barnes_and_noble_search_url(
                $result['title'] ?? null,
                $result['source_author'] ?? null
            );
            $nextSeenIds = [...$seenIds, (int) $result['tmdb_id']];
            ?>
            <section class="wizard-result">
                <?php if ($relaxNotes !== []): ?>
                    <div class="wizard-notes">
                        <?php foreach ($relaxNotes as $note): ?>
                            <p><?= h($note['message']) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="wizard-result__card">
                    <div class="wizard-result__poster-wrap">
                        <?php if ($poster !== null && $trailerKey !== ''): ?>
                            <button class="wizard-result__poster-button trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($result['title']) ?>" aria-label="Watch trailer for <?= h($result['title']) ?>">
                                <img src="<?= h($poster) ?>" alt="<?= h($result['title']) ?> poster" loading="lazy">
                            </button>
                        <?php elseif ($poster !== null): ?>
                            <img class="wizard-result__poster" src="<?= h($poster) ?>" alt="<?= h($result['title']) ?> poster" loading="lazy">
                        <?php else: ?>
                            <div class="wizard-result__empty">No poster</div>
                        <?php endif; ?>
                    </div>

                    <div class="wizard-result__body">
                        <p class="eyebrow">Your recommendation</p>
                        <h2><?= h($result['title']) ?></h2>

                        <?php if (!empty($result['overview'])): ?>
                            <p class="wizard-result__overview"><?= h($result['overview']) ?></p>
                        <?php endif; ?>

                        <?php if ($whyReasons !== []): ?>
                            <div class="wizard-result__why">
                                <p class="wizard-result__why-title">Why this pick</p>
                                <ul>
                                    <?php foreach ($whyReasons as $reason): ?>
                                        <li><?= h($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="wizard-result__actions">
                            <?php if ($trailerKey !== ''): ?>
                                <button class="wizard-result__trailer trailer-theater-trigger" type="button" data-trailer-key="<?= h($trailerKey) ?>" data-trailer-title="<?= h($result['title']) ?>">Watch Trailer</button>
                            <?php endif; ?>
                            <?php if ($bookUrl !== null): ?>
                                <a class="wizard-result__book" href="<?= h($bookUrl) ?>" target="_blank" rel="noopener">Find the Book</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="wizard-result__footer">
                    <a class="wizard-submit wizard-submit--link" href="<?= h(wizard_url($answers, $nextSeenIds)) ?>">Get another suggestion</a>
                    <a class="wizard-restart" href="/recommendation-wizard.php">Start over</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php require_once __DIR__ . '/includes/trailer-theater.php'; ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script src="/assets/js/trailer-theater.js?v=<?= filemtime(__DIR__ . '/assets/js/trailer-theater.js') ?>"></script>
</body>
</html>
