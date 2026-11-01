# Milestone 4B: Namecheap SMTP controlled-test completion

Prepared and completed November 1, 2026. **One separately approved real
verification email was accepted and manually verified. Public submissions remain
disabled. No additional send is authorized by this completion record.**

## Successful controlled test

The operator authorized `namecheap-smtp-v1` for exactly the owned recipient
saved in the private configuration. The approval was constructed privately
in-process for the CLI; neither credentials nor the recipient were printed.

Verified execution results:

1. PHPMailer authenticated to the approved Namecheap host using implicit TLS on
   port 465. The send completed with peer and hostname verification enabled and
   self-signed certificates disallowed; no TLS bypass or fallback was used.
2. SMTP accepted exactly one verification email. The isolated local service
   persisted `delivery_status=sent` and one `verification_email_sent` event.
3. The operator reported Gmail **inbox** delivery, not Spam, and a professional
   presentation. This is operator-confirmed delivery, not an automated inbox probe.
4. The operator opened the received fragment-token link on the local computer and
   pressed **Confirm email** on the restricted loopback verification page.
5. Final read-only SQLite inspection verified one submission with status `new`,
   a non-null verification timestamp, one consumed email token and exactly one
   each of `submitted`, `verification_email_sent` and `email_verified` events.
   Integrity and foreign-key checks passed. No private values were printed.

There was one SMTP attempt and no automatic retry. The separate local fixture,
recoverable key and exclusive send-attempt marker were preserved outside Git.
No Cloud or production data/schema was accessed or changed. The real recipient
is private personal data: its encrypted local fixture is not disposable public
synthetic content and must remain access-restricted.

The first detached verification launcher exited without starting the server.
Direct PHP startup succeeded; the page returned HTTP 200 with CSRF, while
submission/resend/admin/private files remained blocked. A small generated PHP
startup log was discovered during final Git review and removed; it contained
no credential/token indicators. The external launcher now uses the explicit PHP
binary and `php://stderr`, not a repository-relative log filename. This
operational correction does not change repository authorization or mail behavior.

Not yet verified: received `Authentication-Results`, SPF/DKIM/DMARC alignment,
actual signing selector/return path, provider quota/bounce handling, other mailbox
providers and long-term deliverability. Inbox arrival alone proves none of those.
The certificate chain/expiry was not independently inventoried; successful
certificate-validated PHPMailer execution is the evidence for this connection.

## Configuration and isolation

The existing [PHPMailer transport](../includes/submissions/mail.php) is reused
without modifying token issuance, expiration, resend behavior or browser routing.
The [private configuration loader](../includes/submissions/smtp-test.php) accepts
only these Namecheap settings:

| Setting | Value |
|---|---|
| Host | `booktoscreen.org` |
| Port | `465` (integer) |
| Encryption | `smtps` / implicit TLS |
| Authentication | Required |
| Username / From | `hello@booktoscreen.org` |
| Password | Existing mailbox password, configured privately |
| Recipient | One explicitly approved owned mailbox |
| Hourly budget | One attempt, including failures |

This is a **CLI controlled-test configuration**, not an SMTP activation switch
for local/staging/production HTTP routes. Existing browser mock/disabled transport
selection remains unchanged; staging always uses its encrypted mock mailbox.

Private JSON must be outside both the repository and its parent web directory,
and outside an active document root. The parent-directory restriction intentionally
also excludes sibling websites in the local Ampps `www` directory. On Unix,
file/directory permissions must exclude group/other access (0600/0700). On Windows,
use a current-user-only ACL on the directory, inherited by all files; PHP does not
independently attest Windows ACLs. Recheck ACLs after copying files.

A private, current-user-only preparation directory and initially blank-password/
blank-recipient template were created outside the repository for this session.
The operator subsequently completed those fields privately. Its values are
not supplied to the browser. Configure the real password directly in that private
file through a trusted local editor, not chat, commands, source control or logs.
Do not reuse the staging browser key/fixture for this separate email test.

