<?php

declare(strict_types=1);

require_once __DIR__ . '/../author-submissions/bootstrap.php';
$admin = submission_admin_user($submissionDb, $_SESSION);
if ($admin === null) {
    http_response_code(403);
    exit('An active local administrator session is required. Sign in at /author-submissions/staff-login.php.');
}
require_once __DIR__ . '/../includes/functions.php';

$notice = '';
$id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submission_require_post_csrf();
    try {
        $reviewId = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? '';
        $note = $_POST['note'] ?? '';
        $revision = filter_var($_POST['revision'] ?? '', FILTER_VALIDATE_INT);
        if (!is_string($reviewId) || !is_string($status) || !is_string($note) || $revision === false || $revision < 0) {
            throw new SubmissionInputException(['review' => 'Invalid review input.']);
        }
        $submissionService->review($reviewId, $status, $note, (int) $admin['id'], $revision, time());
        header('Location: submissions.php?id=' . rawurlencode($reviewId) . '&notice=saved', true, 303);
        exit;
    } catch (SubmissionInputException | SubmissionConflictException $e) {
        http_response_code(409);
        $notice = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Local submission review update failed.');
        http_response_code(503);
        $notice = 'Review could not be saved. Refresh and retry.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}
$detail = null;
$queue = [];
try {
    if ($id !== '') {
        $detail = $submissionService->detail($id);
        if ($detail === null) {
            http_response_code(404);
            $notice = 'Verified submission not found.';
        }
    } else {
        $page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
        $queue = $submissionService->queue($page);
    }
} catch (Throwable $e) {
    error_log('Local submission review could not be read.');
    http_response_code(503);
    $notice = 'Private review information is unavailable.';
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Private submissions | Book to Screen local review</title>
<link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/submission-prototype.css"></head>
<body class="submission-prototype"><main class="prototype-shell">
<p class="eyebrow">Local admin-only review</p><h1>Private property submissions</h1>
<p>Signed in as <?= h($admin['display_name']) ?>. Only email-verified submissions are listed. Authority and rights remain self-declared.</p>
<form action="../author-submissions/staff-login.php" method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>"><button type="submit" name="logout" value="1" class="secondary-button">Sign out</button></form>
<?php if ($notice !== ''): ?><p role="alert"><?= h($notice) ?></p><?php endif; ?>
<?php if (($_GET['notice'] ?? '') === 'saved'): ?><p role="status">Review saved.</p><?php endif; ?>
<?php if ($detail !== null): ?>
<section class="step-panel"><h2><?= h($detail['book_title']) ?></h2>
<p><a href="submissions.php">Back to queue</a></p>
<h3>Book metadata</h3><dl class="review-list">
<?php foreach ($detail['book'] as $field => $value): ?>
<div><dt><?= h($field) ?></dt><dd><?= h(is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $value) ?></dd></div>
<?php endforeach; ?></dl>
<h3>Contact, pitch, rights and authorization</h3><dl class="review-list">
<?php foreach ($detail['private'] as $field => $value): ?><div><dt><?= h($field) ?></dt><dd><?= h($value) ?></dd></div><?php endforeach; ?>
<div><dt>Email verified at (UTC)</dt><dd><?= h(gmdate('Y-m-d H:i:s', (int) $detail['verified_at'])) ?></dd></div>
<div><dt>Consent versions / time</dt><dd><?= h($detail['consent_version'] . ' / ' . $detail['privacy_version'] . ' / ' . gmdate('Y-m-d H:i:s', (int) $detail['consent_at'])) ?></dd></div>
</dl>
<h3>Possible duplicate properties</h3>
<p>Matches by canonical ISBN or normalized title and author are warnings, not proof. Multiple representatives or editions can be legitimate.</p>
<ul><?php foreach ($detail['duplicates'] as $duplicate): ?><li><?= h($duplicate['book_title']) ?> by <?= h($duplicate['book_authors']) ?> (<?= h($duplicate['review_status']) ?>)</li><?php endforeach; ?></ul>
<?php if ($detail['duplicates'] === []): ?><p>No matching submission found.</p><?php endif; ?>
<h3>Update review</h3>
<form method="post" action="submissions.php?id=<?= h($id) ?>">
<input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>">
<input type="hidden" name="id" value="<?= h($detail['id']) ?>">
<input type="hidden" name="revision" value="<?= h((string) $detail['revision']) ?>">
<div class="field"><label for="review-status">Status</label><select id="review-status" name="status">
<?php foreach (['new','reviewing','needs_information','closed','duplicate','spam'] as $status): ?>
<option value="<?= h($status) ?>"<?= $detail['review_status'] === $status ? ' selected' : '' ?>><?= h($status) ?></option>
<?php endforeach; ?></select></div>
<div class="field"><label for="review-note">Internal note (up to 2,000 characters)</label><textarea id="review-note" name="note" maxlength="2000" rows="4"></textarea></div>
<button class="primary-button" type="submit">Save private review</button></form>
<h3>Recent audit events</h3><dl class="review-list">
<?php foreach ($detail['events'] as $event): ?><div><dt><?= h($event['event_type']) ?></dt><dd><?= h(gmdate('Y-m-d H:i:s', (int) $event['created_at'])) ?> UTC; reviewer ID <?= h((string) ($event['actor_user_id'] ?? 'system')) ?>; <?= h(($event['from_status'] ?? '') . ' -> ' . ($event['to_status'] ?? '')) ?><br><?= h($event['note']) ?></dd></div><?php endforeach; ?>
</dl></section>
<?php elseif ($id === ''): ?>
<section class="step-panel"><h2>Verified submissions</h2>
<?php if ($queue === []): ?><p>No verified submissions on this page.</p><?php endif; ?>
<ul><?php foreach ($queue as $row): ?><li><a href="submissions.php?id=<?= h($row['id']) ?>"><?= h($row['book_title']) ?></a> by <?= h($row['book_authors']) ?> &mdash; <?= h($row['review_status']) ?></li><?php endforeach; ?></ul>
<?php if ($page > 1): ?><a href="submissions.php?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
<?php if (count($queue) === 20): ?><a href="submissions.php?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
</section><?php endif; ?>
</main></body></html>
