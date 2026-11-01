<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/turnstile-challenge.php';
$checks = 0;
function challenge_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$now = time();
$server = ['HTTPS' => 'on', 'HTTP_HOST' => 'booktoscreen.org', 'SCRIPT_NAME' => '/__turnstile-validation/index.php',
    'REQUEST_URI' => '/__turnstile-validation/index.php', 'REMOTE_USER' => 'fixture-tester'];
$settings = ['enabled' => true, 'user' => 'fixture-tester', 'deadline' => $now + 1800];
challenge_check(submission_challenge_gate($server, $settings, $now), 'Exact restricted HTTPS boundary allowed');
foreach ([['HTTPS' => 'off'], ['HTTP_HOST' => 'www.booktoscreen.org'], ['HTTP_HOST' => 'booktoscreen.org:443'],
    ['REMOTE_USER' => ''], ['REMOTE_USER' => 'other'], ['SCRIPT_NAME' => '/author-submissions/index.php'],
    ['REQUEST_URI' => '/__turnstile-validation/index.php/extra']] as $override) {
    challenge_check(!submission_challenge_gate(array_replace($server, $override), $settings, $now), 'Wrong transport/origin/auth/path denied');
}
foreach ([['enabled' => false], ['enabled' => 'true'], ['user' => ''], ['deadline' => $now],
    ['deadline' => $now + 3601], ['deadline' => 'invalid']] as $override) {
    challenge_check(!submission_challenge_gate($server, array_replace($settings, $override), $now), 'Disabled/expired/oversized window denied');
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-challenge-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Private fixture unavailable.');
$configPath = $directory . DIRECTORY_SEPARATOR . 'config.json';
$budgetPath = $directory . DIRECTORY_SEPARATOR . 'budget.txt';
$sessionsPath = $directory . DIRECTORY_SEPARATOR . 'sessions';
$settingsPath = $directory . DIRECTORY_SEPARATOR . 'settings.json';
try {
    if (!mkdir($sessionsPath, 0700)) throw new RuntimeException('Private fixture sessions unavailable.');
    $config = ['base_url' => 'https://booktoscreen.org/author-submissions/', 'hostname' => 'booktoscreen.org',
        'action' => 'author_submission', 'widget_mode' => 'managed', 'pre_clearance' => false,
        'site_key' => str_repeat('s', 22), 'secret_key' => str_repeat('k', 32)];
    file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));
    file_put_contents($budgetPath, '0');
    if (DIRECTORY_SEPARATOR !== '\\') { chmod($configPath, 0600); chmod($budgetPath, 0600); }
    $fullSettings = array_merge($settings, ['widget_config' => $configPath, 'sessions' => $sessionsPath, 'budget' => $budgetPath]);
    file_put_contents($settingsPath, json_encode($fullSettings, JSON_THROW_ON_ERROR));
    if (DIRECTORY_SEPARATOR !== '\\') chmod($settingsPath, 0600);
    challenge_check(submission_challenge_settings($settingsPath) === $fullSettings, 'One private settings file retains exact values without key duplication');
    foreach ([['enabled' => '1'], ['deadline' => (string) ($now + 1800)], ['user' => ''],
        ['widget_config' => 'relative.json'], ['budget' => null], ['secret_key' => 'not-allowed']] as $override) {
        file_put_contents($settingsPath, json_encode(array_replace($fullSettings, $override), JSON_THROW_ON_ERROR));
        try { submission_challenge_settings($settingsPath); throw new LogicException('Invalid settings accepted'); }
        catch (RuntimeException $e) { challenge_check(true, 'Malformed or unexpected private settings rejected'); }
    }
    file_put_contents($settingsPath, '{"enabled":');
    try { submission_challenge_settings($settingsPath); throw new LogicException('Malformed settings accepted'); }
    catch (RuntimeException $e) { challenge_check(true, 'Malformed settings JSON fails closed'); }
    file_put_contents($settingsPath, json_encode($fullSettings, JSON_THROW_ON_ERROR));
    challenge_check(submission_challenge_config($configPath) === $config, 'Gated runtime reader uses same validated config');
    try { submission_challenge_config(__FILE__); throw new LogicException('Public source accepted as private config'); }
    catch (RuntimeException $e) { challenge_check(true, 'Runtime still rejects files within application/web root'); }
    $responses = [
        ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now)],
        ['success' => false, 'error-codes' => ['timeout-or-duplicate']],
        ['success' => false, 'error-codes' => ['invalid-input-response']],
        ['success' => true, 'hostname' => 'wrong.example.com', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now)],
        ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'wrong', 'challenge_ts' => gmdate('c', $now)],
        ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now - 301)],
        ['success' => false, 'error-codes' => ['invalid-input-secret']],
    ];
    foreach ($responses as $i => $response) {
        $calls = 0;
        $verifier = new SubmissionTurnstile('production', $config['site_key'], $config['secret_key'], 'booktoscreen.org', 'author_submission',
            static function () use ($response, &$calls) { $calls++; return $response; });
        $result = submission_challenge_verify($verifier, 'fixture-token', $now, static fn() => submission_challenge_budget($budgetPath));
        challenge_check($result['status'] === ($i === 0 ? 200 : ($i === 6 ? 503 : 422)), 'Fresh/reused/invalid/context/expiry/provider outcome');
        challenge_check($calls === 1, 'Exactly one mocked Siteverify call per attempt');
    }
    foreach (['', null, [], "token\n", str_repeat('t', 2049)] as $token) {
        $result = submission_challenge_verify($verifier, $token, $now, static function () { throw new RuntimeException('Must not reserve malformed tokens'); });
        challenge_check($result['status'] === 422, 'Missing/malformed token rejected without provider budget');
    }
    for ($i = 7; $i < 12; $i++) submission_challenge_budget($budgetPath);
    challenge_check(file_get_contents($budgetPath) === '12', 'Shared budget counts attempted calls');
    $result = submission_challenge_verify($verifier, 'fixture-token', $now, static fn() => submission_challenge_budget($budgetPath));
    challenge_check($result['status'] === 429 && file_get_contents($budgetPath) === '12', 'Exhausted budget never retries or increments');
    $handle = fopen($budgetPath, 'r+');
    flock($handle, LOCK_EX);
    try { submission_challenge_budget($budgetPath); throw new LogicException('Lock accepted'); }
    catch (RuntimeException $e) { challenge_check(true, 'Busy budget fails closed'); }
    fclose($handle);
    file_put_contents($budgetPath, 'invalid');
    try { submission_challenge_budget($budgetPath); throw new LogicException('Corrupt budget accepted'); }
    catch (RuntimeException $e) { challenge_check(true, 'Corrupt budget fails closed'); }
    $code = 'putenv("B2S_TURNSTILE_VALIDATION_SETTINGS_FILE"); register_shutdown_function(static function(){echo "|STATUS:".http_response_code();}); require '
        . var_export(__DIR__ . '/../__turnstile-validation/index.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Disabled page test unavailable.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    challenge_check(proc_close($process) === 0 && str_contains($output, '|STATUS:404') && !str_contains($output, 'challenges.cloudflare.com'), 'Default page denies before keys/widget/network');
    foreach (['GET', 'POST', 'DISABLED', 'EXPIRED', 'WRONG_USER', 'LEGACY', 'MISSING_FILE'] as $method) {
        $caseSettings = $fullSettings;
        if ($method === 'DISABLED') $caseSettings['enabled'] = false;
        if ($method === 'EXPIRED') $caseSettings['deadline'] = $now - 1;
        if (in_array($method, ['DISABLED', 'EXPIRED', 'WRONG_USER'], true)) {
            $caseSettings['widget_config'] = $configPath . '.missing';
        }
        file_put_contents($settingsPath, json_encode($caseSettings, JSON_THROW_ON_ERROR));
        $requestServer = array_replace($server, ['REQUEST_METHOD' => $method]);
        if ($method === 'WRONG_USER') $requestServer['REMOTE_USER'] = 'other';
        $code = '$_SERVER=' . var_export($requestServer, true) . '; $_GET=[];'
            . 'putenv(' . var_export($method === 'LEGACY' ? 'B2S_TURNSTILE_VALIDATION_SETTINGS_FILE'
                : 'B2S_TURNSTILE_VALIDATION_SETTINGS_FILE=' . ($method === 'MISSING_FILE' ? $settingsPath . '.missing' : $settingsPath), true) . ');'
            . 'putenv("B2S_TURNSTILE_VALIDATION_ENABLED=1");'
            . 'register_shutdown_function(static function(){echo "|STATUS:".http_response_code();'
            . 'echo "|NO_SERVICES:".(int)(!class_exists("LocalSqliteDatabase",false)&&!class_exists("SmtpSubmissionMailer",false));});'
            . 'require ' . var_export(__DIR__ . '/../__turnstile-validation/index.php', true) . ';';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Gated render test unavailable.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        challenge_check(proc_close($process) === 0, 'Gated render/CSRF subprocess completes offline');
        challenge_check(!str_contains($output, $config['secret_key']) && str_contains($output, '|NO_SERVICES:1'), 'No secret output or DB/mail service dependency');
        challenge_check($method === 'GET' ? str_contains($output, 'data-csrf=') && str_contains($output, $config['site_key'])
            : str_contains($output, '|STATUS:' . ($method === 'POST' ? '403' : ($method === 'MISSING_FILE' ? '503' : '404'))),
            'Gated render, CSRF, disabled/expired/auth, legacy and missing-file behavior');
    }
    echo "PASS: {$checks} challenge-only checks; mocked Siteverify, no deployment, DB, email or live credentials.\n";
} finally {
    foreach ([$configPath, $budgetPath, $settingsPath] as $path) if (is_file($path)) unlink($path);
    foreach (glob($sessionsPath . DIRECTORY_SEPARATOR . 'sess_*') as $path) if (is_file($path)) unlink($path);
    if (is_dir($sessionsPath)) rmdir($sessionsPath);
    rmdir($directory);
}
