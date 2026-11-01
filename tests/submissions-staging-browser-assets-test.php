<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/staging-browser.php';
$config = submission_browser_config($argv[1] ?? '');
if ($config['mode'] !== 'offline') throw new RuntimeException('Only offline configuration is permitted.');
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($listener === false) throw new RuntimeException('Could not reserve loopback test port.');
$address = stream_socket_get_name($listener, false);
fclose($listener);
$process = null;
$checks = 0;
try {
    $env = getenv();
    unset($env['PHP_CLI_SERVER_WORKERS'], $env['B2S_STAGING_BROWSER_CONNECTION_STRING']);
    $env['B2S_STAGING_BROWSER_ACTIVE'] = '1';
    $env['B2S_STAGING_BROWSER_CONFIG'] = realpath($argv[1]);
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-S', $address, '-t', dirname(__DIR__),
        __DIR__ . '/../scripts/submissions-staging-browser-router.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
    if (!is_resource($process)) throw new RuntimeException('Could not start static-only regression server.');
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($socket !== false) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Static regression server not responsive.');
    $host = parse_url($config['base_url'], PHP_URL_HOST) . ':' . parse_url($config['base_url'], PHP_URL_PORT);
    $request = static function (string $path, string $host) use ($address): array {
        $ch = curl_init('http://' . $address . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Host: ' . $host], CURLOPT_PATH_AS_IS => true]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('Static HTTP request failed.');
        return [$status, $type, $body];
    };
    $html = file_get_contents(__DIR__ . '/../admin/submissions.php');
    preg_match_all('~rel="stylesheet" href="\.\.([^"]+)"~', $html, $matches);
    if (count($matches[1]) !== 4) throw new RuntimeException('Unexpected reviewer stylesheet inventory.');
    foreach ($matches[1] as $path) {
        [$status, $type, $body] = $request($path, $host);
        if ($status !== 200 || !str_starts_with($type, 'text/css')
            || $body !== file_get_contents(dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            throw new RuntimeException('Reviewer stylesheet not served exactly as CSS.');
        }
        $checks++;
    }
    foreach (['/assets/css/admin.css', '/assets/css/', '/assets/css/../admin.css',
        '/includes/db.php', '/scripts/submissions-staging.local.php', '/admin/index.php'] as $path) {
        if ($request($path, $host)[0] !== 404) throw new RuntimeException('Non-allowlisted route exposed.');
        $checks++;
    }
    if ($request('/assets/css/submission-review.css', 'production.example.com')[0] !== 403) {
        throw new RuntimeException('Asset host boundary weakened.');
    }
    $checks++;
    echo "PASS: {$checks} static asset routing checks; no database connection or writes.\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($process);
    }
}
