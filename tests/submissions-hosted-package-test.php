<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$package = realpath($argv[1] ?? '');
if ($package === false || basename($package) !== '__author-staging') throw new RuntimeException('Select isolated hosted package.');
$checks = 0;
function hosted_package_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($package) + 1));
    hosted_package_check(!preg_match('~(^|/)(tests|scripts|__turnstile-validation|\.git)/|\.(json|jsonl|sqlite|db|log|env)$~', $relative)
        && !str_ends_with($relative, '/turnstile-challenge.php') && !str_ends_with($relative, '/smtp-test.php'),
        'No old harness, CLI tool, private state or generated artifact in package');
    if ($file->getExtension() === 'php') {
        $source = file_get_contents($file->getPathname());
        preg_match_all("~require(?:_once)?\s+__DIR__\s*\.\s*'([^']+)'~", $source, $matches);
        foreach ($matches[1] as $dependency) {
            hosted_package_check(is_file(dirname($file->getPathname()) . $dependency), 'All direct relative PHP dependencies bundled');
        }
    }
}
require_once $package . '/app/includes/submissions/hosted-staging.php';
hosted_package_check(hash_equals(hash_file('sha256', $root . '/includes/submissions/migrations.php'),
    hash_file('sha256', $package . '/app/includes/submissions/migrations.php')),
    'Packaged migration definitions preserve exact source bytes');
hosted_package_check(array_keys(submission_migrations()) === [1, 2], 'Only approved migration versions bundled');
foreach (submission_migrations() as $version => $statements) {
    hosted_package_check(hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', implode("\n", $statements))),
        'Packaged migration statements match immutable approval checksum');
}
hosted_package_check(class_exists('SQLiteCloud\\SQLiteCloudClient') && class_exists('PHPMailer\\PHPMailer\\PHPMailer'),
    'Composer and both existing provider implementations load offline');
foreach (['gateway.php', '.htaccess', 'app/.htaccess', 'app/mailbox.php'] as $relative) {
    hosted_package_check(file_get_contents($package . '/' . $relative) === file_get_contents($root . '/__author-staging/' . $relative),
        'Hosted entry/access rules retain exact source parity');
}
foreach (['/author-submissions/', '/app/includes/submissions/hosted-staging.php', '/admin/login.php', '/mailbox.php'] as $path) {
    $code = '$_SERVER=' . var_export(['REQUEST_URI' => '/__author-staging' . $path, 'HTTPS' => 'on',
        'HTTP_HOST' => 'booktoscreen.org', 'REMOTE_USER' => 'fictional-tester'], true) . ';'
        . 'putenv("B2S_HOSTED_STAGING_SETTINGS_FILE");'
        . 'register_shutdown_function(function(){echo "|STATUS:".http_response_code();});'
        . 'require ' . var_export($package . '/gateway.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Packaged gate fixture unavailable.');
    $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    hosted_package_check(proc_close($process) === 0 && str_contains($output, '|STATUS:404'), 'Disabled and non-allowlisted packaged requests deny before Cloud');
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-hosted-asset-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700) || !mkdir($directory . DIRECTORY_SEPARATOR . 'sessions', 0700)) {
    throw new RuntimeException('Private synthetic asset fixture unavailable.');
}
$settingsPath = $directory . DIRECTORY_SEPARATOR . 'settings.json';
$data = ['enabled' => true, 'deadline' => time() + 1800, 'user' => 'fictional-tester',
    'writers_stopped' => true, 'baseline_base_url' => 'http://127.0.0.1:8086/author-submissions'];
