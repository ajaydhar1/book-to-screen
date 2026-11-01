<?php

declare(strict_types=1);

require_once __DIR__ . '/staging-diagnostic.php';

function submission_staging_local_config(): array
{
    $path = __DIR__ . '/../../scripts/submissions-staging.local.php';
    if (!is_file($path)) {
        throw new RuntimeException('The dedicated private staging config file is missing; no fallback is allowed.');
    }
    ob_start();
    try {
        $config = require $path;
        if (ob_get_length() !== 0) throw new RuntimeException('The private config must not produce output.');
    } finally {
        ob_end_clean();
    }
    if (!is_array($config)) {
        throw new RuntimeException('The private staging config must return an array.');
    }
    return $config;
}

function submission_staging_local_check(#[SensitiveParameter] array $config): array
{
    $fields = ['project_name','database','expected_hostname','connection_string'];
    if (array_diff(array_keys($config), $fields) !== [] || array_diff($fields, array_keys($config)) !== []
        || $config['project_name'] !== 'book-to-screen-staging'
        || $config['database'] !== 'author-submissions-staging.sqlite'
        || !is_string($config['connection_string']) || !is_string($config['expected_hostname'])) {
        throw new InvalidArgumentException('Use exactly the documented private config fields and approved staging names.');
    }
    $url = $config['connection_string'];
    if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f]/', $url)) {
        throw new InvalidArgumentException('Enter a single complete staging connection string without whitespace in the private file.');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !is_string($parts['host'] ?? null)
        || strlen($parts['host']) > 253
        || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $parts['host']) !== 1) {
        throw new InvalidArgumentException('The staging connection must contain a canonical lowercase DNS hostname.');
    }
    $target = ['hostname'=>$parts['host'], 'port'=>$parts['port'] ?? 8860, 'database'=>$config['database']];
    submission_staging_diagnostic_auth(['target'=>$target,'credential_mode'=>'staging-admin','expected_user'=>'admin'], $url);
    if ($config['expected_hostname'] !== '' && $config['expected_hostname'] !== $parts['host']) {
        throw new InvalidArgumentException('The extracted hostname does not match the independently confirmed hostname. Stop.');
    }
    return [
        'project_name'=>$config['project_name'], 'hostname'=>$parts['host'], 'port'=>$target['port'],
        'database'=>$config['database'], 'hostname_confirmed'=>$config['expected_hostname'] !== '',
        'connection_opened'=>false, 'queries_executed'=>false,
        'status'=>$config['expected_hostname'] === '' ? 'AWAITING_INDEPENDENT_HOSTNAME_COMPARISON' : 'CONFIGURED_AWAITING_SEPARATE_APPROVAL',
        'isolation_verified'=>false,
    ];
}
