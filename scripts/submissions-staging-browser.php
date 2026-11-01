<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/staging-browser.php';

try {
    $mode = $argv[1] ?? '';
    $path = $argv[2] ?? '';
    if (in_array($mode, ['--prepare-offline', '--prepare-live'], true)) {
        $directory = submission_browser_private_path($path);
        if (!is_dir($directory) || count(scandir($directory)) !== 2) {
            throw new RuntimeException('Use a new, empty, private directory outside Git.');
        }
        $live = $mode === '--prepare-live';
        $port = $live ? ($argv[5] ?? '8086') : ($argv[3] ?? '8085');
        if (!ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) throw new RuntimeException('Invalid loopback port.');
        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'identity.json';
        $keyPath = $directory . DIRECTORY_SEPARATOR . 'key.json';
        if ($live) {
            $manifestPath = submission_browser_private_path($argv[3] ?? '');
            $keyPath = submission_browser_private_path($argv[4] ?? '');
            submission_staging_migration_plan(submission_staging_manifest($manifestPath));
            $local = submission_staging_local_config();
            if (!submission_staging_local_check($local)['hostname_confirmed']) throw new RuntimeException('Staging hostname confirmation required.');
        } else {
            $manifest = [
                'identity' => ['environment' => 'staging', 'project_id' => 'cutaojpqvz',
                    'hostname' => 'cutaojpqvz.g2.sqlite.cloud', 'port' => 8860, 'database' => 'author-submissions-staging.sqlite',
                    'production_project_id' => 'offline-production', 'production_hostname' => 'production.example.com',
                    'production_database' => 'adaptations.sqlite', 'synthetic_only' => true],
                'project_name' => 'book-to-screen-staging', 'control_panel_identity_confirmed' => true,
                'credential_mode' => 'staging-admin', 'expected_user' => 'admin', 'admin_risk_approved' => true,
            ];
            if (file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR)) === false
                || file_put_contents($keyPath, json_encode(['B2S_SUBMISSIONS_STAGING_KEY' => bin2hex(random_bytes(32))], JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Private offline fixture creation failed.');
            }
        }
        $staffPath = $directory . DIRECTORY_SEPARATOR . 'fictional-reviewer.sqlite';
        $staff = new LocalSqliteDatabase($staffPath);
        $staff->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT UNIQUE,display_name TEXT,password_hash TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)');
        $password = bin2hex(random_bytes(16));
        $stmt = $staff->prepare("INSERT INTO users VALUES (900001,'staging-reviewer','Synthetic Staging Reviewer',:password,'admin',1,0)");
        $stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT)]);
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . 'reviewer-login.json',
            json_encode(['username' => 'staging-reviewer', 'password' => $password], JSON_THROW_ON_ERROR)) === false
            || file_put_contents($directory . DIRECTORY_SEPARATOR . '.gitignore', "*\n!.gitignore\n") === false) {
            throw new RuntimeException('Private reviewer configuration failed.');
        }
        if (!mkdir($directory . DIRECTORY_SEPARATOR . 'sessions', 0700)) throw new RuntimeException('Private session directory creation failed.');
        $config = ['mode' => $live ? 'live' : 'offline', 'base_url' => 'http://127.0.0.1:' . $port . '/author-submissions',
            'key_file' => $keyPath, 'staff_db' => $staffPath, 'identity_manifest' => $manifestPath];
        if (!$live) {
            $fixture = $directory . DIRECTORY_SEPARATOR . 'submissions-local.sqlite';
            $db = new LocalSqliteDatabase($fixture);
            $db->exec('CREATE TABLE _sqliteai_vector (tblname TEXT,colname TEXT,key TEXT,value ANY,PRIMARY KEY(tblname,colname,key))');
            submission_migrate($db);
            $config['fixture_db'] = $fixture;
            submission_staging_synthetic_run($manifest,
                'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key',
                submission_browser_key($config), '--test-three-synthetic',
                'cutaojpqvz/author-submissions-staging.sqlite/three-synthetic-v1', new StagingBrowserOfflineClient($fixture));
        } else {
            $crypto = new SubmissionCrypto(submission_browser_key($config));
            if ($crypto->decrypt($crypto->encrypt('private recovery probe')) !== 'private recovery probe') {
                throw new RuntimeException('Preserved key recovery failed.');
            }
        }
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . 'browser.json', json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException('Private browser configuration failed.');
        }
        echo ($live ? 'LIVE_CONFIG_PREPARED_NO_CONNECTION' : 'OFFLINE_FIXTURE_PREPARED') . "\n";
        exit;
    }
    $config = submission_browser_config($path);
    submission_browser_gate($config, $mode, getenv('B2S_STAGING_BROWSER_APPROVAL') ?: '');
    if (is_file($config['stop_file'])) throw new RuntimeException('Previous unexpected failure requires assessment; no automatic restart.');
    $lockDirectory = $config['mode'] === 'live' ? dirname($config['key_file']) : $config['directory'];
    $lock = fopen($lockDirectory . DIRECTORY_SEPARATOR . 'browser.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another browser instance owns this fixture.');
    submission_browser_session($config, static fn(): array => ['status' => 'BROWSER_PREFLIGHT_VERIFIED'], true);
    $crypto = new SubmissionCrypto(submission_browser_key($config));
    if (file_put_contents($config['directory'] . DIRECTORY_SEPARATOR . 'browser-established.json',
        json_encode(['proof' => $crypto->hash('browser-baseline', $config['mode'] . '|' . $config['base_url'])], JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Could not preserve verified browser baseline.');
    }
    $env = getenv();
    unset($env['B2S_STAGING_BROWSER_CONNECTION_STRING']);
    if ($config['mode'] === 'live') {
        $env['B2S_STAGING_BROWSER_CONNECTION_STRING'] = submission_browser_connection_string($config);
    }
    $env['B2S_STAGING_BROWSER_ACTIVE'] = '1';
    $env['B2S_STAGING_BROWSER_CONFIG'] = realpath($path);
    $env['B2S_STAGING_BROWSER_DIRECTORY'] = $config['directory'];
    $env['B2S_STAGING_BROWSER_SERVE_FLAG'] = $mode;
    $env['B2S_SUBMISSIONS_LOCAL'] = '1';
    $env['B2S_SUBMISSIONS_BASE_URL'] = $config['base_url'];
    $env['B2S_SUBMISSIONS_DB'] = $config['directory'] . DIRECTORY_SEPARATOR . 'submissions-local.sqlite';
    $env['B2S_SUBMISSIONS_MAIL'] = 'mock';
    $env['B2S_SUBMISSIONS_MOCK_MAILBOX'] = $config['mailbox'];
    unset($env['PHP_CLI_SERVER_WORKERS']);
    $env['B2S_TURNSTILE_ENV'] = 'development';
    $address = '127.0.0.1:' . parse_url($config['base_url'], PHP_URL_PORT);
    echo "BROWSER_PREFLIGHT_VERIFIED - {$config['mode']} only: {$config['base_url']}\nStop with Ctrl+C. Fictional reviewer credentials are private; no credentials printed.\n";
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', '-S', $address,
        '-t', dirname(__DIR__), __DIR__ . '/submissions-staging-browser-router.php'],
        [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $env);
    if (!is_resource($process)) throw new RuntimeException('Could not launch isolated browser server.');
    exit(proc_close($process));
} catch (Throwable) {
    fwrite(STDERR, "Staging browser preparation/start failed. Details suppressed; no automatic retry or cleanup. Inspect private configuration and fixture state.\n");
    exit(1);
}
