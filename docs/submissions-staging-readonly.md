# First SQLite Cloud staging inspection: preparation only

Approved target names for the diagnostic are `book-to-screen-staging` and
`author-submissions-staging.sqlite`. The user reports a Free / Development /
US East project. The first separately approved read-only connection confirmed the
selected database/admin user and FK ON after operator-confirmed project/endpoint
mapping. It found the empty SQLite-Vector metadata pair. Credential scope and
transactional migration behavior remain unverified. No reconnect was performed
while implementing the metadata refinement.

## Credential support: what is known

SQLite Cloud's current public documentation describes:

- [Users, database-scoped READ roles and privileges](https://docs.sqlitecloud.io/docs/security).
- [CREATE USER with database-restricted roles](https://docs.sqlitecloud.io/docs/user-commands)
  and [GRANT ROLE](https://docs.sqlitecloud.io/docs/role-commands).
- [PHP username/password and API-key connections](https://docs.sqlitecloud.io/docs/sdk-php-methods).
- [Database API keys associated with a username](https://docs.sqlitecloud.io/docs/api-key-commands).
  The documented API-key `RESTRICTION` option is unused; do not treat that option
  as a read-only grant. Never use `LIST APIKEYS` for this diagnostic: it exposes keys.

Thus the platform documents restricted database users compatible with the PHP
method. Availability through the current Free plan/UI is **not confirmed**.
Ask SQLite Cloud support whether this project supports a user with READ scoped
only to this database, how to provision it, and which read-only PRAGMAs it can
query. Do not create users/roles or grant privileges during this connection test.

CloudSync client API keys are not assumed to be interchangeable with database
authentication. This diagnostic accepts username/password for a restricted user,
or a known **database admin connection** API key under the explicit staging-admin
risk acknowledgement. It does not support an unverified CloudSync key.
It does not discover, enumerate or empirically test grants.

Important limitation: [GET DATABASE NAME](https://docs.sqlitecloud.io/docs/database-commands)
is documented as requiring **HOSTADMIN**. A READ user may be denied that operation
and PRAGMA reads. In that case this diagnostic STOPS; it does not fall back to admin
or silently substitute a weaker identity check. Do not grant broad HOSTADMIN merely
to make it pass. Obtain a provider-supported low-privilege identity alternative or
separately approve the narrowly scoped staging-admin inspection.

An admin credential remains capable of writes, grants and other administrative
operations. A fixed read-only command sequence is not server-enforced least
privilege. The risk must be expressly accepted for this isolated project only.

## Configuration and identity evidence

Use an access-restricted nonsecret JSON diagnostic manifest outside the repository.
Do not put credentials in this file. Example below uses **fictional** endpoints/IDs;
replace them with values checked in the control panel. Do not read production
connection secrets to fill it: use the existing nonsecret production inventory.

```json
{
  "identity": {
    "environment": "staging",
    "project_id": "replace-with-staging-project-id",
    "hostname": "staging.example.com",
    "port": 8860,
    "database": "author-submissions-staging.sqlite",
    "production_project_id": "replace-with-production-project-id",
    "production_hostname": "production.example.com",
    "production_database": "adaptations.sqlite",
    "synthetic_only": true
  },
  "project_name": "book-to-screen-staging",
  "control_panel_identity_confirmed": true,
  "credential_mode": "staging-admin",
  "expected_user": "admin",
  "admin_risk_approved": false
}
```

The example intentionally refuses to proceed with admin credentials until
`admin_risk_approved` is explicitly set true after authorization. For a provider-
confirmed restricted user use `restricted-user`, its exact username, and false.
Project IDs, endpoints and database names must all differ from production.
Confirm the complete production endpoint/alias inventory separately.
`control_panel_identity_confirmed` is an operator attestation, not a query result.
Do not set it without checking the project's endpoint and database together.

Privately inject **only** `B2S_SUBMISSIONS_STAGING_READONLY_URL` into the process
environment. Never paste secrets into chat or a shell command that records history.
Do not use the application's `config.local.php`, `get_db()` or generic Cloud
connection variable. Missing staging configuration fails; there is no fallback.
No submission encryption key is needed for schema inspection.

The URL must use `sqlitecloud://`, the exact approved endpoint/port, and
`/author-submissions-staging.sqlite`. Credentials are either percent-encoded
username/password userinfo or a single `apikey` query argument, never both.
No other URL options, fragments, alternative/nested paths or insecure TLS settings
are accepted. An absent port means the documented 8860 default.
The SDK interpolates authentication tokens into its command protocol; whitespace,
quotes, semicolons, control characters and other unapproved token characters are
rejected even when percent-encoded. If a genuine credential is incompatible, stop
for an approved driver/protocol solution rather than weakening validation.

The connector sets SDK properties directly instead of calling its permissive
URL-option parser. It keeps TLS certificate verification enabled, no database
creation/memory mode/weaker consistency, 5-second connect and 10-second socket
timeouts. It uses the SDK directly because the application's statement adapter
exposes rowsets, while GET DATABASE NAME and GET USER return scalar strings.
The existing database adapters and local HTTP routes are unchanged.
AUTH and USE DATABASE are the configured implicit connection commands. No
PRAGMA/SET settings changes, migrations, DDL/DML, transactions, grant operations,
database enumeration, credential listing or fallback connections are issued.

Provider errors/warnings are suppressed and replaced by fixed-phase errors, not
SDK error bodies/traces. The CLI disables PHP error display/logging during the
diagnostic. Run with `-d display_errors=0` to also avoid unrelated PHP startup
warnings in JSON stdout. Reports omit URLs, credentials, schema SQL and row data.
Protect reports too: they may contain nonsecret schema object names.

## Exact diagnostic checks

1. Validate manifest/URL/approval locally before any socket opens.
2. Authenticate/select the configured database using the documented PHP client.
3. Require `GET DATABASE NAME` to equal the exact database and `GET USER` to equal
   the expected database user. Denials or unexpected response shapes stop.
4. Inspect `PRAGMA database_list`: exactly one main database, matching filename;
   no non-main/non-temp attachments. Empty/unknown filenames stop. File paths
   are not reported.
5. Read `sqlite_version()`, `PRAGMA foreign_keys`, `PRAGMA query_only` and
   `PRAGMA journal_mode`, without arguments that change values. Validate response
   shapes. FK OFF is explicitly warned about, never changed.
6. Read at most 101 schema objects from `main.sqlite_schema`. Over 100 stops.
   No objects means the database **appears** empty. The only exception is the
   exact empty SQLite-Vector metadata pair described below. Every other object
   or unexpected record yields `STOP_UNEXPECTED_SCHEMA`.
7. For ordinary tables only, safely quoted bounded `SELECT EXISTS` queries report
   whether records exist, never payloads/count-all scans. Views, virtual tables
   and SQLite internal tables are not executed. Unsafe/unrecognized schema names
   or metadata stop rather than being guessed, logged or cleaned.
8. Disconnect; output sanitized JSON. No writes or cleanup are performed.

### Narrow SQLite-Vector metadata exception

The [official definition](https://github.com/sqliteai/sqlite-vector/blob/00c2ccece3687380c4ceff14b15d033573ee5c89/src/sqlite-vector.c#L129)
specifies `_sqliteai_vector` with columns in order: `tblname TEXT`, `colname TEXT`,
`key TEXT`, `value ANY`, and `PRIMARY KEY(tblname, colname, key)`.
The [extension initialization](https://github.com/sqliteai/sqlite-vector/blob/00c2ccece3687380c4ceff14b15d033573ee5c89/src/sqlite-vector.c#L3981)
creates it. It is ordinary extension-managed metadata, not protected SQLite core
storage. The classifier matches the complete SQL (keyword case/whitespace,
optional IF NOT EXISTS and trailing semicolon only); changed types/columns/PK,
additional constraints/comments/options or unrecognized equivalent definitions
are not guessed or silently accepted.

Both the table and exact `sqlite_autoindex__sqliteai_vector_1` must be present.
The index must have type `index`, owning `tbl_name = _sqliteai_vector`, and
`sql = NULL`, consistent with [SQLite automatic-index metadata](https://www.sqlite.org/schematab.html).
The table's `tbl_name` must also equal its name. The table must have no records.
The pair remains visible as `classification = known_extension_metadata`.
Unknown objects, extra indexes/triggers, missing pair members, mismatched metadata
or populated metadata still stop. There is no prefix-wide ignore list.

Successful inspection reports `no_application_schema_data = true` and
`schema_assessment = No application schema/data`. `appears_empty` remains false
when the metadata pair exists: it does not claim the schema is completely empty.
The diagnostic performs no writes to these objects. It is a pre-migration
inspector; post-migration checks require a separate exact application-schema validator.
See [the controlled migration procedure](submissions-staging-migrations.md).

The TLS hostname plus control-panel endpoint mapping supplies project identity
evidence. `GET DATABASE NAME`, `GET USER` and `database_list` confirm the selected
database/session, **not** a globally unique project identity or restricted grants.
No documented stable project-ID query was established in this preparation.
An admin connection's permissions are explicitly **unverified/write-capable**.
Schema inspection is point-in-time, not a locked snapshot: stop other staging
writers and independently confirm isolation before testing.

## Offline testing

```powershell
php -d display_errors=0 tests\submissions-staging-test.php
php -d display_errors=0 tests\submissions-staging-diagnostic-test.php
# Requires a confirmed nonsecret external manifest; opens no socket:
php -d display_errors=0 scripts\inspect-submissions-staging.php `
  'C:\private-staging\diagnostic-identity.json' --offline
```

Tests use mock SDK responses only: no Cloud calls, databases, migrations or mail.
The diagnostic tests include exact/missing/altered/populated metadata pairs,
unknown prefix tables and extra indexes/triggers. They do not reconnect to Cloud.
Current results: 137 mocked diagnostic checks, 37 offline preparation checks and
18 private-config checks passed. Changed PHP syntax and Git whitespace checks passed.
They cover exact names/endpoint matching, dangerous URL options/auth injection,
both documented auth methods, approval/missing-config/no-fallback gates, empty
and unexpected schema/records, skipped views/virtual tables, denied identity,
malformed rowsets, FK settings, warning/error redaction and disconnect.
Synthetic temporary JSON files are removed after CLI tests.

## Read-only run procedure (additional runs require approval)

### Private local file option (offline setup)

The CLI-only `scripts/submissions-staging.local.php` file is Git-ignored and has
been created empty for you to edit. It returns 404 for non-CLI requests; the
scripts directory also has Apache deny rules. Protect it with current-user-only
filesystem permissions. It is never loaded by public application routes.
Do not use a static web server that serves PHP source. Environment injection
outside the web root remains an alternative for higher isolation.

1. Open the ignored file in your editor privately (not chat). Paste the complete
   staging Connect-panel URL on the empty line between the `STAGING_CONNECTION`
   nowdoc delimiters. Do not alter the delimiters or add leading/trailing spaces.
   Leave `expected_hostname` empty initially. No key/password escaping is needed
   inside this nowdoc.
2. Run **only** the offline checker:

```powershell
php -d display_errors=0 scripts\check-submissions-staging-config.php
```

3. It displays only project, hostname, port, database and offline state flags.
   Independently reopen the **book-to-screen-staging** project's Connect panel,
   ensure the selected database is **author-submissions-staging.sqlite**, and
   compare the reported hostname to the host portion of that panel's URL:
   after `sqlitecloud://` (and any `username:password@` userinfo) and before
   `:port`, `/database`, or `?`. Do not compare with a production Connect panel
   or assume the project name must occur in the hostname. Record the nonsecret
   project ID/host mapping from that staging panel in the external manifest.
4. Enter ONLY that independently confirmed hostname in `expected_hostname`;
   rerun the checker. A mismatch stops; do not automatically copy the extracted
   hostname without independent comparison. Successful setup reports
   `CONFIGURED_AWAITING_SEPARATE_APPROVAL`, not proven isolation.
5. Check `git check-ignore scripts\submissions-staging.local.php`; never force-add
   this file or share its contents. The checker accepts no secrets in arguments,
   loads no production config, performs no DNS/network/SQL, and suppresses config
   parse errors/output. If the URL lacks the exact database path or has unsafe
   options, stop and fix it privately using the documented URL format.
6. Stop and request explicit approval for the read-only diagnostic. Do not run
   the live flag or set the approval variable during setup.

Once separately authorized, the existing diagnostic accepts an explicit
`--local-config` after `--connect-readonly`, with the same external manifest and
approval environment gate. It requires the confirmed local host/port to match
that manifest and never falls back to the environment URL if the file fails.
There is no automatic connection after the offline checker succeeds.

1. Confirm actual project ID, endpoint/port, database, expected database user,
   isolation from all production endpoints/aliases and intended credential type in
   the control panel. Confirm no production data was copied.
2. Resolve restricted-user availability with the provider. If using staging admin,
   obtain explicit approval for its additional risk. Do not expose the credential
   to the assistant; inject it privately into a dedicated local shell process.
3. Prepare the external manifest with confirmed mapping and the appropriate risk
   acknowledgement; run the offline preflight first.
4. Obtain separate authorization stating the exact endpoint/project/database,
   credential mode, and **only this read-only diagnostic**. No grants/provisioning,
   migrations, settings changes or writes are included.
5. After that authorization, privately set the dedicated staging URL environment
   variable, then set the nonsecret approval value and run:

```powershell
$env:B2S_SUBMISSIONS_STAGING_READONLY_APPROVAL = 'book-to-screen-staging/author-submissions-staging.sqlite/read-only'
php -d display_errors=0 scripts\inspect-submissions-staging.php `
  'C:\private-staging\diagnostic-identity.json' --connect-readonly
```

6. Exit code 0 means read-only checks completed with no application schema/data,
   **not** migration readiness or proven read-only grants. Code 2 means unexpected
   schema: stop. Code 1 means configuration/identity/transport failure: stop.
   Never retry with production or broader credentials automatically.
7. Remove the dedicated URL and approval environment variables from that shell.
   Share only the sanitized report. Any migrations, permission changes or writes
   need their own approval.

The live flag and approval environment value are deliberate operator safeguards,
not an authorization service or proof that approval was actually granted.
The reusable connector is CLI-test infrastructure, not wired into HTTP routes.
