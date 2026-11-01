# Milestone 4A: isolated browser staging

## Scope and architecture

The existing three-step form, ISBN normalization/provider lookup, verification
pages, staff login, reviewer dashboard and detail page are reused. This is not a
new public intake or production authentication implementation.

The dedicated CLI launcher binds PHP's development server to **127.0.0.1 only**.
Its router explicitly serves the submission pages, reviewer page, private mock
mailbox and seven UI assets, including the dashboard's admin navigation and
reviewer stylesheets. It never returns `false` to PHP's web-root dispatcher.
All other paths (including application login, configuration, scripts, other
administration pages and production database entry points) return 404.

The browser database wrapper delegates submission operations to the existing
SQLite Cloud adapter. Only the two exact staff authentication/authorization
queries use a separate local `fictional-reviewer.sqlite`. Activity history uses
the same fictional actor, 900001, as the three-property service fixture. No Cloud
`users` table, copied production account or schema change is needed.

### Offline mode

An isolated `submissions-local.sqlite` implements the Cloud SDK response protocol.
The preparation command creates migrations 1 and 2 and the original three
synthetic records locally through the existing migration and service harnesses.
It generates an **independent offline key**, never replacing the preserved Cloud
key. Cloud connections, Google/Open Library calls and Cloudflare calls are mocked.
The widget supplies a dummy token; server verification still rejects missing or
unaccepted tokens. This mock is not selectable in live mode.

### Live mode (separately gated)

The approved target is fixed:

- Project: `book-to-screen-staging` / `cutaojpqvz`
- Endpoint: `cutaojpqvz.g2.sqlite.cloud:8860`
- Database: `author-submissions-staging.sqlite`

It reuses the existing Git-ignored PHP connection configuration, external
identity manifest and original preserved encryption-key JSON. It never uses
`includes/db.php`, production connection configuration or fallback credentials.
TLS verification remains enabled.

The private connection file remains CLI-only. The launcher validates and loads
it, then supplies the URL to its child server through the private
`B2S_STAGING_BROWSER_CONNECTION_STRING` process environment (never command-line
arguments or logs). Approved `cli-server` requests read that inherited value;
they must not include the CLI-only file, which exits with HTTP 404 under the
browser SAPI. Missing child configuration fails closed with no fallback.
Offline launches explicitly remove the inherited URL from their child environment.

Live mode uses only Cloudflare's official development credentials and the
existing server-side Siteverify implementation. It does not select disabled
Turnstile or change production key requirements. Google Books/Open Library
receive only ISBNs, with the existing cache, limits and normalization.

## Private preparation

Create a **new empty directory outside the repository/web root**, restricted to
the current OS user before creating any credentials or fixtures. On Windows:

```powershell
$directory = 'C:\B2S-browser-private\offline'
New-Item -ItemType Directory -Path $directory -ErrorAction Stop | Out-Null
$acl = New-Object System.Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true, $false)
$sid = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
$acl.AddAccessRule($rule)
Set-Acl -LiteralPath $directory -AclObject $acl
php -d display_errors=0 scripts\submissions-staging-browser.php --prepare-offline $directory 8085
```

Preparation refuses to overwrite a populated directory. It creates private
configuration, random reviewer credentials, a password-hash-only local staff
database, encrypted mock-mail capture location, private session directory and
an ignore-all `.gitignore`. On Unix, restrict the parent directory to mode 0700
first. Never commit private files, export tokens into logs or share credentials.

To **prepare live configuration without making a connection**, use another new,
private directory and the existing manifest/key paths:

```powershell
php -d display_errors=0 scripts\submissions-staging-browser.php --prepare-live 'C:\B2S-browser-private\live' 'C:\private\staging-diagnostic-identity.json' 'C:\private\staging-key.json' 8086
```

This creates only a local fictional reviewer and local browser configuration.
It checks the connection configuration offline and locally tests key recovery.
It does not generate a new staging key, query Cloud or alter Cloud data.

Privately open `reviewer-login.json` in your editor to obtain the generated
`staging-reviewer` password. Do not paste it into ordinary logs, source files or
chat. The password is not printed by the launcher.

## Offline startup, validation and shutdown

From the repository root:

```powershell
php -d display_errors=0 scripts\submissions-staging-browser.php --serve-offline 'C:\B2S-browser-private\offline\browser.json'
```

