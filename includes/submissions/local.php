<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/LocalSqliteDatabase.php';
require_once __DIR__ . '/service.php';

function submission_local_database(): Database
{
    if (getenv('B2S_SUBMISSIONS_LOCAL') !== '1') {
        throw new RuntimeException('Local submission infrastructure is disabled.');
    }
    $path = getenv('B2S_SUBMISSIONS_DB') ?: '';
    $resolved = realpath($path);
    $root = realpath(__DIR__ . '/../..');
    if ($resolved === false || !is_file($resolved) || strtolower(basename($resolved)) !== 'submissions-local.sqlite'
        || str_starts_with(strtolower($resolved), strtolower($root) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Select an existing submissions-local.sqlite fixture outside the web root.');
    }
    $db = new LocalSqliteDatabase($resolved);
    $db->exec('PRAGMA busy_timeout = 5000');
    return $db;
}

function submission_local_base_url(): string
{
    $url = rtrim(getenv('B2S_SUBMISSIONS_BASE_URL') ?: '', '/');
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['host'] ?? '', ['127.0.0.1','localhost','[::1]'], true)
        || !in_array($parts['scheme'] ?? '', ['http','https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || ($parts['path'] ?? '') !== '/author-submissions') {
        throw new RuntimeException('Configure the loopback author-submissions base URL.');
    }
    return $url;
}

function submission_local_service(Database $db): SubmissionService
{
    $crypto = new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY') ?: '');
    $transport = getenv('B2S_SUBMISSIONS_MAIL') ?: 'disabled';
    // SMTP is intentionally not selectable from local HTTP routes.
    $mailer = match ($transport) {
        'disabled' => new DisabledSubmissionMailer(),
        'mock' => new LocalMockSubmissionMailer(getenv('B2S_SUBMISSIONS_MOCK_MAILBOX') ?: '', $crypto),
        default => throw new RuntimeException('Local delivery supports only disabled or mock transports.'),
    };
    return new SubmissionService($db, $crypto, $mailer, submission_local_base_url(), SubmissionTurnstile::fromEnvironment());
}
