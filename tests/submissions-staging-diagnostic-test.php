<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/staging-diagnostic.php';
require_once __DIR__ . '/../vendor/autoload.php';

use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

final class StagingDiagnosticMockClient extends SQLiteCloudClient
{
    public array $commands = [];
    public array $responses = [];
    public array $connection = [];
    public bool $closed = false;
    public bool $connectResult = true;
    public bool $disconnectFailure = false;
    public ?string $warningOn = null;

    public function __construct()
    {
        $this->responses = [
            'GET DATABASE NAME' => 'author-submissions-staging.sqlite', 'GET USER' => 'admin',
            'PRAGMA database_list' => self::rowset([['seq'=>0,'name'=>'main','file'=>'/cloud/author-submissions-staging.sqlite']]),
            'SELECT sqlite_version() AS sqlite_version' => self::rowset([['sqlite_version'=>'3.45.0']]),
            'PRAGMA foreign_keys' => self::rowset([['foreign_keys'=>0]]),
            'PRAGMA query_only' => self::rowset([['query_only'=>0]]),
            'PRAGMA journal_mode' => self::rowset([['journal_mode'=>'wal']]),
            'SELECT type,name,tbl_name,sql FROM main.sqlite_schema ORDER BY type,name LIMIT 101' => self::rowset([], ['type','name','tbl_name','sql']),
        ];
    }
    public static function rowset(array $rows, array $columns = []): SQLiteCloudRowset
    {
        $result = new SQLiteCloudRowset();
        $result->colname = $rows === [] ? $columns : array_keys($rows[0]);
        $result->ncols = count($result->colname);
        $result->nrows = count($rows);
        $result->data = [];
        foreach ($rows as $row) array_push($result->data, ...array_values($row));
        return $result;
    }
    public function connect($hostname = 'localhost', $port = 8860)
    {
        $this->connection = ['host'=>$hostname,'port'=>$port,'database'=>$this->database,
            'create'=>$this->create,'memory'=>$this->memory,'insecure'=>$this->insecure,
            'no_verify_certificate'=>$this->no_verify_certificate, 'timeout'=>$this->timeout,
            'connect_timeout'=>$this->connect_timeout,'non_linearizable'=>$this->non_linearizable,
            'apikey'=>$this->apikey,'username'=>$this->username,'password'=>$this->password];
        return $this->connectResult;
    }
    public function execute($command)
    {
        $this->commands[] = $command;
        if ($command === $this->warningOn) trigger_error('synthetic-secret-in-provider-warning', E_USER_WARNING);
        if (!array_key_exists($command, $this->responses)) throw new RuntimeException('Unexpected command: ' . $command);
        $result = $this->responses[$command];
        if ($result instanceof Throwable) throw $result;
        return $result;
    }
    public function disconnect()
    {
        $this->closed = true;
        if ($this->disconnectFailure) {
            $this->disconnectFailure = false;
            throw new RuntimeException('synthetic-secret-in-disconnect');
        }
    }
}

$checks = 0;
function diagnostic_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function diagnostic_reject(callable $operation): void
{
    try {
        $operation();
    } catch (InvalidArgumentException | RuntimeException $e) {
        diagnostic_check(!str_contains($e->getMessage(), 'synthetic-secret'), 'Rejection is explicit and sanitized');
        return;
    }
    throw new RuntimeException('FAIL: unsafe diagnostic accepted');
}

