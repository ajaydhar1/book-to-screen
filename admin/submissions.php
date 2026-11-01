<?php

declare(strict_types=1);

require_once __DIR__ . '/../author-submissions/bootstrap.php';
$admin = submission_admin_user($submissionDb, $_SESSION);
if ($admin === null) {
    http_response_code(403);
    exit('An active local administrator session is required. Sign in at /author-submissions/staff-login.php.');
}
require_once __DIR__ . '/../includes/functions.php';

function review_url(array $params = []): string
{
    return 'submissions.php' . ($params === [] ? '' : '?' . http_build_query($params));
}
function review_date(mixed $timestamp): string
{
    return $timestamp === null ? 'Not confirmed' : gmdate('M j, Y H:i', (int) $timestamp) . ' UTC';
}
function review_value(mixed $value): string
{
    return is_array($value) ? implode(', ', $value) : ((string) $value !== '' ? (string) $value : 'Not provided');
}
function review_status(string $status): string
{
    return SUBMISSION_REVIEW_STATUSES[$status] ?? 'Pending email';
}
function review_declaration(string $field, string $value): string
{
    $labels = [
        'authorization' => ['owner' => 'Claims control of relevant screen rights', 'representative' => 'Claims authorization from the rights controller',
            'recommendation' => 'Recommendation only, without a mandate', 'unknown' => 'Unsure of authority'],
        'rights-controller' => ['unknown' => 'Unknown', 'self' => 'Submitter', 'author' => 'Author / co-authors',
            'agent' => 'Agent / agency', 'publisher' => 'Publisher', 'other' => 'Another party / shared control'],
        'arrangement' => ['unknown' => 'Unknown', 'none' => 'None known', 'discussions' => 'Discussions underway',
            'shopping' => 'Shopping agreement', 'option' => 'Existing option', 'sale' => 'Rights licensed / sold',
            'previous' => 'Previous adaptation', 'other' => 'Other arrangement'],
    ];
    return $labels[$field][$value] ?? review_value($value);
}

