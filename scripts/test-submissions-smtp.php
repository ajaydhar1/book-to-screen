<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/smtp-test.php';
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mode = $argv[1] ?? '--offline';
    if ($mode === '--offline') {
        if (count($argv) > 3) throw new RuntimeException('Unexpected offline SMTP arguments.');
        $config = isset($argv[2]) ? submission_namecheap_config($argv[2]) : [
            'host' => 'booktoscreen.org', 'port' => 465, 'encryption' => 'smtps',
            'username' => 'hello@booktoscreen.org', 'from' => 'hello@booktoscreen.org',
            'password' => '', 'test_recipient' => '', 'hourly_limit' => 1,
        ];
        // Real credentials and recipients are never used by the offline transport.
        $config['password'] = 'offline-smtp-fixture';
        $config['test_recipient'] = 'owned-fixture@example.com';
        $db = new LocalSqliteDatabase(':memory:');
        submission_migrate($db);
        $crypto = new SubmissionCrypto(bin2hex(random_bytes(32)));
        $mock = new class(true) extends \PHPMailer\PHPMailer\PHPMailer {
            public function send()
            {
                return $this->preSend();
            }
        };
        $service = submission_smtp_test_service($db, $crypto,
            submission_namecheap_mailer($config, $db, $crypto, static fn() => $mock),
            'http://127.0.0.1:8088/author-submissions');
        $result = $service->submit(submission_smtp_test_input($config['test_recipient']),
            bin2hex(random_bytes(32)), 'smtp-offline', time());
        if ($service->receipt($result['receipt'])['delivery_status'] !== 'sent') {
            throw new RuntimeException('Offline SMTP fixture was not accepted.');
        }
        echo "OFFLINE_SMTP_VERIFIED: local in-memory fixture, mocked acceptance, no network or real delivery.\n";
    } elseif ($mode === '--check') {
        if (count($argv) !== 3) throw new RuntimeException('Use --check with a private configuration path.');
        $config = submission_namecheap_config($argv[2]);
        echo submission_namecheap_ready($config)
            ? "CONFIG_READY: credentials present; ownership, certificate and delivery remain unverified. No network used.\n"
            : "CONFIG_INCOMPLETE: privately configure the password and approved owned recipient. No network used.\n";
        if (!submission_namecheap_ready($config)) exit(2);
    } elseif ($mode === '--send-one') {
        if (count($argv) !== 7) {
            throw new RuntimeException('Use --send-one CONFIG APPROVAL FIXTURE_DB KEY_FILE LOOPBACK_BASE_URL.');
        }
        $config = submission_namecheap_config($argv[2]);
        if (!submission_namecheap_ready($config)
            || !hash_equals('namecheap-smtp-v1:' . $config['test_recipient'], $argv[3])) {
            throw new RuntimeException('Real email requires exact recipient approval and ready private configuration.');
        }
        [$db, $crypto] = submission_smtp_test_fixture($argv[4], $argv[5]);
        $path = realpath($argv[4]);
        $service = submission_smtp_test_service($db, $crypto,
            submission_namecheap_mailer($config, $db, $crypto, null, $argv[3]), $argv[6]);
        $marker = dirname($path) . DIRECTORY_SEPARATOR . 'smtp-send-attempted.flag';
        $handle = @fopen($marker, 'x');
        if ($handle === false) throw new RuntimeException('A send was already attempted or the private fixture is not writable. Stop.');
        fclose($handle);
        if (DIRECTORY_SEPARATOR !== '\\' && !chmod($marker, 0600)) {
            throw new RuntimeException('Could not restrict the send-attempt marker. Stop.');
        }
        $result = $service->submit(submission_smtp_test_input($config['test_recipient']),
            bin2hex(random_bytes(32)), 'smtp-controlled-test', time());
        if ($service->receipt($result['receipt'])['delivery_status'] !== 'sent') {
            throw new RuntimeException('SMTP acceptance failed. The attempted fixture is retained; do not automatically retry.');
        }
        echo "SMTP_ACCEPTED: one verification message accepted by SMTP, not proof of inbox delivery. Inspect the owned inbox.\n";
    } else {
        throw new RuntimeException('Choose --offline, --check or separately approved --send-one.');
    }
} catch (Throwable $e) {
    // Configuration/provider exceptions may contain secrets. Never print their details.
    fwrite(STDERR, "SMTP test stopped. Check private configuration and approval/fixture prerequisites. Do not retry a live attempt without review.\n");
    exit(1);
}
