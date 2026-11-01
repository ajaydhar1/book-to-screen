<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('B2S_SUBMISSION_TEST_HELPERS', true);
require_once __DIR__ . '/submissions-test.php';

$checks = 0;
function review_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function review_throws(string $class, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        review_check($e instanceof $class, $message . ' (' . get_class($e) . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $message);
}

$crypto = new SubmissionCrypto(str_repeat('a',64));
foreach (['sqlite','cloud-offline'] as $driver) {
    $db = $driver === 'sqlite' ? new LocalSqliteDatabase(':memory:') : fixture_cloud_db();
    review_check(submission_migrate($db,null,1)===[1],'Version 1 fixture created');
    $validated=submission_validate(valid_submission());
    $book=json_encode($validated['book'],JSON_THROW_ON_ERROR);
    $private=$crypto->encrypt(json_encode($validated['private'],JSON_THROW_ON_ERROR));
    $seed=$db->prepare("INSERT INTO property_submissions(id,isbn,book_title,book_authors,book_json,private_payload,
        email_hash,property_hash,idempotency_hash,payload_hash,receipt_hash,consent_version,privacy_version,consent_at,last_send_at,created_at,updated_at)
        VALUES (:id,'9780140328721','Migrated Book','Sample Author',:book,:private,'email','property','key','payload','receipt','consent','privacy',1,1,1,1)");
    $oldId=str_repeat('f',32);
    $seed->execute(['id'=>$oldId,'book'=>$book,'private'=>$private]);
    $db->exec("INSERT INTO submission_email_tokens VALUES ('old-token','{$oldId}',10000,NULL,1)");
    $oldNote=$crypto->encrypt('Previous internal note');
    $stmt=$db->prepare("INSERT INTO submission_events(submission_id,event_type,private_note,created_at) VALUES (:id,'submitted',:note,1)");
    $stmt->execute(['id'=>$oldId,'note'=>$oldNote]);
    review_throws(RuntimeException::class,fn()=>submission_migrate($db),'Migration of existing data requires original key');
    review_check((int)$db->query('SELECT COUNT(*) FROM submission_schema_versions')->fetchColumn()===1
        && $db->query('SELECT private_payload FROM property_submissions')->fetchColumn()===$private,'Failed migration rolls back without loss');
    review_throws(RuntimeException::class,fn()=>submission_migrate($db,new SubmissionCrypto(str_repeat('b',64))),'Wrong key aborts migration');
    review_check(submission_migrate($db,$crypto)===[2] && submission_migrate($db,$crypto)===[],'Version 2 applies and repeats safely');
    review_check($db->query('SELECT submitter_role FROM property_submissions')->fetchColumn()==='author'
        && (int)$db->query('SELECT COUNT(*) FROM submission_genres')->fetchColumn()===2,'Role and genre projections backfilled');
    review_check($db->query('SELECT private_payload FROM property_submissions')->fetchColumn()===$private
        && $db->query('SELECT private_note FROM submission_events')->fetchColumn()===$oldNote
        && $db->query('SELECT token_hash FROM submission_email_tokens')->fetchColumn()==='old-token','Migration preserves ciphertext audit and tokens');
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,display_name TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)");
    $db->exec("INSERT INTO users VALUES (1,'Local Admin','admin',1,0),(2,'Editor','editor',1,0),(3,'Inactive','admin',0,0)");
    $mail=new MockSubmissionMailer();
    $service=new SubmissionService($db,$crypto,$mail,'http://localhost/author-submissions');
    $ids=[];
    $now=strtotime('2026-11-01T12:00:00Z');
    foreach (['new','reviewing','needs_information','research_candidate','closed','duplicate','spam'] as $index=>$status) {
        $input=valid_submission();
        $input['title']='Review Property '.$index;
        $input['role']=$index%2===0 ? 'author' : 'agent';
        $input['genre']=$index%2===0 ? 'Fantasy' : 'Mystery';
        $input['isbn']='';
        $service->submit($input,hash('sha256','review-'.$index),'client-'.$index,$now+$index);
        $token=mock_token($mail);
        review_check($service->verify($token,'verify-'.$index,$now+$index+10),'Email confirms synthetic record');
        $id=$db->query("SELECT id FROM property_submissions WHERE book_title = 'Review Property {$index}'")->fetchColumn();
        $ids[$status]=$id;
        if ($status!=='new') $service->review($id,$status,'Initial note',1,1,$now+20);
    }
    $dashboard=$service->dashboard();
    review_check($dashboard['total']===4 && count($dashboard['rows'])===4,'Default inbox has only verified actionable statuses');
    review_check($dashboard['counts']['inbox']===4 && $dashboard['counts']['unverified']===1
        && $dashboard['counts']['research_candidate']===1 && $dashboard['counts']['spam']===1,'Global summary counts separate inbox and archive');
    review_check($dashboard['rows'][0]['review_status']==='new' && $dashboard['rows'][1]['review_status']==='needs_information','Default priority sorting');
    foreach ($ids as $status=>$id) review_check($service->dashboard(['status'=>$status])['total']===1,'Exact status filter '.$status);
    review_check($service->dashboard(['status'=>'all','verification'=>'unverified'])['total']===1,'Unverified view separated');
    review_check($service->dashboard(['verification'=>'all'])['total']===4,'Inbox cannot include unverified/spam through override');
    review_check($service->dashboard(['role'=>'agent'])['total']===2 && $service->dashboard(['genre'=>'fAnTaSy'])['total']===2,'Indexed role and case-normalized genre filters');
    review_check($service->dashboard(['search'=>'Review Property 3'])['total']===1,'Bound title search');
    review_check($service->dashboard(['search'=>"%' OR 1=1 --"])['total']===0,'Search input cannot widen query');
    review_check($service->dashboard(['search'=>'%'])['total']===0,'Search wildcards treated literally');
    review_check($service->dashboard(['from'=>'2026-11-01','to'=>'2026-11-01'])['total']===4
        && $service->dashboard(['from'=>'2026-11-02'])['total']===0,'Inclusive UTC date filters');
    review_check($service->dashboard(['sort'=>'newest'])['rows'][0]['id']===$ids['research_candidate'],'Newest sort');
    review_check($service->dashboard(['sort'=>'oldest'])['rows'][0]['id']===$ids['new'],'Oldest sort');
    foreach ([['status'=>'invalid'],['role'=>[]],['from'=>'2026-02-30'],['from'=>'2026-11-02','to'=>'2026-11-01'],['sort'=>'id; DROP'],['page'=>'0'],['verification'=>'no']] as $bad) {
        review_throws(SubmissionInputException::class,fn()=>$service->dashboard($bad),'Invalid filters rejected');
    }
    $detail=$service->detail($ids['new']);
    $evaluation=['premise'=>'Clear high-concept premise <script>','rights_questions'=>'Screen rights still unclear'];
    $service->review($ids['new'],'reviewing','Private reviewer note',1,(int)$detail['revision'],$now+30,$evaluation);
    $detail=$service->detail($ids['new']);
    review_check($detail['evaluation']['premise']===$evaluation['premise'] && $detail['events'][0]['event_type']==='evaluation_updated','Optional qualitative evaluation saved and audited');
    review_check(str_contains($detail['events'][0]['note'],'Screen rights still unclear')
        && $detail['events'][1]['note']==='Private reviewer note' && $detail['events'][1]['actor_name']==='Local Admin','Evaluation snapshot notes and reviewer attribution');
    $payload=$db->prepare('SELECT evaluation_payload FROM property_submissions WHERE id=:id');
    $payload->execute(['id'=>$ids['new']]);
    review_check(!str_contains($payload->fetchColumn(),'high-concept'),'Evaluation encrypted at rest');
    review_throws(SubmissionConflictException::class,fn()=>$service->review($ids['new'],'closed','Stale',1,1,$now+31,[]),'Stale review cannot overwrite evaluation');
    $before=count($detail['events']);
    review_throws(SubmissionInputException::class,fn()=>$service->review($ids['new'],'closed','Editor',2,(int)$detail['revision'],$now+31),'Editor not authorized');
    review_throws(SubmissionInputException::class,fn()=>$service->review($ids['new'],'closed','Inactive',3,(int)$detail['revision'],$now+31),'Inactive administrator not authorized');
    review_throws(SubmissionInputException::class,fn()=>$service->review($ids['new'],'closed','',1,(int)$detail['revision'],$now+31,['premise'=>str_repeat('x',1001)]),'Evaluation length checked');
    review_throws(SubmissionInputException::class,fn()=>$service->review($ids['new'],'closed','',1,(int)$detail['revision'],$now+31,['score'=>'profitable']),'Undeclared evaluation criteria rejected');
    review_check(count($service->detail($ids['new'])['events'])===$before,'Rejected reviews leave no audit or state change');
    $service->review($ids['new'],'research_candidate','Candidate for research only',1,(int)$detail['revision'],$now+32,[]);
    $detail=$service->detail($ids['new']);
    review_check(implode('',$detail['evaluation'])==='' && str_contains($detail['events'][0]['note'],'(not recorded)'),'Evaluation can be cleared with audit snapshot');
    review_check($detail['private']['authorization']==='unknown' && $detail['authorization_status']==='self_declared','Research candidate not rights approval');
    $service->review($oldId,'pending_email','Unverified note only',1,0,$now+40);
    review_throws(SubmissionInputException::class,fn()=>$service->review($oldId,'research_candidate','',1,1,$now+41),'Unverified cannot be promoted');
    $service->review($oldId,'spam','Suspected spam',1,1,$now+42);
    review_check($service->detail($oldId)!==null && $service->dashboard()['total']===4,'Spam flag retains record and excludes inbox');
    // A newly delivered email link cannot undo a reviewer spam flag.
    $input=valid_submission(); $input['title']='Flag before confirmation'; $input['isbn']='';
    $service->submit($input,hash('sha256','unverified-flag'),'flag-client',$now+50);
    $token=mock_token($mail);
    $flagId=$db->query("SELECT id FROM property_submissions WHERE book_title='Flag before confirmation'")->fetchColumn();
    $service->review($flagId,'spam','Do not auto-promote',1,0,$now+51);
    review_check($service->verify($token,'verify-flag',$now+52)
        && $service->detail($flagId)['review_status']==='spam','Email confirmation preserves reviewer quarantine');
    for ($i=0;$i<24;$i++) {
        $detail=$service->detail($ids['new']);
        $service->review($ids['new'],'research_candidate','History '.$i,1,(int)$detail['revision'],$now+100+$i);
    }
    $detail=$service->detail($ids['new']);
    review_check(count($detail['events'])===20 && $detail['event_pages']>=2
        && $service->detail($ids['new'],2)['events']!==[],'Activity history paginated rather than discarded');
    $db->exec("CREATE TABLE leads(id INTEGER PRIMARY KEY,marker TEXT)");
    $db->exec("INSERT INTO leads VALUES (1,'unchanged')");
    // Large volume seeded directly without bypassing any publicly reachable route.
    $db->beginTransaction();
    $seed=$db->prepare("INSERT INTO property_submissions(id,isbn,book_title,book_authors,book_json,private_payload,email_hash,
        property_hash,idempotency_hash,payload_hash,receipt_hash,consent_version,privacy_version,consent_at,last_send_at,created_at,updated_at,
        verified_at,review_status,submitter_role)
        VALUES (:id,NULL,:title,'Bulk Author',:book,:private,'bulk-email',:id,:id,:id,:id,'consent','privacy',:now,:now,:now,:now,:now,'new','scout')");
    for ($i=0;$i<2000;$i++) $seed->execute(['id'=>substr(hash('sha256','bulk-'.$i),0,32),'title'=>'Bulk '.str_pad((string)$i,4,'0',STR_PAD_LEFT),'book'=>$book,'private'=>$private,'now'=>$now+200+$i]);
    $db->commit();
    $bulk=$service->dashboard(['role'=>'scout','sort'=>'title','page'=>'2']);
    review_check($bulk['total']===2000 && $bulk['pages']===100 && count($bulk['rows'])===20
        && $bulk['rows'][0]['book_title']==='Bulk 0020','Large volume count pagination and deterministic sort');
    $last=$service->dashboard(['role'=>'scout','page'=>'999999']);
    review_check($last['filters']['page']===100 && count($last['rows'])===20,'Out-of-range pages clamp safely');
    review_check(!array_key_exists('private_payload',$bulk['rows'][0]) && !array_key_exists('email',$bulk['rows'][0]),'Queue never decrypts or exposes contact payloads');
    review_check($db->query('SELECT marker FROM leads')->fetchColumn()==='unchanged','Review never writes public leads');
    [$where,$params]=submission_review_where(submission_review_filters(['status'=>'new','sort'=>'oldest']));
    $plan=$db->prepare('EXPLAIN QUERY PLAN SELECT id FROM property_submissions s WHERE '.$where.' ORDER BY created_at,id LIMIT 20');
    $plan->execute($params);
    review_check(str_contains(json_encode($plan->fetchAll(PDO::FETCH_ASSOC)),'submission_dashboard_idx'),'Actionable status listing uses index');
    [$where,$params]=submission_review_where(submission_review_filters([]));
    $plan=$db->prepare("EXPLAIN QUERY PLAN SELECT id FROM property_submissions s INDEXED BY submission_priority_idx WHERE ".$where."
        ORDER BY CASE s.review_status WHEN 'new' THEN 0 WHEN 'needs_information' THEN 1 WHEN 'reviewing' THEN 2 WHEN 'research_candidate' THEN 3 ELSE 4 END,s.created_at,s.id LIMIT 20");
    $plan->execute($params);
    review_check(str_contains(json_encode($plan->fetchAll(PDO::FETCH_ASSOC)),'submission_priority_idx'),'Default priority inbox uses its expression index');
    $delete=$db->prepare('DELETE FROM property_submissions WHERE id=:id');
    $delete->execute(['id'=>$oldId]);
    review_check((int)$db->query("SELECT COUNT(*) FROM submission_genres WHERE submission_id='{$oldId}'")->fetchColumn()===0
        && (int)$db->query("SELECT COUNT(*) FROM submission_events WHERE submission_id='{$oldId}'")->fetchColumn()===0
        && (int)$db->query("SELECT COUNT(*) FROM submission_email_tokens WHERE submission_id='{$oldId}'")->fetchColumn()===0,'Migrated foreign-key cascades retained');
    echo "PASS reviewer driver: $driver\n";
}
echo "PASS: $checks reviewer checks; synthetic databases and mocked mail only.\n";