$manifest = [
    'identity'=>[
        'environment'=>'staging','project_id'=>'synthetic-staging-id','hostname'=>'staging.example.com','port'=>8860,
        'database'=>'author-submissions-staging.sqlite','production_project_id'=>'synthetic-production-id',
        'production_hostname'=>'production.example.com','production_database'=>'adaptations.sqlite','synthetic_only'=>true,
    ],
    'project_name'=>'book-to-screen-staging','control_panel_identity_confirmed'=>true,
    'credential_mode'=>'staging-admin','expected_user'=>'admin','admin_risk_approved'=>true,
];
$url = 'sqlitecloud://staging.example.com:8860/author-submissions-staging.sqlite?apikey=synthetic-secret-key';
$config = submission_staging_diagnostic_config($manifest);
diagnostic_check(!$config['connection_opened'] && !$config['writes_authorized'], 'Offline config never connects or authorizes writes');
diagnostic_check(!class_exists('SQLiteCloudDatabase', false) && !defined('DB_DRIVER'), 'No application factory/config loaded');
$client = new StagingDiagnosticMockClient();
$report = submission_staging_diagnostic_run($manifest, $url, $client);
diagnostic_check($report['appears_empty'] && $report['status']==='READ_ONLY_CHECKS_COMPLETE', 'Empty mock inspected');
diagnostic_check($report['no_application_schema_data'] && $report['schema_assessment']==='No application schema/data', 'Empty database has no application schema/data');
diagnostic_check($client->commands===$config['read_commands'], 'Exact documented read-only command sequence');
diagnostic_check($client->closed, 'Client disconnected');
diagnostic_check(!$client->connection['create'] && !$client->connection['memory'] && !$client->connection['insecure']
    && !$client->connection['no_verify_certificate'] && !$client->connection['non_linearizable'], 'No create/memory/unsafe TLS or consistency options');
diagnostic_check($client->connection['host']==='staging.example.com' && $client->connection['database']==='author-submissions-staging.sqlite'
    && $client->connection['connect_timeout']===5 && $client->connection['timeout']===10, 'Exact target and bounded socket timeouts');
diagnostic_check($report['foreign_keys']===0 && $report['query_only']===0 && count($report['warnings'])===3, 'Settings reported, not changed');
diagnostic_check(!$report['isolation_programmatically_proven'] && !$report['credential_permissions_verified']
    && !$report['writes_authorized'], 'No false isolation or permissions claims');
diagnostic_check(!str_contains(json_encode($report, JSON_THROW_ON_ERROR), 'synthetic-secret'), 'Report has no credentials');
$client=new StagingDiagnosticMockClient();
$client->responses['PRAGMA foreign_keys']=StagingDiagnosticMockClient::rowset([['foreign_keys'=>'1']]);
$client->responses['PRAGMA query_only']=StagingDiagnosticMockClient::rowset([['query_only'=>1]]);
$report=submission_staging_diagnostic_run($manifest,$url,$client);
diagnostic_check($report['foreign_keys']===1 && $report['query_only']===1 && count($report['warnings'])===1,
    'Read-only/FK-enabled settings reported accurately without setters');

