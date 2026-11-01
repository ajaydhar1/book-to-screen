<?php

declare(strict_types=1);

require_once __DIR__.'/staging-migration.php';
require_once __DIR__.'/service.php';
require_once __DIR__.'/../database/LocalSqliteDatabase.php';

function submission_staging_synthetic_properties(): array
{
    $common=['isbn'=>'','publisher'=>'Synthetic Test Press','publication_date'=>'2026',
        'description'=>'Entirely fictional staging fixture. No real author or rights claims.',
        'subjects'=>'Friendship, Synthetic testing','authorization'=>'unknown','rights-controller'=>'unknown',
        'arrangement'=>'none','website'=>'','consent'=>true,'privacy'=>true,'screen-format'=>'limited'];
    return [
        array_merge($common,['title'=>'[SYNTHETIC] The Lanterns of Tomorrow','authors'=>'Invented Author Mira Quill',
            'genre'=>'Science Fiction','pitch'=>'Fictional lighthouse keepers collect tomorrow\'s memories.',
            'submitter-name'=>'Invented Submitter Mira Quill','email'=>'staging-mira@example.com','role'=>'author']),
        array_merge($common,['title'=>'[SYNTHETIC] The Orchard of Quiet Clocks','authors'=>'Invented Author Rowan Vale',
            'genre'=>'Mystery','pitch'=>'A fictional village detective investigates clocks that count secrets.',
            'submitter-name'=>'Invented Submitter Ellis Reed','email'=>'staging-ellis@example.com','role'=>'agent']),
        array_merge($common,['title'=>'[SYNTHETIC] The Lanterns of Tomorrow','authors'=>'Invented Author Mira Quill',
            'genre'=>'Science Fiction','pitch'=>'A second fictional representative recommends the same imaginary property.',
            'submitter-name'=>'Invented Submitter Tessa Finch','email'=>'staging-tessa@example.com','role'=>'scout']),
    ];
}

final class StagingSyntheticMailer implements SubmissionMailer
{
    public array $messages=[];
    public function sendVerification(string $email,string $link): void
    {
        if (!in_array($email,array_column(submission_staging_synthetic_properties(),'email'),true)) {
            throw new SubmissionMailException('Synthetic mail recipient not allowlisted.');
        }
        $this->messages[]=['email'=>$email,'link'=>$link];
    }
}

final class StagingSyntheticReviewerDatabase implements Database
{
    private LocalSqliteDatabase $reviewer;
    public function __construct(private SQLiteCloudDatabase $cloud)
    {
        $this->reviewer=new LocalSqliteDatabase(':memory:');
        $this->reviewer->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,display_name TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)');
        $this->reviewer->exec("INSERT INTO users VALUES (900001,'Synthetic Staging Reviewer','admin',1,0)");
    }
    public function prepare(string $sql): DatabaseStatement
    {
        if ($sql==='SELECT id, display_name, role, is_active, must_change_password FROM users WHERE id = :id') {
            return $this->reviewer->prepare($sql);
        }
        if (str_contains($sql,'LEFT JOIN users u ON u.id = e.actor_user_id')) {
            $sql=str_replace('LEFT JOIN users u ON u.id = e.actor_user_id',
                "LEFT JOIN (SELECT 900001 AS id,'Synthetic Staging Reviewer' AS display_name) u ON u.id = e.actor_user_id",$sql);
        }
        return $this->cloud->prepare($sql);
    }
    public function query(string $sql): DatabaseStatement { $stmt=$this->prepare($sql); $stmt->execute(); return $stmt; }
    public function exec(string $sql): int|false { return $this->cloud->exec($sql); }
    public function beginTransaction(): bool { return $this->cloud->beginTransaction(); }
    public function commit(): bool { return $this->cloud->commit(); }
    public function rollBack(): bool { return $this->cloud->rollBack(); }
    public function inTransaction(): bool { return $this->cloud->inTransaction(); }
    public function lastInsertId(?string $name=null): string|false { return $this->cloud->lastInsertId($name); }
}

function submission_staging_synthetic_approval(string $flag,string $approval): void
{
    if ($flag!=='--test-three-synthetic'
        || $approval!=='cutaojpqvz/author-submissions-staging.sqlite/three-synthetic-v1') {
        throw new InvalidArgumentException('Separate exact three-synthetic approval is required before connection.');
    }
}

function submission_staging_synthetic_evaluation(int $index): array
{
    return submission_evaluation_validate(['premise'=>'Synthetic qualitative observation '.$index,
        'rights_questions'=>'Entirely invented declarations; no rights ownership established.']);
}

function submission_staging_synthetic_assert(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Synthetic staging invariant failed; stop without recovery writes.');
}

