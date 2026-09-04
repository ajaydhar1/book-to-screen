<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();

$query = trim($_GET['q'] ?? 'adaptation');
$page  = max(1, (int) ($_GET['page'] ?? 1));

$results = [];
$error = null;
$debugHtml = '';

/*
|--------------------------------------------------------------------------
| Build Deadline search URL
|--------------------------------------------------------------------------
|
| For the first POC we're only testing one Deadline search results page.
| We are NOT inserting anything into Book to Screen yet.
|
*/

$params = [
    'q' => $query,
    'size' => 'n_10_n',
    'sort-field' => 'relevance',
    'sort-direction' => 'desc',
];

$deadlineUrl = 'https://deadline.com/results/?' . http_build_query($params);


/*
|--------------------------------------------------------------------------
| Fetch Deadline page
|--------------------------------------------------------------------------
*/

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $deadlineUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,

    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml',
        'Accept-Language: en-US,en;q=0.9',
    ],

    CURLOPT_USERAGENT =>
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) ' .
        'Chrome/131.0 Safari/537.36',
]);

$html = curl_exec($ch);

if ($html === false) {
    $error = 'cURL error: ' . curl_error($ch);
}

$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

curl_close($ch);

if (!$error && $httpCode >= 400) {
    $error = 'Deadline returned HTTP ' . $httpCode;
}


/*
|--------------------------------------------------------------------------
| Parse search results
|--------------------------------------------------------------------------
*/

