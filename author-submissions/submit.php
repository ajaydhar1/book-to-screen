<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
submission_require_post_csrf();
session_write_close();
submission_json_action(static function () use ($submissionService): array {
    $input = submission_read_json();
    $key = $input['request_key'] ?? '';
    if (!is_string($key)) {
        throw new SubmissionInputException(['request_key' => 'Invalid request identifier.']);
    }
    return $submissionService->submit($input, $key, $_SERVER['REMOTE_ADDR'], time());
});
