<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/staging.php';

try {
    if ($argc !== 2) {
        throw new InvalidArgumentException('Usage: php scripts\prepare-submissions-staging.php <external-identity-manifest.json>');
    }
    $manifest = submission_staging_manifest($argv[1]);
    echo json_encode(submission_staging_plan($manifest), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