foreach ([
    ['project_name'=>'production'], ['control_panel_identity_confirmed'=>false], ['admin_risk_approved'=>false],
    ['credential_mode'=>'CloudSync'], ['credential_mode'=>'restricted-user'], ['expected_user'=>'admin;DROP'],
    ['extra_secret'=>'synthetic-secret'],
] as $change) {
    diagnostic_reject(fn()=>submission_staging_diagnostic_config(array_replace($manifest,$change)));
}
foreach (['hostname'=>'production.example.com','database'=>'adaptations.sqlite','project_id'=>'synthetic-production-id'] as $field=>$value) {
    $bad=$manifest;
    $bad['identity'][$field]=$value;
    diagnostic_reject(fn()=>submission_staging_diagnostic_config($bad));
}
foreach ([
    '', 'sqlitecloud://production.example.com:8860/author-submissions-staging.sqlite?apikey=synthetic-secret-key',
    'sqlitecloud://staging.example.com:8860/adaptations.sqlite?apikey=synthetic-secret-key',
    'sqlitecloud://staging.example.com:8860?apikey=synthetic-secret-key',
    str_replace('8860','8861',$url), str_replace('sqlitecloud://','https://',$url), $url.'#fragment',
    $url.'&create=1', $url.'&insecure=1', $url.'&no_verify_certificate=1', $url.'&timeout=0',
    $url.'&apikey=another', str_replace('apikey=','apikey[]=',$url),
    str_replace('synthetic-secret-key','key%3BAUTH%20USER%20admin',$url),
    str_replace('synthetic-secret-key','0',$url),
    str_replace('staging.example.com:8860','admin:synthetic-secret-password@staging.example.com:8860',$url),
    " ".$url, str_replace('/author-', '/extra/author-', $url),
    str_replace('author-submissions', 'author%2dsubmissions', $url),
] as $badUrl) {
    $unconnected = new StagingDiagnosticMockClient();
    diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$badUrl,$unconnected));
    diagnostic_check($unconnected->connection===[], 'Invalid target/auth rejected before connecting');
}
$passwordUrl='sqlitecloud://admin:synthetic-secret-password@staging.example.com:8860/author-submissions-staging.sqlite';
$client=new StagingDiagnosticMockClient();
submission_staging_diagnostic_run($manifest,$passwordUrl,$client);
diagnostic_check($client->connection['username']==='admin' && $client->connection['apikey']==='', 'Username/password auth supported without URL parser');
$restricted=array_replace($manifest,['credential_mode'=>'restricted-user','expected_user'=>'stage_reader','admin_risk_approved'=>false]);
$client=new StagingDiagnosticMockClient();
$client->responses['GET USER']='stage_reader';
$restrictedUrl=str_replace('admin:', 'stage_reader:', $passwordUrl);
submission_staging_diagnostic_run($restricted,$restrictedUrl,$client);
diagnostic_check($client->connection['username']==='stage_reader', 'Documented restricted-user authentication supported');
diagnostic_reject(fn()=>submission_staging_diagnostic_run($restricted,$url,new StagingDiagnosticMockClient()));

foreach ([
    ['GET DATABASE NAME','adaptations.sqlite'], ['GET DATABASE NAME',false],
    ['GET DATABASE NAME',new RuntimeException('synthetic-secret-provider-denial')],
    ['GET USER','different_user'],
    ['PRAGMA database_list',StagingDiagnosticMockClient::rowset([['seq'=>0,'name'=>'main','file'=>'/unknown/db.sqlite']])],
    ['PRAGMA database_list',StagingDiagnosticMockClient::rowset([['seq'=>0,'name'=>'main','file'=>'']])],
    ['PRAGMA database_list',StagingDiagnosticMockClient::rowset([['seq'=>0,'name'=>'main','file'=>'author-submissions-staging.sqlite'],
        ['seq'=>2,'name'=>'attached','file'=>'other.sqlite']])],
    ['PRAGMA foreign_keys',StagingDiagnosticMockClient::rowset([],['foreign_keys'])],
    ['PRAGMA query_only',StagingDiagnosticMockClient::rowset([['query_only'=>2]])],
    ['PRAGMA journal_mode',true],
] as [$command,$response]) {
    $client=new StagingDiagnosticMockClient();
    $client->responses[$command]=$response;
    diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
    diagnostic_check($client->closed, 'Denied/ambiguous/invalid responses disconnect');
    if ($command==='GET DATABASE NAME') diagnostic_check($client->commands===['GET DATABASE NAME'], 'Identity failure stops before schema/settings reads');
}
$client=new StagingDiagnosticMockClient();
$client->warningOn='GET DATABASE NAME';
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
diagnostic_check($client->closed, 'Transport warnings stop and disconnect without provider logs');
$client=new StagingDiagnosticMockClient();
$client->connectResult=false;
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
diagnostic_check($client->commands===[] && $client->closed, 'Failed connection never queries');
$client=new StagingDiagnosticMockClient();
$client->disconnectFailure=true;
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));

