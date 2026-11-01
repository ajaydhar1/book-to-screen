<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/hosted-check.php';
$checks = 0;
function prerequisite_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-prerequisite-' . bin2hex(random_bytes(8));
mkdir($directory, 0700); mkdir($directory . DIRECTORY_SEPARATOR . 'sessions', 0700);
$files = [];
try {
    $manifest = ['identity' => ['environment' => 'staging', 'project_id' => 'cutaojpqvz',
        'hostname' => 'cutaojpqvz.g2.sqlite.cloud', 'port' => 8860, 'database' => 'author-submissions-staging.sqlite',
        'production_project_id' => 'fictional-production', 'production_hostname' => 'production.example.org',
        'production_database' => 'adaptations.sqlite', 'synthetic_only' => true],
        'project_name' => 'book-to-screen-staging', 'control_panel_identity_confirmed' => true,
        'credential_mode' => 'staging-admin', 'expected_user' => 'admin', 'admin_risk_approved' => true];
    $key = str_repeat('a', 64);
    $crypto = new SubmissionCrypto($key);
    $settings = ['enabled' => false, 'deadline' => 0, 'writers_stopped' => false, 'user' => 'synthetic-tester',
        'baseline_base_url' => 'http://127.0.0.1:8086/author-submissions'];
    foreach ([
        'key_file' => ['B2S_SUBMISSIONS_STAGING_KEY' => $key],
        'identity_manifest' => $manifest,
        'connection_file' => ['connection_string' => 'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key'],
        'widget_config' => ['base_url' => 'https://booktoscreen.org/author-submissions/', 'hostname' => 'booktoscreen.org',
            'action' => 'author_submission', 'widget_mode' => 'managed', 'pre_clearance' => false,
            'site_key' => str_repeat('s', 22), 'secret_key' => str_repeat('k', 32)],
    ] as $field => $data) {
        $settings[$field] = $directory . DIRECTORY_SEPARATOR . $field . '.json';
        file_put_contents($settings[$field], json_encode($data, JSON_THROW_ON_ERROR));
        chmod($settings[$field], 0600); $files[] = $settings[$field];
    }
    $settings['staff_db'] = $directory . DIRECTORY_SEPARATOR . 'staff.sqlite'; $files[] = $settings['staff_db'];
    $staff = new SQLite3($settings['staff_db']);
    $staff->exec('CREATE TABLE users(id INTEGER,username TEXT,display_name TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)');
    $staff->exec("INSERT INTO users VALUES(900001,'staging-reviewer','Synthetic Staging Reviewer','admin',1,0)");
    $staff->close(); chmod($settings['staff_db'], 0600);
    $baseline = $directory . DIRECTORY_SEPARATOR . 'browser-established.json'; $files[] = $baseline;
    file_put_contents($baseline, json_encode(['proof' => $crypto->hash('browser-baseline', 'live|' . $settings['baseline_base_url'])]));
    chmod($baseline, 0600);
    $path = $directory . DIRECTORY_SEPARATOR . 'settings.json'; $files[] = $path;
    file_put_contents($path, json_encode($settings)); chmod($path, 0600);
    $server = ['HTTPS' => 'on', 'HTTP_HOST' => 'booktoscreen.org', 'REMOTE_USER' => 'synthetic-tester'];
    $result = submission_hosted_prerequisites($server, $path);
    prerequisite_check(!in_array(false, $result, true), 'Disabled configuration full local prerequisites pass without provider calls');
    $details = submission_hosted_file_diagnostics($server, $path);
    prerequisite_check($details['Loader / Connection format and target'] === 'PASS'
        && $details['sessions / PHP writable'] === 'PASS', 'Independent file and validation-stage checks pass');
    prerequisite_check($details['Manifest / Complete migration plan'] === 'PASS',
        'Manifest subchecks accept the preserved schema and approved source');
    $originalManifest = file_get_contents($settings['identity_manifest']);
    foreach ([
        ["\xEF\xBB\xBF" . $originalManifest, 'UTF-8 without BOM'],
        ['{"identity":', 'JSON syntax and depth'],
        [json_encode(['identity' => $manifest['identity']]), 'Top-level field structure'],
        [json_encode(array_replace_recursive($manifest, ['identity' => ['port' => '8860']])), 'Integer pinned port'],
        [json_encode(array_replace_recursive($manifest, ['identity' => ['project_id' => 'fictional-wrong-project']])), 'Pinned project'],
        [json_encode(array_replace($manifest, ['control_panel_identity_confirmed' => false])), 'Diagnostic declarations'],
        [json_encode(array_replace($manifest, ['expected_user' => 'fictional-private-user'])), 'Approved staging scope'],
    ] as [$contents, $label]) {
        file_put_contents($settings['identity_manifest'], $contents);
        $diagnostic = submission_hosted_manifest_diagnostics($settings['identity_manifest']);
        prerequisite_check($diagnostic['Manifest / ' . $label] === 'FAIL', 'Manifest cause identified: ' . $label);
        prerequisite_check(!str_contains(json_encode($diagnostic), $directory)
            && !str_contains(json_encode($diagnostic), 'fictional-private-user')
            && !str_contains(json_encode($diagnostic), 'fictional-wrong-project'),
            'Manifest subchecks expose only fixed labels');
    }
    file_put_contents($settings['identity_manifest'], $originalManifest);
    foreach (submission_migrations() as $version => $statements) {
        $lfStatements = array_map(static fn(string $sql): string => str_replace("\r\n", "\n", $sql), $statements);
        $restored = array_map(static fn(string $sql): string => str_replace("\n", "\r\n", $sql), $lfStatements);
        prerequisite_check(!hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', implode("\n", $lfStatements)))
            && ($version !== 1 || hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[1], hash('sha256', implode("\n", $restored)))),
            'Source LF conversion and restored CRLF are distinguishable without executing migrations');
    }
    $originalConnection = file_get_contents($settings['connection_file']);
    file_put_contents($settings['connection_file'], '{"connection_string":false}');
    $details = submission_hosted_file_diagnostics($server, $path);
    prerequisite_check($details['connection_file / expected object exists'] === 'PASS'
        && $details['connection_file / PHP readable'] === 'PASS'
        && $details['Loader / Connection format and target'] === 'FAIL'
        && $details['Loader / Key JSON and key format'] === 'NOT CHECKED'
        && $details['sessions / PHP writable'] === 'PASS', 'Format failure distinguished from access and downstream unexecuted checks');
    file_put_contents($settings['connection_file'], $originalConnection);
    $originalKeyPath = $settings['key_file'];
    $settings['key_file'] .= '.missing'; file_put_contents($path, json_encode($settings));
    $details = submission_hosted_file_diagnostics($server, $path);
    prerequisite_check($details['key_file / expected object exists'] === 'FAIL'
        && $details['Loader / Private path: key_file'] === 'FAIL'
        && $details['staff_db / PHP readable'] === 'PASS', 'Missing-file mismatch does not mark other readable files as failed');
    $settings['key_file'] = $originalKeyPath; file_put_contents($path, json_encode($settings));
    prerequisite_check(!str_contains(json_encode($details), $directory)
        && !str_contains(json_encode($details), $key)
        && !str_contains(json_encode($details), 'offline-fictional-key'), 'File diagnostics expose no paths, content or secrets');
    prerequisite_check(!str_contains(json_encode($result), $key) && !str_contains(json_encode($result), 'offline-fictional-key'),
        'Output contains only labels and booleans');
    foreach ([['HTTPS' => 'off'], ['REMOTE_USER' => 'wrong'], ['HTTP_HOST' => 'evil.example.org']] as $override) {
        $result = submission_hosted_prerequisites(array_replace($server, $override), $path);
        prerequisite_check(!$result['Private configuration compatibility'], 'Untrusted request cannot inspect credentials');
    }
    $settings['enabled'] = true; file_put_contents($path, json_encode($settings));
    prerequisite_check(!submission_hosted_prerequisites($server, $path)['Disabled private settings'], 'Active portal settings rejected by prerequisites');
    $settings['enabled'] = false; file_put_contents($path, json_encode($settings));
    $config = submission_hosted_config(submission_hosted_settings($path));
    $mock = new class extends \SQLiteCloud\SQLiteCloudClient {
        public array $commands = [];
        public bool $closed = false;
        public bool $failConnection = false;
        public int $attempts = 0;
        public function connect($hostname = 'localhost', $port = 8860) {
            $this->attempts++;
            if ($this->failConnection) throw new RuntimeException('Synthetic transport failure.');
            if ($hostname !== 'cutaojpqvz.g2.sqlite.cloud' || $port !== 8860 || $this->create || $this->insecure || $this->no_verify_certificate) {
                throw new RuntimeException('Unsafe mock connection options.');
            }
            return true;
        }
        public function disconnect() { $this->closed = true; return true; }
        public function execute($command) {
            $this->commands[] = $command;
            if ($command === 'GET DATABASE NAME') return 'author-submissions-staging.sqlite';
            if ($command === 'GET USER') return 'admin';
            $row = match ($command) {
                'PRAGMA database_list' => ['seq' => 0, 'name' => 'main', 'file' => 'author-submissions-staging.sqlite'],
                'SELECT sqlite_version() AS sqlite_version' => ['sqlite_version' => '3.45.0'],
                'PRAGMA foreign_keys' => ['foreign_keys' => 1],
                'PRAGMA query_only' => ['query_only' => 0],
                'PRAGMA journal_mode' => ['journal_mode' => 'wal'],
                default => throw new RuntimeException('Unapproved SQL.'),
            };
            $result = new \SQLiteCloud\SQLiteCloudRowset();
            $result->ncols = count($row); $result->nrows = 1; $result->colname = array_keys($row); $result->data = array_values($row);
            return $result;
        }
    };
    prerequisite_check(!in_array(false, submission_hosted_connectivity_readonly($config, $mock), true), 'Mocked connectivity validates exact target and settings');
    prerequisite_check(count($mock->commands) === 7 && $mock->closed, 'Exactly seven read-only commands, one mocked connection, disconnect, no writes');
    $approvalPath = $directory . DIRECTORY_SEPARATOR . 'connectivity-approval.json'; $files[] = $approvalPath;
    $markerPath = $directory . DIRECTORY_SEPARATOR . 'connectivity-attempted.flag'; $files[] = $markerPath;
    $now = time();
    file_put_contents($approvalPath, json_encode(['approved' => true, 'deadline' => $now + 300]));
    chmod($approvalPath, 0600);
    $postServer = $server + ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'https://booktoscreen.org',
        'HTTP_SEC_FETCH_SITE' => 'same-origin'];
    $post = ['confirmation' => 'APPROVE ONE STAGING READ ONLY CONNECTION'];
    foreach ([['REQUEST_METHOD' => 'GET'], ['HTTP_ORIGIN' => 'https://evil.example.org'],
        ['REMOTE_USER' => 'wrong'], ['HTTPS' => 'off'], ['HTTP_SEC_FETCH_SITE' => 'cross-site']] as $override) {
        $rejected = false;
        try { submission_hosted_browser_connectivity_attempt(array_replace($postServer, $override), $post, $path, $now, $mock); }
        catch (Throwable) { $rejected = true; }
        prerequisite_check($rejected && count($mock->commands) === 7 && !file_exists($markerPath),
            'Untrusted browser attempt rejected before reservation or connection');
    }
    foreach ([['approved' => false, 'deadline' => $now + 300], ['approved' => true, 'deadline' => $now],
        ['approved' => true, 'deadline' => $now + 901], ['approved' => true, 'deadline' => (string) ($now + 300)]] as $approval) {
        file_put_contents($approvalPath, json_encode($approval));
        $rejected = false;
        try { submission_hosted_browser_connectivity_attempt($postServer, $post, $path, $now, $mock); }
        catch (Throwable) { $rejected = true; }
        prerequisite_check($rejected && !file_exists($markerPath) && count($mock->commands) === 7,
            'Disabled expired oversized-window or untyped approval cannot connect');
    }
    file_put_contents($approvalPath, json_encode(['approved' => true, 'deadline' => $now + 300]));
    $mock->commands = []; $mock->closed = false;
    $browserResult = submission_hosted_browser_connectivity_attempt($postServer, $post, $path, $now, $mock);
    prerequisite_check(!in_array(false, $browserResult, true) && file_exists($markerPath)
        && count($mock->commands) === 7 && $mock->closed, 'Approved browser attempt reserves marker and executes seven mocked reads');
    $rejected = false;
    try { submission_hosted_browser_connectivity_attempt($postServer, $post, $path, $now, $mock); }
    catch (Throwable) { $rejected = true; }
    prerequisite_check($rejected && count($mock->commands) === 7, 'Browser replay cannot reconnect');
    unlink($markerPath);
    $mock->failConnection = true;
    $beforeAttempts = $mock->attempts;
    $rejected = false;
    try { submission_hosted_browser_connectivity_attempt($postServer, $post, $path, $now, $mock); }
    catch (Throwable) { $rejected = true; }
    prerequisite_check($rejected && file_exists($markerPath) && $mock->attempts === $beforeAttempts + 1,
        'Transport failure consumes exactly one attempt');
    try { submission_hosted_browser_connectivity_attempt($postServer, $post, $path, $now, $mock); }
    catch (Throwable) {}
    prerequisite_check($mock->attempts === $beforeAttempts + 1, 'Failed connection cannot be retried');
    unlink($markerPath);
    $rejected = false;
    try { submission_hosted_browser_connectivity_attempt($postServer, ['confirmation' => 'wrong'], $path, $now, $mock); }
    catch (Throwable) { $rejected = true; }
    prerequisite_check($rejected && !file_exists($markerPath), 'Explicit confirmation required');
    $endpointDirectory = $directory . DIRECTORY_SEPARATOR . 'endpoint';
    $helperDirectory = $endpointDirectory . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'submissions';
    mkdir($helperDirectory, 0700, true);
    $endpointPath = $endpointDirectory . DIRECTORY_SEPARATOR . 'connectivity-check.php'; $files[] = $endpointPath;
    copy(__DIR__ . '/../__author-staging/connectivity-check.php', $endpointPath);
    $helperPath = $helperDirectory . DIRECTORY_SEPARATOR . 'hosted-check.php'; $files[] = $helperPath;
    file_put_contents($helperPath, '<?php require ' . var_export(realpath(__DIR__ . '/../includes/submissions/hosted-check.php'), true) . ';');
    foreach (['GET', 'POST'] as $method) {
        $requestServer = $postServer + ['REQUEST_URI' => '/__author-staging/connectivity-check.php'];
        $requestServer['REQUEST_METHOD'] = $method;
        $code = '$_GET=[];$_POST=[];$_SERVER=' . var_export($requestServer, true) . ';'
            . 'putenv(' . var_export('B2S_HOSTED_STAGING_SETTINGS_FILE=' . $path, true) . ');'
            . 'require ' . var_export($endpointPath, true) . ';';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        prerequisite_check(proc_close($process) === 0 && !file_exists($markerPath)
            && ($method === 'GET' ? str_contains($output, '<form method="post"') : str_contains($output, 'check: FAIL'))
            && !str_contains($output, $directory) && !str_contains($output, 'offline-fictional-key'),
            'Actual endpoint GET and invalid POST are sanitized and never connect');
    }
    $process = proc_open([PHP_BINARY, __DIR__ . '/../scripts/check-hosted-staging-connectivity.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    prerequisite_check(proc_close($process) === 1 && !file_exists($directory . DIRECTORY_SEPARATOR . 'connectivity-attempted.flag'),
        'CLI without explicit approval cannot connect or reserve a live attempt');
    echo "PASS: $checks hosted prerequisite/connectivity checks; private synthetic files and mock Cloud only.\n";
} finally {
    foreach ($files as $file) if (is_file($file)) unlink($file);
    if (isset($helperDirectory)) {
        rmdir($helperDirectory); rmdir(dirname($helperDirectory)); rmdir(dirname(dirname($helperDirectory))); rmdir($endpointDirectory);
    }
    rmdir($directory . DIRECTORY_SEPARATOR . 'sessions'); rmdir($directory);
}
