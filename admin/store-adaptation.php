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

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized or invalid user session.');
}

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
    header('Location: /admin/leads.php?status=pending&created=0');
    exit;
}

$sourceUrl = trim((string) ($lead['article_url'] ?? ''));

if ($sourceUrl !== '') {
    $duplicateStmt = $db->prepare(
        'SELECT id
         FROM adaptations
         WHERE trim(COALESCE(source_url, \'\')) <> \'\'
           AND lower(trim(source_url)) = lower(trim(:source_url))
         ORDER BY id ASC
         LIMIT 1'
    );
    $duplicateStmt->execute([':source_url' => $sourceUrl]);
    $duplicateAdaptationId = $duplicateStmt->fetchColumn();

    if ($duplicateAdaptationId !== false) {
        header(
            'Location: /admin/leads.php?status=pending&duplicate=1'
            . '&adaptation_id=' . (int) $duplicateAdaptationId
        );
        exit;
    }
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
            article_excerpt,
            featured_image_url,
            created_by_user_id
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
            :featured_image_url,
            :created_by_user_id
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
        ':source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        ':source_published_at' => $lead['published_at'] ?? null,
        ':article_title' => $lead['article_title'] ?? null,
        ':article_excerpt' => $lead['article_excerpt'] ?? null,
        ':featured_image_url' => $lead['featured_image_url'] ?? null,
        ':created_by_user_id' => $userId,
    ]);

    $update = $db->prepare(
        "UPDATE leads
         SET status = 'approved',
             reviewed_by_user_id = :reviewed_by_user_id,
             reviewed_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );

    $update->execute([
        ':reviewed_by_user_id' => $userId,
        ':id' => $leadId,
    ]);

    $db->commit();

    header('Location: /admin/leads.php?status=pending&created=1');
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    header('Location: /admin/leads.php?status=pending&created=0');
    exit;
}
