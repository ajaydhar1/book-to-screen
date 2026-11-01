<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/smtp-test.php';
require_once __DIR__ . '/../vendor/autoload.php';

final class OfflineNamecheapSMTP extends \PHPMailer\PHPMailer\SMTP
{
    public array $connection = [];
    public int $connections = 0;
    public int $deliveries = 0;
    public string $failure = '';
    private bool $active = false;

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->connections++;
        $this->connection = [$host, $port, $timeout, $options];
        $this->active = $this->failure !== 'connect';
        return $this->active;
    }
    public function connected() { return $this->active; }
    public function hello($host = '')
    {
        $this->server_caps = ['EHLO' => 'offline', 'AUTH' => ['LOGIN']];
        return true;
    }
    public function startTLS() { throw new RuntimeException('SMTPS must not downgrade to STARTTLS.'); }
    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if ($this->failure === 'auth') {
            $this->setError('fixture-password-must-not-leak');
            return false;
        }
        return $username === 'hello@booktoscreen.org' && $password === 'fixture-password-must-not-leak';
    }
    public function mail($from) { return $from === 'hello@booktoscreen.org'; }
    public function recipient($address, $dsn = '') { return $this->failure !== 'recipient'; }
    public function data($msg_data)
    {
        $this->deliveries++;
        return $this->failure !== 'data';
    }
    public function quit($close_on_error = true) { $this->active = false; return true; }
    public function close() { $this->active = false; }
}

