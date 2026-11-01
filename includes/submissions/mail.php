<?php

declare(strict_types=1);

interface SubmissionMailer
{
    public function sendVerification(string $email, string $link): void;
}

final class SubmissionMailException extends RuntimeException
{
}

final class DisabledSubmissionMailer implements SubmissionMailer
{
    public function sendVerification(string $email, string $link): void
    {
        throw new SubmissionMailException('Email delivery is disabled for local development.');
    }
}

final class LocalMockSubmissionMailer implements SubmissionMailer
{
    public function __construct(private string $path, private SubmissionCrypto $crypto)
    {
        $directory = realpath(dirname($path));
        $root = realpath(__DIR__ . '/../..');
        if ($directory === false || basename($path) !== 'submissions-mailbox.jsonl'
            || str_starts_with(strtolower($directory) . DIRECTORY_SEPARATOR, strtolower($root) . DIRECTORY_SEPARATOR)
            || is_link($path)) {
            throw new RuntimeException('Mock mailbox must be explicitly configured outside the web root.');
        }
    }

    public function sendVerification(string $email, string $link): void
    {
        $message = $this->crypto->encrypt(json_encode(['email' => $email, 'link' => $link, 'created_at' => time()], JSON_THROW_ON_ERROR));
        if (file_put_contents($this->path, $message . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new SubmissionMailException('Could not capture mock delivery.');
        }
        if (DIRECTORY_SEPARATOR !== '\\' && !chmod($this->path, 0600)) {
            throw new SubmissionMailException('Could not restrict mock mailbox permissions.');
        }
    }
}

final class SmtpSubmissionMailer implements SubmissionMailer
{
    public function __construct(
        private array $config,
        private Database $db,
        private SubmissionCrypto $crypto,
        private ?Closure $factory = null
    ) {
        if (($config['approved'] ?? false) !== true || !filter_var($config['test_recipient'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Real SMTP requires explicit controlled-test approval and an owned test address.');
        }
        if (!is_string($config['host'] ?? null) || preg_match('/^[a-z0-9.-]+$/i', $config['host']) !== 1
            || !in_array($config['port'] ?? null, [465,587], true)
            || !filter_var($config['username'] ?? '', FILTER_VALIDATE_EMAIL)
            || !is_string($config['password'] ?? null) || $config['password'] === ''
            || !filter_var($config['from'] ?? '', FILTER_VALIDATE_EMAIL)
            || !is_int($config['hourly_limit'] ?? null) || $config['hourly_limit'] < 1 || $config['hourly_limit'] > 100) {
            throw new RuntimeException('Configure authenticated TLS SMTP and a conservative confirmed hourly sending budget.');
        }
    }

    public function sendVerification(string $email, string $link): void
    {
        if (!hash_equals(strtolower($this->config['test_recipient']), strtolower($email))) {
            throw new SubmissionMailException('Recipient is not the approved controlled-test address.');
        }
        try {
            submission_rate_limit($this->db, $this->crypto, 'smtp_budget', 'all',
                time(), $this->config['hourly_limit'], 3600);
        } catch (SubmissionRateException $e) {
            throw new SubmissionMailException('SMTP sending budget exhausted.', 0, $e);
        }
        require_once __DIR__ . '/../../vendor/autoload.php';
        $mail = $this->factory === null ? new \PHPMailer\PHPMailer\PHPMailer(true) : ($this->factory)();
        $mail->isSMTP();
        $mail->Host = $this->config['host'];
        $mail->Port = $this->config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->config['username'];
        $mail->Password = $this->config['password'];
        $mail->SMTPSecure = $mail->Port === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
        $mail->SMTPDebug = 0;
        $mail->Timeout = 10;
        $mail->Timelimit = 15;
        $mail->CharSet = 'UTF-8';
        try {
            $mail->setFrom($this->config['from'], 'Book to Screen');
            $mail->addAddress($email);
            $mail->Subject = 'Confirm your Book to Screen submission email';
            $mail->Body = "Open this link and press Confirm to verify control of your email:\n" . $link
                . "\nThe link expires in 24 hours. Verification does not establish rights ownership."
                . "\nIf you did not request this, ignore the message.";
            if (!$mail->send()) {
                throw new SubmissionMailException('SMTP did not accept the verification email.');
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // Do not expose SMTP diagnostics, credentials or recipient data.
            throw new SubmissionMailException('SMTP delivery could not be completed.');
        }
    }
}

function submission_smtp_from_environment(Database $db, SubmissionCrypto $crypto): SubmissionMailer
{
    $limit = getenv('B2S_SMTP_HOURLY_LIMIT') ?: '';
    return new SmtpSubmissionMailer([
        'approved' => getenv('B2S_SMTP_CONTROLLED_TEST_APPROVED') === '1',
        'test_recipient' => getenv('B2S_SMTP_TEST_RECIPIENT') ?: '',
        'host' => getenv('B2S_SMTP_HOST') ?: '',
        'port' => (int) (getenv('B2S_SMTP_PORT') ?: '0'),
        'username' => getenv('B2S_SMTP_USERNAME') ?: '',
        'password' => getenv('B2S_SMTP_PASSWORD') ?: '',
        'from' => getenv('B2S_SMTP_FROM') ?: '',
        'hourly_limit' => ctype_digit($limit) ? (int) $limit : 0,
    ], $db, $crypto);
}
