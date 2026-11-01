# Private Namecheap synthetic staging deployment

Source prepared offline. **Operator-uploaded staging remains disabled; live
connectivity and workflow acceptance are not yet reported complete.** This is a private fictional
test portal, not production author intake. The existing public website, real
admin accounts, production Cloud project and adaptations database are unchanged.

## Smallest deployment boundary

Use one isolated folder: `public_html/__author-staging`.
All portal routes, fictional reviewer, private mock mailbox and assets are
beneath that folder. Do not upload anything into the existing root
author-submissions, admin, includes, assets or vendor directories.
Do not add navigation, sitemap or public links.

The source-only package has 72 files, created by
[the offline packager](../scripts/package-hosted-staging.php). Upload exactly
the generated folder contents, including both hidden `.htaccess` files.
The accompanying generated upload manifest enumerates every relative path.
The package includes the existing SDK/Composer and PHPMailer source/licences,
but no SMTP test CLI, temporary Turnstile harness, diagnostics, test runner,
private JSON, credentials, databases, logs, sessions or budget files.
Composer does not need to run on hosting for this artifact.

The shipped root rule is **Require all denied**. Its front-controller rewrite
allowlists routes and assets, and internal `app/.htaccess` denies source files.
The app copies retain layout/relative includes; packaging only adjusts the
few absolute public links to the isolated prefix. The real site is not rewritten.

## Hosted architecture and safeguards

- [Gateway](../__author-staging/gateway.php) alone sets the trusted hosted marker,
  after validating private enablement, exact HTTPS host, authenticated user,
  bounded fixed deadline, private files and stopped-writer declaration.
- [Hosted settings](../includes/submissions/hosted-staging.php) pin project
  `cutaojpqvz`, endpoint `cutaojpqvz.g2.sqlite.cloud:8860` and exact
  `author-submissions-staging.sqlite`; existing manifest, TLS identity,
  schema/checksum/FK/integrity/vector and original synthetic-record/key checks
  are reused. No migrations or new Cloud database are created.
- The old loopback server remains loopback-only. Existing local routes receive
  no hosted configuration or marker. Hosted fallback to local/offline or
  production databases is not allowed.
- The preserved staging key must decrypt the original baseline records and
  verify their HMACs. Do not generate a replacement key or edit the records to
  pass preflight. Reuse the original nonsecret baseline URL/proof.
- Only the fictional private staff SQLite fixture is used for login. Existing
  exact single-user checks require the staging reviewer actor. Its named session
  is distinct from real admin and local browser sessions. Real account credentials
  and real users are never copied or consulted.
- Server submission guards enforce [SYNTHETIC] titles, Invented author/submitter
  names, example.com email and blank websites. Authentication and naming rules
  cannot prove that every free-text pitch is fictional: approved testers must
  enter only invented content and no confidential information.
- The existing Managed widget uses booktoscreen.org/action author_submission,
  with real server Siteverify only on final submission. Dummy development keys
  cannot satisfy the hosted path. No replay diagnostics are exposed.
- All email is encrypted mock delivery, with no configurable SMTP switch.
  Fictional reviewer login is additionally required to open the mock mailbox.
  CSRF, rate limits, email expiration/consumption/resend and duplicate rules
  remain unchanged. A failure latches staging stopped; do not reset or retry
  until reviewed and separately approved.

## Private files: manual preparation, no secrets in chat

Use a new private directory above public_html with 0700 directory / 0600 file
permissions, owned/readable/writable by the hosting PHP user. No symlinks or
public aliases. Confirm PHP open_basedir permits the directory.

Copy privately:

1. Original staging encryption-key JSON, unchanged.
2. Existing nonsecret staging identity manifest, unchanged.
3. A consistent backup of the existing fictional staff SQLite fixture, not the
   public site's staff DB. Do not copy an actively changing SQLite/WAL file.