$fixtureFiles = [
    'key_file' => ['B2S_SUBMISSIONS_STAGING_KEY' => str_repeat('a', 64)],
    'identity_manifest' => ['identity' => ['environment' => 'staging', 'project_id' => 'cutaojpqvz',
        'hostname' => 'cutaojpqvz.g2.sqlite.cloud', 'port' => 8860, 'database' => 'author-submissions-staging.sqlite',
        'production_project_id' => 'fictional-production', 'production_hostname' => 'production.example.org',
        'production_database' => 'adaptations.sqlite', 'synthetic_only' => true],
        'project_name' => 'book-to-screen-staging', 'control_panel_identity_confirmed' => true,
        'credential_mode' => 'staging-admin', 'expected_user' => 'admin', 'admin_risk_approved' => true],
    'connection_file' => ['connection_string' => 'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key'],
    'widget_config' => ['base_url' => 'https://booktoscreen.org/author-submissions/', 'hostname' => 'booktoscreen.org',
        'action' => 'author_submission', 'widget_mode' => 'managed', 'pre_clearance' => false,
        'site_key' => str_repeat('s', 22), 'secret_key' => str_repeat('k', 32)],
    'staff_db' => [],
];
try {
    foreach ($fixtureFiles as $field => $contents) {
        $data[$field] = $directory . DIRECTORY_SEPARATOR . $field . '.json';
        file_put_contents($data[$field], json_encode($contents, JSON_THROW_ON_ERROR));
        if (DIRECTORY_SEPARATOR !== '\\') chmod($data[$field], 0600);
    }
    file_put_contents($settingsPath, json_encode($data, JSON_THROW_ON_ERROR));
    if (DIRECTORY_SEPARATOR !== '\\') chmod($settingsPath, 0600);
    $code = '$_SERVER=' . var_export(['REQUEST_URI' => '/__author-staging/assets/css/site.css', 'HTTPS' => 'on',
        'HTTP_HOST' => 'booktoscreen.org', 'REMOTE_USER' => 'fictional-tester', 'DOCUMENT_ROOT' => dirname($package)], true) . ';'
        . 'putenv(' . var_export('B2S_HOSTED_STAGING_SETTINGS_FILE=' . $settingsPath, true) . ');'
        . 'require ' . var_export($package . '/gateway.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Packaged asset fixture unavailable.');
    $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    hosted_package_check(proc_close($process) === 0 && $output === file_get_contents($package . '/app/assets/css/site.css'),
        'Enabled authenticated HTTPS settings serve only expected asset without Cloud/SMTP connection or secret output');
    $code = 'require ' . var_export($package . '/app/includes/submissions/hosted-staging.php', true) . ';'
        . '$_SERVER=' . var_export(['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '203.0.113.9',
            'HTTPS' => 'on', 'HTTP_HOST' => 'booktoscreen.org', 'DOCUMENT_ROOT' => dirname($package)], true) . ';'
        . '$submissionBrowserConfig=submission_hosted_config(submission_hosted_settings(' . var_export($settingsPath, true) . '));'
        . 'define("B2S_HOSTED_STAGING",true);define("B2S_STAGING_BROWSER",true);putenv("B2S_SUBMISSIONS_LOCAL=1");'
        . 'require ' . var_export($package . '/app/author-submissions/index.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Packaged form render fixture unavailable.');
    $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    hosted_package_check(proc_close($process) === 0 && str_contains($output, 'data-csrf=')
        && str_contains($output, 'existing Managed widget') && !str_contains($output, 'official Cloudflare development credentials')
        && !str_contains($output, str_repeat('k', 32)),
        'Hosted form renders with CSRF, correct widget notice and no secret; no provider/Cloud called');
} finally {
    foreach (array_keys($fixtureFiles) as $field) {
        if (isset($data[$field]) && is_file($data[$field])) unlink($data[$field]);
    }
    if (is_file($settingsPath)) unlink($settingsPath);
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'sess_*') as $path) {
        if (is_file($path)) unlink($path);
    }
    rmdir($directory . DIRECTORY_SEPARATOR . 'sessions');
    rmdir($directory);
}
echo "PASS: $checks hosted package/dependency checks; source-only, no live provider or mail.\n";
