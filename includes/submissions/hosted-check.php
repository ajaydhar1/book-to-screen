<?php

declare(strict_types=1);

require_once __DIR__ . '/hosted-staging.php';

function submission_hosted_prerequisites(array $server, string $pointer): array
{
    $checks = [
        'PHP runtime' => PHP_VERSION_ID >= 80200,
        'PHP extensions' => count(array_filter(['pdo', 'pdo_sqlite', 'sqlite3', 'curl', 'openssl', 'session', 'json', 'hash', 'mbstring'],
            static fn(string $name): bool => !extension_loaded($name))) === 0,
        'TLS stream support' => in_array('tls', stream_get_transports(), true),
        'Trusted HTTPS' => ($server['HTTPS'] ?? '') === 'on',
        'Canonical host' => ($server['HTTP_HOST'] ?? '') === 'booktoscreen.org',
        'Disabled private settings' => false, 'Authenticated tester' => false,
        'Private configuration compatibility' => false, 'Preserved baseline' => false,
        'Fictional reviewer fixture' => false, 'Private sessions writable' => false,
    ];
    try {
        $settings = submission_hosted_settings($pointer);
        $checks['Disabled private settings'] = $settings['enabled'] === false
            && $settings['deadline'] === 0 && $settings['writers_stopped'] === false;
        $checks['Authenticated tester'] = ($server['REMOTE_USER'] ?? '') === $settings['user'];
        if (!$checks['Disabled private settings'] || !$checks['Authenticated tester']
            || !$checks['Trusted HTTPS'] || !$checks['Canonical host']) return $checks;
        $config = submission_hosted_config($settings);
        $checks['Private configuration compatibility'] = true;
        $crypto = new SubmissionCrypto(submission_browser_key($config));
        $baseline = submission_private_config_path($config['directory'] . DIRECTORY_SEPARATOR . 'browser-established.json', false);
        $proof = json_decode(file_get_contents($baseline), true, 4, JSON_THROW_ON_ERROR);
        $checks['Preserved baseline'] = is_string($proof['proof'] ?? null)
            && hash_equals($crypto->hash('browser-baseline', 'live|' . $config['baseline_base_url']), $proof['proof']);
        $staff = new SQLite3($config['staff_db'], SQLITE3_OPEN_READONLY);
        try {
            $query = $staff->query('SELECT id,username,display_name,role,is_active,must_change_password FROM users ORDER BY id LIMIT 2');
            $user = $query->fetchArray(SQLITE3_ASSOC);
            $checks['Fictional reviewer fixture'] = $user !== false && $query->fetchArray(SQLITE3_ASSOC) === false
                && (int) $user['id'] === 900001 && $user['username'] === 'staging-reviewer'
                && $user['display_name'] === 'Synthetic Staging Reviewer' && $user['role'] === 'admin'
                && (int) $user['is_active'] === 1 && (int) $user['must_change_password'] === 0
                && $staff->querySingle('PRAGMA integrity_check') === 'ok';
            $query->finalize();
        } finally { $staff->close(); }
        $checks['Private sessions writable'] = is_writable($config['directory'] . DIRECTORY_SEPARATOR . 'sessions');
    } catch (Throwable) {
        error_log('Hosted prerequisite check failed; private details suppressed.');
    }
    return $checks;
}

function submission_hosted_connectivity_readonly(array $config, ?\SQLiteCloud\SQLiteCloudClient $mock = null): array
{
    $manifest = submission_staging_manifest($config['identity_manifest']);
    return submission_staging_session($manifest, $config['hosted_connection'],
        static function ($client) use ($manifest, $config): array {
            $inspection = submission_staging_inspect_connected($manifest, $config['hosted_connection'], $client, true);
            return ['TLS staging connection' => true,
                'Selected staging database' => $inspection['selected_database_confirmed'] === true,
                'Expected database user' => $inspection['authenticated_user_confirmed'] === true,
                'Read-only settings inspection' => true];
        }, $mock);
}