4. Original `browser-established.json` proof, unchanged.
5. Existing private Managed widget JSON. This is separate from the key/connection.
6. New bounded `connection.json` with only `connection_string`, entered privately
   from the existing staging connection configuration. Never use production.
7. Empty dedicated `sessions` directory. Mailbox/cache/stop latch are created
   privately during approved testing; do not copy historic mailbox/session tokens.

Create `hosted-settings.json` with these exact fields. Example paths are
placeholders, not the operator's actual private directory:

```json
{
  "enabled": false,
  "deadline": 0,
  "user": "private-staging-tester",
  "writers_stopped": false,
  "key_file": "/home/ACCOUNT/private-author-staging/key.json",
  "staff_db": "/home/ACCOUNT/private-author-staging/staff.sqlite",
  "identity_manifest": "/home/ACCOUNT/private-author-staging/identity.json",
  "connection_file": "/home/ACCOUNT/private-author-staging/connection.json",
  "widget_config": "/home/ACCOUNT/private-author-staging/turnstile.json",
  "baseline_base_url": "http://127.0.0.1:8086/author-submissions"
}
```

Set baseline_base_url to the preserved local live browser's actual configured
URL; the example must not replace its real baseline. No new baseline is
silently established by HTTP. User must match the hosting authentication identity.
Do not add SMTP/password/recipient fields; unexpected settings are rejected.

## Namecheap checklist: separate deployment/activation approval required

1. Confirm the destination folder does not already contain unknown files.
   Upload/extract the reviewed package only; keep deny-all while configuring.
2. Use cPanel Directory Privacy for the **whole isolated folder**, one approved
   tester credential with private password storage. Preserve cPanel's auth
   block and internal app denial. Replace only root Require all denied with
   hosting-managed authenticated access after approval; never leave anonymous
   access. Keep rewrite-to-gateway rules.
3. Set the nonsecret pointer using the confirmed SetEnv-to-PHP mechanism:
   `B2S_HOSTED_STAGING_SETTINGS_FILE` = absolute private hosted-settings path.
   Add the directive only to this folder, not the live site/global PHP environment.
4. Require HTTPS redirect before the Basic-auth prompt; verified certificate,
   HTTPS=on, exact Host=booktoscreen.org and matching REMOTE_USER. The earlier
   Turnstile folder test does not independently verify these new directory rules.
   Do not spoof proxy headers or weaken PHP checks.
5. PHP 8.2-compatible code: PDO/pdo_sqlite for staff/mock state, curl/OpenSSL,
   sessions, JSON/hash/mbstring and functioning socket/stream TLS for Cloud.
   Confirm the Cloud SDK's outbound TCP port 8860 is allowed by Namecheap,
   trusted CA validation and accurate server time. This is a hosting blocker
   if egress is blocked; do not fall back to production/local databases.
6. Keep errors undisplayed; logs private, no POST/auth/cookie/environment dumps.
   Existing website phpinfo/debug endpoints must not expose this runtime.
7. While disabled, verify anonymous page, reviewer, mailbox and asset requests
   are challenged/denied. Authenticated requests remain disabled. Direct
   `app` helper/vendor/source paths and all unlisted paths must be denied.
8. Before activation, stop all local/staging Cloud browser writers; do not stop
   preserved servers or change records as part of offline preparation.
   Independently approve the hosted synthetic-write/Cloud/metadata/Turnstile
   activity, tester roster, window, records and removal owner.
9. Only then set writers_stopped=true, choose one fixed UNIX deadline at actual
   start (recommended 30 minutes; maximum 24 hours) and enabled=true.
   No public author-submission or production SMTP environment flags are set.

Activation will perform live staging preflight reads and fictional service
writes. Uploading or setting the private paths alone does not authorize it.
No live staging connection was opened during this preparation.

## Approved mock-first acceptance sequence

After separate approval:

- Open `https://booktoscreen.org/__author-staging/author-submissions/`.
- Complete three steps using entirely invented data; final Step 3 loads Managed
  Turnstile. Manual metadata is supported; real ISBN lookup is optional and
  must be corrected to meet fictional field-prefix rules.
- Sign in using the fictional reviewer at the folder's
  `author-submissions/staff-login.php`.
- Open `https://booktoscreen.org/__author-staging/mailbox.php` and follow the
  private encrypted mock verification link, then Confirm email.
- Review only staging records at the folder's `admin/submissions.php`; verify
  note/evaluation persistence, duplicates, resend/consumption and access denial
  after logout. Do not export mock links/screenshots containing tokens.
- Confirm the public website and real admin behavior are unchanged, no SMTP
  message was sent, and no other database was selected.
- Stop on a failure/stop latch. Preserve records/state; no automatic retries,
  migrations, new seed records, key replacements or stop-latch resets.

## Later one-shot hosted SMTP test: separately gated, not in Cloud portal

The operator selected a separate CLI fixture, not a browser SMTP exception.
The initial deployment includes PHPMailer but no SMTP sending endpoint, no
credentials and no CLI test tools. Cloud staging continues example.com/mock-only.

First confirm cPanel Terminal or SSH availability; if unavailable, **stop the
SMTP phase**, rather than expose a CLI script over HTTP. After successful mock
portal acceptance, separately approve private CLI tooling, a new local-only
verification fixture/key (not the staging key), the owned privately configured
Gmail recipient, exactly one send and a protected verification-only URL.

Reuse the existing Namecheap loader/mailer: hello@booktoscreen.org, SMTPS 465,
certificate and hostname validation, allowlisted recipient, exclusive one-shot
attempt marker, fail-closed delivery and no automatic retry. The existing
loopback CLI fixture cannot be uploaded unchanged as a hosted verification
experience: prepare/test a dedicated protected hosted verification adapter
under that later approval once hosting CLI is confirmed. This is an explicit
remaining prerequisite, not a silently enabled feature or claimed hosted test.

No SMTP credentials should be copied with this initial package. Their later
placement is separate private configuration outside public_html/Git. The Gmail
address must never be inserted into the Cloud synthetic submission records.
Approval of hosted fictional Cloud writes does not approve any email.

## Shutdown/removal and production exclusion

Set enabled=false; verify portal/reviewer/mailbox/assets unavailable. Close tabs,
restore root deny-all and revoke the temporary hosting account. Remove only
public_html/__author-staging; clear its pointer and dedicated hosted private
sessions/mock mailbox/cache/settings/stop state under approved cleanup.
Preserve the original staging key/manifest/staff fixture/baseline and existing
Cloud staging records. No automatic cleanup deletes Cloud submissions.

The isolated folder, hosted helper, packager and tests are excluded from
production Git archives. FTP/cPanel/other tooling needs an explicit allowlist;
never deploy the source checkout. This hosted staging exception does not close
production staff identity, grants/isolation, backup/recovery/key-rotation,
privacy/terms/retention or pilot-admission gates.

## Offline evidence and limitations

26 new hosted boundary/synthetic/mail guards, 146 package/dependency/default
denial checks and 841 existing recovery/SMTP/submission/anti-bot/reviewer/
staging-routing/metadata/browser checks passed: **1,013 checks**.
Pending PHP syntax and Git whitespace checks passed.
All provider/SMTP tests were mocked; source loading/denied gateway subprocesses
made no Cloud call. The hosted form also rendered offline with synthetic
bootstrap context, CSRF and correct widget copy; no secret output.
No Namecheap or live hosted submission/Cloud write was
performed. Apache rewrite/auth and enabled full hosted browser workflow require
hosting acceptance under separate approval; offline results are not those proofs.

## Temporary prerequisites and read-only connectivity checker

