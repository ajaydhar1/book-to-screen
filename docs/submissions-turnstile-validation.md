# Restricted challenge-only Turnstile validation harness

Originally prepared offline November 1, 2026. The operator subsequently
completed the separately approved live challenge test and disabled/removed the
hosted folder; see the [completion record](submissions-turnstile-upload.md#milestone-4c-completion-review).
The instructions below are historical/reusable preparation, not authorization
to restore the endpoint. Reuse the existing Managed widget. This is not
public intake and does not close production authentication/database launch gates.

## Exact proposed test URL and files

For Book to Screen directly in Namecheap public_html, use the
[self-contained upload package](submissions-turnstile-upload.md). It keeps all
helper copies beneath the temporary folder and supersedes the split upload
layout below for the live test. Source layout and test semantics remain unchanged.

`https://booktoscreen.org/__turnstile-validation/index.php`

Public test directory, after separate deployment approval:

- [index.php](../__turnstile-validation/index.php): authenticated/gated page and
  bounded CSRF-protected JSON validation endpoint.
- [challenge.js](../__turnstile-validation/challenge.js): manual widget tests,
  in-memory tokens, accessible messages, no automatic validation retries.
- [.htaccess](../__turnstile-validation/.htaccess): **deny-all by default**.
  Do not remove that denial without approved hosting-managed authentication.

Required nonpublic implementation dependencies:

- [turnstile-challenge.php](../includes/submissions/turnstile-challenge.php)
- [turnstile-config.php](../includes/submissions/turnstile-config.php)
- [private-config.php](../includes/submissions/private-config.php)
- [antibot.php](../includes/submissions/antibot.php)
- [security.php](../includes/submissions/security.php)
- [Database.php](../includes/database/Database.php), interface declaration only.

The existing security helper declares DB-related helpers/types, but the harness
never constructs an adapter, invokes those helpers, queries a database or loads
mail, submission services, reviewer routes, Composer or production config.
Keep include files denied to direct HTTP access by the audited hosting rules.
Do not deploy the whole repository or private session folder for this test.

The previous private configuration checker remains CLI-only. Its **pure value
validator** was extracted for shared use; a separate runtime reader is called only
after this endpoint's access gate. No existing intake bootstrap or environment
factory consumes this reader.
The shared private-path helper preserves its original stricter parent-directory
guard for CLI/SMTP preparation. The gated hosted harness explicitly excludes the
application and actual document roots, but allows private files under the account's
parent directory above `public_html`. Rejecting that entire parent account would
otherwise make normal cPanel private storage unusable. It still rejects web-root
files/symlinks and requires restrictive permissions; no arbitrary HTTP path input
selects these files.

## Hosting prerequisites and access boundary

Confirm PHP 8.2-compatible runtime, curl/OpenSSL/session, trusted CA bundle, accurate
clock, outbound verified HTTPS to Siteverify and private writable file/session
storage. Confirm the web SAPI can receive private environment values. No SMTP,
Cloud credentials, database, mail sender or staff authentication is required.

Use cPanel Directory Privacy/Apache Basic authentication with **one approved
temporary tester identity**, a strong privately configured password and private
password-file storage. Authentication must protect **the entire test directory,
including JavaScript**, before content is served. Replace the shipped deny-all
rule with those hosting-managed rules only under the deployment approval.
Verify unauthorized HTTP requests are challenged/denied by Apache; PHP additionally
requires `REMOTE_USER` to equal the configured tester. Do not accept an HTTP header
as authentication or forward an untrusted header into `REMOTE_USER`.

Some hosting PHP handlers do not expose authenticated `REMOTE_USER`. Check that
explicitly before activation; if unavailable, stop and revise the authentication
integration offline. Do not remove the PHP user gate as a workaround.

PHP requires `HTTPS=on`, exact Host `booktoscreen.org` and exact script path.
It intentionally does **not** trust X-Forwarded-Proto/Host. If the hosting proxy
does not reliably set HTTPS, confirm a trusted server integration separately;
do not add client-header exceptions. Visit the full explicit index.php URL.
No testing hostname, redirects, TLS exceptions, localhost widget additions or
production route rewrite is needed.

CSP permits only local script, Cloudflare script/frame and same-origin fetch,
with frame denial, no-store, no-referrer, nosniff and noindex. No analytics or
external fonts/images are included. Basic authentication uses HTTPS; session
cookies are Secure, HttpOnly, SameSite=Strict and scoped to the temporary path.

## Private runtime setup, not performed

Use the already completed widget JSON above public_html/outside Git, with
current-user/application-user-only permissions. Do not enter the keys again into
source, command arguments or the temporary page. Validate the **server's** private
copy/permissions during a separately approved setup. A local JSON path cannot
serve as the hosted server's path.

Prepare outside the web root:

1. Existing compatible widget JSON (site key and matching secret).
2. Dedicated private session directory, 0700.
3. Dedicated shared budget text file, 0600, containing **exactly `0` without a
   newline**, exclusively created for the approved test. It stores only a count,
   not tokens, IPs, names, results or timestamps. Do not reset it to evade limits.
4. Hosting-managed auth password file; no password in PHP configuration.

Configure **one** server-side path setting for this endpoint:
`B2S_TURNSTILE_VALIDATION_SETTINGS_FILE`, the absolute path to a private JSON
settings file above public_html. This replaces all six previous variables;
legacy variables are ignored and cannot activate the page. The pointer contains
no secret and must be supplied by trusted hosting configuration, never a request
header/query parameter. Do not set `enabled: true` before live approval.

Example private settings file (placeholder account paths, not a deployable template):

```json
{
  "enabled": false,
  "user": "temporary-tester",
  "deadline": 0,
  "widget_config": "/home/CPANEL_ACCOUNT/private-turnstile/turnstile.json",
  "sessions": "/home/CPANEL_ACCOUNT/private-turnstile/sessions",
  "budget": "/home/CPANEL_ACCOUNT/private-turnstile/budget.txt"
}
```

Use actual absolute hosted paths. `enabled` must be a JSON boolean; `deadline`
must be a fixed integer UNIX timestamp, at most one hour ahead when enabled.
`user` must match Apache's authenticated `REMOTE_USER` exactly (letters, digits,
underscore, dot, @ and hyphen only, at most 100 characters). Directory Privacy
and this additional user check are both retained; neither is bypassed by loading
the private file. The widget site/secret keys stay in the separate `widget_config`
JSON and are not duplicated here. Unknown fields, including secret/transport
settings, are rejected.

