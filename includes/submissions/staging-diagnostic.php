<?php

declare(strict_types=1);

require_once __DIR__ . '/staging.php';

function submission_staging_diagnostic_config(array $manifest): array
{
    $fields = ['identity', 'project_name', 'control_panel_identity_confirmed',
        'credential_mode', 'expected_user', 'admin_risk_approved'];
    if (array_diff(array_keys($manifest), $fields) !== [] || array_diff($fields, array_keys($manifest)) !== []
        || !is_array($manifest['identity'])) {
        throw new InvalidArgumentException('Supply exactly the documented nonsecret diagnostic manifest fields.');
    }
    $plan = submission_staging_plan($manifest['identity']);
    if ($manifest['project_name'] !== 'book-to-screen-staging'
        || $manifest['identity']['database'] !== 'author-submissions-staging.sqlite'
        || $manifest['control_panel_identity_confirmed'] !== true) {
        throw new InvalidArgumentException('Confirm the exact approved staging project, endpoint mapping and database in the control panel first.');
    }
    if (!in_array($manifest['credential_mode'], ['restricted-user', 'staging-admin'], true)
        || !is_bool($manifest['admin_risk_approved'])
        || !is_string($manifest['expected_user'])
        || preg_match('/^[A-Za-z0-9_.@-]{1,128}$/D', $manifest['expected_user']) !== 1) {
        throw new InvalidArgumentException('Declare the credential mode, expected database user and admin-risk decision.');
    }
    if ($manifest['credential_mode'] === 'staging-admin' && !$manifest['admin_risk_approved']) {
        throw new InvalidArgumentException('A staging admin credential requires explicit acknowledgement of its write-capable scope.');
    }
    if ($manifest['credential_mode'] === 'restricted-user' && strtolower($manifest['expected_user']) === 'admin') {
        throw new InvalidArgumentException('An admin user cannot be declared restricted.');
    }
    return [
        'target' => $plan['target'], 'project_name' => $manifest['project_name'],
        'expected_user' => $manifest['expected_user'], 'credential_mode' => $manifest['credential_mode'],
        'read_commands' => [
            'GET DATABASE NAME', 'GET USER', 'PRAGMA database_list',
            'SELECT sqlite_version() AS sqlite_version', 'PRAGMA foreign_keys', 'PRAGMA query_only', 'PRAGMA journal_mode',
            'SELECT type,name,tbl_name,sql FROM main.sqlite_schema ORDER BY type,name LIMIT 101',
        ],
        'connection_opened' => false, 'writes_authorized' => false,
        'limitations' => [
            'Project-to-endpoint ownership is operator-confirmed, not proven by a SQL project ID.',
            'Effective credential permissions are not tested; no attempted writes or grants inspection.',
            'GET DATABASE NAME requires HOSTADMIN according to the current provider documentation.',
        ],
    ];
}

function submission_staging_diagnostic_auth(array $config, #[SensitiveParameter] string $url): array
{
    if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f]/', $url)) {
        throw new InvalidArgumentException('Supply the dedicated staging URL privately; whitespace is not allowed.');
    }
    $parts = parse_url($url);
    $target = $config['target'];
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'sqlitecloud'
        || ($parts['host'] ?? '') !== $target['hostname']
        || ($parts['port'] ?? 8860) !== $target['port']
        || ($parts['path'] ?? '') !== '/' . $target['database'] || isset($parts['fragment'])) {
        throw new InvalidArgumentException('The private URL must match the approved staging host, port and exact database path.');
    }
    $key = '';
    if (isset($parts['query'])) {
        // Do not pass arbitrary SDK options through its permissive URL parser.
        if (preg_match('/^apikey=([^&;]+)$/D', $parts['query'], $match) !== 1) {
            throw new InvalidArgumentException('Only a single database-connection apikey option is supported; no SDK options.');
        }
        $key = rawurldecode($match[1]);
    }
    $username = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $password = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    $tokenPattern = '/^[A-Za-z0-9._~!$*+@\/=-]{1,512}$/D';
    if ($key !== '') {
        if (isset($parts['user']) || isset($parts['pass']) || $config['credential_mode'] !== 'staging-admin'
            || strlen($key) < 16 || preg_match($tokenPattern, $key) !== 1) {
            throw new InvalidArgumentException('A database API key is supported only as the acknowledged staging-admin credential, without userinfo.');
        }
    } elseif (isset($parts['query']) || $username !== $config['expected_user']
        || $password === '0' || preg_match($tokenPattern, $password) !== 1) {
        throw new InvalidArgumentException('Use the expected database username and a protocol-safe percent-encoded password, without query options.');
    }
    return ['username' => $username, 'password' => $password, 'apikey' => $key];
}

