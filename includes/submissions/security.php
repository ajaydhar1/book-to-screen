<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/Database.php';

final class SubmissionInputException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Please correct the indicated fields.');
    }
}

final class SubmissionRateException extends RuntimeException
{
}

final class SubmissionConflictException extends RuntimeException
{
}

final class SubmissionCrypto
{
    private string $key;

    public function __construct(string $hexKey)
    {
        if (preg_match('/^[a-f0-9]{64}$/i', $hexKey) !== 1) {
            throw new RuntimeException('A 32-byte submission encryption key is required.');
        }
        $this->key = hex2bin($hexKey);
    }

    public function hash(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose . "\0" . $value, $this->key);
    }

    public function encrypt(string $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'b2s-submission-v1');
        if ($encrypted === false) {
            throw new RuntimeException('Could not protect submission data.');
        }
        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $value): string
    {
        $bytes = base64_decode($value, true);
        if ($bytes === false || strlen($bytes) < 28) {
            throw new RuntimeException('Invalid encrypted submission data.');
        }
        $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
            substr($bytes, 0, 12), substr($bytes, 12, 16), 'b2s-submission-v1');
        if ($plain === false) {
            throw new RuntimeException('Could not authenticate submission data.');
        }
        return $plain;
    }
}

function submission_csrf_valid(array $session, mixed $token): bool
{
    return is_string($token) && is_string($session['submission_csrf'] ?? null)
        && hash_equals($session['submission_csrf'], $token);
}

function submission_admin_user(Database $db, array $session): ?array
{
    if (empty($session['admin_logged_in']) || ($session['role'] ?? null) !== 'admin'
        || !empty($session['must_change_password']) || (int) ($session['user_id'] ?? 0) <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT id, display_name, role, is_active, must_change_password FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $session['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user !== false && $user['role'] === 'admin' && (int) $user['is_active'] === 1
        && (int) $user['must_change_password'] === 0 ? $user : null;
}

function submission_rate_limit(Database $db, SubmissionCrypto $crypto, string $action, string $identity, int $now, int $limit, int $window): void
{
    $bucket = $crypto->hash('rate:' . $action, $identity);
    $stmt = $db->prepare("INSERT INTO submission_rate_limits(bucket_hash, window_started_at, attempts)
        VALUES (:bucket, :now, 1)
        ON CONFLICT(bucket_hash) DO UPDATE SET
            attempts = CASE WHEN window_started_at <= :cutoff THEN 1 ELSE attempts + 1 END,
            window_started_at = CASE WHEN window_started_at <= :cutoff THEN :now ELSE window_started_at END");
    $stmt->execute([':bucket' => $bucket, ':now' => $now, ':cutoff' => $now - $window]);
    $stmt = $db->prepare('SELECT attempts FROM submission_rate_limits WHERE bucket_hash = :bucket');
    $stmt->execute([':bucket' => $bucket]);
    if ((int) $stmt->fetchColumn() > $limit) {
        throw new SubmissionRateException('Too many attempts. Please try again later.');
    }
}
