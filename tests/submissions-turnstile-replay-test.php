<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== 'mock-curl-child') {
    $process = proc_open([PHP_BINARY, '-n', __FILE__, 'mock-curl-child'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Offline replay fixture unavailable.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    echo $output;
    if ($exit !== 0) fwrite(STDERR, $errors);
    exit($exit);
}
if (extension_loaded('curl')) throw new RuntimeException('Mock fixture must not load real cURL.');
foreach (['CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLOPT_HTTPHEADER', 'CURLOPT_CONNECTTIMEOUT',
    'CURLOPT_TIMEOUT', 'CURLOPT_FOLLOWLOCATION', 'CURLOPT_PROTOCOLS', 'CURLPROTO_HTTPS',
    'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_WRITEFUNCTION', 'CURLINFO_HTTP_CODE'] as $i => $name) {
    define($name, $i + 1);
}
$requests = [];
$responses = [];
$mockHttp = 200;
$mockExec = true;
$malformedBody = false;
if (!extension_loaded('curl')) {
function curl_init(string $url): object { return (object) ['url' => $url, 'options' => []]; }
function curl_setopt_array(object $handle, array $options): bool { $handle->options = $options; return true; }
function curl_exec(object $handle): bool
{
    global $requests, $responses, $mockExec, $malformedBody;
    $requests[] = $handle;
    if (!$mockExec) return false;
    $response = array_shift($responses);
    if ($response === null) throw new RuntimeException('Unplanned mocked provider call.');
    $body = $malformedBody ? '{invalid' : json_encode($response, JSON_THROW_ON_ERROR);
    return ($handle->options[CURLOPT_WRITEFUNCTION])($handle, $body) === strlen($body);
}
function curl_getinfo(object $handle, int $option): int { global $mockHttp; return $mockHttp; }
function curl_close(object $handle): void {}
}
require_once __DIR__ . '/../includes/submissions/turnstile-challenge.php';
$checks = 0;
function replay_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$now = time();
$success = ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission',
    'challenge_ts' => gmdate('c', $now)];
$token = 'synthetic.+/=_token';
$_SESSION = ['csrf' => 'synthetic-csrf'];
$session = $_SESSION;
foreach ([
    [[$success, $success], [200, 200]],
    [[$success, ['success' => false, 'error-codes' => ['timeout-or-duplicate']]], [200, 422]],
    [[array_replace($success, ['success' => 'true'])], [503]],
    [[array_replace($success, ['hostname' => 'wrong.example.org'])], [422]],
    [[array_replace($success, ['action' => 'wrong'])], [422]],
    [[array_replace($success, ['challenge_ts' => gmdate('c', $now - 301)])], [422]],
] as [$responses, $expected]) {
    $requests = [];
    $reserved = 0;
    $diagnosticState = [];
    foreach ($expected as $status) {
        $diagnostic = submission_challenge_diagnostic_start($diagnosticState, $token, 'Fresh token');
        $observed = [];
        $verifier = new SubmissionTurnstile('production', str_repeat('s', 22), str_repeat('k', 32),
            'booktoscreen.org', 'author_submission', null,
            static function (array $event) use (&$observed): void { $observed = array_replace($observed, $event); });
        $result = submission_challenge_verify($verifier, $token, $now,
            static function () use (&$reserved): void { $reserved++; });
        replay_check($result['status'] === $status, 'Actual verifier follows strict mocked provider outcome');
        replay_check($observed['siteverify_made'] === true && $observed['provider_http_status'] === 200,
            'Observer reports exactly the existing mocked cURL call and response HTTP status');
        if ($reserved > 1) {
            replay_check($diagnostic['same_as_previous_siteverify_token'] === true,
                'Actual client repeated identical token matches server-side diagnostic digest');
        }
        $diagnosticState['diagnostic_previous'] = $diagnostic['token_id'];
    }
    replay_check(count($requests) === count($expected) && $reserved === count($expected),
        'No response cache, skipped reservation or automatic retry');
    $firstBody = $requests[0]->options[CURLOPT_POSTFIELDS];
    foreach ($requests as $request) {
        parse_str($request->options[CURLOPT_POSTFIELDS], $decoded);
        replay_check($request->url === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $decoded['response'] === $token && array_keys($decoded) === ['secret', 'response']
            && $request->options[CURLOPT_POSTFIELDS] === $firstBody,
            'Exact token bytes survive form encoding; no idempotency key or substitution');
        replay_check($request->options[CURLOPT_SSL_VERIFYPEER] === true
            && $request->options[CURLOPT_SSL_VERIFYHOST] === 2
            && $request->options[CURLOPT_FOLLOWLOCATION] === false,
            'Verified TLS and no redirect remain enforced');
    }
    replay_check($_SESSION === $session, 'Verification does not mutate session or regenerate token');
}
foreach ([[502, true, false], [0, false, false], [200, true, true]] as [$mockHttp, $mockExec, $malformedBody]) {
    $observed = [];
    $requests = [];
    $responses = [$success];
    $verifier = new SubmissionTurnstile('production', str_repeat('s', 22), str_repeat('k', 32),
        'booktoscreen.org', 'author_submission', null,
        static function (array $event) use (&$observed): void { $observed = array_replace($observed, $event); });
    $result = submission_challenge_verify($verifier, $token, $now, static function (): void {});
    replay_check($result['status'] === 503 && count($requests) === 1 && $observed['siteverify_made'] === true
        && $observed['provider_http_status'] === ($mockHttp ?: null)
        && ($mockHttp === 502 ? $observed['success'] === true : !array_key_exists('success', $observed)),
        'HTTP, transport and malformed-JSON failures remain reject with only observed metadata and no retry');
}
echo "PASS: $checks replay/actual-client checks; mocked cURL only, no raw tokens or secrets printed.\n";