Visit `http://127.0.0.1:8085/author-submissions/`.
Stop with **Ctrl+C in the launch terminal**. For an agent-managed process, stop
the specific launcher process tree, not all PHP processes. Verify this port is
no longer responding. Keep the key, fixture and mock mailbox for later review.

In a second terminal, the offline HTTP test uses only the explicitly configured
offline fixture; it refuses a live configuration:

```powershell
php -d display_errors=0 tests\submissions-staging-browser-test.php 'C:\B2S-browser-private\offline\browser.json'
php -d display_errors=0 tests\submissions-staging-browser-guards-test.php
php -d display_errors=0 tests\submissions-staging-browser-routing-test.php
```

The HTTP test creates a fictional property, verifies it, saves an encrypted
evaluation, tests reload/conflicts and quarantines it as spam. It also creates
one expired token **in the local fixture only**. It does not delete/reset data.
Existing rate limits apply: run write-heavy HTTP tests on a newly prepared
offline fixture, or wait for the configured cooldown; do not bypass/reset the
limits to rerun. The mock guard suite uses independent in-memory databases.

## Live preflight and approval

**Do not execute the following live command until separately authorized.**

The required approval is:

> I authorize the loopback-only `browser-synthetic-v1` workflow on project
> `book-to-screen-staging`, endpoint `cutaojpqvz.g2.sqlite.cloud:8860`, database
> `author-submissions-staging.sqlite`, using the original preserved key,
> fictional local reviewer, mocked email and official development Turnstile.
> I confirm all other staging writers are stopped and accept the documented
> Free-plan recovery limitations for additional disposable fictional records.
> Do not change schema/settings, access production or send real emails.

Only after approval:

```powershell
$env:B2S_STAGING_BROWSER_APPROVAL = 'cutaojpqvz/author-submissions-staging.sqlite/browser-synthetic-v1'
$env:B2S_STAGING_BROWSER_WRITERS_STOPPED = '1'
try {
    php -d display_errors=0 scripts\submissions-staging-browser.php --serve-live-staging 'C:\B2S-browser-private\live\browser.json'
} finally {
    Remove-Item Env:\B2S_STAGING_BROWSER_APPROVAL
    Remove-Item Env:\B2S_STAGING_BROWSER_WRITERS_STOPPED
}
```

Startup performs only read operations before serving:

1. Exact manifest project/endpoint/database, independently confirmed host,
   approved admin identity, TLS and selected database/user/database-list checks.
2. Pinned migration versions 1/2 and checksums, complete schema/index/constraint
   definitions, unchanged ledger and exact empty SQLite-Vector metadata pair.
3. Foreign keys ON, `foreign_key_check` empty and `integrity_check` equal to `ok`.
4. The preserved key decrypts all private fields, evaluations and audit notes.
5. First launch requires the exact completed three-property synthetic fixture,
   including statuses, tokens, audits, genres, revisions and rate buckets.

A key-authenticated private baseline permits later intentional browser reviews
and new fictional properties to survive server restart. Later checks still
require all three original immutable payloads and idempotency identities,
matching encrypted/search projections, valid decryption and fictional markers.
All records must remain fictional. Keep this a small controlled test, not a load test.

Every dynamic request repeats identity, schema/ledger, vector, foreign-key,
integrity and fixture checks on the same SDK connection used by the action.
The browser inspects identity/settings separately, reads schema metadata exactly
once, and reuses that request-local snapshot for exact schema validation.
The five application-table counts are one read with exact column/order/row
validation and nonnegative integer values. Redundant per-table existence reads
are omitted only from this browser path; validated counts, ledger and vector
emptiness checks retain their corresponding guarantees. Standalone diagnostics
and migration validation retain their full default inspection behavior.
The complete browser preflight takes 16 Cloud commands (previously 29);
dashboard/detail reads add four commands. There is no cross-request cache,
persistent connection, pooling or skipped dynamic-request safeguard.
There are no migrations, settings modifications, automatic cleanup or retries.
The local server lock is held for its lifetime; live configurations using the
same preserved key share that lock. This does **not** prove database-wide writer
exclusivity: stopping all external writers is an operator confirmation.

Unexpected failures create `browser-stopped.flag` privately. Subsequent requests
and launch attempts stop, rather than blindly retrying an ambiguous transaction.
Do not remove the flag or reset records automatically. Assess the outcome and
obtain approval for a recovery inspection/restart. Previously committed service
operations may remain; there is no whole-browser-test transaction.

## Manual author and reviewer workflow