$schemaQuery=$config['read_commands'][7];
$malformed=StagingDiagnosticMockClient::rowset([['foreign_keys'=>1]]);
$malformed->data=[];
$client=new StagingDiagnosticMockClient();
$client->responses['PRAGMA foreign_keys']=$malformed;
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
$client=new StagingDiagnosticMockClient();
$client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset([], ['wrong_metadata']);
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
$client=new StagingDiagnosticMockClient();
$client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset([
    ['type'=>'table','name'=>'unexpected','tbl_name'=>'unexpected','sql'=>'CREATE TABLE unexpected(payload TEXT)'],
    ['type'=>'view','name'=>'unsafe_view','tbl_name'=>'unsafe_view','sql'=>'CREATE VIEW unsafe_view AS SELECT extension()'],
    ['type'=>'table','name'=>'virtual','tbl_name'=>'virtual','sql'=>'CREATE VIRTUAL TABLE virtual USING extension'],
]);
$client->responses['SELECT EXISTS(SELECT 1 FROM main."unexpected" LIMIT 1) AS has_records']=StagingDiagnosticMockClient::rowset([['has_records'=>1]]);
$report=submission_staging_diagnostic_run($manifest,$url,$client);
diagnostic_check(!$report['appears_empty'] && $report['status']==='STOP_UNEXPECTED_SCHEMA', 'Unexpected schema triggers stop report');
diagnostic_check($report['schema_objects'][0]['has_records']===true && $report['schema_objects'][1]['has_records']===null
    && $report['schema_objects'][2]['has_records']===null, 'Record existence only, views/virtual tables never queried');
diagnostic_check(count($client->commands)===9
    && array_keys($report['schema_objects'][0])===['type','name','has_records','classification']
    && !str_contains(json_encode($report),'CREATE TABLE'), 'No data payloads or SQL definitions exposed');
