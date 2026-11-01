<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
try {
    submission_rate_limit($submissionDb, new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY')),
        'lookup', $_SERVER['REMOTE_ADDR'], time(), 30, 3600);
} catch (SubmissionRateException $e) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'api_error', 'metadata' => null, 'message' => $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    error_log('Local submission lookup limit could not be checked.');
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'api_error', 'metadata' => null, 'message' => 'Lookup is temporarily unavailable. Manual entry remains available.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../includes/submissions/lookup-cache.php';
$bookMetadataLookup = static function (string $isbn): array {
    try {
        return submission_cached_book_lookup($isbn, dirname(getenv('B2S_SUBMISSIONS_DB')) . DIRECTORY_SEPARATOR . 'submissions-isbn-cache.json');
    } catch (Throwable $e) {
        error_log('Local bibliographic cache/lookup unavailable.');
        return ['status' => 'api_error', 'metadata' => null, 'message' => 'Lookup is temporarily unavailable or busy. Retry shortly, or enter your book manually.'];
    }
};
require __DIR__ . '/../submission-prototype/lookup.php';
