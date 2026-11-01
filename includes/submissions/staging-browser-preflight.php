<?php

declare(strict_types=1);

require_once __DIR__ . '/staging-migration.php';

function submission_browser_preflight(array $manifest, #[SensitiveParameter] string $url,
    \SQLiteCloud\SQLiteCloudClient $connected): SQLiteCloudDatabase
{
    $inspection = submission_staging_inspect_connected($manifest, $url, $connected, true);
    if ($inspection['foreign_keys'] !== 1 || $inspection['query_only'] !== 0) {
        throw new RuntimeException('Unsuitable staging session.');
    }
    $cloud = new SQLiteCloudDatabase($connected);
    $schema = submission_staging_migration_schema($cloud);
    $vector = array_intersect_key($schema, array_flip(['_sqliteai_vector', 'sqlite_autoindex__sqliteai_vector_1']));
    if (count($vector) !== 2 || !submission_staging_vector_metadata_matches(array_values($vector))) {
        throw new RuntimeException('Expected SQLite-Vector pair is missing or modified.');
    }
    submission_staging_verify_install($cloud, $vector, false, $schema);
    if (submission_staging_migration_rows($cloud, 'PRAGMA integrity_check', ['integrity_check'], 1) !== [['integrity_check' => 'ok']]) {
        throw new RuntimeException('Staging integrity check failed.');
    }
    return $cloud;
}