Private file format (create/edit only outside publicly served directories):

```json
{
  "host": "booktoscreen.org",
  "port": 465,
  "encryption": "smtps",
  "username": "hello@booktoscreen.org",
  "password": "",
  "from": "hello@booktoscreen.org",
  "test_recipient": "",
  "hourly_limit": 1
}
```

Unknown settings, TLS overrides, nonpinned connection values and malformed inputs
are rejected. Loading the file cannot set an approval flag. The CLI never prints
configuration/provider exception details, credentials, addresses, tokens or private
submission contents.

## TLS and delivery semantics

PHPMailer selects `ENCRYPTION_SMTPS` for port 465, authenticates and uses:
`verify_peer=true`, `verify_peer_name=true`, `allow_self_signed=false`.
SMTP debug is off, with a 10-second connection timeout and 15-second command
limit. Do not disable validation to accommodate a certificate error. The live
certificate must be trusted and valid specifically for `booktoscreen.org`;
this cannot be verified offline. The approved live send successfully used these
validation settings.
The current CLI PHP configuration has an explicit readable `openssl.cafile`.
The approved live connection subsequently completed with this trust configuration.

A true send result means the SMTP server accepted the message, **not that it
reached the inbox**. Connect/authentication/recipient/DATA failures raise sanitized
mail exceptions; the existing submission service persists `delivery_status=failed`
and `verification_email_failed`. Successful acceptance records `sent` and
`verification_email_sent`. No success-shaped fallback is added.

Network loss after DATA or a database failure after SMTP acceptance can be
ambiguous: stop and inspect the owned inbox/provider logs and private fixture.
Do not automatically retry. The CLI reserves a persistent exclusive send-attempt
marker before calling the service; a used/nonempty fixture cannot be reused.
The one-hour budget is **per fixture**, not a provider-global limit. Do not create
fresh fixtures to evade it; every further real send requires new approval.

## Public DNS assessment

Read-only DNS queries on November 1, 2026 returned:

- SPF:
  `v=spf1 +a +mx +ip4:199.188.201.66 +ip4:199.188.201.77 include:spf.web-hosting.com ~all`
- Domain A: `199.188.201.77`.
- MX: `mx1-hosting.jellyfish.systems` (5), `mx2-hosting.jellyfish.systems`
  (10), `mx3-hosting.jellyfish.systems` (20).
- `default._domainkey.booktoscreen.org`: nonempty `v=DKIM1; k=rsa; p=...`.
- `_dmarc.booktoscreen.org`: `v=DMARC1; p=none;`.
- `spf.web-hosting.com` includes five `spf1` through `spf5.web-hosting.com`
  records and `spf.jellyfish.systems`; the inspected children contain IP
  mechanisms rather than further includes.

Deliverability concerns/prerequisites:

1. SPF currently includes the confirmed host IP and provider ranges. The observed
   worst-case chain has nine DNS-lookup mechanisms (`a`, `mx`, parent include,
   six child includes), close to SPF's ten-mechanism limit. Do not add another
   provider/include casually. Actual sending IP, MX address expansion limits,
   DNS availability and return-path alignment remain to be checked on real mail.
2. The default-selector DKIM public key is published, but this does not prove
   cPanel signs outgoing SMTP mail or uses that selector. Check cPanel Email
   Deliverability, then inspect an approved received message's signature and
   Authentication-Results.
3. DMARC `p=none` is monitoring-only, not spoofing enforcement. No `rua` reporting
   destination is published. Plan owned/private aggregate reporting and eventual
   enforcement only after all legitimate senders are inventoried and aligned.
4. Confirm real received mail reports SPF/DKIM/DMARC pass with an aligned
   `booktoscreen.org` identity; check junk-folder placement and provider quotas.