function submission_staging_diagnostic_approval(string $mode, string $approval): void
{
    if ($mode !== '--connect-readonly'
        || $approval !== 'book-to-screen-staging/author-submissions-staging.sqlite/read-only') {
        throw new InvalidArgumentException('A separately approved live read-only run requires the exact CLI flag and dedicated approval environment value.');
    }
}

function submission_staging_vector_metadata_matches(array $objects): bool
{
    $tables = array_values(array_filter($objects, static fn(array $object): bool => $object['name'] === '_sqliteai_vector'));
    $indexes = array_values(array_filter($objects, static fn(array $object): bool => $object['name'] === 'sqlite_autoindex__sqliteai_vector_1'));
    if (count($tables) !== 1 || count($indexes) !== 1) return false;
    $table = $tables[0];
    $index = $indexes[0];
    // Match the complete documented definition, not an object-name prefix or partial SQL.
    $definition = '/\A\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?_sqliteai_vector\s*\(\s*'
        . 'tblname\s+TEXT\s*,\s*colname\s+TEXT\s*,\s*key\s+TEXT\s*,\s*value\s+ANY\s*,\s*'
        . 'PRIMARY\s+KEY\s*\(\s*tblname\s*,\s*colname\s*,\s*key\s*\)\s*\)\s*;?\s*\z/i';
    return $table['type'] === 'table' && $table['tbl_name'] === '_sqliteai_vector'
        && is_string($table['sql']) && preg_match($definition, $table['sql']) === 1
        && $index['type'] === 'index' && $index['tbl_name'] === '_sqliteai_vector'
        && array_key_exists('sql', $index) && $index['sql'] === null;
}

function submission_staging_diagnostic_run(
    array $manifest,
    #[SensitiveParameter] string $url,
    ?\SQLiteCloud\SQLiteCloudClient $client = null
): array {
    return submission_staging_session($manifest, $url,
        static fn(\SQLiteCloud\SQLiteCloudClient $connected): array => submission_staging_inspect_connected($manifest, $url, $connected),
        $client);
}

function submission_staging_session(
    array $manifest,
    #[SensitiveParameter] string $url,
    callable $operation,
    ?\SQLiteCloud\SQLiteCloudClient $client = null
): array {
    $config = submission_staging_diagnostic_config($manifest);
    $auth = submission_staging_diagnostic_auth($config, $url);
    // Configure public SDK properties directly: connectWithString can enable writes/settings via URL options.
    require_once __DIR__ . '/../../vendor/autoload.php';
    $client ??= new \SQLiteCloud\SQLiteCloudClient();
    $phase = 'connection';
    set_error_handler(static function (): never {
        throw new RuntimeException('Staging transport warning; provider details suppressed.');
    });
    try {
        foreach ($auth as $field => $value) $client->{$field} = $value;
        $client->database = $config['target']['database'];
        $client->connect_timeout = 5;
        $client->timeout = 10;
        $client->password_hashed = false;
        $client->create = $client->memory = $client->insecure = $client->no_verify_certificate = false;
        $client->non_linearizable = $client->compression = $client->zerotext = $client->noblob = false;
        $client->maxdata = $client->maxrows = $client->maxrowset = 0;
        $client->tls_root_certificate = $client->tls_certificate = $client->tls_certificate_key = null;
        if ($client->connect($config['target']['hostname'], $config['target']['port']) !== true) {
            throw new RuntimeException('Connection rejected.');
        }
        $phase = 'approved staging operation';
        return $operation($client);
    } catch (Throwable) {
        throw new RuntimeException('Staging operation failed during ' . $phase . '; provider details suppressed. Stop; do not retry automatically.');
    } finally {
        try {
            $client->disconnect();
        } catch (Throwable) {
            throw new RuntimeException('Staging disconnect failed; provider details suppressed.');
        } finally {
            restore_error_handler();
        }
    }
}

