<?php

declare(strict_types=1);

require_once __DIR__ . '/staging-diagnostic.php';
require_once __DIR__ . '/../database/SQLiteCloudDatabase.php';

const SUBMISSION_STAGING_APPROVED_CHECKSUMS = [
    1 => 'bab034d1f5038391a10043aac96cb67ddb453cfb75f0f6c5af71078c3f007269',
    2 => '12232008e1ebec93807896aa89e7b652473e59063cbe54b63caee6a16855e3ef',
];

function submission_staging_migration_plan(array $manifest): array
{
    $config = submission_staging_diagnostic_config($manifest);
    if ($config['target']['project_id'] !== 'cutaojpqvz' || $config['target']['hostname'] !== 'cutaojpqvz.g2.sqlite.cloud'
        || $config['target']['port'] !== 8860 || $config['expected_user'] !== 'admin'
        || $config['credential_mode'] !== 'staging-admin') {
        throw new InvalidArgumentException('Only the exact approved staging project, endpoint, database and acknowledged admin are supported.');
    }
    $migrations = submission_migrations();
    if (array_keys($migrations) !== [1,2]) throw new RuntimeException('Unexpected migration versions; stop for review.');
    foreach ($migrations as $version => $sql) {
        if (!hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', implode("\n", $sql)))) {
            throw new RuntimeException('Migration checksum differs from the reviewed approval; stop.');
        }
    }
    return ['target'=>$config['target'],'checksums'=>SUBMISSION_STAGING_APPROVED_CHECKSUMS,
        'operation'=>'empty-install-v1-v2','connection_opened'=>false,'writes_authorized'=>false];
}

function submission_staging_migration_approval(string $flag, string $approval): void
{
    if ($flag !== '--apply-empty-v1-v2'
        || $approval !== 'cutaojpqvz/author-submissions-staging.sqlite/empty-install-v1-v2') {
        throw new InvalidArgumentException('The exact empty-install CLI flag and separate migration approval environment value are required.');
    }
}

function submission_staging_schema_sql(string $sql): string
{
    // Preserve string literals; normalize only SQL whitespace, keyword case and identifier quoting.
    preg_match_all('/\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|[A-Za-z_][A-Za-z0-9_]*|[0-9]+|[^\s;]/', $sql, $matches);
    return implode(' ', array_map(static function (string $token): string {
        if (str_starts_with($token, "'")) return $token;
        if (str_starts_with($token, '"')) $token = str_replace('""', '"', substr($token,1,-1));
        return strtolower($token);
    }, $matches[0]));
}

function submission_staging_expected_schema(): array
{
    $ledger = 'CREATE TABLE submission_schema_versions (version INTEGER PRIMARY KEY, checksum TEXT NOT NULL, applied_at INTEGER NOT NULL)';
    $sql = [$ledger, 'CREATE TABLE sqlite_sequence(name,seq)'];
    $migrations = submission_migrations();
    foreach (array_merge($migrations[1], $migrations[2]) as $statement) {
        if (!str_starts_with($statement, 'CREATE ')) continue;
        $sql[] = str_replace('property_submissions_v2', 'property_submissions',
            str_replace(['submission_email_tokens_v2','submission_events_v2'], ['submission_email_tokens','submission_events'], $statement));
    }
    $expected = [];
    foreach ($sql as $statement) {
        if (preg_match('/^CREATE (TABLE|INDEX) ([A-Za-z_][A-Za-z0-9_]*)/', $statement, $match) !== 1) {
            throw new RuntimeException('Unrecognized approved schema declaration.');
        }
        $type = strtolower($match[1]);
        $owner = $match[2];
        if ($type === 'index') {
            if (preg_match('/ ON ([A-Za-z_][A-Za-z0-9_]*)/', $statement, $on) !== 1) throw new RuntimeException('Missing index owner.');
            $owner = $on[1];
        }
        $expected[$match[2]] = ['type'=>$type,'name'=>$match[2],'tbl_name'=>$owner,'sql'=>submission_staging_schema_sql($statement)];
    }
    foreach (['property_submissions'=>3,'submission_email_tokens'=>1,'submission_rate_limits'=>1,'submission_genres'=>1] as $table=>$count) {
        for ($i=1;$i<=$count;$i++) {
            $name='sqlite_autoindex_'.$table.'_'.$i;
            $expected[$name]=['type'=>'index','name'=>$name,'tbl_name'=>$table,'sql'=>null];
        }
    }
    ksort($expected);
    return $expected;
}

