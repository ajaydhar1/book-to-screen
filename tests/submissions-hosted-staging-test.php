<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/hosted-staging.php';
$checks = 0;
function hosted_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$now = time();
$server = ['HTTPS' => 'on', 'HTTP_HOST' => 'booktoscreen.org', 'REMOTE_USER' => 'fictional-tester'];
$settings = ['enabled' => true, 'writers_stopped' => true, 'deadline' => $now + 1800, 'user' => 'fictional-tester'];
hosted_check(submission_hosted_gate($server, $settings, $now), 'Approved hosted boundary allowed');
foreach ([['HTTPS' => 'off'], ['HTTPS' => ''], ['HTTP_HOST' => 'www.booktoscreen.org'],
    ['REMOTE_USER' => ''], ['REMOTE_USER' => 'wrong']] as $override) {
    hosted_check(!submission_hosted_gate(array_replace($server, $override), $settings, $now), 'Untrusted context denied');
}
foreach ([['enabled' => false], ['writers_stopped' => false], ['deadline' => $now], ['deadline' => $now + 86401]] as $override) {
    hosted_check(!submission_hosted_gate($server, array_replace($settings, $override), $now), 'Disabled/expired/concurrent-writer state denied');
}
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-hosted-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Synthetic fixture failed.');
$path = $directory . DIRECTORY_SEPARATOR . 'settings.json';
try {
    $data = array_merge($settings, ['baseline_base_url' => 'http://127.0.0.1:8086/author-submissions']);
    foreach (['key_file', 'staff_db', 'identity_manifest', 'connection_file', 'widget_config'] as $field) {
        $data[$field] = $directory . DIRECTORY_SEPARATOR . $field;
    }
    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    if (DIRECTORY_SEPARATOR !== '\\') chmod($path, 0600);
    $parsed = submission_hosted_settings($path);
    hosted_check($parsed['directory'] === $directory && $parsed['enabled'] === true, 'Bounded private settings parsed');
    foreach ([['enabled' => 'true'], ['mail' => 'smtp'], ['connection_file' => 'public.json'],
        ['baseline_base_url' => SUBMISSION_HOSTED_BASE], ['deadline' => 'later']] as $override) {
        file_put_contents($path, json_encode(array_replace($data, $override), JSON_THROW_ON_ERROR));
        try { submission_hosted_settings($path); throw new LogicException('Invalid config accepted.'); }
        catch (RuntimeException $e) { hosted_check(true, 'Unsafe config rejected'); }
    }
    foreach ([['email' => 'real@example.net'], ['title' => 'Real title'], ['authors' => 'Real Author'],
        ['submitter-name' => 'Real Person'], ['website' => 'https://example.com']] as $override) {
        $input = array_replace(submission_staging_synthetic_properties()[0], $override);
        try { submission_browser_validate_fiction($input); throw new LogicException('Real data accepted.'); }
        catch (SubmissionInputException $e) { hosted_check(true, 'Synthetic declaration guard preserved'); }
    }
    $mailer = new StagingBrowserMailer(new LocalMockSubmissionMailer($directory . DIRECTORY_SEPARATOR . 'submissions-mailbox.jsonl',
        new SubmissionCrypto(str_repeat('a', 64))));
    try { $mailer->sendVerification('owned@gmail.com', 'synthetic-link'); throw new LogicException('Real recipient accepted.'); }
    catch (SubmissionMailException $e) { hosted_check(true, 'Cloud portal cannot send to SMTP recipient'); }
    $root = dirname(__DIR__);
    $gate = file_get_contents($root . '/__author-staging/gateway.php');
    hosted_check(!str_contains($gate, 'SmtpSubmissionMailer') && !str_contains($gate, 'namecheap_mailer')
        && !str_contains($gate, 'smtp-test.php'), 'Hosted gateway has no real-mail selection');
    hosted_check(str_contains(file_get_contents($root . '/__author-staging/.htaccess'), 'Require all denied')
        && trim(file_get_contents($root . '/__author-staging/app/.htaccess')) === 'Require all denied',
        'Public package and internal sources ship denied');
    hosted_check(!str_contains($gate, "'/admin/login.php'") && !str_contains($gate, "'/__staging/")
        && !str_contains($gate, 'turnstile-challenge'), 'No real admin, old harness or CLI routes');
    hosted_check(str_contains($gate, "str_starts_with(\$requestPath, \$prefix . '/')"),
        'Path matching cannot escape isolated prefix');
    echo "PASS: $checks hosted staging boundary checks; synthetic data only, no Cloud or SMTP.\n";
} finally {
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
