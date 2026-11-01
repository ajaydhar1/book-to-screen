<?php

declare(strict_types=1);

require_once __DIR__ . '/staging-synthetic.php';
require_once __DIR__ . '/staging-local-config.php';
require_once __DIR__ . '/staging-browser-preflight.php';

const SUBMISSION_BROWSER_APPROVAL = 'cutaojpqvz/author-submissions-staging.sqlite/browser-synthetic-v1';

function submission_browser_private_path(string $path): string
{
    $resolved = realpath($path);
    $root = realpath(__DIR__ . '/../..');
    if ($resolved === false || is_link($path)
        || str_starts_with(strtolower($resolved) . DIRECTORY_SEPARATOR, strtolower($root) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Browser fixtures must exist outside the repository.');
    }
    return $resolved;
}

function submission_browser_config(string $path): array
{
    $path = submission_browser_private_path($path);
    $config = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($config) || !in_array($config['mode'] ?? '', ['offline', 'live'], true)
        || !is_string($config['base_url'] ?? null)
        || !preg_match('~^http://127\.0\.0\.1:([0-9]{4,5})/author-submissions$~D', $config['base_url'], $port)
        || (int) $port[1] < 1024 || (int) $port[1] > 65535) {
        throw new RuntimeException('Invalid isolated browser configuration.');
    }
    foreach (['key_file', 'staff_db', 'identity_manifest'] as $field) {
        $config[$field] = submission_browser_private_path($config[$field] ?? '');
    }
    $config['directory'] = dirname($path);
    $config['mailbox'] = $config['directory'] . DIRECTORY_SEPARATOR . 'submissions-mailbox.jsonl';
    $config['stop_file'] = $config['directory'] . DIRECTORY_SEPARATOR . 'browser-stopped.flag';
    if ($config['mode'] === 'offline') {
        $config['fixture_db'] = submission_browser_private_path($config['fixture_db'] ?? '');
    }
    return $config;
}

function submission_browser_key(array $config): string
{
    $data = json_decode(file_get_contents($config['key_file']), true, 8, JSON_THROW_ON_ERROR);
    $key = $data['B2S_SUBMISSIONS_STAGING_KEY'] ?? '';
    new SubmissionCrypto($key);
    return $key;
}

function submission_browser_connection_string(array $config): string
{
    if (defined('B2S_HOSTED_STAGING') && B2S_HOSTED_STAGING === true && ($config['hosted'] ?? false) === true) {
        return $config['hosted_connection'];
    }
    if (PHP_SAPI === 'cli') {
        $local = submission_staging_local_config();
        if (!submission_staging_local_check($local)['hostname_confirmed']) throw new RuntimeException('Hostname confirmation missing.');
        return $local['connection_string'];
    }
    if (PHP_SAPI !== 'cli-server' || !defined('B2S_STAGING_BROWSER') || B2S_STAGING_BROWSER !== true
        || getenv('B2S_STAGING_BROWSER_ACTIVE') !== '1' || $config['mode'] !== 'live') {
        throw new RuntimeException('Private staging connection is unavailable outside the approved browser server.');
    }
    submission_browser_gate($config, getenv('B2S_STAGING_BROWSER_SERVE_FLAG') ?: '',
        getenv('B2S_STAGING_BROWSER_APPROVAL') ?: '');
    $url = getenv('B2S_STAGING_BROWSER_CONNECTION_STRING') ?: '';
    if ($url === '') throw new RuntimeException('The launcher must supply the staging connection privately; no fallback allowed.');
    return $url;
}

function submission_browser_gate(array $config, string $flag, string $approval): void
{
    if (($config['mode'] === 'offline' && $flag !== '--serve-offline')
        || ($config['mode'] === 'live' && ($flag !== '--serve-live-staging' || $approval !== SUBMISSION_BROWSER_APPROVAL
            || getenv('B2S_STAGING_BROWSER_WRITERS_STOPPED') !== '1'))) {
        throw new RuntimeException('Separate exact live browser approval and exclusive-writer confirmation required.');
    }
}

function submission_browser_validate_fiction(array $input): void
{
    $errors = [];
    foreach (['title' => '[SYNTHETIC] ', 'authors' => 'Invented ', 'submitter-name' => 'Invented '] as $field => $prefix) {
        if (!is_string($input[$field] ?? null) || !str_starts_with(trim($input[$field]), $prefix)) {
            $errors[$field] = 'Use entirely fictional data beginning with ' . $prefix;
        }
    }
    if (!is_string($input['email'] ?? null)
        || !preg_match('/^[A-Za-z0-9._+-]+@example\.com$/iD', trim($input['email']))) {
        $errors['email'] = 'Staging accepts fictional example.com email addresses only.';
    }
    if (($input['website'] ?? '') !== '') $errors['website'] = 'Leave websites blank in staging.';
    if ($errors !== []) throw new SubmissionInputException($errors);
}

