<?php

declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('B2S_STAGING_MIGRATION_TEST_HELPERS',true);
require_once __DIR__.'/submissions-staging-migration-test.php';
require_once __DIR__.'/../includes/submissions/staging-synthetic.php';

$checks=0;
function synthetic_check(bool $condition,string $message): void {
    global $checks; if (!$condition) throw new RuntimeException('FAIL: '.$message); $checks++;
}
function synthetic_reject(callable $op): void {
    try { $op(); } catch (RuntimeException|InvalidArgumentException $e) {
        synthetic_check(!str_contains($e->getMessage(),'synthetic-secret'),'Sanitized rejection'); return;
    }
    throw new RuntimeException('FAIL: unsafe synthetic operation accepted');
}
$manifest=[
    'identity'=>['environment'=>'staging','project_id'=>'cutaojpqvz','hostname'=>'cutaojpqvz.g2.sqlite.cloud','port'=>8860,
        'database'=>'author-submissions-staging.sqlite','production_project_id'=>'fictional-production',
        'production_hostname'=>'production.example.com','production_database'=>'adaptations.sqlite','synthetic_only'=>true],
    'project_name'=>'book-to-screen-staging','control_panel_identity_confirmed'=>true,
    'credential_mode'=>'staging-admin','expected_user'=>'admin','admin_risk_approved'=>true,
];
$url='sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=synthetic-secret-key';
$key=str_repeat('a',64); $flag='--test-three-synthetic'; $approval='cutaojpqvz/author-submissions-staging.sqlite/three-synthetic-v1';
$fixture=static function (): StagingMigrationFixtureClient {
    $client=new StagingMigrationFixtureClient();
    submission_migrate(new SQLiteCloudDatabase($client));
    $client->commands=[]; $client->closed=false;
    return $client;
};
$run=static fn(StagingMigrationFixtureClient $c): array=>submission_staging_synthetic_run($manifest,$url,$key,$flag,$approval,$c);
$client=$fixture();
$schema=$client->pdo->query('SELECT * FROM sqlite_schema ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$result=$run($client);
synthetic_check($result['status']==='THREE_SYNTHETIC_VERIFIED' && $result['mock_emails']===3,'Three fictional properties and mock tokens verified');
synthetic_check($result['summary']['submission_count']===3 && $result['summary']['inbox_count']===2
    && $result['summary']['audit_count']===15,'Statuses, evaluations, audit and duplicate quarantine checked');
synthetic_check($client->pdo->query('SELECT * FROM sqlite_schema ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)===$schema,'No Cloud schema changes or users table');
synthetic_check($client->connections===1 && $client->closed,'One mock connection closed');
$reviewerDb=new StagingSyntheticReviewerDatabase(new SQLiteCloudDatabase($client));
synthetic_check(submission_admin_user($reviewerDb,['admin_logged_in'=>true,'role'=>'admin','user_id'=>900001])!==null,
    'Approved fictional reviewer passes existing active-admin checks');
synthetic_check(submission_admin_user($reviewerDb,['admin_logged_in'=>true,'role'=>'admin','user_id'=>900002])===null,
    'Unknown reviewer rejected by existing authorization checks');
synthetic_check(!array_filter($client->commands,static fn(string $sql):bool=>preg_match('/^(CREATE|DROP|ALTER|PRAGMA\s+\w+\s*=|SET)\b/i',$sql)===1),'Harness performs no DDL or settings changes');
$before=[];
foreach (['property_submissions','submission_email_tokens','submission_events','submission_genres','submission_rate_limits','submission_schema_versions','_sqliteai_vector'] as $table)
    $before[$table]=$client->pdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC);
$start=count($client->commands);
$result=$run($client);
synthetic_check($result['status']==='SYNTHETIC_REPEAT_VERIFIED' && !$result['writes_performed'] && $result['mock_emails']===0,'Repeat verifies without mail or writes');
foreach ($before as $table=>$rows) synthetic_check($client->pdo->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC)===$rows,'Repeat preserves '.$table);
synthetic_check(!array_filter(array_slice($client->commands,$start),static fn(string $sql):bool=>preg_match('/^(INSERT|UPDATE|DELETE|BEGIN|COMMIT|ROLLBACK)/i',$sql)===1),'Repeat issues only reads');
foreach (['wrong-key','extra-rate','modified-pitch','modified-note','token','genre','status'] as $failure) {
    $c=$fixture(); $run($c);
    if ($failure==='extra-rate') $c->pdo->exec("INSERT INTO submission_rate_limits VALUES ('foreign',1,1)");
    if ($failure==='modified-pitch') $c->pdo->exec("UPDATE property_submissions SET payload_hash='wrong'");
    if ($failure==='modified-note') $c->pdo->exec("UPDATE submission_events SET private_note='corrupt' WHERE event_type='review_updated'");
    if ($failure==='token') $c->pdo->exec('UPDATE submission_email_tokens SET consumed_at=NULL');
    if ($failure==='genre') $c->pdo->exec('DELETE FROM submission_genres');
    if ($failure==='status') $c->pdo->exec("UPDATE property_submissions SET review_status='closed'");
    $start=count($c->commands);
    synthetic_reject(fn()=>submission_staging_synthetic_run($manifest,$url,$failure==='wrong-key'?str_repeat('b',64):$key,$flag,$approval,$c));
    synthetic_check(!array_filter(array_slice($c->commands,$start),static fn(string $sql):bool=>preg_match('/^(INSERT|UPDATE|DELETE|BEGIN)/i',$sql)===1),'Foreign/modified state stops before writes');
}
foreach (['fk','identity','schema','vector','partial','ambiguous'] as $failure) {
    $c=$fixture();
    if ($failure==='fk') $c->fkOff=true;
    if ($failure==='identity') $c->wrongIdentity=true;
    if ($failure==='schema') $c->pdo->exec('CREATE TABLE unknown(x)');
    if ($failure==='vector') $c->pdo->exec("INSERT INTO _sqliteai_vector VALUES ('fictional','v','x',1)");
    if ($failure==='partial') $c->pdo->exec("INSERT INTO submission_rate_limits VALUES ('partial',1,1)");
    if ($failure==='ambiguous') $c->failAfterCommit=true;
    synthetic_reject(fn()=>$run($c));
    synthetic_check($c->closed,'Failure disconnects');
    if ($failure==='ambiguous') synthetic_check(count(array_filter($c->commands,static fn(string $sql):bool=>$sql==='COMMIT'))===1,'Ambiguous commit never retried');
}
$c=$fixture();
synthetic_reject(fn()=>submission_staging_synthetic_run($manifest,$url,$key,$flag,'',$c));
synthetic_check($c->connections===0,'Approval rejected before network');
synthetic_reject(fn()=>submission_staging_synthetic_run($manifest,$url,'',$flag,$approval,$c));
synthetic_check($c->connections===0,'Missing key rejected before network, no fallback');
$mailer=new StagingSyntheticMailer();
synthetic_reject(fn()=>$mailer->sendVerification('foreign@example.org','http://localhost/verify'));
$path=tempnam(sys_get_temp_dir(),'b2s-synthetic-');
if ($path===false) throw new RuntimeException('Could not create mock manifest.');
try {
    if (file_put_contents($path,json_encode($manifest,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Could not write mock manifest.');
    $cli=static function (string $path,string $mode,string $approval):array {
        $env=getenv(); $env['B2S_SUBMISSIONS_STAGING_KEY']=''; $env['B2S_SUBMISSIONS_STAGING_SYNTHETIC_APPROVAL']=$approval;
        $process=proc_open([PHP_BINARY,'-d','display_errors=0',__DIR__.'/../scripts/test-submissions-staging.php',$path,$mode],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if (!is_resource($process)) throw new RuntimeException('Could not run offline gate.');
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process),$out,$err];
    };
    [$status,$out]=$cli($path,'--offline','');
    $plan=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    synthetic_check($status===0 && !$plan['connection_opened'] && count($plan['fictional_properties'])===3,'Offline plan opens no connection or private config');
    foreach ([['--test-three-synthetic',''],['--test-three-synthetic',$approval],['--write','']] as [$mode,$value]) {
        [$status,$out,$err]=$cli($path,$mode,$value);
        synthetic_check($status===1 && $out==='' && $err!=='','CLI missing approval/key/invalid mode stops before private config');
    }
} finally {
    if (!unlink($path)) throw new RuntimeException('Could not remove mock manifest.');
}
echo "Passed {$checks} synthetic staging checks; isolated in-memory fixtures and mock connections/mail only.\n";
