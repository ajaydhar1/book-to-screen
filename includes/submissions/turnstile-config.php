<?php

declare(strict_types=1);

require_once __DIR__ . '/private-config.php';
require_once __DIR__ . '/antibot.php';

function submission_turnstile_private_config(string $path): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Turnstile preparation configuration is CLI-only.');
    $path = submission_private_config_path($path);
    if (!is_file($path) || filesize($path) > 8192) throw new RuntimeException('Use a bounded private Turnstile JSON file.');
    $contents = file_get_contents($path);
    if ($contents === false) throw new RuntimeException('Could not read private Turnstile configuration.');
    try {
        $config = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Invalid private Turnstile JSON.');
    }
    return submission_turnstile_validate_config($config);
}

function submission_turnstile_validate_config(mixed $config): array
{
    $keys = ['base_url', 'hostname', 'action', 'widget_mode', 'pre_clearance', 'site_key', 'secret_key'];
    if (!is_array($config) || array_diff(array_keys($config), $keys) !== [] || array_diff($keys, array_keys($config)) !== []
        || $config['base_url'] !== 'https://booktoscreen.org/author-submissions/'
        || $config['hostname'] !== 'booktoscreen.org' || $config['action'] !== 'author_submission'
        || $config['widget_mode'] !== 'managed' || $config['pre_clearance'] !== false
        || !is_string($config['site_key']) || !is_string($config['secret_key'])) {
        throw new RuntimeException('Use the approved canonical URL, hostname/action and Managed widget without pre-clearance.');
    }
    foreach (['site_key', 'secret_key'] as $field) {
        if ($config[$field] !== '' && (!preg_match('/^[a-zA-Z0-9_-]{20,100}$/D', $config[$field])
            || in_array($config[$field], array_merge(SubmissionTurnstile::TEST_SITES, SubmissionTurnstile::TEST_SECRETS), true))) {
            throw new RuntimeException('Use real widget keys, never development keys or malformed values.');
        }
    }
    if ($config['site_key'] !== '' && $config['secret_key'] !== '') {
        $verifier = new SubmissionTurnstile('production', $config['site_key'], $config['secret_key'], $config['hostname'], $config['action']);
        $verifier->requireForBaseUrl($config['base_url']);
    }
    return $config;
}
