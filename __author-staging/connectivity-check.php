<?php

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true) || $_GET !== []
    || ($_SERVER['HTTP_HOST'] ?? '') !== 'booktoscreen.org'
    || parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== '/__author-staging/connectivity-check.php') {
    http_response_code(404); exit;
}
try {
    set_error_handler(static function (): never { throw new RuntimeException('Connectivity runtime warning suppressed.'); });
    require_once __DIR__ . '/app/includes/submissions/hosted-check.php';
    $pointer = getenv('B2S_HOSTED_STAGING_SETTINGS_FILE') ?: '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        submission_hosted_browser_connectivity_settings($_SERVER, $pointer, time());
        echo '<p>Test authorization: PASS</p><form method="post" action="/__author-staging/connectivity-check.php">'
            . '<label>Type APPROVE ONE STAGING READ ONLY CONNECTION '
            . '<input name="confirmation" required autocomplete="off"></label>'
            . '<button type="submit">Run one approved staging connectivity test</button></form>';
    } else {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded'
            || !ctype_digit((string) ($_SERVER['CONTENT_LENGTH'] ?? ''))
            || (int) $_SERVER['CONTENT_LENGTH'] > 256) {
            throw new RuntimeException('Bounded form required.');
        }
        foreach (submission_hosted_browser_connectivity_attempt($_SERVER, $_POST, $pointer, time()) as $label => $passed) {
            echo '<p>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': ' . ($passed ? 'PASS' : 'FAIL') . '</p>';
        }
    }
} catch (Throwable) {
    error_log('Hosted browser connectivity check failed; details suppressed.');
    http_response_code(403);
    echo '<p>Read-only connectivity check: FAIL</p>';
} finally {
    restore_error_handler();
}
