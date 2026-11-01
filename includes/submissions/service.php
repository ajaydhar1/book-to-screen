<?php

declare(strict_types=1);

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/antibot.php';

final class SubmissionService
{
    public function __construct(
        private Database $db,
        private SubmissionCrypto $crypto,
        private SubmissionMailer $mailer,
        private string $baseUrl,
        private ?SubmissionTurnstile $turnstile = null
    ) {
        $this->turnstile ??= new SubmissionTurnstile();
        $this->turnstile->requireForBaseUrl($baseUrl);
    }

    private function transaction(callable $operation): mixed
    {
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('Could not begin submission transaction.');
        }
        try {
            $result = $operation();
            if (!$this->db->commit()) {
                throw new RuntimeException('Could not commit submission transaction.');
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function query(string $sql, array $params = []): DatabaseStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function event(string $id, string $type, int $now, ?int $actor = null, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        $this->query('INSERT INTO submission_events(submission_id,event_type,created_at,actor_user_id,from_status,to_status,private_note)
            VALUES (:id,:type,:now,:actor,:from,:to,:note)', [
            ':id' => $id, ':type' => $type, ':now' => $now, ':actor' => $actor, ':from' => $from,
            ':to' => $to, ':note' => $note === null || $note === '' ? null : $this->crypto->encrypt($note),
        ]);
    }

    private function issueToken(string $id, int $now): string
    {
        $token = bin2hex(random_bytes(32));
        $this->query('UPDATE submission_email_tokens SET consumed_at = :now WHERE submission_id = :id AND consumed_at IS NULL',
            [':now' => $now, ':id' => $id]);
        $this->query('INSERT INTO submission_email_tokens(token_hash,submission_id,expires_at,created_at)
            VALUES (:hash,:id,:expiry,:now)', [
            ':hash' => hash('sha256', $token), ':id' => $id, ':expiry' => $now + 86400, ':now' => $now,
        ]);
        return $token;
    }