Protect this settings file with 0600/current-application-user access. Missing pointer
means HTTP 404; malformed/missing/inaccessible settings produce a sanitized failure,
never fallback to legacy variables. Disabled, expired or wrong-user requests deny
before reading widget keys, starting a session or making any provider call.

No `B2S_TURNSTILE_ENV`, public-submission flag, local/staging mode, DB URL or mail
transport is set. Deadline checks occur on GET and POST and fail closed if
missing/expired/more than one hour in the future. The production-disabled intake
restrictions remain unchanged.

The application has a **shared maximum of 12 attempted Siteverify calls**, reserved
under a nonblocking exclusive file lock before each outbound call. Failure attempts
count; exhausted, busy/corrupt/unwritable budget state fails closed. Missing or
malformed tokens and invalid CSRF/JSON never call Siteverify. No automatic counter
reset, timeout resend, persistent tokens or provider-result cache exists.
The count limit covers backend Siteverify requests, **not the number of requests
made internally by Cloudflare's browser widget**; authorize that browser activity
separately. Page reload/session changes cannot replenish the shared budget.

## Live test steps after explicit approval

First verify denied/default-disabled behavior, HTTP-to-HTTPS handling, unauthenticated
directory protection, private files' HTTP denial and the exact trusted authenticated
user/HTTPS server variables. Then activate only the temporary endpoint within the
fixed window. Do not use existing author intake routes.