$client->responses['SELECT EXISTS(SELECT 1 FROM main."unexpected" LIMIT 1) AS has_records']=StagingDiagnosticMockClient::rowset([['has_records'=>0]]);
$report=submission_staging_diagnostic_run($manifest,$url,$client);
diagnostic_check(!$report['appears_empty'] && $report['schema_objects'][0]['has_records']===false, 'Empty unexpected tables still fail empty-schema expectation');
$vectorSql='CREATE TABLE _sqliteai_vector (tblname TEXT, colname TEXT, key TEXT, value ANY, PRIMARY KEY(tblname, colname, key))';
$vectorObjects=[
    ['type'=>'index','name'=>'sqlite_autoindex__sqliteai_vector_1','tbl_name'=>'_sqliteai_vector','sql'=>null],
    ['type'=>'table','name'=>'_sqliteai_vector','tbl_name'=>'_sqliteai_vector','sql'=>$vectorSql],
];
foreach (submission_migrations() as $version=>$statements) {
    diagnostic_check(!str_contains(implode("\n",$statements),'_sqliteai_vector'),
        'Migration '.$version.' definitions never target SQLite-Vector objects (no migrations executed)');
}
$vectorPresence='SELECT EXISTS(SELECT 1 FROM main."_sqliteai_vector" LIMIT 1) AS has_records';
foreach ([$vectorSql, str_replace('CREATE TABLE ', "create\n table IF NOT EXISTS ", $vectorSql).';'] as $definition) {
    $objects=$vectorObjects;
    $objects[1]['sql']=$definition;
    $client=new StagingDiagnosticMockClient();
    $client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset($objects);
    $client->responses[$vectorPresence]=StagingDiagnosticMockClient::rowset([['has_records'=>0]]);
    $report=submission_staging_diagnostic_run($manifest,$url,$client);
    diagnostic_check($report['status']==='READ_ONLY_CHECKS_COMPLETE' && $report['no_application_schema_data']
        && !$report['appears_empty'], 'Exact empty extension pair allows inspection, not schema-empty claim');
    diagnostic_check(count($report['schema_objects'])===2
        && $report['schema_objects'][0]['classification']==='known_extension_metadata'
        && $report['schema_objects'][1]['classification']==='known_extension_metadata'
        && $report['schema_objects'][1]['has_records']===false, 'Both extension objects remain reported with empty metadata');
    diagnostic_check($client->commands===array_merge($config['read_commands'],[$vectorPresence])
        && $client->closed, 'Refinement adds no settings or migration queries');
}
$badDefinitions=[
    str_replace('value ANY','value TEXT',$vectorSql),
    str_replace('key TEXT','key TEXT NOT NULL',$vectorSql),
    str_replace('PRIMARY KEY(tblname, colname, key)','PRIMARY KEY(colname, tblname, key)',$vectorSql),
    str_replace('PRIMARY KEY(tblname, colname, key)','PRIMARY KEY(tblname, colname)',$vectorSql),
    str_replace(', value ANY',', extra TEXT, value ANY',$vectorSql),
    str_replace('tblname TEXT','tblname TEXT DEFAULT \'x\'',$vectorSql),
    $vectorSql.' WITHOUT ROWID', $vectorSql.' STRICT', $vectorSql.'; CREATE TABLE unknown(x)',
    str_replace('tblname TEXT','tblname TEXT /* hidden change */',$vectorSql),
];
foreach ($badDefinitions as $definition) {
    $objects=$vectorObjects;
    $objects[1]['sql']=$definition;
    $client=new StagingDiagnosticMockClient();
    $client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset($objects);
    $client->responses[$vectorPresence]=StagingDiagnosticMockClient::rowset([['has_records'=>0]]);
    $report=submission_staging_diagnostic_run($manifest,$url,$client);
    diagnostic_check(!$report['no_application_schema_data'] && $report['status']==='STOP_UNEXPECTED_SCHEMA',
        'Altered column/type/constraint/PK/full definition remains blocked');
}
$badObjectSets=[];
foreach ([
    ['name'=>'sqlite_autoindex__sqliteai_vector_2'], ['tbl_name'=>'different_table'],
    ['sql'=>'CREATE INDEX sqlite_autoindex__sqliteai_vector_1 ON _sqliteai_vector(key)'], ['type'=>'view'],
] as $change) {
    $objects=$vectorObjects;
    $objects[0]=array_replace($objects[0],$change);
    $badObjectSets[]=$objects;
}
$badObjectSets[]=[$vectorObjects[1]];
$badObjectSets[]=[$vectorObjects[0]];
$objects=$vectorObjects;
$objects[1]['tbl_name']='different_table';
$badObjectSets[]=$objects;
$badObjectSets[]=array_merge($vectorObjects,[['type'=>'index','name'=>'sqlite_autoindex__sqliteai_vector_2','tbl_name'=>'_sqliteai_vector','sql'=>null]]);
$badObjectSets[]=array_merge($vectorObjects,[['type'=>'table','name'=>'_sqliteai_other','tbl_name'=>'_sqliteai_other','sql'=>'CREATE TABLE _sqliteai_other(x)']]);
$badObjectSets[]=array_merge($vectorObjects,[['type'=>'trigger','name'=>'vector_trigger','tbl_name'=>'_sqliteai_vector','sql'=>'CREATE TRIGGER vector_trigger AFTER INSERT ON _sqliteai_vector BEGIN SELECT 1; END']]);
foreach ($badObjectSets as $objects) {
    $client=new StagingDiagnosticMockClient();
    $client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset($objects);
    $client->responses[$vectorPresence]=StagingDiagnosticMockClient::rowset([['has_records'=>0]]);
    $client->responses['SELECT EXISTS(SELECT 1 FROM main."_sqliteai_other" LIMIT 1) AS has_records']=StagingDiagnosticMockClient::rowset([['has_records'=>0]]);
    $report=submission_staging_diagnostic_run($manifest,$url,$client);
    diagnostic_check(!$report['no_application_schema_data'] && $report['status']==='STOP_UNEXPECTED_SCHEMA',
        'Missing pair, incorrect metadata, extra index/trigger or unknown prefix table stays blocked');
}
$client=new StagingDiagnosticMockClient();
$client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset($vectorObjects);
$client->responses[$vectorPresence]=StagingDiagnosticMockClient::rowset([['has_records'=>1]]);
$report=submission_staging_diagnostic_run($manifest,$url,$client);
diagnostic_check(!$report['no_application_schema_data'] && $report['status']==='STOP_UNEXPECTED_SCHEMA'
    && $report['schema_objects'][1]['has_records']===true, 'Populated known metadata is rejected during pre-migration inspection');
