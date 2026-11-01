<?php

declare(strict_types=1);

require_once __DIR__ . '/turnstile-config.php';

function submission_challenge_settings(string $path): array
{
    $path = submission_private_config_path($path, false);
    if (!is_file($path) || filesize($path) > 8192) throw new RuntimeException('Invalid private challenge settings.');
    $contents = file_get_contents($path);
    if ($contents === false) throw new RuntimeException('Private challenge settings unavailable.');
    try {
        $settings = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Invalid private challenge settings.');
    }
    $keys = ['enabled', 'user', 'deadline', 'widget_config', 'sessions', 'budget'];
    if (!is_array($settings) || array_diff(array_keys($settings), $keys) !== []
        || array_diff($keys, array_keys($settings)) !== []
        || !is_bool($settings['enabled']) || !is_int($settings['deadline']) || $settings['deadline'] < 0
        || !is_string($settings['user']) || !preg_match('/^[a-zA-Z0-9_.@-]{1,100}$/D', $settings['user'])) {
        throw new RuntimeException('Use explicit challenge enable state, tester and fixed integer deadline.');
    }
    foreach (['widget_config', 'sessions', 'budget'] as $field) {
        if (!is_string($settings[$field]) || str_contains($settings[$field], "\0")
            || !preg_match('~^(?:/|[a-zA-Z]:[\\\\/])~', $settings[$field])) {
            throw new RuntimeException('Use absolute private challenge state paths.');
        }
    }
    return $settings;
}

function submission_challenge_gate(array $server, array $settings, int $now): bool
{
    return ($settings['enabled'] ?? false) === true
        && ($server['HTTPS'] ?? '') === 'on'
        && ($server['HTTP_HOST'] ?? '') === 'booktoscreen.org'
        && ($server['SCRIPT_NAME'] ?? '') === '/__turnstile-validation/index.php'
        && parse_url($server['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/__turnstile-validation/index.php'
        && is_string($settings['user'] ?? null) && $settings['user'] !== ''
        && ($server['REMOTE_USER'] ?? '') === $settings['user']
        && is_int($settings['deadline'] ?? null)
        && $settings['deadline'] > $now && $settings['deadline'] <= $now + 3600;
}

function submission_challenge_config(string $path): array
{
    $path = submission_private_config_path($path, false);
    if (!is_file($path) || filesize($path) > 8192) throw new RuntimeException('Invalid private challenge configuration.');
    $contents = file_get_contents($path);
    if ($contents === false) throw new RuntimeException('Private challenge configuration unavailable.');
    try {
        $config = submission_turnstile_validate_config(json_decode($contents, true, 8, JSON_THROW_ON_ERROR));
    } catch (JsonException $e) {
        throw new RuntimeException('Invalid private challenge configuration.');
    }
    if ($config['site_key'] === '' || $config['secret_key'] === '') throw new RuntimeException('Challenge keys are incomplete.');
    return $config;
}

function submission_challenge_budget(string $path): void
{
    $path = submission_private_config_path($path, false);
    if (!is_file($path) || filesize($path) > 32) throw new RuntimeException('Invalid private challenge budget.');
    $handle = fopen($path, 'r+');
    if ($handle === false) throw new RuntimeException('Challenge budget unavailable.');
    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('Challenge budget busy.');
        $count = stream_get_contents($handle, 33);
        if (!is_string($count) || !preg_match('/^(0|[1-9][0-9]?)$/D', $count)) {
            throw new RuntimeException('Invalid challenge budget state.');
        }
        if ((int) $count >= 12) throw new SubmissionRateException('The approved validation budget is exhausted. Stop the test.');
        $next = (string) ((int) $count + 1);
        if (!rewind($handle) || fwrite($handle, $next) !== strlen($next) || !ftruncate($handle, strlen($next)) || !fflush($handle)) {
            throw new RuntimeException('Could not reserve challenge attempt.');
        }
    } finally {
        fclose($handle);
    }
}

function submission_challenge_verify(SubmissionTurnstile $verifier, mixed $token, int $now, Closure $reserve): array
{
    if (!is_string($token) || $token === '' || strlen($token) > 2048 || preg_match('/[\x00-\x20\x7f]/', $token)) {
        return ['status' => 422, 'message' => 'Missing or malformed token rejected locally. No Siteverify call.'];
    }

    try {
        $reserve();
        $verifier->verify($token, $now);
        return ['status' => 200, 'message' => 'Provider accepted token; expected hostname, action and timestamp confirmed.'];
    } catch (SubmissionInputException $e) {
        return ['status' => 422, 'message' => 'Rejected: invalid, expired, spent or wrong-context token.'];
    } catch (SubmissionAntibotUnavailable $e) {
        return ['status' => 503, 'message' => 'Siteverify unavailable or configuration rejected. Stop; no automatic retry.'];
    } catch (SubmissionRateException $e) {
        return ['status' => 429, 'message' => 'Approved test budget exhausted. Stop the test.'];
    }
}

function submission_challenge_diagnostic_start(array &$state, mixed $token, string $type, int $deadline = 0): array
{
    $types = ['Fresh token', 'Reused token', 'Expired unused token', 'Missing token', 'Invalid token'];
    if (!in_array($type, $types, true)) throw new RuntimeException('Invalid diagnostic test type.');
    if (($state['diagnostic_deadline'] ?? null) !== $deadline) {
        unset($state['diagnostic_key'], $state['diagnostic_number'], $state['diagnostic_previous']);
        $state['diagnostic_deadline'] = $deadline;
    }
    $state['diagnostic_key'] ??= bin2hex(random_bytes(32));
    $state['diagnostic_number'] = ($state['diagnostic_number'] ?? 0) + 1;
    $valid = is_string($token) && $token !== '' && strlen($token) <= 2048
        && !preg_match('/[\x00-\x20\x7f]/', $token);
    $digest = $valid ? hash_hmac('sha256', $token, $state['diagnostic_key']) : null;
    return [
        'test_number' => $state['diagnostic_number'], 'test_type' => $type,
        'same_as_previous_siteverify_token' => $digest !== null && isset($state['diagnostic_previous'])
            ? hash_equals($state['diagnostic_previous'], $digest) : null,
        'token_id' => $digest, 'siteverify_made' => false, 'provider_http_status' => null,
        'success' => null, 'error_codes' => [], 'hostname_pass' => null,
        'action_pass' => null, 'timestamp_pass' => null, 'decision' => 'reject',
    ];
}