function submission_hosted_reserve_connectivity_attempt(array $config): void
{
    $handle = fopen($config['directory'] . DIRECTORY_SEPARATOR . 'connectivity-attempted.flag', 'x');
    if ($handle === false) throw new RuntimeException('Already attempted; stop without retry.');
    try {
        if (DIRECTORY_SEPARATOR !== '\\' && !chmod($config['directory'] . DIRECTORY_SEPARATOR . 'connectivity-attempted.flag', 0600)) {
            throw new RuntimeException('Private marker restriction failed.');
        }
    } finally { fclose($handle); }
}

function submission_hosted_browser_connectivity_settings(array $server, string $pointer, int $now): array
{
    $settings = submission_hosted_settings($pointer);
    if ($settings['enabled'] !== false || $settings['deadline'] !== 0 || $settings['writers_stopped'] !== false
        || ($server['REMOTE_USER'] ?? '') !== $settings['user']
        || ($server['HTTPS'] ?? '') !== 'on' || ($server['HTTP_HOST'] ?? '') !== 'booktoscreen.org') {
        throw new RuntimeException('Disabled authenticated HTTPS boundary required.');
    }
    $path = submission_private_config_path($settings['directory'] . DIRECTORY_SEPARATOR . 'connectivity-approval.json', false);
    if (!is_file($path) || filesize($path) > 1024) throw new RuntimeException('Invalid private approval.');
    $approval = json_decode(file_get_contents($path), true, 4, JSON_THROW_ON_ERROR);
    if (!is_array($approval) || array_diff(array_keys($approval), ['approved', 'deadline']) !== []
        || array_diff(['approved', 'deadline'], array_keys($approval)) !== []
        || $approval['approved'] !== true || !is_int($approval['deadline'])
        || $approval['deadline'] <= $now || $approval['deadline'] > $now + 900) {
        throw new RuntimeException('Separate short-lived approval required.');
    }
    $marker = $settings['directory'] . DIRECTORY_SEPARATOR . 'connectivity-attempted.flag';
    if (file_exists($marker) || is_link($marker)) throw new RuntimeException('Attempt already reserved.');
    return $settings;
}

