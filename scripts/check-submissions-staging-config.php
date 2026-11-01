<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require_once __DIR__ . '/../includes/submissions/staging-local-config.php';

try {
    if ($argc !== 1) throw new InvalidArgumentException('This offline checker accepts no arguments or credentials.');
    echo json_encode(submission_staging_local_check(submission_staging_local_config()), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable) {
    // The user-editable PHP config can produce parse errors containing secret text.
    fwrite(STDERR, "Private staging configuration is missing, invalid, or has a hostname mismatch. Check it privately against the guide; details suppressed. No connection was opened.\n");
    exit(1);
}