1. Sign in through the hosting-managed prompt, then open the exact test URL.
2. Complete the real Managed widget. Click **Validate current token**.
   Success means server-side verified HTTPS Siteverify returned success with
   hostname `booktoscreen.org`, action `author_submission` and a fresh timestamp.
3. Click **Test last submitted token again**. Expect rejection as spent.
4. Click **Test missing token**: expect local rejection with no provider call.
5. Click **Test synthetic invalid token**: expect rejection.
6. Get a fresh challenge; before sending it, click **Hold current token unused
   for expiry test**. The UI keeps it only in memory and requests a separate fresh
   challenge. Wait at least 301 seconds, then click **Test held token after five
   minutes**. This tests expiry of an unused token, not expiry confused with reuse.
7. Record sanitized results/call count only, never keys/tokens or identifying
   request data. Any provider/config/network failure or budget exhaustion tells
   the tester to stop; do not automatically retry/reload to repeat uncertain calls.
8. Stop/disable and remove the temporary endpoint as below.

Wrong-context, test-key rejection and provider failure paths are deterministic
offline tests. Do not modify the real widget's allowed hostnames or keys to
produce unsafe live negative cases. Managed mode/key ownership are operator
dashboard attestations until the bounded real test confirms key validity.

The PHP endpoint reports generic rejection; it deliberately does not display
provider diagnostics, raw response bodies, tokens or secret. A generic rejection
alone cannot distinguish expired from reused: use the controlled fresh/held
sequence above as the evidence. No author record or email is produced.

## Privacy, logging and removal

No personal-data form, application IP/email/name capture or DB persistence exists.
Only an opaque session/CSRF value and anonymous shared attempt count are stored.
The auth layer and ordinary hosting/Cloudflare processing **can** see account,
network and browser signals; this is not a claim of zero third-party personal-data
processing. Approve the temporary tester notice and hosting log policy.

Ensure hosting/APM does not log POST bodies, Authorization/cookies, environment
secrets or PHP variable dumps. Web logs may contain IP/auth usernames under the
host's policy; restrict access/retention and minimize those logs. Leave display_errors
off; direct PHP error logging to private storage or real stderr, not a public
relative filename. No phpinfo/debug endpoint should receive the secret environment.

Removal procedure (explicitly resolved paths, no broad deletion):

1. Set `enabled: false` in the private settings file (or clear the single path
   setting), restore directory deny-all, verify
   the page is denied even for the tester. Deadline is a fallback, not cleanup.
2. Remove only the three deployed temporary-directory files and the endpoint
   directory once empty; verify index.php and challenge.js are unavailable.
3. Revoke the temporary hosting auth identity, remove its credential file/entry
   using the hosting control panel, clear the single test-only path setting and
   remove only its private settings file.
4. Remove only the dedicated session files/directory and budget file after any
   approved sanitized count has been recorded. Do not remove unrelated sessions.
5. Remove the test-only hosted copy/reference of the secret; retain the original
   existing widget configuration privately for later approved integration.
   The widget itself is reused, not deleted or recreated automatically.
6. Confirm existing site/public-disabled submission behavior remains unchanged.

Source test files may remain in Git for review; production should not retain an
enabled or accessible live harness. No automatic deployment/cleanup script runs.

## Offline checks and approval

```powershell
php -d display_errors=0 tests\submissions-turnstile-challenge-test.php
node tests\submissions-turnstile-challenge-js-test.js
php -d display_errors=0 tests\submissions-turnstile-config-test.php
php -d display_errors=0 tests\submissions-antibot-test.php
```

Tests use synthetic keys and mocked Siteverify/widget/fetch; the default page is
rejected before credentials/widget/network are loaded. The real private key file
was not read or activated during harness preparation.

Original harness offline validation: **414 checks passed** (before the subsequent
single-file configuration regression expansion):

