<?php

declare(strict_types=1);

if (!defined('B2S_HOSTED_STAGING') || B2S_HOSTED_STAGING !== true) { http_response_code(404); exit; }
require_once __DIR__ . '/author-submissions/bootstrap.php';
if (submission_admin_user($submissionDb, $_SESSION) === null) { http_response_code(403); exit('Fictional reviewer login required.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) { http_response_code(405); exit; }
$lines = is_file($submissionBrowserConfig['mailbox'])
    ? file($submissionBrowserConfig['mailbox'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
if ($lines === false) throw new RuntimeException('Private mock mailbox unavailable.');
$crypto = new SubmissionCrypto(submission_browser_key($submissionBrowserConfig));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private fictional mailbox</title></head>
<body><h1>Private fictional mailbox</h1><p>No real email sent. Never share verification links.</p>
<?php foreach (array_slice($lines, -10) as $line):
    $data = json_decode($crypto->decrypt($line), true, 8, JSON_THROW_ON_ERROR);
    if (!preg_match('/^[A-Za-z0-9._+-]+@example\.com$/iD', $data['email'] ?? '')
        || !str_starts_with($data['link'] ?? '', SUBMISSION_HOSTED_BASE . '/verify.php#token=')) {
        throw new RuntimeException('Unexpected fictional message.');
    }
?>
<p><a href="<?= htmlspecialchars($data['link'], ENT_QUOTES, 'UTF-8') ?>">Open fictional verification link</a></p>
<?php endforeach; ?>
<a href="admin/submissions.php">Fictional reviewer</a></body></html>
