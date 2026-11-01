<?php

declare(strict_types=1);

require_once __DIR__ . '/local-only.php';
$localIntake = defined('B2S_LOCAL_INTAKE_VIEW') && B2S_LOCAL_INTAKE_VIEW === true;
$stagingBrowser = defined('B2S_STAGING_BROWSER');
$turnstile = $localIntake ? ($stagingBrowser ? submission_browser_turnstile($submissionBrowserConfig) : SubmissionTurnstile::fromEnvironment()) : null;
$turnstileEnabled = $turnstile !== null && $turnstile->environment !== 'disabled';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('This prototype does not accept submissions.');
}
$offlineBrowser = $stagingBrowser && $submissionBrowserConfig['mode'] === 'offline';
$challengeSource = $turnstileEnabled && !$offlineBrowser ? ' https://challenges.cloudflare.com' : '';
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self'{$challengeSource}; frame-src " . ($turnstileEnabled && !$offlineBrowser ? 'https://challenges.cloudflare.com' : "'none'") . "; style-src 'self'; img-src 'self'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Share Your Story | Book to Screen Prototype</title>
    <link rel="icon" href="../favicon.png">
    <link rel="stylesheet" href="../assets/css/site.css">
    <link rel="stylesheet" href="../assets/css/submission-prototype.css">
    <script src="../assets/js/submission-prototype.js" defer></script>
    <?php if ($localIntake): ?><script src="../assets/js/submission-intake.js" defer></script><?php endif; ?>
