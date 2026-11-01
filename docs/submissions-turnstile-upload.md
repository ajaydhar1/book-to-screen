# Self-contained temporary Turnstile upload

Originally prepared offline. The operator subsequently completed and removed
the separately approved hosted challenge test; see the
[Milestone 4C completion review](#milestone-4c-completion-review).
This source/runbook is not approval to redeploy or reactivate. No production
database/email operation or public author activation is part of this milestone.
This guide supersedes the split public-directory/helper upload layout for the
temporary test only. The existing live website needs no new global includes.

## Upload artifact

The supplied `__turnstile-validation` folder contains exactly ten files:

```text
__turnstile-validation/
    index.php
    challenge.js
    .htaccess
    includes/
        .htaccess
        submissions/
            turnstile-challenge.php
            turnstile-config.php
            private-config.php
            antibot.php
            security.php
        database/
            Database.php
```

Upload/extract **only this folder** into the confirmed Namecheap `public_html`,
so the page is exactly:
`https://booktoscreen.org/__turnstile-validation/index.php`.
Do not upload the parent build directory, test files, private session folder,
settings, widget keys or logs. If that target folder already exists, stop rather
than overwrite an unknown deployment. Include hidden `.htaccess` files.

The packaging-only PHP change is the page's relative require path, now
`__DIR__ . '/includes/submissions/turnstile-challenge.php'`. All six bundled
dependencies and JavaScript are byte-identical to the reviewed source; the helper
tree has an additional `Require all denied` rule. `Database.php` is only an
interface declaration: no adapter, connection, database file, Composer, SMTP,
submission service or reviewer dependency is bundled.

The package is a source snapshot. If the original harness/helpers change, rebuild
and run [the parity/package regression](../tests/submissions-turnstile-package-test.php)
before uploading. Do not hand-maintain a divergent production verifier.

The result display now immediately clears a previous result with numbered,
test-labelled progress, then displays the new HTTP status and server message.
Widget callbacks use a separate status area. Controls are disabled while a
request is pending or after an unsafe outcome. Reuse retains exactly the token
from the last successful fresh verification; rejected fresh tokens do not replace
it. This offline correction does not establish the inconclusive live reuse result.

## Private configuration above public_html

After separate hosting/setup approval, prepare an account-private directory such
as `/home/ACCOUNT/private-turnstile-validation` (replace ACCOUNT privately).
No symlinks or public directory aliases. Use 0700 directories, 0600 files and
ownership readable/writable by the hosting PHP account.

1. Copy the existing completed widget JSON privately to `turnstile.json`.
   Keep its secret separate; do not paste it into PHP, JavaScript, .htaccess or chat.
   Confirm canonical hostname `booktoscreen.org`, Managed mode, pre-clearance off
   and action `author_submission`. The site key is public in rendered HTML;
   the secret never is.
2. Create a dedicated `sessions` directory.
3. Exclusively create `budget.txt` containing **exactly `0` with no newline**.
   Do not recreate/reset it to evade the shared 12-attempt allowance.
4. Create `validation-settings.json`, initially disabled:

```json
{
  "enabled": false,
  "user": "temporary-tester",
  "deadline": 0,
  "widget_config": "/home/ACCOUNT/private-turnstile-validation/turnstile.json",
  "sessions": "/home/ACCOUNT/private-turnstile-validation/sessions",
  "budget": "/home/ACCOUNT/private-turnstile-validation/budget.txt"
}
```

Set one trusted per-endpoint hosting variable:
`B2S_TURNSTILE_VALIDATION_SETTINGS_FILE` = the absolute private settings path.
Namecheap must confirm how the selected PHP handler supplies this to `getenv()`;
do not assume cPanel/Apache SetEnv propagation works. No six-variable fallback
exists, and no request input may select the configuration.

The packaged guard excludes the package directory and actual document root.
With `public_html` as DOCUMENT_ROOT, all secrets/state must remain outside
public_html, including sibling application paths. Local template paths cannot
be used as hosted paths. The existing local private JSON is retained and was
not read or copied into this source-only artifact.

## Access restrictions and hosting checks

- Shipped root `.htaccess` is **deny-all**, protecting page and JavaScript.
  After deployment approval, use cPanel Directory Privacy to replace only the
  root denial with hosting-managed authenticated access for one temporary tester.
  Protect the entire folder. Keep `includes/.htaccess` deny-all unchanged even
  for authenticated users; PHP requires can still read those files internally.
- Confirm cPanel's actual rules challenge/deny anonymous requests and PHP receives
  reliable authenticated `REMOTE_USER`, exactly matching the private `user`.
  The extra PHP user check is retained. If missing, stop; never spoof a header
  or remove the check as a workaround.
- Require a trusted certificate and HTTP-to-HTTPS redirection **before** a Basic
  authentication prompt; PHP must see trusted `HTTPS=on`. Exact Host must be
  `booktoscreen.org`, not www or a port-suffixed alias. No proxy-header bypass.
- PHP must support the tested 8.2-compatible code, curl, OpenSSL, sessions,
  reliable file locks/private writable state and accurate time. Confirm trusted
  CA bundle and HTTPS egress without sending unauthorized test requests.
- Confirm `.htaccess` AllowOverride/AuthConfig support and nested helper denial.
  Do not use PHP's built-in development server as evidence for Apache restrictions.
- Keep display_errors off, logs private, no request-body/auth-cookie/environment
  dumps or public diagnostics. Do not inject secret values into the environment;
  only the nonsecret private settings path is needed.

These are required Namecheap acceptance checks, not inferred from the successful
local Apache tests. If any fails, stop without weakening the gates.

## Approved live test sequence

Do not activate merely by uploading. First obtain separate authorization for
exact test URL, temporary tester, key placement, provider/browser activity,
fixed deadline and removal owner. Confirm no other test writers/instances use
this budget or session directory.

Verify denied/default-disabled behavior and direct helper URLs before activation.
For the approved window set `enabled: true` and `deadline` to a **fixed integer
UNIX timestamp about 30 minutes ahead**, never a sliding timestamp. The code denies
missing/expired or more-than-one-hour-ahead deadlines; no public intake setting
is touched. Directory Privacy remains enabled throughout.

1. Visit the exact index.php URL using the temporary authentication prompt.
2. Complete a fresh real widget token, click **Validate current token** and expect
   accepted hostname/action/freshness. Server Siteverify uses verified TLS.
3. Click **Test last submitted token again** and expect rejection.
4. Test missing token (local rejection, no Siteverify call) and synthetic-invalid
   token (provider rejection).
5. Generate a fresh token, hold it **unused** with the expiry button, wait at least
   301 seconds and submit the held token; expect rejection. Do not confuse this
   with submitting an already used token.
6. Stop on auth/TLS/provider/network/state failures. Never automatically retry,
   reload to repeat an ambiguous attempt or replenish the budget. Record only
   sanitized pass/fail and count; no token/secret/identifying request data.

Maximum 12 shared attempted Siteverify calls, including failures. This does not
limit the widget's own internal Cloudflare browser requests; approve that activity
too. CSRF, exact context, bounded JSON, Secure/HttpOnly/SameSite session cookie,
no-store/referrer/CSP protections and manual-only retries remain unchanged.
Only opaque session/CSRF state and an attempt count persist; ordinary hosting/auth
and Cloudflare can process network/browser identity signals under their policies.

## Removal: no live website dependencies

1. Set private `enabled: false` (or clear the path variable) and restore root
   deny-all. Verify the tester is denied. Deadline expiry is a fallback, not removal.
2. Revoke the temporary Directory Privacy account/credential entry using cPanel.
3. Delete **only `public_html/__turnstile-validation`**, including its bundled
   helper tree, after resolving that exact path. No global includes were uploaded.
   Verify the page, JavaScript and helpers are unavailable.
4. Clear the one test-only hosting path variable. Remove only its private settings,
   budget and dedicated session files/directory, following approved logging TTL.
5. Remove the test-only hosted widget JSON copy; preserve the original private
   existing widget configuration and widget itself for future approved work.
6. Confirm the live website and public-disabled author workflow are unchanged.

No removal command is run automatically; do not delete public_html or unrelated
accounts, sessions, keys, includes or application directories.

## Original packaging offline evidence

- 34 packaged isolation/parity checks: only relative page include changed,
  helper parity, missing/default/disabled/expired/wrong-user/CSRF denial, synthetic
  rendering without secret or DB/mail runtime, public-path rejection and mocked
  fresh/reused validation.
- Existing challenge 69, challenge JS 13, config 43 and anti-bot 80 checks passed.
- **11 actual loopback Apache/PHP HTTP checks**: six helper PHP files plus nested
  rules denied, missing settings returned 404, packaged JavaScript available in
  the test-only root-override phase, and shipped root deny-all blocked both
  page and JavaScript. Local Apache configuration never used real keys.
- **250 checks total.** Packaged PHP syntax, JavaScript syntax and Git whitespace
  checks passed. Temporary Apache processes/log/config were removed; no existing
  live server or preserved key/fixture was changed.

The upload artifact contains exactly ten source/rules files, no private JSON,
keys, sessions, budget, logs, DBs or email contents. Tests use synthetic values
and mocked Siteverify only. No cloud/production connection was needed.

### Reuse-display correction regression

After the first operator-reported live fresh verification, reuse remained
inconclusive. The local display correction passed 23 mocked JavaScript checks,
34 package parity/isolation, 69 challenge, 43 configuration and 80 anti-bot
checks (249 total), plus PHP/JavaScript syntax and Git whitespace checks.
No new live provider calls or hosted budget changes occurred. The existing
live helper-denial evidence is unchanged; Apache checks were not rerun for
this JavaScript/markup-only correction.

### Reported duplicate live acceptance: unresolved, launch blocked

The operator subsequently reported distinct HTTP 200 results for a fresh token
and explicit reuse. This is not considered successful single-use validation.
The hosted harness must remain disabled pending investigation; public intake
must not be enabled. No further live request is authorized by this report.

Offline evidence confirms identical JSON bytes for fresh/reuse despite a new
widget callback, unchanged session state, and a new cURL POST for every
verification. The actual HTTP-client code was exercised with cURL functions
mocked in an isolated PHP process without the cURL extension: decoded
`response` equals the input token, request fields are only secret/response,
there is no idempotency key, and TLS/hostname validation remains enabled.
Two valid mocked provider successes yield two HTTP 200 results; a mocked
`timeout-or-duplicate` yields HTTP 422. Boolean success parsing, hostname,
action and timestamp failures remain fail-closed.

These tests establish source behavior, not what the deployed code or provider
actually returned. Hosted source/version parity, intermediary behavior and
actual Siteverify responses remain unverified. No raw tokens or provider
responses were logged or collected. Existing replay tests simulate provider
single-use; they do not independently enforce it.

The challenge success message now says "Provider accepted token; expected
hostname, action and timestamp confirmed", not "fresh token". The UI explicitly
flags a reused-token HTTP 200 as UNEXPECTED REPLAY ACCEPTANCE and stops further
requests. No local replay filter was added: it would mask the provider anomaly
in this diagnostic. Provider-independent atomic shared token-digest claims
would be a separate production protection needing reviewed persistence and
concurrency/expiry tests, not an established root-cause fix.

The latest correction passed 26 JavaScript, 36 actual-client replay, 34 package,
69 challenge and 80 anti-bot checks (245 total), plus syntax/whitespace checks.
The local package includes these source corrections; nothing was deployed and
no hosted attempt budget or environment was reset.

### Sanitized request/response diagnostic panel

Prepared offline only; the hosted harness remains disabled. The current local
package adds a labelled panel for each CSRF-valid verification attempt. It
reports a server-sequential number/type, equality with the preceding token
that reached the Siteverify call, a test-scoped HMAC-SHA256 identifier, whether
the existing cURL call was attempted, its HTTP status, parsed boolean success,
allowlisted documented error codes, expected context/timestamp flags and final
application decision. Missing/invalid JSON or access/CSRF rejection is not a
verification attempt and may have no panel data.

Correlation is confined to the existing authenticated PHP session and fixed
deadline. PHP holds the session lock through verification so parallel requests
cannot reorder the comparison. Only the random private HMAC key, digest,
sequence and deadline persist in the private session; no raw token persists.
The key is not returned. A new approved fixed window changes diagnostic scope,
not the shared budget. Comparisons use constant-time digest equality; the
actual-client regression separately confirms that form encoding forwards the
input bytes unchanged. This is cryptographic correlation, not a raw-token dump.

An optional observer instruments the existing shared verifier/client; only the
temporary entry point supplies it. Production routes do not request, receive
or render diagnostics. No second client/provider request is used. Without the
observer, existing failure behavior remains unchanged. A non-200 provider
response can have parsed boolean success reported but is still rejected;
unavailable/unparseable values are NOT OBSERVED, never inferred as false.
Request-made means cURL execution was attempted, not proof the provider received
it; transport failures may have no HTTP response.

Only these error codes are emitted: missing-input-secret, invalid-input-secret,
missing-input-response, invalid-input-response, bad-request, timeout-or-duplicate,
internal-error. Unknown codes and all other provider fields are excluded.
No full responses, token bytes, secrets, usernames, paths, cookies, IPs or
session identifiers are displayed/logged. Provider success is not independent
proof of single-use; a reused-token HTTP 200 still flags the anomaly and stops.
Test types are allowlisted client labels, not security assertions.

Latest offline validation: 27 JavaScript, 12 diagnostic-state, 49 actual-client
replay, 34 package, 69 challenge, 43 configuration and 80 anti-bot checks,
**314 total**, plus PHP/JavaScript syntax and whitespace. All services are mocked;
synthetic fixtures only. No budget reset, live network, deployment or activation.

#### Exact hosted file updates, only after separate approval

Update these four files from the refreshed self-contained package, keeping the
entry point's packaged relative require path:

- `__turnstile-validation/index.php`
- `__turnstile-validation/challenge.js`
- `__turnstile-validation/includes/submissions/turnstile-challenge.php`
- `__turnstile-validation/includes/submissions/antibot.php`

Do not replace either hosting access-rule file, upload test scripts, alter
private settings paths, reset sessions/budget, or update global website helpers.
The JSON request now requires token plus an allowlisted test_type, so PHP and
JavaScript must be updated together while disabled. Missing diagnostic output
stops the UI rather than silently continuing with an older hosted version.

#### Proposed bounded live diagnostic, not authorized by this document

Obtain explicit approval for these four file updates and exactly **two**
additional Siteverify attempts on the existing budget: one fresh and one reuse.
Confirm at least two attempts remain by reading the count privately, not resetting
it. Preserve authentication, helper denial and private state. Choose one fixed
30-minute deadline at actual start, then enable only this temporary endpoint.

Use one tab and the exact HTTPS index.php URL. Submit a fresh token once; wait
for the completed panel, not just the widget callback. Expect request-made YES,
HTTP 200, success true, context flags PASS and decision ACCEPT. The first token
is normally NOT COMPARABLE in the new diagnostic window.

Then click reuse once and wait for its numbered panel. Expect the same identifier,
same-token YES and request-made YES. If HTTP 200/success false/duplicate code and
decision REJECT, provider rejection is established for this pair. If success true
and ACCEPT again, preserve only the sanitized fields and treat single-use as
unresolved: do not attempt a third call. If same-token NO, request-made NO,
missing diagnostics, network/config failure or an unknown outcome, stop without
retry/reload. Do not inspect/export request bodies or full network responses.

Immediately set enabled false, confirm endpoint denial, close the tab and complete
the removal procedure above. Retain the existing widget privately, not this test
deployment. No public submissions, database or mail activation is part of approval.

## Milestone 4C completion review

November 1, 2026. **GO for the reviewed source-only milestone commit;
NO-GO for production deployment, restricted pilot or public intake.**
The following live evidence is operator-reported, not independently rechecked
by this offline review:

| Controlled live result | Evidence reported |
|---|---|
| Existing Managed widget | Loaded successfully on booktoscreen.org |
| Fresh token | Siteverify boolean success=true; hostname, action and timestamp checks passed; application accepted |
| Identical reused token | Server-side comparison confirmed identical token; Siteverify success=false with timeout-or-duplicate; application rejected |
| Shutdown/removal | enabled=false; temporary public folder deleted; its URL returned HTTP 404 |

The earlier distinctly labelled double-acceptance results remain **unexplained**.
Do not characterize them as a stale-message issue, a known Cloudflare bug, or
a fixed verifier defect. Offline tests show identical request-token forwarding,
no cached response, no reused idempotency keys and strict boolean/context checks.
The final observed test pair demonstrates the expected provider rejection; it
does not reconstruct the earlier provider responses or certify every future
request. Missing/invalid/expired/context/provider failures are covered offline;
no additional live negative results are claimed without operator evidence.
There is no independent persistent production replay store.

The transient harness had no author submission, database, SMTP or reviewer
functionality. Existing production author routes and local/staging mail defaults
remain disabled/mock, with no production DB/schema/migration change. This review
made no live provider calls, production connections, deployment, mail sends,
commits or pushes and did not access/reset hosted budget/session state.

### Commit versus deployment inventory

Commit all 23 reviewed pending source/documentation paths as one 4C scope:

- `.gitattributes`, `README.md`.
- `docs/submissions-production-readiness.md`, `docs/submissions-turnstile.md`,
  `docs/submissions-turnstile-validation.md`, `docs/submissions-turnstile-upload.md`.
- `includes/submissions/antibot.php`, `private-config.php`, `smtp-test.php`,
  `turnstile-config.php`, `turnstile-challenge.php`.
- `scripts/check-submissions-turnstile-config.php`.
- `__turnstile-validation/.htaccess`, `index.php`, `challenge.js`.
- `tests/submissions-pilot-readiness-test.php` and the seven
  `tests/submissions-turnstile-*` PHP/JavaScript regressions.

The SMTP change is only the previously reviewed extraction/delegation of its
private-path guard, tightly coupled to the Turnstile loader. No unrelated pending
changes were identified, and no changes are staged by this review.

**Source version control is not a production upload allowlist.**
Commit the temporary source/tests for reproducibility, but exclude these paths
from every production artifact:

- Entire `__turnstile-validation` tree (page, JavaScript, diagnostics and rules).
- `includes/submissions/turnstile-challenge.php`, used only by that harness.
- `scripts/check-submissions-turnstile-config.php`, an offline CLI preparation tool.
- Entire `tests` tree, including fictional staff/Cloud/mail fixtures.

The new `.gitattributes` applies `export-ignore` to those paths for `git archive`.
It does not constrain Git checkout, cPanel File Manager, FTP/rsync, Composer
packaging or third-party deployment tools. Those methods require an explicit
reviewed allowlist/exclusion list and post-upload denied-route checks. Never
deploy the source checkout or the challenge-upload ZIP wholesale. Git archives
alone also do not audit unrelated pre-existing sensitive/diagnostic site files:
the production-readiness deployment audit remains a launch prerequisite.

The committed harness `.htaccess` is deny-all, not cPanel credentials/rules.
Even if copied inadvertently, PHP has no fallback settings path and requires
explicit private enablement, an unexpired fixed deadline no more than one hour
ahead, trusted HTTPS, exact host/path/authenticated user and CSRF before requests.
Never copy hosted authentication rules/private runtime settings into source.
The verifier observer is opt-in; author routes have no diagnostic wiring/panel.
No production route consumes the temporary environment pointer.

Do not commit/upload private widget/settings/SMTP JSON, keys, recipient addresses,
tokens, session files, budget, DBs, logs, email contents, the generated upload ZIP,
the external one-off hosting diagnostic or local Apache fixtures. All are outside
the reviewed repository changes. Original private widget/key custody is retained;
hosted private-state cleanup and temporary credential/environment revocation,
beyond the reported public-folder removal, still need operator confirmation.

### Completion regression evidence

Twenty-one offline suites passed **1,303 checks**: Turnstile deployment 15,
diagnostic 12, actual-client replay 49, package 34, challenge 69, config 43,
pilot recovery 20, SMTP 133, submissions/email verification 191, anti-bot 80,
reviewer 124, staging preparation 37, config 18, diagnostic 137, migration 96,
synthetic services 52, browser guards 56, routing 12, ISBN metadata 75,
challenge JavaScript 27 and intake JavaScript 23.

All 14 pending PHP and two pending JavaScript files passed syntax checks;
Git whitespace and pending-file trailing-whitespace checks passed. Tests used
synthetic local SQLite, mock Cloud/SMTP/metadata/Siteverify/browser APIs and
one self-contained loopback routing test, not retained live environments.
No full live/offline browser submission fixture suite or Namecheap Apache
request was rerun during this completion review; prior HTTP/access evidence
is historical. Pre-existing duplicate PHP-extension warnings remain.

The pending-file privacy audit compared configured private widget keys and
SMTP password/approved recipient in-process without printing their values:
zero matches across all 23 pending files. No real machine/account paths,
private key blocks, private JSON, generated logs/DBs/session/email artifacts
or trailing whitespace were found; example paths and token/key literals are
explicit placeholders or synthetic test data.

An actual Git archive of the reviewed current tree was tested using a separate
temporary index: the requested temporary-source/test paths were omitted,
leaving only the control README file and empty ancestor directories. The real
Git index was hash-checked unchanged; temporary ZIP/index were removed. This
validates export-ignore behavior, not a full production artifact audit.

### Remaining launch blockers

The [production readiness assessment](submissions-production-readiness.md)
remains authoritative: real staff identity across separate DBs; database-scoped
grants/restore isolation; monitored off-host backups and restoration;
production-only encryption-key custody/recovery and safe rotation policy;
approved privacy/submission terms, retention/deletion and pilot admission.
Production bootstrap, secret injection, SMTP quotas/bounces/authentication
headers, deployment allowlist/HTTPS and final bounded pilot approval also remain.
Turnstile success alone does not close these gates.

Suggested commit subject:
`Complete Milestone 4C Turnstile validation and production-readiness safeguards`