function submission_hosted_browser_connectivity_attempt(
    array $server, array $post, string $pointer, int $now, ?\SQLiteCloud\SQLiteCloudClient $mock = null
): array {
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($server['HTTP_ORIGIN'] ?? '') !== 'https://booktoscreen.org'
        || (($server['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin')
        || array_keys($post) !== ['confirmation']
        || $post['confirmation'] !== 'APPROVE ONE STAGING READ ONLY CONNECTION') {
        throw new RuntimeException('Explicit same-origin POST authorization required.');
    }
    $settings = submission_hosted_browser_connectivity_settings($server, $pointer, $now);
    $config = submission_hosted_config($settings);
    submission_hosted_reserve_connectivity_attempt($config);
    return submission_hosted_connectivity_readonly($config, $mock);
}

function submission_hosted_file_diagnostics(array $server, string $pointer): array
{
    $result = [];
    $settings = submission_hosted_settings($pointer);
    if ($settings['enabled'] !== false || $settings['deadline'] !== 0 || $settings['writers_stopped'] !== false
        || ($server['REMOTE_USER'] ?? '') !== $settings['user']
        || ($server['HTTPS'] ?? '') !== 'on' || ($server['HTTP_HOST'] ?? '') !== 'booktoscreen.org') {
        return ['Diagnostic boundary' => 'FAIL'];
    }
    $paths = array_intersect_key($settings, array_flip(['key_file', 'staff_db', 'identity_manifest', 'connection_file', 'widget_config']));
    $paths['baseline'] = $settings['directory'] . DIRECTORY_SEPARATOR . 'browser-established.json';
    $paths['sessions'] = $settings['directory'] . DIRECTORY_SEPARATOR . 'sessions';
    foreach ($paths as $label => $path) {
        $blocked = false;
        set_error_handler(static function (int $severity, string $message) use (&$blocked): bool {
            if (str_contains($message, 'open_basedir restriction')) $blocked = true;
            return true;
        });
        try {
            clearstatcache(true, $path);
            $resolved = realpath($path);
            $exists = $label === 'sessions' ? is_dir($path) : is_file($path);
            $readable = is_readable($path);
            $permissions = fileperms($path);
            $link = is_link($path);
            $owner = fileowner($path);
            $writable = $label === 'sessions' ? is_writable($path) : null;
        } finally { restore_error_handler(); }
        $result[$label . ' / open_basedir access'] = $blocked ? 'FAIL' : 'PASS';
        $result[$label . ' / expected object exists'] = $exists ? 'PASS' : 'FAIL';
        $result[$label . ' / PHP readable'] = $readable ? 'PASS' : 'FAIL';
        $result[$label . ' / restrictive permissions'] = $permissions === false ? 'NOT CHECKED'
            : (DIRECTORY_SEPARATOR === '\\' ? 'NOT CHECKED' : (($permissions & 0077) === 0 ? 'PASS' : 'FAIL'));
        $result[$label . ' / same PHP owner'] = function_exists('posix_geteuid') && $owner !== false
            ? ($owner === posix_geteuid() ? 'PASS' : 'FAIL') : 'NOT CHECKED';
        $result[$label . ' / no symlink'] = $resolved === false ? 'NOT CHECKED' : ($link ? 'FAIL' : 'PASS');
        if ($writable !== null) $result[$label . ' / PHP writable'] = $writable ? 'PASS' : 'FAIL';
        if ($exists && $readable && !$blocked) {
            try {
                submission_private_config_path($path, false);
                $result[$label . ' / private path policy'] = 'PASS';
            } catch (Throwable) {
                $result[$label . ' / private path policy'] = 'FAIL';
            }
        } else $result[$label . ' / private path policy'] = 'NOT CHECKED';
    }
    $stages = ['Private path: key_file', 'Private path: staff_db', 'Private path: identity_manifest',
        'Private path: connection_file', 'Private path: widget_config', 'Manifest format and staging identity',
        'Connection format and target', 'Widget format and compatibility', 'Private sessions path',
        'Key JSON and key format', 'Existing private state: submissions-mailbox.jsonl',
        'Existing private state: submissions-isbn-cache.json', 'Existing private state: browser-established.json',
        'Existing private state: browser-stopped.flag'];
    foreach ($stages as $stage) $result['Loader / ' . $stage] = 'NOT CHECKED';
    $current = null;
    try {
        submission_hosted_config($settings, static function (string $stage) use (&$result, &$current, $stages): void {
            if (!in_array($stage, $stages, true)) throw new RuntimeException('Unknown diagnostic stage.');
            if ($current !== null) $result['Loader / ' . $current] = 'PASS';
            $current = $stage;
        });
        if ($current !== null) $result['Loader / ' . $current] = 'PASS';
    } catch (Throwable) {
        if ($current !== null) $result['Loader / ' . $current] = 'FAIL';
    }
    if ($result['identity_manifest / private path policy'] === 'PASS') {
        $result = array_merge($result, submission_hosted_manifest_diagnostics($settings['identity_manifest']));
    }
    return $result;
}

function submission_hosted_manifest_diagnostics(string $path): array
{
    $labels = ['Size within 16 KiB', 'UTF-8 without BOM', 'JSON syntax and depth',
        'Top-level field structure', 'Nested identity field structure', 'Staging identity validation',
        'Pinned project', 'Pinned hostname', 'Integer pinned port', 'Pinned database',
        'Diagnostic declarations', 'Approved staging scope', 'Migration versions',
        'Migration 1 exact checksum', 'Migration 2 exact checksum',
        'Migration 1 checksum after CRLF conversion', 'Migration 2 checksum after CRLF conversion',
        'Migration 1 checksum with restored statement CRLF',
        'Original manifest loader', 'Complete migration plan'];
    $result = array_fill_keys(array_map(static fn(string $label): string => 'Manifest / ' . $label, $labels), 'NOT CHECKED');
    $check = static function (string $label, bool $passed) use (&$result): void {
        $result['Manifest / ' . $label] = $passed ? 'PASS' : 'FAIL';
    };
    try {
        $path = submission_private_config_path($path, false);
        $size = filesize($path);
        $check('Size within 16 KiB', $size !== false && $size <= 16384);
        if ($size === false || $size > 16384) return $result;
        $json = file_get_contents($path);
        if ($json === false) throw new RuntimeException('Manifest diagnostic read failed.');
        $check('UTF-8 without BOM', mb_check_encoding($json, 'UTF-8') && !str_starts_with($json, "\xEF\xBB\xBF"));
        try {
            $manifest = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
            $check('JSON syntax and depth', true);
        } catch (JsonException) {
            $check('JSON syntax and depth', false);
            return $result;
        }
        $fields = ['identity', 'project_name', 'control_panel_identity_confirmed', 'credential_mode', 'expected_user', 'admin_risk_approved'];
        $shape = is_array($manifest) && array_diff(array_keys($manifest), $fields) === []
            && array_diff($fields, array_keys($manifest)) === [] && is_array($manifest['identity']);
        $check('Top-level field structure', $shape);
        if (!$shape) return $result;
        $identity = $manifest['identity'];
        $fields = ['environment', 'project_id', 'hostname', 'port', 'database',
            'production_project_id', 'production_hostname', 'production_database', 'synthetic_only'];
        $check('Nested identity field structure', array_diff(array_keys($identity), $fields) === []
            && array_diff($fields, array_keys($identity)) === []);
        try { submission_staging_plan($identity); $check('Staging identity validation', true); }
        catch (Throwable) { $check('Staging identity validation', false); }
        $check('Pinned project', ($identity['project_id'] ?? null) === 'cutaojpqvz');
        $check('Pinned hostname', ($identity['hostname'] ?? null) === 'cutaojpqvz.g2.sqlite.cloud');
        $check('Integer pinned port', ($identity['port'] ?? null) === 8860);
        $check('Pinned database', ($identity['database'] ?? null) === 'author-submissions-staging.sqlite');
        try { submission_staging_diagnostic_config($manifest); $check('Diagnostic declarations', true); }
        catch (Throwable) { $check('Diagnostic declarations', false); }
        $check('Approved staging scope', $manifest['expected_user'] === 'admin'
            && $manifest['credential_mode'] === 'staging-admin' && $manifest['admin_risk_approved'] === true);
        $migrations = submission_migrations();
        $check('Migration versions', array_keys($migrations) === [1, 2]);
        foreach ([1, 2] as $version) {
            if (!isset($migrations[$version])) continue;
            $sql = implode("\n", $migrations[$version]);
            $check('Migration ' . $version . ' exact checksum',
                hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', $sql)));
            $check('Migration ' . $version . ' checksum after CRLF conversion',
                hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', str_replace("\r\n", "\n", $sql))));
            if ($version === 1) {
                $restored = array_map(static fn(string $statement): string =>
                    str_replace("\n", "\r\n", str_replace("\r\n", "\n", $statement)), $migrations[$version]);
                $check('Migration 1 checksum with restored statement CRLF',
                    hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[1], hash('sha256', implode("\n", $restored))));
            }
        }
        try { submission_staging_manifest($path); $check('Original manifest loader', true); }
        catch (Throwable) { $check('Original manifest loader', false); }
        try { submission_staging_migration_plan($manifest); $check('Complete migration plan', true); }
        catch (Throwable) { $check('Complete migration plan', false); }
    } catch (Throwable) {
        error_log('Hosted manifest diagnostic failed; private details suppressed.');
        return ['Manifest / Diagnostic execution' => 'FAIL'] + $result;
    }
    return $result;
}
