<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$leads = [
    [
        'rss_guid' => 'fake-deadline-guid-001',
        'article_title' => 'Bestselling Fantasy Novel Lands Major Streaming Adaptation',
        'article_url' => 'https://deadline.com/fake/fantasy-novel-adaptation',
        'article_excerpt' => 'A bestselling fantasy novel is being developed as a new streaming series.',
        'published_at' => '2026-07-01 09:00:00',
        'status' => 'pending',
        'notes' => 'Looks like a strong book-to-screen lead.',
    ],
    [
        'rss_guid' => 'fake-deadline-guid-002',
        'article_title' => 'Award-Winning Memoir Set for Feature Film Treatment',
        'article_url' => 'https://deadline.com/fake/memoir-film-treatment',
        'article_excerpt' => 'An acclaimed memoir is being adapted into a feature film.',
        'published_at' => '2026-07-02 11:30:00',
        'status' => 'pending',
        'notes' => 'Possible nonfiction adaptation.',
    ],
    [
        'rss_guid' => 'fake-deadline-guid-003',
        'article_title' => 'Studio Announces New Original Sci-Fi Thriller',
        'article_url' => 'https://deadline.com/fake/original-sci-fi-thriller',
        'article_excerpt' => 'A studio has announced a new original sci-fi thriller not based on existing material.',
        'published_at' => '2026-07-03 08:15:00',
        'status' => 'ignored',
        'notes' => 'Seed example of a processed article that is probably not book-related.',
    ],
];

$stmt = $db->prepare("
    INSERT INTO leads (
        rss_guid,
        article_title,
        article_url,
        article_excerpt,
        published_at,
        status,
        notes
    )
    VALUES (
        :rss_guid,
        :article_title,
        :article_url,
        :article_excerpt,
        :published_at,
        :status,
        :notes
    )
");

foreach ($leads as $lead) {
    $stmt->execute($lead);
}

echo "Seed data inserted successfully.\n";