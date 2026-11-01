<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('B2S_SUBMISSION_TEST_HELPERS', true);
require_once __DIR__ . '/submissions-test.php';

$base = $argv[1] ?? '';
$fixture = $argv[2] ?? '';
$password = getenv('B2S_LOCAL_ADMIN_PASSWORD') ?: '';
if (!preg_match('~^http://127\.0\.0\.1:\d+$~', $base) || basename($fixture) !== 'submissions-local.sqlite' || !is_file($fixture)) {
    fwrite(STDERR,"Use a loopback test server and its explicitly initialized fixture database.\n");
    exit(1);
}
$directory = dirname($fixture);
$cookie = $directory . DIRECTORY_SEPARATOR . 'http-test-cookies.txt';
$crypto = new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY') ?: '');
$db = new LocalSqliteDatabase($fixture);
$checks = 0;
function http_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function http_test_request(string $path, ?array $body = null, ?string $csrf = null, bool $form = false): array
{
    global $base, $cookie;
    $ch = curl_init($base . $path);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name,$value] = explode(':',$line,2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$form ? http_build_query($body) : json_encode($body,JSON_THROW_ON_ERROR));
        curl_setopt($ch,CURLOPT_HTTPHEADER,[
            $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json',
            'X-CSRF-Token: ' . ($csrf ?? ''),
        ]);
    }
    $body = curl_exec($ch);
    if ($body === false) throw new RuntimeException('HTTP test connection failed.');
    $code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code,'body'=>$body,'headers'=>$headers];
}
function page_csrf(string $body): string
{
    if (preg_match('/data-csrf="([a-f0-9]+)"/',$body,$match) || preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$match)) {
        return $match[1];
    }
    throw new RuntimeException('CSRF token not found.');
}
try {
    $page = http_test_request('/author-submissions/');
    http_check($page['code']===200,'Local gated page available');
    http_check(($page['headers']['cache-control']??'')==='no-store','Private page no-store');
    http_check(str_contains($page['body'],'Local infrastructure test')&&!str_contains($page['body'],'googletagmanager'),'Local UI clearly marked, no analytics');
    $csrf = page_csrf($page['body']);
    $denied = http_test_request('/admin/submissions.php');
    http_check($denied['code']===403,'Anonymous admin access denied');
    $input = valid_submission();
    $input['request_key']=str_repeat('9',64);
    $bad = http_test_request('/author-submissions/submit.php',$input,'bad');
    http_check($bad['code']===403 && (int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===0,'CSRF prevents persistence');
    $invalid = $input;
    $invalid['email']='bad';
    http_check(http_test_request('/author-submissions/submit.php',$invalid,$csrf)['code']===422,'Server validation errors');
    $spam = $input;
    $spam['company-website'] = 'automated';
    $honeypot = http_test_request('/author-submissions/submit.php',$spam,$csrf);
    http_check($honeypot['code']===422 && (int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===0,'Honeypot prevents persistence');
    http_check(str_contains($page['body'],'company-website')&&!str_contains($page['body'],'id="submission-security"'),'Local default honeypot present; Turnstile disabled');
    $recorded = http_test_request('/author-submissions/submit.php',$input,$csrf);
    http_check($recorded['code']===200,'Submission persisted locally');
    $receipt = json_decode($recorded['body'],true,16,JSON_THROW_ON_ERROR)['receipt'];
    $state = http_test_request('/author-submissions/receipt.php',['receipt'=>$receipt],$csrf);
    http_check(json_decode($state['body'],true)['state']==='pending_verification','Pending receipt state');
    $again = http_test_request('/author-submissions/submit.php',$input,$csrf);
    http_check($again['body']===$recorded['body']&&(int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===1,'HTTP repeated request idempotent');
    $mailbox = file($directory . DIRECTORY_SEPARATOR . 'submissions-mailbox.jsonl',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $message=json_decode($crypto->decrypt($mailbox[count($mailbox)-1]),true,16,JSON_THROW_ON_ERROR);
    parse_str(parse_url($message['link'],PHP_URL_FRAGMENT),$fragment);
    $token=$fragment['token'];
    $verification=http_test_request('/author-submissions/verify.php');
    http_check($verification['code']===200,'Scanner can open verification landing');
    http_check($db->query('SELECT consumed_at FROM submission_email_tokens')->fetchColumn()===null,'GET scanner does not consume token');
    $verifyCsrf=page_csrf($verification['body']);
    http_check(http_test_request('/author-submissions/verify.php',['token'=>$token],'bad')['code']===403,'Verification CSRF required');
    $verified=http_test_request('/author-submissions/verify.php',['token'=>$token],$verifyCsrf);
    http_check(json_decode($verified['body'],true)['state']==='verified','Verification POST succeeds');
    $reused=http_test_request('/author-submissions/verify.php',['token'=>$token],$verifyCsrf);
    http_check(json_decode($reused['body'],true)['state']==='unavailable','Reused token generic response');
    $unknown = http_test_request('/author-submissions/verify.php',['token'=>str_repeat('f',64)],$verifyCsrf);
    http_check($unknown['body']===$reused['body'],'Unknown and reused links have same response');
    $login=http_test_request('/author-submissions/staff-login.php');
    $loginCsrf=page_csrf($login['body']);
    $signed=http_test_request('/author-submissions/staff-login.php',['username'=>'local-reviewer','password'=>$password,'csrf'=>$loginCsrf],$loginCsrf,true);
    http_check($signed['code']===303,'Local fixture administrator authenticated');
    $queue=http_test_request('/admin/submissions.php');
    http_check($queue['code']===200&&str_contains($queue['body'],'Sample O&#039;Brien'),'Admin verified queue escaped');
    $id=$db->query('SELECT id FROM property_submissions')->fetchColumn();
    $detail=http_test_request('/admin/submissions.php?id='.$id);
    http_check($detail['code']===200&&str_contains($detail['body'],'reader@example.com'),'Authorized private detail');
    $reviewCsrf=page_csrf($detail['body']);
    http_check(http_test_request('/admin/submissions.php?id='.$id,['id'=>$id,'status'=>'reviewing','note'=>'Internal test note','revision'=>'0'],'bad',true)['code']===403,'Admin review CSRF enforced');
    $review=http_test_request('/admin/submissions.php?id='.$id,['id'=>$id,'status'=>'reviewing','note'=>'Internal test note','revision'=>'0','csrf'=>$reviewCsrf],$reviewCsrf,true);
    http_check($review['code']===303&&$db->query('SELECT review_status FROM property_submissions')->fetchColumn()==='reviewing','Admin status update');
    $db->exec('UPDATE users SET is_active = 0');
    http_check(http_test_request('/admin/submissions.php')['code']===403,'Revoked staff account immediately denied');
    $statusPage=http_test_request('/author-submissions/status.php');
    http_check($statusPage['code']===200&&str_contains($statusPage['body'],'Check verification status'),'Private receipt landing available');
    $receiptState=http_test_request('/author-submissions/receipt.php',['receipt'=>$receipt],$reviewCsrf);
    http_check(json_decode($receiptState['body'],true)['state']==='verified','Receipt reflects email verification');
    $countsBefore=(int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn();
    for($i=0;$i<11;$i++) {
        $limited=http_test_request('/author-submissions/submit.php',['request_key'=>str_repeat('e',64)],$reviewCsrf);
    }
    http_check($limited['code']===429&&(int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===$countsBefore,'HTTP submission rate limit blocks without new records');
    for($i=0;$i<21;$i++) {
        $limited=http_test_request('/author-submissions/verify.php',['token'=>str_repeat('f',64)],$reviewCsrf);
    }
    http_check($limited['code']===429,'HTTP verification rate limit enforced');
    for($i=0;$i<31;$i++) {
        $limited=http_test_request('/author-submissions/lookup.php?isbn=invalid');
        if ($i===0) http_check($limited['code']===422,'Malformed ISBN rejected without provider access');
    }
    http_check($limited['code']===429,'ISBN endpoint rate limits malformed requests too');
    $prototype=http_test_request('/submission-prototype/');
    http_check($prototype['code']===200&&!str_contains($prototype['body'],'data-mode="local-intake"'),'Original demo unchanged and inert');
    echo "PASS: {$checks} HTTP checks; mocked local delivery only.\n";
} finally {
    if(is_file($cookie))unlink($cookie);
}
