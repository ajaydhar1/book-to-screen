<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submission_require_post_csrf();
    session_write_close();
    submission_json_action(static function () use ($submissionService): array {
        $input = submission_read_json();
        $token = $input['token'] ?? '';
        if (!is_string($token)) {
            $token = '';
        }
        $verified = $submissionService->verify($token, $_SERVER['REMOTE_ADDR'], time());
        return ['state' => $verified ? 'verified' : 'unavailable', 'message' => $verified
            ? 'Email confirmed. The submission is now in the private local review queue. Rights ownership is not verified.'
            : 'This link is invalid, expired or already used. Return to your receipt to request another link.'];
    });
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) {
    http_response_code(400);
    exit('Open a verification link without query parameters.');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirm email | Book to Screen local testing</title>
<link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/submission-prototype.css">
<script src="../assets/js/submission-intake.js" defer></script></head>
<body class="submission-prototype"><main class="prototype-shell">
<section id="verification-page" class="step-panel" data-csrf="<?= htmlspecialchars($_SESSION['submission_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<h1>Confirm your email</h1><p>Opening this page does not verify anything. Press Confirm to use your single-use link. Email control does not prove rights ownership.</p>
<button id="confirm-email" type="button" class="primary-button">Confirm email</button>
<p id="verification-message" role="status" aria-live="polite"></p>
<p><a href="./">Return to local intake</a></p>
</section></main></body></html>
