<?php

declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/submissions/staging-migration.php';

use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

final class StagingMigrationFixtureClient extends SQLiteCloudClient
{
    public PDO $pdo;
    public array $commands=[];
    public int $connections=0;
    public bool $closed=false;
    public ?string $failOn=null;
    public bool $failAfterCommit=false;
    public bool $wrongIdentity=false;
    public bool $fkOff=false;
    public array $overrides=[];

    public function __construct(bool $vector=true)
    {
        $this->pdo=new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        if ($vector) $this->pdo->exec('CREATE TABLE _sqliteai_vector (tblname TEXT,colname TEXT,key TEXT,value ANY,PRIMARY KEY(tblname,colname,key))');
    }
    public function connect($hostname='localhost',$port=8860)
    {
        $this->connections++;
        return $hostname==='cutaojpqvz.g2.sqlite.cloud' && $port===8860 && $this->database==='author-submissions-staging.sqlite'
            && !$this->create && !$this->insecure && !$this->no_verify_certificate;
    }
    public function execute($command)
    {
        $this->commands[]=$command;
        if (array_key_exists($command,$this->overrides)) return $this->overrides[$command];
        if ($command===$this->failOn) return false;
        if ($command==='GET DATABASE NAME') return $this->wrongIdentity?'adaptations.sqlite':'author-submissions-staging.sqlite';
        if ($command==='GET USER') return 'admin';
        if ($command==='PRAGMA database_list') return self::rowset(['seq','name','file'],[[0,'main','/staging/author-submissions-staging.sqlite']]);
        if ($command==='PRAGMA foreign_keys' && $this->fkOff) return self::rowset(['foreign_keys'],[[0]]);
        $stmt=$this->pdo->query($command);
        if ($command==='COMMIT' && $this->failAfterCommit) {
            $this->failAfterCommit=false;
            return false;
        }
        if ($stmt->columnCount()===0) return true;
        $columns=[];
        for ($i=0;$i<$stmt->columnCount();$i++) $columns[]=$stmt->getColumnMeta($i)['name'];
        return self::rowset($columns,$stmt->fetchAll(PDO::FETCH_NUM));
    }
    public static function rowset(array $columns,array $rows): SQLiteCloudRowset
    {
        $rs=new SQLiteCloudRowset();
        $rs->ncols=count($columns); $rs->nrows=count($rows); $rs->colname=$columns; $rs->data=[];
        foreach ($rows as $row) array_push($rs->data,...$row);
        return $rs;
    }
    public function disconnect() { $this->closed=true; }
}

if (defined('B2S_STAGING_MIGRATION_TEST_HELPERS')) return;

