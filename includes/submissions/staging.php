<?php

declare(strict_types=1);

require_once __DIR__ . '/migrations.php';

function submission_staging_plan(array $manifest): array
{
    $fields = ['environment', 'project_id', 'hostname', 'port', 'database',
        'production_project_id', 'production_hostname', 'production_database', 'synthetic_only'];
    if (array_diff(array_keys($manifest), $fields) !== [] || array_diff($fields, array_keys($manifest)) !== []) {
        throw new InvalidArgumentException('Supply exactly the documented staging identity fields; no credentials.');
    }
    if ($manifest['environment'] !== 'staging' || $manifest['synthetic_only'] !== true) {
        throw new InvalidArgumentException('Only explicitly identified synthetic-only staging is supported.');
    }
    foreach (['project_id', 'production_project_id'] as $field) {
        if (!is_string($manifest[$field]) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $manifest[$field]) !== 1) {
            throw new InvalidArgumentException('Supply valid project identifiers from the approved Cloud inventory.');
        }
    }
    foreach (['hostname', 'production_hostname'] as $field) {
        if (!is_string($manifest[$field]) || strlen($manifest[$field]) > 253
            || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $manifest[$field]) !== 1) {
            throw new InvalidArgumentException('Supply canonical lowercase DNS hostnames from the approved Cloud inventory.');
        }
    }
    if (!is_int($manifest['port']) || $manifest['port'] < 1 || $manifest['port'] > 65535) {
        throw new InvalidArgumentException('Supply the staging endpoint port as an integer.');
    }
    foreach (['database', 'production_database'] as $field) {
        if (!is_string($manifest[$field]) || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}$/D', $manifest[$field]) !== 1) {
            throw new InvalidArgumentException('Supply explicit single database names, not paths or SQL.');
        }
    }
    if ($manifest['database'] !== 'author-submissions-staging.sqlite'
        && (!str_starts_with($manifest['database'], 'b2s_submissions_staging_')
            || strlen($manifest['database']) <= strlen('b2s_submissions_staging_'))) {
        throw new InvalidArgumentException('Use the approved author-submissions-staging.sqlite or a b2s_submissions_staging_<label> database.');
    }

    foreach (['project_id', 'hostname', 'database'] as $field) {
        if (strcasecmp($manifest[$field], $manifest['production_' . $field]) === 0) {
            throw new InvalidArgumentException('Staging must use a different project, endpoint, and database from production.');
        }
    }
    $versions = [];
    foreach (submission_migrations() as $version => $statements) {
        $versions[] = ['version' => $version, 'checksum' => hash('sha256', implode("\n", $statements)),
            'statement_count' => count($statements)];
    }
    return [
        'assessment' => 'OFFLINE ONLY: identity claims validated, not verified against Cloud',
        'connection_opened' => false,
        'writes_authorized' => false,
        'target' => array_intersect_key($manifest, array_flip(['environment', 'project_id', 'hostname', 'port', 'database'])),
        'migrations' => $versions,
        'required_approvals' => ['Provision isolated staging and scoped credentials',
            'Read-only Cloud identity and permission inspection', 'Migration and synthetic-write validation'],
    ];
}

function submission_staging_manifest(string $filename): array
{
    $path = realpath($filename);
    $root = realpath(__DIR__ . '/../..');
    if ($path === false || !is_file($path)
        || str_starts_with(strtolower($path), strtolower($root) . DIRECTORY_SEPARATOR)) {
        throw new InvalidArgumentException('Select an existing nonsecret JSON identity manifest outside the repository.');
    }
    if (filesize($path) > 16384) {
        throw new InvalidArgumentException('The identity manifest must not exceed 16 KiB.');
    }
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Could not read the identity manifest.');
    }
    $manifest = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new InvalidArgumentException('The identity manifest must be a JSON object.');
    }
    return $manifest;
}
