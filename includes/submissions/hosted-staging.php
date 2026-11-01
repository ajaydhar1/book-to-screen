<?php

declare(strict_types=1);

require_once __DIR__ . '/private-config.php';
require_once __DIR__ . '/staging-browser.php';
require_once __DIR__ . '/turnstile-config.php';

const SUBMISSION_HOSTED_BASE = 'https://booktoscreen.org/__author-staging/author-submissions';

function submission_hosted_settings(string $path): array
{
    $path = submission_private_config_path($path, false);
    if (!is_file($path) || filesize($path) > 8192) throw new RuntimeException('Invalid hosted settings.');
    $data = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
    $keys = ['enabled', 'deadline', 'user', 'writers_stopped', 'key_file', 'staff_db', 'identity_manifest',
        'connection_file', 'widget_config', 'baseline_base_url'];
    if (!is_array($data) || array_diff(array_keys($data), $keys) !== [] || array_diff($keys, array_keys($data)) !== []
        || !is_bool($data['enabled']) || !is_bool($data['writers_stopped'])
        || !is_int($data['deadline']) || $data['deadline'] < 0
        || !is_string($data['user']) || !preg_match('/^[a-zA-Z0-9_.@-]{1,100}$/D', $data['user'])) {
        throw new RuntimeException('Invalid explicit hosted settings.');
    }
    if (!is_string($data['baseline_base_url'])
        || !preg_match('~^http://127\.0\.0\.1:[0-9]{4,5}/author-submissions$~D', $data['baseline_base_url'])) {
        throw new RuntimeException('Preserved staging baseline URL required.');
    }
    foreach (['key_file', 'staff_db', 'identity_manifest', 'connection_file', 'widget_config'] as $field) {
        if (!is_string($data[$field]) || !preg_match('~^(?:/|[a-zA-Z]:[\\\\/])~', $data[$field])) {
            throw new RuntimeException('Absolute private hosted files required.');
        }
    }
    $data['directory'] = dirname($path);
    submission_private_config_path($data['directory'], false);
    return $data;
}

function submission_hosted_gate(array $server, array $settings, int $now): bool
{
    return $settings['enabled'] === true && $settings['writers_stopped'] === true
        && $settings['deadline'] > $now && $settings['deadline'] <= $now + 86400
        && ($server['HTTPS'] ?? '') === 'on' && ($server['HTTP_HOST'] ?? '') === 'booktoscreen.org'
        && ($server['REMOTE_USER'] ?? '') === $settings['user'];
}

function submission_hosted_config(array $settings, ?Closure $stageObserver = null): array
{
    foreach (['key_file', 'staff_db', 'identity_manifest', 'connection_file', 'widget_config'] as $field) {
        if ($stageObserver !== null) $stageObserver('Private path: ' . $field);
        $settings[$field] = submission_private_config_path($settings[$field], false);
        if (!is_file($settings[$field])) throw new RuntimeException('Private hosted file missing.');
    }
    if ($stageObserver !== null) $stageObserver('Manifest format and staging identity');
    $manifest = submission_staging_manifest($settings['identity_manifest']);
    $identity = $manifest['identity'] ?? [];
    if (($identity['project_id'] ?? '') !== 'cutaojpqvz'
        || ($identity['hostname'] ?? '') !== 'cutaojpqvz.g2.sqlite.cloud'
        || ($identity['port'] ?? null) !== 8860
        || ($identity['database'] ?? '') !== 'author-submissions-staging.sqlite') {
        throw new RuntimeException('Only the preserved exact staging target is permitted.');
    }
    submission_staging_migration_plan($manifest);
    if ($stageObserver !== null) $stageObserver('Connection format and target');
    $connection = json_decode(file_get_contents($settings['connection_file']), true, 4, JSON_THROW_ON_ERROR);
    if (!is_array($connection) || array_keys($connection) !== ['connection_string']
        || !is_string($connection['connection_string'])) throw new RuntimeException('Invalid private staging connection.');
    submission_staging_diagnostic_auth(submission_staging_diagnostic_config($manifest), $connection['connection_string']);
    if ($stageObserver !== null) $stageObserver('Widget format and compatibility');
    if (filesize($settings['widget_config']) > 8192) throw new RuntimeException('Bounded widget settings required.');
    $widget = submission_turnstile_validate_config(json_decode(file_get_contents($settings['widget_config']), true, 8, JSON_THROW_ON_ERROR));
    if ($widget['site_key'] === '' || $widget['secret_key'] === '') throw new RuntimeException('Real widget configuration required.');
    $config = array_merge($settings, [
        'mode' => 'live', 'hosted' => true, 'base_url' => SUBMISSION_HOSTED_BASE,
        'mailbox' => $settings['directory'] . DIRECTORY_SEPARATOR . 'submissions-mailbox.jsonl',
        'stop_file' => $settings['directory'] . DIRECTORY_SEPARATOR . 'browser-stopped.flag',
        'hosted_connection' => $connection['connection_string'], 'hosted_widget' => $widget,
    ]);
    if ($stageObserver !== null) $stageObserver('Private sessions path');
    submission_private_config_path($settings['directory'] . DIRECTORY_SEPARATOR . 'sessions', false);
    if (!is_dir($settings['directory'] . DIRECTORY_SEPARATOR . 'sessions')) throw new RuntimeException('Private sessions missing.');
    if ($stageObserver !== null) $stageObserver('Key JSON and key format');
    submission_browser_key($config);
    foreach (['submissions-mailbox.jsonl', 'submissions-isbn-cache.json', 'browser-established.json', 'browser-stopped.flag'] as $name) {
        $file = $settings['directory'] . DIRECTORY_SEPARATOR . $name;
        if ($stageObserver !== null) $stageObserver('Existing private state: ' . $name);
        if (file_exists($file) || is_link($file)) submission_private_config_path($file, false);
    }
    return $config;
}