5. MX availability and DNS publication alone do not verify credentials, TLS or
   delivery. The controlled test now establishes successful authenticated/
   certificate-validated sending and operator-confirmed delivery for one recipient.
   No DNS changes were made.

## Offline checks

Run from the repository:

```powershell
php -d display_errors=0 scripts\test-submissions-smtp.php
php -d display_errors=0 scripts\test-submissions-smtp.php --offline 'C:\private-smtp\smtp.json'
php -d display_errors=0 scripts\test-submissions-smtp.php --check 'C:\private-smtp\smtp.json'
php -d display_errors=0 tests\submissions-smtp-test.php
```

The default and `--offline` use an in-memory local fixture and a PHPMailer
mock that prepares the message but never sends. Supplied real password/recipient
values are replaced with dummy fixture values before the mock transport is built.
`--check` only validates configuration; exit 2 means missing password or a
missing/reserved test address. A ready result is not ownership verification.

The dedicated regression suite uses actual PHPMailer sending logic with an
in-memory SMTP response double: acceptance, connection/authentication/recipient/
DATA rejection, TLS options, allowlisting, budget, idempotency, expiry, single-use
verification, failure persistence/audits, private configuration and CLI guards.
No DNS or sockets are used by those tests.

Final preparation validation: **460 checks passed** (133 SMTP, 191 submission/
database, 80 anti-bot and 56 staging browser guards), plus syntax checks for all
three new PHP files and Git whitespace checks. The offline CLI passed; the private
template checker correctly reported incomplete configuration (exit 2). Expected
mock failure log messages contain no recipient/provider details. Existing duplicate
PHP extension startup warnings are unrelated environment noise.

## First real email test: approval required

The first approval has been consumed by the successful test above. The following
procedure is retained as reference, not permission to send again. A used fixture
must not be reset/reused; any further send requires separate explicit approval.

Do not run the following until separately approved:

1. Configure the private mailbox password and **one exact owned recipient**;
   never send to `example.com` or other reserved domains.
2. Confirm sender permission, cPanel deliverability settings and a usable CA trust
   store. Agree that a certificate/auth failure stops the test without fallback.
3. Prepare a **new, isolated empty local** `submissions-local.sqlite` fixture,
   unchanged migrations 1/2, a separate recoverable key JSON containing
   `B2S_SUBMISSIONS_KEY`, and current-user-only permissions. Do not migrate a
   Cloud/production database or reuse the live staging/demo fixture.
4. Prepare a matching loopback author-submissions verification server using that
   local DB/key, mock email selection and existing CSRF-protected confirmation.
   No other fixture writers should be active during the SMTP send.
5. Confirm the existing mailbox may receive one real verification email, and
   authorize its exact address. The test sends through `SubmissionService`, so a
   local synthetic record, encrypted private payload, token hash/rate rows and
   audit events persist; no Cloud data is accessed.

Exact approval statement:

> I authorize `namecheap-smtp-v1` to connect to `booktoscreen.org:465` using
> certificate-validated SMTPS and `hello@booktoscreen.org`, and attempt exactly
> one verification email to **[exact owned email address]**. I confirm we own
> that recipient, the private credentials and isolated local DB/key/verification
> server are ready, and no other local-fixture writers are active. Keep public
> submissions disabled and Cloud/production databases untouched. Stop on TLS,
> authentication, send or database failure; do not automatically retry.

Approval-gated command shape (the address below is a placeholder, not approval):

```powershell
php -d display_errors=0 scripts\test-submissions-smtp.php --send-one `
  'C:\private-smtp\smtp.json' `
  'namecheap-smtp-v1:EXACT_OWNED_ADDRESS' `
  'C:\private-smtp-fixture\submissions-local.sqlite' `
  'C:\private-smtp-fixture\key.json' `
  'http://127.0.0.1:8088/author-submissions'