1. Open `/author-submissions/`. Confirm the correct offline/live staging banner.
2. Use manual entry: title `[SYNTHETIC] The Lantern Voyage`, author
   `Invented Author Nova Quill`. Optional ISBN lookup remains editable; do not
   submit real book/author claims from retrieved metadata.
3. Enter a fictional pitch, submitter `Invented Submitter Nova Quill` and
   `browser-nova@example.com`. Leave the website blank; all other free text must
   also be fictional. Choose a role, self-declared authorization and arrangements.
4. Review step 3, acknowledge both consents and complete the security check.
   Record the submission once and retain its private receipt link.
5. In another tab, sign in at `/author-submissions/staff-login.php` with the
   private fictional credentials, then open `/__staging/mailbox`. The mailbox
   requires reviewer authentication even on loopback; there is no public token
   viewer. This means inspection requires signing in before verification.
6. Open the private mock link and press **Confirm email**. GET does not consume
   a token; POST requires CSRF. The fragment is removed from browser history.
   Reuse/expired/unknown links show the generic unavailable response.
7. Open `/admin/submissions.php`, locate the verified property, open its detail
   and save fictional research notes/evaluations and a status change.
8. Reload and confirm the saved status, qualitative evaluation and audit event.
   Check genre/status filters and POST-based private search.
9. Inspect the original duplicate through the Duplicates filter. Mark the new
   test record spam/duplicate if desired; confirm it leaves the default inbox.
10. Log out and confirm the reviewer detail and mock mailbox return 403.
    Test bad CSRF separately; never load-test live services or reset rate buckets.

## Controls and remaining limitations

- Exact host/remote-address gates and isolated, HTTP-only SameSite Strict session
  cookies. Session names and private session directories are configuration-specific.
- All existing server-side active-admin checks, CSRF, validation, consent,
  rate limits, audit and revision-conflict checks remain in place.
- Submissions require `[SYNTHETIC]` titles, `Invented` author/submitter names and
  `example.com` email addresses; websites are rejected. Markers do not prove
  every free-text field contains no real personal information. That remains a
  tester obligation. Never enter real identities, contracts or rights claims.
- Mail always uses encrypted local capture; SMTP cannot be selected. Tokens
  appear only in authenticated private mailbox links and verification fragments,
  not public pages, URL query strings or ordinary application/access logs.
- Existing duplicates are warnings, not automatic accusations; reviewers retain
  the existing explicit quarantine controls. Default inbox separation is unchanged.
- Project ownership/isolation relies on the operator-confirmed dashboard manifest
  and TLS hostname, not a SQL project-ID proof. Admin credentials remain write-capable.
- Free-plan automatic snapshots are not assumed. Preserve the original key and
  accept disposable synthetic-only loss; no automatic restore, delete or reseed.
- This is single-machine, single-writer development testing, not a public PHP
  server, production staff-authentication readiness or concurrency/fault certification.
- The original strict `three-synthetic-v1` harness is intentionally no longer a
  valid repeat check after new browser records or reviews. Use the browser
  safety checks; do not relax that earlier harness or reset data to make it pass.
- Full live browser behavior, provider permissions and development Siteverify
  connectivity required separate approval; the approved manual workflow below
  now demonstrates live staging submission and review. Offline provider mocks
  still do not certify live ISBN provider behavior or production readiness.

See [migration recovery limitations](submissions-staging-migrations.md) and
[synthetic-service testing](submissions-staging-synthetic.md).

## Preparation validation results

### Successful manual live browser test - November 1, 2026

The operator reported the following successful end-to-end workflow:

1. Opened the three-step author form on `http://127.0.0.1:8086/author-submissions/`.
2. Manually entered the fictional book `[SYNTHETIC] The Last Signal from Europa`.
3. Submitted it successfully to `book-to-screen-staging`,
   `cutaojpqvz.g2.sqlite.cloud:8860`, `author-submissions-staging.sqlite`.
4. Signed in as the fictional local reviewer and opened the private encrypted
   mock mailbox.
5. Followed the private link and completed email verification.
6. Confirmed the status became **New, email confirmed**.
7. Opened the property in the reviewer dashboard.
8. Changed the review status to **Under review**.
9. Added an internal research note.
10. Saved successfully and confirmed the updated page loaded.

Expected results were observed: persistence in the isolated Cloud staging
database, email-control confirmation through mocked delivery, visibility in the
private actionable queue and a saved reviewer status/note. The original staging
key, development Turnstile and fictional reviewer boundary were reused.
This records the operator's manual result, not a new automated database audit;
the final-review task did not replay the writes.

