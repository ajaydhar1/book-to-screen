<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) {
    http_response_code(400);
    exit('Use a private receipt link without query parameters.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Private receipt | Book to Screen local testing</title>
<link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/submission-prototype.css">
<script src="../assets/js/submission-intake.js" defer></script></head>
<body class="submission-prototype"><main class="prototype-shell"><section id="receipt-page" class="step-panel" data-csrf="<?= htmlspecialchars($_SESSION['submission_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<h1>Private local receipt</h1><p>This private receipt contains no form fields. Keep the link private. Email confirmation is not approval or rights verification.</p>
<button id="check-receipt" type="button" class="primary-button">Check verification status</button>
<button id="receipt-resend" type="button" class="secondary-button">Request another verification message</button>
<p id="receipt-message" role="status" aria-live="polite"></p>
</section></main></body></html>