function submission_staging_migration_rows(SQLiteCloudDatabase $db, string $sql, array $columns, int $limit = 100): array
{
    $result=$db->executeRaw($sql);
    if (!$result instanceof \SQLiteCloud\SQLiteCloudRowset || $result->colname!==$columns
        || !is_int($result->nrows) || $result->nrows<0 || $result->nrows>$limit
        || !is_int($result->ncols) || $result->ncols!==count($columns)
        || !is_array($result->data) || count($result->data)!==$result->nrows*$result->ncols) {
        throw new RuntimeException('Unexpected staging read response shape.');
    }
    $rows=[];
    for ($r=0;$r<$result->nrows;$r++) {
        $row=[];
        foreach ($columns as $c=>$name) $row[$name]=$result->value($r,$c);
        $rows[]=$row;
    }
    return $rows;
}

function submission_staging_migration_schema(SQLiteCloudDatabase $db): array
{
    $rows = submission_staging_migration_rows($db,
        'SELECT type,name,tbl_name,sql FROM main.sqlite_schema ORDER BY type,name LIMIT 101', ['type','name','tbl_name','sql']);
    $schema=[];
    foreach ($rows as $row) {
        if (!is_string($row['name']) || isset($schema[$row['name']]) || !in_array($row['type'],['table','index','view','trigger'],true)
            || !is_string($row['tbl_name']) || (!is_string($row['sql']) && $row['sql']!==null)) {
            throw new RuntimeException('Unexpected schema metadata.');
        }
        $schema[$row['name']]=$row;
    }
    ksort($schema);
    return $schema;
}

