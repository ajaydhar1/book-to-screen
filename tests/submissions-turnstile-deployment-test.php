<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$checks = 0;
function deployment_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
foreach (['__turnstile-validation', 'includes/submissions/turnstile-challenge.php',
    'scripts/check-submissions-turnstile-config.php', 'tests'] as $path) {
    $process = proc_open(['git', 'check-attr', 'export-ignore', '--', $path],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Could not check deployment exclusions.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    deployment_check(proc_close($process) === 0 && $errors === ''
        && trim($output) === $path . ': export-ignore: set', 'Test-only path excluded from Git archives');
}
$rules = file_get_contents($root . '/__turnstile-validation/.htaccess');
deployment_check(trim($rules) === "Options -Indexes\nRequire all denied"
    || trim(str_replace("\r\n", "\n", $rules)) === "Options -Indexes\nRequire all denied",
    'Committed harness ships deny-all, never hosting credentials');
foreach (glob($root . '/author-submissions/*.php') as $path) {
    $source = file_get_contents($path);
    deployment_check(!str_contains($source, 'submission_challenge_')
        && !str_contains($source, 'B2S_TURNSTILE_VALIDATION_SETTINGS_FILE')
        && !str_contains($source, 'diagnostic_start'), 'Author route has no temporary diagnostic wiring');
}
deployment_check(!str_contains(file_get_contents($root . '/assets/js/submission-intake.js'), 'same_as_previous_siteverify_token'),
    'Author browser has no replay diagnostic panel');
echo "PASS: $checks deployment/diagnostic isolation checks; no network or deployment.\n";