    private function deliver(string $id, string $email, string $token, int $now): bool
    {
        $sent = true;
        try {
            // Fragment tokens do not enter HTTP access logs or referer headers.
            $this->mailer->sendVerification($email, $this->baseUrl . '/verify.php#token=' . $token);
        } catch (SubmissionMailException $e) {
            $sent = false;
            error_log('Submission email delivery failed.'); // No addresses, links or provider response bodies.
        }
        $this->transaction(function () use ($id, $token, $now, $sent): void {
            // Do not let an older in-flight delivery overwrite a newer attempt.
            $stmt = $this->query('UPDATE property_submissions SET delivery_status = :status, updated_at = :now
                WHERE id = :id AND EXISTS (SELECT 1 FROM submission_email_tokens
                    WHERE token_hash = :hash AND consumed_at IS NULL)', [
                ':status' => $sent ? 'sent' : 'failed', ':now' => $now, ':id' => $id, ':hash' => hash('sha256', $token),
            ]);
            if ($stmt->rowCount() > 0) {
                $this->event($id, $sent ? 'verification_email_sent' : 'verification_email_failed', $now);
            }
        });
        return $sent;
    }

    public function submit(array $input, string $requestKey, string $identity, int $now): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $requestKey) !== 1) {
            throw new SubmissionInputException(['request_key' => 'Please reload to start a new submission.']);
        }
        submission_rate_limit($this->db, $this->crypto, 'submit', $identity, $now, 10, 3600);
        submission_check_honeypot($input);
        $validated = submission_validate($input);
        $payload = json_encode($validated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $keyHash = $this->crypto->hash('idempotency', $requestKey);
        $payloadHash = $this->crypto->hash('payload', $payload);
        $receipt = $this->crypto->hash('receipt', $requestKey);
        $id = bin2hex(random_bytes(16));
        $private = $validated['private'];
        $book = $validated['book'];
        $emailHash = $this->crypto->hash('email', $private['email']);
        submission_rate_limit($this->db, $this->crypto, 'submit_email', $private['email'], $now, 10, 3600);
        $this->turnstile->verify($input['turnstile_token'] ?? null, $now);
        $propertyText = preg_replace('/\s+/u', ' ', trim($book['title'] . '|' . $book['authors']));
        $propertyHash = $this->crypto->hash('property', mb_strtolower($propertyText, 'UTF-8'));
        $result = $this->transaction(function () use ($id, $book, $private, $emailHash, $propertyHash, $keyHash, $payloadHash, $receipt, $now): array {
            $insert = $this->query("INSERT INTO property_submissions(
                id,isbn,book_title,book_authors,book_json,private_payload,email_hash,property_hash,
                idempotency_hash,payload_hash,receipt_hash,consent_version,privacy_version,consent_at,last_send_at,created_at,updated_at)
                VALUES (:id,:isbn,:title,:authors,:book,:private,:email,:property,:key,:payload,:receipt,:consent,:privacy,:now,:now,:now,:now)
                ON CONFLICT(idempotency_hash) DO NOTHING", [
                ':id' => $id, ':isbn' => $book['isbn'], ':title' => $book['title'], ':authors' => $book['authors'],
                ':book' => json_encode($book, JSON_THROW_ON_ERROR), ':private' => $this->crypto->encrypt(json_encode($private, JSON_THROW_ON_ERROR)),
                ':email' => $emailHash, ':property' => $propertyHash, ':key' => $keyHash, ':payload' => $payloadHash,
                ':receipt' => hash('sha256', $receipt), ':consent' => SUBMISSION_CONSENT_VERSION,
                ':privacy' => SUBMISSION_PRIVACY_VERSION, ':now' => $now,
            ]);
            if ($insert->rowCount() === 0) {
                $existing = $this->query('SELECT payload_hash FROM property_submissions WHERE idempotency_hash = :key',
                    [':key' => $keyHash])->fetch(PDO::FETCH_ASSOC);
                if (!$existing || !hash_equals($existing['payload_hash'], $payloadHash)) {
                    throw new SubmissionConflictException('This request was already used with different information. Reload to begin again.');
                }
                return ['created' => false, 'token' => null];
            }
            $token = $this->issueToken($id, $now);
            $this->event($id, 'submitted', $now, null, null, 'pending_email');
            return ['created' => true, 'token' => $token];
        });
        if ($result['created']) {
            $this->deliver($id, $private['email'], $result['token'], $now);
        }
        return ['receipt' => $receipt, 'state' => 'pending_verification'];
    }

    public function receipt(string $receipt): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $receipt) !== 1) {
            return null;
        }
        $row = $this->query('SELECT delivery_status,verified_at,review_status FROM property_submissions WHERE receipt_hash = :hash',
            [':hash' => hash('sha256', $receipt)])->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function resend(string $receipt, string $identity, int $now): void
    {
        submission_rate_limit($this->db, $this->crypto, 'resend', $identity, $now, 5, 3600);
        if (preg_match('/^[a-f0-9]{64}$/', $receipt) !== 1) {
            return;
        }
        $attempt = $this->transaction(function () use ($receipt, $now): ?array {
            $row = $this->query('SELECT id,private_payload FROM property_submissions WHERE receipt_hash = :hash',
                [':hash' => hash('sha256', $receipt)])->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $claim = $this->query("UPDATE property_submissions SET resend_count = resend_count + 1,
                last_send_at = :now, delivery_status = 'pending', updated_at = :now
                WHERE id = :id AND verified_at IS NULL AND resend_count < 3 AND last_send_at <= :cutoff",
                [':now' => $now, ':id' => $row['id'], ':cutoff' => $now - 60]);
            if ($claim->rowCount() === 0) {
                return null;
            }
            $token = $this->issueToken($row['id'], $now);
            $this->event($row['id'], 'verification_resend_requested', $now);
            $private = json_decode($this->crypto->decrypt($row['private_payload']), true, 16, JSON_THROW_ON_ERROR);
            return ['id' => $row['id'], 'email' => $private['email'], 'token' => $token];
        });
        if ($attempt !== null) {
            $this->deliver($attempt['id'], $attempt['email'], $attempt['token'], $now);
        }
    }

    public function verify(string $token, string $identity, int $now): bool
    {
        submission_rate_limit($this->db, $this->crypto, 'verify', $identity, $now, 20, 3600);
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return false;
        }
        return $this->transaction(function () use ($token, $now): bool {
            $hash = hash('sha256', $token);
            $consume = $this->query('UPDATE submission_email_tokens SET consumed_at = :now
                WHERE token_hash = :hash AND consumed_at IS NULL AND expires_at > :now',
                [':hash' => $hash, ':now' => $now]);
            if ($consume->rowCount() !== 1) {
                return false;
            }
            $id = (string) $this->query('SELECT submission_id FROM submission_email_tokens WHERE token_hash = :hash',
                [':hash' => $hash])->fetchColumn();
            $update = $this->query("UPDATE property_submissions SET verified_at = :now, review_status = 'new', updated_at = :now
                WHERE id = :id AND verified_at IS NULL AND review_status = 'pending_email'", [':id' => $id, ':now' => $now]);
            if ($update->rowCount() !== 1) {
                return false;
            }
            $this->query('UPDATE submission_email_tokens SET consumed_at = :now WHERE submission_id = :id AND consumed_at IS NULL',
                [':now' => $now, ':id' => $id]);
            $this->event($id, 'email_verified', $now, null, 'pending_email', 'new');
            return true;
        });
    }

    public function queue(int $page = 1): array
    {
        return $this->query('SELECT id,book_title,book_authors,isbn,review_status,created_at,verified_at,revision
            FROM property_submissions WHERE verified_at IS NOT NULL ORDER BY created_at DESC,id LIMIT :limit OFFSET :offset',
            [':limit' => 20, ':offset' => (max(1, $page) - 1) * 20])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function detail(string $id): ?array
    {
        $row = $this->query('SELECT * FROM property_submissions WHERE id = :id AND verified_at IS NOT NULL', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['private'] = json_decode($this->crypto->decrypt($row['private_payload']), true, 16, JSON_THROW_ON_ERROR);
        $row['book'] = json_decode($row['book_json'], true, 16, JSON_THROW_ON_ERROR);
        unset($row['private_payload'], $row['receipt_hash'], $row['idempotency_hash'], $row['payload_hash'], $row['email_hash']);
        $row['duplicates'] = $this->query('SELECT id,book_title,book_authors,review_status FROM property_submissions
            WHERE id <> :id AND (property_hash = :property OR (isbn IS NOT NULL AND isbn = :isbn)) ORDER BY created_at DESC LIMIT 10',
            [':id' => $id, ':property' => $row['property_hash'], ':isbn' => $row['isbn']])->fetchAll(PDO::FETCH_ASSOC);
        $row['events'] = $this->query('SELECT event_type,from_status,to_status,actor_user_id,private_note,created_at
            FROM submission_events WHERE submission_id = :id ORDER BY id DESC LIMIT 50', [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($row['events'] as &$event) {
            $event['note'] = $event['private_note'] === null ? '' : $this->crypto->decrypt($event['private_note']);
            unset($event['private_note']);
        }
        return $row;
    }

    public function review(string $id, string $status, string $note, int $actor, int $revision, int $now): void
    {
        if (submission_admin_user($this->db, ['admin_logged_in' => true, 'role' => 'admin', 'user_id' => $actor]) === null) {
            throw new SubmissionInputException(['review' => 'An active administrator is required.']);
        }
        if (!in_array($status, ['new','reviewing','needs_information','closed','duplicate','spam'], true)
            || $actor <= 0 || mb_strlen($note, 'UTF-8') > 2000 || preg_match('//u', $note) !== 1 || str_contains($note, "\0")) {
            throw new SubmissionInputException(['review' => 'Choose a valid status and keep notes within 2,000 characters.']);
        }
        $this->transaction(function () use ($id, $status, $note, $actor, $revision, $now): void {
            $old = $this->query('SELECT review_status FROM property_submissions WHERE id = :id AND verified_at IS NOT NULL', [':id' => $id])->fetchColumn();
            $stmt = $this->query('UPDATE property_submissions SET review_status = :status,reviewed_by_user_id = :actor,
                reviewed_at = :now,updated_at = :now,revision = revision + 1
                WHERE id = :id AND verified_at IS NOT NULL AND revision = :revision', [
                ':id' => $id, ':status' => $status, ':actor' => $actor, ':now' => $now, ':revision' => $revision,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new SubmissionConflictException('This submission changed. Refresh before reviewing again.');
            }
            $this->event($id, 'review_updated', $now, $actor, (string) $old, $status, trim($note));
        });
    }
}
