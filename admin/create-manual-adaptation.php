<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Create Manual Adaptation';
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/create-adaptation.css?v=<?= filemtime(__DIR__ . '/../assets/css/create-adaptation.css') ?>">
</head>

<body>

    <main class="admin-page">

        <p>
            <a href="/admin/leads.php?status=pending">← Back to Leads</a>
        </p>

        <h1>Create Manual Adaptation</h1>

        <section class="admin-card">
            <p class="eyebrow">Book Details</p>

            <h2>Book and screen details</h2>

            <p class="admin-note">
                Enter adaptation and source details manually. Only Book Title is required; leave unknown information blank.
            </p>

            <form method="post" action="/admin/store-manual-adaptation.php">

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

                <p class="eyebrow" style="margin-top: 28px;">Source &amp; Article Metadata</p>

                <h2>Source and article details</h2>

                <p class="admin-note">
                    Provide optional source or article metadata if known.
                </p>

                <div class="form-group">
                    <label for="source_name">Source Name</label>
                    <input type="text" id="source_name" name="source_name">
                </div>

                <div class="form-group">
                    <label for="source_url">Source URL</label>
                    <input type="url" id="source_url" name="source_url">
                </div>

                <div class="form-group">
                    <label for="source_published_at">Source Published At</label>
                    <input type="text" id="source_published_at" name="source_published_at" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>

                <div class="form-group">
                    <label for="article_title">Article Title</label>
                    <input type="text" id="article_title" name="article_title">
                </div>

                <div class="form-group">
                    <label for="article_excerpt">Article Excerpt</label>
                    <textarea id="article_excerpt" name="article_excerpt" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="featured_image_url">Featured Image URL</label>
                    <input type="url" id="featured_image_url" name="featured_image_url">
                </div>

                <div class="form-actions">
                    <button type="submit">Create Adaptation</button>

                    <a href="/admin/leads.php?status=pending" class="button-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </section>

    </main>

</body>

</html>
