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
    if (defined('B2S_STAGING_BROWSER')) submission_browser_stop();
    error_log('Local submission lookup limit could not be checked.');
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'api_error', 'metadata' => null, 'message' => 'Lookup is temporarily unavailable. Manual entry remains available.']);
    exit;
}
session_write_close();
require_once __DIR__ . '/../includes/submissions/lookup-cache.php';
$bookMetadataLookup = static function (string $isbn): array {
    if (defined('B2S_STAGING_BROWSER') && $GLOBALS['submissionBrowserConfig']['mode'] === 'offline') {
        return book_metadata_lookup($isbn, static fn(string $url): array => ['items' => [[
            'volumeInfo' => ['title' => '[SYNTHETIC] Offline ISBN Lantern', 'authors' => ['Invented Author'],
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => $isbn]]],
        ]]], '');
    }
    try {
        $directory = defined('B2S_HOSTED_STAGING') && B2S_HOSTED_STAGING === true
            ? $GLOBALS['submissionBrowserConfig']['directory'] : dirname(getenv('B2S_SUBMISSIONS_DB'));
        return submission_cached_book_lookup($isbn, $directory . DIRECTORY_SEPARATOR . 'submissions-isbn-cache.json');
    } catch (Throwable $e) {
        error_log('Local bibliographic cache/lookup unavailable.');
        return ['status' => 'api_error', 'metadata' => null, 'message' => 'Lookup is temporarily unavailable or busy. Retry shortly, or enter your book manually.'];
    }
};
require __DIR__ . '/../submission-prototype/lookup.php';
