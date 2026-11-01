<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/turnstile-config.php';
$checks = 0;
function widget_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function widget_reject(callable $operation): void
{
    try { $operation(); } catch (RuntimeException $e) {
        widget_check(!str_contains($e->getMessage(), str_repeat('k', 32)), 'Exception contains no fixture secret');
        return;
    }
    throw new RuntimeException('FAIL: unsafe widget configuration accepted');
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-widget-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Could not prepare fixture.');
$path = $directory . DIRECTORY_SEPARATOR . 'turnstile.json';
$config = ['base_url' => 'https://booktoscreen.org/author-submissions/', 'hostname' => 'booktoscreen.org',
    'action' => 'author_submission', 'widget_mode' => 'managed', 'pre_clearance' => false, 'site_key' => '', 'secret_key' => ''];
$write = static function (array $data) use ($path): void {
    if (file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Fixture write failed.');
    if (DIRECTORY_SEPARATOR !== '\\' && !chmod($path, 0600)) throw new RuntimeException('Fixture permissions failed.');
};
try {
    $write($config);
    widget_check(submission_turnstile_private_config($path) === $config, 'Blank template parsed without activation');
    $full = array_replace($config, ['site_key' => str_repeat('s', 22), 'secret_key' => str_repeat('k', 32)]);
    $beforeEnvironment = getenv();
    $write($full);
    widget_check(submission_turnstile_private_config($path) === $full, 'Compatible keys accepted offline');
    widget_check(getenv() === $beforeEnvironment, 'Checker does not inject production credentials or flags');
    foreach ([
        ['base_url' => 'http://booktoscreen.org/author-submissions/'],
        ['base_url' => 'https://booktoscreen.org/another/'],
        ['hostname' => 'www.booktoscreen.org'], ['hostname' => 'localhost'],
        ['action' => 'test'], ['widget_mode' => 'invisible'], ['pre_clearance' => true],
        ['site_key' => []], ['secret_key' => "secret\n"], ['secret_key' => str_repeat('x', 101)],
        ['active' => true], ['transport' => 'live'],
    ] as $override) {
        $write(array_replace($full, $override));
        widget_reject(static fn() => submission_turnstile_private_config($path));
    }
    foreach (array_merge(SubmissionTurnstile::TEST_SITES, SubmissionTurnstile::TEST_SECRETS) as $testKey) {
        foreach (['site_key', 'secret_key'] as $field) {
            $write(array_replace($full, [$field => $testKey]));
            widget_reject(static fn() => submission_turnstile_private_config($path));
        }
    }
    widget_reject(static fn() => submission_turnstile_private_config(__FILE__));
    file_put_contents($path, '{"secret_key":"' . str_repeat('k', 32) . '",');
    widget_reject(static fn() => submission_turnstile_private_config($path));
    $write($full);
    $now = time();
    $calls = 0;
    $used = [];
    $verifier = new SubmissionTurnstile('production', $full['site_key'], $full['secret_key'], $full['hostname'], $full['action'],
        static function ($secret, $token) use (&$calls, &$used, $now): array {
            $calls++;
            if (isset($used[$token])) return ['success' => false, 'error-codes' => ['timeout-or-duplicate']];
            $used[$token] = true;
            return ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now)];
        });
    $verifier->requireForBaseUrl($full['base_url']);
    widget_reject(static fn() => $verifier->requireForBaseUrl('http://127.0.0.1:8085/author-submissions'));
    widget_reject(static fn() => $verifier->verify('', $now));
    widget_check($calls === 0, 'Missing token never invokes mocked provider');
    $verifier->verify('synthetic-token', $now);
    widget_check($calls === 1, 'Canonical context succeeds with mocked Siteverify only');
    widget_reject(static fn() => $verifier->verify('synthetic-token', $now));
    widget_check($calls === 2, 'Reused token rejected by Siteverify contract');
    foreach ([$config, $full] as $candidate) {
        $write($candidate);
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/../scripts/check-submissions-turnstile-config.php', $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('CLI fixture launch failed.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        widget_check($exit === ($candidate['site_key'] === '' ? 2 : 0), 'CLI incomplete/compatible status correct');
        widget_check(!str_contains($output, $full['secret_key']) && !str_contains($output, $full['site_key']), 'CLI does not display keys');
    }
    echo "PASS: {$checks} private Turnstile config checks; mocked Siteverify, no activation or network.\n";
} finally {
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
