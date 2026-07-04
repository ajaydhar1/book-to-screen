<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$db = get_db();

$leadId = (int) ($_POST['lead_id'] ?? 0);

$bookTitle = trim($_POST['book_title'] ?? '');
$bookAuthor = trim($_POST['book_author'] ?? '');
$adaptationTitle = trim($_POST['adaptation_title'] ?? '');
$adaptationType = trim($_POST['adaptation_type'] ?? '');
$adaptationStatus = trim($_POST['adaptation_status'] ?? 'In Development');
$shortNote = trim($_POST['short_note'] ?? '');

if ($leadId <= 0 || $bookTitle === '') {
    header('Location: /admin/leads.php?created=0');
    exit;
}

$stmt = $db->prepare(
    'SELECT *
     FROM leads
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([':id' => $leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    header('Location: /admin/leads.php?created=0');
    exit;
}

$db->beginTransaction();

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
            article_excerpt
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
            :article_excerpt
        )'
    );

    $insert->execute([
        ':lead_id' => $leadId,
        ':book_title' => $bookTitle,
        ':book_author' => $bookAuthor,
        ':adaptation_title' => $adaptationTitle,
        ':adaptation_type' => $adaptationType,
        ':adaptation_status' => $adaptationStatus,
        ':short_note' => $shortNote,
        ':source_name' => $lead['source'] ?? null,
        ':source_url' => $lead['article_url'] ?? null,
        ':source_published_at' => $lead['published_at'] ?? null,
        ':article_title' => $lead['article_title'] ?? null,
        ':article_excerpt' => $lead['article_excerpt'] ?? null,
    ]);

    $update = $db->prepare(
        "UPDATE leads
         SET status = 'approved',
             reviewed_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );

    $update->execute([':id' => $leadId]);

    $db->commit();

    header('Location: /admin/leads.php?created=1');
    exit;
} catch (Throwable $e) {
    $db->rollBack();

    header('Location: /admin/leads.php?created=0');
    exit;
}