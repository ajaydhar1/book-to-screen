<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/local.php';
try {
    $db = submission_local_database();
    if (!$db->beginTransaction()) {
        throw new RuntimeException('Could not begin local cleanup.');
    }
    $now = time();
    $stmt = $db->prepare("DELETE FROM property_submissions
        WHERE (verified_at IS NULL AND created_at <= :unverified)
        OR (review_status IN ('closed','duplicate','spam') AND updated_at <= :closed)");
    $stmt->execute([':unverified' => $now - 7 * 86400, ':closed' => $now - 365 * 86400]);
    $removed = $stmt->rowCount();
    $stmt = $db->prepare('DELETE FROM submission_email_tokens WHERE expires_at <= :cutoff');
    $stmt->execute([':cutoff' => $now - 7 * 86400]);
    $stmt = $db->prepare('DELETE FROM submission_rate_limits WHERE window_started_at <= :cutoff');
    $stmt->execute([':cutoff' => $now - 86400]);
    if (!$db->commit()) {
        throw new RuntimeException('Could not commit cleanup.');
    }
    echo 'Removed local expired submissions: ' . $removed . PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Local cleanup failed.' . PHP_EOL);
    exit(1);
}
