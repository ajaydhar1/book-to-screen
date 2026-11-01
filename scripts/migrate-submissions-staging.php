<?php

declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
ini_set('log_errors','0');
require_once __DIR__.'/../includes/submissions/staging-migration.php';
require_once __DIR__.'/../includes/submissions/staging-local-config.php';

try {
    if ($argc!==3 || !in_array($argv[2],['--offline','--apply-empty-v1-v2'],true)) {
        throw new InvalidArgumentException('Usage: php scripts\migrate-submissions-staging.php <external-identity-manifest.json> --offline|--apply-empty-v1-v2');
    }
    $manifest=submission_staging_manifest($argv[1]);
    $plan=submission_staging_migration_plan($manifest);
    if ($argv[2]==='--offline') {
        echo json_encode($plan,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    $approval=getenv('B2S_SUBMISSIONS_STAGING_MIGRATION_APPROVAL')?:'';
    submission_staging_migration_approval($argv[2],$approval);
    $private=submission_staging_local_config();
    $checked=submission_staging_local_check($private);
    if (!$checked['hostname_confirmed'] || $checked['hostname']!==$plan['target']['hostname']
        || $checked['port']!==$plan['target']['port']) throw new RuntimeException('Private config identity mismatch.');
    $report=submission_staging_migration_run($manifest,$private['connection_string'],$argv[2],$approval);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Controlled staging migration stopped. No automatic retries or cleanup. Check identity/approval and obtain a recovery assessment if a connection was opened; committed versions may remain. Details suppressed.\n");
    exit(1);
}
