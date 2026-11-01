<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require_once __DIR__ . '/../includes/submissions/staging-diagnostic.php';
require_once __DIR__ . '/../includes/submissions/staging-local-config.php';

try {
    if (!in_array($argc, [3,4], true) || !in_array($argv[2], ['--offline', '--connect-readonly'], true)
        || ($argc === 4 && ($argv[3] !== '--local-config' || $argv[2] !== '--connect-readonly'))) {
        throw new InvalidArgumentException('Usage: php scripts\inspect-submissions-staging.php <external-diagnostic-manifest.json> --offline|--connect-readonly [--local-config]');
    }
    $manifest = submission_staging_manifest($argv[1]);
    $config = submission_staging_diagnostic_config($manifest);
    if ($argv[2] === '--offline') {
        echo json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit;
    }
    submission_staging_diagnostic_approval($argv[2], getenv('B2S_SUBMISSIONS_STAGING_READONLY_APPROVAL') ?: '');
    if ($argc === 4) {
        $private = submission_staging_local_config();
        $checked = submission_staging_local_check($private);
        if (!$checked['hostname_confirmed'] || $checked['hostname'] !== $config['target']['hostname']
            || $checked['port'] !== $config['target']['port']) {
            throw new InvalidArgumentException('Confirm the private hostname independently and match it to the external identity manifest before connecting.');
        }
        $url = $private['connection_string'];
    } else {
        $url = getenv('B2S_SUBMISSIONS_STAGING_READONLY_URL') ?: '';
    }
    $report = submission_staging_diagnostic_run($manifest, $url);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($report['no_application_schema_data'] ? 0 : 2);
} catch (Throwable $e) {
    // No traces/provider errors: only our sanitized validation and diagnostic messages.
    fwrite(STDERR, isset($argv[3]) ? "Private-config staging diagnostic failed; details suppressed. No writes attempted.\n" : ($e instanceof InvalidArgumentException || $e instanceof RuntimeException
        ? $e->getMessage() . PHP_EOL : "Staging diagnostic configuration failed; details suppressed.\n"));
    exit(1);
}
