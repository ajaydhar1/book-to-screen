<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('B2S_SUBMISSION_TEST_HELPERS', true);
require_once __DIR__ . '/submissions-test.php';
require_once __DIR__ . '/../includes/submissions/lookup-cache.php';

$checks = 0;
function bot_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function bot_throws(string $class, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        bot_check($e instanceof $class, $message . ' (' . get_class($e) . ')');
        return;
    }
    throw new RuntimeException('FAIL: ' . $message);
}
function bot_config(?Closure $transport = null): SubmissionTurnstile
{
    return new SubmissionTurnstile('production', str_repeat('s', 22), str_repeat('k', 32),
        'books.example.org', 'author_submission', $transport);
}

$now = time();
$success = ['success' => true, 'hostname' => 'books.example.org', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now)];
$cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-antibot-' . bin2hex(random_bytes(8));
mkdir($cacheDirectory, 0700);
$cache = $cacheDirectory . DIRECTORY_SEPARATOR . 'submissions-isbn-cache.json';
try {
    putenv('B2S_TURNSTILE_ENV');
    bot_check(SubmissionTurnstile::fromEnvironment()->environment === 'disabled', 'Disabled by default');
    putenv('B2S_TURNSTILE_ENV=development');
    putenv('B2S_TURNSTILE_DEV_SITE_KEY=' . SubmissionTurnstile::TEST_SITES[0]);
    putenv('B2S_TURNSTILE_DEV_SECRET=' . SubmissionTurnstile::TEST_SECRETS[0]);
    putenv('B2S_TURNSTILE_DEV_HOSTNAME=localhost');
    putenv('B2S_TURNSTILE_DEV_ACTION=test');
    bot_check(SubmissionTurnstile::fromEnvironment()->siteKey === SubmissionTurnstile::TEST_SITES[0], 'Development environment reads development-only keys');
    putenv('B2S_TURNSTILE_ENV=production');
    foreach (['SITE_KEY','SECRET','HOSTNAME','ACTION'] as $name) putenv('B2S_TURNSTILE_PROD_' . $name);
    bot_throws(RuntimeException::class,fn()=>SubmissionTurnstile::fromEnvironment(),'Production never falls back to development settings');
    putenv('B2S_TURNSTILE_ENV');
    bot_throws(RuntimeException::class, fn() => (new SubmissionTurnstile())->requireForBaseUrl('https://books.example.org/author-submissions'), 'Public base cannot bypass anti-bot');
    bot_throws(RuntimeException::class, fn() => new SubmissionTurnstile('typo'), 'Unknown environment rejected');
    bot_throws(RuntimeException::class, fn() => new SubmissionTurnstile('production'), 'Incomplete configuration rejected');
    foreach (SubmissionTurnstile::TEST_SITES as $site) {
        bot_throws(RuntimeException::class, fn() => new SubmissionTurnstile('production', $site, str_repeat('k',32), 'books.example.org', 'author_submission'), 'Production rejects official test site');
    }
    foreach (SubmissionTurnstile::TEST_SECRETS as $secret) {
        bot_throws(RuntimeException::class, fn() => new SubmissionTurnstile('production', str_repeat('s',22), $secret, 'books.example.org', 'author_submission'), 'Production rejects official test secret');
    }
    $dev = new SubmissionTurnstile('development', SubmissionTurnstile::TEST_SITES[0], SubmissionTurnstile::TEST_SECRETS[0], 'localhost', 'test');
    bot_throws(RuntimeException::class, fn() => $dev->requireForBaseUrl('https://books.example.org/author-submissions'), 'Development cannot serve public intake');
    bot_throws(RuntimeException::class, fn() => bot_config()->requireForBaseUrl('http://books.example.org/author-submissions'), 'Public intake requires HTTPS');
    bot_throws(RuntimeException::class, fn() => bot_config()->requireForBaseUrl('https://other.example.org/author-submissions'), 'Public intake hostname must match');
    bot_throws(RuntimeException::class, fn() => bot_config()->requireForBaseUrl('http://127.0.0.1:8084/author-submissions'), 'Production config cannot serve loopback fixtures');
    bot_config()->requireForBaseUrl('https://books.example.org/author-submissions');
    bot_check(true, 'Matching production boundary accepted without activation');
    $sent = [];
    $verifier = bot_config(static function (string $secret, string $token) use (&$sent, $success): array {
        $sent = [$secret, $token];
        return $success;
    });
    foreach ([null, '', [], str_repeat('x',2049), "abc\0def", "abc def"] as $token) {
        bot_throws(SubmissionInputException::class, fn() => $verifier->verify($token,$now), 'Missing/malformed/oversized token fails');
    }
    bot_check($sent === [], 'Malformed tokens never reach provider');
    $verifier->verify('sample-token', $now);
    bot_check($sent === [str_repeat('k',32),'sample-token'], 'Transport receives only secret and token');
    foreach ([
        ['success'=>false, 'error-codes'=>['invalid-input-response']],
        ['success'=>false, 'error-codes'=>['timeout-or-duplicate']],
        array_replace($success,['hostname'=>'evil.example.org']),
        array_replace($success,['action'=>'another_form']),
        array_diff_key($success,['action'=>true]),
        array_replace($success,['challenge_ts'=>gmdate('c',$now-301)]),
        array_replace($success,['challenge_ts'=>gmdate('c',$now+61)]),
        array_replace($success,['challenge_ts'=>'invalid']),
    ] as $response) {
        bot_throws(SubmissionInputException::class, fn() => bot_config(fn()=>$response)->verify('sample-token',$now), 'Failed, expired, reused or wrong-context token rejected');
    }
    foreach ([null, [], ['success'=>'true'], ['success'=>false,'error-codes'=>['internal-error']],
        ['success'=>false,'error-codes'=>['invalid-input-secret']]] as $response) {
        bot_throws(SubmissionAntibotUnavailable::class, fn() => bot_config(fn()=>$response)->verify('sample-token',$now), 'Provider failure fails closed');
    }
    bot_throws(SubmissionAntibotUnavailable::class, fn() => bot_config(static function () {
        throw new SubmissionAntibotUnavailable('Mock timeout');
    })->verify('sample-token',$now), 'Timeout fails closed');
    $dummy = ['success'=>true,'hostname'=>'example.com','metadata'=>['result_with_testing_key'=>true]];
    $dummyDev = new SubmissionTurnstile('development',SubmissionTurnstile::TEST_SITES[0],SubmissionTurnstile::TEST_SECRETS[0],'localhost','test',fn()=>$dummy);
    $dummyDev->verify('XXXX.DUMMY.TOKEN.XXXX',$now);
    bot_check(true,'Official development response sentinel accepted');
    bot_throws(SubmissionInputException::class, fn() => $dummyDev->verify('other-token',$now),'Dummy sentinel requires dummy token');
    bot_throws(SubmissionInputException::class, fn() => bot_config(fn()=>$dummy)->verify('XXXX.DUMMY.TOKEN.XXXX',$now),'Production never accepts development sentinel');
    foreach ([null, [], 'filled', ' '] as $value) {
        bot_throws(SubmissionInputException::class, fn() => submission_check_honeypot(['company-website'=>$value]),'Populated honeypot rejected');
    }
    submission_check_honeypot(['company-website'=>'']);
    bot_check(true,'Empty honeypot accepted');
    foreach (['sqlite','cloud-offline'] as $driver) {
        $db = $driver === 'sqlite' ? new LocalSqliteDatabase(':memory:') : fixture_cloud_db();
        submission_migrate($db);
        $mailer = new MockSubmissionMailer();
        $crypto = new SubmissionCrypto(str_repeat('a',64));
        bot_throws(RuntimeException::class, fn() => new SubmissionService($db,$crypto,$mailer,'https://books.example.org/author-submissions'), 'Service refuses nonlocal disabled configuration');
        $used = [];
        $calls = 0;
        $verified = bot_config(static function ($secret,$token) use (&$used,&$calls,$success): array {
            $calls++;
            if (isset($used[$token])) return ['success'=>false,'error-codes'=>['timeout-or-duplicate']];
            $used[$token]=true;
            return $success;
        });
        $service = new SubmissionService($db,$crypto,$mailer,'https://books.example.org/author-submissions',$verified);
        $input = valid_submission();
        $key = str_repeat('c',64);
        bot_throws(SubmissionInputException::class, fn()=>$service->submit($input,$key,'missing',$now),'Missing verification blocks storage');
        $input['company-website']='spam';
        bot_throws(SubmissionInputException::class, fn()=>$service->submit($input,$key,'honeypot',$now),'Honeypot blocks storage');
        bot_check($calls===0 && (int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===0 && $mailer->messages===[],'Rejected bot writes no submission/token/email');
        $input['company-website']='';
        $input['turnstile_token']='first-token';
        $first = $service->submit($input,$key,'person',$now);
        bot_throws(SubmissionInputException::class, fn()=>$service->submit($input,$key,'person',$now),'Used token cannot replay even idempotent request');
        $input['turnstile_token']='fresh-token';
        bot_check($service->submit($input,$key,'person',$now)===$first,'Fresh-token retry preserves idempotency');
        bot_check((int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===1 && count($mailer->messages)===1,'One record and one mocked email');
        $row=$db->query('SELECT book_json,private_payload FROM property_submissions')->fetch(PDO::FETCH_ASSOC);
        bot_check(!str_contains($row['book_json'].$crypto->decrypt($row['private_payload']),'turnstile_token')
            && !str_contains($row['book_json'].$crypto->decrypt($row['private_payload']),'company-website'),'Token and honeypot never persisted');
        for ($i=0;$i<10;$i++) {
            try { $service->submit([],$key,'flood',$now); } catch (SubmissionInputException $e) {}
        }
        $before=$calls;
        bot_throws(SubmissionRateException::class,fn()=>$service->submit([],$key,'flood',$now),'Submission limit still enforced');
        bot_check($calls===$before,'Rate-limited request never calls Siteverify');
    }
    $lookups=0;
    $lookup=static function (string $isbn) use (&$lookups): array {
        $lookups++;
        return ['status'=>'success','metadata'=>['isbn'=>$isbn,'title'=>'Sample Book'],'message'=>'Found'];
    };
    $a=submission_cached_book_lookup('0140328726',$cache,$lookup,$now);
    bot_check(submission_cached_book_lookup('978-0-14-032872-1',$cache,$lookup,$now+1)===$a && $lookups===1,'Canonical ISBN-10/13 share cache');
    submission_cached_book_lookup('0140328726',$cache,$lookup,$now+21600);
    bot_check($lookups===2,'Success cache expires after six hours');
    $misses=0;
    $missing=static function () use (&$misses): array {
        $misses++;
        return ['status'=>'not_found','metadata'=>null,'message'=>'No match'];
    };
    submission_cached_book_lookup('9780439023481',$cache,$missing,$now);
    submission_cached_book_lookup('9780439023481',$cache,$missing,$now+599);
    bot_check($misses===1,'No-result cache prevents repeated requests');
    submission_cached_book_lookup('9780439023481',$cache,$missing,$now+600);
    bot_check($misses===2,'No-result cache expires after ten minutes');
    $errors=0;
    $failure=static function () use (&$errors): array {
        $errors++;
        return ['status'=>'api_error','metadata'=>null,'message'=>'Mock timeout'];
    };
    submission_cached_book_lookup('9780061120084',$cache,$failure,$now);
    submission_cached_book_lookup('9780061120084',$cache,$failure,$now);
    bot_check($errors===2,'API failures are not cached');
    bot_check(submission_cached_book_lookup('invalid',$cache,$lookup,$now)['status']==='invalid_isbn','Invalid ISBN never calls provider');
    $lock=fopen($cache,'r+b');
    flock($lock,LOCK_EX);
    bot_throws(RuntimeException::class,fn()=>submission_cached_book_lookup('0140328726',$cache,$lookup,$now),'Concurrent cache contention fails promptly, not provider stampede');
    fclose($lock);
    for ($i=0;$i<70;$i++) {
        $base='978' . str_pad((string)$i,9,'0',STR_PAD_LEFT);
        $sum=0;
        for ($digit=0;$digit<12;$digit++) $sum+=(int)$base[$digit]*($digit%2===0 ? 1 : 3);
        submission_cached_book_lookup($base . ((10-$sum%10)%10),$cache,$lookup,$now);
    }
    bot_check(count(json_decode(file_get_contents($cache),true,16,JSON_THROW_ON_ERROR))===64,'Cache retains at most 64 canonical editions');
    file_put_contents($cache,str_repeat('x',2097153));
    bot_throws(RuntimeException::class,fn()=>submission_cached_book_lookup('0140328726',$cache,$lookup,$now),'Cache rejects files exceeding 2 MiB');
    file_put_contents($cache,'bad json');
    bot_throws(JsonException::class,fn()=>submission_cached_book_lookup('0140328726',$cache,$lookup,$now),'Corrupt cache fails explicitly');
    bot_throws(RuntimeException::class,fn()=>submission_cached_book_lookup('0140328726',__DIR__ . DIRECTORY_SEPARATOR . 'submissions-isbn-cache.json',$lookup,$now),'Cache cannot write in web root');
    if (($argv[1] ?? '') === '--live-official-tests') {
        foreach (SubmissionTurnstile::TEST_SECRETS as $index=>$secret) {
            $official=new SubmissionTurnstile('development',SubmissionTurnstile::TEST_SITES[0],$secret,'localhost','test');
            if ($index===0) {
                $official->verify('XXXX.DUMMY.TOKEN.XXXX',time());
                bot_check(true,'Official always-pass Siteverify');
            } else {
                bot_throws(SubmissionInputException::class,fn()=>$official->verify('XXXX.DUMMY.TOKEN.XXXX',time()),'Official fail/spent Siteverify');
            }
        }
    }
    echo "PASS: $checks anti-bot/cache checks; local databases, mocked email, " . (($argv[1]??'')==='--live-official-tests' ? 'three official dummy Siteverify calls.' : 'mocked Siteverify only.') . "\n";
} finally {
    if (is_file($cache)) unlink($cache);
    rmdir($cacheDirectory);
}
