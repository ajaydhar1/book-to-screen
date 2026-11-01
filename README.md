# Book to Screen

![Status](https://img.shields.io/badge/status-active-2ea44f)

Book to Screen is an editorial website that tracks upcoming book-to-screen adaptations through a curated publishing workflow. It combines automated RSS ingestion with human editorial review to create a searchable collection of film and television adaptations.

The project was built to demonstrate a complete content management workflow—from collecting industry news to publishing curated adaptation pages.

---

## Features

### Public Website

* Browse published book-to-screen adaptations
* Individual adaptation pages
* Responsive design for desktop and mobile
* SEO-friendly URLs and metadata
* XML sitemap

### Editorial Workflow

* Automatic RSS ingestion from industry news sources
* Pending leads dashboard
* Review, approve, or reject adaptation leads
* Editorial notes
* Featured article images
* Status tracking
* Duplicate prevention

### Administration

* Password-protected admin area
* SQLite database
* Automated cron job for importing new leads
* Clean editorial workflow from discovery to publication

---

## Tech Stack

* PHP
* SQLite
* HTML5
* CSS3
* JavaScript
* RSS/XML parsing
* Cron jobs
* Git & GitHub

---

## Project Goals

Book to Screen was created to provide readers with a curated database of upcoming book-to-film and book-to-television adaptations.

Rather than automatically publishing every entertainment article, the site uses a human-reviewed editorial workflow to ensure each adaptation is verified before publication.

---

## Running Locally

1. Clone the repository.

2. Create:

```
includes/config.local.php
```

and define your local administrator credentials.

3. Initialize the SQLite database.

4. Run the RSS import script manually or configure a cron job.

5. Serve the project using any PHP-enabled web server.

---

## Roadmap

* User accounts
* Search and filtering
* Adaptation timeline
* Expanded metadata
* Editorial analytics
* Additional news sources
* Image management improvements

---

## Live Website

https://booktoscreen.com

---

## Development Status

### Users Page
**Completed**
- SQLite-backed users table
- Display users from database
- Add User functionality
- Permission checks
- Temporary password validation

**Next**
- Connect dashboard statistics cards
- Edit User
- Reset Password
- Deactivate User
- Audit trail

---

## Contributing

### Local author-submission prototype

Milestone 1 is a local-only, three-step demonstration at
`/submission-prototype/`. It is not linked from public navigation and rejects
non-loopback requests. It does not load the application database or start a
session. Form values stay in the current page's DOM only: no email, submission
request, files, browser storage, or submission records are created.

Run from the repository root:

```powershell
php -d display_errors=0 -S 127.0.0.1:8083
```

Then visit `http://127.0.0.1:8083/submission-prototype/`.
When using AMPPS instead, visit
`http://localhost/book-to-screen/submission-prototype/`.
Stop the development server when finished. Do not expose it to the network.

The server queries Google Books first, with Open Library as a fallback. Set
`GOOGLE_BOOKS_API_KEY` in the PHP server's environment for reliable Google
access. Without a key, Google may reject or rate-limit requests; fallback and
manual entry remain available. Never put a real key in source control.
Optionally set `BOOK_METADATA_CONTACT` to an application contact email for
Open Library's identifying User-Agent. These settings are independent of
`includes/config.local.php`; no production/database configuration is loaded.

Both ISBN checksums are validated, and ISBN-10 is canonicalized to ISBN-13.
Providers must return matching identifiers. Normalized output includes title,
authors, subtitle, publisher, publication date, suggested genres, subjects/themes, description
when supplied, and provider provenance. Missing values remain empty/editable.
Open Library's Books API often has no description. New lookups ask before
replacing existing book fields. Each field identifies API, manual, or edited
origin. Rights and authority are self-declared, never inferred from author or
email identity.

Genres and subjects are separate editable fields in both the book and review
steps. Common genre buttons support multiple selections; free text also allows
custom comma-separated genres. Suggestions use conservative, explicit aliases
matched against whole provider taxonomy components (including slash-separated
categories), never titles or descriptions. Generic fiction/children terms do
not imply Literary Fiction or Young Adult. Unknown classifications remain
unclassified, not automatically "Other".

Subjects are case-insensitively deduplicated, stripped of common catalog,
format and classification noise, and limited to eight short topics. Up to 100
sanitized original provider terms (200 characters each) remain in the lookup
response for context, not displayed or persisted. These rules are a small
English-language vocabulary, not a comprehensive taxonomy; author correction
is expected. No genre or subject information is required to continue.

Lookup accepts only a GET request with one `isbn` query parameter and no body.
Each click makes at most one call per provider, with 3-second connect and
6-second total timeouts per call, TLS verification and a 512 KiB response limit.
Only the ISBN leaves the page; provider errors contain no API URL or key.
The local prototype uses click-triggered requests and a client cooldown, not a
shared production rate limiter or persistent cache. Do not load-test providers.
Before any launch, review [Google Books usage](https://developers.google.com/books/docs/v1/using)
and [Open Library API guidelines](https://openlibrary.org/developers/api),
configure quotas and identification, and design appropriate server-side limits.

Run the dependency-free mocked service and safety tests:

```powershell
php -d display_errors=0 tests\book-metadata-test.php
node --check assets\js\submission-prototype.js
```

For browser evaluation, use sample contact data and check lookup/loading/error
states, editing, conditional fields, all step-navigation paths, replacement
confirmation, mobile widths, keyboard focus, and the demo-only final action.
Reloading starts over. The isolated header/footer deliberately omit the shared
search form, analytics and My List storage script.

No manuscript uploads, accounts, payments, email verification, administrative
queue, submission persistence, publication, deployment or research-record
changes are part of the Milestone 1 demonstration.

### Milestone 2: isolated local submission infrastructure

The original `/submission-prototype/` remains a no-storage demonstration.
The new `/author-submissions/` workflow and `/admin/submissions.php` return
404 unless `B2S_SUBMISSIONS_LOCAL=1`, and always reject non-loopback access.
There are no public navigation links. No code loads `includes/db.php` or
production configuration from these routes. Public activation is not implemented.

#### Local fixture setup

Use **sample information only**. Create a private directory outside the web root
and an empty file named exactly `submissions-local.sqlite`. Do not use an existing
application database. Set these environment variables in the same PowerShell
process that runs the setup scripts and server:

```powershell
$env:B2S_SUBMISSIONS_LOCAL = '1'
$env:B2S_SUBMISSIONS_DB = 'C:\private-local-tests\submissions-local.sqlite'
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$rng.Dispose()
$env:B2S_SUBMISSIONS_KEY = -join ($bytes | ForEach-Object { $_.ToString('x2') })
$env:B2S_SUBMISSIONS_BASE_URL = 'http://127.0.0.1:8084/author-submissions'
$env:B2S_SUBMISSIONS_MAIL = 'mock'
$env:B2S_SUBMISSIONS_MOCK_MAILBOX = 'C:\private-local-tests\submissions-mailbox.jsonl'
# Set B2S_LOCAL_ADMIN_PASSWORD locally to a test password of at least 12 characters.
php -d display_errors=0 scripts\setup-submissions-fixture.php
php -d display_errors=0 -S 127.0.0.1:8084
```

Keep the generated key in private environment configuration,
not source control. Changing it makes existing private data unreadable.
Restrict the fixture directory to your account (Windows ACLs or Unix 0700);
the mock mailbox is encrypted and Unix file permissions are restricted.

Visit `http://127.0.0.1:8084/author-submissions/`. Complete the existing three-step
form and both local consent statements. Recording saves privately to the fixture.
The mock sender captures only recipient, verification link and timestamp in the
encrypted mailbox. It never opens an SMTP connection. Read the last ten messages
with `php scripts\read-submissions-mock-mail.php` using the same environment,
open a mock link, and press Confirm. Merely opening the link changes nothing.

Use `/author-submissions/staff-login.php` with username `local-reviewer` and the
fixture password, then `/admin/submissions.php`. This isolated login follows the
existing database-backed admin/editor session model but never loads the normal
login's production connection. The new review endpoint rechecks active admin
status and forced-password-change state for every request. Normal site sessions
do not grant access to the local fixture.

`B2S_SUBMISSIONS_MAIL=disabled` exercises recoverable delivery failures.
After 60 seconds a private receipt permits a resend. Retain the opaque receipt
link if you want to check status after leaving the form; no private form data
is stored in the browser. Receipts and verification tokens travel in fragments
and POST bodies, not URL query strings. Pages remove fragments from history on
load. Do not share receipt links, token links, mock-mailbox output or credentials.

#### Schema and migration behavior

`scripts\migrate-submissions-local.php` applies versioned, transactional migrations
only to the explicitly named outside-web-root local fixture. Web requests do not
run migrations. `submission_schema_versions` stores a checksum and timestamp;
repeat application is a no-op and editing an applied migration is rejected.
Add a new numbered migration for future changes.

Version 1 creates:

* `property_submissions`: book metadata/ISBN, corrected genre and subject arrays
  and self-declared provenance, encrypted pitch/contact/rights declarations,
  keyed email/property/payload hashes, unique idempotency and receipt hashes,
  consent/privacy versions and timestamp, email/review/delivery states,
  resend counters, reviewer attribution and optimistic revision number.
* `submission_email_tokens`: SHA-256 hashes only, submission reference,
  expiration, consumption and creation timestamps.
* `submission_events`: status transitions, staff attribution, timestamps and
  encrypted internal notes; no duplicated personal payloads or raw tokens.
* `submission_rate_limits`: keyed identity buckets, window start and attempts.

No public lead/adaptation/TMDB tables are modified or queried by the intake
service. The setup helper creates only a fixture staff table; it is not a
production staff migration. Bibliographic titles/authors are searchable in the
private database; contact, pitch, rights details and notes use authenticated
AES-256-GCM encryption. Key loss is unrecoverable without an independently
secured backup. Application-managed encryption does not replace host, backup
or database-access protection.

Verification links expire in 24 hours, are single-use, and require explicit
CSRF-protected POST confirmation. Confirming email moves `pending_email` to
`new`; it never verifies identity/rights or publishes a record. Resending
invalidates old links, requires a 60-second cooldown, and permits at most three
resends per submission. Unknown/expired/reused links and unknown/ineligible
resend requests use generic responses.

Delivery is attempted after committing the record. A delivery failure preserves
the pending record and records a non-sensitive audit event. Retry uses the same
idempotency key; a changed payload under the same key is rejected. This handles
double-clicks/concurrent requests but does not promise exactly-once delivery
after an SMTP timeout. A crashed pending delivery is recoverable by resend.

Database-backed request limits: 10 submissions per IP and per email per hour,
5 resend requests per IP per hour, 20 verification attempts per IP per hour,
30 receipt checks and 30 ISBN lookups per IP per hour; staff login allows five
attempts per 15 minutes. Account-level SMTP budgets count attempts, including
failures, conservatively. IPs/emails are HMAC-keyed rather than stored in rate
buckets. These local policies need traffic/proxy review before public use.

Milestone 4B [Namecheap SMTP preparation](docs/submissions-smtp.md) reuses
PHPMailer with private pinned port-465 SMTPS settings and a separate approval-gated
one-recipient CLI test. One separately approved real test completed authenticated
SMTP acceptance, operator-confirmed inbox delivery and browser email confirmation.
Regression tests remain offline; local/staging browser mail remains mocked or
disabled and public submissions stay disabled.

Milestone 4C [restricted-pilot readiness assessment](docs/submissions-production-readiness.md)
documents Turnstile Managed configuration, separate-database staff identity,
key/backup recovery, Namecheap compatibility and explicit launch approval gates.
Source preparation and regressions are offline; production deployment and activation are NO-GO
until the documented isolation, authentication, privacy and recovery blockers close.

The [existing Managed Turnstile widget guide](docs/submissions-turnstile.md) covers
private key entry and a nonactivating offline checker for the canonical
`booktoscreen.org` hostname and `author_submission` action. Reuse the existing
widget. The operator-confirmed controlled challenge test demonstrated fresh
acceptance and identical-token duplicate rejection; the harness was disabled
and removed. Earlier double-acceptance reports remain unexplained.
See the [4C completion record and deployment exclusions](docs/submissions-turnstile-upload.md#milestone-4c-completion-review).
Temporary harness/diagnostics and tests are source-only, excluded from Git
archives by `.gitattributes`; other deployment methods must exclude them explicitly.
Any further live testing requires new approval.

The [private hosted synthetic-staging plan](docs/submissions-hosted-staging.md)
prepares an isolated password-protected `__author-staging` package. It reuses
only the pinned staging database/key and fictional staff fixture, with mock
email. It is disabled by default; the operator reports that the protected
Namecheap copy passes disabled-state checks. Live connectivity and hosted
workflow acceptance have not yet been reported complete.
The later hosted one-shot SMTP fixture requires separate approval and confirmed
Terminal/SSH access; no browser SMTP switch or real intake is enabled.

The minimal review area lists verified records, displays declarations and consent,
warns about matching ISBN/title-author properties, and accepts CSRF-protected
status/note updates. Optimistic revision checks prevent stale overwrites.
There is no acceptance, rights-certification or publication action.

#### Anti-bot protection (disabled by default)

Turnstile complements rather than replaces CSRF, database-backed IP/email limits,
scanner-safe email confirmation and internal duplicate-property warnings. Mailbox
control still does not establish identity or rights ownership. Neither Turnstile
nor the hidden honeypot is a guarantee against human-assisted spam.
Implementation follows Cloudflare's [server validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/),
[official testing keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/),
and [CSP guidance](https://developers.cloudflare.com/turnstile/reference/content-security-policy/).

The original no-storage demonstration never loads Turnstile. Local intake also
leaves it disabled unless explicitly configured. Incomplete active configuration
fails closed with HTTP 503. Public routes are still loopback-gated; no production
activation switch was added. Additionally, `SubmissionService` refuses a
non-loopback base URL unless it uses HTTPS and a matching **production** Turnstile
configuration. Omitting the verifier cannot bypass this service-level guard.

For local testing, add these variables to the fixture server's environment:

```powershell
$env:B2S_TURNSTILE_ENV = 'development'
$env:B2S_TURNSTILE_DEV_SITE_KEY = '1x00000000000000000000AA'
$env:B2S_TURNSTILE_DEV_SECRET = '1x0000000000000000000000000000000AA'
$env:B2S_TURNSTILE_DEV_HOSTNAME = 'localhost'
$env:B2S_TURNSTILE_DEV_ACTION = 'test'
```

These are Cloudflare's public dummy credentials, not private credentials.
Development accepts only official testing keys and cannot serve a non-loopback
base URL. Cloudflare's dummy endpoint sometimes returns the fixed `example.com`
hostname, no action and `metadata.result_with_testing_key=true`; that exact
sentinel is accepted only in development with the dummy token. Normal responses
must match the configured hostname/action, and production never accepts this
exception. Use mocked production-context responses to exercise strict matching.
Dummy keys deliberately do not offer real anti-bot protection.

Future production configuration uses `B2S_TURNSTILE_ENV=production` and separate
`B2S_TURNSTILE_PROD_SITE_KEY`, `B2S_TURNSTILE_PROD_SECRET`,
`B2S_TURNSTILE_PROD_HOSTNAME` (exact hostname, no scheme or port), and
`B2S_TURNSTILE_PROD_ACTION` (recommend `author_submission`).
Production rejects official dummy site/secret keys. Keep real secrets in private
hosting environment configuration, not source control; the site key is public.
Create a separate Cloudflare widget in **Managed mode**, restrict its hostnames
to the intended production domain, and do not allow localhost on that widget.
Managed mode is a dashboard setting, not a client-side switch. This code cannot
prove a key belongs to a Managed widget or that it is valid until Siteverify.
Do not enable pre-clearance for this integration.

The widget loads only upon reaching Step 3. CSP allows only Cloudflare's challenge
script/frame origin in addition to existing self-only resources; no analytics or
unsafe-inline permissions are added. The application sends Siteverify only the
token and secret over verified HTTPS, with 3-second connection/10-second total
timeouts, no redirects and a bounded response. It does not send private form
fields or forward visitor IPs. The widget itself processes browser/network
signals with Cloudflare; approve the privacy notice and vendor/data-handling
requirements before public activation. Verification and receipt pages never
load third-party scripts.

Every configured submission attempt, including an idempotent retry, requires
Siteverify success before storing a submission or sending mail. Production also
checks exact hostname/action and a fresh challenge timestamp. Tokens are valid
for five minutes and single-use; expired/invalid/spent tokens prompt a fresh
check. Network, malformed-response or configuration failures fail closed.
The browser clears tokens after each attempt and allows an accessible retry
without losing form details. Validation failures allow corrections; ambiguous
network failures preserve the request key/payload for safe unchanged retries.
Tokens and the off-screen honeypot value are never persisted or logged.

The local intake ISBN endpoint still rate-limits **all** requests, including
cache hits and malformed ISBNs, to 30/IP/hour. A shared, canonical-ISBN cache
stores bibliographic responses only in `submissions-isbn-cache.json` alongside
the outside-web-root fixture. Successful lookups last six hours; no-results last
ten minutes; invalid ISBNs and provider errors are not cached. It is bounded to
64 editions/2 MiB. An exclusive nonblocking lock prevents provider stampedes;
concurrent misses may briefly report a busy service. Failures remain explicit
and manual entry always works. Protect this directory with filesystem permissions,
and remove the named cache file when cleaning up fixtures. The original
loopback-only demonstration lookup is unchanged and is not a public endpoint.

```powershell
php -d display_errors=0 tests\submissions-antibot-test.php
node tests\submissions-intake-js-test.js
# Optional: three live Siteverify requests using only official dummy credentials.
php -d display_errors=0 tests\submissions-antibot-test.php --live-official-tests
```

Automated tests mock successful/failed/spent/expired/wrong-host/wrong-action
responses, timeouts and malformed responses, production gating, honeypots,
no-storage-before-verification, unchanged rate limits/idempotency, and cache
normalization/expiry/errors/contention. Local SQLite and offline actual Cloud
adapters are exercised without production resources or real email.
The dependency-free JavaScript suite mocks browser/widget/fetch APIs to check
Step 3-only loading, accessible failure/expiry/retry states, token clearing,
idempotency and correction behavior without third-party calls.

Before activation, create/verify the Managed production widget, configure the
real secrets/hostname/action privately, verify strict checks on approved staging
with separate real staging keys, review CSP/accessibility/privacy behavior and
outage recovery, and configure proxy-aware IP limits/edge protections.
Distributed abuse can evade per-IP limits; the bounded local cache is not a
replacement for production edge policies. No public activation is authorized.

#### Namecheap SMTP preference and delivery approval

PHPMailer is declared in Composer and replaces a separate transactional provider.
Authenticated Namecheap SMTP is a reasonable first choice for low-volume
verification mail. Do not rely on unauthenticated PHP `mail()` simply because
hosting supports it. No real email was sent during implementation/testing.

`SmtpSubmissionMailer` supports implicit TLS on 465 or required STARTTLS on 587,
certificate verification, authentication, bounded timeouts and no SMTP debug
logging. Local HTTP routes **cannot select SMTP**, even if SMTP credentials
are present. Constructor configuration requires explicit controlled-test approval,
an exact owned recipient allowlist and an hourly budget (1–100 attempts).
Automated tests inject a mocked PHPMailer instance; no SMTP server is contacted.

For a separately approved controlled test, configure privately:
SMTP host, port, full mailbox username, mailbox/application password, authorized
From address, owned test recipient and confirmed hourly budget. Pass those values
to the transport through private configuration, not committed files. Wiring a real
transport into publicly reachable routes still requires separate approval.

`submission_smtp_from_environment()` reads `B2S_SMTP_HOST`, `B2S_SMTP_PORT`,
`B2S_SMTP_USERNAME`, `B2S_SMTP_PASSWORD`, `B2S_SMTP_FROM`,
`B2S_SMTP_TEST_RECIPIENT`, `B2S_SMTP_HOURLY_LIMIT`, and
`B2S_SMTP_CONTROLLED_TEST_APPROVED`. Leave approval unset; set it to `1` only
after an explicitly approved test. The helper is not called by local HTTP
routes or any implementation/test command.

First confirm whether the existing mailbox is **cPanel hosting email** or
**Namecheap Private Email**. Their servers and quotas differ. Private Email's
[official configuration](https://www.namecheap.com/support/knowledgebase/article.aspx/1179/2175/general-private-email-configuration-for-mail-clients-and-mobile-devices/)
specifies `mail.privateemail.com`, full-address authentication, and 465/587 with
encryption. For cPanel, use the account's current "Connect Devices" secure SMTP
hostname rather than assume that host. Confirm current per-account/mailbox hourly
and daily limits in the hosting panel or with Namecheap support; no plan or quota
was available for verification here. Set an application budget below the remaining
limit after other mailbox traffic, and retain the per-submission resend cap.

Before delivery approval, validate one SPF record authorizing the actual sender,
enable provider DKIM, and check DMARC alignment with the From domain. Review
DMARC reports before enforcing a stricter policy. Check TLS connectivity, verified
sender configuration, spam-folder placement, delivery/bounce monitoring and
mailbox reputation using only an explicitly approved address you own.
SMTP acceptance is not inbox delivery; persistent failures/bounces need operational
follow-up. Consider a transactional provider later only if measured reliability,
bounce handling or sustained volume warrants it.

#### Tests and activation prerequisites

```powershell
php -d display_errors=0 tests\submissions-test.php
php -d display_errors=0 tests\book-metadata-test.php
node --check assets\js\submission-prototype.js
node --check assets\js\submission-intake.js
```

The service suite tests validation, CSRF/admin authorization helpers, encryption,
idempotency, concurrent local writers, verification/expiry/reuse/resends, failure
rollback, rate windows, private review, non-publication, and mocked SMTP.
It runs through local SQLite and the actual SQLite Cloud adapters backed by an
offline SQLite transport. This verifies query/rowset/binding compatibility, not
live SQLite Cloud server behavior. The binding compiler now leaves SQL literals,
quoted identifiers and comments intact when replacing named parameters.
Regression cases also cover escaped quotes, repeated/prefix parameter names,
research-style duplicate queries, typed pagination, insert/update/delete row
counts, statement reuse, fetch modes and transactional rollback on both adapters.

For HTTP tests, initialize a **fresh** local fixture, run the mock-enabled server,
then, in another shell with the same key and fixture admin password:

```powershell
php -d display_errors=0 tests\submissions-http-test.php `
  'http://127.0.0.1:8084' 'C:\private-local-tests\submissions-local.sqlite'
```

This creates synthetic submissions and revokes its fixture admin at the end;
do not run it on a fixture you want to keep for interactive review. It checks
CSRF, validation, idempotency, scanner-safe verification, private receipts,
local staff login, reviewer updates, deactivation and original demo preservation.
Stop the server when finished and delete only those explicitly created test
fixture files, mailbox and test cookies.

`scripts\cleanup-submissions-local.php` provides explicit local-only cleanup:
unverified records after seven days, closed/duplicate/spam records after one year,
old verification tokens and rate buckets. Cascades remove related audit/tokens.
It is not scheduled and does not clean separately retained backups or the mock
mailbox. These sample periods require policy approval before any public use.

Before public activation: approve final consent/privacy and retention policies;
schedule and validate expiration/deletion and rate-bucket cleanup;
define withdrawal/retention/backups/key rotation and bounce monitoring; validate
a separately authorized nonproduction SQLite Cloud database, credentials and
foreign-key/transaction behavior; integrate the real staff-auth boundary; configure
HTTPS, proxy/IP limits and SMTP/DNS; approve controlled delivery testing; and obtain
separate approval for migration, deployment and public intake. No production
SQLite Cloud schema/data, real emails, navigation links or deployment are changed.

Suggestions, bug reports, and pull requests are welcome.

### Milestone 3: private literary review (local-only)

The existing local staff login now opens a responsive literary review desk at
`http://127.0.0.1:8084/admin/submissions.php`. It uses the same isolated fixture,
session, active-admin checks, CSRF and no-store/CSP headers as Milestone 2.
No public or production navigation link was added. The private navigation reuses
admin styling but does not load the normal admin navigation's production-system
links or inline script.

The default inbox contains only verified `new`, `reviewing`, `needs_information`
and `research_candidate` records, prioritized by status and oldest submission.
Separate views show unverified, suspected spam, duplicate and closed records.
Overall counts, bibliographic-only search (title/author/ISBN), status, UTC date
range, exact case-normalized genre, role, verification, deterministic sorting
and 20-record pagination are available. Inbox verification cannot be relaxed by
query parameters. Search treats `%` and `_` literally. Other filters combine
with AND. Role/genre are declared metadata, not verified identity classifications.
Contact/pitch/rights payloads are never decrypted to search or list the queue.
Search/filter forms and pagination use CSRF-protected POST so private search
terms do not enter application-generated URLs or ordinary access logs. Filter
state is carried in the returned form, not persisted in sessions/browser storage.
This differs intentionally from existing public/admin GET searches for privacy.

Detail pages show editable reviewer evaluations alongside the original metadata,
separate genres/themes, pitch/format, contact, role, authority, rights declarations,
arrangements, email/consent timestamps, duplicate links and paginated activity.
Provenance is explicitly submitter-reported; provider suggestions and email
confirmation do not establish identity, rights ownership or independently
verified bibliographic facts.

Administrators can begin review, mark needs-information or a research candidate,
close, flag duplicate/spam, reopen verified records, and add encrypted internal
notes. Needs-information is **an internal task only**, not an email request.
Flags alone do not delete a record. Optional qualitative observations cover
premise, conflict, characters, cinematic qualities, format, distinctiveness,
information completeness and outstanding rights/authorization questions
(1,000 characters per criterion). They can be edited or cleared, are encrypted
at rest and have encrypted snapshots in the audit history. Notes are append-only
activity entries, not overwritten by later evaluations. There are no commercial
scores, rights approvals, referrals or publication actions.

All updates use POST, CSRF and optimistic revisions, with staff attribution.
On a validation/stale-update error, attempted text stays visible for comparison
with the current history. Unverified records can receive notes or be closed/
flagged but cannot enter actionable statuses. Later email confirmation preserves
reviewer quarantine and increments revision to invalidate stale forms.
The optional rejected-activity summary is intentionally omitted: existing HMAC
rate buckets do not distinguish accepted from rejected activity reliably, and
no additional spam payloads or personal analytics were introduced.

#### Version 2 reviewer migration

Version 1 remains unchanged and checksum-compatible. Version 2 transactionally
rebuilds the submission/token/event tables to extend the status CHECK constraint
with `research_candidate`; it copies all records, token hashes, encrypted payloads
and audit IDs before removing old tables, with foreign-key cascades retained.
It adds `submitter_role`, encrypted `evaluation_payload`, indexed
`submission_genres` projections, and dashboard/date/role indexes. Existing roles/
genres are backfilled in batches of 100 using the **original encryption key**.
A missing/wrong key or corrupt payload aborts and rolls back version 2. No private
payload is made searchable or published.

Web routes do not migrate. With the same explicitly configured local fixture
and original key, stop the test server, take a private fixture backup if needed,
then run `php scripts\migrate-submissions-local.php` before restarting.
For a new fixture, `scripts\setup-submissions-fixture.php` applies both versions.
Do not run either script against production. Live SQLite Cloud table-renaming,
foreign-key and transactional DDL behavior still require an approved staging test.

#### Local review and validation

Use the Milestone 2 environment/setup instructions above. Submit sample data
locally, read the mock mailbox, explicitly confirm the sample email, then sign in
as `local-reviewer`. Use the dashboard and detail pages to evaluate and review.
Keep the mock mailbox/key/fixture outside the web root; real SMTP and public
intake remain unavailable. Existing fixture retention cleanup is explicit,
unscheduled and still requires policy approval; flagging in the reviewer UI
never invokes cleanup.

```powershell
php -d display_errors=0 tests\submissions-review-test.php
php -d display_errors=0 tests\submissions-test.php
php -d display_errors=0 tests\submissions-antibot-test.php
php -d display_errors=0 tests\book-metadata-test.php
node tests\submissions-intake-js-test.js
# With a fresh mock-enabled fixture/server, same key and fixture admin password:
php -d display_errors=0 tests\submissions-http-test.php `
  'http://127.0.0.1:8084' 'C:\private-local-tests\submissions-local.sqlite'
```

Reviewer tests cover populated version-1 upgrades, missing/wrong-key rollback,
retained token/audit cascades, counts/filter/search/date/sort/pagination, transitions,
encrypted evaluation/history, authorization/stale updates, spam quarantine and
2,000 synthetic records per SQLite/offline-Cloud adapter. List queries have
bounded result sets and do not decrypt whole-table private payloads; indexes
support status/date/role/genre filters. Literal substring searches, global counts,
genre options and priority sorting still require work proportional to matching
volume for filtered views. The unfiltered priority inbox explicitly uses its
partial expression index to avoid sorting the whole queue; other filters let
SQLite choose their indexes. Production-scale measurements may justify approved FTS/keyset pagination
later. No new dependency or public workflow was introduced.

Live integration still requires approved nonproduction Cloud validation, production
staff authorization/session integration, privacy/retention/backups/key recovery,
SMTP/DNS delivery testing, real Managed Turnstile staging verification, and proxy/
HTTPS review. Suggested next step: review the local UX and workflow labels first,
then plan a separately authorized controlled staging exercise. This milestone
does not begin activation work.

---

### Milestone 4A: SQLite Cloud staging preparation

See [the staging preparation and approval-gated validation plan](docs/submissions-sqlitecloud-staging.md)
for isolated project/credential requirements, migration compatibility checks,
encryption/backup considerations and the future read-only/write approval gates.
The first separately approved read-only staging connection is complete;
provisioning and Cloud migrations are not implemented by the preparation tools.

`scripts\prepare-submissions-staging.php` accepts an external **nonsecret** JSON
identity manifest and reports the existing migration checksums without loading
application configuration, reading credentials, opening a database or running SQL.
Its output validates configuration claims only, not effective Cloud isolation or
permission to connect/write. Run its offline guards with:

```powershell
php tests\submissions-staging-test.php
```

The separately gated [read-only staging diagnostic](docs/submissions-staging-readonly.md)
supports the exact reported `book-to-screen-staging` project and
`author-submissions-staging.sqlite` database. It requires an external nonsecret
manifest and a dedicated private environment URL, never production configuration.
Default preparation uses `--offline`; a real connection requires both
`--connect-readonly` and the documented explicit approval environment value.
Further live runs need separate approval. Admin credentials are write-capable
and require explicit risk acknowledgement; CloudSync keys are not assumed compatible.

```powershell
php -d display_errors=0 tests\submissions-staging-diagnostic-test.php
```

For private URL entry, the Git-ignored CLI-only
`scripts\submissions-staging.local.php` template and
`scripts\check-submissions-staging-config.php` provide offline hostname extraction.
Follow the read-only guide's independent Connect-panel comparison before setting
the confirmed hostname. No connection follows configuration; separate approval
is still required. Never force-add the private file.

The pre-migration diagnostic now recognizes only the exact documented, empty
`_sqliteai_vector` table and its exact automatic index as reported extension
metadata. Success means **no application schema/data**, not necessarily an empty
schema. Modified/populated metadata and unknown objects remain blocked.
See [the controlled staging migration validation procedure](docs/submissions-staging-migrations.md)
for migration checksums, identity/FK/rollback checks, Free-plan recovery limitations
and the separate empty-install approval. The dedicated
`scripts\migrate-submissions-staging.php` runner is now implemented and offline-tested:
`--offline` reports the plan without loading credentials or connecting;
`--apply-empty-v1-v2` requires the exact migration-specific approval environment
value and private local config. It uses a single verified staging connection,
checks complete installed schema/ledger/FK/zero-row state and repeatability,
and preserves the known vector metadata pair. No live migration or new Cloud
connection was run while preparing/testing it.

```powershell
php -d display_errors=0 tests\submissions-staging-migration-test.php
```

The [three-property synthetic staging harness](docs/submissions-staging-synthetic.md)
is prepared for a separately approved service-level submission/verification/
review test. It uses a preserved private staging key, in-memory mock mail and
an approved isolated fictional reviewer source (no Cloud users/schema changes).
Repeat runs only verify a complete matching fixture set; partial/foreign states
stop without cleanup. No live synthetic test was performed during preparation.

The [isolated staging browser integration](docs/submissions-staging-browser.md)
reuses the existing form and reviewer pages behind an allowlisted 127.0.0.1
router. Offline mode uses local SQLite and mocked Cloud/ISBN/Turnstile responses;
live mode requires separate exact approval, the original preserved staging key,
and per-request identity/schema/fixture checks. Fictional staff credentials and
encrypted mock emails remain outside Git. This does not enable public intake or
establish production staff authentication. Browser integration was prepared and
tested offline first. Subsequent separately authorized live validation completed
the empty v1/v2 installation, three-property synthetic service test and manual
browser submission of `[SYNTHETIC] The Last Signal from Europa`, mock verification,
and a saved **Under review** status/internal research note. This was staging-only,
not production deployment, real SMTP delivery or production staff authentication.

```powershell
php -d display_errors=0 tests\submissions-staging-browser-guards-test.php
```

See the browser integration guide for private fixture preparation, startup,
authenticated mock-mail inspection, HTTP workflow tests and the live approval gate.
See the [Milestone 4A final review](docs/milestone4a-final-review.md) for the complete
commit inventory, manual browser outcome, security/compatibility findings,
1,103 passing regression checks and remaining production/SMTP approval boundaries.

## License

The MIT License applies to the source code only.

Original editorial content—including adaptation summaries, reviews, articles, and other written content—is **© 2026 Ajay Dhar. All rights reserved.**