```

Only file paths/explicit recipient approval enter command arguments; never passwords
or keys. The CLI performs local integrity/FK, migration checksum and empty-table
preflight, validates the loopback URL and creates `smtp-send-attempted.flag`
exclusively. There is no migration, reset, automatic resend or cleanup option.

After approval/acceptance, inspect the owned inbox privately, follow the fragment
link and use the existing confirmation POST. Check persisted email-confirmed status,
single-use behavior and received authentication results. Do not publish headers,
links or tokens. Retain the fixture/key/marker for review, including on failure.
Local browser routes still select mock mail: do not use their Resend action during
this real-delivery test. A public deployment, broader send budget, other recipient,
SMTP-backed browser workflow or production activation requires separate work.

## Files

- [Private loader/test helpers](../includes/submissions/smtp-test.php)
- [Offline/check/approval-gated CLI](../scripts/test-submissions-smtp.php)
- [Mocked SMTP regression tests](../tests/submissions-smtp-test.php)
- This guide and [README](../README.md)

The existing PHPMailer mail transport, token service, migrations, local/staging
browser integration, credentials and deployment configuration are unchanged.

## Final review and Git recommendation

**GO for a manual source-only Milestone 4B commit. NO-GO for public activation
or production rollout.** No commit, push, deployment, additional SMTP send or live
Cloud write was performed during final review.

All pending Git files were reviewed, with no unrelated source change identified.
The generated startup log was the only non-source artifact and was removed.
Final intended inventory is five files:

| Change | File |
|---|---|
| Modified | [README.md](../README.md) |
| New | [This guide](submissions-smtp.md) |
| New | [Private loader/test helpers](../includes/submissions/smtp-test.php) |
| New | [Approval-gated CLI](../scripts/test-submissions-smtp.php) |
| New | [Mock SMTP tests](../tests/submissions-smtp-test.php) |

Read-only security review reported no vulnerabilities. Direct review confirmed
exact-recipient approval, private paths, strict TLS, conservative failure budgets,
exclusive send-attempt marker, no retry/reset, and unchanged mock/disabled browser
delivery. Existing token expiry, consumption, resend limits and anti-abuse behavior
are covered by regressions. Core mail/service/migration/Cloud adapter code matches
HEAD: this milestone requires no production or Cloud schema modification.

Private SMTP configuration, recipient, local and staging keys, DB, sessions and
email contents remain outside Git/public directories. Exact private password/
recipient/key comparisons and captured-token/Cloud-URL pattern checks found no
matches in pending files; Windows ACLs remain current-user-only. Dummy test
credentials and reserved-domain fixture definitions are intentional source data.
Do not force-add private files or include the personal-data fixture in a commit.

Final offline regression results:

| Suite | Passed |
|---|---:|
| SMTP configuration/delivery/verification | 133 |
| Submission services / token expiry, consumption, resend | 191 |
| Anti-bot/cache | 80 |
| Reviewer services | 124 |
| Staging preparation | 37 |
| Staging private config | 18 |
| Staging read-only diagnostic | 137 |
| Staging migration fixtures | 96 |
| Staging synthetic fixtures | 52 |
| Staging browser guards | 56 |
| Browser routing / CLI handoff | 12 |
| **Total** | **936** |

All three new PHP files passed syntax checks; Git whitespace checks passed.
Expected mock-failure messages and pre-existing duplicate PHP extension warnings
were not test failures. No regression used real SMTP or live Cloud.

Remaining limits: Windows ACLs require operator verification; one-fixture budgets
are not provider-global limits; ambiguous SMTP acceptance/database outcomes require
manual review, not retries. Production authentication, public Turnstile, DNS
authentication evidence, privacy/retention rollout and broader delivery testing
remain separate approval/work items. The verification-only server is not a
production application server.

Recommended commit message:

```text
Complete Milestone 4B controlled Namecheap SMTP verification

Add private pinned SMTPS configuration and approval-gated single-email harness.
Test delivery failures, recipient isolation and verification behavior offline.
Document successful inbox delivery, browser confirmation and remaining gates.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
```
