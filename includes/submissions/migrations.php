<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/security.php';

function submission_migrations(): array
{
    $migrations = [
        1 => [
            "CREATE TABLE property_submissions (
                id TEXT PRIMARY KEY,
                isbn TEXT,
                book_title TEXT NOT NULL,
                book_authors TEXT NOT NULL,
                book_json TEXT NOT NULL,
                private_payload TEXT NOT NULL,
                email_hash TEXT NOT NULL,
                property_hash TEXT NOT NULL,
                idempotency_hash TEXT NOT NULL UNIQUE,
                payload_hash TEXT NOT NULL,
                receipt_hash TEXT NOT NULL UNIQUE,
                review_status TEXT NOT NULL DEFAULT 'pending_email'
                    CHECK (review_status IN ('pending_email','new','reviewing','needs_information','closed','duplicate','spam')),
                authorization_status TEXT NOT NULL DEFAULT 'self_declared'
                    CHECK (authorization_status = 'self_declared'),
                verified_at INTEGER,
                consent_version TEXT NOT NULL,
                privacy_version TEXT NOT NULL,
                consent_at INTEGER NOT NULL,
                delivery_status TEXT NOT NULL DEFAULT 'pending'
                    CHECK (delivery_status IN ('pending','sent','failed')),
                resend_count INTEGER NOT NULL DEFAULT 0 CHECK (resend_count BETWEEN 0 AND 3),
                last_send_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                reviewed_by_user_id INTEGER,
                reviewed_at INTEGER,
                revision INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE INDEX submission_review_idx ON property_submissions(review_status, verified_at, created_at)",
            "CREATE INDEX submission_isbn_idx ON property_submissions(isbn)",
            "CREATE INDEX submission_property_idx ON property_submissions(property_hash)",
            "CREATE INDEX submission_email_idx ON property_submissions(email_hash)",
            "CREATE TABLE submission_email_tokens (
                token_hash TEXT PRIMARY KEY,
                submission_id TEXT NOT NULL REFERENCES property_submissions(id) ON DELETE CASCADE,
                expires_at INTEGER NOT NULL,
                consumed_at INTEGER,
                created_at INTEGER NOT NULL
            )",
            "CREATE INDEX submission_token_owner_idx ON submission_email_tokens(submission_id)",
            "CREATE TABLE submission_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id TEXT NOT NULL REFERENCES property_submissions(id) ON DELETE CASCADE,
                event_type TEXT NOT NULL,
                from_status TEXT,
                to_status TEXT,
                actor_user_id INTEGER,
                private_note TEXT,
                created_at INTEGER NOT NULL
            )",
            "CREATE INDEX submission_event_owner_idx ON submission_events(submission_id, id)",
            "CREATE TABLE submission_rate_limits (
                bucket_hash TEXT PRIMARY KEY,
                window_started_at INTEGER NOT NULL,
                attempts INTEGER NOT NULL
            )",
        ],
    ];
    // Version 1 is immutable; copy its schema to preserve existing columns and constraints.
    $parent = str_replace(
        ['CREATE TABLE property_submissions (', "'needs_information','closed'", 'revision INTEGER NOT NULL DEFAULT 0'],
        ['CREATE TABLE property_submissions_v2 (', "'needs_information','research_candidate','closed'",
            "revision INTEGER NOT NULL DEFAULT 0,\n                submitter_role TEXT NOT NULL DEFAULT '' CHECK (submitter_role IN ('','author','agent','rights-holder','scout')),\n                evaluation_payload TEXT"],
        $migrations[1][0]
    );
    $migrations[2] = [
        $parent,
        'INSERT INTO property_submissions_v2 SELECT *, \'\', NULL FROM property_submissions',
        str_replace(['submission_email_tokens (', 'REFERENCES property_submissions('],
            ['submission_email_tokens_v2 (', 'REFERENCES property_submissions_v2('], $migrations[1][5]),
        'INSERT INTO submission_email_tokens_v2 SELECT * FROM submission_email_tokens',
        str_replace(['submission_events (', 'REFERENCES property_submissions('],
            ['submission_events_v2 (', 'REFERENCES property_submissions_v2('], $migrations[1][7]),
        'INSERT INTO submission_events_v2 SELECT * FROM submission_events',
        'DROP TABLE submission_email_tokens',
        'DROP TABLE submission_events',
        'DROP TABLE property_submissions',
        'ALTER TABLE property_submissions_v2 RENAME TO property_submissions',
        'ALTER TABLE submission_email_tokens_v2 RENAME TO submission_email_tokens',
        'ALTER TABLE submission_events_v2 RENAME TO submission_events',
        ...array_slice($migrations[1], 1, 4),
        $migrations[1][6],
        $migrations[1][8],
        'CREATE INDEX submission_dashboard_idx ON property_submissions(review_status,created_at,id) WHERE verified_at IS NOT NULL',
        "CREATE INDEX submission_priority_idx ON property_submissions(
            CASE review_status WHEN 'new' THEN 0 WHEN 'needs_information' THEN 1 WHEN 'reviewing' THEN 2 WHEN 'research_candidate' THEN 3 ELSE 4 END,created_at,id)
            WHERE verified_at IS NOT NULL AND review_status IN ('new','reviewing','needs_information','research_candidate')",
        'CREATE INDEX submission_date_idx ON property_submissions(created_at,id)',
        'CREATE INDEX submission_role_idx ON property_submissions(submitter_role,created_at)',
        "CREATE TABLE submission_genres (
            submission_id TEXT NOT NULL REFERENCES property_submissions(id) ON DELETE CASCADE,
            genre_key TEXT NOT NULL,
            genre_label TEXT NOT NULL,
            PRIMARY KEY (submission_id,genre_key)
        )",
        'CREATE INDEX submission_genre_filter_idx ON submission_genres(genre_key,submission_id)',
    ];
    return $migrations;
}

