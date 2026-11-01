<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
submission_require_post_csrf();
session_write_close();
submission_json_action(static function () use ($submissionService): array {
    $input = submission_read_json();
    $receipt = $input['receipt'] ?? '';
    if (!is_string($receipt)) {
        throw new SubmissionInputException(['receipt' => 'Invalid request.']);
    }
    $submissionService->resend($receipt, $_SERVER['REMOTE_ADDR'], time());
    return ['message' => 'If the request is eligible, another verification message has been requested. This local build uses mocked or disabled delivery only.'];
});
