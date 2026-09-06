<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();

$leadId = isset($_GET['lead_id']) ? (int) $_GET['lead_id'] : 0;

if ($leadId <= 0) {
    http_response_code(400);
    exit('Missing or invalid lead ID.');
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM leads
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([':id' => $leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(404);
    exit('Lead not found.');
}

$pageTitle = 'Create Adaptation';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-LRF3X9CMCT');
    </script>

    <meta charset="UTF-8">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/create-adaptation.css?v=<?= filemtime(__DIR__ . '/../assets/css/create-adaptation.css') ?>">
</head>

<body>

    <main class="admin-page">

        <p>
            <a href="/admin/leads.php?status=pending">← Back to Leads</a>
        </p>

        <h1>Create Adaptation</h1>

        <section class="lead-list">
            <article class="lead-card">
                <div class="lead-meta">
                    <span class="status-badge <?= h(status_class($lead['status'])) ?>">
                        <?= h($lead['status']) ?>
                    </span>

                    <span><?= h($lead['source']) ?></span>

                    <?php if (!empty($lead['published_at'])): ?>
                        <span>Published: <?= h(format_datetime($lead['published_at'])) ?></span>
                    <?php endif; ?>

                    <span>Lead #<?= h((string) $lead['id']) ?></span>
                </div>

                <h2>
                    <a href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                        <?= h($lead['article_title']) ?>
                    </a>
                </h2>

                <?php if (!empty($lead['article_excerpt'])): ?>
                    <p class="excerpt"><?= h($lead['article_excerpt']) ?></p>
                <?php endif; ?>

                <?php if (!empty($lead['notes'])): ?>
                    <p class="notes"><?= h($lead['notes']) ?></p>
                <?php endif; ?>

                <div class="lead-actions">
                    <a class="button button-primary" href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                        View Article
                    </a>
                </div>
            </article>
        </section>

        <section class="admin-card">
            <p class="eyebrow">Book Details</p>

            <h2>Book and screen details</h2>

            <p class="admin-note">
                Complete the fields below using the imported article as your source. Leave unknown information blank.
            </p>

            <form method="post" action="/admin/store-adaptation.php">

                <input type="hidden" name="lead_id" value="<?= (int) $leadId ?>">

                <div class="form-group">
                    <label for="book_title">Book Title</label>
                    <input type="text" id="book_title" name="book_title" required>
                </div>

                <div class="form-group">
                    <label for="book_author">Book Author</label>
                    <input type="text" id="book_author" name="book_author">
                </div>

                <div class="form-group">
                    <label for="adaptation_title">Adaptation Title</label>
                    <input type="text" id="adaptation_title" name="adaptation_title">
                </div>

                <div class="form-group">
                    <label for="adaptation_type">Adaptation Type</label>
                    <select id="adaptation_type" name="adaptation_type">
                        <option value="">Unknown</option>
                        <option value="Film">Film</option>
                        <option value="Television Series">Television Series</option>
                        <option value="Limited Series">Limited Series</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="adaptation_status">Adaptation Status</label>
                    <select id="adaptation_status" name="adaptation_status">
                        <option value="In Development">In Development</option>
                        <option value="Optioned">Optioned</option>
                        <option value="Announced">Announced</option>
                        <option value="In Production">In Production</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Released">Released</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="short_note">Short Note</label>
                    <textarea id="short_note" name="short_note" rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit">Create Adaptation</button>

                    <a href="/admin/leads.php?status=pending" class="button-secondary">
                        Keep Pending
                    </a>
                </div>

            </form>
        </section>

    </main>

</body>

</html>