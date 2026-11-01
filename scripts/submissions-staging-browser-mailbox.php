<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || !defined('B2S_STAGING_BROWSER')) { http_response_code(404); exit; }
require_once __DIR__ . '/../author-submissions/bootstrap.php';
if (submission_admin_user($submissionDb, $_SESSION) === null) {
    http_response_code(403);
    exit('Sign in as the fictional local reviewer to inspect private mock delivery.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) { http_response_code(405); exit('GET without query parameters required.'); }
$path = $submissionBrowserConfig['mailbox'];
$messages = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
if ($messages === false) throw new RuntimeException('Private mock mailbox read failed.');
$crypto = new SubmissionCrypto(submission_browser_key($submissionBrowserConfig));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Private staging mock mailbox</title><link rel="stylesheet" href="/assets/css/site.css"></head>
<body><main><h1>Private staging mock mailbox</h1><p>Fictional messages only. No real email sent. These single-use links are private; do not share screenshots or logs.</p>
<?php foreach (array_slice($messages, -10) as $message):
    $data = json_decode($crypto->decrypt($message), true, 16, JSON_THROW_ON_ERROR);
    if (!preg_match('/^[A-Za-z0-9._+-]+@example\.com$/iD', $data['email'] ?? '')
        || !str_starts_with($data['link'] ?? '', $submissionBrowserConfig['base_url'] . '/verify.php#token=')) {
        throw new RuntimeException('Unexpected mock message.');
    }
?>
<p><?= htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8') ?>:
<a href="<?= htmlspecialchars($data['link'], ENT_QUOTES, 'UTF-8') ?>">Open private verification link</a></p>
<?php endforeach; ?>
<p><a href="/admin/submissions.php">Reviewer dashboard</a></p></main></body></html>