$checks = 0;
function smtp_assert(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $checks++;
}
function smtp_reject(callable $operation, string $description): void
{
    try {
        $operation();
    } catch (RuntimeException $e) {
        smtp_assert(!str_contains($e->getMessage(), 'fixture-password-must-not-leak'), 'Error is sanitized');
        smtp_assert(true, $description);
        return;
    }
    throw new RuntimeException('FAIL: ' . $description);
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-smtp-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Could not prepare private SMTP fixture.');
$path = $directory . DIRECTORY_SEPARATOR . 'smtp.json';
$config = [
    'host' => 'booktoscreen.org', 'port' => 465, 'encryption' => 'smtps',
    'username' => 'hello@booktoscreen.org', 'password' => 'fixture-password-must-not-leak',
    'from' => 'hello@booktoscreen.org', 'test_recipient' => 'owned-fixture@example.com', 'hourly_limit' => 1,
];
function smtp_write_fixture(string $path, array $config): void
{
    if (file_put_contents($path, json_encode($config, JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Could not write private SMTP fixture.');
    }
    if (DIRECTORY_SEPARATOR !== '\\' && !chmod($path, 0600)) throw new RuntimeException('Could not restrict fixture.');
}
try {
    smtp_write_fixture($path, $config);
    smtp_assert(submission_namecheap_config($path) === $config, 'Private pinned config loads');
    smtp_assert(!submission_namecheap_ready($config), 'Reserved example recipient cannot receive live mail');
    smtp_assert(submission_namecheap_ready(array_replace($config, ['test_recipient' => 'owned@booktoscreen.org'])), 'Configured nonreserved address ready for explicit ownership approval');
    smtp_assert(!submission_namecheap_ready(array_replace($config, ['password' => ''])), 'Missing password not ready');
    foreach ([
        ['host' => 'other.invalid'], ['port' => 587], ['port' => '465'], ['encryption' => 'none'],
        ['username' => 'other@example.com'], ['from' => 'other@example.com'], ['hourly_limit' => 2],
        ['test_recipient' => "recipient@example.com\r\nBcc: injected@example.com"],
        ['password' => "invalid\npassword"], ['password' => []],
        ['approved' => true], ['SMTPOptions' => ['ssl' => ['verify_peer' => false]]],
    ] as $override) {
        smtp_write_fixture($path, array_replace($config, $override));
        smtp_reject(static fn() => submission_namecheap_config($path), 'Unsafe or nonpinned configuration rejected');
    }
    file_put_contents($path, '{"password":"fixture-password-must-not-leak",');
    smtp_reject(static fn() => submission_namecheap_config($path), 'Malformed JSON does not reveal content');
    smtp_reject(static fn() => submission_smtp_private_path(__FILE__), 'Web-root file rejected');
    smtp_write_fixture($path, $config);
    $crypto = new SubmissionCrypto(bin2hex(random_bytes(32)));
    foreach (['', 'connect', 'auth', 'recipient', 'data'] as $failure) {
        $db = new LocalSqliteDatabase(':memory:');
        submission_migrate($db);
        $smtp = new OfflineNamecheapSMTP();
        $smtp->failure = $failure;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->setSMTPInstance($smtp);
        $mailer = submission_namecheap_mailer($config, $db, $crypto, static fn() => $mail);
        smtp_reject(static fn() => $mailer->sendVerification('unapproved@example.com', 'fixture'), 'Unapproved recipient blocked before connection');
        smtp_assert($smtp->connections === 0, 'Recipient rejection does not connect');
        $service = submission_smtp_test_service($db, $crypto, $mailer, 'http://127.0.0.1:8088/author-submissions');
        $now = time();
        $request = bin2hex(random_bytes(32));
        $result = $service->submit(submission_smtp_test_input($config['test_recipient']), $request, 'offline-smtp', $now);
        $expected = $failure === '' ? 'sent' : 'failed';
        smtp_assert($service->receipt($result['receipt'])['delivery_status'] === $expected, 'SMTP outcome persists, never false success');
        smtp_assert($smtp->connection[0] === 'ssl://booktoscreen.org' && $smtp->connection[1] === 465, 'Implicit TLS target and port');
        smtp_assert($smtp->connection[3]['ssl'] === ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false], 'Peer and hostname validation, no self-signed certificates');
        smtp_assert($mail->SMTPDebug === 0 && $mail->Timeout === 10 && $mail->Timelimit === 15, 'Quiet bounded SMTP');
        smtp_assert($mail->getToAddresses() === [['owned-fixture@example.com', '']], 'Exactly one allowlisted recipient');
        smtp_assert(!str_contains($mail->Body, 'fictional property') && str_contains($mail->Body, '24 hours'), 'Verification-only body, no pitch');
        $events = $db->query("SELECT event_type FROM submission_events ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        smtp_assert(in_array($failure === '' ? 'verification_email_sent' : 'verification_email_failed', $events, true), 'Correct delivery audit event');
        $before = $smtp->connections;
        $service->submit(submission_smtp_test_input($config['test_recipient']), $request, 'offline-smtp', $now + 1);
        smtp_assert($smtp->connections === $before, 'Idempotent repeat does not reconnect or resend');
        smtp_reject(static fn() => $mailer->sendVerification($config['test_recipient'], 'fixture'), 'Attempted sends consume hourly budget even on failure');
        smtp_assert($smtp->connections === $before, 'Budget blocks connection');
        preg_match('/#token=([a-f0-9]{64})/', $mail->Body, $token);
        smtp_assert(isset($token[1]), 'Existing cryptographic token reaches verification body');
        smtp_assert((int) $db->query('SELECT expires_at-created_at FROM submission_email_tokens')->fetchColumn() === 86400, 'Existing 24-hour expiry unchanged');
        if ($failure === '') {
            smtp_assert($service->verify($token[1], 'offline-smtp', $now + 2), 'Delivered token verifies');
            smtp_assert(!$service->verify($token[1], 'offline-smtp', $now + 3), 'Email token is single-use');
        }
    }
    smtp_reject(static fn() => submission_namecheap_mailer(array_replace($config, ['test_recipient' => 'owned@booktoscreen.org']), $db, $crypto), 'No live factory without exact approval');
    smtp_reject(static fn() => submission_smtp_test_service($db, $crypto, new DisabledSubmissionMailer(), 'https://booktoscreen.org/author-submissions'), 'Controlled test cannot select public intake');
    $dbPath = $directory . DIRECTORY_SEPARATOR . 'submissions-local.sqlite';
    $keyPath = $directory . DIRECTORY_SEPARATOR . 'key.json';
    smtp_write_fixture($keyPath, ['B2S_SUBMISSIONS_KEY' => str_repeat('a', 64)]);
    $fixture = new LocalSqliteDatabase($dbPath);
    if (DIRECTORY_SEPARATOR !== '\\') chmod($dbPath, 0600);
    submission_migrate($fixture);
    [$validatedDb, $validatedCrypto] = submission_smtp_test_fixture($dbPath, $keyPath);
    smtp_assert($validatedDb instanceof LocalSqliteDatabase && $validatedCrypto instanceof SubmissionCrypto, 'Live fixture preflight verified offline');
    $validatedDb = null;
    $fixture->exec("UPDATE submission_schema_versions SET checksum = 'incorrect' WHERE version = 2");
    smtp_reject(static fn() => submission_smtp_test_fixture($dbPath, $keyPath), 'Changed fixture checksum stops before SMTP');
    $statements = submission_migrations()[2];
    $fixture->prepare('UPDATE submission_schema_versions SET checksum = :checksum WHERE version = 2')
        ->execute([':checksum' => hash('sha256', implode("\n", $statements))]);
    $fixture->exec("INSERT INTO submission_rate_limits(bucket_hash,window_started_at,attempts) VALUES ('fixture',0,1)");
    smtp_reject(static fn() => submission_smtp_test_fixture($dbPath, $keyPath), 'Used fixture stops before SMTP');
    smtp_write_fixture($keyPath, ['B2S_SUBMISSIONS_KEY' => 'invalid']);
    smtp_reject(static fn() => submission_smtp_test_fixture($dbPath, $keyPath), 'Invalid fixture key rejected');
    foreach ([[], ['--send-one'], ['--check', $path], ['--send-one', $path, 'wrong-approval', $dbPath, $keyPath, 'http://127.0.0.1:8088/author-submissions']] as $arguments) {
        $process = proc_open(array_merge([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/../scripts/test-submissions-smtp.php'], $arguments),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not execute offline CLI guard test.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        smtp_assert(!str_contains($output, 'fixture-password-must-not-leak'), 'CLI output contains no credentials');
        smtp_assert($arguments === [] ? $exit === 0 && str_contains($output, 'OFFLINE_SMTP_VERIFIED')
            : ($arguments[0] === '--check' ? $exit === 2 : $exit === 1), 'CLI defaults offline; incomplete/live approvals fail closed');
    }
    echo "PASS: {$checks} SMTP checks; actual PHPMailer with offline SMTP responses, no sockets or email.\n";
} finally {
    $fixture = null;
    $validatedDb = null;
    foreach ([$directory . DIRECTORY_SEPARATOR . 'submissions-local.sqlite', $directory . DIRECTORY_SEPARATOR . 'key.json'] as $temporary) {
        if (is_file($temporary)) unlink($temporary);
    }
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