function submission_migrate(Database $db, ?SubmissionCrypto $crypto = null, ?int $throughVersion = null): array
{
    $db->exec("CREATE TABLE IF NOT EXISTS submission_schema_versions (
        version INTEGER PRIMARY KEY, checksum TEXT NOT NULL, applied_at INTEGER NOT NULL
    )");
    $applied = [];
    foreach (submission_migrations() as $version => $statements) {
        if ($throughVersion !== null && $version > $throughVersion) break;
        $checksum = hash('sha256', implode("\n", $statements));
        if (!$db->beginTransaction()) {
            throw new RuntimeException('Could not begin migration transaction.');
        }
        try {
            $stmt = $db->prepare('SELECT checksum FROM submission_schema_versions WHERE version = :version');
            $stmt->execute([':version' => $version]);
            $existing = $stmt->fetchColumn();
            if ($existing !== false) {
                if (!hash_equals((string) $existing, $checksum)) {
                    throw new RuntimeException('Previously applied submission migration was changed.');
                }
            } else {
                foreach ($statements as $sql) {
                    $db->exec($sql);
                }
                if ($version === 2) {
                    $lastId = '';
                    do {
                        $stmt = $db->prepare('SELECT id,book_json,private_payload FROM property_submissions WHERE id > :last ORDER BY id LIMIT 100');
                        $stmt->execute(['last' => $lastId]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($rows !== [] && $crypto === null) {
                            throw new RuntimeException('The existing submission encryption key is required for reviewer migration.');
                        }
                        foreach ($rows as $row) {
                            $private = json_decode($crypto->decrypt($row['private_payload']), true, 16, JSON_THROW_ON_ERROR);
                            $book = json_decode($row['book_json'], true, 16, JSON_THROW_ON_ERROR);
                            $stmt = $db->prepare('UPDATE property_submissions SET submitter_role = :role WHERE id = :id');
                            $stmt->execute(['role' => $private['role'], 'id' => $row['id']]);
                            foreach ($book['genres'] as $genre) {
                                $stmt = $db->prepare('INSERT INTO submission_genres(submission_id,genre_key,genre_label) VALUES (:id,:key,:label)');
                                $stmt->execute(['id' => $row['id'], 'key' => mb_strtolower($genre, 'UTF-8'), 'label' => $genre]);
                            }
                            $lastId = $row['id'];
                        }
                    } while (count($rows) === 100);
                }
                $stmt = $db->prepare('INSERT INTO submission_schema_versions(version, checksum, applied_at) VALUES (:version, :checksum, :now)');
                $stmt->execute([':version' => $version, ':checksum' => $checksum, ':now' => time()]);
                $applied[] = $version;
            }
            if (!$db->commit()) {
                throw new RuntimeException('Could not commit submission migration.');
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
    return $applied;
}