This was **not** a production deployment, real SMTP/email-delivery test,
production staff-authentication certification, manuscript submission or rights
ownership verification. The test used fictional data only. Manual evaluation
of every research field, live concurrency/fault recovery, populated migration
upgrades and production infrastructure still need their own approval and tests.
Free-plan recovery limitations and original-key retention still apply.

- 52 offline HTTP workflow/access-control checks.
- 40 mocked browser guard checks.
- Existing regressions: 191 submission, 124 reviewer, 80 anti-bot, 75 ISBN,
  23 intake JavaScript, 52 synthetic staging, 96 migration and 137 diagnostic checks.
- Browser smoke test: all three form steps, offline security widget and fictional
  submission persistence. Verification/login/review/reload were covered by the
  complete HTTP route test; a full interactive/live browser walkthrough remains
  a manual step after the appropriate approval.
- PHP/JavaScript syntax and Git whitespace checks passed.
- No Cloud connection or Cloud write during browser integration preparation.

### HTTP 404 correction validation

The live launch initially passed CLI preflight but returned 404 during the first
browser request because that request included the CLI-only private connection
file. Offline mode skipped that include, so its workflow passed. The connection
handoff described above fixes the SAPI mismatch without changing the router
allowlist, document root, private-file guard or authorization checks.

Correction regressions passed offline: 12 routing/handoff checks (including the
original 404 reproduction, missing approval/configuration and wrong-target
rejections), 52 browser HTTP checks, 40 mock browser guards, 18 private config,
80 anti-bot, 124 reviewer and 137 diagnostic checks. Syntax and whitespace checks
passed. No live Cloud connection, schema change or Cloud data mutation was made
while correcting the issue; live restart still requires separate authorization.

### Preflight performance optimization

Read-only live measurements after offline validation:

| Page | Before response time | After response time | Commands before / after |
|---|---|---|---|
| Author form | 3.62-4.59 s | 2.30-2.77 s | 29 / 16 |
| Reviewer dashboard | 4.63-4.64 s | 2.59-2.71 s | 33 / 20 |
| Property detail | 4.54-5.06 s | 2.66-2.71 s | 33 / 20 |

Samples are not a controlled load benchmark; network conditions differ.
Instrumented optimized preflight was 2.23-2.28 s including connection
establishment of 0.43-0.47 s. Dashboard/detail service reads added 0.44-0.54 s.
Remaining costs are sequential Cloud round trips, connection establishment and
the development server's single-threaded head-of-line blocking. The live session
was retained, and measurement issued only read operations; no Cloud data or
schema was changed.

## Files in this browser integration

Created:

- [Browser safeguards, adapter boundary and offline protocol mock](../includes/submissions/staging-browser.php)
- [Private fixture preparation and server launcher](../scripts/submissions-staging-browser.php)
- [Allowlisted router](../scripts/submissions-staging-browser-router.php)
- [Authenticated mock mailbox](../scripts/submissions-staging-browser-mailbox.php)
- [Offline-only widget mock](../scripts/submissions-staging-browser-turnstile.js)
- [HTTP workflow tests](../tests/submissions-staging-browser-test.php)
- [Mock guard tests](../tests/submissions-staging-browser-guards-test.php)
- [Optimized per-request preflight](../includes/submissions/staging-browser-preflight.php)
- [Routing handoff regressions](../tests/submissions-staging-browser-routing-test.php)
- [Exact dashboard asset regressions](../tests/submissions-staging-browser-assets-test.php)
- This guide.

Modified:

- [Route bootstrap](../author-submissions/bootstrap.php): router-supplied services,
  isolated sessions and stop-on-error behavior.
- [Submission](../author-submissions/submit.php): fictional staging input restrictions.
- [Lookup](../author-submissions/lookup.php): offline provider mock and database failure stop.
- [Staff login](../author-submissions/staff-login.php): fictional authentication notice and failure stop.
- [Reviewer page](../admin/submissions.php): immediate stop on unexpected staging errors.
- [Prototype template](../submission-prototype/index.php): conditional staging notices/security settings.
- [Intake JavaScript](../assets/js/submission-intake.js): offline widget URL from the gated page.
- [README](../README.md): browser integration guidance.

No migration definition, production connection/deployment configuration,
production authentication or original staging-key file was changed.
