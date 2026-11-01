<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/staging-browser.php';
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-router-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Could not create isolated routing fixture.');
$process = null;
$checks = 0;
function routing_check(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
try {
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($listener === false) throw new RuntimeException('Could not reserve test port.');
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $library = var_export(realpath(__DIR__ . '/../includes/submissions/staging-browser.php'), true);
    $router = str_replace('__LIBRARY__', $library, <<<'PHP'
<?php
require __LIBRARY__;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/legacy') {
    require __DIR__ . '/cli-only.php';
    exit;
}
if ($path === '/direct-config') {
    require __DIR__ . '/cli-only.php';
    exit;
}
$config = ['mode' => 'live'];
putenv('B2S_STAGING_BROWSER_ACTIVE=1');
putenv('B2S_STAGING_BROWSER_SERVE_FLAG=--serve-live-staging');
putenv('B2S_STAGING_BROWSER_APPROVAL=' . SUBMISSION_BROWSER_APPROVAL);
putenv('B2S_STAGING_BROWSER_WRITERS_STOPPED=1');
if ($path !== '/no-boundary') define('B2S_STAGING_BROWSER', true);
if ($path === '/missing-url') putenv('B2S_STAGING_BROWSER_CONNECTION_STRING');
if ($path === '/missing-approval') putenv('B2S_STAGING_BROWSER_APPROVAL');
if ($path === '/inactive') putenv('B2S_STAGING_BROWSER_ACTIVE=0');
if ($path === '/offline') $config['mode'] = 'offline';
if ($path === '/wrong-flag') putenv('B2S_STAGING_BROWSER_SERVE_FLAG=--serve-offline');
if ($path === '/writers-active') putenv('B2S_STAGING_BROWSER_WRITERS_STOPPED');
if ($path === '/wrong-target') putenv('B2S_STAGING_BROWSER_CONNECTION_STRING=sqlitecloud://production.example.com:8860/adaptations.sqlite?apikey=offline-fictional-key');
try {
    $url = submission_browser_connection_string($config);
    submission_staging_diagnostic_auth(['target' => ['hostname' => 'cutaojpqvz.g2.sqlite.cloud',
        'port' => 8860, 'database' => 'author-submissions-staging.sqlite'],
        'credential_mode' => 'staging-admin', 'expected_user' => 'admin'], $url);
    echo 'PRIVATE_CHILD_CONFIGURATION_VALIDATED';
} catch (Throwable) {
    http_response_code(503);
    echo 'REJECTED_NO_FALLBACK';
}
PHP
    );
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'router.php', $router);
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'cli-only.php',
        "<?php\nif (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\nreturn ['connection_string'=>'fictional'];\n");
    $env = getenv();
    unset($env['PHP_CLI_SERVER_WORKERS']);
    $env['B2S_STAGING_BROWSER_CONNECTION_STRING'] =
        'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-S', $address, '-t', $directory,
        $directory . DIRECTORY_SEPARATOR . 'router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $directory . DIRECTORY_SEPARATOR . 'server.log', 'a'],
            2 => ['file', $directory . DIRECTORY_SEPARATOR . 'server.log', 'a']], $pipes, $directory, $env);
    if (!is_resource($process)) throw new RuntimeException('Could not launch mock routing server.');
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($socket !== false) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    routing_check($ready, 'Mock server is responsive on loopback');
    $request = static function (string $path) use ($address): array {
        $ch = curl_init('http://' . $address . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($body === false) throw new RuntimeException('Mock routing request failed.');
        return [$code, $body];
    };
    routing_check($request('/legacy') === [404, ''], 'Original CLI-only include reproduces browser 404');
    routing_check($request('/direct-config') === [404, ''], 'Direct HTTP access to private configuration stays blocked');
    routing_check($request('/approved') === [200, 'PRIVATE_CHILD_CONFIGURATION_VALIDATED'], 'Approved child uses inherited private configuration');
    foreach (['missing-url', 'missing-approval', 'inactive', 'offline', 'wrong-flag', 'writers-active', 'no-boundary', 'wrong-target'] as $failure) {
        routing_check($request('/' . $failure) === [503, 'REJECTED_NO_FALLBACK'], 'Invalid child boundary rejected: ' . $failure);
    }
    echo "PASS: {$checks} browser routing checks; CLI guard 404 reproduced and corrected child handoff validated; no Cloud connection.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    foreach (['router.php', 'cli-only.php', 'server.log'] as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) unlink($path);
    }
    rmdir($directory);
}
