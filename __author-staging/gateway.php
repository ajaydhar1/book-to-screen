<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
ini_set('display_errors', '0');
$prefix = '/__author-staging';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
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
    '/mailbox.php' => 'mailbox.php',
];
$assets = [
    '/assets/css/site.css' => 'text/css', '/assets/css/submission-prototype.css' => 'text/css',
    '/assets/css/admin-nav.css' => 'text/css', '/assets/css/submission-review.css' => 'text/css',
    '/assets/js/submission-prototype.js' => 'text/javascript', '/assets/js/submission-intake.js' => 'text/javascript',
    '/favicon.png' => 'image/png',
];
$path = is_string($requestPath) && str_starts_with($requestPath, $prefix . '/') ? substr($requestPath, strlen($prefix)) : '';
if (!isset($routes[$path]) && !isset($assets[$path])) { http_response_code(404); exit; }
$root = __DIR__ . '/app';
require_once $root . '/includes/submissions/hosted-staging.php';
try {
    $pointer = getenv('B2S_HOSTED_STAGING_SETTINGS_FILE') ?: '';
    if ($pointer === '') { http_response_code(404); exit('Private staging disabled.'); }
    $settings = submission_hosted_settings($pointer);
    if (!submission_hosted_gate($_SERVER, $settings, time())) { http_response_code(404); exit('Private staging disabled.'); }
    putenv('B2S_STAGING_BROWSER_DIRECTORY=' . $settings['directory']);
    $submissionBrowserConfig = submission_hosted_config($settings);
    if (is_file($submissionBrowserConfig['stop_file'])) throw new RuntimeException('Staging stopped.');
    if (isset($assets[$path])) {
        header('Content-Type: ' . $assets[$path]);
        readfile($root . $path);
        exit;
    }
    define('B2S_HOSTED_STAGING', true);
    define('B2S_STAGING_BROWSER', true);
    putenv('B2S_SUBMISSIONS_LOCAL=1');
    submission_browser_session($submissionBrowserConfig, static function ($db, $service, $crypto) use ($root, $routes, $path, $submissionBrowserConfig): array {
        $submissionDb = $db;
        $submissionService = $service;
        putenv('B2S_SUBMISSIONS_KEY=' . submission_browser_key($submissionBrowserConfig));
        require $root . '/' . $routes[$path];
        return [];
    });
} catch (Throwable) {
    submission_browser_stop();
    error_log('Private hosted staging failed; details suppressed. Stop and inspect.');
    http_response_code(503);
    exit('Private staging stopped. Do not retry or reset records.');
}