function submission_staging_synthetic_verify(SQLiteCloudDatabase $db,SubmissionService $service,SubmissionCrypto $crypto): array
{
    $rows=$db->query('SELECT * FROM property_submissions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    submission_staging_synthetic_assert(count($rows)===3);
    $statuses=['research_candidate','needs_information','duplicate'];
    $ids=[];
    foreach (submission_staging_synthetic_properties() as $i=>$input) {
        $request=hash('sha256','b2s-staging-three-v1-'.$i);
        $matches=array_values(array_filter($rows,static fn(array $r):bool=>$r['idempotency_hash']===$crypto->hash('idempotency',$request)));
        submission_staging_synthetic_assert(count($matches)===1);
        $row=$matches[0]; $ids[]=$row['id'];
        $validated=submission_validate($input);
        $propertyText=preg_replace('/\s+/u',' ',trim($input['title'].'|'.$input['authors']));
        submission_staging_synthetic_assert($row['payload_hash']===$crypto->hash('payload',json_encode($validated,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))
            && json_decode($crypto->decrypt($row['private_payload']),true,16,JSON_THROW_ON_ERROR)===$validated['private']
            && json_decode($row['book_json'],true,16,JSON_THROW_ON_ERROR)===$validated['book']
            && $row['book_title']===$input['title'] && $row['book_authors']===$input['authors'] && $row['isbn']===null
            && $row['email_hash']===$crypto->hash('email',$input['email'])
            && $row['property_hash']===$crypto->hash('property',mb_strtolower($propertyText,'UTF-8'))
            && $row['receipt_hash']===hash('sha256',$crypto->hash('receipt',$request))
            && $row['authorization_status']==='self_declared' && $row['submitter_role']===$input['role']
            && $row['consent_version']===SUBMISSION_CONSENT_VERSION && $row['privacy_version']===SUBMISSION_PRIVACY_VERSION
            && (int)$row['resend_count']===0 && (int)$row['consent_at']===(int)$row['created_at']
            && $row['review_status']===$statuses[$i] && $row['verified_at']!==null && (int)$row['revision']===2
            && $row['delivery_status']==='sent' && (int)$row['reviewed_by_user_id']===900001
            && json_decode($crypto->decrypt($row['evaluation_payload']),true,16,JSON_THROW_ON_ERROR)===submission_staging_synthetic_evaluation($i)
            && !str_contains($row['private_payload'],$input['email']));
        $detail=$service->detail($row['id']);
        submission_staging_synthetic_assert($detail!==null && $detail['event_total']===5
            && count($detail['duplicates'])===($i===1?0:1));
        $events=$db->prepare('SELECT event_type,actor_user_id,private_note FROM submission_events WHERE submission_id=:id ORDER BY id');
        $events->execute(['id'=>$row['id']]); $events=$events->fetchAll(PDO::FETCH_ASSOC);
        $snapshot=[];
        foreach (SUBMISSION_EVALUATION_FIELDS as $field=>$label) {
            $value=submission_staging_synthetic_evaluation($i)[$field];
            $snapshot[]=$label.': '.($value===''?'(not recorded)':$value);
        }
        submission_staging_synthetic_assert(array_column($events,'event_type')===['submitted','verification_email_sent','email_verified','review_updated','evaluation_updated']
            && $events[0]['private_note']===null && $events[1]['private_note']===null && $events[2]['private_note']===null
            && $events[0]['actor_user_id']===null && $events[1]['actor_user_id']===null && $events[2]['actor_user_id']===null
            && (int)$events[3]['actor_user_id']===900001 && (int)$events[4]['actor_user_id']===900001
            && $crypto->decrypt($events[3]['private_note'])==='Synthetic staging note '.$i
            && $crypto->decrypt($events[4]['private_note'])===implode("\n\n",$snapshot));
        $tokens=$db->prepare('SELECT token_hash,expires_at,consumed_at,created_at FROM submission_email_tokens WHERE submission_id=:id');
        $tokens->execute(['id'=>$row['id']]); $tokens=$tokens->fetchAll(PDO::FETCH_ASSOC);
        submission_staging_synthetic_assert(count($tokens)===1 && preg_match('/^[a-f0-9]{64}$/D',$tokens[0]['token_hash'])===1
            && $tokens[0]['consumed_at']!==null && (int)$tokens[0]['expires_at']-(int)$tokens[0]['created_at']===86400);
    }
    $queue=$service->queue();
    submission_staging_synthetic_assert(count($queue)===2 && !in_array('duplicate',array_column($queue,'review_status'),true));
    $genres=$db->query('SELECT submission_id,genre_key,genre_label FROM submission_genres')->fetchAll(PDO::FETCH_ASSOC);
    submission_staging_synthetic_assert(count($genres)===3);
    foreach ($ids as $i=>$id) {
        $matches=array_values(array_filter($genres,static fn(array $g):bool=>$g['submission_id']===$id));
        $label=submission_staging_synthetic_properties()[$i]['genre'];
        submission_staging_synthetic_assert(count($matches)===1 && $matches[0]['genre_label']===$label
            && $matches[0]['genre_key']===mb_strtolower($label,'UTF-8'));
    }
    $buckets=$db->query('SELECT bucket_hash,attempts FROM submission_rate_limits')->fetchAll(PDO::FETCH_ASSOC);
    $expected=[];
    foreach (submission_staging_synthetic_properties() as $i=>$input) {
        $expected[$crypto->hash('rate:submit','synthetic-three-'.$i)]=2;
        $expected[$crypto->hash('rate:submit_email',$input['email'])]=2;
        $expected[$crypto->hash('rate:verify','synthetic-verify-'.$i)]=2;
    }
    submission_staging_synthetic_assert(count($buckets)===9);
    foreach ($buckets as $bucket) submission_staging_synthetic_assert(isset($expected[$bucket['bucket_hash']])
        && (int)$bucket['attempts']===$expected[$bucket['bucket_hash']]);
    return ['statuses'=>$statuses,'submission_count'=>3,'inbox_count'=>2,'token_count'=>3,'audit_count'=>15,'genre_count'=>3,'rate_bucket_count'=>9];
}

function submission_staging_synthetic_run(array $manifest,#[SensitiveParameter] string $url,
    #[SensitiveParameter] string $key,string $flag,string $approval,?\SQLiteCloud\SQLiteCloudClient $client=null): array
{
    $plan=submission_staging_migration_plan($manifest);
    submission_staging_synthetic_approval($flag,$approval);
    $crypto=new SubmissionCrypto($key);
    return submission_staging_session($manifest,$url,static function (\SQLiteCloud\SQLiteCloudClient $connected) use ($manifest,$url,$crypto,$plan):array {
        $db=new SQLiteCloudDatabase($connected);
        $inspection=submission_staging_inspect_connected($manifest,$url,$connected);
        submission_staging_synthetic_assert($inspection['foreign_keys']===1 && $inspection['query_only']===0);
        $schema=submission_staging_migration_schema($db);
        $vector=array_intersect_key($schema,array_flip(['_sqliteai_vector','sqlite_autoindex__sqliteai_vector_1']));
        submission_staging_synthetic_assert(count($vector)===2 && submission_staging_vector_metadata_matches(array_values($vector)));
        $before=submission_staging_verify_install($db,$vector,false);
        $mailer=new StagingSyntheticMailer();
        $service=new SubmissionService(new StagingSyntheticReviewerDatabase($db),$crypto,$mailer,'http://localhost/author-submissions');
        $counts=$before['counts'];
        if (array_sum($counts)!==0) {
            // Complete prior fixtures may be read/verified, but partial/foreign states are never resumed automatically.
            $summary=submission_staging_synthetic_verify($db,$service,$crypto);
            submission_staging_synthetic_assert($counts===['property_submissions'=>3,'submission_email_tokens'=>3,'submission_events'=>15,
                'submission_genres'=>3,'submission_rate_limits'=>9]);
            return ['status'=>'SYNTHETIC_REPEAT_VERIFIED','target'=>$plan['target'],'writes_performed'=>false,'summary'=>$summary,'mock_emails'=>0];
        }
        $now=time();
        foreach (submission_staging_synthetic_properties() as $i=>$input) {
            $request=hash('sha256','b2s-staging-three-v1-'.$i);
            $receipt=$service->submit($input,$request,'synthetic-three-'.$i,$now)['receipt'];
            submission_staging_synthetic_assert(count($mailer->messages)===$i+1);
            $message=$mailer->messages[$i];
            parse_str((string)parse_url($message['link'],PHP_URL_FRAGMENT),$fragment);
            $token=$fragment['token']??'';
            submission_staging_synthetic_assert($message['email']===$input['email'] && preg_match('/^[a-f0-9]{64}$/D',$token)===1);
            $second=$service->submit($input,$request,'synthetic-three-'.$i,$now);
            submission_staging_synthetic_assert($second['receipt']===$receipt && count($mailer->messages)===$i+1);
            submission_staging_synthetic_assert($service->verify($token,'synthetic-verify-'.$i,$now+1));
            submission_staging_synthetic_assert(!$service->verify($token,'synthetic-verify-'.$i,$now+2));
        }
        submission_staging_synthetic_assert(count($service->queue())===3);
        $statuses=['research_candidate','needs_information','duplicate'];
        foreach (submission_staging_synthetic_properties() as $i=>$input) {
            $stmt=$db->prepare('SELECT id FROM property_submissions WHERE idempotency_hash=:hash');
            $stmt->execute(['hash'=>$crypto->hash('idempotency',hash('sha256','b2s-staging-three-v1-'.$i))]);
            $service->review((string)$stmt->fetchColumn(),$statuses[$i],'Synthetic staging note '.$i,900001,1,$now+3,
                submission_staging_synthetic_evaluation($i));
        }
        $summary=submission_staging_synthetic_verify($db,$service,$crypto);
        $after=submission_staging_verify_install($db,$vector,false);
        submission_staging_synthetic_assert($before['ledger']===$after['ledger'] && $before['schema_fingerprint']===$after['schema_fingerprint']);
        return ['status'=>'THREE_SYNTHETIC_VERIFIED','target'=>$plan['target'],'summary'=>$summary,'mock_emails'=>3,
            'schema_checksums_unchanged'=>true,'vector_metadata_preserved'=>true,'foreign_keys'=>1,
            'reviewer_source'=>'isolated local fictional reviewer; not live staff authentication'];
    },$client);
}