$notice = '';
$detail = null;
$dashboard = null;
$id = $_GET['id'] ?? '';
$posted = [];
$dashboardInput = $_GET;
$dashboardRequest = ($_POST['dashboard'] ?? null) === '1';
if (!is_string($id) || ($id !== '' && preg_match('/^[a-f0-9]{32}$/', $id) !== 1)) {
    http_response_code(422);
    $notice = 'Invalid submission identifier.';
    $id = '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submission_require_post_csrf();
    if ($dashboardRequest && $id === '') {
        $dashboardInput = $_POST;
    } else {
        try {
            $reviewId = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            $note = $_POST['note'] ?? '';
            $evaluation = $_POST['evaluation'] ?? null;
            $revision = filter_var($_POST['revision'] ?? '', FILTER_VALIDATE_INT);
            if (!is_string($reviewId) || $reviewId !== $id || !is_string($status) || !is_string($note)
                || ($evaluation !== null && !is_array($evaluation)) || $revision === false || $revision < 0) {
                throw new SubmissionInputException(['review' => 'Invalid review input.']);
            }
            $posted = ['status' => $status, 'note' => $note, 'evaluation' => $evaluation];
            $submissionService->review($reviewId, $status, $note, (int) $admin['id'], $revision, time(), $evaluation);
            header('Location: ' . review_url(['id' => $reviewId, 'notice' => 'saved']), true, 303);
            exit;
        } catch (SubmissionInputException $e) {
            http_response_code(422);
            $notice = $e->getMessage() . ' ' . implode(' ', $e->errors);
        } catch (SubmissionConflictException $e) {
            http_response_code(409);
            $notice = $e->getMessage() . ' Your attempted notes are shown below. Compare them with the latest history before saving again.';
        } catch (Throwable $e) {
            error_log('Local submission review update failed.');
            http_response_code(503);
            $notice = 'Review could not be saved. Your attempted text is shown below; refresh and compare the latest history before retrying.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}
try {
    if ($id !== '') {
        $eventPage = $_GET['activity_page'] ?? '1';
        if (!is_string($eventPage) || !ctype_digit($eventPage) || (int) $eventPage < 1 || (int) $eventPage > 1000000) {
            throw new SubmissionInputException(['activity' => 'Choose a valid activity page.']);
        }
        $detail = $submissionService->detail($id, (int) $eventPage);
        if ($detail === null) {
            http_response_code(404);
            $notice = 'Private submission not found.';
        }
    } elseif ($notice === '') {
        if (array_key_exists('search', $_GET) && $_GET['search'] !== '') {
            throw new SubmissionInputException(['search' => 'Use the private search form rather than a URL search parameter.']);
        }
        $dashboard = $submissionService->dashboard($dashboardInput);
    }
} catch (SubmissionInputException $e) {
    http_response_code(422);
    $notice = $e->getMessage() . ' ' . implode(' ', $e->errors);
} catch (Throwable $e) {
    error_log('Local submission review could not be read.');
    http_response_code(503);
    $notice = 'Private review information is unavailable. Check the local schema migration and configuration.';
}
$roles = ['author' => 'Author', 'agent' => 'Agent', 'rights-holder' => 'Rights holder', 'scout' => 'Independent scout'];
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Literary review | Book to Screen</title>
<link rel="stylesheet" href="../assets/css/site.css">
<link rel="stylesheet" href="../assets/css/submission-prototype.css">
<link rel="stylesheet" href="../assets/css/admin-nav.css">
<link rel="stylesheet" href="../assets/css/submission-review.css">
</head>
<body class="submission-prototype submission-review">
<a class="skip-link" href="#review-main">Skip to private review</a>
<header class="review-header">
    <a class="prototype-brand" href="submissions.php">Book <span>to</span> Screen</a>
    <span class="prototype-tag">Private local review</span>
    <form action="../author-submissions/staff-login.php" method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>"><button type="submit" name="logout" value="1" class="secondary-button">Sign out</button></form>
</header>
<nav class="admin-subnav" aria-label="Private review sections"><div class="admin-subnav__inner">
    <a class="admin-subnav__link<?= $id === '' ? ' is-active' : '' ?>" href="submissions.php">Review inbox</a>
    <a class="admin-subnav__link" href="<?= h(review_url(['status' => 'all', 'verification' => 'unverified'])) ?>">Unverified</a>
    <a class="admin-subnav__link" href="<?= h(review_url(['status' => 'spam', 'verification' => 'all'])) ?>">Suspected spam</a>
    <a class="admin-subnav__link" href="<?= h(review_url(['status' => 'duplicate', 'verification' => 'all'])) ?>">Duplicates</a>
    <a class="admin-subnav__link" href="<?= h(review_url(['status' => 'closed', 'verification' => 'all'])) ?>">Closed</a>
</div></nav>
<main id="review-main" class="review-shell">
<p class="eyebrow">Literary property research</p>
<h1><?= $detail !== null ? h($detail['book_title']) : 'Your private review desk' ?></h1>
<p class="review-intro">Signed in as <?= h($admin['display_name']) ?>. Preliminary research only. No publication, referrals, rights approvals or outgoing information requests.</p>
<?php if ($notice !== ''): ?><p class="review-notice" role="alert"><?= h($notice) ?></p><?php endif; ?>
<?php if (($_GET['notice'] ?? '') === 'saved'): ?><p class="review-notice" role="status">Private review saved.</p><?php endif; ?>
<?php if ($dashboard !== null): $filters = $dashboard['filters']; ?>
<div class="review-counts" aria-label="Overall submission counts">
<?php foreach (['inbox' => 'Actionable inbox', 'new' => 'New', 'reviewing' => 'Under review', 'needs_information' => 'Needs information', 'research_candidate' => 'Research candidates', 'closed' => 'Closed', 'duplicate' => 'Duplicates', 'spam' => 'Suspected spam', 'unverified' => 'Unverified'] as $key => $label): ?>
    <a class="review-count" href="<?= h(review_url($key === 'unverified' ? ['status' => 'all', 'verification' => 'unverified'] : ['status' => $key, 'verification' => in_array($key, ['closed','duplicate','spam'], true) ? 'all' : 'verified'])) ?>"><span><?= h($label) ?></span><strong><?= (int) $dashboard['counts'][$key] ?></strong></a>
<?php endforeach; ?>
</div>
<section class="review-card" aria-labelledby="filter-title">
<h2 id="filter-title">Find a property</h2>
<form method="post" action="submissions.php" class="review-filters">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>">
    <input type="hidden" name="dashboard" value="1">
    <div class="field"><label for="review-search">Title, author or ISBN</label><input id="review-search" name="search" value="<?= h($filters['search']) ?>" maxlength="300" autocomplete="off"></div>
    <div class="field"><label for="filter-status">Review status</label><select id="filter-status" name="status">
        <?php foreach (['inbox' => 'Actionable inbox', 'all' => 'All statuses', 'pending_email' => 'Pending email', ...SUBMISSION_REVIEW_STATUSES] as $value => $label): ?><option value="<?= h($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label for="filter-verification">Email verification</label><select id="filter-verification" name="verification"><?php foreach (['verified' => 'Confirmed', 'unverified' => 'Unconfirmed', 'all' => 'Both'] as $value => $label): ?><option value="<?= h($value) ?>"<?= $filters['verification'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="filter-role">Submitter role</label><select id="filter-role" name="role"><option value="">All roles</option><?php foreach ($roles as $value => $label): ?><option value="<?= h($value) ?>"<?= $filters['role'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="filter-genre">Primary genre</label><input id="filter-genre" name="genre" list="review-genres" value="<?= h($filters['genre']) ?>" maxlength="300" placeholder="Any genre"><datalist id="review-genres"><?php foreach ($dashboard['genres'] as $genre): ?><option value="<?= h($genre['genre_label']) ?>"><?php endforeach; ?></datalist></div>
    <div class="field"><label for="filter-from">Submitted from (UTC)</label><input id="filter-from" type="date" name="from" value="<?= h($filters['from']) ?>"></div>
    <div class="field"><label for="filter-to">Submitted through (UTC)</label><input id="filter-to" type="date" name="to" value="<?= h($filters['to']) ?>"></div>
    <div class="field"><label for="filter-sort">Sort</label><select id="filter-sort" name="sort"><?php foreach (['priority' => 'Status priority, oldest first', 'oldest' => 'Oldest first', 'newest' => 'Newest first', 'title' => 'Title A-Z'] as $value => $label): ?><option value="<?= h($value) ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
    <div class="review-filter-actions"><button class="primary-button" type="submit">Apply filters</button><a href="submissions.php">Reset to inbox</a></div>
</form>
<p class="field-help">Counts above are overall totals, not filtered totals. The actionable inbox always excludes unverified, closed, duplicate and spam records. Search covers bibliographic fields only.</p>
</section>
<section class="review-card" aria-labelledby="results-title">
<div class="review-section-heading"><h2 id="results-title"><?= $filters['status'] === 'inbox' ? 'Actionable submissions' : 'Filtered submissions' ?></h2><p><?= $dashboard['total'] ?> matching &middot; Page <?= $filters['page'] ?> of <?= $dashboard['pages'] ?></p></div>
<?php if ($dashboard['rows'] === []): ?><p class="review-empty">No submissions match this view. Try another filter or return to the inbox.</p><?php endif; ?>
<ul class="review-properties">
<?php foreach ($dashboard['rows'] as $row): ?>
<li><div><a class="review-property-title" href="<?= h(review_url(['id' => $row['id']])) ?>"><?= h($row['book_title']) ?></a><p>by <?= h($row['book_authors']) ?></p><p class="field-help"><?= h($row['isbn'] ?? 'No ISBN') ?> &middot; <?= h($roles[$row['submitter_role']] ?? 'Role unavailable') ?> &middot; <?= h(review_date($row['created_at'])) ?></p></div><div><span class="review-badge"><?= h(review_status($row['review_status'])) ?></span><p class="field-help"><?= $row['verified_at'] === null ? 'Email unconfirmed' : 'Email confirmed' ?></p></div></li>
<?php endforeach; ?>
</ul>
<nav class="review-pagination" aria-label="Submission pages">
<?php foreach (['Previous' => $filters['page'] - 1, 'Next' => $filters['page'] + 1] as $label => $targetPage):
    if ($targetPage < 1 || $targetPage > $dashboard['pages']) continue; ?>
<form method="post" action="submissions.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>"><input type="hidden" name="dashboard" value="1">
<?php foreach (array_replace($filters, ['page' => $targetPage]) as $key => $value): ?><input type="hidden" name="<?= h($key) ?>" value="<?= h((string) $value) ?>"><?php endforeach; ?>
<button class="secondary-button" type="submit"><?= h($label) ?></button></form>
<?php endforeach; ?>
</nav></section>
<?php elseif ($detail !== null): ?>
<p><a href="submissions.php">&larr; Back to review inbox</a></p>
<div class="review-detail-grid">
<div>
<section class="review-card"><div class="review-section-heading"><h2>The literary property</h2><span class="review-badge"><?= h(review_status($detail['review_status'])) ?></span></div>
<p class="review-notice">Book metadata, pitch, authority and rights below are submitter declarations, including any provider-suggested metadata they reviewed. They have not been independently verified.</p>
<dl class="review-list">
<?php foreach (['title' => 'Title', 'authors' => 'Author / creator', 'isbn' => 'ISBN', 'subtitle' => 'Subtitle', 'publisher' => 'Publisher', 'publication_date' => 'Publication date', 'genres' => 'Primary genres', 'subjects' => 'Subjects / themes', 'description' => 'Description'] as $key => $label): ?>
<div><dt><?= h($label) ?></dt><dd><?= h(review_value($detail['book'][$key] ?? '')) ?><?php $origin = $detail['book']['provenance'][$key === 'genres' ? 'genre' : $key] ?? null; if ($origin): ?><small class="field-origin">Submitter-reported provenance: <?= h($origin['provider'] . ' / ' . $origin['state']) ?></small><?php endif; ?></dd></div>
<?php endforeach; ?>
</dl><h3>Adaptation pitch</h3><p class="review-prose"><?= h($detail['private']['pitch']) ?></p><p>Preferred format: <?= h(['unknown' => 'Open / unsure', 'film' => 'Film', 'tv' => 'Television series', 'limited' => 'Limited series'][$detail['private']['screen-format']]) ?></p>
</section>
<section class="review-card"><h2>Submitter declarations</h2><dl class="review-list">
<?php foreach (['submitter-name' => 'Name', 'email' => 'Contact email', 'role' => 'Role', 'authorization' => 'Claimed authority', 'authority-details' => 'Authorization context', 'rights-controller' => 'Declared screen-rights controller', 'rights-name' => 'Rights controller name', 'arrangement' => 'Known arrangements', 'arrangement-details' => 'Arrangement context', 'website' => 'Public website (not visited)'] as $key => $label): ?>
<div><dt><?= h($label) ?></dt><dd><?= h($key === 'role' ? ($roles[$detail['private'][$key]] ?? $detail['private'][$key]) : review_declaration($key, $detail['private'][$key])) ?></dd></div>
<?php endforeach; ?></dl></section>
<section class="review-card"><h2>Possible duplicates</h2><p class="field-help">Canonical ISBN or normalized title/author matches are warnings, not proof. Editions and multiple representatives may be legitimate. Up to ten recent matches are shown.</p>
<?php if ($detail['duplicates'] === []): ?><p>No matching submission found.</p><?php else: ?><ul><?php foreach ($detail['duplicates'] as $duplicate): ?><li><a href="<?= h(review_url(['id' => $duplicate['id']])) ?>"><?= h($duplicate['book_title']) ?></a> by <?= h($duplicate['book_authors']) ?> &middot; <?= h(review_status($duplicate['review_status'])) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
</div>
<aside>
<section class="review-card"><h2>Recorded facts</h2><dl class="review-list">
<div><dt>Submitted</dt><dd><?= h(review_date($detail['created_at'])) ?></dd></div>
<div><dt>Email confirmed</dt><dd><?= h(review_date($detail['verified_at'])) ?></dd></div>
<div><dt>Last reviewed</dt><dd><?= h(review_date($detail['reviewed_at'])) ?><?php if ($detail['reviewed_by_user_id']): ?> &middot; reviewer ID <?= (int) $detail['reviewed_by_user_id'] ?><?php endif; ?></dd></div>
<div><dt>Consent versions</dt><dd><?= h($detail['consent_version'] . ' / ' . $detail['privacy_version']) ?><br><?= h(review_date($detail['consent_at'])) ?></dd></div>
</dl><p class="rights-note">Email confirmation establishes mailbox control only. Identity, authorization and adaptation-rights ownership have not been independently verified.</p></section>
</aside>
</div>
<section class="review-card" aria-labelledby="review-action-title"><h2 id="review-action-title">Private review &amp; evaluation</h2>
<p>Optional qualitative observations, not automated commercial-viability scores. “Needs information” records an internal task only; no email is sent. Flags do not delete submissions.</p>
<form method="post" action="<?= h(review_url(['id' => $id])) ?>">
<input type="hidden" name="csrf" value="<?= h($_SESSION['submission_csrf']) ?>">
<input type="hidden" name="id" value="<?= h($detail['id']) ?>">
<input type="hidden" name="revision" value="<?= (int) $detail['revision'] ?>">
<div class="review-filters">
<div class="field"><label for="review-status">Review status</label><select id="review-status" name="status">
<?php foreach (['pending_email' => 'Pending email (leave unconfirmed)', ...SUBMISSION_REVIEW_STATUSES] as $status => $label):
    if ($status === 'pending_email' && ($detail['verified_at'] !== null || $detail['review_status'] !== 'pending_email')) continue;
    if ($detail['verified_at'] === null && !in_array($status, ['pending_email','closed','duplicate','spam'], true)) continue; ?>
<option value="<?= h($status) ?>"<?= ($posted['status'] ?? $detail['review_status']) === $status ? ' selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?></select></div>
<div class="field"><label for="review-note">Add an internal note (2,000 characters)</label><textarea id="review-note" name="note" maxlength="2000" rows="4"><?= h($posted['note'] ?? '') ?></textarea></div>
</div>
<details class="review-evaluation"<?= $posted !== [] || implode('', $detail['evaluation']) !== '' ? ' open' : '' ?>><summary>Preliminary research evaluation (optional)</summary><div class="review-filters">
<?php foreach (SUBMISSION_EVALUATION_FIELDS as $key => $label): ?><div class="field"><label for="evaluation-<?= h($key) ?>"><?= h($label) ?></label><textarea id="evaluation-<?= h($key) ?>" name="evaluation[<?= h($key) ?>]" maxlength="1000" rows="3"><?php $value = $posted['evaluation'][$key] ?? $detail['evaluation'][$key]; echo h(is_string($value) ? $value : ''); ?></textarea></div><?php endforeach; ?>
</div></details>
<button class="primary-button" type="submit">Save private review</button>
</form></section>
<section class="review-card"><h2>Notes &amp; activity history</h2>
<ol class="review-activity"><?php foreach ($detail['events'] as $event): ?><li><div><strong><?= h(ucwords(str_replace('_', ' ', $event['event_type']))) ?></strong><span class="field-help"><?= h(review_date($event['created_at'])) ?> &middot; <?= h($event['actor_name'] ?? ($event['actor_user_id'] === null ? 'System' : 'Reviewer #' . $event['actor_user_id'])) ?></span></div>
<?php if ($event['from_status'] !== null || $event['to_status'] !== null): ?><p><?= h(review_status($event['from_status'] ?? 'pending_email')) ?> &rarr; <?= h(review_status($event['to_status'] ?? 'pending_email')) ?></p><?php endif; ?>
<?php if ($event['note'] !== ''): ?><p class="review-prose"><?= h($event['note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol>
<nav class="review-pagination" aria-label="Activity pages"><span>Page <?= $detail['event_page'] ?> of <?= $detail['event_pages'] ?></span>
<?php if ($detail['event_page'] > 1): ?><a href="<?= h(review_url(['id' => $id, 'activity_page' => $detail['event_page'] - 1])) ?>">Newer activity</a><?php endif; ?>
<?php if ($detail['event_page'] < $detail['event_pages']): ?><a href="<?= h(review_url(['id' => $id, 'activity_page' => $detail['event_page'] + 1])) ?>">Older activity</a><?php endif; ?></nav>
</section>
<?php endif; ?>
<footer class="prototype-footer">Private local fixture &middot; No public publication or rights approval</footer>
</main></body></html>
