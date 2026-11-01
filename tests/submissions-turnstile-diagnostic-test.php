<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/turnstile-challenge.php';
$checks = 0;
function diagnostic_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
$state = [];
$first = submission_challenge_diagnostic_start($state, 'synthetic-token', 'Fresh token');
diagnostic_check($first['test_number'] === 1 && $first['same_as_previous_siteverify_token'] === null, 'First token is not comparable');
$state['diagnostic_previous'] = $first['token_id'];
$second = submission_challenge_diagnostic_start($state, 'synthetic-token', 'Reused token');
diagnostic_check($second['test_number'] === 2 && $second['same_as_previous_siteverify_token'] === true
    && $second['token_id'] === $first['token_id'], 'Server digest equality identifies identical bytes');
$different = submission_challenge_diagnostic_start($state, 'different-token', 'Invalid token');
diagnostic_check($different['same_as_previous_siteverify_token'] === false, 'Different bytes differ');
$other = [];
$otherFirst = submission_challenge_diagnostic_start($other, 'synthetic-token', 'Fresh token');
diagnostic_check($otherFirst['token_id'] !== $first['token_id'], 'Identifiers are scoped to independent diagnostic sessions');
$rotated = submission_challenge_diagnostic_start($state, 'synthetic-token', 'Fresh token', 123);
diagnostic_check($rotated['token_id'] !== $first['token_id'] && $rotated['test_number'] === 1
    && $rotated['same_as_previous_siteverify_token'] === null, 'New fixed test window changes correlation scope without changing budget');
$missing = submission_challenge_diagnostic_start($state, '', 'Missing token');
diagnostic_check($missing['token_id'] === null && !$missing['siteverify_made'], 'Missing token has no correlation identifier or provider call');
diagnostic_check(!str_contains(json_encode($state), 'synthetic-token')
    && !str_contains(json_encode($second), $state['diagnostic_key']), 'No raw token or private HMAC key in displayed state');
$now = time();
$events = [];
$calls = 0;
$verifier = new SubmissionTurnstile('production', str_repeat('s', 22), str_repeat('k', 32),
    'booktoscreen.org', 'author_submission',
    static function () use (&$calls, $now): array {
        $calls++;
        return ['success' => true, 'hostname' => 'booktoscreen.org', 'action' => 'author_submission',
            'challenge_ts' => gmdate('c', $now), 'error-codes' => ['timeout-or-duplicate', 'private-code', ['unsafe']],
            'private-response-field' => 'must-not-display'];
    }, static function (array $event) use (&$events): void { $events[] = $event; });
$result = submission_challenge_verify($verifier, 'synthetic-token', $now, static function (): void {});
diagnostic_check($calls === 1 && $result['status'] === 200, 'Observer does not generate requests or change acceptance');
diagnostic_check($events[0]['success'] === true && $events[0]['hostname_pass'] && $events[0]['action_pass']
    && $events[0]['timestamp_pass'], 'Boolean success and context flags observed');
diagnostic_check($events[0]['error_codes'] === ['timeout-or-duplicate']
    && !str_contains(json_encode($events), 'private-code') && !str_contains(json_encode($events), 'must-not-display'),
    'Provider diagnostics are bounded to allowlisted derived fields');
$before = $calls;
$events = [];
$missingResult = submission_challenge_verify($verifier, '', $now, static function (): void {
    throw new RuntimeException('Missing token must not reserve.');
});
diagnostic_check($missingResult['status'] === 422 && $calls === $before && $events === [],
    'Local missing rejection cannot cause diagnostic provider calls');
$limited = submission_challenge_verify($verifier, 'synthetic-token', $now, static function (): void {
    throw new SubmissionRateException('Synthetic budget exhausted.');
});
diagnostic_check($limited['status'] === 429 && $calls === $before && $events === [],
    'Budget rejection cannot trigger diagnostic provider calls');
echo "PASS: $checks safe diagnostic checks; synthetic state and mocked provider only.\n";
