<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$allowedStatuses = ['ignored', 'rejected', 'flagged', 'pending'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$status = $_GET['status'] ?? '';

$returnStatus = $_GET['return_status'] ?? 'pending';
$allowedResearchers = ['all', 'sarah', 'researcher2'];
$returnResearcher = $_GET['return_researcher'] ?? 'all';
$returnSearch = trim(isset($_GET['search']) && is_string($_GET['search']) ? $_GET['search'] : '');

if (!in_array($returnResearcher, $allowedResearchers, true)) {
    $returnResearcher = 'all';
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;

$returnParams = [
    'status' => $returnStatus,
    'researcher' => $returnResearcher,
    'page' => $page,
];

if ($returnSearch !== '') {
    $returnParams['search'] = $returnSearch;
}

if (!$id || !in_array($status, $allowedStatuses, true)) {
    $returnParams['notice'] = 'invalid';
    header('Location: leads.php?' . http_build_query($returnParams));
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Unauthorized or invalid user session.');
}

if ($status === 'pending') {
    $stmt = $db->prepare("
        UPDATE leads
        SET status = :status,
            reviewed_by_user_id = NULL,
            reviewed_at = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':status' => 'pending',
        ':id' => $id,
    ]);
} else {
    $stmt = $db->prepare("
        UPDATE leads
        SET status = :status,
            reviewed_by_user_id = :reviewed_by_user_id,
            reviewed_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':status' => $status,
        ':reviewed_by_user_id' => $userId,
        ':id' => $id,
    ]);
}

$returnParams['notice'] = $status;
header('Location: leads.php?' . http_build_query($returnParams));
exit;