function submission_staging_verify_install(SQLiteCloudDatabase $db, array $vector, bool $requireEmpty = true, ?array $schemaSnapshot = null): array
{
    $schema=$schemaSnapshot ?? submission_staging_migration_schema($db);
    foreach ($vector as $name=>$object) {
        if (($schema[$name]??null)!==$object) throw new RuntimeException('Extension metadata changed; stop.');
        unset($schema[$name]);
    }
    foreach ($schema as &$object) {
        if ($object['sql']!==null) $object['sql']=submission_staging_schema_sql($object['sql']);
    }
    unset($object);
    if ($schema!==submission_staging_expected_schema()) throw new RuntimeException('Installed tables/indexes/constraints differ from approved definitions.');
    $ledger=submission_staging_migration_rows($db, 'SELECT version,checksum,applied_at FROM submission_schema_versions ORDER BY version',
        ['version','checksum','applied_at'],2);
    if (count($ledger)!==2) throw new RuntimeException('Unexpected migration ledger.');
    foreach ($ledger as $i=>$row) {
        if (!in_array($row['version'],[$i+1,(string)($i+1)],true)
            || $row['checksum']!==SUBMISSION_STAGING_APPROVED_CHECKSUMS[$i+1]
            || !is_numeric($row['applied_at']) || (int)$row['applied_at']<=0) throw new RuntimeException('Invalid migration ledger.');
    }
    $counts=[];
    $tables=['property_submissions','submission_email_tokens','submission_events','submission_genres','submission_rate_limits'];
    $combined=null;
    if ($schemaSnapshot!==null) {
        $sql='SELECT '.implode(',',array_map(static fn(string $table): string =>
            '(SELECT COUNT(*) FROM '.$table.') AS '.$table,$tables));
        $rows=submission_staging_migration_rows($db,$sql,$tables,1);
        if (count($rows)!==1) throw new RuntimeException('Unexpected combined application row counts.');
        $combined=$rows[0];
        foreach ($combined as $value) {
            if ((!is_int($value) && (!is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D',$value)!==1))
                || (is_int($value) && $value<0) || (is_string($value) && (string)(int)$value!==$value)) {
                throw new RuntimeException('Invalid combined application row count.');
            }
        }
    }
    foreach ($tables as $table) {
        $rows=$combined===null ? submission_staging_migration_rows($db, 'SELECT COUNT(*) AS total FROM '.$table,['total'],1)
            : [['total'=>$combined[$table]]];
        if (count($rows)!==1 || !is_numeric($rows[0]['total']) || (int)$rows[0]['total']<0
            || ($requireEmpty && !in_array($rows[0]['total'],[0,'0'],true))) throw new RuntimeException('Unexpected application row count.');
        $counts[$table]=(int)$rows[0]['total'];
    }
    if ($vector!==[]) {
        $rows=submission_staging_migration_rows($db,'SELECT EXISTS(SELECT 1 FROM main."_sqliteai_vector" LIMIT 1) AS has_records',['has_records'],1);
        if (count($rows)!==1 || !in_array($rows[0]['has_records'],[0,'0'],true)) throw new RuntimeException('Extension metadata has records.');
    }
    $fk=submission_staging_migration_rows($db,'PRAGMA foreign_keys',['foreign_keys'],1);
    if (count($fk)!==1 || !in_array($fk[0]['foreign_keys'],[1,'1'],true)) throw new RuntimeException('Foreign keys must remain enabled.');
    if (submission_staging_migration_rows($db,'PRAGMA foreign_key_check',['table','rowid','parent','fkid'])!==[]) {
        throw new RuntimeException('Foreign key integrity check failed.');
    }
    return ['ledger'=>$ledger,'counts'=>$counts,'schema_fingerprint'=>hash('sha256',json_encode($schema,JSON_THROW_ON_ERROR))];
}

function submission_staging_migration_run(
    array $manifest,
    #[SensitiveParameter] string $url,
    string $flag,
    string $approval,
    ?\SQLiteCloud\SQLiteCloudClient $client = null
): array {
    $plan=submission_staging_migration_plan($manifest);
    submission_staging_migration_approval($flag,$approval);
    return submission_staging_session($manifest,$url,static function (\SQLiteCloud\SQLiteCloudClient $connected) use ($manifest,$url,$plan): array {
        $db=new SQLiteCloudDatabase($connected);
        $phase='prewrite identity/schema inspection';
        try {
            $inspection=submission_staging_inspect_connected($manifest,$url,$connected);
            if ($inspection['foreign_keys']!==1 || $inspection['query_only']!==0) {
                throw new RuntimeException('Foreign keys/permissions are unsuitable; no settings changes allowed.');
            }
            $schema=submission_staging_migration_schema($db);
            $vector=array_intersect_key($schema,array_flip(['_sqliteai_vector','sqlite_autoindex__sqliteai_vector_1']));
            if ($vector!==[] && !submission_staging_vector_metadata_matches(array_values($vector))) {
                throw new RuntimeException('Extension metadata does not match.');
            }
            if ($inspection['no_application_schema_data']) {
                $phase='migration versions 1 and 2';
                $applied=submission_migrate($db,null,2);
                if ($applied!==[1,2]) throw new RuntimeException('Unexpected applied versions.');
            } else {
                // A whole-harness repeat is permitted only for an exact, zero-row v2 installation.
                submission_staging_verify_install($db,$vector);
                $applied=[];
            }
            $phase='postmigration verification';
            $before=submission_staging_verify_install($db,$vector);
            $phase='repeat no-op verification';
            if (submission_migrate($db,null,2)!==[]) throw new RuntimeException('Repeat unexpectedly applied a migration.');
            $after=submission_staging_verify_install($db,$vector);
            if ($before!==$after) throw new RuntimeException('Repeat changed installed state.');
            return ['status'=>'EMPTY_INSTALL_VERIFIED','target'=>$plan['target'],'applied_versions'=>$applied,
                'checksums'=>$plan['checksums'],'foreign_keys'=>1,'application_counts'=>$after['counts'],
                'repeat_no_op'=>true,'vector_metadata_preserved'=>true,'connection_closed_on_return'=>true];
        } catch (Throwable) {
            // Do not retry, reset, or expose provider errors. The migration runner rolls back its active version.
            throw new RuntimeException('Controlled staging migration failed during '.$phase.'. Stop for read-only recovery assessment; committed versions may remain.');
        }
    },$client);
}
