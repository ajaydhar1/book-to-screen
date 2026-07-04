<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$allowedStatuses = ['ignored', 'rejected'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$status = $_GET['status'] ?? '';

$returnStatus = $_GET['return_status'] ?? 'pending';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;

if (!$id || !in_array($status, $allowedStatuses, true)) {
    header('Location: leads.php?status=' . urlencode($returnStatus) . '&page=' . $page . '&notice=invalid');
    exit;
}

$stmt = $db->prepare("
    UPDATE leads
    SET status = :status,
        reviewed_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id
");

$stmt->execute([
    ':status' => $status,
    ':id' => $id,
]);

header('Location: leads.php?status=' . urlencode($returnStatus) . '&page=' . $page . '&notice=' . urlencode($status));
exit;