The new `hosting-check.php` is a temporary no-network page, not an activation
route. It requires the existing private settings to remain enabled=false,
deadline=0, writers_stopped=false. It reports fixed labels and PASS/FAIL only:
PHP/extensions/TLS streams, HTTPS, host, matching authenticated tester, private
configuration/key/widget compatibility, preserved baseline, fictional staff
fixture integrity and session-directory writability. It opens the staff SQLite
file read-only; no Cloud connection, session creation, widget load, email,
migration or DB modification occurs. It neither displays values nor logs errors
containing private data. FAIL does not identify which private field to expose.

For the next manual no-network check, upload only:

- `__author-staging/hosting-check.php` to the protected folder root.
- `includes/submissions/hosted-check.php` to its existing
  `app/includes/submissions/hosted-check.php`.

Do not overwrite the gateway or any cPanel authentication block. Add this one
temporary rewrite exception immediately before the existing catch-all:

```apache
RewriteRule ^hosting-check\.php$ - [END]
RewriteRule ^ gateway.php [END]
```

The first rule bypasses routing only, never Directory Privacy or HTTPS rules.
Keep nested app denial. Verify anonymous checker access returns 401, then use
the exact authenticated HTTPS hosting-check.php URL with no query parameters.
Record only PASS/FAIL labels. It cannot verify the HTTP-before-auth redirect
ordering or prove Cloud port egress/remote CA validity without a connection.
Confirm the former through hosting configuration/manual HTTP status inspection
without supplying Basic credentials over HTTP.

After collecting the no-network results, delete the temporary root checker,
remove only its exception rule and confirm the checker URL is
unavailable. The ordinary 72-file packager excludes both checker files.
Retain the shared helper until any connectivity checker using it is removed.
No changes to disabled settings, sessions or stop latch are required.

The separate CLI connectivity tool is source preparation only and must not be
uploaded into a public directory. Terminal/SSH availability still needs
confirmation. Its explicit one-shot mode reserves a new private attempt marker
before using the existing validated staging session helper. It issues exactly
seven reads: GET DATABASE NAME, GET USER, PRAGMA database_list, SELECT
sqlite_version(), PRAGMA foreign_keys, PRAGMA query_only and PRAGMA journal_mode.
No PRAGMA assignment, transaction, schema/data write, migration or table-content
query is used. It verifies expected identity/settings and disconnects, with
only PASS/FAIL output and no retry. Server credential read-only grants are not
proven by these reads; the SDK authenticates and selects the existing DB with
create=false, TLS/certificate verification enabled and bounded timeouts.

No browser live-connect switch is included in the prerequisite page.
The separate browser one-shot adapter below is prepared for hosting without
Terminal/SSH. Do not expose the CLI tool or enable the portal to compensate.

Nine new checker tests and 26 existing hosted-boundary checks passed, with
syntax/whitespace. Full private compatibility used synthetic local files;
connectivity used only a mocked SDK verifying seven-command/read-only scope.
Hosted PHP/auth/config results remain pending the operator's no-network check.
Any actual staging connection needs separate approval after that evidence.

### Investigating cascading prerequisite failures

The original checker initializes compatibility, baseline, staff and session
results to false and calls the combined configuration loader first. If that
loader throws, the latter three are not executed. Four FAIL labels therefore
do not establish four defects. The exact failing hosted stage remains unknown.

The settings/HTTPS/tester PASS results establish that PHP can read/validate
the settings JSON and its directory. They do not establish access to the
separately referenced files or sessions. Candidates include mistyped paths,
per-file ownership/readability, open_basedir restrictions, symlinks/private
path policy, JSON/BOM/schema/target mismatch, and restrictive-mode mismatch.
Original local files passed earlier checks, not the hosted copies.

The locally enhanced temporary checker independently probes key, staff,
manifest, connection, widget, baseline and sessions for expected object
existence, PHP readability, restrictive permissions, owner match if available,
symlink policy, private path policy and session writability. It reports whether
a filesystem probe actually observed an open_basedir denial; PASS on this
label does not prove availability when another failure prevents resolution.
It reports fixed validation-stage labels with PASS/FAIL/NOT CHECKED so a
connection-format failure is distinguishable from unreadable files and later
unexecuted key checks. Stage observation is optional and changes no normal
loader decision or network behavior.