function submission_staging_inspect_connected(
    array $manifest,
    #[SensitiveParameter] string $url,
    \SQLiteCloud\SQLiteCloudClient $client,
    bool $identityAndSettingsOnly = false
): array {
    $config = submission_staging_diagnostic_config($manifest);
    $auth = submission_staging_diagnostic_auth($config, $url);
    $phase = 'database identity';
    try {
        $phase = 'database identity';
        if ($client->execute('GET DATABASE NAME') !== $config['target']['database']) {
            throw new RuntimeException('Selected database not confirmed.');
        }
        if ($client->execute('GET USER') !== $config['expected_user']) {
            throw new RuntimeException('Authenticated user not confirmed.');
        }
        $rows = static function (string $query, array $columns) use ($client): array {
            $result = $client->execute($query);
            if (!$result instanceof \SQLiteCloud\SQLiteCloudRowset
                || !is_int($result->ncols) || $result->ncols !== count($columns) || $result->colname !== $columns
                || !is_int($result->nrows) || $result->nrows < 0 || $result->nrows > 101 || !is_array($result->data)
                || count($result->data) !== $result->ncols * $result->nrows) {
                throw new RuntimeException('Unexpected read-only result shape.');
            }
            $rows = [];
            for ($r = 0; $r < $result->nrows; $r++) {
                $row = [];
                for ($c = 0; $c < $result->ncols; $c++) $row[$result->name($c)] = $result->value($r, $c);
                $rows[] = $row;
            }
            return $rows;
        };
        $databases = $rows('PRAGMA database_list', ['seq','name','file']);
        $main = array_values(array_filter($databases, static fn(array $row): bool => ($row['name'] ?? null) === 'main'));
        if (count($main) !== 1 || !is_string($main[0]['file'] ?? null)
            || basename(str_replace('\\', '/', $main[0]['file'])) !== $config['target']['database']
            || array_filter($databases, static fn(array $row): bool => !in_array($row['name'] ?? null, ['main','temp'], true))) {
            throw new RuntimeException('Database list is ambiguous, has attachments, or does not match the expected name.');
        }
        $phase = 'SQLite settings';
        $scalar = static function (string $query, string $column) use ($rows): mixed {
            $result = $rows($query, [$column]);
            if (count($result) !== 1 || !array_key_exists($column, $result[0])) {
                throw new RuntimeException('Missing read-only scalar result.');
            }
            return $result[0][$column];
        };
        $version = $scalar('SELECT sqlite_version() AS sqlite_version', 'sqlite_version');
        $foreignKeys = $scalar('PRAGMA foreign_keys', 'foreign_keys');
        $queryOnly = $scalar('PRAGMA query_only', 'query_only');
        $journal = $scalar('PRAGMA journal_mode', 'journal_mode');
        if (!is_string($version) || preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1
            || !in_array($foreignKeys, [0,1,'0','1'], true) || !in_array($queryOnly, [0,1,'0','1'], true)
            || !in_array($journal, ['delete','truncate','persist','memory','wal','off'], true)) {
            throw new RuntimeException('SQLite settings response not understood.');
        }
        if ($identityAndSettingsOnly) {
            return ['selected_database_confirmed' => true, 'authenticated_user_confirmed' => true,
                'foreign_keys' => (int) $foreignKeys, 'query_only' => (int) $queryOnly,
                'sqlite_version' => $version, 'journal_mode' => $journal];
        }
        $phase = 'schema inspection';
        $objects = $rows($config['read_commands'][7], ['type','name','tbl_name','sql']);
        if (count($objects) > 100) throw new RuntimeException('Unexpected schema exceeds the diagnostic limit.');
        $schema = [];
        foreach ($objects as $object) {
            if (!in_array($object['type'] ?? null, ['table','index','view','trigger'], true)
                || !is_string($object['name'] ?? null) || !is_string($object['tbl_name'] ?? null)
                || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}$/D', $object['name']) !== 1
                || preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}$/D', $object['tbl_name']) !== 1
                || !array_key_exists('sql', $object)
                || (!is_string($object['sql']) && $object['sql'] !== null)) {
                throw new RuntimeException('Unexpected schema metadata; contents suppressed.');
            }
            foreach ([$auth['apikey'], $auth['password']] as $secret) {
                if ($secret !== '' && (str_contains($object['name'], $secret) || str_contains($object['tbl_name'], $secret))) {
                    throw new RuntimeException('Sensitive schema metadata; contents suppressed.');
                }
            }
            $entry = ['type' => $object['type'], 'name' => $object['name'], 'has_records' => null,
                'classification' => 'unexpected_schema'];
            // Never execute views or virtual tables; their definitions may call extensions/functions.
            if ($object['type'] === 'table' && !str_starts_with($object['name'], 'sqlite_')
                && preg_match('/^\s*CREATE\s+TABLE\b/i', $object['sql'] ?? '') === 1) {
                $table = '"' . $object['name'] . '"';
                $hasRecords = $scalar('SELECT EXISTS(SELECT 1 FROM main.' . $table . ' LIMIT 1) AS has_records', 'has_records');
                if (!in_array($hasRecords, [0,1,'0','1'], true)) throw new RuntimeException('Record-presence response not understood.');
                $entry['has_records'] = (bool) $hasRecords;
            }
            $schema[] = $entry;
        }
        $vectorRows = array_values(array_filter($schema, static fn(array $entry): bool => $entry['name'] === '_sqliteai_vector'));
        $knownVector = submission_staging_vector_metadata_matches($objects)
            && count($vectorRows) === 1 && $vectorRows[0]['has_records'] === false;
        if ($knownVector) {
            foreach ($schema as &$entry) {
                if (in_array($entry['name'], ['_sqliteai_vector','sqlite_autoindex__sqliteai_vector_1'], true)) {
                    $entry['classification'] = 'known_extension_metadata';
                }
            }
            unset($entry);
        }
        $noApplicationSchema = !array_filter($schema, static fn(array $entry): bool => $entry['classification'] !== 'known_extension_metadata');
        return [
            'status' => $noApplicationSchema ? 'READ_ONLY_CHECKS_COMPLETE' : 'STOP_UNEXPECTED_SCHEMA',
            'project_name' => $config['project_name'], 'database' => $config['target']['database'],
            'selected_database_confirmed' => true, 'authenticated_user_confirmed' => true,
            'project_identity_source' => 'operator-confirmed control-panel endpoint mapping plus TLS hostname',
            'isolation_programmatically_proven' => false, 'credential_permissions_verified' => false,
            'credential_mode' => $config['credential_mode'], 'writes_authorized' => false,
            'sqlite_version' => $version, 'foreign_keys' => (int) $foreignKeys,
            'query_only' => (int) $queryOnly, 'journal_mode' => $journal,
            'appears_empty' => $schema === [], 'no_application_schema_data' => $noApplicationSchema,
            'schema_assessment' => $noApplicationSchema ? 'No application schema/data' : 'Unexpected schema or records; stop',
            'schema_objects' => $schema,
            'warnings' => array_values(array_filter([
                $config['credential_mode'] === 'staging-admin' ? 'Admin credential is write-capable; this diagnostic does not enforce server-side read-only grants.' : 'Restricted grants must be confirmed separately with the provider.',
                (int) $foreignKeys === 0 ? 'Foreign keys are OFF; write validation must not proceed.' : null,
                (int) $queryOnly === 0 ? 'Session query_only is OFF; no setting was modified.' : null,
                !$noApplicationSchema ? 'Expected no application schema/data, except the exact empty SQLite-Vector metadata pair. No cleanup attempted.' : null,
            ])),
        ];
    } catch (Throwable) {
        // Transport exceptions/warnings can contain credentials or server data; expose only the fixed phase.
        throw new RuntimeException('Read-only staging diagnostic failed during ' . $phase . '; no writes attempted. Provider details suppressed.');
    }
}
