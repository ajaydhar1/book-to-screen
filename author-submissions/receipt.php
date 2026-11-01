<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
submission_require_post_csrf();
session_write_close();
submission_json_action(static function () use ($submissionService, $submissionDb): array {
    $input = submission_read_json();
    $receipt = $input['receipt'] ?? '';
    if (!is_string($receipt)) {
        throw new SubmissionInputException(['receipt' => 'Invalid receipt.']);
    }
    submission_rate_limit($submissionDb, new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY')),
        'receipt', $_SERVER['REMOTE_ADDR'], time(), 30, 3600);
    $state = $submissionService->receipt($receipt);
    return ['state' => $state === null ? 'unavailable' : (
        $state['verified_at'] !== null ? 'verified' : ($state['delivery_status'] === 'failed' ? 'delivery_failed' : 'pending_verification')
    )];
});