final class StagingBrowserMailer implements SubmissionMailer
{
    public function __construct(private LocalMockSubmissionMailer $mock) {}
    public function sendVerification(string $email, string $link): void
    {
        if (!preg_match('/^[A-Za-z0-9._+-]+@example\.com$/iD', $email)) {
            throw new SubmissionMailException('Only fictional staging recipients are permitted.');
        }
        $this->mock->sendVerification($email, $link);
    }
}

final class StagingBrowserDatabase implements Database
{
    public function __construct(private SQLiteCloudDatabase $submissions, private LocalSqliteDatabase $staff) {}
    public function prepare(string $sql): DatabaseStatement
    {
        if (in_array($sql, [
            'SELECT id, display_name, role, is_active, must_change_password FROM users WHERE id = :id',
            'SELECT id,display_name,password_hash,role,is_active,must_change_password FROM users WHERE username = :username',
        ], true)) return $this->staff->prepare($sql);
        if (str_contains($sql, 'LEFT JOIN users u ON u.id = e.actor_user_id')) {
            $sql = str_replace('LEFT JOIN users u ON u.id = e.actor_user_id',
                "LEFT JOIN (SELECT 900001 AS id,'Synthetic Staging Reviewer' AS display_name) u ON u.id = e.actor_user_id", $sql);
        }
        return $this->submissions->prepare($sql);
    }
    public function query(string $sql): DatabaseStatement { $stmt = $this->prepare($sql); $stmt->execute(); return $stmt; }
    public function exec(string $sql): int|false { return $this->submissions->exec($sql); }
    public function beginTransaction(): bool { return $this->submissions->beginTransaction(); }
    public function commit(): bool { return $this->submissions->commit(); }
    public function rollBack(): bool { return $this->submissions->rollBack(); }
    public function inTransaction(): bool { return $this->submissions->inTransaction(); }
    public function lastInsertId(?string $name = null): string|false { return $this->submissions->lastInsertId($name); }
}

// This protocol mock is selectable only by an explicitly offline server, never as a Cloud fallback.
final class StagingBrowserOfflineClient extends \SQLiteCloud\SQLiteCloudClient
{
    private PDO $pdo;
    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:' . submission_browser_private_path($path));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
    public function connect($hostname = 'localhost', $port = 8860)
    {
        return $hostname === 'cutaojpqvz.g2.sqlite.cloud' && $port === 8860
            && $this->database === 'author-submissions-staging.sqlite';
    }
    public function execute($command)
    {
        if ($command === 'GET DATABASE NAME') return 'author-submissions-staging.sqlite';
        if ($command === 'GET USER') return 'admin';
        if ($command === 'PRAGMA database_list') {
            return $this->rowset(['seq', 'name', 'file'], [[0, 'main', 'author-submissions-staging.sqlite']]);
        }
        $stmt = $this->pdo->query($command);
        if ($stmt->columnCount() === 0) return true;
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) $columns[] = $stmt->getColumnMeta($i)['name'];
        return $this->rowset($columns, $stmt->fetchAll(PDO::FETCH_NUM));
    }
    private function rowset(array $columns, array $rows): \SQLiteCloud\SQLiteCloudRowset
    {
        $result = new \SQLiteCloud\SQLiteCloudRowset();
        $result->ncols = count($columns); $result->nrows = count($rows); $result->colname = $columns; $result->data = [];
        foreach ($rows as $row) array_push($result->data, ...$row);
        return $result;
    }
    public function disconnect() {}
}

function submission_browser_turnstile(array $config): SubmissionTurnstile
{
    if (defined('B2S_HOSTED_STAGING') && B2S_HOSTED_STAGING === true && ($config['hosted'] ?? false) === true) {
        $widget = $config['hosted_widget'];
        return new SubmissionTurnstile('production', $widget['site_key'], $widget['secret_key'],
            $widget['hostname'], $widget['action']);
    }
    $transport = $config['mode'] === 'offline'
        ? static fn(string $secret, string $token): array => ['success' => $token === 'XXXX.DUMMY.TOKEN.XXXX',
            'hostname' => 'localhost', 'action' => 'test']
        : null;
    return new SubmissionTurnstile('development', SubmissionTurnstile::TEST_SITES[0],
        SubmissionTurnstile::TEST_SECRETS[0], 'localhost', 'test', $transport);
}

function submission_browser_service(array $config, Database $db, SubmissionCrypto $crypto): SubmissionService
{
    return new SubmissionService($db, $crypto,
        new StagingBrowserMailer(new LocalMockSubmissionMailer($config['mailbox'], $crypto)),
        $config['base_url'], submission_browser_turnstile($config));
}

