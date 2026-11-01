# Existing Managed Turnstile widget: private preparation

November 1, 2026. Reuse the operator's existing Managed widget for
`booktoscreen.org`; do not create another widget. Initial preparation was offline.
The operator subsequently completed the separately approved temporary live test;
see the [completion record](submissions-turnstile-upload.md#milestone-4c-completion-review).
Public submissions remain disabled; the test folder was disabled and removed.

## Compatibility

The existing [verifier](../includes/submissions/antibot.php) and
[browser widget](../assets/js/submission-intake.js) are compatible with:

- Canonical URL: `https://booktoscreen.org/author-submissions/`.
- Exact hostname: `booktoscreen.org`.
- Action: `author_submission`, supplied by the widget's render configuration,
  not necessarily a dashboard setting.
- Managed mode: a Cloudflare dashboard setting, not a JavaScript flag.
- Pre-clearance: off; this workflow uses token validation, not clearance cookies.

The operator reports hostname and Managed mode are already configured.
This is configuration compatibility, **not independent proof of widget ownership,
key pairing, host restrictions or live Siteverify validity**. Confirm the existing
widget's hostname list excludes localhost/unintended hosts and pre-clearance is off.
Do not change the widget or create credentials as part of this offline task.

## Enter privately

A blank-key JSON template was prepared outside Git and the web directory in
the session's private `turnstile-preparation` directory. It has a current-user-only
Windows ACL. Open it locally, not in chat, and enter only:

1. `site_key`: the **Site key of the existing widget**.
2. `secret_key`: the matching **Secret key of that same widget**.

Leave canonical URL, hostname, action, `widget_mode: "managed"` and
`pre_clearance: false` unchanged after confirming the dashboard settings.
Do not substitute the SMTP password, Gmail address, staging encryption key or
official dummy keys. Do not paste secrets into commands, reports or source control.

```json
{
  "base_url": "https://booktoscreen.org/author-submissions/",
  "hostname": "booktoscreen.org",
  "action": "author_submission",
  "widget_mode": "managed",
  "pre_clearance": false,
  "site_key": "",
  "secret_key": ""
}
```

The site key is intentionally public when ultimately rendered into HTML; the
secret never is. Keeping both privately configured avoids hardcoding operational
values, but does not make the rendered site key confidential.

Protect the directory/files with 0700/0600 on Unix or current-user/application-user-only
ACLs on Windows. The checker enforces outside-parent-web-root/document-root paths
and Unix file permissions; Windows ACLs require separate operator verification.
No private material belongs below `public_html`, including Git-ignored files.
Avoid public diagnostics such as phpinfo before injecting any environment secrets.

## Offline validation only

```powershell
php -d display_errors=0 scripts\check-submissions-turnstile-config.php `
  'C:\private-turnstile\turnstile.json'
php -d display_errors=0 tests\submissions-turnstile-config-test.php
```

Replace only the path with the existing private template location. Do not supply
keys as arguments. Exit 2 means missing keys; exit 1 means invalid/private-path
configuration; exit 0 means **offline-compatible**, not an accepted live widget.
Output never prints either key. Exceptions are sanitized.

The [loader](../includes/submissions/turnstile-config.php) validates exact bounded
JSON fields, canonical values and key syntax, rejects official development keys
in either field, and reuses the production verifier's constructor/base-URL checks.
It **does not set environment variables, call verify, load the browser widget,
enable production routes or connect anywhere**. Even privately filling both keys
does not activate them. Current local/staging browser configuration remains unchanged.

The existing SMTP private-path logic was extracted unchanged into a small
[shared helper](../includes/submissions/private-config.php); the SMTP wrapper and
regression behavior remain intact. This avoids importing SMTP/DB services merely
to inspect Turnstile settings. The preparation loader is CLI-only deliberately:
future HTTP injection needs the separately approved production bootstrap, not a
weakening of this guard.

Planned future mapping, not performed:

| Private field | Existing production environment |
|---|---|
| site_key | `B2S_TURNSTILE_PROD_SITE_KEY` |
| secret_key | `B2S_TURNSTILE_PROD_SECRET` |
| hostname | `B2S_TURNSTILE_PROD_HOSTNAME` |
| action | `B2S_TURNSTILE_PROD_ACTION` |

`B2S_TURNSTILE_ENV=production` belongs only in a separately reviewed/approved
runtime configuration. Current routes remain loopback-gated; this is no activation switch.

## Controlled live validation: separate approval and harness required

The [challenge-only harness preparation guide](submissions-turnstile-validation.md)
now lists exact files, private settings, authentication, budgets and cleanup.
Its source ships deny-all/default-disabled. The separately deployed temporary
copy was subsequently removed; it must not be included in production deployments.

The hostname-restricted widget cannot be honestly tested by renaming localhost.
Do not add localhost, disable hostname validation or use a development sentinel
to simulate a real production credential test.

Before a live test, agree and implement offline a **challenge-only** restricted
page on the canonical HTTPS origin, protected by an independent tester-access
boundary. It must have no intake, SMTP, production DB or reviewer dependency;
it must not replace/enable the existing `/author-submissions/` routes. Coordinate
an exact dedicated test path and explicit deployment/configuration approval.
Managed-widget validation will itself contact Cloudflare from the browser.

The challenge-only backend should reuse `SubmissionTurnstile`, enforce CSRF,
request bounds and a small tester budget, and return sanitized pass/fail only.
Inject the existing keys privately for that test alone. Verify TLS/CA, CSP,
clock accuracy and HTTPS first; do not log tokens or secret, and use no request-body
logging. Plan expiry/reuse tests within a bounded number of widget/Siteverify calls.

Test evidence:

1. Existing Managed widget renders on approved `booktoscreen.org` test page.
2. Fresh token passes verified-HTTPS Siteverify with exact `booktoscreen.org`,
   action `author_submission` and a fresh timestamp.
3. Missing/invalid tokens fail; the same used token fails as spent; an expired
   token fails. Provider outage/config failure fails closed, tested offline where
   deterministic live failures are unsafe.
4. Wrong action/context and official development keys are rejected by offline
   regressions; do not broaden real-widget hostname scope to create a negative test.
5. Error/timeout/expiry feedback and explicit fresh-token retry are accessible;
   no challenge during ISBN or intermediate form steps in the eventual intake.
6. Remove/disable the challenge-only endpoint/configuration after the approved
   window. No submission record or real email is created by the widget test.

Required approval should name the existing widget, exact HTTPS test path/access
boundary, key injection scope, tester(s), test window and maximum provider calls,
and authorize deployment of **only that challenge-only harness**. A separate
final production/pilot activation approval remains required afterwards.

No production author-submission real-key wiring was deployed or activated.
[Production readiness blockers](submissions-production-readiness.md)
for authentication, scoped DB grants, recovery, privacy and admission still apply.

## Offline results and files

**355 checks passed**: private config 43, SMTP 133, anti-bot 80, disabled intake/
local recovery 20, intake JavaScript 23, staging browser guards 56.
All five new/affected PHP files passed syntax checks; whitespace checks passed.
The actual blank template correctly returned incomplete (exit 2), with its
current-user-only ACL verified. Mocked Siteverify tests include canonical
context and spent-token responses; existing suites cover hostname/action mismatch,
expiry, provider errors and development-key rejection. No live Cloudflare call.

Changes:

- New [shared private path helper](../includes/submissions/private-config.php).
- Modified [SMTP preparation helper](../includes/submissions/smtp-test.php), delegating
  the unchanged private-path boundary.
- New [Turnstile preparation loader](../includes/submissions/turnstile-config.php).
- New [sanitized CLI checker](../scripts/check-submissions-turnstile-config.php).
- New [offline configuration tests](../tests/submissions-turnstile-config-test.php).
- This guide; links added to README/readiness report.
- Outside Git: blank-key private template; no credentials created or copied.
