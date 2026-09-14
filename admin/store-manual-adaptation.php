<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$db = get_db();

$bookTitle = trim($_POST['book_title'] ?? '');
$bookAuthor = trim($_POST['book_author'] ?? '');
$adaptationTitle = trim($_POST['adaptation_title'] ?? '');
$adaptationType = trim($_POST['adaptation_type'] ?? '');
$adaptationStatus = trim($_POST['adaptation_status'] ?? 'In Development');
$shortNote = trim($_POST['short_note'] ?? '');

$sourceName = trim($_POST['source_name'] ?? '');
$sourceUrl = trim($_POST['source_url'] ?? '');
$sourcePublishedAt = trim($_POST['source_published_at'] ?? '');
$articleTitle = trim($_POST['article_title'] ?? '');
$articleExcerpt = trim($_POST['article_excerpt'] ?? '');
$featuredImageUrl = trim($_POST['featured_image_url'] ?? '');

if ($bookTitle === '') {
    header('Location: /admin/leads.php?created=0');
    exit;
}

try {
    $insert = $db->prepare(
        'INSERT INTO adaptations (
            lead_id,
            book_title,
            book_author,
            adaptation_title,
            adaptation_type,
            adaptation_status,
            short_note,
            source_name,
            source_url,
            source_published_at,
            article_title,
            article_excerpt,
            featured_image_url
        )
        VALUES (
            :lead_id,
            :book_title,
            :book_author,
            :adaptation_title,
            :adaptation_type,
            :adaptation_status,
            :short_note,
            :source_name,
            :source_url,
            :source_published_at,
            :article_title,
            :article_excerpt,
            :featured_image_url
        )'
    );

    $insert->execute([
        ':lead_id' => null,
        ':book_title' => $bookTitle,
        ':book_author' => $bookAuthor !== '' ? $bookAuthor : null,
        ':adaptation_title' => $adaptationTitle !== '' ? $adaptationTitle : null,
        ':adaptation_type' => $adaptationType !== '' ? $adaptationType : null,
        ':adaptation_status' => $adaptationStatus !== '' ? $adaptationStatus : 'In Development',
        ':short_note' => $shortNote !== '' ? $shortNote : null,
        ':source_name' => $sourceName !== '' ? $sourceName : null,
        ':source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        ':source_published_at' => $sourcePublishedAt !== '' ? $sourcePublishedAt : null,
        ':article_title' => $articleTitle !== '' ? $articleTitle : null,
        ':article_excerpt' => $articleExcerpt !== '' ? $articleExcerpt : null,
        ':featured_image_url' => $featuredImageUrl !== '' ? $featuredImageUrl : null,
    ]);

    header('Location: /admin/leads.php?created=1');
    exit;
} catch (Throwable $e) {
    header('Location: /admin/leads.php?created=0');
    exit;
}
