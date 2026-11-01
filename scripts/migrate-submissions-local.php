<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/local.php';
require_once __DIR__ . '/../includes/submissions/migrations.php';

try {
    $versions = submission_migrate(submission_local_database(), new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY') ?: ''));
    echo $versions === [] ? "Submission schema is current.\n" : 'Applied submission migrations: ' . implode(', ', $versions) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
