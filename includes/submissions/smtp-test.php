<?php

declare(strict_types=1);

require_once __DIR__ . '/local.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/private-config.php';

function submission_smtp_private_path(string $path): string
{
    return submission_private_config_path($path);
}

function submission_namecheap_config(string $path): array
{
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('Private SMTP configuration is CLI-only.');
    }
    $path = submission_smtp_private_path($path);
    if (!is_file($path) || filesize($path) > 8192) {
        throw new RuntimeException('Use a bounded private SMTP JSON configuration.');
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Could not read private SMTP configuration.');
    }
    try {
        $config = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Private SMTP configuration is invalid JSON.');
    }
    $keys = ['host', 'port', 'encryption', 'username', 'password', 'from', 'test_recipient', 'hourly_limit'];
    if (!is_array($config) || array_diff(array_keys($config), $keys) !== []
        || array_diff($keys, array_keys($config)) !== []
        || $config['host'] !== 'booktoscreen.org' || $config['port'] !== 465
        || $config['encryption'] !== 'smtps' || $config['username'] !== 'hello@booktoscreen.org'
        || $config['from'] !== 'hello@booktoscreen.org'
        || !is_string($config['password']) || strlen($config['password']) > 1024
        || preg_match('/[\x00\r\n]/', $config['password'])
        || !is_string($config['test_recipient'])
        || ($config['test_recipient'] !== '' && !filter_var($config['test_recipient'], FILTER_VALIDATE_EMAIL))
        || $config['hourly_limit'] !== 1) {
        throw new RuntimeException('Use the pinned Namecheap SMTPS settings and a one-message hourly budget.');
    }
    return $config;
}

function submission_namecheap_ready(array $config): bool
{
    $domain = strtolower(substr(strrchr($config['test_recipient'], '@') ?: '', 1));
    return $config['password'] !== '' && $domain !== ''
        && !preg_match('/(^|\.)(example\.(com|net|org)|invalid|localhost|test)$/D', $domain);
}

function submission_namecheap_mailer(array $config, Database $db, SubmissionCrypto $crypto, ?Closure $factory = null, string $approval = ''): SmtpSubmissionMailer
{
    if (PHP_SAPI !== 'cli' || ($factory === null && (!submission_namecheap_ready($config)
        || !hash_equals('namecheap-smtp-v1:' . $config['test_recipient'], $approval)))) {
        throw new RuntimeException('Namecheap SMTP requires CLI execution and exact owned-recipient approval.');
    }
    return new SmtpSubmissionMailer(array_replace($config, ['approved' => true]), $db, $crypto, $factory);
}

function submission_smtp_test_input(string $recipient): array
{
    return [
        'isbn' => '', 'title' => '[SYNTHETIC] SMTP Verification Test', 'authors' => 'Invented Test Author',
        'genre' => 'Science Fiction', 'pitch' => 'A fictional property used only to test email verification.',
        'submitter-name' => 'Invented Test Submitter', 'email' => $recipient,
        'screen-format' => 'film', 'role' => 'author', 'authorization' => 'unknown',
        'rights-controller' => 'unknown', 'arrangement' => 'none', 'website' => '',
        'consent' => true, 'privacy' => true,
    ];
}

function submission_smtp_test_service(Database $db, SubmissionCrypto $crypto, SubmissionMailer $mailer, string $baseUrl): SubmissionService
{
    $parts = parse_url($baseUrl);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'http' || ($parts['host'] ?? '') !== '127.0.0.1'
        || !isset($parts['port']) || $parts['port'] < 1024 || $parts['port'] > 65535
        || ($parts['path'] ?? '') !== '/author-submissions'
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException('Use the explicitly prepared loopback verification fixture URL.');
    }
    return new SubmissionService($db, $crypto, $mailer, $baseUrl);
}

function submission_smtp_test_fixture(string $path, string $keyPath): array
{
    $path = submission_smtp_private_path($path);
    if (!is_file($path) || basename($path) !== 'submissions-local.sqlite') {
        throw new RuntimeException('Select only a separately prepared empty local submission fixture.');
    }
    $keyPath = submission_smtp_private_path($keyPath);
    if (!is_file($keyPath) || filesize($keyPath) > 8192) {
        throw new RuntimeException('Use a bounded private local fixture key file.');
    }
    $contents = file_get_contents($keyPath);
    if ($contents === false) throw new RuntimeException('Could not read the local fixture key.');
    try {
        $key = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Invalid local fixture key configuration.');
    }
    if (!is_array($key) || !is_string($key['B2S_SUBMISSIONS_KEY'] ?? null)) {
        throw new RuntimeException('The local fixture encryption key is missing.');
    }
    $crypto = new SubmissionCrypto($key['B2S_SUBMISSIONS_KEY']);
    $db = new LocalSqliteDatabase($path);
    if ((int) $db->query('PRAGMA foreign_keys')->fetchColumn() !== 1
        || $db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok'
        || $db->query('PRAGMA foreign_key_check')->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('Local SMTP fixture integrity checks failed.');
    }
    $versions = $db->query('SELECT version,checksum FROM submission_schema_versions ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    $expected = [];
    foreach (submission_migrations() as $version => $statements) {
        $expected[] = ['version' => $version, 'checksum' => hash('sha256', implode("\n", $statements))];
    }
    if ($versions !== $expected) throw new RuntimeException('Local SMTP fixture migration checksums differ.');
    foreach (['property_submissions', 'submission_email_tokens', 'submission_events', 'submission_genres', 'submission_rate_limits'] as $table) {
        if ((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
            throw new RuntimeException('Local SMTP fixture is not empty. Do not retry or reset it.');
        }
    }
    return [$db, $crypto];
}
