<?php

declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); ini_set('log_errors','0');
require_once __DIR__.'/../includes/submissions/staging-synthetic.php';
require_once __DIR__.'/../includes/submissions/staging-local-config.php';
try {
    if ($argc!==3 || !in_array($argv[2],['--offline','--test-three-synthetic'],true)) throw new RuntimeException('Invalid CLI mode.');
    $manifest=submission_staging_manifest($argv[1]);
    $plan=submission_staging_migration_plan($manifest);
    if ($argv[2]==='--offline') {
        echo json_encode(['target'=>$plan['target'],'operation'=>'three-synthetic-v1','connection_opened'=>false,
            'writes_authorized'=>false,'fictional_properties'=>submission_staging_synthetic_properties()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    $approval=getenv('B2S_SUBMISSIONS_STAGING_SYNTHETIC_APPROVAL')?:'';
    submission_staging_synthetic_approval($argv[2],$approval);
    $key=getenv('B2S_SUBMISSIONS_STAGING_KEY')?:'';
    new SubmissionCrypto($key);
    $private=submission_staging_local_config();
    $checked=submission_staging_local_check($private);
    if (!$checked['hostname_confirmed'] || $checked['hostname']!==$plan['target']['hostname'] || $checked['port']!==$plan['target']['port']) throw new RuntimeException('Identity mismatch.');
    $report=submission_staging_synthetic_run($manifest,$private['connection_string'],$key,$argv[2],$approval);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Synthetic staging test stopped; details suppressed. Preserve the original key. Committed synthetic operations may remain; no retries or cleanup were performed.\n");
    exit(1);
}
