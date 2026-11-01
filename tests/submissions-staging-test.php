<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/staging.php';

$checks = 0;
function staging_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}

$manifest = [
    'environment' => 'staging', 'project_id' => 'synthetic-staging-project',
    'hostname' => 'staging.example.com', 'port' => 8860, 'database' => 'b2s_submissions_staging_synthetic',
    'production_project_id' => 'synthetic-production-project', 'production_hostname' => 'production.example.com',
    'production_database' => 'adaptations.sqlite', 'synthetic_only' => true,
];
$plan = submission_staging_plan($manifest);
staging_check(!$plan['connection_opened'] && !$plan['writes_authorized'], 'Preparation never connects or authorizes writes');
staging_check(count($plan['migrations']) === 2, 'Both existing migrations included');
foreach (submission_migrations() as $version => $sql) {
    staging_check($plan['migrations'][$version - 1]['checksum'] === hash('sha256', implode("\n", $sql)),
        'Plan uses the immutable migration checksum ' . $version);
}
staging_check(!isset($plan['target']['production_hostname']), 'Report excludes production identity');
staging_check(submission_staging_plan(array_replace($manifest, ['database'=>'author-submissions-staging.sqlite']))['target']['database']
    === 'author-submissions-staging.sqlite', 'Explicitly approved database name accepted without relaxing other guards');
foreach ([
    ['environment' => 'production'], ['synthetic_only' => false], ['synthetic_only' => 'true'],
    ['project_id' => 'synthetic-production-project'], ['project_id' => 'SYNTHETIC-PRODUCTION-PROJECT'],
    ['hostname' => 'production.example.com'], ['database' => 'adaptations.sqlite'],
    ['database' => 'b2s_submissions_staging_'], ['database' => 'b2s_submissions_staging_x;DROP'],
    ['hostname' => 'sqlitecloud://staging.example.com'], ['hostname' => 'STAGING.example.com'],
    ['hostname' => 'localhost'], ['port' => '8860'], ['port' => 0], ['port' => 65536],
    ['project_id' => []], ['production_project_id' => ''], ['production_database' => '../adaptations.sqlite'],
    ['connection_string' => 'must-not-be-accepted'], ['api_key' => 'must-not-be-accepted'],
] as $change) {
    try {
        submission_staging_plan(array_replace($manifest, $change));
    } catch (InvalidArgumentException $e) {
        staging_check(true, 'Unsafe or ambiguous manifest rejected');
        continue;
    }
    throw new RuntimeException('FAIL: unsafe manifest accepted');
}
$missing = $manifest;
unset($missing['production_hostname']);
try {
    submission_staging_plan($missing);
    throw new RuntimeException('FAIL: missing production inventory accepted');
} catch (InvalidArgumentException $e) {
    staging_check(true, 'Missing production inventory rejected');
}
staging_check(!class_exists('SQLiteCloud\\SQLiteCloudClient', false), 'Offline preflight does not even load the Cloud client');
staging_check(!defined('SQLITE_CLOUD_CONNECTION_STRING') && !defined('DB_DRIVER'), 'No application configuration or fallback loaded');
$path = tempnam(sys_get_temp_dir(), 'b2s-staging-');
if ($path === false) throw new RuntimeException('Could not create the synthetic identity test file.');
try {
    $run = static function (string $path): array {
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/../scripts/prepare-submissions-staging.php', $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start the offline CLI test.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output, $error];
    };
    if (file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Could not write the synthetic identity test file.');
    }
    [$status, $output] = $run($path);
    staging_check($status === 0, 'Offline CLI accepts an external synthetic identity manifest');
    $report = json_decode($output, true, 8, JSON_THROW_ON_ERROR);
    staging_check($report['connection_opened'] === false && $report['writes_authorized'] === false,
        'CLI output explicitly denies connection and write authorization');
    foreach (['{invalid', json_encode(array_replace($manifest, ['environment' => 'production']), JSON_THROW_ON_ERROR),
        json_encode(array_replace($manifest, ['api_key' => 'synthetic-do-not-print']), JSON_THROW_ON_ERROR),
        str_repeat('x', 16385)] as $invalid) {
        if (file_put_contents($path, $invalid) === false) throw new RuntimeException('Could not write invalid test input.');
        [$status, $output, $error] = $run($path);
        staging_check($status === 1 && $output === '' && $error !== '' && !str_contains($error, 'synthetic-do-not-print'),
            'CLI rejects invalid input explicitly without success output or secret contents');
    }
    foreach ([__DIR__ . '/../README.md', $path . '.missing'] as $invalidPath) {
        [$status, $output, $error] = $run($invalidPath);
        staging_check($status === 1 && $output === '' && $error !== '', 'CLI rejects repository or missing manifests');
    }
} finally {
    if (!unlink($path)) throw new RuntimeException('Could not remove the synthetic identity test file.');
}
echo "Passed {$checks} staging preparation checks (offline only).\n";