| Suite | Checks |
|---|---:|
| Challenge gates, bounded budget, provider outcomes, gated render/secret omission/CSRF | 46 |
| Challenge JavaScript manual fresh/reuse/unused expiry/stop controls | 13 |
| Private Turnstile configuration | 43 |
| SMTP/private-path compatibility | 133 |
| Anti-bot/context/expiry/provider errors | 80 |
| Default-disabled intake/local recovery | 20 |
| Existing staging browser safeguards | 56 |
| Existing intake JavaScript | 23 |

Five new/affected PHP files passed syntax checks, challenge JavaScript syntax and
Git whitespace checks passed. The active-render/CSRF tests use synthetic CLI
server-variable fixtures, not a real HTTPS/authenticated hosting server.
Apache Directory Privacy, real TLS/origin behavior, Managed widget validity and
live Siteverify remain controlled live acceptance checks. Expected mocked
provider-error logs and existing duplicate extension warnings are not failures.

Required approval statement, after hosting prerequisites and the offline artifact
are reviewed:

> I authorize deployment and temporary activation of only the challenge-only
> harness at `https://booktoscreen.org/__turnstile-validation/index.php`, protected
> by the approved hosting-managed tester authentication. Reuse the existing Managed
> `booktoscreen.org` widget with action `author_submission`, inject its keys only
> from private server configuration, and run the approved browser challenges plus
> at most 12 server-side Siteverify attempts within **[fixed test window/deadline]**.
> No database, email, intake or reviewer routes may be activated. Stop on unexpected
> authentication/TLS/provider/network failures without automatic retries. Disable
> and remove only this harness/auth/session/budget configuration afterward.

This approval is **not yet given**. Confirm exact hosting paths, temporary tester
identity, deadline, log policy and removal owner before it is requested. If hosting
inspection or private key upload requires live access, obtain that specific approval
first. Deployment, widget activity and Siteverify are live actions; no approval is
implied by an offline-compatible key check.

## Minimum Namecheap setup after approval

1. Confirm HTTPS redirects before authentication, trusted `HTTPS=on` and
   Directory Privacy delivering reliable `REMOTE_USER`. Keep both access gates;
   failure to expose the user is a blocker, not reason to remove it.
2. Protect the entire temporary directory using cPanel Directory Privacy and one
   private temporary login; do not replace shipped deny-all until approved.
3. Above public_html prepare the separate widget JSON, one disabled settings JSON,
   dedicated sessions directory and `0` budget file with restrictive permissions.
4. Confirm PHP curl/OpenSSL/session and CA/egress, then configure only
   `B2S_TURNSTILE_VALIDATION_SETTINGS_FILE` using the host's supported PHP-handler
   environment method. Do not assume Apache SetEnv automatically reaches getenv;
   confirm this with Namecheap. The simplified approach still needs one trusted
   path pointer, not six separate settings.
5. Deploy only the previously listed exact files/helpers under separate approval.
   Verify default-disabled, unauthorized and private-file denial before testing.
6. For the approved fixed window, set the exact user/deadline and `enabled: true`
   privately; run manual tests within the unchanged shared 12-call limit.
7. Disable immediately afterward and follow the targeted removal steps above.

No database/email/public route, verifier behavior, JavaScript controls, budget
size or auth boundary was changed by this configuration simplification.

Single-file simplification validation: **437 offline checks passed** (challenge
settings/gates 69, challenge JS 13, widget config 43, anti-bot 80, SMTP 133,
pilot/default-disabled recovery 20, staging guards 56, intake JS 23).
The three affected PHP files passed syntax and Git whitespace checks.
Regression cases include malformed/unknown settings, disabled/expired/wrong-user
denial before widget-key reads, ignored legacy activation variables, missing
settings failure, gated rendering without secret output and preserved CSRF/budget.

A disabled `validation-settings.json` template was created in the outside-Git
local widget-preparation directory. It references the existing separate widget
file but does not read/activate it. Its local sessions/budget paths are placeholders
for later preparation, not existing hosted state. Replace paths and the tester
identity privately for Namecheap; leave disabled/deadline zero until approval.
