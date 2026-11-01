<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/staging-local-config.php';

$checks = 0;
function local_config_check(bool $condition): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: offline private staging config check');
    $checks++;
}
$config = [
    'project_name'=>'book-to-screen-staging','database'=>'author-submissions-staging.sqlite',
    'expected_hostname'=>'',
    'connection_string'=>'sqlitecloud://staging.example.com:8860/author-submissions-staging.sqlite?apikey=synthetic-secret-key',
];
$report=submission_staging_local_check($config);
local_config_check($report['hostname']==='staging.example.com' && $report['port']===8860);
local_config_check(!$report['hostname_confirmed'] && !$report['connection_opened'] && !$report['queries_executed']);
local_config_check(!str_contains(json_encode($report,JSON_THROW_ON_ERROR),'synthetic-secret'));
$report=submission_staging_local_check(array_replace($config,['expected_hostname'=>'staging.example.com']));
local_config_check($report['hostname_confirmed'] && $report['status']==='CONFIGURED_AWAITING_SEPARATE_APPROVAL');
local_config_check(!class_exists('SQLiteCloud\\SQLiteCloudClient',false) && !defined('DB_DRIVER'));
foreach ([
    ['expected_hostname'=>'production.example.com'], ['project_name'=>'production'], ['database'=>'adaptations.sqlite'],
    ['connection_string'=>''], ['connection_string'=>[]], ['api_key'=>'synthetic-secret'],
    ['connection_string'=>$config['connection_string'].'&create=1'],
    ['connection_string'=>$config['connection_string'].'&insecure=1'],
    ['connection_string'=>str_replace('/author-submissions-staging.sqlite','',$config['connection_string'])],
    ['connection_string'=>str_replace('staging.example.com','localhost',$config['connection_string'])],
    ['connection_string'=>str_replace('staging.example.com','STAGING.example.com',$config['connection_string'])],
    ['connection_string'=>str_replace('synthetic-secret-key','secret%3BQUERY',$config['connection_string'])],
] as $change) {
    try {
        submission_staging_local_check(array_replace($config,$change));
    } catch (InvalidArgumentException $e) {
        local_config_check(!str_contains($e->getMessage(),'synthetic-secret'));
        continue;
    }
    throw new RuntimeException('FAIL: unsafe local config accepted');
}
$report=submission_staging_local_check(array_replace($config,['connection_string'=>str_replace(':8860','',$config['connection_string'])]));
local_config_check($report['port']===8860);
echo "Passed {$checks} offline private staging config checks; no files containing credentials read, no network or queries.\n";
