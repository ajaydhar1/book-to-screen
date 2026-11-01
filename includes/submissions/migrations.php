<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/Database.php';

function submission_migrations(): array
{
    return [
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
}

function submission_migrate(Database $db): array
{
    $db->exec("CREATE TABLE IF NOT EXISTS submission_schema_versions (
        version INTEGER PRIMARY KEY, checksum TEXT NOT NULL, applied_at INTEGER NOT NULL
    )");
    $applied = [];
    foreach (submission_migrations() as $version => $statements) {
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