$client->responses[$vectorPresence]=false;
diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
foreach ([
    [['type'=>'table','name'=>'unsafe";DROP','tbl_name'=>'unsafe','sql'=>'CREATE TABLE unsafe(x)']],
    [['type'=>'table','name'=>'synthetic-secret-key','tbl_name'=>'unexpected','sql'=>'CREATE TABLE unexpected(x)']],
    array_fill(0,101,['type'=>'table','name'=>'unexpected','tbl_name'=>'unexpected','sql'=>'CREATE TABLE unexpected(x)']),
] as $badSchema) {
    $client=new StagingDiagnosticMockClient();
    $client->responses[$schemaQuery]=StagingDiagnosticMockClient::rowset($badSchema);
    diagnostic_reject(fn()=>submission_staging_diagnostic_run($manifest,$url,$client));
}
diagnostic_reject(fn()=>submission_staging_diagnostic_approval('--connect-readonly',''));
diagnostic_reject(fn()=>submission_staging_diagnostic_approval('--offline','book-to-screen-staging/author-submissions-staging.sqlite/read-only'));
submission_staging_diagnostic_approval('--connect-readonly','book-to-screen-staging/author-submissions-staging.sqlite/read-only');
diagnostic_check(true,'Exact live double gate accepted offline without connection');
$path=tempnam(sys_get_temp_dir(),'b2s-probe-');
if ($path===false) throw new RuntimeException('Could not create the mock manifest file.');
try {
    if (file_put_contents($path,json_encode($manifest,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Could not write the mock manifest.');
    $run=static function (string $path, string $flag, string $approval): array {
        $environment=getenv();
        $environment['B2S_SUBMISSIONS_STAGING_READONLY_APPROVAL']=$approval;
        $environment['B2S_SUBMISSIONS_STAGING_READONLY_URL']='';
        // A production-looking legacy variable must never serve as a fallback.
        $environment['SQLITE_CLOUD_CONNECTION_STRING']='sqlitecloud://production.example.com/adaptations.sqlite?apikey=synthetic-secret-production';
        $process=proc_open([PHP_BINARY,'-d','display_errors=0',__DIR__.'/../scripts/inspect-submissions-staging.php',$path,$flag],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment);
        if (!is_resource($process)) throw new RuntimeException('Could not start CLI gate test.');
        fclose($pipes[0]);
        $output=stream_get_contents($pipes[1]);
        $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process),$output,$error];
    };
    [$status,$output]=$run($path,'--offline','');
    $report=json_decode($output,true,8,JSON_THROW_ON_ERROR);
    diagnostic_check($status===0 && !$report['connection_opened'] && !$report['writes_authorized'], 'CLI offline mode needs no URL or approval and never connects');
    foreach ([['--connect-readonly',''], ['--connect-readonly','book-to-screen-staging/author-submissions-staging.sqlite/read-only'],
        ['--write','']] as [$flag,$approval]) {
        [$status,$output,$error]=$run($path,$flag,$approval);
        diagnostic_check($status===1 && $output==='' && $error!=='' && !str_contains($error,'synthetic-secret'),
            'CLI missing approval/missing URL/invalid mode fails before connecting, without production fallback');
    }
} finally {
    if (!unlink($path)) throw new RuntimeException('Could not remove the mock manifest file.');
}
echo "Passed {$checks} read-only staging diagnostic checks; mock transport only.\n";
