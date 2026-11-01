<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$package = realpath($argv[1] ?? '');
if ($package === false || basename($package) !== '__turnstile-validation') {
    throw new RuntimeException('Supply the isolated packaged folder.');
}
$checks = 0;
function package_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$root = dirname(__DIR__);
foreach (['challenge.js', '.htaccess'] as $name) {
    package_check(file_get_contents($package . DIRECTORY_SEPARATOR . $name)
        === file_get_contents($root . DIRECTORY_SEPARATOR . '__turnstile-validation' . DIRECTORY_SEPARATOR . $name), 'Page asset/default deny unchanged');
}
$original = file_get_contents($root . DIRECTORY_SEPARATOR . '__turnstile-validation' . DIRECTORY_SEPARATOR . 'index.php');
package_check(file_get_contents($package . DIRECTORY_SEPARATOR . 'index.php')
    === str_replace("__DIR__ . '/../includes/submissions/turnstile-challenge.php'", "__DIR__ . '/includes/submissions/turnstile-challenge.php'", $original),
    'Only page relative dependency path changed');
foreach (['submissions\\turnstile-challenge.php', 'submissions\\turnstile-config.php', 'submissions\\private-config.php',
    'submissions\\antibot.php', 'submissions\\security.php', 'database\\Database.php'] as $name) {
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $name);
    package_check(file_get_contents($package . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relative)
        === file_get_contents($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $relative), 'Bundled helper byte-identical to tested source');
}
package_check(trim(file_get_contents($package . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . '.htaccess')) === 'Require all denied',
    'Bundled helper tree has explicit recursive HTTP denial');
require_once $package . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'submissions' . DIRECTORY_SEPARATOR . 'turnstile-challenge.php';
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-package-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Private fixture preparation failed.');
$settingsPath = $directory . DIRECTORY_SEPARATOR . 'settings.json';
$keysPath = $directory . DIRECTORY_SEPARATOR . 'widget.json';
$sessions = $directory . DIRECTORY_SEPARATOR . 'sessions';
try {
    mkdir($sessions, 0700);
    $widget = ['base_url' => 'https://booktoscreen.org/author-submissions/', 'hostname' => 'booktoscreen.org',
        'action' => 'author_submission', 'widget_mode' => 'managed', 'pre_clearance' => false,
        'site_key' => str_repeat('s', 22), 'secret_key' => str_repeat('k', 32)];
    file_put_contents($keysPath, json_encode($widget, JSON_THROW_ON_ERROR));
    if (DIRECTORY_SEPARATOR !== '\\') chmod($keysPath, 0600);
    $settings = ['enabled' => true, 'user' => 'fixture-tester', 'deadline' => time() + 1800,
        'widget_config' => $keysPath, 'sessions' => $sessions, 'budget' => $directory . DIRECTORY_SEPARATOR . 'budget.txt'];
    foreach (['default', 'missing', 'disabled', 'expired', 'wrong-user', 'allowed', 'csrf'] as $case) {
        $data = $settings;
        if ($case === 'disabled') $data['enabled'] = false;
        if ($case === 'expired') $data['deadline'] = time() - 1;
        file_put_contents($settingsPath, json_encode($data, JSON_THROW_ON_ERROR));
        if (DIRECTORY_SEPARATOR !== '\\') chmod($settingsPath, 0600);
        $server = ['HTTPS' => 'on', 'HTTP_HOST' => 'booktoscreen.org', 'SCRIPT_NAME' => '/__turnstile-validation/index.php',
            'REQUEST_URI' => '/__turnstile-validation/index.php', 'REMOTE_USER' => $case === 'wrong-user' ? 'other' : 'fixture-tester',
            'REQUEST_METHOD' => $case === 'csrf' ? 'POST' : 'GET', 'DOCUMENT_ROOT' => dirname($package)];
        $pointer = $case === 'default' ? 'B2S_TURNSTILE_VALIDATION_SETTINGS_FILE'
            : 'B2S_TURNSTILE_VALIDATION_SETTINGS_FILE=' . ($case === 'missing' ? $settingsPath . '.missing' : $settingsPath);
        $code = '$_SERVER=' . var_export($server, true) . '; $_GET=[]; putenv(' . var_export($pointer, true) . ');'
            . 'register_shutdown_function(static function(){echo "|STATUS:".http_response_code();'
            . 'echo "|NO_SERVICES:".(int)(!class_exists("LocalSqliteDatabase",false)&&!class_exists("SmtpSubmissionMailer",false));});'
            . 'require ' . var_export($package . DIRECTORY_SEPARATOR . 'index.php', true) . ';';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Package subprocess failed.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        package_check(proc_close($process) === 0, 'Packaged page executes in isolation');
        package_check(!str_contains($output, $widget['secret_key']) && str_contains($output, '|NO_SERVICES:1'), 'No secret output or DB/mail runtime');
        package_check($case === 'allowed' ? str_contains($output, 'data-csrf=') && str_contains($output, $widget['site_key'])
            : str_contains($output, '|STATUS:' . ($case === 'missing' ? '503' : ($case === 'csrf' ? '403' : '404'))),
            'Packaged missing/default/disabled/expiry/auth/CSRF/render behavior');
    }
    $_SERVER['DOCUMENT_ROOT'] = dirname($package);
    try {
        submission_private_config_path($package . DIRECTORY_SEPARATOR . 'index.php', false);
        throw new LogicException('Webroot file accepted');
    } catch (RuntimeException $e) { package_check(true, 'Packaged private-path guard still rejects public files'); }
    $now = time();
    $used = [];
    $verifier = new SubmissionTurnstile('production', $widget['site_key'], $widget['secret_key'], 'booktoscreen.org', 'author_submission',
        static function ($secret, $token) use (&$used, $now): array {
            if (isset($used[$token])) return ['success' => false, 'error-codes' => ['timeout-or-duplicate']];
            $used[$token] = true;
            return ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission', 'challenge_ts' => gmdate('c', $now)];
        });
    package_check(submission_challenge_verify($verifier, 'fixture-token', $now, static fn() => null)['status'] === 200, 'Bundled verifier accepts mocked fresh token');
    package_check(submission_challenge_verify($verifier, 'fixture-token', $now, static fn() => null)['status'] === 422, 'Bundled verifier rejects mocked reuse');
    echo "PASS: {$checks} isolated package checks; synthetic configuration and mocked Siteverify only.\n";
} finally {
    foreach ([$settingsPath, $keysPath] as $path) if (is_file($path)) unlink($path);
    foreach (glob($sessions . DIRECTORY_SEPARATOR . 'sess_*') as $path) if (is_file($path)) unlink($path);
    rmdir($sessions);
    rmdir($directory);
}