No paths, owner identifiers, mode values, private contents, hashes or
exception messages are emitted. Ownership information is a diagnostic clue,
not permission to broaden modes or require unnecessary ownership changes.
Keep 0700/0600 and existing protections. Provider calls, writes and session
creation are absent. Thirteen checker tests and 26 hosted-boundary checks
passed plus syntax/whitespace, including missing file, incompatible JSON and
no secret/path output. Full OS ownership/open_basedir behavior on Namecheap
is not certified offline.

The operator uploaded the enhanced checker and confirmed private-file access.
Manifest-stage diagnostics then isolated transferred migration-source newline
changes. The byte-preserving repair below restored the exact approved checksums;
the operator reports all primary no-network prerequisites now PASS. Preserve
disabled settings until the separately authorized acceptance window.

When the first failure is `Manifest format and staging identity`, the temporary
helper also reports fixed manifest subchecks: size, UTF-8/BOM, JSON/depth,
top-level and nested fields, existing identity/declaration validators, pinned
target, approved scope, migration versions/checksums and the complete original
load/plan. No field values or hashes are displayed. All checks on the preserved
local identity manifest passed; its bytes were unchanged.

This stage includes source approval checks, not only JSON validation.
The approved migration hashes currently depend on CRLF inside multiline SQL
statements (with LF between statements). Offline conversion of those statement
line endings to LF fails both approved hashes; restoring statement CRLF matches
version 1. Version 2 additionally has generated LF fragments, so blanket CRLF
restoration is not a valid test for that version.
The diagnostic reports both variants without altering source or relaxing
checksum approval. Version 1 exact checksum FAIL with restored-statement-CRLF PASS is
evidence of source line-ending conversion, not a reason to edit identity.json.
The operator subsequently reported exact checksum failures with the version 1
restoration probe passing, followed by all primary checks passing after repair.

Only hosted-check.php has changed for these additional rows; an upload of that
helper alone requires operator approval. Keep the existing protected checker
page, settings and all permissions unchanged. No hosted changes were made while
preparing this diagnosis. Thirty checker tests include malformed JSON, BOM,
structure, typed port, target/declaration/scope mismatch, sanitized output and
source newline transformations. No migration or remote connection was run.

### Byte-preserving migration-source repair

The original external staging folder and original upload ZIP were compared
byte-for-byte against the local migrations.php: both matched. Packaging reads
and writes that file without text substitution or newline conversion.
The hosted diagnostic (all manifest checks PASS, both exact checksums FAIL,
version 1 restored-CRLF PASS) is consistent with source newline conversion
after packaging. FileZilla ASCII transfer is a candidate; the specific transfer
mode or other hosted editor cannot be proven from these results alone.

Both approved migration versions are defined/generated in the single
app/includes/submissions/migrations.php file. Replace only that file with
the byte-identical original using FileZilla **Transfer > Transfer type > Binary**.
Do not edit it in cPanel or re-save it in an editor. Do not upload the full
package or replace staging-migration.php, identity.json, settings, access rules
or database files. Preserve permissions and disabled settings.

Packaging now checks exact migration-source byte parity and both immutable
approval checksums before reporting success; package regression tests check
the same invariants. No checksum constant, SQL definition or schema history
was changed. A separate one-file repair folder was prepared outside Git.
After the operator's binary upload, reload the protected no-network checker:
both exact checksums and the complete migration plan must PASS. Later stages
may reveal separate prerequisites; do not activate or connect on that basis.

### Temporary browser-only one-shot connectivity test

When Terminal/SSH is unavailable, the optional connectivity-check.php endpoint
uses the existing hosted-check.php helper. Neither is shipped by the normal
packager. Upload these two files only after operator approval:

- `__author-staging/connectivity-check.php` to the protected folder root.
- `includes/submissions/hosted-check.php` to its `app/includes/submissions/` folder.

Keep cPanel Directory Privacy and all existing rewrite/access rules. Before
the catch-all gateway rule, add only:

```apache
RewriteRule ^connectivity-check\.php$ - [END]
```

The endpoint requires exact HTTPS/host/Directory Privacy identity and disabled
settings. GET never connects and only offers a confirmation form when a
separate private `connectivity-approval.json` beside hosted-settings.json has
exactly `{"approved":true,"deadline":<integer Unix timestamp>}` with a deadline
in the next 15 minutes. Prepare it initially as
`{"approved":false,"deadline":0}`, outside public_html, permissions 0600.
Do not change hosted-settings.json to authorize this test.

Separate live approval must explicitly cover one TLS connection from Namecheap
to the existing staging database, authentication/database selection, the seven
previously listed reads, and creation of the private one-attempt marker.
After that approval, the operator sets the private approval flag/deadline,
opens the authenticated HTTPS endpoint, and types
`APPROVE ONE STAGING READ ONLY CONNECTION` before submitting once.
POST requires the exact same-origin Origin, same-origin Fetch Metadata when
present, a bounded URL-encoded form and that exact confirmation.
The private approval window is rechecked on POST. No sessions/cookies or
provider widget are created by the endpoint.

The shared CLI/browser marker is created exclusively before connecting and
restricted to 0600. A connection failure still consumes the attempt; double
submissions/refreshes cannot reconnect. Do not delete/reset the marker or retry
without new approval. The marker is a deliberate local write, not a database
write. TLS verification and 5-second connect/10-second socket timeouts are
preserved; no schema/data reads or PRAGMA assignments are added. Admin
credentials remain write-capable; read-only is the allowlisted operation scope,
not a server-side grant assertion. Output/errors/logs contain fixed sanitized
labels only. A FAIL does not prove a connection was attempted.

Removal: first disable the private approval (approved=false, deadline=0), then
remove the temporary endpoint and its single rewrite exception. Remove the
private approval file; retain the marker. Keep the shared hosted-check.php
helper while the existing prerequisite checker uses it. Check the temporary
URL no longer serves a form/results and unauthenticated protected URLs still
require Directory Privacy. Keep the portal disabled throughout.

## Consolidated remaining operator batches

Do not repeat completed disabled-state checks or upload the full package again.

1. **Connectivity:** upload the temporary browser endpoint and shared helper
   together in FileZilla Binary mode, add its routing exception, and prepare the
   private approval file. After explicit live authorization, give the approval
   a ten-minute deadline and submit the confirmation once. Require all four
   results PASS; then revoke that approval. On any unexpected failure, stop
   without resetting the attempt marker.
2. **Conditional acceptance:** only after connectivity passes and hosted
   workflow authorization covers the next window, stop competing staging Cloud
   writers without deleting fixtures. Set writers_stopped=true, enabled=true
   and a fixed deadline 30 minutes ahead together. Use manual metadata initially,
   at most two new entirely fictional submissions, mock verification, Managed
   Turnstile and reviewer updates on those new records only. Do not modify
   preserved baseline submissions. After testing restore enabled=false,
   deadline=0, writers_stopped=false and remove temporary connectivity access
   as described above.

One explicit authorization may cover both batches, conditional on connectivity
passing. It must include the one connection/marker and the bounded hosted
preflight connections, fictional writes, mock verification and Turnstile calls.
New live-access scope, unexpected failures, security concerns or destructive
operations require stopping, not another diagnostic or automatic retry.
Copilot can prepare source, run offline checks, interpret sanitized results and
operate shared browser pages within that scope. The operator handles hosting
uploads/configuration, credentials, stopping competing writers and interactive
CAPTCHA. Nothing in committing these source files deploys or activates hosting.
