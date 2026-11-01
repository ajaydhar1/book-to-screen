<?php

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $_GET !== []
    || ($_SERVER['HTTP_HOST'] ?? '') !== 'booktoscreen.org'
    || parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/__author-staging/hosting-check.php') {
    http_response_code(404); exit;
}
try {
    set_error_handler(static function (): never { throw new RuntimeException('Prerequisite runtime warning suppressed.'); });
    require_once __DIR__ . '/app/includes/submissions/hosted-check.php';
    $checks = submission_hosted_prerequisites($_SERVER, getenv('B2S_HOSTED_STAGING_SETTINGS_FILE') ?: '');
    foreach ($checks as $label => $passed) echo $label . ': ' . ($passed ? 'PASS' : 'FAIL') . "\n";
    if ($checks['Disabled private settings'] && $checks['Authenticated tester']
        && $checks['Trusted HTTPS'] && $checks['Canonical host']) {
        foreach (submission_hosted_file_diagnostics($_SERVER, getenv('B2S_HOSTED_STAGING_SETTINGS_FILE') ?: '') as $label => $status) {
            echo $label . ': ' . $status . "\n";
        }
    }
} catch (Throwable) {
    error_log('Hosted prerequisite endpoint failed; details suppressed.');
    http_response_code(503);
    echo "Prerequisite checker: FAIL\n";
} finally {
    restore_error_handler();
}