function submission_browser_verify_records(SQLiteCloudDatabase $db, SubmissionCrypto $crypto): void
{
    $rows = $db->query('SELECT * FROM property_submissions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 3) throw new RuntimeException('Unexpected browser fixture count.');
    foreach ($rows as $row) {
        $private = json_decode($crypto->decrypt($row['private_payload']), true, 16, JSON_THROW_ON_ERROR);
        $book = json_decode($row['book_json'], true, 16, JSON_THROW_ON_ERROR);
        submission_browser_validate_fiction(array_merge($book, $private));
        if ($row['book_title'] !== $book['title'] || $row['book_authors'] !== $book['authors']
            || $row['email_hash'] !== $crypto->hash('email', $private['email'])
            || $row['payload_hash'] !== $crypto->hash('payload', json_encode(['book' => $book, 'private' => $private],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Unexpected fictional payload or search projection.');
        }
        if ($row['evaluation_payload'] !== null) {
            submission_evaluation_validate(json_decode($crypto->decrypt($row['evaluation_payload']), true, 16, JSON_THROW_ON_ERROR));
        }
    }
    foreach (submission_staging_synthetic_properties() as $i => $input) {
        $hash = $crypto->hash('idempotency', hash('sha256', 'b2s-staging-three-v1-' . $i));
        $matches = array_values(array_filter($rows, static fn(array $row): bool => $row['idempotency_hash'] === $hash));
        $validated = submission_validate($input);
        if (count($matches) !== 1 || $matches[0]['payload_hash'] !== $crypto->hash('payload',
                json_encode($validated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
            || json_decode($crypto->decrypt($matches[0]['private_payload']), true, 16, JSON_THROW_ON_ERROR) !== $validated['private']
            || json_decode($matches[0]['book_json'], true, 16, JSON_THROW_ON_ERROR) !== $validated['book']) {
            throw new RuntimeException('Original synthetic fixture or encryption key differs.');
        }
    }
    foreach ($db->query('SELECT private_note FROM submission_events WHERE private_note IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $crypto->decrypt($event['private_note']);
    }
}

function submission_browser_session(array $config, callable $operation, bool $initial = false, ?\SQLiteCloud\SQLiteCloudClient $mockClient = null): array
{
    $manifest = submission_staging_manifest($config['identity_manifest']);
    submission_staging_migration_plan($manifest);
    $key = submission_browser_key($config);
    $crypto = new SubmissionCrypto($key);
    if ($config['mode'] === 'offline') {
        $url = 'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key';
        $client = $mockClient ?? new StagingBrowserOfflineClient($config['fixture_db']);
    } else {
        $url = submission_browser_connection_string($config);
        if ($mockClient !== null) throw new RuntimeException('Mock connections cannot be supplied in live mode.');
        $client = null;
    }
    return submission_staging_session($manifest, $url, static function ($connected) use ($manifest, $url, $config, $crypto, $operation, $initial): array {
        register_shutdown_function(static function () use ($connected): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) submission_browser_stop();
            try { $connected->disconnect(); } catch (Throwable) {
                submission_browser_stop();
                error_log('Staging browser disconnect failed; details suppressed.');
            }
        });
        $cloud = submission_browser_preflight($manifest, $url, $connected);
        submission_browser_verify_records($cloud, $crypto);
        $staff = new LocalSqliteDatabase($config['staff_db']);
        $users = $staff->query('SELECT id,username,display_name,role,is_active,must_change_password FROM users')->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) !== 1 || (int) $users[0]['id'] !== 900001 || $users[0]['username'] !== 'staging-reviewer'
            || $users[0]['display_name'] !== 'Synthetic Staging Reviewer' || $users[0]['role'] !== 'admin'
            || (int) $users[0]['is_active'] !== 1 || (int) $users[0]['must_change_password'] !== 0) {
            throw new RuntimeException('Unexpected fictional staff fixture.');
        }
        $db = new StagingBrowserDatabase($cloud, $staff);
        $service = submission_browser_service($config, $db, $crypto);
        $baseline = $config['directory'] . DIRECTORY_SEPARATOR . 'browser-established.json';
        if (is_file($baseline)) {
            $proof = json_decode(file_get_contents($baseline), true, 8, JSON_THROW_ON_ERROR);
            $baselineBase = $config['baseline_base_url'] ?? $config['base_url'];
            if (($proof['proof'] ?? null) !== $crypto->hash('browser-baseline', $config['mode'] . '|' . $baselineBase)) {
                throw new RuntimeException('Browser baseline differs; stop.');
            }
        } elseif ($initial) {
            submission_staging_synthetic_verify($cloud, $service, $crypto);
        } else {
            throw new RuntimeException('Startup preflight has not established the browser baseline.');
        }
        return $operation($db, $service, $crypto);
    }, $client);
}

function submission_browser_stop(): void
{
    $directory = getenv('B2S_STAGING_BROWSER_DIRECTORY') ?: '';
    if ($directory !== '' && file_put_contents($directory . DIRECTORY_SEPARATOR . 'browser-stopped.flag',
        'Stopped after an unexpected failure. Inspect before separately approving another launch.', LOCK_EX) === false) {
        error_log('Could not persist staging browser stop latch.');
    }
}
