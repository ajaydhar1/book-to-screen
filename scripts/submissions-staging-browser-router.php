<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || getenv('B2S_STAGING_BROWSER_ACTIVE') !== '1'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$routes = [
    '/' => 'author-submissions/index.php', '/author-submissions/' => 'author-submissions/index.php',
    '/author-submissions/index.php' => 'author-submissions/index.php',
    '/author-submissions/lookup.php' => 'author-submissions/lookup.php',
    '/author-submissions/submit.php' => 'author-submissions/submit.php',
    '/author-submissions/verify.php' => 'author-submissions/verify.php',
    '/author-submissions/receipt.php' => 'author-submissions/receipt.php',
    '/author-submissions/resend.php' => 'author-submissions/resend.php',
    '/author-submissions/status.php' => 'author-submissions/status.php',
    '/author-submissions/staff-login.php' => 'author-submissions/staff-login.php',
    '/admin/submissions.php' => 'admin/submissions.php',
    '/__staging/mailbox' => 'scripts/submissions-staging-browser-mailbox.php',
];
$assets = [
    '/assets/css/site.css' => 'text/css', '/assets/css/submission-prototype.css' => 'text/css',
    '/assets/css/admin-nav.css' => 'text/css', '/assets/css/submission-review.css' => 'text/css',
    '/assets/js/submission-prototype.js' => 'text/javascript', '/assets/js/submission-intake.js' => 'text/javascript',
    '/favicon.png' => 'image/png',
];
require_once $root . '/includes/submissions/staging-browser.php';
try {
    $submissionBrowserConfig = submission_browser_config(getenv('B2S_STAGING_BROWSER_CONFIG') ?: '');
    $host = parse_url($submissionBrowserConfig['base_url'], PHP_URL_HOST) . ':' . parse_url($submissionBrowserConfig['base_url'], PHP_URL_PORT);
    if (($_SERVER['HTTP_HOST'] ?? '') !== $host) {
        http_response_code(403);
        exit('Use the configured 127.0.0.1 URL.');
    }
    if (is_file($submissionBrowserConfig['stop_file'])) {
        http_response_code(503);
        exit('Staging browser stopped. Inspect the failure before another approved launch.');
    }
    if (isset($assets[$path])) {
        header('Content-Type: ' . $assets[$path]);
        readfile($root . str_replace('/', DIRECTORY_SEPARATOR, $path));
        exit;
    }
    if ($path === '/__staging/turnstile.js' && $submissionBrowserConfig['mode'] === 'offline') {
        header('Content-Type: text/javascript');
        readfile(__DIR__ . '/submissions-staging-browser-turnstile.js');
        exit;
    }
    if (!isset($routes[$path])) { http_response_code(404); exit('Not available in the isolated staging server.'); }
    submission_browser_gate($submissionBrowserConfig, getenv('B2S_STAGING_BROWSER_SERVE_FLAG') ?: '',
        getenv('B2S_STAGING_BROWSER_APPROVAL') ?: '');
    define('B2S_STAGING_BROWSER', true);
    submission_browser_session($submissionBrowserConfig, static function ($db, $service, $crypto) use ($root, $path, $routes, $submissionBrowserConfig): array {
        $submissionDb = $db;
        $submissionService = $service;
        putenv('B2S_SUBMISSIONS_KEY=' . submission_browser_key($submissionBrowserConfig));
        require $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $routes[$path]);
        return [];
    });
} catch (Throwable) {
    submission_browser_stop();
    error_log('Staging browser failed; details suppressed. Stop and inspect without retrying.');
    http_response_code(503);
    echo 'Staging browser stopped after an unexpected failure. Do not retry or reset records.';
}
