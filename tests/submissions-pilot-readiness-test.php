<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('B2S_SUBMISSION_TEST_HELPERS', true);
require_once __DIR__ . '/submissions-test.php';

$checks = 0;
function pilot_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function pilot_reject(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException $e) {
        pilot_check(true, $message);
        return;
    }
    throw new RuntimeException('FAIL: ' . $message);
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-pilot-recovery-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Could not prepare private recovery fixture.');
$sourcePath = $directory . DIRECTORY_SEPARATOR . 'source.sqlite';
$backupPath = $directory . DIRECTORY_SEPARATOR . 'backup.sqlite';
$restorePath = $directory . DIRECTORY_SEPARATOR . 'restore.sqlite';
$keyPath = $directory . DIRECTORY_SEPARATOR . 'key.json';
$db = $source = $backup = $restored = $service = $recovered = null;
try {
    $key = bin2hex(random_bytes(32));
    $keyJson = json_encode(['key' => $key], JSON_THROW_ON_ERROR);
    pilot_check(file_put_contents($keyPath, $keyJson) === strlen($keyJson), 'Local synthetic key preserved privately');
    if (DIRECTORY_SEPARATOR !== '\\' && !chmod($keyPath, 0600)) throw new RuntimeException('Could not restrict synthetic key.');
    $crypto = new SubmissionCrypto($key);
    $db = new LocalSqliteDatabase($sourcePath);
    submission_migrate($db);
    $db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,display_name TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)');
    $db->exec("INSERT INTO users VALUES (900001,'Invented Recovery Reviewer','admin',1,0)");
    $mailer = new MockSubmissionMailer();
    $service = new SubmissionService($db, $crypto, $mailer, 'http://127.0.0.1:8089/author-submissions');
    $input = valid_submission();
    $input['title'] = '[SYNTHETIC] Recovery Fixture';
    $input['authors'] = 'Invented Recovery Author';
    $input['email'] = 'recovery@example.com';
    $now = time();
    $receipt = $service->submit($input, bin2hex(random_bytes(32)), 'recovery-fixture', $now);
    $token = substr(strrchr($mailer->messages[0]['link'], '='), 1);
    pilot_check($service->verify($token, 'recovery-verification', $now + 1), 'Synthetic email verification completed');
    $id = $db->query('SELECT id FROM property_submissions')->fetchColumn();
    $service->review($id, 'reviewing', 'Synthetic internal recovery note', 900001, 1, $now + 2,
        ['premise' => 'Synthetic recovery premise', 'rights_questions' => 'Synthetic rights question']);
    $before = $service->detail($id);
    pilot_check($before['evaluation']['premise'] === 'Synthetic recovery premise', 'Encrypted evaluation prepared');
    $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
    $backup = new SQLite3($backupPath);
    pilot_check($source->backup($backup), 'SQLite consistent backup succeeds on populated fixture');
    $backup->close();
    $source->close();
    $source = $backup = null;
    $digest = hash_file('sha256', $backupPath);
    pilot_check(copy($backupPath, $restorePath), 'Backup restored to separate local path');
    pilot_check(hash_equals($digest, hash_file('sha256', $restorePath)), 'Restore bytes match snapshot');
    $saved = json_decode(file_get_contents($keyPath), true, 8, JSON_THROW_ON_ERROR);
    $recoveredCrypto = new SubmissionCrypto($saved['key']);
    pilot_check($recoveredCrypto->decrypt($crypto->encrypt('key recovery')) === 'key recovery', 'Recovered original key decrypts');
    $restored = new LocalSqliteDatabase($restorePath);
    pilot_check($restored->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'Restored integrity valid');
    pilot_check($restored->query('PRAGMA foreign_key_check')->fetch(PDO::FETCH_ASSOC) === false, 'Restored FK relationships valid');
    pilot_check((int) $restored->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Restored connection enforces FK');
    $recovered = new SubmissionService($restored, $recoveredCrypto, new MockSubmissionMailer(), 'http://127.0.0.1:8089/author-submissions');
    pilot_check($recovered->detail($id) === $before, 'Contacts, evaluations, actor attribution and encrypted audit notes recover');
    pilot_check($recovered->receipt($receipt['receipt']) === $service->receipt($receipt['receipt']), 'Receipt and verification state recover');
    pilot_check(!$recovered->verify($token, 'restored-verification', $now + 3), 'Consumed token remains single-use after restore');
    pilot_check(submission_migrate($restored, $recoveredCrypto) === [], 'Migration checksums intact without new migrations');
    $tables = $restored->query("SELECT name FROM sqlite_schema WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    pilot_check(!in_array('adaptations', $tables, true) && !in_array('research_leads', $tables, true), 'Submission fixture has no public research tables');
    $wrongKeyService = new SubmissionService($restored, new SubmissionCrypto(bin2hex(random_bytes(32))),
        new MockSubmissionMailer(), 'http://127.0.0.1:8089/author-submissions');
    pilot_reject(static fn() => $wrongKeyService->detail($id), 'Wrong key cannot produce successful private detail');
    $payload = $restored->query('SELECT private_payload FROM property_submissions')->fetchColumn();
    $bytes = base64_decode($payload, true);
    $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);
    pilot_reject(static fn() => $recoveredCrypto->decrypt(base64_encode($bytes)), 'Tampered ciphertext fails authenticated decryption');
    pilot_reject(static fn() => new SubmissionCrypto(''), 'Missing original key fails explicitly');

    foreach (['127.0.0.1', '203.0.113.9'] as $remote) {
        $code = 'putenv("B2S_SUBMISSIONS_LOCAL"); putenv("B2S_STAGING_BROWSER_ACTIVE");'
            . ' $_SERVER["REMOTE_ADDR"]=' . var_export($remote, true) . ';'
            . ' register_shutdown_function(static function(){echo "|STATUS:".http_response_code();});'
            . ' require ' . var_export(__DIR__ . '/../author-submissions/bootstrap.php', true) . ';';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not execute disabled-route test.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        pilot_check(proc_close($process) === 0 && str_contains($output, $remote === '127.0.0.1' ? '|STATUS:404' : '|STATUS:403'),
            'Disabled intake rejects local default and public requests before any DB connection');
    }
    echo "PASS: {$checks} pilot readiness/recovery checks; synthetic local backups, keys and mail only. No Cloud or Siteverify connection.\n";
} finally {
    $wrongKeyService = $service = $recovered = $db = $restored = $source = $backup = null;
    foreach ([$sourcePath, $backupPath, $restorePath, $keyPath] as $path) {
        if (is_file($path)) unlink($path);
    }
    rmdir($directory);
}
