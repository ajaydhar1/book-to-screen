<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/hosted-check.php';
try {
    set_error_handler(static function (): never { throw new RuntimeException('Connectivity runtime warning suppressed.'); });
    if (count($argv) !== 3 || $argv[1] !== '--approved-one-readonly-connection') {
        throw new RuntimeException('Separate live approval required.');
    }
    $settings = submission_hosted_settings($argv[2]);
    if ($settings['enabled'] !== false || $settings['deadline'] !== 0 || $settings['writers_stopped'] !== false) {
        throw new RuntimeException('Portal must remain disabled.');
    }
    $config = submission_hosted_config($settings);
    submission_hosted_reserve_connectivity_attempt($config);
    foreach (submission_hosted_connectivity_readonly($config) as $label => $passed) {
        echo $label . ': ' . ($passed ? 'PASS' : 'FAIL') . "\n";
    }
} catch (Throwable) {
    fwrite(STDERR, "Read-only connectivity check: FAIL\n");
    exit(1);
} finally {
    restore_error_handler();
}
