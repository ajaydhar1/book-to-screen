<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('B2S_STAGING_MIGRATION_TEST_HELPERS', true);
require_once __DIR__ . '/submissions-staging-migration-test.php';
require_once __DIR__ . '/../includes/submissions/staging-browser.php';
require_once __DIR__ . '/../includes/submissions/staging-browser-preflight.php';
$checks = 0;
function browser_guard_check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function browser_guard_reject(callable $operation): void
{
    try { $operation(); } catch (RuntimeException|InvalidArgumentException $e) {
        browser_guard_check(!str_contains($e->getMessage(), 'offline-fictional-key'), 'Sanitized failure'); return;
    }
    throw new RuntimeException('FAIL: unsafe browser fixture accepted.');
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-browser-guards-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Private test directory failed.');
$files = ['key.json', 'identity.json', 'staff.sqlite', 'browser-established.json'];
try {
    $key = bin2hex(random_bytes(32));
    $crypto = new SubmissionCrypto($key);
    $manifest = [
        'identity' => ['environment' => 'staging', 'project_id' => 'cutaojpqvz', 'hostname' => 'cutaojpqvz.g2.sqlite.cloud',
            'port' => 8860, 'database' => 'author-submissions-staging.sqlite', 'production_project_id' => 'fictional-production',
            'production_hostname' => 'production.example.com', 'production_database' => 'adaptations.sqlite', 'synthetic_only' => true],
        'project_name' => 'book-to-screen-staging', 'control_panel_identity_confirmed' => true,
        'credential_mode' => 'staging-admin', 'expected_user' => 'admin', 'admin_risk_approved' => true,
    ];
    $config = ['mode' => 'offline', 'directory' => $directory, 'base_url' => 'http://127.0.0.1:8085/author-submissions',
        'key_file' => $directory . DIRECTORY_SEPARATOR . 'key.json', 'identity_manifest' => $directory . DIRECTORY_SEPARATOR . 'identity.json',
        'staff_db' => $directory . DIRECTORY_SEPARATOR . 'staff.sqlite', 'mailbox' => $directory . DIRECTORY_SEPARATOR . 'submissions-mailbox.jsonl',
        'fixture_db' => $directory . DIRECTORY_SEPARATOR . 'unused.sqlite'];
    file_put_contents($config['key_file'], json_encode(['B2S_SUBMISSIONS_STAGING_KEY' => $key], JSON_THROW_ON_ERROR));
    file_put_contents($config['identity_manifest'], json_encode($manifest, JSON_THROW_ON_ERROR));
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'browser-established.json',
        json_encode(['proof' => $crypto->hash('browser-baseline', 'offline|' . $config['base_url'])], JSON_THROW_ON_ERROR));
    $staff = new LocalSqliteDatabase($config['staff_db']);
    $staff->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,display_name TEXT,password_hash TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)');
    $stmt = $staff->prepare("INSERT INTO users VALUES (900001,'staging-reviewer','Synthetic Staging Reviewer',:password,'admin',1,0)");
    $stmt->execute(['password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]); $stmt = null;
    $fixture = static function () use ($manifest, $key): StagingMigrationFixtureClient {
        $client = new StagingMigrationFixtureClient();
        submission_migrate(new SQLiteCloudDatabase($client));
        submission_staging_synthetic_run($manifest,
            'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key',
            $key, '--test-three-synthetic', 'cutaojpqvz/author-submissions-staging.sqlite/three-synthetic-v1', $client);
        $client->commands = []; $client->connections = 0; $client->closed = false;
        return $client;
    };
    $run = static fn($client): array => submission_browser_session($config, static fn(): array => ['verified' => true], false, $client);
    $client = $fixture();
    $url = 'sqlitecloud://cutaojpqvz.g2.sqlite.cloud:8860/author-submissions-staging.sqlite?apikey=offline-fictional-key';
    $optimized = $fixture();
    submission_browser_preflight($manifest, $url, $optimized);
    browser_guard_check(count($optimized->commands) === 14, 'Optimized schema/identity/integrity preflight uses exactly 14 reads');
    browser_guard_check(count(array_filter($optimized->commands, static fn($sql): bool => str_contains($sql, 'FROM main.sqlite_schema'))) === 1,
        'Exactly one schema read per optimized preflight');
    $countSql = 'SELECT ' . implode(',', array_map(static fn(string $table): string => '(SELECT COUNT(*) FROM ' . $table . ') AS ' . $table,
        ['property_submissions','submission_email_tokens','submission_events','submission_genres','submission_rate_limits']));
    $columns = ['property_submissions','submission_email_tokens','submission_events','submission_genres','submission_rate_limits'];
    foreach ([[], [[3,3,15,3,-1]], [[3,3,15,3,'1.5']], [[3,3,15,3,null]], [[3,3,15,3,'9223372036854775808']],
        [[3,3,15,3,9],[3,3,15,3,9]]] as $badRows) {
        $c = $fixture();
        $c->overrides[$countSql] = StagingMigrationFixtureClient::rowset($columns, $badRows);
        browser_guard_reject(fn() => submission_browser_preflight($manifest, $url, $c));
    }
    $c = $fixture();
    $c->overrides[$countSql] = StagingMigrationFixtureClient::rowset(array_reverse($columns), [[3,3,15,3,9]]);
    browser_guard_reject(fn() => submission_browser_preflight($manifest, $url, $c));
    foreach (['ledger','fk-check','integrity','schema','vector'] as $failure) {
        $c = $fixture();
        if ($failure === 'ledger') $c->pdo->exec("UPDATE submission_schema_versions SET checksum='modified'");
        if ($failure === 'fk-check') $c->overrides['PRAGMA foreign_key_check'] = StagingMigrationFixtureClient::rowset(
            ['table','rowid','parent','fkid'], [['submission_events',1,'property_submissions',0]]);
        if ($failure === 'integrity') $c->overrides['PRAGMA integrity_check'] = StagingMigrationFixtureClient::rowset(['integrity_check'], [['not ok']]);
        if ($failure === 'schema') $c->pdo->exec('CREATE TABLE unknown(x)');
        if ($failure === 'vector') $c->pdo->exec("INSERT INTO _sqliteai_vector VALUES ('x','y','z',1)");
        browser_guard_reject(fn() => submission_browser_preflight($manifest, $url, $c));
    }
    browser_guard_check($run($client) === ['verified' => true], 'Mock browser preflight succeeds');
    browser_guard_check($client->connections === 1 && $client->closed, 'Mock connection closed');
    browser_guard_check(count($client->commands) === 16, 'Complete browser preflight uses exactly 16 Cloud reads');
    $beforeCommands = count($client->commands);
    browser_guard_check($run($client) === ['verified' => true] && $client->connections === 2
        && count($client->commands) - $beforeCommands === 16, 'Every request reconnects and repeats all safeguards without caching');
    browser_guard_check(!array_filter($client->commands, static fn($sql): bool => preg_match('/^(INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|BEGIN|COMMIT|ROLLBACK)/i', $sql) === 1),
        'Browser preflight performs only reads');
    foreach (['identity', 'fk', 'schema', 'vector-record', 'vector-definition', 'payload', 'note', 'missing-original', 'foreign-email'] as $failure) {
        $c = $fixture();
        if ($failure === 'identity') $c->wrongIdentity = true;
        if ($failure === 'fk') $c->fkOff = true;
        if ($failure === 'schema') $c->pdo->exec('CREATE TABLE unknown(x)');
        if ($failure === 'vector-record') $c->pdo->exec("INSERT INTO _sqliteai_vector VALUES ('fictional','column','key',1)");
        if ($failure === 'vector-definition') { $c->pdo->exec('DROP TABLE _sqliteai_vector'); $c->pdo->exec('CREATE TABLE _sqliteai_vector (x)'); }
        if ($failure === 'payload') $c->pdo->exec("UPDATE property_submissions SET payload_hash='modified'");
        if ($failure === 'note') $c->pdo->exec("UPDATE submission_events SET private_note='corrupt' WHERE event_type='review_updated'");
        if ($failure === 'missing-original') $c->pdo->exec("UPDATE property_submissions SET idempotency_hash='modified' WHERE id=(SELECT id FROM property_submissions LIMIT 1)");
        if ($failure === 'foreign-email') {
            $row = $c->pdo->query('SELECT id,private_payload FROM property_submissions LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $payload = json_decode($crypto->decrypt($row['private_payload']), true);
            $payload['email'] = 'not-fictional@example.org';
            $stmt = $c->pdo->prepare('UPDATE property_submissions SET private_payload=? WHERE id=?');
            $stmt->execute([$crypto->encrypt(json_encode($payload, JSON_THROW_ON_ERROR)), $row['id']]); $stmt = null;
        }
        browser_guard_reject(fn() => $run($c));
        browser_guard_check($c->closed, 'Failure closes mock connection');
        browser_guard_check(!array_filter($c->commands, static fn($sql): bool => preg_match('/^(INSERT|UPDATE|DELETE|BEGIN)/i', $sql) === 1),
            'Unexpected state stops before writes');
    }
    $c = $fixture();
    file_put_contents($config['key_file'], json_encode(['B2S_SUBMISSIONS_STAGING_KEY' => bin2hex(random_bytes(32))], JSON_THROW_ON_ERROR));
    browser_guard_reject(fn() => $run($c));
    file_put_contents($config['key_file'], json_encode(['B2S_SUBMISSIONS_STAGING_KEY' => $key], JSON_THROW_ON_ERROR));
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'browser-established.json', '{"proof":"modified"}');
    browser_guard_reject(fn() => $run($fixture()));
    $live = array_merge($config, ['mode' => 'live']);
    browser_guard_reject(fn() => submission_browser_gate($live, '--serve-live-staging', ''));
    browser_guard_reject(fn() => submission_browser_gate($live, '--serve-offline', SUBMISSION_BROWSER_APPROVAL));
    putenv('B2S_STAGING_BROWSER_WRITERS_STOPPED');
    browser_guard_reject(fn() => submission_browser_gate($live, '--serve-live-staging', SUBMISSION_BROWSER_APPROVAL));
    $challenge = submission_browser_turnstile($config);
    $challenge->verify('XXXX.DUMMY.TOKEN.XXXX', time());
    browser_guard_check($challenge->environment === 'development', 'Official development configuration retained offline');
    browser_guard_reject(fn() => $challenge->verify('', time()));
    browser_guard_reject(fn() => $challenge->verify('invalid', time()));
    browser_guard_check(submission_browser_turnstile($live)->environment === 'development', 'Live mode selects enforced official development transport');
    browser_guard_reject(fn() => submission_browser_private_path(__FILE__));
    echo "PASS: {$checks} staging browser guard checks using mocked Cloud connections only.\n";
} finally {
    $staff = null; $stmt = null;
    foreach ($files as $file) { $path = $directory . DIRECTORY_SEPARATOR . $file; if (is_file($path)) unlink($path); }
    rmdir($directory);
}
