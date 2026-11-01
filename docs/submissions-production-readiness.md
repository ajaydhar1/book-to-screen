# Milestone 4C: restricted-pilot readiness assessment

November 1, 2026. **NO-GO for deployment or public activation.**
This assessment began offline and is not production deployment approval.
The operator subsequently deployed and removed the separately approved temporary
challenge-only harness and reported successful real widget/fresh/duplicate
validation. See the [4C completion record](submissions-turnstile-upload.md#milestone-4c-completion-review).
The earlier double acceptance remains unexplained. No production database
provisioning/connection, DNS change, new real email, production author deployment
or public activation is part of 4C. This completion review is offline; no commit
or push occurred.

## 1. Existing components versus launch work

Milestones 4A/4B established a working staging submission/reviewer workflow and
one approved real SMTP/inbox/browser verification test. Those results do not
certify production authentication, hosting, scoped database grants or recovery.

| Component | Implemented / demonstrated | Still required for pilot |
|---|---|---|
| Three-step intake / ISBN | Optional Google Books/Open Library lookup, manual corrections, mobile form, consent, bounds and provenance | Production bootstrap/URLs/copy; provider quota and private optional API key |
| Turnstile | Strict verified-HTTPS Siteverify, test-key rejection; operator-confirmed Managed widget, fresh acceptance and identical-token duplicate rejection on the temporary hosted harness | Production bootstrap/CSP/private injection and pilot admission; earlier double acceptance remains unexplained, no guarantee beyond the confirmed test pair |
| Anti-abuse | CSRF, honeypot, HMAC-keyed DB rate buckets, email/resend/lookup limits, idempotency | Hosting proxy/IP trust policy, pilot/global quotas, monitoring and clock accuracy |
| Mail | PHPMailer authenticated validated SMTPS, controlled one-recipient CLI, explicit sent/failed states | Pilot transport factory, provider-global budget, approved recipient scope, authentication headers/bounces |
| Data | Dedicated versioned schema, encrypted contacts/pitches/declarations/evaluations/audit notes | New production DB, exact identity/grants/preflight, production key, production adapters |
| Review | Private actionable inbox, explicit suspicious/unverified filters, revisions/status/evaluations/audit | Real staff identity integration across separate DBs; authorization on every request/action |
| Isolation | Loopback routes, pinned staging target, CLI gates, synthetic-only staging, mock mail | Production-safe bootstrap with no local/staging fallback and separately controlled pilot admission |
| Recovery | Local SQLite backup primitive for adaptations; new synthetic submissions restore proof | Cloud export/snapshot procedures, measured RPO/RTO, off-site/key backup and live restore rehearsal |

No user accounts, manuscripts, payments, automatic adaptation promotion or public
launch functionality was added. [Core submission services](../includes/submissions/service.php)
write only the submission schema. `research_candidate` is a private queue status,
not insertion into public adaptations/research leads.

## 2. Turnstile configuration and validation plan

The operator subsequently confirmed an existing Managed widget for
`booktoscreen.org`. Reuse it rather than provisioning another; see the
[private configuration and controlled-test guide](submissions-turnstile.md).
No key was activated or live widget validation performed by that preparation.
The later separately approved temporary live test is recorded in the completion
section linked above. The test endpoint is now disabled and removed.

Reuse [SubmissionTurnstile](../includes/submissions/antibot.php) and the existing
[intake JavaScript](../assets/js/submission-intake.js). No new framework is needed.

**Proposed canonical URL, subject to operator approval:**
`https://booktoscreen.org/author-submissions/`.
The existing [.htaccess](../.htaccess) redirects `www.booktoscreen.org` to the
non-www domain. Choose one canonical origin; do not include paths, schemes or
ports in a widget hostname. If a dedicated submission subdomain is preferred,
approve that before widget creation and change the expected hostname consistently.

Planned runtime values (not set by this milestone):

| Setting | Proposed value |
|---|---|
| Widget type | Managed, selected in Cloudflare dashboard |
| Widget hostnames | Only the approved canonical production hostname |
| Pre-clearance | Off for this integration |
| `B2S_TURNSTILE_ENV` | `production`, only in a separately approved production bootstrap |
| `B2S_TURNSTILE_PROD_SITE_KEY` | Operator-created real site key |
| `B2S_TURNSTILE_PROD_SECRET` | Operator-created real secret, outside public_html/Git |
| `B2S_TURNSTILE_PROD_HOSTNAME` | `booktoscreen.org` if canonical URL above is approved |
| `B2S_TURNSTILE_PROD_ACTION` | `author_submission` |

Store both keys in private configuration; the site key must be rendered into
HTML and is **public**, not a secret. The secret must never enter HTML/JS,
command arguments, phpinfo output or logs. Environment names already exist;
setting them alone cannot enable current loopback-gated routes. Code cannot
prove Managed mode or widget ownership offline; record dashboard confirmation
and separately approved Siteverify evidence before pilot.

Existing controls:

- Only final Step 3 loads the widget. ISBN lookups and intermediate steps do not
  challenge the user. Verification/receipt pages load no Turnstile widget.
- Server POSTs only secret/token to
  `https://challenges.cloudflare.com/turnstile/v0/siteverify`; no private form
  fields or forwarded visitor IP. TLS peer/host checks stay on; no redirects.
- Missing/malformed tokens fail before provider calls. Every submission attempt,
  including retries, needs successful validation; wrong hostname/action fails.
- Production timestamp checks reject over-five-minute and excessive future
  responses; single-use is enforced by Siteverify's `timeout-or-duplicate`
  result. There is no persistent token cache or client-only trust.
- Official development keys and dummy response sentinels cannot satisfy production.
- Provider/config/malformed failures fail closed, with generic sanitized logging.
- The UI has live-region messages, expiry/timeout/error callbacks, explicit retry
  and preserved form contents. Retry gets a fresh token with the same request
  identifier for unchanged payloads; it is not an automatic submission resend.
- CSP allows only the challenge script/frame origin in addition to self resources;
  do not add broad unsafe-inline/unsafe-eval exceptions to make deployment work.

Before live validation approve hostname/action and Managed mode, verify HTTPS,
clock/NTP, outbound verified HTTPS/CA configuration and CSP, then test success,
missing/spent/expired token, wrong context and provider failure on a restricted
nonpublic origin. Do not use production keys on loopback/staging development routes
or call the optional live-test switches during offline validation.

## 3. Production database and staff authentication

### Proposed architecture: conditionally acceptable, not yet proven

- Keep existing production `adaptations.sqlite` and its tables/users unchanged.
- Provision a new `author-submissions.sqlite` only after separate infrastructure
  approval. A different database filename in the same Cloud project is **not**
  sufficient authorization or breach isolation.
- A submissions runtime credential must be restricted to that database and the
  required reads/DML, with no database creation, grants, DDL or selection of
  adaptations. Use a separately approved temporary migration credential, revoked
  after schema verification. Reviewer access must not grant public research writes.
- Verify the actual plan's database-scoped permissions. If a credential can read
  or write unrelated databases, **stop**: choose a separate project or revise the
  identity design rather than relying on code naming or `query_only`.
- Separate DB credentials limit permitted operations, not total compromise of a
  shared PHP process/OS account that also holds research credentials. Document that
  shared-host threat model; consider a separate runtime/secret scope if stronger
  breach isolation is required. A separate project alone does not isolate secrets
  readable by the same application account.
- Approved control-panel mapping plus verified TLS, selected database identity,
  migration checksums, schema and FK/integrity form the connection boundary.
  Client flags do not prove server grants. Production requires new exact-target
  safeguards; staging harnesses intentionally reject production and must not be
  loosened or repurposed by renaming their configuration.
- Use independent connection factories for research/staff identity and submissions,
  explicit dependencies and no `get_db()` fallback in intake. Do not initialize
  an unrelated research database on anonymous intake.

Existing staging demonstrated schema/transaction behavior on its approved target.
That is not evidence for production credential grants, populated upgrade recovery,
availability or the proposed production project's plan. No production connection
was made to resolve these questions.

### Staff identity is a significant implementation gate

The existing general admin flow uses its normal session and database; submission
bootstrap uses its own named local session. A production admin login is not
automatically a submissions login.

[submission_admin_user](../includes/submissions/security.php) rechecks active
admin role and password-change restrictions against `users`. Review mutation
checks and detail/audit joins also assume `users` on the supplied DB.
Migrations 1/2 do not create users. Simply passing a dedicated submission database
will therefore fail; copying production passwords/users into it is not the fix.

Recommended narrow design, **not implemented here**:

1. Explicitly integrate the trusted existing staff session with a read-only staff
   identity source. Approve its cookie/session scope, secure flags, CSRF behavior,
   session regeneration, inactivity expiry, logout and revocation behavior.
2. Add a shared identity/actor lookup boundary used by reviewer authorization,
   review mutations and audit display; pass the submission DB separately. Avoid
   staging's exact SQL-rewrite mock in production and avoid duplicated user tables.
3. Keep stable production actor IDs; current audit actor IDs are not cross-database
   foreign keys. Define historical names/deleted-user display without weakening
   authorization. Identity outages must deny access, not use stale session claims.
4. Require explicit submissions-review permission (initially active admins only),
   rechecked on list/detail/update requests; test downgraded/inactive/forced-password/
   logged-out users and unavailable identity service. Private details must never be
   rendered before authorization.
5. Production bootstrap must reject all local/staging mode flags, fictional
   reviewer paths/IDs and development transport settings. Never copy fictional
   users, staging keys or fixture records into production.

This changes application wiring and requires an agreed design plus offline
cross-database authorization tests before implementation. No production staff
login, session or research schema was modified in 4C.

## 4. Encryption, backups, rotation and failure recovery

Existing [SubmissionCrypto](../includes/submissions/security.php) uses a random-IV
AES-256-GCM envelope with authenticated decryption and a required 32-byte hex key.
Wrong/missing keys and damaged ciphertext do not produce a successful plaintext
fallback. Contacts, pitches/rights declarations, evaluations and private audit
notes use that key. Email tokens are cryptographically random and stored as hashes.
Bibliographic titles/authors and some role/status/consent metadata are not encrypted;
the database and backups still need strict access controls.

### Key custody and an important rotation limitation

- Generate a **new production-only** CSPRNG key after explicit approval; never
  copy the staging/local key. Store privately outside public_html/Git with
  application-user access only, not as a public PHP file or command argument.
- Keep a separately encrypted recoverable copy in an approved offline/secret
  store with two designated custodians, documented restore access and periodic
  recovery drills. Protect encryption backup keys independently from DB exports.
- Current code uses the **same key for AES and HMAC** projections (email/property,
  idempotency/payload/receipt derivation and rate buckets). There is no key-ID
  envelope or online key ring. Routine replacement is **not safe key rotation**:
  reencrypting payloads alone breaks index/idempotency consistency, and original
  request keys needed to recompute idempotency hashes are not stored.
- Existing receipt lookup hashes the supplied receipt with SHA-256, so a stored
  receipt can still resolve after a hypothetical rotation; deriving new receipts/
  retries and HMAC projections would nevertheless become inconsistent. Do not
  claim all links survive a simple key replacement.
- Before pilot, agree a tested rotation design: separate stable indexing/HMAC
  key from versioned encryption keys, preserve legacy compatibility where needed,
  and approve any migration/reencryption separately. Alternatively keep the
  initial pilot key fixed with an explicit stop/intake-disable incident procedure
  until a reviewed rotation implementation exists. Rotation remains a launch
  policy gate, not an implemented feature.

### Backup/recovery proposal: operator approval required

Proposed starting objectives for a low-volume pilot: **RPO <= 24 hours, RTO <=
one business day**, daily consistent encrypted exports/snapshots, 30 daily
copies, and a pre-migration snapshot. These are proposed policy targets, not
measured guarantees. Choose a plan/process that can meet the approved target.
Do not assume Free-plan snapshots or cross-database restore isolation.

The existing [backup script](../scripts/backup-database.php) backs up only local
adaptations, not Cloud submissions. No Cloud submission backup automation exists.
Confirm provider/export consistency, plan retention and whether restore affects
one database or the entire project; a project-wide restore must never silently
overwrite adaptations. If isolated restore is unavailable, use a separate
project/export strategy before admitting real pilot records.

Keep DB exports encrypted and access-restricted outside hosting web directories,
with separate key custody, checksums/ledger metadata, monitored success/age and
an off-host copy. Test restore into a newly approved isolated target, never over
production: selected identity, integrity/FK, schema/index/checksum, row totals,
private field/evaluation/audit decryption, consumed-token state, reviewer
authorization and unaffected research data. Measure downtime and verify retention.

The new [offline recovery test](../tests/submissions-pilot-readiness-test.php)
uses SQLite's consistent backup API on a populated synthetic local database,
restores to a separate local path, recovers the key and compares private details/
evaluation/audit/receipt state. It tests missing/wrong keys, ciphertext tampering
and consumed-token preservation. It is **not a Cloud snapshot, live restore,
production rotation or off-host disaster-recovery certification**.

Database/identity/SMTP errors must remain explicit and sanitized. Disable pilot
admission during outages; never fall back to research/staging DBs or plaintext.
An uncertain commit or SMTP DATA result must be investigated with existing request
identity/audit/inbox evidence, not retried blindly. Automated cleanup/reset or
silent “success” on a failed operation is not an approved recovery mechanism.

### Retention/deletion

[Local cleanup](../scripts/cleanup-submissions-local.php) removes unverified
submissions after seven days and closed/duplicate/spam submissions after 365
days, plus old tokens/rate buckets; FK cascades remove dependent notes/genres.
It is local-only and must not be aimed at Cloud or enabled by production flags.

Approve actual production durations, active-review maximum retention, exceptions/
legal holds, deletion requests, evidence/audit retention and backup expiry.
Implement a separate production dry-run/count/approval cleanup job with verified
scope and cascades; do not promise immediate deletion from retained backups.
Restoration must reapply deletion decisions before reopening access. Encrypting
or deleting a live record does not erase backups or previously sent email.

## 5. Namecheap deployment compatibility assessment

**Local evidence only:** PHP 8.2.27 with curl, OpenSSL, mbstring, JSON, filter,
hash, session, PDO/pdo_sqlite, sqlite3 and streams is available. Existing Composer
packages are SQLite Cloud SDK 0.2.0 and PHPMailer 6.x. Composer's generated PHP
7 platform check is a dependency minimum, **not the application requirement**;
the app uses PHP 8.1-era features and is tested on 8.2. Use a currently supported,
patched PHP version compatible with all tests on Namecheap.

Not inspected on Namecheap: actual PHP/web-SAPI version and enabled extensions,
OpenSSL/CA bundle, socket/HTTPS/SMTP egress restrictions, filesystem ownership,
PHP-FPM environment injection, sessions, cron availability, resource limits,
certificate configuration, reverse-proxy behavior and provider mail quotas.
Hosting settings are a separate approved inspection, not inferred from local PHP.

Deployment preparation requirements:

- Build/install locked Composer dependencies offline or in an approved build
  environment; deploy the compatible vendor output without test/dev artifacts.
  Use `composer install --no-dev --prefer-dist --optimize-autoloader` and
  `composer check-platform-reqs --no-dev` in that approved environment; no
  dependency install/update was needed or run in this task.
- SMTP remains `hello@booktoscreen.org`, authenticated `booktoscreen.org:465`
  with implicit TLS, verified peer/name and no self-signed acceptance. The 4B
  single-message test is not a production HTTP SMTP factory or provider-wide
  limit. Define pilot recipient admission and send budget, retries/manual
  escalation, rate limits and bounce monitoring before wiring a runtime mailer.
- SPF/DKIM are published and DMARC is `p=none`; received alignment/signing,
  SPF lookup headroom, sender reputation and quotas remain unverified.
  [4B evidence and limitations](submissions-smtp.md) apply. Privately inspect
  existing received headers if separately approved; do not send again for this task.
- Enforce HTTPS for every route (existing root rewrite handles only www
  canonicalization), Secure/HttpOnly/SameSite session cookies, no-store on private
  pages, no-referrer, nosniff, CSP and frame denial. Set HSTS only after confirming
  the entire affected domain/subdomain scope works on HTTPS.
- Store credentials and encryption keys above public_html or in private runtime
  injection. Do not use Git-ignore as HTTP access control.
- Audit the deployment allowlist/HTTP deny rules: `.git`, config/JSON/env files,
  DB/backups/logs, scripts/tests/staging fixtures and diagnostic endpoints must
  not be publicly served. Root `phpinfo.php` exists and must be excluded/denied
  **before injecting secrets**; its presence in source does not prove it is live.
  Disable display_errors; avoid public provider errors and plaintext debug output.
  Existing `Options -Indexes` does not deny direct file downloads.
- Logs must omit request bodies, auth headers, query tokens, private content,
  recipient addresses and connection URLs. Verification/receipt tokens use
  fragments and confirmation POSTs; configure hosting/APM not to record POST
  bodies, headers or session data. Keep retained logs private with approved TTL.

No production hosting deployment configuration was changed in this review. Diagnostics must not be
excluded blindly if the existing site needs them administratively; approve an
explicit authenticated/CLI replacement rather than public secret-bearing output.

## 6. Privacy and operational safety

The default inbox includes only verified actionable statuses; unverified,
closed, duplicate and suspected-spam records are separate filters. Rate limits
reduce spam but do not eliminate human abuse, distributed floods or provider
exhaustion. Define a small invited pilot population, admission controls, global
caps and a kill switch that disables intake without losing reviewer access/data.
An unlinked public URL or Turnstile alone is **not** restricted-pilot admission.

Approve a least-privilege production reviewer roster, incident contacts,
credential revocation, access review and sanitized audit monitoring. Do not
automatically export/promote private pitches, contact details or internal notes.
Email control confirms a mailbox, not identity or rights; self-declarations
remain clearly labeled. No representation, producer introduction or adaptation
opportunity is guaranteed.

Before pilot, replace local/sample consent copy with approved terms and a real
privacy notice describing purpose, operator contact, field visibility, legal
basis/consent version, provider processing (Cloudflare, Cloud DB, Namecheap mail),
retention/backups, international transfers if applicable, requests/deletion and
limits of authorization/rights assessment. Explain that bibliographic ISBN
queries go to Google Books/Open Library and the widget processes browser signals;
do not imply those vendors receive the private pitch from this application.
No manuscripts or confidential third-party contacts should be requested.
Obtain appropriate legal/privacy review; no policy or compliance certification
was implied by the test workflow.

## 7. Offline validation and findings

Read-only security assessment found **no high-confidence exploitable vulnerability
in the current loopback boundaries**. Production gaps above are launch blockers,
not claims of a demonstrated production exploit. No runtime safeguards were
weakened or bypassed to improve readiness.

Final tests use synthetic local SQLite, mocked Cloud/SMTP/metadata/Siteverify and
mocked browser APIs. See the commands already documented in the earlier milestone
guides. New test command:

```powershell
php -d display_errors=0 tests\submissions-pilot-readiness-test.php
```

| Offline suite | Passed checks |
|---|---:|
| New pilot readiness / local encrypted recovery | 20 |
| SMTP configuration/delivery | 133 |
| Submission services / verification | 191 |
| Anti-bot/cache | 80 |
| Reviewer services / authorization | 124 |
| Staging preparation | 37 |
| Staging private config | 18 |
| Staging read-only diagnostic | 137 |
| Staging migration fixtures | 96 |
| Staging synthetic services | 52 |
| Staging browser guards | 56 |
| Browser routing / CLI handoff | 12 |
| ISBN metadata | 75 |
| Intake JavaScript / accessible widget retry | 23 |
| Full offline browser workflow | 52 |
| Exact CSS/static routing | 11 |
| **Total** | **1,117** |

Syntax checks passed for 28 PHP files covering submission helpers/routes and
the new readiness test/SMTP CLI; Git whitespace checks passed. Expected mock
failure logs and pre-existing duplicate PHP extension startup warnings are not
test failures. No dependency installation or live provider request was required.

The HTTP browser suite was run on a newly created outside-Git offline fixture,
not the live staging or SMTP verification DB. Temporary test servers/fixtures
are removed at completion; preserved live keys/configurations/demos are untouched.
Production-disabled tests verify default loopback rejection and public-address
rejection before a DB connection. They do not certify a not-yet-built production
auth/session boundary, grants or Cloud recovery process.

## 8. Prioritized restricted-pilot milestones and approval gates

| Priority / phase | Outcome | Explicit live/change approval required |
|---|---|---|
| P0 / 4C-A: decisions | Canonical URL/action, pilot admission/caps, reviewer roster, privacy/retention, RPO/RTO and key rotation policy | Approve design/policies only; no infrastructure implied |
| P0 / 4C-B: identity/isolation evidence | Confirm production project scope, DB-level grants/restore isolation, Namecheap PHP/egress/CA/HTTPS | Separately authorize specific control-panel/read-only hosting and Cloud inspections; no DDL/DML, migrations or mail |
| P0 / 4C-C: offline wiring | Explicit two-DB staff identity boundary, production bootstrap/activation gate, pilot-only admission, runtime SMTP factory, secure settings loader | Approve significant code design; implement/tests offline first, default disabled, no staging fallbacks |
| P0 / 4C-D: recovery | Dedicated production key custody, monitored consistent backups and isolated restore/rotation drill | Separately approve key generation, backup storage/plan and exact isolated restore target; never overwrite adaptations |
| P1 / 4C-E: provisioning | Exact approved `author-submissions.sqlite`, least-privilege runtime and temporary migration role | Separate target-specific DB creation/migration approval with preflight, snapshots, FK/checksum/integrity proof and rollback plan; no public activation |
| P1 / 4C-F: Turnstile | Existing Managed widget; completed temporary live fresh/identical-reuse validation and removal | No new widget required. Production key/bootstrap/CSP wiring and any further provider test require separate approval; earlier anomaly remains documented |
| P1 / 4C-G: controlled mail | Received authentication evidence, quotas/bounces and one controlled pilot-path email | Approve private header inspection; any new email requires exact recipient/count approval. DNS changes require separate record-specific approval |
| P1 / 4C-H: disabled deployment | Deploy audited artifact with HTTPS, private configuration and routing, production disabled | Separate Namecheap deployment approval for reviewed files/config; verify denied routes, staff revocation and no writes/public admission |
| P2 / restricted pilot | Invite a capped roster, activate admission/mail/production writes for agreed duration, monitor and rehearse kill switch | Explicit final GO covering target, users/recipients, write/send budgets, retention, recovery, timeframe and rollback owner |

Provisioning, Turnstile and hosting evidence can be scheduled independently after
their prerequisites are approved. There is no blanket approval for later phases.
Do not run staging migration/browser harnesses against production or copy their
fictional reviewer/authentication boundary.

**Current GO:** continued offline implementation/design review.
**Current NO-GO:** production provisioning, real Turnstile creation, deployment,
pilot/public activation and further live email. All P0 gates must be resolved and
restricted production behavior tested before a pilot GO is issued.

## 9. Changes made in 4C

- New: this assessment/plan.
- New: [pilot readiness/recovery regression test](../tests/submissions-pilot-readiness-test.php).
- Modified: [README](../README.md), linking this assessment.

Prior Milestone 4B pending files, if still uncommitted, remain a separate reviewed
scope; they were not silently included in a commit. No credentials/config templates,
schema, production route, staff account, key or DNS was changed in this review.
4C now also includes private Turnstile preparation, shared private-path extraction,
opt-in sanitized verifier observation (unwired on author routes), source-only
challenge/diagnostic harness and its mocked tests, documentation and archive
exclusions. No production SMTP transport selection was changed.