if (!$error && is_string($html) && $html !== '') {

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    if (!$dom->loadHTML($html)) {
        $error = 'Could not parse Deadline HTML.';
    } else {

        $xpath = new DOMXPath($dom);

        /*
        |--------------------------------------------------------------------------
        | Find candidate article links
        |--------------------------------------------------------------------------
        |
        | Deadline's exact search markup may change.
        |
        | Instead of depending on one CSS class for this POC, we collect links
        | that look like actual Deadline article URLs and then de-duplicate them.
        |
        */

        $links = $xpath->query('//a[@href]');

        $seenUrls = [];

        if ($links !== false) {

            foreach ($links as $link) {

                $url = trim((string) $link->getAttribute('href'));
                $title = trim(preg_replace('/\s+/', ' ', $link->textContent));

                if ($url === '' || $title === '') {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Only keep likely Deadline article URLs
                |--------------------------------------------------------------------------
                */

                if (
                    !str_starts_with($url, 'https://deadline.com/')
                    && !str_starts_with($url, 'http://deadline.com/')
                    && !str_starts_with($url, '/')
                ) {
                    continue;
                }

                if (str_starts_with($url, '/')) {
                    $url = 'https://deadline.com' . $url;
                }

                /*
                |--------------------------------------------------------------------------
                | Skip obvious navigation / utility URLs
                |--------------------------------------------------------------------------
                */

                $skipPatterns = [
                    '/results/',
                    '/category/',
                    '/tag/',
                    '/author/',
                    '/about/',
                    '/contact/',
                    '/privacy/',
                    '/terms/',
                    '/subscribe/',
                ];

                $skip = false;

                foreach ($skipPatterns as $pattern) {
                    if (str_contains($url, $pattern)) {
                        $skip = true;
                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Require a substantial title
                |--------------------------------------------------------------------------
                */

                if (mb_strlen($title) < 20) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Remove URL fragments and normalize
                |--------------------------------------------------------------------------
                */

                $url = preg_replace('/#.*$/', '', $url);

                if (isset($seenUrls[$url])) {
                    continue;
                }

                $seenUrls[$url] = true;


                /*
                |--------------------------------------------------------------------------
                | Look for nearby excerpt / date / image
                |--------------------------------------------------------------------------
                */

                $container = $link->parentNode;

                /*
                 * Walk upward a few levels so we hopefully reach the search-result card.
                 */

                for ($i = 0; $i < 4 && $container?->parentNode; $i++) {

                    $text = trim(
                        preg_replace('/\s+/', ' ', $container->textContent)
                    );

                    if (mb_strlen($text) > 100) {
                        break;
                    }

                    $container = $container->parentNode;
                }


                $excerpt = '';
                $publishedAt = '';
                $imageUrl = '';

                if ($container instanceof DOMNode) {

                    /*
                    |--------------------------------------------------------------------------
                    | Excerpt
                    |--------------------------------------------------------------------------
                    */

                    $paragraphs = $xpath->query('.//p', $container);

                    if ($paragraphs !== false) {
                        foreach ($paragraphs as $paragraph) {

                            $text = trim(
                                preg_replace('/\s+/', ' ', $paragraph->textContent)
                            );

                            if (mb_strlen($text) >= 40) {
                                $excerpt = $text;
                                break;
                            }
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Date
                    |--------------------------------------------------------------------------
                    */

                    $times = $xpath->query('.//time', $container);

                    if ($times !== false && $times->length > 0) {

                        $time = $times->item(0);

                        if ($time instanceof DOMElement) {

                            $publishedAt =
                                trim($time->getAttribute('datetime'))
                                ?: trim($time->textContent);
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Image
                    |--------------------------------------------------------------------------
                    */

                    $images = $xpath->query('.//img', $container);

                    if ($images !== false && $images->length > 0) {

                        $image = $images->item(0);

                        if ($image instanceof DOMElement) {

                            $imageUrl =
                                trim($image->getAttribute('src'))
                                ?: trim($image->getAttribute('data-src'))
                                ?: trim($image->getAttribute('data-lazy-src'));
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Check Book to Screen leads
                |--------------------------------------------------------------------------
                */

                $leadCheck = $db->prepare("
                    SELECT id, status
                    FROM leads
                    WHERE article_url = :url
                    LIMIT 1
                ");

                $leadCheck->execute([
                    ':url' => $url,
                ]);

                $existingLead = $leadCheck->fetch(PDO::FETCH_ASSOC);


                /*
                |--------------------------------------------------------------------------
                | Check published adaptations
                |--------------------------------------------------------------------------
                */

                $adaptationCheck = $db->prepare("
                    SELECT id
                    FROM adaptations
                    WHERE source_url = :url
                    LIMIT 1
                ");

                $adaptationCheck->execute([
                    ':url' => $url,
                ]);

                $existingAdaptation =
                    $adaptationCheck->fetch(PDO::FETCH_ASSOC);


                /*
                |--------------------------------------------------------------------------
                | Determine POC status
                |--------------------------------------------------------------------------
                */

                if ($existingAdaptation) {
                    $importStatus = 'adaptation';
                } elseif ($existingLead) {
                    $importStatus = 'lead';
                } else {
                    $importStatus = 'new';
                }


                $results[] = [
                    'title' => $title,
                    'url' => $url,
                    'excerpt' => $excerpt,
                    'published_at' => $publishedAt,
                    'image_url' => $imageUrl,
                    'import_status' => $importStatus,
                    'existing_lead_status' =>
                        $existingLead['status'] ?? null,
                ];


                /*
                |--------------------------------------------------------------------------
                | POC only: stop after 10 candidate results
                |--------------------------------------------------------------------------
                */

                if (count($results) >= 10) {
                    break;
                }
            }
        }
    }

    libxml_clear_errors();
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function poc_status_label(array $result): string
{
    return match ($result['import_status']) {
        'adaptation' => 'Already Published',
        'lead'       => 'Already a Lead',
        default      => 'New',
    };
}

function poc_status_class(array $result): string
{
    return match ($result['import_status']) {
        'adaptation' => 'status-published',
        'lead'       => 'status-existing',
        default      => 'status-new',
    };
}

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <title>Deadline Backfill POC | Book to Screen</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f2ea;
            color: #241c15;
        }

        .admin-shell {
            width: min(1100px, calc(100% - 40px));
            margin: 0 auto;
            padding: 40px 0 80px;
        }

        .admin-header {
            margin-bottom: 30px;
        }

        .admin-header p {
            margin: 0 0 8px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8a5a32;
        }

        .admin-header h1 {
            margin: 0 0 12px;
            font-size: 2.3rem;
        }

        .admin-header .description {
            max-width: 760px;
            margin: 0;
            line-height: 1.6;
            color: #65584c;
        }

        .search-card {
            margin-bottom: 26px;
            padding: 20px;
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 16px;
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 220px;
            padding: 11px 13px;
            border: 1px solid #cab9a6;
            border-radius: 10px;
            font: inherit;
        }

        .button {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 999px;
            background: #2b2118;
            color: white;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .meta-panel {
            margin-bottom: 26px;
            padding: 18px 20px;
            background: #fff;
            border: 1px solid #ded2c2;
            border-radius: 14px;
            line-height: 1.6;
        }

        .meta-panel code {
            word-break: break-all;
        }

        .error {
            margin-bottom: 26px;
            padding: 16px 18px;
            background: #fef2f2;
            border: 1px solid #f5a5a5;
            border-radius: 12px;
            color: #991b1b;
        }

        .summary {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .summary-card {
            min-width: 120px;
            padding: 14px 16px;
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 12px;
        }

        .summary-card span {
            display: block;
            margin-bottom: 4px;
            font-size: 0.78rem;
            text-transform: uppercase;
            color: #75685b;
        }

        .summary-card strong {
            font-size: 1.5rem;
        }

        .result-list {
            display: grid;
            gap: 16px;
        }

        .result-card {
            padding: 20px;
            background: #fffaf3;
            border: 1px solid #ded2c2;
            border-radius: 16px;
        }

        .result-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.85rem;
            color: #75685b;
        }

        .status {
            padding: 5px 9px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .status-new {
            background: #dcfce7;
            color: #166534;
        }

        .status-existing {
            background: #fef3c7;
            color: #92400e;
        }

        .status-published {
            background: #e0e7ff;
            color: #3730a3;
        }

        .result-card h2 {
            margin: 0 0 12px;
            font-size: 1.35rem;
            line-height: 1.25;
        }

        .result-card h2 a {
            color: inherit;
        }

        .excerpt {
            margin: 0 0 14px;
            line-height: 1.55;
            color: #5d5146;
        }

        .url {
            margin: 0;
            font-size: 0.82rem;
            word-break: break-all;
            color: #75685b;
        }

        .empty-state {
            padding: 24px;
            background: #fffaf3;
            border: 1px dashed #c9b8a4;
            border-radius: 14px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #5d5146;
        }

    </style>

</head>

<body>

<main class="admin-shell">

    <a class="back-link" href="/admin/leads.php">
        ← Back to Leads
    </a>

    <header class="admin-header">

        <p>Book to Screen POC</p>

        <h1>Historical Deadline Backfill</h1>

        <p class="description">
            Preview older Deadline search results and check whether each article
            already exists in Book to Screen. This page does not insert or modify
            any database records.
        </p>

    </header>


    <section class="search-card">

        <form class="search-form" method="get">

            <input
                type="search"
                name="q"
                value="<?= h($query) ?>"
                placeholder="Deadline search term">

            <button class="button" type="submit">
                Search Deadline
            </button>

        </form>

    </section>


    <div class="meta-panel">

        <strong>Requested URL:</strong><br>

        <code><?= h($deadlineUrl) ?></code>

        <?php if (!empty($finalUrl)): ?>

            <br><br>

            <strong>Final URL:</strong><br>

            <code><?= h($finalUrl) ?></code>

        <?php endif; ?>

        <br><br>

        <strong>HTTP status:</strong>
        <?= h((string) $httpCode) ?>

    </div>


    <?php if ($error): ?>

        <div class="error">
            <strong>Deadline request failed.</strong><br>
            <?= h($error) ?>
        </div>

    <?php else: ?>

        <?php

        $newCount = 0;
        $leadCount = 0;
        $publishedCount = 0;

        foreach ($results as $result) {

            switch ($result['import_status']) {

                case 'new':
                    $newCount++;
                    break;

                case 'lead':
                    $leadCount++;
                    break;

                case 'adaptation':
                    $publishedCount++;
                    break;
            }
        }

        ?>

        <section class="summary">

            <div class="summary-card">
                <span>Found</span>
                <strong><?= count($results) ?></strong>
            </div>

            <div class="summary-card">
                <span>New</span>
                <strong><?= $newCount ?></strong>
            </div>

            <div class="summary-card">
                <span>Existing Leads</span>
                <strong><?= $leadCount ?></strong>
            </div>

            <div class="summary-card">
                <span>Published</span>
                <strong><?= $publishedCount ?></strong>
            </div>

        </section>


        <?php if (empty($results)): ?>

            <div class="empty-state">

                <strong>No candidate articles were detected.</strong>

                <p>
                    This does not necessarily mean Deadline returned no results.
                    It may mean their search-page markup or automated-access
                    response differs from what this POC expects.
                </p>

            </div>

        <?php else: ?>

            <section class="result-list">

                <?php foreach ($results as $result): ?>

                    <article class="result-card">

                        <div class="result-meta">

                            <span class="status <?= h(poc_status_class($result)) ?>">
                                <?= h(poc_status_label($result)) ?>
                            </span>

                            <?php if (!empty($result['published_at'])): ?>

                                <span>
                                    <?= h($result['published_at']) ?>
                                </span>

                            <?php endif; ?>

                            <?php if (!empty($result['existing_lead_status'])): ?>

                                <span>
                                    Lead status:
                                    <?= h($result['existing_lead_status']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <h2>

                            <a
                                href="<?= h($result['url']) ?>"
                                target="_blank"
                                rel="noopener">

                                <?= h($result['title']) ?>

                            </a>

                        </h2>


                        <?php if (!empty($result['excerpt'])): ?>

                            <p class="excerpt">
                                <?= h($result['excerpt']) ?>
                            </p>

                        <?php endif; ?>


                        <p class="url">
                            <?= h($result['url']) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

    <?php endif; ?>

</main>

</body>

</html>