$checks=0;
function migration_check(bool $condition,string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $checks++;
}
function migration_reject(callable $op): void
{
    try { $op(); } catch (RuntimeException|InvalidArgumentException $e) {
        migration_check(!str_contains($e->getMessage(),'synthetic-secret'),'Failure sanitized');
        return;
    }
    throw new RuntimeException('FAIL: invalid migration accepted');
}
$manifest=[
    'identity'=>['environment'=>'staging','project_id'=>'cutaojpqvz','hostname'=>'cutaojpqvz.g2.sqlite.cloud','port'=>8860,
        'database'=>'author-submissions-staging.sqlite','production_project_id'=>'synthetic-production',
        'production_hostname'=>'production.example.com','production_database'=>'adaptations.sqlite','synthetic_only'=>true],
    'project_name'=>'book-to-screen-staging','control_panel_identity_confirmed'=>true,
    'credential_mode'=>'staging-admin','expected_user'=>'admin','admin_risk_approved'=>true,
];
$url='sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=synthetic-secret-key';
$flag='--apply-empty-v1-v2';
$approval='cutaojpqvz/author-submissions-staging.sqlite/empty-install-v1-v2';
$run=static fn(StagingMigrationFixtureClient $client): array => submission_staging_migration_run($manifest,$url,$flag,$approval,$client);
migration_check(submission_staging_migration_plan($manifest)['checksums']===SUBMISSION_STAGING_APPROVED_CHECKSUMS,'Exact reviewed checksums');
foreach ([['--offline',$approval],[$flag,''],[$flag,'book-to-screen-staging/author-submissions-staging.sqlite/read-only']] as [$badFlag,$badApproval]) {
    $client=new StagingMigrationFixtureClient();
    migration_reject(fn()=>submission_staging_migration_run($manifest,$url,$badFlag,$badApproval,$client));
    migration_check($client->connections===0,'Approval rejection before connection');
}
$bad=$manifest; $bad['identity']['hostname']='other-staging.example.com';
$client=new StagingMigrationFixtureClient();
migration_reject(fn()=>submission_staging_migration_run($bad,$url,$flag,$approval,$client));
migration_check($client->connections===0,'Exact endpoint pinned, even another staging endpoint rejected');
foreach ([true,false] as $withVector) {
    $client=new StagingMigrationFixtureClient($withVector);
    $baseline=$client->pdo->query('SELECT type,name,tbl_name,sql FROM sqlite_schema ORDER BY type,name')->fetchAll(PDO::FETCH_ASSOC);
    $result=$run($client);
    migration_check($result['status']==='EMPTY_INSTALL_VERIFIED' && $result['applied_versions']===[1,2],'Empty install both versions');
    migration_check($client->connections===1 && $client->closed,'One connection for identity, migration, repeat and disconnect');
    migration_check($result['repeat_no_op'] && array_sum($result['application_counts'])===0,'No application records and repeat no-op');
    foreach ($baseline as $object) {
        $stmt=$client->pdo->prepare('SELECT type,name,tbl_name,sql FROM sqlite_schema WHERE name=:name');
        $stmt->execute(['name'=>$object['name']]);
        migration_check($stmt->fetch(PDO::FETCH_ASSOC)===$object,'Exact metadata preserved');
    }
    migration_check(!array_filter($client->commands,static fn(string $sql): bool=>preg_match('/^(PRAGMA\s+\w+\s*=|SET\s|INSERT INTO users|INSERT INTO _sqliteai_vector)/i',$sql)===1),
        'No settings changes, users or vector inserts');
    $ledger=$client->pdo->query('SELECT * FROM submission_schema_versions')->fetchAll(PDO::FETCH_ASSOC);
    $result=$run($client);
    migration_check($result['applied_versions']===[] && $result['repeat_no_op'],'Whole harness repeat allowed only exact empty v2');
    migration_check($client->pdo->query('SELECT * FROM submission_schema_versions')->fetchAll(PDO::FETCH_ASSOC)===$ledger,'Ledger timestamps unchanged on repeat');
}
foreach (['identity','fk','unknown','modified-vector','extra-vector-index','populated-vector'] as $failure) {
    $client=new StagingMigrationFixtureClient();
    if ($failure==='identity') $client->wrongIdentity=true;
    if ($failure==='fk') $client->fkOff=true;
    if ($failure==='unknown') $client->pdo->exec('CREATE TABLE unknown(x)');
    if ($failure==='modified-vector') { $client->pdo->exec('DROP TABLE _sqliteai_vector'); $client->pdo->exec('CREATE TABLE _sqliteai_vector(x)'); }
    if ($failure==='extra-vector-index') $client->pdo->exec('CREATE INDEX unexpected_vector_idx ON _sqliteai_vector(key)');
    if ($failure==='populated-vector') $client->pdo->exec("INSERT INTO _sqliteai_vector VALUES ('synthetic','synthetic','synthetic',1)");
    migration_reject(fn()=>$run($client));
    migration_check(!in_array('CREATE TABLE IF NOT EXISTS submission_schema_versions (',$client->commands,true)
        && (int)$client->pdo->query("SELECT COUNT(*) FROM sqlite_schema WHERE name='submission_schema_versions'")->fetchColumn()===0,
        'Precheck failure never creates migration ledger');
    migration_check($client->closed,'Precheck failure disconnects');
}
foreach (['CREATE INDEX submission_review_idx ON property_submissions(review_status, verified_at, created_at)',
    'ALTER TABLE property_submissions_v2 RENAME TO property_submissions','COMMIT'] as $failOn) {
    $client=new StagingMigrationFixtureClient();
    $client->failOn=$failOn;
    migration_reject(fn()=>$run($client));
    migration_check(in_array('ROLLBACK',$client->commands,true),'Active migration failure attempts rollback');
    migration_check(!$client->pdo->inTransaction(),'No transaction remains active');
    migration_check((int)$client->pdo->query("SELECT COUNT(*) FROM sqlite_schema WHERE name LIKE '%_v2'")->fetchColumn()===0,'No partial v2 tables');
    $versions=$client->pdo->query('SELECT version FROM submission_schema_versions')->fetchAll(PDO::FETCH_COLUMN);
    migration_check($versions===($failOn==='ALTER TABLE property_submissions_v2 RENAME TO property_submissions'?[1]:[]),
        'Failure preserves only earlier committed versions');
    migration_check((int)$client->pdo->query('SELECT COUNT(*) FROM _sqliteai_vector')->fetchColumn()===0 && $client->closed,'Vector retained and disconnected after rollback');
}
$client=new StagingMigrationFixtureClient(); $client->failAfterCommit=true;
migration_reject(fn()=>$run($client));
migration_check((int)$client->pdo->query('SELECT COUNT(*) FROM submission_schema_versions')->fetchColumn()===1,'Ambiguous committed v1 remains; never cleaned up');
migration_check(count(array_filter($client->commands,static fn(string $sql):bool=>$sql==='COMMIT'))===1,'No automatic retry after ambiguous commit');
// Corrupt only expendable offline fixture state, never seed property submissions.
foreach (['index','index-definition','column','ledger','rows','partial','unknown-table','extra-vector-index'] as $failure) {
    $client=new StagingMigrationFixtureClient();
    $run($client);
    if ($failure==='index') $client->pdo->exec('DROP INDEX submission_role_idx');
    if ($failure==='index-definition') {
        $client->pdo->exec('DROP INDEX submission_role_idx');
        $client->pdo->exec('CREATE INDEX submission_role_idx ON property_submissions(created_at)');
    }
    if ($failure==='column') $client->pdo->exec('ALTER TABLE property_submissions ADD COLUMN unexpected TEXT');
    if ($failure==='ledger') $client->pdo->exec("UPDATE submission_schema_versions SET checksum='synthetic-invalid' WHERE version=1");
    if ($failure==='rows') $client->pdo->exec("INSERT INTO submission_rate_limits VALUES ('synthetic',1,1)");
    if ($failure==='partial') $client->pdo->exec('DELETE FROM submission_schema_versions WHERE version=2');
    if ($failure==='unknown-table') $client->pdo->exec('CREATE TABLE unknown(x)');
    if ($failure==='extra-vector-index') $client->pdo->exec('CREATE INDEX unexpected_vector_idx ON _sqliteai_vector(key)');
    $start=count($client->commands);
    migration_reject(fn()=>$run($client));
    $new=array_slice($client->commands,$start);
    migration_check(!in_array('BEGIN',$new,true),'Bad installed state rejected before migration/repeat transaction');
}
foreach ([
    ['PRAGMA foreign_key_check',true],
    ['PRAGMA foreign_key_check',StagingMigrationFixtureClient::rowset(['wrong_column'],[])],
    ['SELECT COUNT(*) AS total FROM property_submissions',StagingMigrationFixtureClient::rowset(['total'],[[1]])],
    ['SELECT version,checksum,applied_at FROM submission_schema_versions ORDER BY version',StagingMigrationFixtureClient::rowset(['version','checksum','applied_at'],[])],
] as [$query,$response]) {
    $client=new StagingMigrationFixtureClient();
    $client->overrides[$query]=$response;
    migration_reject(fn()=>$run($client));
    migration_check($client->closed,'Bad postmigration response stops and disconnects');
    migration_check((int)$client->pdo->query('SELECT COUNT(*) FROM submission_schema_versions')->fetchColumn()===2,
        'Postcommit validation failure does not destroy committed schema');
}
$client=new StagingMigrationFixtureClient();
$client->failOn='PRAGMA query_only';
migration_reject(fn()=>$run($client));
migration_check(!in_array('BEGIN',$client->commands,true),'Permission-denied precheck stops before transaction');
// Test CLI approval/config guards without ever reading the private config or opening a socket.
$path=tempnam(sys_get_temp_dir(),'b2s-migration-');
if ($path===false) throw new RuntimeException('Could not create the nonsecret test manifest.');
try {
    if (file_put_contents($path,json_encode($manifest,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Could not write test manifest.');
    $cli=static function (string $path,string $flag,string $approval): array {
        $env=getenv();
        $env['B2S_SUBMISSIONS_STAGING_MIGRATION_APPROVAL']=$approval;
        $env['SQLITE_CLOUD_CONNECTION_STRING']='must-not-be-used';
        $process=proc_open([PHP_BINARY,'-d','display_errors=0',__DIR__.'/../scripts/migrate-submissions-staging.php',$path,$flag],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if (!is_resource($process)) throw new RuntimeException('Could not run offline CLI guard.');
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process),$out,$err];
    };
    [$status,$out]=$cli($path,'--offline','');
    $plan=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    migration_check($status===0 && !$plan['connection_opened'] && !$plan['writes_authorized'],'Offline CLI opens no connection or private config');
    foreach ([[$flag,''],[$flag,'read-only'],['--apply','']] as [$badFlag,$badApproval]) {
        [$status,$out,$err]=$cli($path,$badFlag,$badApproval);
        migration_check($status===1 && $out==='' && $err!=='','CLI rejects invalid approval before config/network');
    }
} finally {
    if (!unlink($path)) throw new RuntimeException('Could not remove the synthetic manifest.');
}
echo "Passed {$checks} controlled staging migration checks; in-memory SQL and mock connections only; no property submissions inserted.\n";
