<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/local.php';
try {
    if (getenv('B2S_SUBMISSIONS_LOCAL') !== '1' || getenv('B2S_SUBMISSIONS_MAIL') !== 'mock') {
        throw new RuntimeException('Only the explicitly enabled local mock mailbox may be read.');
    }
    $crypto = new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY') ?: '');
    $path = getenv('B2S_SUBMISSIONS_MOCK_MAILBOX') ?: '';
    new LocalMockSubmissionMailer($path, $crypto);
    if (!is_file($path)) {
        throw new RuntimeException('No mocked verification messages captured yet.');
    }
    $messages = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($messages === false) {
        throw new RuntimeException('Could not read the local mock mailbox.');
    }
    foreach (array_slice($messages, -10) as $message) {
        $data = json_decode($crypto->decrypt($message), true, 16, JSON_THROW_ON_ERROR);
        echo "LOCAL MOCK ONLY\nTo: " . $data['email'] . "\nVerification link: " . $data['link'] . "\n\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Mock mailbox could not be read: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