</head>
<body class="submission-prototype">
    <a class="skip-link" href="#prototype-main">Skip to the prototype</a>
    <header class="prototype-header">
        <a class="prototype-brand" href="../">Book <span>to</span> Screen</a>
        <span class="prototype-tag"><?= $stagingBrowser ? ($offlineBrowser ? 'Offline staging mock' : 'SQLite Cloud staging') : 'Local prototype' ?></span>
    </header>
    <main id="prototype-main" class="prototype-shell">
        <div class="prototype-intro">
            <p class="eyebrow">Every screen story begins somewhere</p>
            <h1 class="page-title">Share the story.<br>Explore the possibilities.</h1>
            <p class="page-intro">Try a simpler way to introduce your book for preliminary adaptation research. An ISBN can do some of the typing for you.</p>
        </div>
        <aside class="prototype-notice" aria-label="Demonstration notice">
            <?php if ($stagingBrowser): ?>
            <strong><?= $offlineBrowser ? 'OFFLINE MOCK browser test' : 'SQLite Cloud staging browser test' ?> — not a public launch.</strong>
            Use fictional data only: titles beginning [SYNTHETIC], author and submitter names beginning Invented, and example.com email addresses.
            Leave websites blank. Only mocked email is captured privately. Never enter real personal information or rights claims.
            <?= $offlineBrowser ? 'ISBN and Turnstile providers are mocked offline.' : ((defined('B2S_HOSTED_STAGING') && B2S_HOSTED_STAGING === true) ? 'Only ISBNs go to metadata providers; the final security check uses the existing Managed widget. Real author intake and real email are disabled.' : 'Only ISBNs go to metadata providers; the security check uses official Cloudflare development credentials.') ?>
            <?php elseif ($localIntake): ?>
            <strong>Local infrastructure test, not a public launch.</strong> Use sample data only.
            The final action stores a private record in the explicitly configured local fixture.
            Real email delivery is disabled. Only ISBNs are sent to metadata providers.
            <?php if ($turnstileEnabled): ?>The final security check uses Cloudflare Turnstile; it does not receive your book, contact, pitch or rights fields from this application.<?php endif; ?>
            <?php else: ?>
            <strong>A walkthrough, not a submission.</strong> Nothing you enter is saved or sent.
            Only an ISBN is sent for book lookup. Use sample contact details while testing.
            Leaving or reloading this page clears your work.
            <?php endif; ?>
        </aside>
        <noscript><p class="prototype-notice">JavaScript is required for this demonstration. No submission can be made from this page.</p></noscript>
        <div id="prototype-flow" hidden<?php if ($localIntake): ?> data-mode="local-intake" data-csrf="<?= htmlspecialchars($_SESSION['submission_csrf'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?><?php if ($offlineBrowser): ?> data-security-script="/__staging/turnstile.js"<?php endif; ?>>
            <nav aria-label="Submission progress">
                <ol class="step-progress">
                    <li><button type="button" data-step-nav="1" aria-current="step"><span>1</span> Your Book</button></li>
                    <li><button type="button" data-step-nav="2"><span>2</span> Adaptation Details</button></li>
                    <li><button type="button" data-step-nav="3"><span>3</span> <?= $localIntake ? 'Review &amp; Local Test' : 'Review &amp; Demo' ?></button></li>
                </ol>
            </nav>
            <p id="validation-message" class="validation-message" role="alert" hidden></p>
            <section class="step-panel" data-step="1" aria-labelledby="step-1-title">
                <p class="eyebrow">Step 1 of 3</p>
                <h2 id="step-1-title" tabindex="-1">First, your book.</h2>
                <p class="step-intro">Look up an edition, or start with what you know. You can edit every detail.</p>
                <div class="lookup-box">
                    <label for="isbn">ISBN-10 or ISBN-13 <span class="optional">(optional)</span></label>
                    <div class="lookup-controls">
                        <input id="isbn" type="text" maxlength="40" autocomplete="off" spellcheck="false" placeholder="e.g. 9780140328721" aria-describedby="isbn-help lookup-status">
                        <button id="lookup-book" type="button" class="primary-button">Look Up Book</button>
                    </div>
                    <p id="isbn-help" class="field-help">Spaces and hyphens are fine. An ISBN identifies a book edition, not rights ownership.</p>
                    <button id="manual-entry" type="button" class="text-button">No ISBN? Enter book details manually</button>
                    <p id="lookup-status" class="lookup-status" role="status" aria-live="polite" aria-atomic="true">Lookup is optional. Your book details can always be entered below.</p>
                    <div id="lookup-result" class="lookup-result" hidden>
                        <p id="lookup-preview"></p>
                        <button id="apply-lookup" type="button" class="secondary-button">Use these details (replace book fields)</button>
                        <button id="discard-lookup" type="button" class="text-button">Keep my current details</button>
                    </div>
                </div>
                <p class="field-help">Required fields are marked *. The small labels show where each value came from.</p>
                <div class="field-grid">
                    <div class="field"><label for="title">Book title *</label><input id="title" type="text" required maxlength="300" autocomplete="off" data-book-field="title" aria-describedby="origin-title"><small id="origin-title" class="field-origin">Not entered</small></div>
                    <div class="field"><label for="authors">Author / creator names *</label><input id="authors" type="text" required maxlength="2000" autocomplete="off" data-book-field="authors" aria-describedby="origin-authors"><small id="origin-authors" class="field-origin">Not entered</small></div>
                    <div class="field"><label for="subtitle">Subtitle</label><input id="subtitle" type="text" maxlength="500" autocomplete="off" data-book-field="subtitle" aria-describedby="origin-subtitle"><small id="origin-subtitle" class="field-origin">Not entered</small></div>
                    <div class="field"><label for="publisher">Publisher</label><input id="publisher" type="text" maxlength="300" autocomplete="off" data-book-field="publisher" aria-describedby="origin-publisher"><small id="origin-publisher" class="field-origin">Not entered</small></div>
                    <div class="field"><label for="publication_date">Publication date</label><input id="publication_date" type="text" maxlength="100" autocomplete="off" placeholder="Year or date, if known" data-book-field="publication_date" aria-describedby="origin-publication_date"><small id="origin-publication_date" class="field-origin">Not entered</small></div>
                    <div class="field"><label for="genre">Primary Genre(s)</label><input id="genre" type="text" maxlength="500" autocomplete="off" placeholder="e.g. Science Fiction, Young Adult" data-book-field="genres" aria-describedby="genre-help origin-genre"><small id="origin-genre" class="field-origin">Not entered</small><p id="genre-help" class="field-help">Optional suggestions from provider terms, not confirmed classifications. Edit or remove them; separate multiple genres with commas.</p>
                        <details><summary>Choose common genres</summary><div class="genre-choices" aria-label="Common genre choices">
                            <?php foreach (['Science Fiction', 'Fantasy', 'Romance', 'Mystery', 'Thriller', 'Horror', 'Historical Fiction', 'Literary Fiction', 'Young Adult', 'Memoir / Biography', 'Nonfiction', 'Other'] as $genre): ?>
                                <button type="button" class="secondary-button" data-genre="<?= htmlspecialchars($genre, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false"><?= htmlspecialchars($genre, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div></details>
                    </div>
                    <div class="field full-width"><label for="subjects">Subjects / Themes</label><input id="subjects" type="text" maxlength="1000" autocomplete="off" placeholder="e.g. Survival, Friendship" data-book-field="subjects" aria-describedby="subjects-help origin-subjects"><small id="origin-subjects" class="field-origin">Not entered</small><p id="subjects-help" class="field-help">Optional keywords. Up to eight filtered provider topics are suggested; edit, add or remove any. Raw classification terms are not displayed.</p></div>
                    <div class="field full-width"><label for="description">Book description</label><textarea id="description" rows="5" maxlength="5000" autocomplete="off" data-book-field="description" aria-describedby="description-help origin-description"></textarea><small id="origin-description" class="field-origin">Not entered</small><p id="description-help" class="field-help">Optional bibliographic description. Your adaptation pitch comes next.</p></div>
                </div>
                <div class="step-actions"><span class="field-help">No ISBN required.</span><button type="button" class="primary-button" data-next="2">Continue to adaptation details &rarr;</button></div>
            </section>
            <section class="step-panel" data-step="2" aria-labelledby="step-2-title" hidden>
                <p class="eyebrow">Step 2 of 3</p>
                <h2 id="step-2-title" tabindex="-1">What makes it a screen story?</h2>
                <p class="step-intro">A short introduction is enough. Unknown rights details are welcome; please do not include confidential contract terms.</p>
                <div class="field-grid">
                    <div class="field full-width"><label for="pitch">Short film or television adaptation pitch *</label><textarea id="pitch" rows="4" maxlength="1000" required autocomplete="off" aria-describedby="pitch-help"></textarea><p id="pitch-help" class="field-help">What is the story, and why might it work on screen? Up to 1,000 characters.</p></div>
                    <div class="field"><label for="screen-format">Preferred screen format</label><select id="screen-format"><option value="unknown">Not sure / open to possibilities</option value="film">Feature film</option><option value="tv">Television series</option><option value="limited">Limited series</option></select></div>
                    <div class="field"><label for="website">Public book or author website</label><input id="website" type="url" maxlength="500" autocomplete="off" placeholder="https://example.com" aria-describedby="website-help"><p id="website-help" class="field-help">Optional. This prototype will not visit the link.</p></div>
                </div>
                <h3>Your connection to the book</h3>
                <div class="field-grid">
                    <div class="field"><label for="submitter-name">Your name *</label><input id="submitter-name" type="text" required maxlength="200" autocomplete="off" placeholder="Use a sample name"></div>
                    <div class="field"><label for="email">Your email *</label><input id="email" type="email" required maxlength="254" autocomplete="off" placeholder="reader@example.com" aria-describedby="email-help"><p id="email-help" class="field-help"><?= $localIntake ? 'Sample email only. No real email is sent; confirmation uses the local mock mailbox.' : 'For demonstration only. No email will be sent or verified.' ?></p></div>
                    <div class="field"><label for="role">Your role *</label><select id="role" required><option value="">Choose your role</option><option value="author">Author / co-author</option><option value="agent">Agent</option><option value="rights-holder">Rights holder</option><option value="scout">Independent scout</option></select></div>
                    <div class="field"><label for="authorization">Claimed authorization *</label><select id="authorization" required><option value="">Choose what applies</option><option value="owner">I control the relevant screen rights</option><option value="representative">I am authorized by the rights controller</option><option value="recommendation">I am recommending only, without a mandate</option><option value="unknown">I am unsure of my authority</option></select></div>
                    <div class="field full-width" id="authority-details-field" hidden><label for="authority-details">Who authorized you, and in what capacity?</label><input id="authority-details" type="text" maxlength="500" autocomplete="off"><p class="field-help">Optional brief explanation. Do not paste contracts or private contact details.</p></div>
                </div>
                <p id="authority-note" class="rights-note">A name, email address or author role does not prove screen-rights ownership. All authority here is self-declared.</p>
                <h3>Rights and existing arrangements</h3>
                <div class="field-grid">
                    <div class="field"><label for="rights-controller">Who controls screen adaptation rights?</label><select id="rights-controller"><option value="unknown">Unknown</option><option value="self">I do</option><option value="author">Author / co-authors</option><option value="agent">Agent / agency</option><option value="publisher">Publisher</option><option value="other">Another party / shared control</option></select></div>
                    <div class="field" id="rights-name-field" hidden><label for="rights-name">Rights controller's name</label><input id="rights-name" type="text" maxlength="300" autocomplete="off"><p class="field-help">Optional. No private third-party contact details.</p></div>
                    <div class="field full-width"><label for="arrangement">Known options, agreements or adaptations</label><select id="arrangement"><option value="unknown">Unknown</option><option value="none">None known</option><option value="discussions">Discussions underway</option><option value="shopping">Shopping agreement</option><option value="option">Existing option</option><option value="sale">Rights licensed / sold</option><option value="previous">Previous adaptation</option><option value="other">Other arrangement</option></select></div>
                    <div class="field full-width" id="arrangement-details-field" hidden><label for="arrangement-details">Brief arrangement details</label><textarea id="arrangement-details" rows="3" maxlength="1000" autocomplete="off"></textarea><p class="field-help">Optional public or non-confidential context, affected rights, territories or expiry if known. A previous adaptation does not establish current availability.</p></div>
                </div>
                <div class="step-actions"><button type="button" class="secondary-button" data-back="1">&larr; Back to your book</button><button type="button" class="primary-button" data-next="3">Review your details &rarr;</button></div>
            </section>
            <section class="step-panel" data-step="3" aria-labelledby="step-3-title" hidden>
                <p class="eyebrow">Step 3 of 3</p>
                <h2 id="step-3-title" tabindex="-1">A final look at your story.</h2>
                <p class="step-intro">Review the details below. You can go back and change anything without losing your work.</p>
                <div class="review-heading"><h3>Your book</h3><button type="button" class="text-button" data-back="1">Edit book details</button></div>
                <dl id="book-review" class="review-list"></dl>
                <div class="review-heading"><h3>Adaptation details &amp; your connection</h3><button type="button" class="text-button" data-back="2">Edit adaptation details</button></div>
                <dl id="adaptation-review" class="review-list"></dl>
                <div class="prototype-notice">
                    <?php if ($stagingBrowser): ?>
                    <strong>Controlled staging test.</strong> This stores a private fictional record in <?= $offlineBrowser ? 'an offline SQLite fixture' : 'the approved SQLite Cloud staging database' ?>. No real email is sent.
                    <?php elseif ($localIntake): ?>
                    <strong>Local test submission.</strong> This creates a private local record only. No real email is sent.
                    <?php else: ?>
                    <strong>This is not a real submission.</strong> No information will be saved, emailed or submitted.
                    <?php endif; ?>
                    Book to Screen does not guarantee representation, producer introductions, publication or adaptation opportunities.
                    Self-declared authority has not been verified.
                </div>
                <?php if ($localIntake): ?>
                <div class="field"><label><input id="consent" type="checkbox"> I confirm the information reflects my knowledge and stated authority. I retain my rights and understand no representation, introductions or adaptation opportunity is promised.</label></div>
                <div class="field"><label><input id="privacy" type="checkbox"> I agree to private <?= $stagingBrowser ? 'staging' : 'local' ?> storage and staff review of this sample submission. No contracts, manuscripts or confidential third-party contacts are included.</label></div>
                <div class="submission-honeypot" aria-hidden="true"><label for="company-website">Automation field: leave empty</label><input id="company-website" type="text" tabindex="-1" autocomplete="off" maxlength="200"></div>
                <?php if ($turnstileEnabled): ?>
                <div id="submission-security" class="field" data-site-key="<?= htmlspecialchars($turnstile->siteKey, ENT_QUOTES, 'UTF-8') ?>" data-action="<?= htmlspecialchars($turnstile->action, ENT_QUOTES, 'UTF-8') ?>">
                    <h3>One quick security check</h3>
                    <p class="field-help"><?= $offlineBrowser ? 'The offline provider mock still requires an accepted test token on the server. No Cloudflare connection is made.' : 'Cloudflare checks browser signals to help prevent spam. Your form details stay here until you choose to record the local test.' ?></p>
                    <div id="turnstile-widget"></div>
                    <p id="security-message" role="status" aria-live="polite">The security check starts when you reach this step.</p>
                    <button id="retry-security" type="button" class="secondary-button">Retry security check</button>
                </div>
                <?php else: ?><p class="field-help">Anti-bot verification is disabled for this loopback fixture. Public intake remains unavailable.</p><?php endif; ?>
                <?php endif; ?>
                <div class="step-actions"><button type="button" class="secondary-button" data-back="2">&larr; Back to adaptation details</button><button id="complete-demo" type="button" class="primary-button"><?= $localIntake ? 'Record local test submission' : 'Complete prototype demo &mdash; no submission' ?></button></div>
                <p id="demo-complete" class="demo-complete" role="status" tabindex="-1" hidden>Demo complete. Nothing was submitted, saved or emailed. Your details remain only on this page until you leave or reload it.</p>
                <?php if ($localIntake): ?><button id="resend-verification" type="button" class="secondary-button" hidden>Retry local verification delivery</button><p id="receipt-link-container" hidden><a id="receipt-link">Keep this private receipt link to check status or retry after leaving this page</a></p><?php endif; ?>
            </section>
        </div>
    </main>
    <footer class="prototype-footer">Book to Screen &middot; Private local evaluation &middot; No accounts, uploads or payments</footer>
</body>
</html>
